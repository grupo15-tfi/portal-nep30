<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comprueba que las migraciones dejan la base igual al modelo v4.4 y que las
 * reglas que protege la propia base funcionan desde Laravel.
 * (Las 139 pruebas completas del modelo están en docs/base-de-datos/04_pruebas.sql.)
 */
class BaseDeDatosTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function contar(string $sql): int
    {
        return (int) DB::scalar($sql);
    }

    public function test_las_migraciones_crean_el_modelo_v44_completo(): void
    {
        $tablas = $this->contar("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'migrations'");
        $this->assertSame(34, $tablas, 'tablas');
        $this->assertSame(39, $this->contar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'), 'triggers');
        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'"), 'procedimientos');
        $this->assertSame(7, $this->contar('SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()'), 'vistas');
    }

    public function test_los_seeders_cargan_roles_catalogo_y_administrador(): void
    {
        $this->assertEqualsCanonicalizing(
            ['administrador', 'profesor', 'alumno', 'auditor'],
            DB::table('roles')->pluck('name')->all(),
        );
        $this->assertSame(12, DB::table('planes_estudio')->count());
        $this->assertTrue(User::where('email', 'admin@nep30.test')->first()->hasRole('administrador'));
    }

    public function test_sin_cupo_la_preinscripcion_queda_en_lista_de_espera(): void
    {
        // Los datos de prueba llenan Peluquería (cupo 2) y mandan dos aspirantes más.
        $this->assertSame(2, DB::table('v_lista_espera')->count());
        $this->assertSame(2, DB::table('preinscripciones')->where('estado', 'en_espera')->count());
    }

    public function test_la_base_rechaza_a_un_menor_de_edad(): void
    {
        $curso = DB::table('cursos')->value('id');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('no cumple la edad mínima');

        DB::table('preinscripciones')->insert([
            'curso_id' => $curso, 'dni' => '45000000', 'apellido' => 'Prueba', 'nombre' => 'Menor',
            'fecha_nacimiento' => now()->subYears(15)->toDateString(), 'email' => 'menor@nep30.test',
            'telefono' => '3704 222222', 'acepta_privacidad' => true,
        ]);
    }

    public function test_la_correccion_de_asistencia_queda_auditada(): void
    {
        $profesor = User::where('email', 'profesor@nep30.test')->firstOrFail();
        $asistencia = DB::table('asistencias')->where('estado', 'justificado')->first();

        DB::statement('SET @app_usuario_id = ?, @app_ip = ?', [$profesor->id, '127.0.0.1']);
        DB::table('asistencias')->where('id', $asistencia->id)->update(['estado' => 'presente']);

        $registro = DB::table('auditoria')->where('tabla', 'asistencias')->where('registro_id', $asistencia->id)->first();
        $this->assertNotNull($registro);
        $this->assertSame($profesor->id, (int) $registro->usuario_id);
        $this->assertSame('justificado', json_decode($registro->valores_anteriores)->estado);
        $this->assertSame('presente', json_decode($registro->valores_nuevos)->estado);
    }

    public function test_el_middleware_identifica_al_usuario_y_la_base_frena_al_auditor(): void
    {
        $auditor = User::where('email', 'auditor@nep30.test')->firstOrFail();

        $this->actingAs($auditor)->get('/')->assertOk();
        $this->assertSame($auditor->id, (int) DB::scalar('SELECT @app_usuario_id'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('El rol Auditor es de solo lectura');
        DB::table('asistencias')->where('id', '>', 0)->update(['estado' => 'ausente']);
    }
}
