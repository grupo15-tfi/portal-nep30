<?php

namespace Tests\Feature\Campus;

use App\Actions\Notas\CerrarModulo;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Queries\HistorialAcademico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU17 (#125): historial académico leído de las vistas v_historial_academico y v_historial_resumen.
 * Datos de DatosDePruebaSeeder: Alumna Uno y Alumno Dos, inscriptos en Peluquería (2026) con
 * «Módulo 1: Corte y peinado» (una clase, presente o justificado) y «Módulo 2: Coloración» (sin clases).
 */
class HistorialConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function inscripcion(string $email): Inscripcion
    {
        return Inscripcion::where('alumno_id', $this->usuario($email)->id)->orderBy('id')->firstOrFail();
    }

    private function modulo(string $email, string $materia): InscripcionMateria
    {
        return InscripcionMateria::where('inscripcion_id', $this->inscripcion($email)->id)
            ->where('materia_id', Materia::where('nombre', $materia)->value('id'))->firstOrFail();
    }

    /** Corte y peinado acreditado con 7 y Coloración cerrada con 5 (pendiente), como lo haría la profesora */
    private function cerrarModulos(): void
    {
        $this->actingAs($this->usuario('profesor@nep30.test'));
        CerrarModulo::ejecutar($this->modulo('alumno@nep30.test', 'Módulo 1: Corte y peinado'), 7);
        CerrarModulo::ejecutar($this->modulo('alumno@nep30.test', 'Módulo 2: Coloración'), 5);
    }

    /** Otra inscripción de la alumna en otro curso (Informática o Alfabetización), con la fecha y el estado que se pida */
    private function otraInscripcion(string $plan, string $fecha, string $estado): Inscripcion
    {
        $curso = Curso::where('plan_id', PlanEstudio::where('nombre', 'like', "%{$plan}%")->value('id'))->firstOrFail();
        $id = DB::table('inscripciones')->insertGetId([
            'alumno_id' => $this->usuario('alumno@nep30.test')->id, 'curso_id' => $curso->id,
            'fecha_inscripcion' => $fecha, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $insc = Inscripcion::findOrFail($id);
        if ($estado !== 'activa') {
            $insc->update(['estado' => $estado, 'fecha_baja' => $estado === 'baja' ? now()->toDateString() : null]);
        }

        return $insc->fresh();
    }

    // ── de() ───────────────────────────────────────────────

    public function test_el_historial_trae_los_datos_del_alumno_y_de_la_inscripcion(): void
    {
        $h = HistorialAcademico::de($this->inscripcion('alumno@nep30.test'));

        $this->assertSame(['dni' => '30000001', 'apellido' => 'Prueba', 'nombre' => 'Alumna Uno'], $h['alumno']);
        $this->assertStringContainsString('Peluquer', $h['plan']);
        $this->assertNotEmpty($h['sede']);
        $this->assertIsInt($h['anio']);
        $this->assertSame('activa', $h['estado_inscripcion']);
        $this->assertTrue($h['emitido_en']->isToday());
        $this->assertSame(['alumno', 'plan', 'sede', 'anio', 'estado_inscripcion', 'modulos', 'resumen', 'emitido_en'], array_keys($h));
    }

    public function test_un_modulo_acreditado_uno_pendiente_y_uno_sin_clases(): void
    {
        $this->cerrarModulos();

        $modulos = HistorialAcademico::de($this->inscripcion('alumno@nep30.test'))['modulos'];

        $this->assertSame(['Módulo 1: Corte y peinado', 'Módulo 2: Coloración'], array_column($modulos, 'materia'));

        [$corte, $color] = $modulos;
        $this->assertSame('acreditada', $corte['estado']);
        $this->assertSame(7.0, $corte['nota_final']);
        $this->assertSame(now()->toDateString(), $corte['fecha_acreditacion']);
        $this->assertSame(100.0, $corte['asistencia']);

        $this->assertSame('pendiente', $color['estado']);
        $this->assertSame(5.0, $color['nota_final']);
        $this->assertNull($color['fecha_acreditacion']);
        $this->assertNull($color['asistencia']);   // sin clases dictadas
    }

    public function test_el_resumen_sale_de_la_vista(): void
    {
        $this->cerrarModulos();

        $resumen = HistorialAcademico::de($this->inscripcion('alumno@nep30.test'))['resumen'];
        $vista = DB::table('v_historial_resumen')->where('inscripcion_id', $this->inscripcion('alumno@nep30.test')->id)->first();

        $this->assertSame(['modulos_acreditados' => 1, 'promedio' => 7.0, 'modulos_pendientes' => (int) $vista->modulos_pendientes], $resumen);
        $this->assertGreaterThanOrEqual(1, $resumen['modulos_pendientes']);
    }

    public function test_sin_modulos_acreditados_el_promedio_es_null(): void
    {
        $resumen = HistorialAcademico::de($this->inscripcion('alumno@nep30.test'))['resumen'];

        $this->assertSame(0, $resumen['modulos_acreditados']);
        $this->assertNull($resumen['promedio']);
    }

    public function test_no_mezcla_datos_de_otro_alumno(): void
    {
        $this->cerrarModulos();   // solo de Alumna Uno

        $h = HistorialAcademico::de($this->inscripcion('alumno2@nep30.test'));

        $this->assertSame('Alumno Dos', $h['alumno']['nombre']);
        $this->assertSame(['cursando', 'cursando'], array_column($h['modulos'], 'estado'));
        $this->assertSame(0, $h['resumen']['modulos_acreditados']);
    }

    // ── inscripcionesDe() ──────────────────────────────────

    public function test_inscripciones_activas_finalizadas_y_de_baja_la_mas_reciente_primero(): void
    {
        $vieja = $this->otraInscripcion('Informática', '2026-03-01', 'finalizada');
        $actual = $this->inscripcion('alumno@nep30.test');   // 2026-03-02, activa
        $nueva = $this->otraInscripcion('Alfabetización', '2026-03-05', 'baja');

        $filas = HistorialAcademico::inscripcionesDe($this->usuario('alumno@nep30.test'));

        $this->assertSame([$nueva->id, $actual->id, $vieja->id], $filas->pluck('inscripcion_id')->all());
        $this->assertSame(['baja', 'activa', 'finalizada'], $filas->pluck('estado')->all());
        $this->assertSame(['inscripcion_id', 'plan', 'sede', 'anio', 'estado'], array_keys($filas->first()));
    }

    public function test_un_alumno_ve_solo_sus_inscripciones(): void
    {
        $filas = HistorialAcademico::inscripcionesDe($this->usuario('alumno2@nep30.test'));

        $this->assertSame([$this->inscripcion('alumno2@nep30.test')->id], $filas->pluck('inscripcion_id')->all());
        $this->assertCount(0, HistorialAcademico::inscripcionesDe($this->usuario('profesor@nep30.test')));
    }
}