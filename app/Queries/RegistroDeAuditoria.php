<?php

namespace App\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HU16 (#122): buscar en el registro de auditoría (tabla `auditoria`, la llenan los triggers de la base).
 * Contrato (#121):
 *   buscar(array $filtros): LengthAwarePaginator · filtros opcionales: tabla, usuario (DNI o apellido),
 *   accion, desde, hasta (Y-m-d) · 25 por página, lo más reciente primero.
 *   Cada renglón: id, fecha, usuario («Apellido, Nombre» o «Sistema»), accion, tabla, tabla_legible,
 *   registro_id, ip, cambios (lista de campo, antes, despues; solo lo que cambió).
 *   TABLAS (tabla → nombre legible) y ACCIONES (accion → nombre legible), para los filtros de la pantalla.
 * Solo lee: la auditoría no se puede editar ni borrar (triggers trg_auditoria_no_update y _no_delete).
 * La usa la pantalla «Auditoría» (#123), del Administrador y del Auditor.
 */
class RegistroDeAuditoria
{
    public const POR_PAGINA = 25;

    public const TABLAS = [
        'users'                => 'Usuarios',
        'model_has_roles'      => 'Roles de usuario',
        'preinscripciones'     => 'Preinscripciones',
        'inscripciones'        => 'Inscripciones',
        'inscripcion_materias' => 'Módulos del inscripto',
        'evaluaciones'         => 'Notas',
        'asistencias'          => 'Asistencias',
    ];

    public const ACCIONES = [
        'alta'          => 'Alta',
        'modificacion'  => 'Modificación',
        'baja'          => 'Baja',
        'login'         => 'Inicio de sesión',
        'login_fallido' => 'Inicio de sesión fallido',
        'descarga'      => 'Descarga',
    ];

    /** Campos que nunca se muestran, aunque aparezcan en el JSON */
    public const OCULTOS = ['password', 'remember_token', 'token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public static function buscar(array $filtros = []): LengthAwarePaginator
    {
        $tabla = $filtros['tabla'] ?? null;
        $accion = $filtros['accion'] ?? null;
        $usuario = trim((string) ($filtros['usuario'] ?? ''));
        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;

        return DB::table('auditoria as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->select('a.*', 'u.apellido as usuario_apellido', 'u.nombre as usuario_nombre')
            ->when($tabla, fn ($q) => $q->where('a.tabla', $tabla))
            ->when($accion, fn ($q) => $q->where('a.accion', $accion))
            ->when($usuario !== '', fn ($q) => ctype_digit($usuario)
                ? $q->where('u.dni', 'like', $usuario.'%')
                : $q->where('u.apellido', 'like', $usuario.'%'))
            ->when($desde, fn ($q) => $q->where('a.created_at', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($hasta, fn ($q) => $q->where('a.created_at', '<', Carbon::parse($hasta)->addDay()->startOfDay()))
            ->orderByDesc('a.created_at')->orderByDesc('a.id')
            ->paginate(self::POR_PAGINA)
            ->through(fn (object $r) => [
                'id'            => (int) $r->id,
                'fecha'         => Carbon::parse($r->created_at),
                'usuario'       => $r->usuario_id === null ? 'Sistema' : $r->usuario_apellido.', '.$r->usuario_nombre,
                'accion'        => $r->accion,
                'tabla'         => $r->tabla,
                'tabla_legible' => self::TABLAS[$r->tabla] ?? $r->tabla,
                'registro_id'   => $r->registro_id === null ? null : (int) $r->registro_id,
                'ip'            => $r->ip,
                'cambios'       => self::cambios($r->valores_anteriores, $r->valores_nuevos),
            ]);
    }

    /**
     * Compara los dos JSON y devuelve solo los campos que cambiaron.
     * En un alta no hay valores anteriores (antes = null); en una baja, puede no haber nuevos (despues = null).
     */
    public static function cambios(?string $anteriores, ?string $nuevos): array
    {
        $antes = json_decode($anteriores ?? 'null', true) ?: [];
        $despues = json_decode($nuevos ?? 'null', true) ?: [];

        $cambios = [];
        foreach (array_unique([...array_keys($antes), ...array_keys($despues)]) as $campo) {
            if (in_array($campo, self::OCULTOS, true)) {
                continue;
            }
            $a = $antes[$campo] ?? null;
            $d = $despues[$campo] ?? null;
            if ($a === null && $d === null) {
                continue;
            }
            if (json_encode($a) !== json_encode($d)) {
                $cambios[] = ['campo' => $campo, 'antes' => $a, 'despues' => $d];
            }
        }

        return $cambios;
    }
}