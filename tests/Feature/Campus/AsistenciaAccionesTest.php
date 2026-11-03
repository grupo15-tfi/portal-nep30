<?php

namespace Tests\Feature\Campus;

use App\Actions\Asistencia\GuardarClase;
use App\Actions\Asistencia\TomarAsistencia;
use App\Models\Clase;
use App\Models\CursoProfesor;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU13 (#92): clases y toma de asistencia.
 * Datos de DatosDePruebaSeeder:
 *   - Profesora (profesor@nep30.test) con «Módulo 1: Corte y peinado» en Peluquería.
 *   - Alumna Uno (alumno@nep30.test) y Alumno Dos (alumno2@nep30.test) inscriptos en Peluquería.
 *   - Una clase de Corte el 09/03/2026 a las 14:00, dictada: Alumna Uno presente y Alumno Dos justificado.
 */
class AsistenciaAccionesTest extends TestCase
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

    /** La asignación de la profesora en «Corte y peinado» */
    private function corte(): CursoProfesor
    {
        return CursoProfesor::where('profesor_id', $this->profesora->id)
            ->where('materia_id', Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id'))
            ->firstOrFail();
    }

    private function inscripcion(string $email): Inscripcion
    {
        return Inscripcion::where('alumno_id', User::where('email', $email)->value('id'))->firstOrFail();
    }

    private function claseDePrueba(): Clase
    {
        return Clase::where('curso_profesor_id', $this->corte()->id)->whereDate('fecha', '2026-03-09')->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        return ['fecha' => '2026-05-04', 'hora_inicio' => '14:00', 'hora_fin' => '18:00', 'tema' => 'Cortes en capas', 'estado' => 'dictada', ...$cambios];
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

    public function test_la_profesora_registra_una_clase_de_su_materia(): void
    {
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos());

        $this->assertSame($this->corte()->id, $clase->curso_profesor_id);
        $this->assertSame('2026-05-04', $clase->fresh()->fecha->toDateString());
        $this->assertSame('Cortes en capas', $clase->tema);
        $this->assertSame('dictada', $clase->estado);
    }

    public function test_otro_profesor_no_registra_clases_en_una_materia_ajena(): void
    {
        $this->actingAs(User::factory()->conRol('profesor')->create());

        $this->assertArrayHasKey('clase', $this->errores(fn () => GuardarClase::ejecutar($this->corte(), $this->datos())));
    }

    public function test_fecha_futura_estado_invalido_y_horario_al_reves(): void
    {
        $this->assertArrayHasKey('fecha', $this->errores(fn () => GuardarClase::ejecutar($this->corte(), $this->datos(['fecha' => now()->addDay()->toDateString()]))));
        $this->assertArrayHasKey('estado', $this->errores(fn () => GuardarClase::ejecutar($this->corte(), $this->datos(['estado' => 'cancelada']))));
        $this->assertArrayHasKey('hora_fin', $this->errores(fn () => GuardarClase::ejecutar($this->corte(), $this->datos(['hora_fin' => '13:00']))));
    }

    public function test_no_se_repite_la_clase_el_mismo_dia_y_hora_tampoco_sin_hora(): void
    {
        // Ya existe la del 09/03 a las 14:00
        $this->assertArrayHasKey('fecha', $this->errores(fn () => GuardarClase::ejecutar($this->corte(), $this->datos(['fecha' => '2026-03-09']))));

        // Sin hora: la base no lo frena (NULL en el índice único), la acción sí
        $sinHora = $this->datos(['hora_inicio' => null, 'hora_fin' => null]);
        GuardarClase::ejecutar($this->corte(), $sinHora);
        $this->assertArrayHasKey('fecha', $this->errores(fn () => GuardarClase::ejecutar($this->corte(), $sinHora)));
    }

    public function test_editar_una_clase_y_marcarla_suspendida(): void
    {
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos());

        GuardarClase::ejecutar($this->corte(), $this->datos(['tema' => 'Feriado puente', 'estado' => 'suspendida']), $clase);

        $this->assertSame('suspendida', $clase->fresh()->estado);
        $this->assertSame('Feriado puente', $clase->fresh()->tema);
        $this->assertSame(2, Clase::where('curso_profesor_id', $this->corte()->id)->count());
    }

    public function test_tomar_asistencia_de_una_clase_nueva(): void
    {
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos());

        TomarAsistencia::ejecutar($clase, [
            $this->inscripcion('alumno@nep30.test')->id  => 'presente',
            $this->inscripcion('alumno2@nep30.test')->id => 'tarde',
        ]);

        $this->assertSame(['presente', 'tarde'], $clase->asistencias()->orderBy('inscripcion_id')->pluck('estado')->all());
    }

    public function test_corregir_la_asistencia_queda_en_la_auditoria_solo_si_cambia(): void
    {
        DB::statement('SET @app_usuario_id = ?', [$this->profesora->id]);   // lo hace el middleware en cada pedido
        $clase = $this->claseDePrueba();
        $alumno2 = $this->inscripcion('alumno2@nep30.test');

        TomarAsistencia::ejecutar($clase, [
            $this->inscripcion('alumno@nep30.test')->id => 'presente',   // no cambia
            $alumno2->id                                => 'ausente',    // justificado → ausente
        ]);

        $auditoria = DB::table('auditoria')->where(['tabla' => 'asistencias', 'accion' => 'modificacion'])->get();
        $this->assertCount(1, $auditoria);
        $this->assertSame('justificado', json_decode($auditoria->first()->valores_anteriores)->estado);
        $this->assertSame('ausente', json_decode($auditoria->first()->valores_nuevos)->estado);
        $this->assertSame($this->profesora->id, $auditoria->first()->usuario_id);
    }

    public function test_un_alumno_de_otro_curso_o_dado_de_baja_da_error_y_no_se_guarda_nada(): void
    {
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos());
        $baja = $this->inscripcion('alumno2@nep30.test');
        $baja->update(['estado' => 'baja', 'fecha_baja' => today(), 'motivo_baja' => 'Prueba']);

        $errores = $this->errores(fn () => TomarAsistencia::ejecutar($clase, [
            $this->inscripcion('alumno@nep30.test')->id => 'presente',
            $baja->id                                   => 'presente',
            999999                                      => 'presente',
        ]));

        $this->assertArrayHasKey("estados.{$baja->id}", $errores);
        $this->assertArrayHasKey('estados.999999', $errores);
        $this->assertSame(0, $clase->asistencias()->count());
    }

    public function test_estado_de_asistencia_que_no_existe(): void
    {
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos());
        $id = $this->inscripcion('alumno@nep30.test')->id;

        $this->assertArrayHasKey("estados.{$id}", $this->errores(fn () => TomarAsistencia::ejecutar($clase, [$id => 'faltó'])));
    }

    public function test_en_una_clase_suspendida_no_se_toma_asistencia(): void
    {
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos(['estado' => 'suspendida']));

        $this->assertArrayHasKey('clase', $this->errores(fn () => TomarAsistencia::ejecutar($clase, [
            $this->inscripcion('alumno@nep30.test')->id => 'presente',
        ])));
    }

    public function test_otro_profesor_no_toma_asistencia_en_una_clase_ajena(): void
    {
        $this->actingAs(User::factory()->conRol('profesor')->create());

        $this->assertArrayHasKey('clase', $this->errores(fn () => TomarAsistencia::ejecutar($this->claseDePrueba(), [
            $this->inscripcion('alumno@nep30.test')->id => 'ausente',
        ])));
    }

    public function test_una_clase_suspendida_no_cuenta_para_el_porcentaje(): void
    {
        $alumna = $this->inscripcion('alumno@nep30.test');
        $clase = GuardarClase::ejecutar($this->corte(), $this->datos());
        TomarAsistencia::ejecutar($clase, [$alumna->id => 'ausente']);

        $porcentaje = fn () => DB::table('v_asistencia_porcentaje')
            ->where(['inscripcion_id' => $alumna->id, 'materia_id' => $this->corte()->materia_id])->first();
        $this->assertSame(2, (int) $porcentaje()->clases_dictadas);   // 1 presente + 1 ausente = 50 %

        GuardarClase::ejecutar($this->corte(), $this->datos(['estado' => 'suspendida']), $clase);
        $this->assertSame(1, (int) $porcentaje()->clases_dictadas);   // vuelve a 100 %
        $this->assertEquals(100, $porcentaje()->porcentaje);
    }
}