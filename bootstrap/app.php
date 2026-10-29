<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HU05 (#30): cierra la sesión de un usuario dado de baja. Va antes de la
        // auditoría para que la base no registre sus pedidos.
        $middleware->appendToGroup('web', \App\Http\Middleware\UsuarioActivo::class);
        // Y antes de 'auth': si no, Laravel lo adelanta y manda al login sin el aviso.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\UsuarioActivo::class,
        );

        // Quién hace cada pedido, para la auditoría y el rol Auditor de la base.
        $middleware->appendToGroup('web', \App\Http\Middleware\ContextoAuditoria::class);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
