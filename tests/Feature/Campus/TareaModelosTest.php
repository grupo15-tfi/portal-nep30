<?php

namespace Tests\Feature\Campus;

use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
* HU12 (#118): modelos Tarea y Entrega.
* Datos de DatosDePruebaSeeder: la tarea «Trabajo práctico 1: tipos de corte» de «Corte y peinado»,
* con fecha límite 01/12/2026 23:59 y sin entregas.
*/

class TareaModelosTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;
    public function test_la_tarea_del_seeder_y_sus_relaciones(): void
    {
        $corte = CursoProfesor::where('materia_id', Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id'))->firstOrFail();
        $tarea = Tarea::where('titulo', 'Trabajo práctico 1: tipos de corte')->firstOrFail();
        $this->assertSame($corte->id, $tarea->cursoProfesor->id);
        $this->assertSame('2026-12-01 23:59', $tarea->fecha_entrega->format('Y-m-d H:i'));
        $this->assertCount(0, $tarea->entregas);
        $this->assertTrue($corte->tareas->contains($tarea));
    }

    public function test_la_entrega_y_lo_que_calcula_la_base(): void
    {
        $tarea = Tarea::firstOrFail();
        $insc = Inscripcion::where('alumno_id', User::where('email', 'alumno@nep30.test')->value('id'))->firstOrFail();

        // Entrega después de la fecha límite: la base la marca fuera de término (trg_entrega_bi)
        $entrega = Entrega::create([
            'tarea_id' => $tarea->id, 'inscripcion_id' => $insc->id, 'archivo_ruta' => 'entregas/x.pdf',
            'archivo_tamano' => 1024, 'archivo_tipo' => 'pdf', 'fecha_entrega' => $tarea->fecha_entrega->copy()->addDay(),
        ])->refresh();

        $this->assertTrue($entrega->fuera_de_termino);
        $this->assertSame($tarea->id, $entrega->tarea->id);
        $this->assertSame($insc->id, $entrega->inscripcion->id);
        $this->assertTrue($insc->entregas->contains($entrega));
        $this->assertSame(['pdf', 'docx', 'jpg', 'png', 'zip'], Entrega::TIPOS);
        $this->assertSame(10 * 1024 * 1024, Entrega::TAMANO_MAXIMO);
    }
}