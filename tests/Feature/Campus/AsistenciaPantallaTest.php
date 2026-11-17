<?php

namespace Tests\Feature\Campus;

use App\Livewire\Alumno\Asistencia as AsistenciaAlumno;
use App\Livewire\Profesor\Asistencia;
use App\Models\Clase;
use App\Models\CursoProfesor;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU13 (#94): pantallas de asistencia. Datos de DatosDePruebaSeeder: en Corte y peinado hay una
 * clase dictada el 09/03/2026 con Alumna Uno presente y Alumno Dos justificado. Mínimo del plan: 80 %.
 */
class AsistenciaPantallaTest extends TestCase
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

    private function inscripcionDe(string $email): Inscripcion
    {
        return Inscripcion::whereHas('alumno', fn ($q) => $q->where('email', $email))
            ->where('curso_id', $this->corteYPeinado()->curso_id)->firstOrFail();
    }

    private function pantalla()
    {
        return Livewire::actingAs($this->profesora())->test(Asistencia::class, ['cursoProfesor' => $this->corteYPeinado()]);
    }

    public function test_solo_el_profesor_de_la_materia_entra(): void
    {
        $url = route('profesor.asistencia', $this->corteYPeinado());

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->profesora())->get($url)->assertOk()->assertSee('Asistencia · Módulo 1: Corte y peinado');
        $this->actingAs(User::factory()->conRol('profesor')->create())->get($url)->assertForbidden();
        foreach (['administrador', 'alumno', 'auditor'] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())->get($url)->assertForbidden();
        }

        $this->actingAs($this->profesora())->get(route('profesor.materias'))
            ->assertSee('href="'.$url.'"', false);
    }

    public function test_muestra_clases_y_porcentaje_por_alumno(): void
    {
        $this->pantalla()
            ->assertSee('09/03/2026')
            ->assertSee('0 ausentes')
            ->assertSeeInOrder(['Prueba, Alumna Uno', "100\u{00A0}%", 'Prueba, Alumno Dos', "100\u{00A0}%"]);
    }

    public function test_toma_la_asistencia_de_hoy_con_todos_presentes_por_defecto(): void
    {
        $uno = $this->inscripcionDe('alumno@nep30.test');
        $dos = $this->inscripcionDe('alumno2@nep30.test');

        $this->pantalla()
            ->call('hoy')
            ->assertSet('ventanaAbierta', true)
            ->assertSet('estados', [$uno->id => 'presente', $dos->id => 'presente'])
            ->assertSee('Asistencia de hoy')
            ->set("estados.{$dos->id}", 'ausente')
            ->set('tema', 'Corte en capas')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('ventanaAbierta', false)
            ->assertSee('Se guardó la asistencia del '.today()->format('d/m/Y').': 1 ausente.')
            // 1 presente y 1 justificada de 2 clases: Alumno Dos queda en 50 %, debajo del 80 %
            ->assertSee("50\u{00A0}%")
            ->assertSee('Debajo del mínimo');

        $clase = Clase::where('curso_profesor_id', $this->corteYPeinado()->id)->whereDate('fecha', today())->firstOrFail();
        $this->assertSame('Corte en capas', $clase->tema);
        $this->assertDatabaseHas('asistencias', ['clase_id' => $clase->id, 'inscripcion_id' => $dos->id, 'estado' => 'ausente']);
        $this->assertDatabaseHas('asistencias', ['clase_id' => $clase->id, 'inscripcion_id' => $uno->id, 'estado' => 'presente']);
    }

    public function test_hoy_dos_veces_abre_la_misma_clase_para_corregir(): void
    {
        $this->pantalla()->call('hoy')->call('guardar');
        $this->pantalla()->call('hoy')->assertSee('Clase del '.today()->format('d/m/Y'))->assertNotSet('claseId', null);

        $this->assertSame(1, Clase::where('curso_profesor_id', $this->corteYPeinado()->id)->whereDate('fecha', today())->count());
    }

    public function test_corrige_una_clase_anterior(): void
    {
        $clase = Clase::where('curso_profesor_id', $this->corteYPeinado()->id)->firstOrFail();
        $dos = $this->inscripcionDe('alumno2@nep30.test');

        $this->pantalla()
            ->call('abrir', $clase->id)
            ->assertSet("estados.{$dos->id}", 'justificado')
            ->set("estados.{$dos->id}", 'tarde')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Se corrigió la asistencia del 09/03/2026.');

        $this->assertDatabaseHas('asistencias', ['clase_id' => $clase->id, 'inscripcion_id' => $dos->id, 'estado' => 'tarde']);
    }

    public function test_una_clase_suspendida_no_cuenta(): void
    {
        $this->pantalla()
            ->call('hoy')
            ->call('todos', 'ausente')
            ->set('suspendida', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('quedó como suspendida')
            ->assertSee('Suspendida')
            ->assertDontSee('Debajo del mínimo');

        $clase = Clase::where('curso_profesor_id', $this->corteYPeinado()->id)->whereDate('fecha', today())->firstOrFail();
        $this->assertSame('suspendida', $clase->estado);
        $this->assertSame(0, $clase->asistencias()->count());
    }

    public function test_no_se_carga_una_clase_futura_y_el_error_queda_en_la_ventana(): void
    {
        $this->pantalla()
            ->call('hoy')
            ->set('fecha', today()->addDay()->format('Y-m-d'))
            ->call('guardar')
            ->assertHasErrors(['fecha'])
            ->assertSet('ventanaAbierta', true);
    }

    public function test_no_abre_clases_de_otra_materia(): void
    {
        $otra = CursoProfesor::where('profesor_id', $this->profesora()->id)->where('id', '!=', $this->corteYPeinado()->id)->firstOrFail();
        $ajena = Clase::create(['curso_profesor_id' => $otra->id, 'fecha' => '2026-03-10', 'estado' => 'dictada']);

        $this->pantalla()->call('abrir', $ajena->id)->assertStatus(404);
    }

    public function test_el_alumno_ve_su_porcentaje_minimo_y_faltas(): void
    {
        $dos = User::where('email', 'alumno2@nep30.test')->firstOrFail();

        $this->get(route('alumno.asistencia'))->assertRedirect(route('login'));
        $this->actingAs($dos)->get(route('alumno.inicio'))
            ->assertSee('href="'.route('alumno.asistencia').'"', false)->assertSee('Mi asistencia');
        $this->actingAs($this->profesora())->get(route('alumno.asistencia'))->assertForbidden();

        Livewire::actingAs($dos)->test(AsistenciaAlumno::class)
            ->assertSee('Módulo 1: Corte y peinado')
            ->assertSee("100\u{00A0}%")
            ->assertSee("mínimo del plan: 80\u{00A0}%")
            ->assertSee('09/03/2026 · Justificada');

        Livewire::actingAs(User::where('email', 'alumno@nep30.test')->firstOrFail())->test(AsistenciaAlumno::class)
            ->assertSee('Sin faltas.')
            ->assertDontSee('Justificada');
    }
}
