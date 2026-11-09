<?php

namespace Tests\Feature\Admin;

use App\Actions\Alumnos\DarDeAlta;
use App\Actions\Alumnos\DarDeBaja;
use App\Actions\Preinscripciones\AprobarPreinscripcion;
use App\Models\Preinscripcion;
use App\Models\User;
use App\Notifications\CrearContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU09 (#86): alta y baja de alumnos.
 * Datos de prueba: Aspirante Tres (30000005) pendiente en Informática (con cupo);
 * la alumna de prueba (30000001) inscripta en Peluquería.
 */
class AlumnosAccionesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->actingAs(User::factory()->conRol('administrador')->create());
    }

    private function aprobada(string $dni = '30000005'): Preinscripcion
    {
        return AprobarPreinscripcion::ejecutar(Preinscripcion::where('dni', $dni)->where('estado', '<>', 'aprobada')->firstOrFail());
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

    private function alumna(): User
    {
        return User::where('email', 'alumno@nep30.test')->firstOrFail();
    }

    public function test_el_alta_crea_al_alumno_lo_inscribe_con_las_materias_y_le_manda_el_enlace(): void
    {
        $p = $this->aprobada();
        $libresAntes = $p->curso->cupo_disponible;

        $inscripcion = DarDeAlta::ejecutar($p);

        $alumno = User::where('dni', '30000005')->sole();
        $this->assertTrue($alumno->hasRole('alumno'));
        $this->assertTrue($alumno->activo);
        $this->assertSame('activa', $inscripcion->estado);
        $this->assertSame($p->curso->materiasDelPlan->count(), $inscripcion->materias()->count());
        $this->assertSame($libresAntes - 1, $p->curso->fresh()->cupo_disponible);
        Notification::assertSentTo($alumno, CrearContrasena::class);
    }

    public function test_no_se_da_de_alta_lo_que_no_esta_aprobado_ni_dos_veces(): void
    {
        $pendiente = Preinscripcion::where('dni', '30000005')->firstOrFail();
        $this->assertArrayHasKey('estado', $this->errores(fn () => DarDeAlta::ejecutar($pendiente)));

        $p = $this->aprobada();
        DarDeAlta::ejecutar($p);
        $this->assertArrayHasKey('estado', $this->errores(fn () => DarDeAlta::ejecutar($p->fresh())));
    }

    public function test_un_alumno_que_ya_existe_se_reutiliza_y_no_recibe_otro_enlace(): void
    {
        // La alumna de prueba se preinscribe también en Informática
        $p = Preinscripcion::create([
            'curso_id' => Preinscripcion::where('dni', '30000005')->value('curso_id'),
            'dni' => '30000001', 'apellido' => 'Prueba', 'nombre' => 'Alumna Uno',
            'fecha_nacimiento' => '1995-08-20', 'email' => 'alumno@nep30.test',
            'telefono' => '3704 111111', 'acepta_privacidad' => true,
        ]);
        AprobarPreinscripcion::ejecutar($p->refresh());

        DarDeAlta::ejecutar($p->fresh());

        $this->assertSame(1, User::where('dni', '30000001')->count());
        $this->assertSame(2, $this->alumna()->inscripciones()->activas()->count());
        Notification::assertNotSentTo($this->alumna(), CrearContrasena::class);
    }

    public function test_el_dni_de_un_profesor_no_se_inscribe_como_alumno(): void
    {
        $p = Preinscripcion::create([
            'curso_id' => Preinscripcion::where('dni', '30000005')->value('curso_id'),
            'dni' => '20000001', 'apellido' => 'Prueba', 'nombre' => 'Profesora',
            'fecha_nacimiento' => '1980-01-01', 'email' => 'otro.correo@nep30.test',
            'telefono' => '3704 111111', 'acepta_privacidad' => true,
        ]);
        AprobarPreinscripcion::ejecutar($p->refresh());

        $this->assertArrayHasKey('alta', $this->errores(fn () => DarDeAlta::ejecutar($p->fresh())));
        $this->assertNull($p->fresh()->inscripcion);
    }

    public function test_la_baja_exige_motivo_libera_el_lugar_y_bloquea_el_ingreso(): void
    {
        $inscripcion = $this->alumna()->inscripciones()->sole();
        $libresAntes = $inscripcion->curso->cupo_disponible;

        $this->assertArrayHasKey('motivo', $this->errores(fn () => DarDeBaja::ejecutar($inscripcion, '')));

        DarDeBaja::ejecutar($inscripcion, 'Se mudó a otra ciudad.');

        $this->assertSame('baja', $inscripcion->fresh()->estado);
        $this->assertNotNull($inscripcion->fresh()->fecha_baja);
        $this->assertSame($libresAntes + 1, $inscripcion->curso->fresh()->cupo_disponible);
        $this->assertFalse($this->alumna()->activo);
    }

    public function test_si_le_queda_otra_inscripcion_activa_puede_seguir_ingresando(): void
    {
        $p = Preinscripcion::create([
            'curso_id' => Preinscripcion::where('dni', '30000005')->value('curso_id'),
            'dni' => '30000001', 'apellido' => 'Prueba', 'nombre' => 'Alumna Uno',
            'fecha_nacimiento' => '1995-08-20', 'email' => 'alumno@nep30.test',
            'telefono' => '3704 111111', 'acepta_privacidad' => true,
        ]);
        AprobarPreinscripcion::ejecutar($p->refresh());
        DarDeAlta::ejecutar($p->fresh());

        $peluqueria = $this->alumna()->inscripciones()->where('curso_id', '<>', $p->curso_id)->sole();
        DarDeBaja::ejecutar($peluqueria, 'Terminó el curso de otra forma.');

        $this->assertTrue($this->alumna()->activo);
    }
}