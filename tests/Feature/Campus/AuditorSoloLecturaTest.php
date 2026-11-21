<?php

namespace Tests\Feature\Campus;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU18 (#131): el rol Auditor es de solo lectura también en la base.
 * Prueba el procedimiento exigir_escritura() y los triggers que lo llaman
 * (database/sql/triggers_v4.4.sql). No hay código nuevo: si alguna falla, se avisa en #128.
 *
 * @app_usuario_id lo pone el middleware ContextoAuditoria en cada pedido;
 * acá se pone a mano, igual que en BaseDeDatosTest.
 */
class AuditorSoloLecturaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function comoUsuario(?int $id): void
    {
        DB::statement('SET @app_usuario_id = ?', [$id]);
    }

    /** El cambio tiene que fallar con el mensaje de la base */
    private function assertSoloLectura(callable $cambio): void
    {
        try {
            $cambio();
            $this->fail('La base permitió un cambio del Auditor');
        } catch (QueryException $e) {
            $this->assertStringContainsString('El rol Auditor es de solo lectura', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        DB::statement('SET @app_usuario_id = NULL');
        parent::tearDown();
    }

    // ── El Auditor no puede cambiar nada ───────────────────

    public function test_el_auditor_no_borra_preinscripciones_ni_inscripciones(): void
    {
        $preinscripcion = DB::table('preinscripciones')->value('id');
        $inscripcion = DB::table('inscripciones')->value('id');
        $this->comoUsuario($this->usuario('auditor@nep30.test')->id);

        $this->assertSoloLectura(fn () => DB::table('preinscripciones')->where('id', $preinscripcion)->delete());
        $this->assertSoloLectura(fn () => DB::table('inscripciones')->where('id', $inscripcion)->delete());

        $this->assertDatabaseHas('preinscripciones', ['id' => $preinscripcion]);
        $this->assertDatabaseHas('inscripciones', ['id' => $inscripcion]);
    }

    public function test_el_auditor_no_borra_notas_ni_cambia_asistencias(): void
    {
        $nota = DB::table('evaluaciones')->value('id');
        $asistencia = DB::table('asistencias')->first();
        $this->comoUsuario($this->usuario('auditor@nep30.test')->id);

        $this->assertSoloLectura(fn () => DB::table('evaluaciones')->where('id', $nota)->delete());
        $this->assertSoloLectura(fn () => DB::table('asistencias')->where('id', $asistencia->id)->update(['estado' => 'ausente']));
        $this->assertSoloLectura(fn () => DB::table('asistencias')->where('id', $asistencia->id)->delete());

        $this->assertDatabaseHas('evaluaciones', ['id' => $nota]);
        $this->assertDatabaseHas('asistencias', ['id' => $asistencia->id, 'estado' => $asistencia->estado]);
    }

    public function test_el_auditor_no_crea_usuarios_ni_cambia_roles(): void
    {
        $alumno = $this->usuario('alumno@nep30.test');
        $this->comoUsuario($this->usuario('auditor@nep30.test')->id);

        $this->assertSoloLectura(fn () => User::create([
            'dni' => '39999999', 'apellido' => 'Prueba', 'nombre' => 'Nuevo',
            'email' => 'nuevo@nep30.test', 'password' => 'secreto123',
        ]));
        $this->assertSoloLectura(fn () => $alumno->assignRole('administrador'));
        $this->assertSoloLectura(fn () => $alumno->removeRole('alumno'));

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@nep30.test']);
        $this->assertTrue($alumno->fresh()->hasRole('alumno'));
        $this->assertFalse($alumno->fresh()->hasRole('administrador'));
    }

    // ── Cuándo sí se permite ───────────────────────────────

    public function test_un_auditor_que_tambien_es_administrador_puede_escribir(): void
    {
        $auditor = $this->usuario('auditor@nep30.test');
        $auditor->assignRole('administrador');            // con @app_usuario_id null (como un seeder)
        $preinscripcion = DB::table('preinscripciones')
            ->whereNotIn('id', DB::table('inscripciones')->whereNotNull('preinscripcion_id')->select('preinscripcion_id'))
            ->value('id');

        $this->comoUsuario($auditor->id);
        DB::table('preinscripciones')->where('id', $preinscripcion)->delete();

        $this->assertDatabaseMissing('preinscripciones', ['id' => $preinscripcion]);
    }

    public function test_sin_usuario_logueado_se_permite(): void
    {
        $this->comoUsuario(null);                          // consola, seeders, tareas programadas

        $nuevo = User::create([
            'dni' => '39999998', 'apellido' => 'Prueba', 'nombre' => 'Consola',
            'email' => 'consola@nep30.test', 'password' => 'secreto123',
        ]);
        $nuevo->assignRole('alumno');

        $this->assertTrue($nuevo->fresh()->hasRole('alumno'));
    }
}