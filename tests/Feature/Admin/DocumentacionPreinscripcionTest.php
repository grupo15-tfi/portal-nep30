<?php

namespace Tests\Feature\Admin;

use App\Actions\Alumnos\DarDeAlta;
use App\Actions\Preinscripciones\AprobarPreinscripcion;
use App\Actions\Preinscripciones\RegistrarDocumentacion;
use App\Livewire\Admin\Preinscripciones\Index;
use App\Models\Preinscripcion;
use App\Models\User;
use App\Queries\RegistroDeAuditoria;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #107: registrar la documentación presentada (en papel) antes de dar de alta.
 * El sistema no guarda copias: solo cuándo, quién y una observación opcional.
 * Datos de DatosDePruebaSeeder: Aspirante Tres (30000005) pendiente en Informática, con cupo.
 */
class DocumentacionPreinscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::where('email', 'admin@nep30.test')->firstOrFail();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        DB::statement('SET @app_usuario_id = NULL');
        parent::tearDown();
    }

    private function pendiente(): Preinscripcion
    {
        return Preinscripcion::where('dni', '30000005')->firstOrFail();
    }

    private function aprobada(): Preinscripcion
    {
        return AprobarPreinscripcion::ejecutar($this->pendiente());
    }

    private function errores(callable $accion): array
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Se esperaba un error de validación.');
    }

    // ── La acción ──────────────────────────────────────────

    public function test_registra_cuando_quien_y_la_observacion(): void
    {
        $p = RegistrarDocumentacion::ejecutar($this->aprobada(), true, '  Falta el certificado, lo trae el lunes  ');

        $p->refresh();
        $this->assertTrue($p->documentacionPresentada());
        $this->assertSame($this->admin->id, $p->documentacion_por);
        $this->assertTrue($p->documentacion_en->isToday());
        $this->assertSame('Falta el certificado, lo trae el lunes', $p->documentacion_obs);
    }

    public function test_sin_documentacion_no_se_da_de_alta(): void
    {
        $p = $this->aprobada();

        $errores = $this->errores(fn () => DarDeAlta::ejecutar($p));

        $this->assertSame(['Falta registrar la documentación presentada'], $errores['documentacion']);
        $this->assertNull($p->fresh()->inscripcion);
    }

    public function test_con_documentacion_se_da_de_alta(): void
    {
        $p = RegistrarDocumentacion::ejecutar($this->aprobada(), true);

        $inscripcion = DarDeAlta::ejecutar($p->fresh());

        $this->assertSame('activa', $inscripcion->estado);
    }

    public function test_se_desmarca_antes_del_alta_pero_no_despues(): void
    {
        $p = RegistrarDocumentacion::ejecutar($this->aprobada(), true, 'Completa');

        RegistrarDocumentacion::ejecutar($p->fresh(), false);
        $p->refresh();
        $this->assertFalse($p->documentacionPresentada());
        $this->assertNull($p->documentacion_por);
        $this->assertNull($p->documentacion_obs);

        RegistrarDocumentacion::ejecutar($p, true);
        DarDeAlta::ejecutar($p->fresh());

        $this->assertArrayHasKey('documentacion', $this->errores(fn () => RegistrarDocumentacion::ejecutar($p->fresh(), false)));
        $this->assertTrue($p->fresh()->documentacionPresentada());
    }

    public function test_solo_en_preinscripciones_aprobadas_y_observacion_de_hasta_300(): void
    {
        $this->assertArrayHasKey('documentacion', $this->errores(fn () => RegistrarDocumentacion::ejecutar($this->pendiente(), true)));

        $p = $this->aprobada();
        $this->assertArrayHasKey('documentacion_obs', $this->errores(fn () => RegistrarDocumentacion::ejecutar($p, true, str_repeat('a', 301))));
        $this->assertFalse($p->fresh()->documentacionPresentada());
    }

    // ── La base ────────────────────────────────────────────

    public function test_la_base_exige_fecha_y_usuario_juntos_y_solo_en_aprobadas(): void
    {
        $pendiente = $this->pendiente();
        $intentar = function (array $valores, int $id) {
            try {
                DB::table('preinscripciones')->where('id', $id)->update($valores);
                $this->fail('La base aceptó una documentación inválida');
            } catch (QueryException $e) {
                $this->assertStringContainsString('chk_preinsc_doc', $e->getMessage());
            }
        };

        $intentar(['documentacion_en' => now(), 'documentacion_por' => $this->admin->id], $pendiente->id);   // no está aprobada
        $aprobada = $this->aprobada();
        $intentar(['documentacion_en' => now(), 'documentacion_por' => null], $aprobada->id);                // falta quién
    }

    public function test_queda_en_la_auditoria(): void
    {
        $p = $this->aprobada();
        DB::statement('SET @app_usuario_id = ?', [$this->admin->id]);
        $this->travel(1)->minutes();

        RegistrarDocumentacion::ejecutar($p, true, 'Completa');

        $fila = RegistroDeAuditoria::buscar(['tabla' => 'preinscripciones'])->getCollection()->firstWhere('registro_id', $p->id);
        $this->assertSame('Administrador, Prueba', $fila['usuario']);
        $this->assertSame('Completa', collect($fila['cambios'])->firstWhere('campo', 'documentacion_obs')['despues']);
        $this->assertNotNull(collect($fila['cambios'])->firstWhere('campo', 'documentacion_en')['despues']);
    }

    // ── La pantalla ────────────────────────────────────────

    public function test_la_pantalla_pide_la_documentacion_antes_de_mostrar_dar_de_alta(): void
    {
        $p = $this->aprobada();

        $pantalla = Livewire::test(Index::class)->set('estado', 'aprobada')
            ->assertSee('Registrar documentación')->assertSee('Falta la documentación')
            ->assertDontSeeHtml('wire:click="darDeAlta('.$p->id.')"');

        $pantalla->call('abrirDocumentacion', $p->id)
            ->assertSee('Documentación presentada')
            ->set('presentada', true)->set('observacionDoc', 'Trajo todo')
            ->call('guardarDocumentacion')->assertHasNoErrors()
            ->assertSee('Ya se le puede dar el alta')
            ->assertSeeHtml('wire:click="darDeAlta('.$p->id.')"');

        $pantalla->call('ver', $p->id)
            ->assertSee('Presentada')->assertSee('Administrador, Prueba')->assertSee('Trajo todo');

        $pantalla->call('cerrar')->call('darDeAlta', $p->id)->assertHasNoErrors()->assertSee('quedó inscripto');
    }

    public function test_en_la_pantalla_la_observacion_larga_da_error_en_la_ventana(): void
    {
        $p = $this->aprobada();

        Livewire::test(Index::class)
            ->call('abrirDocumentacion', $p->id)
            ->set('presentada', true)->set('observacionDoc', str_repeat('a', 301))
            ->call('guardarDocumentacion')
            ->assertHasErrors('documentacion_obs')
            ->assertSee('hasta 300 caracteres');

        $this->assertFalse($p->fresh()->documentacionPresentada());
    }
}