<?php

namespace Tests\Feature\Profesor;

use App\Actions\Profesores\GuardarProfesor;
use App\Models\Curso;
use App\Models\CursoProfesor;
use App\Models\Materia;
use App\Models\PlanEstudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU08 (#54): «Mis materias» en el panel del profesor.
 * Datos de DatosDePruebaSeeder: la profesora «profesor@nep30.test» tiene 3 asignaciones
 * (Corte y peinado y Coloración en Peluquería, Ofimática en Informática), todas en la Sede Central (prueba).
 */
class MisMateriasTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function profesora(): User
    {
        return User::where('email', 'profesor@nep30.test')->firstOrFail();
    }

    private function nuevoProfesor(): User
    {
        return GuardarProfesor::ejecutar(['dni' => '30111222', 'apellido' => 'Zárate', 'nombre' => 'Inés', 'email' => 'ines.zarate@nep30.test', 'sede_id' => null]);
    }

    public function test_solo_el_profesor_entra_y_el_menu_tiene_mis_materias(): void
    {
        // Sin sesión, al login
        $this->get(route('profesor.materias'))->assertRedirect(route('login'));

        $this->actingAs($this->profesora())->get(route('profesor.materias'))->assertOk();
        $this->actingAs($this->profesora())->get(route('profesor.inicio'))
            ->assertSee('href="'.route('profesor.materias').'"', false)
            ->assertSee('Mis materias');

        foreach (['administrador', 'alumno', 'auditor'] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())->get(route('profesor.materias'))->assertForbidden();
        }
    }

    public function test_lista_sus_asignaciones_con_curso_sede_turno_horario_y_materia(): void
    {
        $this->actingAs($this->profesora())->get(route('profesor.materias'))
            ->assertOk()
            ->assertSee('3 materias')
            ->assertSeeInOrder(['Materia', 'Curso', 'Sede', 'Turno', 'Horario'])
            ->assertSee('Módulo 1: Corte y peinado')
            ->assertSee('Módulo 2: Coloración')
            ->assertSee('Módulo 1: Ofimática')
            ->assertSee('Peluquería')
            ->assertSee('Operador de Informática para la Administración y Gestión')
            ->assertSee('Sede Central (prueba)')
            ->assertSee('Tarde')
            ->assertSee('Lunes a viernes · 14:00 a 18:00');
    }

    public function test_no_ve_las_asignaciones_de_otro_profesor(): void
    {
        // Otro profesor con una materia que la profesora de prueba no tiene
        $otro = $this->nuevoProfesor();
        $alfa = Curso::where('plan_id', PlanEstudio::where('nombre', 'Primaria para adultos: Alfabetización')->value('id'))->firstOrFail();
        $materia = Materia::where('plan_id', $alfa->plan_id)->firstOrFail();
        CursoProfesor::create(['curso_id' => $alfa->id, 'materia_id' => $materia->id, 'profesor_id' => $otro->id]);

        $this->actingAs($this->profesora())->get(route('profesor.materias'))
            ->assertOk()
            ->assertDontSee($materia->nombre)
            ->assertDontSee('data-asignacion="'.CursoProfesor::where('profesor_id', $otro->id)->value('id').'"', false);

        $this->actingAs($otro)->get(route('profesor.materias'))
            ->assertSee($materia->nombre)
            ->assertSee('1 materia')
            ->assertDontSee('Módulo 1: Corte y peinado');
    }

    public function test_sin_asignaciones_muestra_un_mensaje(): void
    {
        $this->actingAs($this->nuevoProfesor())->get(route('profesor.materias'))
            ->assertOk()
            ->assertSee('Todavía no tenés materias asignadas')
            ->assertDontSee('<table', false);
    }

    public function test_en_el_celular_cada_materia_se_ve_como_tarjeta(): void
    {
        $this->actingAs($this->profesora())->get(route('profesor.materias'))
            ->assertSee('class="tabla-apilada"', false)
            ->assertSee('data-etiqueta="Materia"', false)
            ->assertSee('data-etiqueta="Horario"', false);
    }
}
