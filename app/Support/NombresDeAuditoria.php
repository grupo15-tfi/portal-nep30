<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HU16 (#123): traduce los números internos de la auditoría a nombres que entiende cualquiera.
 * «Notas #1» → «Prueba, Alumna Uno · Módulo 1: Corte y peinado»; «curso_id 2» → «Peluquería · Sede Central · turno mañana».
 * Busca de a una tabla por vez con whereIn (nunca una consulta por renglón). Solo lee.
 * Si el registro ya no existe (por ejemplo, una nota borrada), lo dice en vez de mostrar el número solo.
 */
class NombresDeAuditoria
{
    /** Qué busca cada tabla auditada: el registro_id de model_has_roles es el usuario */
    private const POR_TABLA = [
        'users'                => 'usuarios',
        'model_has_roles'      => 'usuarios',
        'preinscripciones'     => 'preinscripciones',
        'inscripciones'        => 'inscripciones',
        'inscripcion_materias' => 'cursadas',
        'evaluaciones'         => 'notas',
        'asistencias'          => 'asistencias',
    ];

    /** Campos de los cambios que son números de otro registro */
    private const POR_CAMPO = [
        'alumno_id'              => 'usuarios',
        'revisado_por'           => 'usuarios',
        'curso_id'               => 'cursos',
        'inscripcion_id'         => 'inscripciones',
        'inscripcion_materia_id' => 'cursadas',
        'clase_id'               => 'clases',
    ];

    /**
     * «Sobre quién» es cada renglón de la auditoría.
     *
     * @param  iterable<array>  $filas  renglones de RegistroDeAuditoria::buscar()
     * @return array<int, string>  id del renglón → texto
     */
    public static function deRegistros(iterable $filas): array
    {
        $porTipo = [];
        foreach ($filas as $f) {
            if ($f['registro_id'] !== null && isset(self::POR_TABLA[$f['tabla']])) {
                $porTipo[self::POR_TABLA[$f['tabla']]][] = $f['registro_id'];
            }
        }
        $nombres = self::buscar($porTipo);

        $textos = [];
        foreach ($filas as $f) {
            $tipo = self::POR_TABLA[$f['tabla']] ?? null;
            $textos[$f['id']] = match (true) {
                $f['registro_id'] === null => '—',
                $tipo === null             => 'n.º '.$f['registro_id'],
                default                    => $nombres[$tipo][$f['registro_id']] ?? 'n.º '.$f['registro_id'].' (ya no existe)',
            };
        }

        return $textos;
    }

    /**
     * Los cambios de un renglón con los números de otros registros reemplazados por nombres.
     *
     * @param  array<array{campo: string, antes: mixed, despues: mixed}>  $cambios
     */
    public static function deCambios(array $cambios): array
    {
        $porTipo = [];
        foreach ($cambios as $c) {
            if ($tipo = self::POR_CAMPO[$c['campo']] ?? null) {
                foreach (['antes', 'despues'] as $lado) {
                    if (is_numeric($c[$lado])) {
                        $porTipo[$tipo][] = (int) $c[$lado];
                    }
                }
            }
        }
        $nombres = self::buscar($porTipo);

        return array_map(function (array $c) use ($nombres) {
            if ($tipo = self::POR_CAMPO[$c['campo']] ?? null) {
                foreach (['antes', 'despues'] as $lado) {
                    if (is_numeric($c[$lado])) {
                        $c[$lado] = $nombres[$tipo][(int) $c[$lado]] ?? 'n.º '.$c[$lado].' (ya no existe)';
                    }
                }
            }

            return $c;
        }, $cambios);
    }

    /** @param array<string, int[]> $porTipo  tipo → ids · @return array<string, array<int, string>> */
    private static function buscar(array $porTipo): array
    {
        $nombres = [];
        foreach ($porTipo as $tipo => $ids) {
            $nombres[$tipo] = self::$tipo(array_values(array_unique($ids)));
        }

        return $nombres;
    }

    private static function usuarios(array $ids): array
    {
        return DB::table('users')->whereIn('id', $ids)->get(['id', 'apellido', 'nombre'])
            ->mapWithKeys(fn ($u) => [$u->id => "{$u->apellido}, {$u->nombre}"])->all();
    }

    private static function preinscripciones(array $ids): array
    {
        return DB::table('preinscripciones as p')
            ->join('cursos as c', 'c.id', '=', 'p.curso_id')
            ->join('planes_estudio as pl', 'pl.id', '=', 'c.plan_id')
            ->whereIn('p.id', $ids)->get(['p.id', 'p.apellido', 'p.nombre', 'pl.nombre as plan'])
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->apellido}, {$p->nombre} · {$p->plan}"])->all();
    }

    private static function inscripciones(array $ids): array
    {
        return DB::table('inscripciones as i')
            ->join('users as u', 'u.id', '=', 'i.alumno_id')
            ->join('cursos as c', 'c.id', '=', 'i.curso_id')
            ->join('planes_estudio as pl', 'pl.id', '=', 'c.plan_id')
            ->whereIn('i.id', $ids)->get(['i.id', 'u.apellido', 'u.nombre', 'pl.nombre as plan'])
            ->mapWithKeys(fn ($i) => [$i->id => "{$i->apellido}, {$i->nombre} · {$i->plan}"])->all();
    }

    /** Un alumno cursando un módulo (inscripcion_materias) */
    private static function cursadas(array $ids): array
    {
        return DB::table('inscripcion_materias as im')
            ->join('inscripciones as i', 'i.id', '=', 'im.inscripcion_id')
            ->join('users as u', 'u.id', '=', 'i.alumno_id')
            ->join('materias as m', 'm.id', '=', 'im.materia_id')
            ->whereIn('im.id', $ids)->get(['im.id', 'u.apellido', 'u.nombre', 'm.nombre as materia'])
            ->mapWithKeys(fn ($r) => [$r->id => "{$r->apellido}, {$r->nombre} · {$r->materia}"])->all();
    }

    private static function notas(array $ids): array
    {
        $notas = DB::table('evaluaciones')->whereIn('id', $ids)->pluck('inscripcion_materia_id', 'id');
        $cursadas = self::cursadas($notas->unique()->values()->all());

        return $notas->map(fn ($im) => $cursadas[$im] ?? null)->filter()->all();
    }

    private static function asistencias(array $ids): array
    {
        return DB::table('asistencias as a')
            ->join('inscripciones as i', 'i.id', '=', 'a.inscripcion_id')
            ->join('users as u', 'u.id', '=', 'i.alumno_id')
            ->join('clases as cl', 'cl.id', '=', 'a.clase_id')
            ->whereIn('a.id', $ids)->get(['a.id', 'u.apellido', 'u.nombre', 'cl.fecha'])
            ->mapWithKeys(fn ($r) => [$r->id => "{$r->apellido}, {$r->nombre} · clase del ".Carbon::parse($r->fecha)->format('d/m/Y')])->all();
    }

    private static function cursos(array $ids): array
    {
        return DB::table('cursos as c')
            ->join('planes_estudio as pl', 'pl.id', '=', 'c.plan_id')
            ->join('sedes as s', 's.id', '=', 'c.sede_id')
            ->whereIn('c.id', $ids)->get(['c.id', 'pl.nombre as plan', 's.nombre as sede', 'c.turno'])
            ->mapWithKeys(fn ($c) => [$c->id => "{$c->plan} · {$c->sede} · turno {$c->turno}"])->all();
    }

    private static function clases(array $ids): array
    {
        return DB::table('clases as cl')
            ->join('curso_profesores as cp', 'cp.id', '=', 'cl.curso_profesor_id')
            ->join('materias as m', 'm.id', '=', 'cp.materia_id')
            ->whereIn('cl.id', $ids)->get(['cl.id', 'cl.fecha', 'm.nombre as materia'])
            ->mapWithKeys(fn ($c) => [$c->id => "{$c->materia} · ".Carbon::parse($c->fecha)->format('d/m/Y')])->all();
    }
}
