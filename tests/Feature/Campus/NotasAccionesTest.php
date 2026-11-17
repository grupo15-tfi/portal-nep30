<?php

namespace Tests\Feature\Campus;

use App\Actions\Notas\CerrarModulo;
use App\Actions\Notas\GuardarNota;
use App\Models\CursoProfesor;
use App\Models\Evaluacion;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU11 (#89): cargar, corregir notas y cerrar el módulo.
 * Datos de DatosDePruebaSeeder:
 *   - Profesora (profesor@nep30.test) con «Módulo 1: Corte y peinado» y «Módulo 2: Coloración» en Peluquería.
 *   - Alumna Uno (alumno@nep30.test) y Alumno Dos (alumno2@nep30.test) inscriptos en Peluquería.
 *   - Alumna Uno tiene un parcial con 8 en Corte (20/04/2026) y estuvo presente en la única clase.
 *   - Plan de Peluquería: se acredita con 6 y 80 % de asistencia.
 */
class NotasAccionesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $profesora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profesora = User::where('email', 'profesor@nep30.test')->firstOrFail();
        $this->actingAs($this->profesora);
    }

    /** La materia «Corte y peinado» que cursa un alumno */
    private function corteDe(string $email): InscripcionMateria
    {
        $alumno = User::where('email', $email)->firstOrFail();
        $corte = Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id');

        return InscripcionMateria::whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $alumno->id))
            ->where('materia_id', $corte)
            ->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        return [
            'inscripcion_materia_id' => $this->corteDe('alumno2@nep30.test')->id,
            'tipo'                   => 'parcial',
            'nota'                   => 7.5,
            'fecha'                  => '2026-05-04',
            ...$cambios,
        ];
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

    public function test_la_profesora_carga_una_nota_en_su_materia(): void
    {
        $nota = GuardarNota::ejecutar($this->datos());

        $this->assertSame('7.50', $nota->fresh()->nota);
        $this->assertSame($this->profesora->id, $nota->cargada_por);
        $this->assertSame(1, $this->corteDe('alumno2@nep30.test')->evaluaciones()->count());
    }

    public function test_nota_fuera_de_rango_o_con_mas_de_dos_decimales(): void
    {
        foreach ([0, 11, 7.555, 'siete'] as $mala) {
            $this->assertArrayHasKey('nota', $this->errores(fn () => GuardarNota::ejecutar($this->datos(['nota' => $mala]))));
        }
        $this->assertSame(0, Evaluacion::where('cargada_por', $this->profesora->id)->where('fecha', '2026-05-04')->count());
    }

    public function test_tipo_que_no_existe_y_fecha_futura(): void
    {
        $this->assertArrayHasKey('tipo', $this->errores(fn () => GuardarNota::ejecutar($this->datos(['tipo' => 'oral']))));
        $this->assertArrayHasKey('fecha', $this->errores(fn () => GuardarNota::ejecutar($this->datos(['fecha' => now()->addDay()->toDateString()]))));
    }

    public function test_un_profesor_no_carga_notas_en_una_materia_ajena(): void
    {
        $this->actingAs(User::factory()->conRol('profesor')->create());

        $this->assertArrayHasKey('inscripcion_materia_id', $this->errores(fn () => GuardarNota::ejecutar($this->datos())));
        $this->assertSame(0, $this->corteDe('alumno2@nep30.test')->evaluaciones()->count());
    }

    public function test_el_recuperatorio_solo_despues_de_un_parcial_desaprobado(): void
    {
        // Alumna Uno aprobó el parcial (8): no le corresponde recuperatorio
        $im = $this->corteDe('alumno@nep30.test')->id;
        $this->assertArrayHasKey('tipo', $this->errores(fn () => GuardarNota::ejecutar(
            $this->datos(['inscripcion_materia_id' => $im, 'tipo' => 'recuperatorio', 'fecha' => '2026-05-10'])
        )));

        // Alumno Dos desaprueba (4) y después rinde el recuperatorio
        GuardarNota::ejecutar($this->datos(['nota' => 4, 'fecha' => '2026-05-04']));
        $recu = GuardarNota::ejecutar($this->datos(['tipo' => 'recuperatorio', 'nota' => 7, 'fecha' => '2026-05-11']));

        $this->assertSame('recuperatorio', $recu->tipo);
    }

    public function test_no_se_repite_la_misma_nota_el_mismo_dia(): void
    {
        GuardarNota::ejecutar($this->datos());

        $this->assertArrayHasKey('fecha', $this->errores(fn () => GuardarNota::ejecutar($this->datos(['nota' => 9]))));
    }

    public function test_corregir_una_nota_queda_en_la_auditoria_con_el_valor_anterior(): void
    {
        DB::statement('SET @app_usuario_id = ?', [$this->profesora->id]);   // lo hace el middleware en cada pedido
        $nota = $this->corteDe('alumno@nep30.test')->evaluaciones()->sole();   // el parcial con 8

        GuardarNota::ejecutar([...$nota->only('inscripcion_materia_id', 'tipo'), 'nota' => 9, 'fecha' => '2026-04-20'], $nota);

        $auditoria = DB::table('auditoria')->where(['tabla' => 'evaluaciones', 'registro_id' => $nota->id, 'accion' => 'modificacion'])->sole();
        $this->assertSame($this->profesora->id, $auditoria->usuario_id);
        $this->assertEquals(8, json_decode($auditoria->valores_anteriores)->nota);
        $this->assertEquals(9, json_decode($auditoria->valores_nuevos)->nota);
        $this->assertSame('9.00', $nota->fresh()->nota);
    }

    public function test_al_corregir_no_se_pasa_la_nota_a_otro_alumno(): void
    {
        $nota = $this->corteDe('alumno@nep30.test')->evaluaciones()->sole();

        $this->assertArrayHasKey('inscripcion_materia_id', $this->errores(fn () => GuardarNota::ejecutar($this->datos(), $nota)));
    }

    public function test_cerrar_el_modulo_con_6_o_mas_lo_acredita(): void
    {
        $im = CerrarModulo::ejecutar($this->corteDe('alumno@nep30.test'), 7);

        $this->assertSame('acreditada', $im->estado);
        $this->assertSame('7.00', $im->nota_final);
        $this->assertTrue($im->fecha_acreditacion->isToday());
    }

    public function test_cerrar_el_modulo_con_menos_de_6_queda_pendiente(): void
    {
        $im = CerrarModulo::ejecutar($this->corteDe('alumno@nep30.test'), 5);

        $this->assertSame('pendiente', $im->estado);
        $this->assertSame('5.00', $im->nota_final);
        $this->assertNull($im->fecha_acreditacion);
    }

    public function test_sin_el_80_por_ciento_de_asistencia_queda_pendiente(): void
    {
        // A Alumno Dos se le agregan 4 clases dictadas con ausente: queda en 1 de 5 (20 %)
        $im = $this->corteDe('alumno2@nep30.test');
        $cp = CursoProfesor::where('profesor_id', $this->profesora->id)->where('materia_id', $im->materia_id)->value('id');
        foreach (['2026-03-16', '2026-03-23', '2026-03-30', '2026-04-06'] as $fecha) {
            $clase = DB::table('clases')->insertGetId(['curso_profesor_id' => $cp, 'fecha' => $fecha, 'hora_inicio' => '14:00', 'estado' => 'dictada']);
            DB::table('asistencias')->insert(['clase_id' => $clase, 'inscripcion_id' => $im->inscripcion_id, 'estado' => 'ausente']);
        }

        $cerrado = CerrarModulo::ejecutar($im, 9);

        $this->assertSame('pendiente', $cerrado->estado);
    }

    public function test_un_modulo_acreditado_no_se_vuelve_a_cerrar_ni_lo_cierra_otro_profesor(): void
    {
        CerrarModulo::ejecutar($this->corteDe('alumno@nep30.test'), 8);
        $this->assertArrayHasKey('nota_final', $this->errores(fn () => CerrarModulo::ejecutar($this->corteDe('alumno@nep30.test'), 9)));

        $this->actingAs(User::factory()->conRol('profesor')->create());
        $this->assertArrayHasKey('inscripcion_materia_id', $this->errores(fn () => CerrarModulo::ejecutar($this->corteDe('alumno2@nep30.test'), 8)));
    }
}