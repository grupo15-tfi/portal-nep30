<?php

namespace Tests\Feature\Campus;

use App\Models\Evaluacion;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\User;
use App\Queries\NotasDelAlumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU11 (#90): NotasDelAlumno::de(), la consulta de «Mis notas».
 * Datos de DatosDePruebaSeeder: Alumna Uno y Alumno Dos en Peluquería (Sede Central (prueba)),
 * con «Módulo 1: Corte y peinado» y «Módulo 2: Coloración». Alumna Uno tiene un parcial con 8 en Corte.
 */
class NotasConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function corteDe(string $email): InscripcionMateria
    {
        return InscripcionMateria::whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $this->usuario($email)->id))
            ->where('materia_id', Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id'))
            ->firstOrFail();
    }

    public function test_una_fila_por_materia_con_curso_evaluaciones_nota_final_y_estado(): void
    {
        $filas = NotasDelAlumno::de($this->usuario('alumno@nep30.test'));

        $this->assertSame(['Módulo 1: Corte y peinado', 'Módulo 2: Coloración'], $filas->pluck('materia')->all());

        $corte = $filas->firstWhere('materia', 'Módulo 1: Corte y peinado');
        $this->assertSame('Peluquería · Sede Central (prueba)', $corte['curso']);
        $this->assertSame('cursando', $corte['estado']);
        $this->assertNull($corte['nota_final']);
        $this->assertCount(1, $corte['evaluaciones']);
        $this->assertSame('parcial', $corte['evaluaciones'][0]['tipo']);
        $this->assertSame('8.00', $corte['evaluaciones'][0]['nota']);
        $this->assertSame('2026-04-20', $corte['evaluaciones'][0]['fecha']->toDateString());
    }

    public function test_las_evaluaciones_salen_ordenadas_por_fecha(): void
    {
        $im = $this->corteDe('alumno@nep30.test');
        $profesora = $this->usuario('profesor@nep30.test');
        Evaluacion::create(['inscripcion_materia_id' => $im->id, 'tipo' => 'final', 'nota' => 9, 'fecha' => '2026-06-01', 'cargada_por' => $profesora->id]);
        Evaluacion::create(['inscripcion_materia_id' => $im->id, 'tipo' => 'trabajo_practico', 'nota' => 7, 'fecha' => '2026-03-15', 'cargada_por' => $profesora->id]);

        $evaluaciones = NotasDelAlumno::de($this->usuario('alumno@nep30.test'))
            ->firstWhere('materia', 'Módulo 1: Corte y peinado')['evaluaciones'];

        $this->assertSame(['trabajo_practico', 'parcial', 'final'], $evaluaciones->pluck('tipo')->all());
    }

    public function test_cada_alumno_ve_solo_sus_notas(): void
    {
        $corte = NotasDelAlumno::de($this->usuario('alumno2@nep30.test'))
            ->firstWhere('materia', 'Módulo 1: Corte y peinado');

        $this->assertCount(0, $corte['evaluaciones']);   // el parcial con 8 es de Alumna Uno
    }

    public function test_muestra_la_nota_final_y_el_estado_del_modulo_cerrado(): void
    {
        $this->corteDe('alumno@nep30.test')->update(['nota_final' => 7.5, 'estado' => 'acreditada']);

        $corte = NotasDelAlumno::de($this->usuario('alumno@nep30.test'))->firstWhere('materia', 'Módulo 1: Corte y peinado');

        $this->assertSame('7.50', $corte['nota_final']);
        $this->assertSame('acreditada', $corte['estado']);
    }

    public function test_sin_inscripciones_activas_no_devuelve_nada(): void
    {
        Inscripcion::where('alumno_id', $this->usuario('alumno@nep30.test')->id)
            ->update(['estado' => 'baja', 'fecha_baja' => today(), 'motivo_baja' => 'Prueba']);

        $this->assertTrue(NotasDelAlumno::de($this->usuario('alumno@nep30.test'))->isEmpty());
        $this->assertTrue(NotasDelAlumno::de($this->usuario('profesor@nep30.test'))->isEmpty());
    }
}