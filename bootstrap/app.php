<?php

use App\Domain\EarningLine\Exceptions\EarningLineNotFound;
use App\Domain\EarningLine\Exceptions\InvalidManualAdjustment;
use App\Infrastructure\EventStore\ConcurrencyException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->map(EarningLineNotFound::class, fn(EarningLineNotFound $e) => new NotFoundHttpException($e->getMessage(), $e));
        $exceptions->map(InvalidManualAdjustment::class, fn(InvalidManualAdjustment $e) => new UnprocessableEntityHttpException($e->getMessage(), $e));
        $exceptions->map(ConcurrencyException::class, fn(ConcurrencyException $e) => new ConflictHttpException($e->getMessage(), $e));
    })->create();
