<?php

namespace App\Queries;

use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * #156: las tarjetas del Inicio del panel, según el rol. Cada tarjeta lleva a su pantalla y el número
 * sale de la misma tabla o consulta que usa esa pantalla, así coinciden. Una tarjeta cuya ruta todavía
 * no existe no se muestra (como el menú del panel).
 *
 * para(User $usuario): array → tarjetas (titulo, valor, detalle, ruta, tono) y proximas (solo alumno:
 * hasta 3 tareas sin entregar y sin vencer, la más cercana primero: titulo, materia, fecha_entrega).
 * tono: 'primario' | 'exito' | 'aviso' (el aviso es para lo que pide atención: pendientes, por debajo del mínimo).
 */
class InicioDelPanel
{
    public static function para(User $usuario): array
    {
        $rol = $usuario->getRoleNames()->first();

        [$tarjetas, $proximas] = match ($rol) {
            'administrador' => [self::administrador(), []],
            'profesor'      => [self::profesor($usuario), []],
            'alumno'        => self::alumno($usuario),
            'auditor'       => [self::auditor(), []],
            default         => [[], []],
        };

        return [
            'tarjetas' => collect($tarjetas)->filter(fn ($t) => Route::has($t['ruta']))->values()->all(),
            'proximas' => $proximas,
        ];
    }

    private static function tarjeta(string $titulo, int|string $valor, string $detalle, string $ruta, string $tono = 'primario'): array
    {
        return compact('titulo', 'valor', 'detalle', 'ruta', 'tono');
    }

    private static function administrador(): array
    {
        $pendientes = DB::table('preinscripciones')->where('estado', 'pendiente')->count();
        $alumnos = DB::table('inscripciones')->where('estado', 'activa')->distinct()->count('alumno_id');
        $conEspera = DB::table('v_oferta_publica')->where('con_lista_espera', true)->distinct()->count('curso_id');

        return [
            self::tarjeta('Preinscripciones pendientes', $pendientes, $pendientes === 1 ? 'Espera tu revisión' : 'Esperan tu revisión', 'admin.preinscripciones', $pendientes > 0 ? 'aviso' : 'exito'),
            self::tarjeta('Alumnos activos', $alumnos, 'Con al menos una inscripción activa', 'admin.alumnos'),
            self::tarjeta('Cursos con lista de espera', $conEspera, $conEspera === 1 ? 'Curso sin cupo' : 'Cursos sin cupo', 'admin.cursos', $conEspera > 0 ? 'aviso' : 'exito'),
        ];
    }

    private static function profesor(User $profesor): array
    {
        $materias = CursoProfesor::where('profesor_id', $profesor->id)->count();
        $sinDevolver = Entrega::whereNull('devolucion')
            ->whereHas('tarea.cursoProfesor', fn ($q) => $q->where('profesor_id', $profesor->id))
            ->count();
        $abiertas = Tarea::where('fecha_entrega', '>=', now())
            ->whereHas('cursoProfesor', fn ($q) => $q->where('profesor_id', $profesor->id))
            ->count();

        return [
            self::tarjeta('Mis materias', $materias, $materias === 1 ? 'Materia asignada' : 'Materias asignadas', 'profesor.materias'),
            self::tarjeta('Entregas sin devolver', $sinDevolver, $sinDevolver === 1 ? 'Espera tu devolución' : 'Esperan tu devolución', 'profesor.tareas', $sinDevolver > 0 ? 'aviso' : 'exito'),
            self::tarjeta('Tareas abiertas', $abiertas, 'Con fecha de entrega por delante', 'profesor.tareas'),
        ];
    }

    /** @return array{0: array, 1: array} */
    private static function alumno(User $alumno): array
    {
        $pendientes = TareasDelAlumno::de($alumno)
            ->filter(fn ($t) => $t['entrega'] === null && ! $t['vencida'])
            ->sortBy('fecha_entrega')
            ->values();

        // Promedio y módulos de la inscripción activa más reciente (los mismos números que «Historial académico»)
        $inscripcion = HistorialAcademico::inscripcionesDe($alumno)->firstWhere('estado', 'activa');
        $resumen = $inscripcion
            ? HistorialAcademico::de(\App\Models\Inscripcion::findOrFail(data_get($inscripcion, 'inscripcion_id')))['resumen']
            : null;

        $debajo = AsistenciaDelAlumno::de($alumno)
            ->filter(fn ($m) => $m['porcentaje'] !== null && $m['porcentaje'] < $m['asistencia_minima'])
            ->count();

        $tarjetas = [
            self::tarjeta('Tareas pendientes', $pendientes->count(),
                $pendientes->isEmpty() ? 'No tenés tareas por entregar' : 'La próxima vence el '.$pendientes->first()['fecha_entrega']->format('d/m'),
                'alumno.tareas', $pendientes->isEmpty() ? 'exito' : 'aviso'),
            self::tarjeta('Promedio', $resumen && $resumen['promedio'] !== null ? number_format($resumen['promedio'], 2, ',', '.') : '—',
                $resumen ? "{$resumen['modulos_acreditados']} acreditados · {$resumen['modulos_pendientes']} pendientes" : 'Todavía no tenés una inscripción activa',
                'alumno.historial'),
            self::tarjeta('Asistencia', $debajo === 0 ? 'Al día' : $debajo,
                $debajo === 0 ? 'Ninguna materia por debajo del mínimo' : ($debajo === 1 ? 'Materia por debajo del mínimo' : 'Materias por debajo del mínimo'),
                'alumno.asistencia', $debajo === 0 ? 'exito' : 'aviso'),
        ];

        $proximas = $pendientes->take(3)->map(fn ($t) => [
            'titulo'        => $t['titulo'],
            'materia'       => $t['materia'],
            'fecha_entrega' => $t['fecha_entrega'],
        ])->all();

        return [$tarjetas, $proximas];
    }

    private static function auditor(): array
    {
        $sedes = collect(ResumenPorSede::todas());
        $hoy = DB::table('auditoria')->whereDate('created_at', today())->count();

        return [
            self::tarjeta('Sedes activas', $sedes->where('activo', true)->count(),
                $sedes->sum('alumnos_activos').' alumnos activos en total', 'auditor.sedes'),
            self::tarjeta('Movimientos de hoy', $hoy, 'En el registro de auditoría', 'auditor.auditoria'),
        ];
    }
}
