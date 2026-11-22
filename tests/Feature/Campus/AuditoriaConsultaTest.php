<?php

namespace Tests\Feature\Campus;

use App\Actions\Notas\GuardarNota;
use App\Actions\Preinscripciones\AprobarPreinscripcion;
use App\Actions\Preinscripciones\Preinscribir;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\User;
use App\Queries\RegistroDeAuditoria;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * HU16 (#122): la consulta del registro de auditoría y la cobertura de los triggers.
 * @app_usuario_id lo pone el middleware ContextoAuditoria en cada pedido; acá se pone a mano.
 * Los renglones de prueba de los filtros se cargan en marzo de 2026, lejos de los del seeder (hoy).
 */
class AuditoriaConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function tearDown(): void
    {
        DB::statement('SET @app_usuario_id = NULL, @app_ip = NULL');
        parent::tearDown();
    }

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function comoUsuario(?User $usuario): void
    {
        DB::statement('SET @app_usuario_id = ?, @app_ip = ?', [$usuario?->id, '127.0.0.1']);
    }

    /** Un renglón de auditoría hecho a mano (insertar sí se puede; editar o borrar, no) */
    private function registro(array $datos): int
    {
        return DB::table('auditoria')->insertGetId([
            'usuario_id' => null, 'accion' => 'modificacion', 'tabla' => 'users', 'registro_id' => 1,
            'valores_anteriores' => null, 'valores_nuevos' => null, 'ip' => '127.0.0.1',
            ...$datos,
        ]);
    }

    /** El último renglón que dejó un trigger para ese registro */
    private function ultimo(string $tabla, int $id): array
    {
        $fila = RegistroDeAuditoria::buscar(['tabla' => $tabla])->getCollection()
            ->firstWhere('registro_id', $id);
        $this->assertNotNull($fila, "No quedó registrado el cambio en {$tabla} #{$id}");

        return $fila;
    }

    private function cambio(array $fila, string $campo): ?array
    {
        return collect($fila['cambios'])->firstWhere('campo', $campo);
    }

    // ── Búsqueda ───────────────────────────────────────────

    public function test_sin_filtros_devuelve_todo_paginado_de_a_25_y_lo_mas_reciente_primero(): void
    {
        foreach (range(1, 30) as $i) {
            $this->registro(['created_at' => '2026-03-01 10:00:00']);
        }
        $nuevo = $this->registro(['created_at' => now()->addMinute()]);

        $pagina = RegistroDeAuditoria::buscar([]);

        $this->assertSame(25, $pagina->perPage());
        $this->assertCount(25, $pagina->items());
        $this->assertSame(DB::table('auditoria')->count(), $pagina->total());
        $this->assertSame($nuevo, $pagina->items()[0]['id']);
        $fechas = collect($pagina->items())->pluck('fecha');
        $this->assertEquals($fechas->sortDesc()->values(), $fechas->values());
    }

    public function test_cada_filtro_funciona_solo_y_combinado(): void
    {
        $this->comoUsuario(null);
        $gomez = User::create(['dni' => '41111111', 'apellido' => 'Gómez', 'nombre' => 'Ana',
            'email' => 'gomez@nep30.test', 'password' => 'secreto123']);

        $a = $this->registro(['usuario_id' => $gomez->id, 'tabla' => 'evaluaciones', 'accion' => 'alta', 'created_at' => '2026-03-02 09:00:00']);
        $b = $this->registro(['usuario_id' => $gomez->id, 'tabla' => 'users', 'accion' => 'baja', 'created_at' => '2026-03-05 18:30:00']);
        $c = $this->registro(['usuario_id' => null, 'tabla' => 'evaluaciones', 'accion' => 'alta', 'created_at' => '2026-03-05 20:00:00']);

        $ids = fn (array $f) => collect(RegistroDeAuditoria::buscar(['desde' => '2026-03-01', 'hasta' => '2026-03-31', ...$f])->items())
            ->pluck('id')->sort()->values()->all();

        $this->assertSame([$a, $c], $ids(['tabla' => 'evaluaciones']));
        $this->assertSame([$b], $ids(['accion' => 'baja']));
        $this->assertSame([$a, $b], $ids(['usuario' => '4111']));       // DNI (empieza con)
        $this->assertSame([$a, $b], $ids(['usuario' => 'Góm']));        // apellido (empieza con)
        $this->assertSame([$b, $c], $ids(['desde' => '2026-03-05', 'hasta' => '2026-03-05']));  // el día completo
        $this->assertSame([$a], $ids(['tabla' => 'evaluaciones', 'usuario' => 'Gómez']));
        $this->assertSame([], $ids(['tabla' => 'users', 'accion' => 'alta']));
    }

    public function test_cambios_trae_solo_lo_que_cambio_y_oculta_contrasenas_y_tokens(): void
    {
        $cambios = RegistroDeAuditoria::cambios(
            json_encode(['apellido' => 'Pérez', 'nombre' => 'Ana', 'password' => 'viejo', 'remember_token' => 'abc']),
            json_encode(['apellido' => 'Gómez', 'nombre' => 'Ana', 'password' => 'nuevo', 'remember_token' => 'xyz']),
        );

        $this->assertSame([['campo' => 'apellido', 'antes' => 'Pérez', 'despues' => 'Gómez']], $cambios);

        // En un alta no hay valores anteriores
        $this->assertSame([['campo' => 'nota', 'antes' => null, 'despues' => 8]],
            RegistroDeAuditoria::cambios(null, json_encode(['nota' => 8])));
    }

    public function test_sin_usuario_dice_sistema_y_trae_los_nombres_legibles(): void
    {
        $id = $this->registro(['usuario_id' => null, 'tabla' => 'evaluaciones', 'created_at' => now()->addMinute()]);

        $fila = RegistroDeAuditoria::buscar([])->items()[0];

        $this->assertSame($id, $fila['id']);
        $this->assertSame('Sistema', $fila['usuario']);
        $this->assertSame('Notas', $fila['tabla_legible']);
        $this->assertArrayHasKey('alta', RegistroDeAuditoria::ACCIONES);
        $this->assertArrayHasKey('preinscripciones', RegistroDeAuditoria::TABLAS);
    }

    // ── Cobertura: cada caso de la historia queda registrado ─

    public function test_queda_registrada_el_alta_de_un_usuario(): void
    {
        $this->comoUsuario($admin = $this->usuario('admin@nep30.test'));
        $nuevo = User::create(['dni' => '41222333', 'apellido' => 'Ríos', 'nombre' => 'Juan',
            'email' => 'rios@nep30.test', 'password' => 'secreto123']);

        $fila = $this->ultimo('users', $nuevo->id);
        $this->assertSame('alta', $fila['accion']);
        $this->assertSame($admin->apellido.', '.$admin->nombre, $fila['usuario']);
        $this->assertSame(['campo' => 'dni', 'antes' => null, 'despues' => '41222333'], $this->cambio($fila, 'dni'));
    }

    public function test_queda_registrada_la_modificacion_de_un_usuario(): void
    {
        $alumno = $this->usuario('alumno2@nep30.test');
        $this->comoUsuario($admin = $this->usuario('admin@nep30.test'));
        $alumno->update(['apellido' => 'Corregido', 'password' => 'otraClave123']);

        $fila = $this->ultimo('users', $alumno->id);
        $this->assertSame('modificacion', $fila['accion']);
        $this->assertSame($admin->apellido.', '.$admin->nombre, $fila['usuario']);
        $this->assertSame(['campo' => 'apellido', 'antes' => 'Prueba', 'despues' => 'Corregido'], $this->cambio($fila, 'apellido'));
        $this->assertStringNotContainsString('otraClave123', json_encode($fila));
    }

    public function test_queda_registrada_la_baja_de_un_usuario(): void
    {
        $alumno = $this->usuario('alumno2@nep30.test');
        $this->comoUsuario($this->usuario('admin@nep30.test'));
        $alumno->update(['activo' => false]);

        $fila = $this->ultimo('users', $alumno->id);
        $this->assertSame('baja', $fila['accion']);
        $this->assertSame(['campo' => 'activo', 'antes' => 1, 'despues' => 0], $this->cambio($fila, 'activo'));
    }

    public function test_quedan_registradas_la_carga_y_la_correccion_de_una_nota(): void
    {
        $profesora = $this->usuario('profesor@nep30.test');
        $this->actingAs($profesora);
        $this->comoUsuario($profesora);
        $im = InscripcionMateria::whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $this->usuario('alumno2@nep30.test')->id))
            ->where('materia_id', Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id'))->firstOrFail();
        $datos = ['inscripcion_materia_id' => $im->id, 'tipo' => 'parcial', 'nota' => 5, 'fecha' => '2026-05-04'];

        $nota = GuardarNota::ejecutar($datos);
        $alta = $this->ultimo('evaluaciones', $nota->id);
        $this->assertSame('alta', $alta['accion']);
        $this->assertSame('Prueba, Profesora', $alta['usuario']);
        $this->assertEquals(5, $this->cambio($alta, 'nota')['despues']);

        $this->travel(1)->minutes();
        GuardarNota::ejecutar([...$datos, 'nota' => 6.5], $nota);
        $correccion = $this->ultimo('evaluaciones', $nota->id);
        $this->assertSame('modificacion', $correccion['accion']);
        $this->assertEquals(5, $this->cambio($correccion, 'nota')['antes']);
        $this->assertEquals(6.5, $this->cambio($correccion, 'nota')['despues']);
    }

    public function test_quedan_registrados_el_alta_y_el_cambio_de_estado_de_una_preinscripcion(): void
    {
        Notification::fake();
        Mail::fake();
        $this->comoUsuario(null);                                        // la hace un aspirante, desde el sitio público
        $pre = Preinscribir::ejecutar([
            'curso_id' => DB::table('v_oferta_publica')->where('plan', 'like', '%Informática%')->value('curso_id'),
            'dni' => '40123456', 'apellido' => 'Gómez', 'nombre' => 'María', 'fecha_nacimiento' => '2000-04-15',
            'email' => 'maria@nep30.test', 'telefono' => '3704 123456', 'acepta_privacidad' => true,
        ]);

        $alta = $this->ultimo('preinscripciones', $pre->id);
        $this->assertSame('alta', $alta['accion']);
        $this->assertSame('Sistema', $alta['usuario']);
        $this->assertSame('40123456', $this->cambio($alta, 'dni')['despues']);

        $this->travel(1)->minutes();
        $this->actingAs($admin = $this->usuario('admin@nep30.test'));
        $this->comoUsuario($admin);
        AprobarPreinscripcion::ejecutar($pre->fresh());

        $cambio = $this->ultimo('preinscripciones', $pre->id);
        $this->assertSame('modificacion', $cambio['accion']);
        $this->assertSame($admin->apellido.', '.$admin->nombre, $cambio['usuario']);
        $this->assertSame(['campo' => 'estado', 'antes' => $pre->estado, 'despues' => 'aprobada'], $this->cambio($cambio, 'estado'));
    }

    // ── La auditoría no se toca ────────────────────────────

    public function test_no_se_puede_editar_ni_borrar_un_renglon_de_la_auditoria(): void
    {
        $id = $this->registro(['created_at' => now()]);

        try {
            DB::table('auditoria')->where('id', $id)->update(['accion' => 'baja']);
            $this->fail('La base dejó editar la auditoría');
        } catch (QueryException $e) {
            $this->assertStringContainsString('La auditoría no puede modificarse', $e->getMessage());
        }

        try {
            DB::table('auditoria')->where('id', $id)->delete();
            $this->fail('La base dejó borrar la auditoría');
        } catch (QueryException $e) {
            $this->assertStringContainsString('La auditoría no puede eliminarse', $e->getMessage());
        }

        $this->assertDatabaseHas('auditoria', ['id' => $id, 'accion' => 'modificacion']);
    }
}