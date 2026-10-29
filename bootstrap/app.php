<?php

use App\Http\Middleware\EnsureAmpuPlug;
use App\Http\Middleware\EnsureAmpuPraktikum;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->prefix('api/client')->group(base_path('routes/client_api.php'));
            Route::middleware('api')->prefix('api/admin')->group(base_path('routes/admin_api.php'));
            Route::middleware('api')->prefix('api/public')->group(base_path('routes/public_api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'ampu' => EnsureAmpuPraktikum::class,
            'ampu-plug' => EnsureAmpuPlug::class,
        ]);

        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
