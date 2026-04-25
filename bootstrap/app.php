<?php
// =============================================================================
// FILE: bootstrap/app.php
// PURPOSE: Laravel 11 application bootstrap - registrasi middleware dan routes
// =============================================================================

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SupplierActiveMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:     __DIR__ . '/../routes/web.php',
        api:     __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health:  '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ── API middleware group ─────────────────────────────────────────────
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // ── Alias middleware ─────────────────────────────────────────────────
        $middleware->alias([
            'role'             => RoleMiddleware::class,
            'supplier.active'  => SupplierActiveMiddleware::class,
        ]);

        // ── CORS: izinkan request dari Vue dev server dan Capacitor ──────────
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // JSON response untuk semua exception di API routes
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->expectsJson() || $request->is('api/*');
        });
    })
    ->create();

