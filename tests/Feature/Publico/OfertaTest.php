<?php

namespace Tests\Feature\Publico;

use App\Models\Curso;
use App\Models\PlanEstudio;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HU02 (#44): oferta educativa por sede y calendario académico (RF03, RF07).
 * Datos de DatosDePruebaSeeder, período 2026 activo:
 *   - Sede Central (prueba): Peluquería (tarde, cupo 2 lleno → lista de espera) y Operador de Informática (mañana, cupo 20).
 *   - Anexo Barrio Norte (prueba): Primaria para adultos: Alfabetización (noche, cupo 25).
 *   - Evento público «Inicio de clases 2026» el 02/03/2026.
 */
class OfertaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function curso(string $plan): Curso
    {
        return Curso::where('plan_id', PlanEstudio::where('nombre', $plan)->value('id'))->firstOrFail();
    }

    private function sede(string $nombre): Sede
    {
        return Sede::where('nombre', $nombre)->firstOrFail();
    }

    private function evento(array $datos): void
    {
        DB::table('eventos')->insert(array_merge([
            'tipo' => 'institucional', 'publico' => true,
            'creado_por' => User::role('administrador')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ], $datos));
    }

    // ---------- Oferta ----------

    public function test_la_oferta_muestra_los_cursos_vigentes_con_sede_turno_horario_y_cupos(): void
    {
        $this->get(route('oferta'))
            ->assertOk()
            ->assertSee('Peluquería')
            ->assertSee('Operador de Informática para la Administración y Gestión')
            ->assertSee('Primaria para adultos: Alfabetización')
            ->assertSee('Sede Central (prueba)')
            ->assertSee('Anexo Barrio Norte (prueba)')
            ->assertSee('Turno mañana')
            ->assertSee('08:00 a 12:00')
            ->assertSee('20 cupos disponibles');
    }

    public function test_el_filtro_por_sede_muestra_solo_los_cursos_de_esa_sede(): void
    {
        $anexo = $this->sede('Anexo Barrio Norte (prueba)');

        $this->get(route('oferta', ['sede' => $anexo->id]))
            ->assertOk()
            ->assertSee('Primaria para adultos: Alfabetización')
            ->assertDontSee('Peluquería')
            ->assertDontSee('Operador de Informática para la Administración y Gestión');
    }

    public function test_un_filtro_de_sede_invalido_muestra_toda_la_oferta(): void
    {
        $this->get(route('oferta', ['sede' => 'abc']))->assertOk()->assertSee('Peluquería')->assertSee('Primaria para adultos: Alfabetización');
    }

    public function test_un_curso_sin_cupo_muestra_lista_de_espera(): void
    {
        $peluqueria = $this->curso('Peluquería');
        $this->assertSame(0, $peluqueria->cupo_disponible);

        $html = $this->get(route('oferta'))->assertOk()->getContent();

        // Dentro de la tarjeta de Peluquería dice «Lista de espera» y no «cupos disponibles»
        $tarjeta = $this->tarjeta($html, $peluqueria->id);
        $this->assertStringContainsString('Lista de espera', $tarjeta);
        $this->assertStringNotContainsString('cupos disponibles', $tarjeta);
    }

    public function test_curso_plan_sede_o_periodo_inactivo_no_aparece(): void
    {
        $pelu = $this->curso('Peluquería');
        $pelu->update(['estado' => 'inactivo']);
        $this->get(route('oferta'))->assertDontSee('Peluquería')->assertSee('Operador de Informática');

        PlanEstudio::where('nombre', 'Operador de Informática para la Administración y Gestión')->update(['activo' => false]);
        $this->get(route('oferta'))->assertDontSee('Operador de Informática');

        $this->sede('Anexo Barrio Norte (prueba)')->update(['activo' => false]);
        $this->get(route('oferta'))->assertDontSee('Primaria para adultos: Alfabetización')->assertSee('Todavía no hay cursos');
    }

    public function test_un_periodo_cerrado_saca_sus_cursos(): void
    {
        DB::table('periodos_lectivos')->update(['estado' => 'cerrado']);

        $this->get(route('oferta'))->assertOk()->assertDontSee('Peluquería')->assertSee('Todavía no hay cursos');
    }

    // ---------- Detalle del curso ----------

    public function test_el_detalle_muestra_las_materias_del_plan_y_los_datos_del_curso(): void
    {
        $pelu = $this->curso('Peluquería');

        $this->get(route('oferta.curso', $pelu->id))
            ->assertOk()
            ->assertSee('Peluquería')
            ->assertSee('Módulo 1: Corte y peinado')
            ->assertSee('Módulo 2: Coloración')
            ->assertSee('Sede Central (prueba)')
            ->assertSee('14:00 a 18:00')
            ->assertSee('Lista de espera')
            ->assertSee('18 años');
    }

    public function test_el_detalle_de_un_curso_inactivo_o_inexistente_da_404(): void
    {
        $pelu = $this->curso('Peluquería');
        $pelu->update(['estado' => 'inactivo']);

        $this->get(route('oferta.curso', $pelu->id))->assertNotFound();
        $this->get('/oferta/999999')->assertNotFound();
    }

    public function test_con_la_ruta_de_hu04_aparece_el_enlace_preinscribirme(): void
    {
        // HU04 (#52) ya define preinscripcion.crear → GET /preinscripcion/{curso}: el aviso «disponible pronto» no sale más
        $this->assertTrue(Route::has('preinscripcion.crear'));

        $info = $this->curso('Operador de Informática para la Administración y Gestión');
        $pelu = $this->curso('Peluquería');

        $this->get(route('oferta'))
            ->assertSee(route('preinscripcion.crear', $info->id), false)
            ->assertSee(route('preinscripcion.crear', $pelu->id), false);

        $this->get(route('oferta.curso', $info->id))->assertSee('Preinscribirme')->assertSee(route('preinscripcion.crear', $info->id), false);
        $this->get(route('oferta.curso', $pelu->id))->assertSee('Anotarme en la lista de espera');
        $this->get(route('oferta.curso', $info->id))->assertDontSee('La preinscripción en línea va a estar disponible pronto');
    }

    // ---------- Calendario ----------

    public function test_el_calendario_muestra_los_eventos_publicos_del_mes(): void
    {
        $this->get(route('calendario', ['mes' => '2026-03']))
            ->assertOk()
            ->assertSee('Inicio de clases 2026')
            ->assertSee('Marzo 2026');
    }

    public function test_un_evento_no_publico_o_de_un_curso_no_aparece(): void
    {
        $this->evento(['titulo' => 'Reunión de profesores', 'fecha_inicio' => '2026-03-10 18:00:00', 'publico' => false]);
        $this->evento(['titulo' => 'Parcial de Corte', 'tipo' => 'evaluacion', 'fecha_inicio' => '2026-03-12 14:00:00',
            'publico' => false, 'curso_id' => $this->curso('Peluquería')->id]);

        $this->get(route('calendario', ['mes' => '2026-03']))
            ->assertSee('Inicio de clases 2026')
            ->assertDontSee('Reunión de profesores')
            ->assertDontSee('Parcial de Corte');
    }

    public function test_los_eventos_se_ordenan_por_fecha_y_se_navega_entre_meses(): void
    {
        $this->evento(['titulo' => 'Feriado de prueba', 'tipo' => 'feriado', 'fecha_inicio' => '2026-03-24 00:00:00']);
        $this->evento(['titulo' => 'Evento de abril', 'fecha_inicio' => '2026-04-15 10:00:00']);

        $html = $this->get(route('calendario', ['mes' => '2026-03']))
            ->assertSee(route('calendario', ['mes' => '2026-02']), false)
            ->assertSee(route('calendario', ['mes' => '2026-04']), false)
            ->assertDontSee('Evento de abril')
            ->getContent();

        $this->assertLessThan(strpos($html, 'Feriado de prueba'), strpos($html, 'Inicio de clases 2026'));
    }

    public function test_un_mes_invalido_muestra_el_mes_actual(): void
    {
        $this->get(route('calendario', ['mes' => 'cualquiera']))
            ->assertOk()
            ->assertSee(ucfirst(now()->locale('es')->translatedFormat('F Y')));
    }

    // ---------- Menú ----------

    public function test_el_menu_publico_enlaza_la_oferta_educativa(): void
    {
        $this->get('/')->assertSee('href="'.route('oferta').'"', false);
        $this->get(route('oferta'))->assertSee(route('calendario'), false);
    }

    /** Recorta el HTML de la tarjeta del curso (marcada con data-curso) */
    private function tarjeta(string $html, int $cursoId): string
    {
        $inicio = strpos($html, 'data-curso="'.$cursoId.'"');
        $this->assertNotFalse($inicio, "No se encontró la tarjeta del curso {$cursoId}");

        return substr($html, $inicio, strpos($html, '</article>', $inicio) - $inicio);
    }
}
