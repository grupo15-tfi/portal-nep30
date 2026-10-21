<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Cursos\Index;
use App\Models\Curso;
use App\Models\PeriodoLectivo;
use App\Models\PlanEstudio;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU07 (#9): ABM de cursos, cupo y cupo disponible (RF15).
 * Usa los datos de DatosDePruebaSeeder: período 2026, "Sede Central (prueba)",
 * "Anexo Barrio Norte (prueba)" y el curso de Peluquería (central, tarde).
 */
class CursosTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    /** Datos válidos: Peluquería en el anexo, turno mañana (no existe en los datos de prueba) */
    private function datosValidos(): array
    {
        return [
            'planId'     => (string) PlanEstudio::where('nombre', 'Peluquería')->value('id'),
            'sedeId'     => (string) Sede::where('nombre', 'Anexo Barrio Norte (prueba)')->value('id'),
            'periodoId'  => (string) PeriodoLectivo::where('anio', 2026)->value('id'),
            'turno'      => 'mañana',
            'dias'       => 'Lunes y miércoles',
            'horaInicio' => '08:00',
            'horaFin'    => '12:00',
            'cupo'       => '20',
        ];
    }

    private function formulario(array $cambios = [])
    {
        $componente = Livewire::actingAs($this->admin())->test(Index::class);
        foreach (array_merge($this->datosValidos(), $cambios) as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente->call('guardar');
    }

    public function test_el_administrador_ve_la_pantalla_de_cursos(): void
    {
        $this->actingAs($this->admin())->get('/admin/cursos')->assertOk();
    }

    public function test_profesor_y_alumno_reciben_403(): void
    {
        foreach (['profesor', 'alumno'] as $rol) {
            $user = User::factory()->conRol($rol)->create();
            $this->actingAs($user)->get('/admin/cursos')->assertForbidden();
        }
    }

    public function test_alta_de_curso_valida(): void
    {
        $this->formulario()->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['turno' => 'mañana', 'cupo' => 20, 'estado' => 'activo',
            'sede_id' => (int) $this->datosValidos()['sedeId']]);
    }

    public function test_cupo_fuera_de_rango_da_error(): void
    {
        $this->formulario(['cupo' => '0'])->assertHasErrors('cupo');
        $this->formulario(['cupo' => '501'])->assertHasErrors('cupo');
    }

    public function test_hora_de_fin_anterior_a_la_de_inicio_da_error(): void
    {
        $this->formulario(['horaInicio' => '12:00', 'horaFin' => '08:00'])->assertHasErrors('horaFin');
    }

    public function test_turno_invalido_da_error(): void
    {
        $this->formulario(['turno' => 'madrugada'])->assertHasErrors('turno');
    }

    public function test_mismo_plan_sede_periodo_y_turno_da_error(): void
    {
        // Ya existe Peluquería en la Sede Central, turno tarde
        $this->formulario([
            'sedeId' => (string) Sede::where('nombre', 'Sede Central (prueba)')->value('id'),
            'turno'  => 'tarde',
        ])->assertHasErrors(['turno' => 'unique']);
    }

    public function test_cupo_disponible_es_cupo_menos_inscriptos_activos(): void
    {
        foreach (Curso::all() as $curso) {
            $activos = DB::table('inscripciones')->where('curso_id', $curso->id)->where('estado', 'activa')->count();

            $this->assertSame($curso->cupo - $activos, $curso->cupo_disponible, "curso {$curso->id}");
        }
    }

    public function test_desactivar_curso_lo_saca_de_la_oferta_publica(): void
    {
        $curso = Curso::where('estado', 'activo')->firstOrFail();
        $this->assertSame(1, DB::table('v_oferta_publica')->where('curso_id', $curso->id)->count());

        Livewire::actingAs($this->admin())->test(Index::class)->call('cambiarEstado', $curso->id);

        $this->assertSame('inactivo', $curso->fresh()->estado);
        $this->assertSame(0, DB::table('v_oferta_publica')->where('curso_id', $curso->id)->count());
        $this->assertDatabaseHas('cursos', ['id' => $curso->id]); // no se borró
    }
}
