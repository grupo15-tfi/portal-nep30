<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * HU05 (#30): corta la sesión de un usuario dado de baja.
 * El login solo revisa `activo` al ingresar: si el Administrador da de baja a
 * alguien que tiene la sesión abierta, seguiría navegando hasta que venza.
 * En cada pedido se comprueba que siga activo y sin baja lógica (deleted_at).
 * Va antes de ContextoAuditoria para que la base no registre a un usuario dado de baja.
 */
class UsuarioActivo
{
    public const AVISO = 'Tu cuenta está dada de baja. Consultá con la institución.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->dadoDeBaja($request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('aviso', self::AVISO);
        }

        return $next($request);
    }

    private function dadoDeBaja(Request $request): bool
    {
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Solo false explícito: un modelo recién creado sin `activo` usa el valor
            // por defecto de la base (true) aunque en memoria quede null.
            return $user->activo === false || $user->trashed();
        }

        // La sesión guarda un id, pero el usuario ya no se puede cargar:
        // SoftDeletes lo excluye porque tiene deleted_at.
        $guard = Auth::guard();

        return $request->hasSession()
            && method_exists($guard, 'getName')
            && $request->session()->has($guard->getName());
    }
}
