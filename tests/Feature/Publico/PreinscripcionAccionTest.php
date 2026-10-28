<?php

namespace Tests\Feature\Publico;

use App\Actions\Preinscripciones\Preinscribir;
use App\Events\PreinscripcionRecibida;
use App\Models\Curso;
use App\Models\Preinscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU04 (#51): acción Preinscribir (RF06, RF09).
 * Datos de DatosDePruebaSeeder:
 *   - Informática (Sede Central): cupo 20, con lugar → queda pendiente.
 *   - Peluquería (Sede Central): cupo 2 completo → queda en lista de espera.
 *   - DNI 30000005 ya tiene una preinscripción pendiente en Informática.
 */
class PreinscripcionAccionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PreinscripcionRecibida::class]); // el correo de acuse es de #53: acá solo se mira que se dispare
    }

    private function cursoDe(string $plan): Curso
    {
        return Curso::whereHas('planEstudio', fn ($q) => $q->where('nombre', 'like', "%{$plan}%"))->firstOrFail();
    }

    /** Datos válidos de un aspirante de prueba; se pisan los que cambia cada prueba */
    private function datos(array $cambios = []): array
    {
        return [
            'curso_id'          => $this->cursoDe('Informática')->id,
            'dni'               => '40123456',
            'apellido'          => 'Prueba',
            'nombre'            => 'Aspirante Nuevo',
            'fecha_nacimiento'  => '2000-04-15',
            'email'             => 'nuevo@nep30.test',
            'telefono'          => '3704 222222',
            'acepta_privacidad' => true,
            ...$cambios,
        ];
    }

    /** Ejecuta la acción esperando un error de validación y devuelve los errores por campo */
    private function erroresDe(array $datos): array
    {
        try {
            Preinscribir::ejecutar($datos);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Se esperaba un error de validación y la preinscripción se guardó.');
    }

    public function test_una_preinscripcion_valida_queda_pendiente_y_dispara_el_evento(): void
    {
        $usuarios = User::count();

        $pre = Preinscribir::ejecutar($this->datos());

        $this->assertSame('pendiente', $pre->estado);
        $this->assertDatabaseHas('preinscripciones', ['dni' => '40123456', 'estado' => 'pendiente']);
        $this->assertSame($usuarios, User::count()); // no se crea usuario
        Event::assertDispatched(PreinscripcionRecibida::class, fn ($e) => $e->preinscripcion->is($pre));
    }

    public function test_sin_cupo_queda_en_lista_de_espera(): void
    {
        $pre = Preinscribir::ejecutar($this->datos(['curso_id' => $this->cursoDe('Peluquería')->id]));

        $this->assertSame('en_espera', $pre->estado); // lo decidió el trigger de la base
        $this->assertTrue($pre->enEspera());
        Event::assertDispatched(PreinscripcionRecibida::class);
    }

    public function test_una_solicitud_pendiente_repetida_da_aviso_en_el_dni(): void
    {
        $errores = $this->erroresDe($this->datos(['dni' => '30000005']));

        $this->assertSame([Preinscribir::DUPLICADA], $errores['dni']);
        Event::assertNotDispatched(PreinscripcionRecibida::class);
    }

    public function test_con_una_solicitud_rechazada_puede_volver_a_preinscribirse(): void
    {
        $vieja = Preinscripcion::where('dni', '30000005')->firstOrFail();
        $admin = User::role('administrador')->firstOrFail();
        $vieja->forceFill([
            'estado' => 'rechazada', 'observacion' => 'Prueba', 'revisado_por' => $admin->id, 'revisado_en' => now(),
        ])->save();

        $pre = Preinscribir::ejecutar($this->datos(['dni' => '30000005']));

        $this->assertSame('pendiente', $pre->estado);
    }

    public function test_un_menor_de_la_edad_minima_da_error_en_la_fecha_de_nacimiento(): void
    {
        $errores = $this->erroresDe($this->datos(['fecha_nacimiento' => now()->subYears(17)->toDateString()]));

        $this->assertSame(['Para este curso tenés que tener al menos 18 años.'], $errores['fecha_nacimiento']);
        $this->assertDatabaseMissing('preinscripciones', ['dni' => '40123456']);
    }

    public function test_sin_aceptar_la_privacidad_no_se_envia(): void
    {
        $errores = $this->erroresDe($this->datos(['acepta_privacidad' => false]));

        $this->assertArrayHasKey('acepta_privacidad', $errores);
        $this->assertDatabaseMissing('preinscripciones', ['dni' => '40123456']);
    }

    public function test_un_curso_inactivo_no_acepta_preinscripciones(): void
    {
        $curso = $this->cursoDe('Informática');
        $curso->update(['estado' => 'inactivo']);

        $errores = $this->erroresDe($this->datos(['curso_id' => $curso->id]));

        $this->assertSame(['Ese curso no está disponible para preinscripción.'], $errores['curso_id']);
    }

    public function test_dni_correo_y_telefono_invalidos_dan_error_en_cada_campo(): void
    {
        $errores = $this->erroresDe($this->datos(['dni' => '123', 'email' => 'no-es-un-correo', 'telefono' => 'llamame']));

        $this->assertArrayHasKey('dni', $errores);
        $this->assertArrayHasKey('email', $errores);
        $this->assertArrayHasKey('telefono', $errores);
    }

    public function test_el_dni_con_puntos_se_guarda_solo_con_numeros(): void
    {
        $pre = Preinscribir::ejecutar($this->datos(['dni' => '40.123.456', 'email' => 'Nuevo@NEP30.test']));

        $this->assertSame('40123456', $pre->dni);
        $this->assertSame('nuevo@nep30.test', $pre->email);
    }

    public function test_no_guarda_datos_que_no_estan_en_la_lista(): void
    {
        $pre = Preinscribir::ejecutar($this->datos(['latitud' => -26.17, 'estado' => 'aprobada', 'observacion' => 'x']));

        $this->assertSame('pendiente', $pre->estado);
        $this->assertNull($pre->latitud);
        $this->assertNull($pre->observacion);
    }
}