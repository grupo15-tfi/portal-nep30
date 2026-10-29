<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HU02 (#44): oferta educativa por sede y calendario académico. Páginas públicas, sin sesión.
 *
 * La oferta sale de la vista SQL v_oferta_publica, que ya filtra cursos, planes y sedes activos
 * y el período lectivo activo, y calcula los cupos disponibles. No se repiten esas condiciones acá.
 */
class OfertaController extends Controller
{
    /** GET /oferta (?sede=ID): cursos vigentes agrupados por sede */
    public function index(Request $request)
    {
        $sedeId = filter_var($request->query('sede'), FILTER_VALIDATE_INT) ?: null;

        // Sedes que tienen al menos un curso vigente (para el filtro)
        $sedes = DB::table('v_oferta_publica')
            ->select('sede_id', 'sede', 'localidad')
            ->distinct()
            ->orderBy('sede')
            ->get();

        // Un ID que no está en la oferta se ignora y se muestra todo
        if ($sedeId && ! $sedes->contains('sede_id', $sedeId)) {
            $sedeId = null;
        }

        // Primero la sede central, después las sedes y los anexos
        $cursos = DB::table('v_oferta_publica as o')
            ->join('sedes as s', 's.id', '=', 'o.sede_id')
            ->select('o.*')
            ->when($sedeId, fn ($q) => $q->where('o.sede_id', $sedeId))
            ->orderByRaw("FIELD(s.tipo, 'central', 'sede', 'anexo')")
            ->orderBy('o.sede')
            ->orderByRaw("FIELD(o.tipo, 'formacion_profesional', 'terminalidad')")
            ->orderBy('o.plan')
            ->orderByRaw("FIELD(o.turno, 'mañana', 'tarde', 'noche')")
            ->get();

        return view('publico.oferta.index', [
            'porSede'  => $cursos->groupBy('sede_id'),
            'sedes'    => $sedes,
            'sedeId'   => $sedeId,
            'total'    => $cursos->count(),
            'proximos' => Evento::publicos()->where('fecha_inicio', '>=', now()->startOfDay())->limit(3)->get(),
        ]);
    }

    /** GET /oferta/{curso}: detalle con las materias del plan */
    public function curso(int $curso)
    {
        $oferta = DB::table('v_oferta_publica')->where('curso_id', $curso)->first();
        abort_if(! $oferta, 404);

        $planId = DB::table('cursos')->where('id', $curso)->value('plan_id');

        return view('publico.oferta.curso', [
            'curso'       => $oferta,
            'descripcion' => DB::table('planes_estudio')->where('id', $planId)->value('descripcion'),
            'materias'    => Materia::where('plan_id', $planId)->orderBy('ciclo')->orderBy('nombre')->get(),
        ]);
    }

    /** GET /preinscripcion/{curso} → preinscripcion.crear (HU04, #52): solo cursos de la oferta pública */
    public function preinscripcion(int $curso)
    {
        $oferta = DB::table('v_oferta_publica')->where('curso_id', $curso)->first();
        abort_if(! $oferta, 404);

        return view('publico.preinscripcion.crear', ['curso' => $oferta]);
    }

    /** GET /calendario (?mes=AAAA-MM): eventos públicos del mes */
    public function calendario(Request $request)
    {
        $mes = $request->query('mes');
        $inicio = (is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes))
            ? Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfDay()
            : now()->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        return view('publico.oferta.calendario', [
            'inicio'   => $inicio->locale('es'),
            'eventos'  => Evento::publicos()->entre($inicio, $fin)->get(),
            'anterior' => $inicio->copy()->subMonth()->format('Y-m'),
            'siguiente' => $inicio->copy()->addMonth()->format('Y-m'),
        ]);
    }
}
