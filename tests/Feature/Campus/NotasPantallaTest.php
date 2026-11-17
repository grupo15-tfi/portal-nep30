<?php

namespace Tests\Feature\Campus;

use App\Livewire\Alumno\Notas as NotasAlumno;
use App\Livewire\Profesor\Notas;
use App\Models\CursoProfesor;
use App\Models\Evaluacion;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU11 (#91): pantallas de notas. El profesor carga y corrige notas de sus materias;
 * el alumno ve solo las suyas. Datos de DatosDePruebaSeeder: la profesora tiene Corte y peinado
 * en el curso de Peluquería, con Alumna Uno (parcial 8) y Alumno Dos.
 */
class NotasPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function profesora(): User
    {
        return User::where('email', 'profesor@nep30.test')->firstOrFail();
    }

    private function corteYPeinado(): CursoProfesor
    {
        return CursoProfesor::where('profesor_id', $this->profesora()->id)
            ->whereHas('materia', fn ($q) => $q->where('nombre', 'like', '%Corte y peinado%'))->firstOrFail();
    }

    private function materiaDe(string $email): InscripcionMateria
    {
        $cp = $this->corteYPeinado();

        return InscripcionMateria::where('materia_id', $cp->materia_id)
            ->whereHas('inscripcion', fn ($q) => $q->where('curso_id', $cp->curso_id)
                ->whereHas('alumno', fn ($a) => $a->where('email', $email)))->firstOrFail();
    }

    public function test_solo_el_profesor_de_la_materia_entra_a_sus_notas(): void
    {
        $url = route('profesor.notas', $this->corteYPeinado());

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->profesora())->get($url)->assertOk()->assertSee('Notas · Módulo 1: Corte y peinado');

        // Otro profesor: 403. Otros roles: 403.
        $otro = User::factory()->conRol('profesor')->create();
        $this->actingAs($otro)->get($url)->assertForbidden();
        foreach (['administrador', 'alumno', 'auditor'] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())->get($url)->assertForbidden();
        }
    }

    public function test_mis_materias_tiene_el_enlace_a_notas_y_el_menu_no_se_rompe(): void
    {
        $this->actingAs($this->profesora())->get(route('profesor.materias'))
            ->assertOk()
            ->assertSee('href="'.route('profesor.notas', $this->corteYPeinado()).'"', false);
    }

    public function test_lista_los_alumnos_con_sus_notas_y_estado(): void
    {
        Livewire::actingAs($this->profesora())->test(Notas::class, ['cursoProfesor' => $this->corteYPeinado()])
            ->assertSeeInOrder(['Prueba, Alumna Uno', 'Parcial', '8', 'Prueba, Alumno Dos', 'Sin notas'])
            ->assertSee('Cursando');
    }

    public function test_carga_una_nota_con_coma_decimal(): void
    {
        $im = $this->materiaDe('alumno2@nep30.test');

        Livewire::actingAs($this->profesora())->test(Notas::class, ['cursoProfesor' => $this->corteYPeinado()])
            ->call('nueva', $im->id)
            ->assertSet('ventanaAbierta', true)
            ->assertSee('Cargar nota')
            ->set('tipo', 'trabajo_practico')
            ->set('nota', '7,5')
            ->set('fecha', today()->format('Y-m-d'))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('ventanaAbierta', false)
            ->assertSee('Se cargó la nota de Prueba, Alumno Dos: Trabajo práctico 7,5.');

        $this->assertDatabaseHas('evaluaciones', ['inscripcion_materia_id' => $im->id, 'tipo' => 'trabajo_practico', 'nota' => 7.50]);
    }

    public function test_los_errores_quedan_dentro_de_la_ventana(): void
    {
        $im = $this->materiaDe('alumno2@nep30.test');

        Livewire::actingAs($this->profesora())->test(Notas::class, ['cursoProfesor' => $this->corteYPeinado()])
            ->call('nueva', $im->id)
            ->set('nota', '11')
            ->call('guardar')
            ->assertHasErrors(['nota'])
            ->assertSet('ventanaAbierta', true)
            ->assertSee('aria-invalid', false);

        $this->assertDatabaseMissing('evaluaciones', ['inscripcion_materia_id' => $im->id]);
    }

    public function test_corrige_una_nota_existente(): void
    {
        $ev = Evaluacion::where('inscripcion_materia_id', $this->materiaDe('alumno@nep30.test')->id)->firstOrFail();

        Livewire::actingAs($this->profesora())->test(Notas::class, ['cursoProfesor' => $this->corteYPeinado()])
            ->call('editar', $ev->id)
            ->assertSet('nota', '8')
            ->assertSee('Corregir nota')
            ->set('nota', '9,25')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Se corrigió la nota de Prueba, Alumna Uno: Parcial 9,25.');

        $this->assertEquals(9.25, (float) $ev->fresh()->nota);
        $this->assertEquals(1, Evaluacion::where('inscripcion_materia_id', $ev->inscripcion_materia_id)->count());
    }

    public function test_no_abre_alumnos_ni_notas_de_otra_materia(): void
    {
        $cp = $this->corteYPeinado();
        $ajena = InscripcionMateria::where('materia_id', '!=', $cp->materia_id)->firstOrFail();

        Livewire::actingAs($this->profesora())->test(Notas::class, ['cursoProfesor' => $cp])
            ->call('nueva', $ajena->id)
            ->assertStatus(404);
    }

    public function test_el_alumno_ve_solo_sus_notas_y_tiene_el_menu(): void
    {
        $alumna = User::where('email', 'alumno@nep30.test')->firstOrFail();

        $this->get(route('alumno.notas'))->assertRedirect(route('login'));
        $this->actingAs($alumna)->get(route('alumno.inicio'))
            ->assertSee('href="'.route('alumno.notas').'"', false)->assertSee('Mis notas');
        $this->actingAs($this->profesora())->get(route('alumno.notas'))->assertForbidden();

        Livewire::actingAs($alumna)->test(NotasAlumno::class)
            ->assertSee('Módulo 1: Corte y peinado')
            ->assertSee('Parcial')
            ->assertSee('Nota final');

        // Alumno Dos no tiene notas: no ve el 8 de su compañera
        Livewire::actingAs(User::where('email', 'alumno2@nep30.test')->firstOrFail())->test(NotasAlumno::class)
            ->assertSee('Sin notas cargadas todavía')
            ->assertDontSee('<td class="text-right font-semibold">8</td>', false);
    }

    public function test_formato_de_nota(): void
    {
        $this->assertSame('8', Notas::formatoNota('8.00'));
        $this->assertSame('8,5', Notas::formatoNota(8.5));
        $this->assertSame('7,25', Notas::formatoNota('7.25'));
        $this->assertSame('—', Notas::formatoNota(null));
    }
}
