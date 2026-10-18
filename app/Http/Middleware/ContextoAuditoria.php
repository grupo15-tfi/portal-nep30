<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le dice a MySQL quién hace cada pedido. Los triggers de la base usan
 * @app_usuario_id y @app_ip para:
 *   - registrar en la tabla auditoria quién hizo cada cambio, y
 *   - rechazar cambios del rol Auditor (procedimiento exigir_escritura).
 * En el sitio público (preinscripción) no hay sesión: queda NULL.
 */
class ContextoAuditoria
{
    public function handle(Request $request, Closure $next): Response
    {
        DB::statement('SET @app_usuario_id = ?, @app_ip = ?', [$request->user()?->getKey(), $request->ip()]);

        return $next($request);
    }
}
