<?php

use App\Http\Middleware\BlockDuringMaintenanceMode;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\InspectAdminAccess;
use App\Http\Middleware\ProtectSensitiveApiEndpoints;
use App\Http\Middleware\PerformanceHeaders;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Support\HttpStatusCatalog;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['mail_admin_token']);
        $middleware->append(ProtectSensitiveApiEndpoints::class);
        $middleware->append(BlockDuringMaintenanceMode::class);
        $middleware->append(PerformanceHeaders::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin.security' => InspectAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson()) {
                $meta = HttpStatusCatalog::find(401);
                return response()->json([
                    'message' => 'Oturum gerekli.',
                    'code' => 401,
                    'status' => $meta['text'],
                    'category' => $meta['category'],
                    'request_uid' => $request->attributes->get('kgm_request_uid'),
                    'error_uid' => $request->attributes->get('kgm_error_uid'),
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson() || $request->wantsJson())) {
                return null;
            }

            $status = method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500;
            if ($status < 100 || $status > 599) {
                $status = 500;
            }

            $meta = HttpStatusCatalog::find($status);

            return response()->json([
                'message' => $meta['message'],
                'code' => $status,
                'status' => $meta['text'],
                'category' => $meta['category'],
                'request_uid' => $request->attributes->get('kgm_request_uid'),
                'error_uid' => $request->attributes->get('kgm_error_uid'),
            ], $status);
        });
    })->create();
