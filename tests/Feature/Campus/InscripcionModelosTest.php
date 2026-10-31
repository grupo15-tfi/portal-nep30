<?php

namespace Tests\Feature\Campus;

use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU09 (#86): modelos de inscripción, con los datos de DatosDePruebaSeeder:
 * la alumna de prueba (alumno@nep30.test) está inscripta en Peluquería con 2 módulos.
 */
class InscripcionModelosTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function alumna(): User
    {
        return User::where('email', 'alumno@nep30.test')->firstOrFail();
    }

    public function test_la_alumna_tiene_su_inscripcion_activa_con_sus_materias(): void
    {
        $inscripcion = $this->alumna()->inscripciones()->activas()->with('curso.planEstudio', 'materias.materia')->sole();

        $this->assertSame('Peluquería', $inscripcion->curso->planEstudio->nombre);
        $this->assertCount(2, $inscripcion->materias);
        $this->assertSame('cursando', $inscripcion->materias->first()->estado);
        $this->assertNotNull($inscripcion->materias->first()->materia->nombre);
    }

    public function test_la_inscripcion_conoce_su_preinscripcion_y_al_reves(): void
    {
        $inscripcion = $this->alumna()->inscripciones()->sole();

        $this->assertSame($this->alumna()->dni, $inscripcion->preinscripcion->dni);
        $this->assertTrue($inscripcion->preinscripcion->inscripcion->is($inscripcion));
        $this->assertTrue($inscripcion->alumno->is($this->alumna()));
    }

    public function test_el_scope_activas_deja_afuera_las_bajas(): void
    {
        $this->assertSame(2, Inscripcion::activas()->count());

        $this->alumna()->inscripciones()->sole()
            ->update(['estado' => 'baja', 'fecha_baja' => today(), 'motivo_baja' => 'Prueba']);

        $this->assertSame(1, Inscripcion::activas()->count());
    }
}