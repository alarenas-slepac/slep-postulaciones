<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TouchLastSeen;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias de middleware (colócalos aquí, SOLO alias – nada que resuelva URL/Route)
        $middleware->alias([
            'verified'    => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'ensure.role' => \App\Http\Middleware\EnsureRole::class,
            'ensure.active-role' => \App\Http\Middleware\EnsureActiveRole::class,
            // si usas spatie/permission, puedes aliasarlos también:
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'touch.last.seen' => TouchLastSeen::class,
            'ensure.module' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);
        $middleware->web(append: [
            TouchLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
