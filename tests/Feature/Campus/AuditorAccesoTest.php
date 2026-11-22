<?php

namespace Tests\Feature\Campus;

use App\Models\CursoProfesor;
use App\Models\Inscripcion;
use App\Models\Sede;
use App\Models\User;
use App\Queries\ResumenPorSede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HU18 (#129): el Auditor consulta todo y no entra a ninguna pantalla de otro rol.
 * Datos de DatosDePruebaSeeder: auditor@nep30.test y una sede central y un anexo con cursos,
 * dos alumnos activos en Peluquería, dos aspirantes en lista de espera y una preinscripción pendiente.
 */
class AuditorAccesoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ── ResumenPorSede ─────────────────────────────────────

    public function test_el_resumen_trae_cada_sede_con_los_numeros_de_la_vista(): void
    {
        $filas = ResumenPorSede::todas();
        $vista = DB::table('v_resumen_sedes')->get();

        $this->assertCount($vista->count(), $filas);
        $this->assertSame(
            ['sede_id', 'sede', 'activo', 'cursos_activos', 'alumnos_activos', 'preinscripciones_pendientes', 'en_lista_espera'],
            array_keys($filas->first())
        );
        $this->assertSame((int) $vista->sum('alumnos_activos'), $filas->sum('alumnos_activos'));
        $this->assertSame(2, $filas->sum('alumnos_activos'));
        $this->assertSame(2, $filas->sum('en_lista_espera'));
        $this->assertSame(1, $filas->sum('preinscripciones_pendientes'));
    }

    public function test_ordenado_por_nombre_e_incluye_las_sedes_inactivas(): void
    {
        $sede = Sede::query()->orderBy('id')->firstOrFail();
        $sede->update(['activo' => false]);

        $filas = ResumenPorSede::todas();
        $nombres = $filas->pluck('sede')->all();
        $ordenados = $nombres;
        sort($ordenados, SORT_STRING | SORT_FLAG_CASE);

        $this->assertSame($ordenados, $nombres);
        $this->assertFalse($filas->firstWhere('sede_id', $sede->id)['activo']);
    }

    // ── Ruta auditor.sedes ─────────────────────────────────

    public function test_la_ruta_del_resumen_es_solo_del_auditor(): void
    {
        $url = route('auditor.sedes');

        $this->get($url)->assertRedirect(route('login'));

        foreach (['admin@nep30.test', 'profesor@nep30.test', 'alumno@nep30.test'] as $email) {
            $this->actingAs($this->usuario($email))->get($url)->assertForbidden();
        }
    }

    // ── El Auditor no entra a pantallas de otros roles ─────

    public function test_el_auditor_recibe_403_en_todas_las_pantallas_de_admin_profesor_y_alumno(): void
    {
        $this->actingAs($this->usuario('auditor@nep30.test'));

        // Valores reales para las rutas con parámetros, así responde el permiso y no un 404
        $parametros = [
            'profesor'      => $this->usuario('profesor@nep30.test')->id,
            'cursoProfesor' => CursoProfesor::query()->value('id'),
            'inscripcion'   => Inscripcion::query()->value('id'),
        ];

        $revisadas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Ruta $r) => in_array('GET', $r->methods())
                && in_array(explode('.', (string) $r->getName())[0], ['admin', 'profesor', 'alumno'], true))
            ->each(function (Ruta $r) use ($parametros) {
                $url = route($r->getName(), array_intersect_key($parametros, array_flip($r->parameterNames())));
                $this->assertSame(403, $this->get($url)->status(), "El Auditor no debería entrar a {$r->getName()} ({$url})");
            });

        // Que la prueba de verdad recorra las rutas (si alguien las renombra, se nota)
        $this->assertGreaterThanOrEqual(15, $revisadas->count());
    }
}