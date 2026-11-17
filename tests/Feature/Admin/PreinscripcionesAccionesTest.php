<?php

namespace Tests\Feature\Admin;

use App\Actions\Alumnos\DarDeBaja;
use App\Actions\Preinscripciones\AprobarPreinscripcion;
use App\Actions\Preinscripciones\RechazarPreinscripcion;
use App\Actions\Preinscripciones\RegistrarAviso;
use App\Models\Preinscripcion;
use App\Models\User;
use App\Notifications\PreinscripcionAprobada;
use App\Notifications\PreinscripcionRechazada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU09 (#86): aprobar, rechazar y registrar el aviso.
 * Datos de prueba: Aspirante Tres (30000005) pendiente en Informática (con cupo);
 * Aspirante Uno (30000003) y Dos (30000004) en espera en Peluquería (completo).
 */
class PreinscripcionesAccionesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->conRol('administrador')->create();
        $this->actingAs($this->admin);
    }

    private function dni(string $dni): Preinscripcion
    {
        return Preinscripcion::where('dni', $dni)->firstOrFail();
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

    public function test_aprobar_guarda_quien_y_cuando_y_avisa_al_aspirante(): void
    {
        $p = AprobarPreinscripcion::ejecutar($this->dni('30000005'));

        $this->assertSame('aprobada', $p->fresh()->estado);
        $this->assertSame($this->admin->id, $p->fresh()->revisado_por);
        $this->assertNotNull($p->fresh()->revisado_en);
        Notification::assertSentOnDemand(PreinscripcionAprobada::class);
    }

    public function test_con_el_curso_completo_no_se_aprueba_y_sigue_en_espera(): void
    {
        $errores = $this->errores(fn () => AprobarPreinscripcion::ejecutar($this->dni('30000003')));

        $this->assertArrayHasKey('cupo', $errores);
        $this->assertSame('en_espera', $this->dni('30000003')->estado);
        Notification::assertNothingSent();
    }

    public function test_cuando_se_libera_un_lugar_el_primero_de_la_lista_se_puede_aprobar(): void
    {
        $alumna = User::where('email', 'alumno@nep30.test')->firstOrFail();
        DarDeBaja::ejecutar($alumna->inscripciones()->sole(), 'Se mudó a otra ciudad.');

        AprobarPreinscripcion::ejecutar($this->dni('30000003'));

        $this->assertSame('aprobada', $this->dni('30000003')->estado);
    }

    public function test_rechazar_exige_motivo_y_lo_manda_por_correo(): void
    {
        $this->assertArrayHasKey('motivo', $this->errores(fn () => RechazarPreinscripcion::ejecutar($this->dni('30000005'), '   ')));

        RechazarPreinscripcion::ejecutar($this->dni('30000005'), 'El curso no corresponde a su nivel educativo.');

        $p = $this->dni('30000005');
        $this->assertSame('rechazada', $p->estado);
        $this->assertSame('El curso no corresponde a su nivel educativo.', $p->observacion);
        Notification::assertSentOnDemand(PreinscripcionRechazada::class,
            fn ($correo, $canales, $destino) => $destino->routes['mail'] === 'aspirante3@nep30.test');
    }

    public function test_una_preinscripcion_revisada_no_cambia(): void
    {
        RechazarPreinscripcion::ejecutar($this->dni('30000005'), 'Datos incompletos.');

        $this->assertArrayHasKey('estado', $this->errores(fn () => AprobarPreinscripcion::ejecutar($this->dni('30000005'))));
        $this->assertSame('rechazada', $this->dni('30000005')->estado);
    }

    public function test_registrar_aviso_solo_en_la_lista_de_espera(): void
    {
        RegistrarAviso::ejecutar($this->dni('30000003'));

        $p = $this->dni('30000003');
        $this->assertNotNull($p->avisado_en);
        $this->assertSame($this->admin->id, $p->avisado_por);

        $this->assertArrayHasKey('estado', $this->errores(fn () => RegistrarAviso::ejecutar($this->dni('30000005'))));
    }
}