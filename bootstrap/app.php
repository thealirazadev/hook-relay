<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::group([], base_path('routes/ingest.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('ingest/*')) {
                return null;
            }

            [$status, $code, $message] = match (true) {
                $e instanceof MethodNotAllowedHttpException => [405, 'method_not_allowed', 'Only POST is allowed on this endpoint.'],
                $e instanceof NotFoundHttpException => [404, 'unknown_source', 'No active source matches this ingest key.'],
                default => [500, 'server_error', 'An unexpected error occurred.'],
            };

            if ($status === 500) {
                Log::error('ingest.server_error', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'error' => ['code' => $code, 'message' => $message],
            ], $status);
        });
    })->create();
