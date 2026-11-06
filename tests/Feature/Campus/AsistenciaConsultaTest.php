<?php

namespace Tests\Feature\Campus;

use App\Models\CursoProfesor;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use App\Queries\AsistenciaDelAlumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU13 (#93): AsistenciaDelAlumno::de() y ::delCurso(), leídos de v_asistencia_porcentaje.
 * Datos de DatosDePruebaSeeder: una clase de «Módulo 1: Corte y peinado» el 09/03/2026, dictada:
 * Alumna Uno presente y Alumno Dos justificado. «Módulo 2: Coloración» todavía no tiene clases.
 * Plan de Peluquería: mínimo 80 %.
 */
class AsistenciaConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function asignacion(string $materia): CursoProfesor
    {
        return CursoProfesor::where('profesor_id', $this->usuario('profesor@nep30.test')->id)
            ->where('materia_id', Materia::where('nombre', $materia)->value('id'))
            ->firstOrFail();
    }

    private function inscripcion(string $email): Inscripcion
    {
        return Inscripcion::where('alumno_id', $this->usuario($email)->id)->firstOrFail();
    }

    /** Agrega una clase de Corte con el estado de un alumno (directo en la base, sin pasar por la acción) */
    private function clase(string $fecha, string $email, string $estado, string $estadoClase = 'dictada'): void
    {
        $clase = DB::table('clases')->insertGetId([
            'curso_profesor_id' => $this->asignacion('Módulo 1: Corte y peinado')->id,
            'fecha' => $fecha, 'hora_inicio' => '14:00', 'estado' => $estadoClase,
        ]);
        DB::table('asistencias')->insert(['clase_id' => $clase, 'inscripcion_id' => $this->inscripcion($email)->id, 'estado' => $estado]);
    }

    public function test_de_una_fila_por_materia_tambien_sin_clases(): void
    {
        $filas = AsistenciaDelAlumno::de($this->usuario('alumno2@nep30.test'));

        $this->assertSame(['Módulo 1: Corte y peinado', 'Módulo 2: Coloración'], $filas->pluck('materia')->all());

        $corte = $filas->firstWhere('materia', 'Módulo 1: Corte y peinado');
        $this->assertSame('Peluquería · Sede Central (prueba)', $corte['curso']);
        $this->assertSame(1, $corte['clases_dictadas']);
        $this->assertSame(100.0, $corte['porcentaje']);   // la justificada cuenta como asistencia
        $this->assertSame(80, $corte['asistencia_minima']);
        $this->assertSame([['fecha' => '2026-03-09', 'estado' => 'justificado']], $corte['inasistencias']->all());

        $color = $filas->firstWhere('materia', 'Módulo 2: Coloración');
        $this->assertSame(0, $color['clases_dictadas']);
        $this->assertNull($color['porcentaje']);
        $this->assertSame(80, $color['asistencia_minima']);
        $this->assertCount(0, $color['inasistencias']);
    }

    public function test_tarde_no_es_falta_y_el_porcentaje_tiene_un_decimal(): void
    {
        $this->clase('2026-03-16', 'alumno@nep30.test', 'tarde');
        $this->clase('2026-03-23', 'alumno@nep30.test', 'ausente');

        $corte = AsistenciaDelAlumno::de($this->usuario('alumno@nep30.test'))->firstWhere('materia', 'Módulo 1: Corte y peinado');

        $this->assertSame(3, $corte['clases_dictadas']);
        $this->assertSame(66.7, $corte['porcentaje']);   // presente + tarde de 3
        $this->assertSame(['ausente'], $corte['inasistencias']->pluck('estado')->all());
    }

    public function test_las_clases_suspendidas_no_cuentan(): void
    {
        $this->clase('2026-03-16', 'alumno@nep30.test', 'ausente', 'suspendida');

        $corte = AsistenciaDelAlumno::de($this->usuario('alumno@nep30.test'))->firstWhere('materia', 'Módulo 1: Corte y peinado');

        $this->assertSame(1, $corte['clases_dictadas']);
        $this->assertSame(100.0, $corte['porcentaje']);
        $this->assertCount(0, $corte['inasistencias']);
    }

    public function test_cada_alumno_ve_solo_su_asistencia(): void
    {
        $this->clase('2026-03-16', 'alumno2@nep30.test', 'ausente');

        $corte = AsistenciaDelAlumno::de($this->usuario('alumno@nep30.test'))->firstWhere('materia', 'Módulo 1: Corte y peinado');

        $this->assertSame(1, $corte['clases_dictadas']);   // la clase nueva no es de ella
        $this->assertCount(0, $corte['inasistencias']);
    }

    public function test_del_curso_una_fila_por_alumno_con_su_porcentaje(): void
    {
        $this->clase('2026-03-16', 'alumno2@nep30.test', 'ausente');

        $filas = AsistenciaDelAlumno::delCurso($this->asignacion('Módulo 1: Corte y peinado'))->keyBy('nombre');

        $this->assertCount(2, $filas);
        $this->assertSame('Prueba', $filas['Alumna Uno']['apellido']);
        $this->assertSame($this->inscripcion('alumno@nep30.test')->id, $filas['Alumna Uno']['inscripcion_id']);
        $this->assertSame(100.0, $filas['Alumna Uno']['porcentaje']);
        $this->assertSame(2, $filas['Alumno Dos']['clases_dictadas']);
        $this->assertSame(50.0, $filas['Alumno Dos']['porcentaje']);
        $this->assertSame(80, $filas['Alumno Dos']['asistencia_minima']);
    }

    public function test_del_curso_incluye_a_los_que_no_tienen_clases_y_no_a_los_de_baja(): void
    {
        $color = AsistenciaDelAlumno::delCurso($this->asignacion('Módulo 2: Coloración'));
        $this->assertCount(2, $color);
        $this->assertSame([null, null], $color->pluck('porcentaje')->all());
        $this->assertSame([0, 0], $color->pluck('clases_dictadas')->all());

        $this->inscripcion('alumno2@nep30.test')->update(['estado' => 'baja', 'fecha_baja' => today(), 'motivo_baja' => 'Prueba']);

        $this->assertSame(['Alumna Uno'], AsistenciaDelAlumno::delCurso($this->asignacion('Módulo 2: Coloración'))->pluck('nombre')->all());
    }

    public function test_sin_inscripciones_activas_no_devuelve_nada(): void
    {
        $this->assertTrue(AsistenciaDelAlumno::de($this->usuario('profesor@nep30.test'))->isEmpty());
    }
}