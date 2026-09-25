<?php

use App\Http\Middleware\EnsureDashboardApiClient;
use App\Http\Middleware\HumanOnly;
use App\Http\Middleware\SetLocale;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\AuditOtpSend::class,
        );
        $middleware->validateCsrfTokens(except: [
            'payment-gateway/qc168/callback',
            'payment-gateway/qc168/withdrawal/callback',
        ]);

        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'otp.audit' => \App\Http\Middleware\AuditOtpSend::class,
            'otp.challenge' => \App\Http\Middleware\VerifyOtpChallenge::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'dashboard.api-client' => EnsureDashboardApiClient::class,
            'human.only' => HumanOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['turnstile_token']);

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Validation failed', $exception->errors(), 422);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', [], 401);
            }

            return null;
        });

        $exceptions->render(function (
            AuthorizationException|AccessDeniedHttpException $exception,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage() ?: 'Forbidden.', [], 403);
            }

            return null;
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Too many requests.', [], 429)
                    ->withHeaders($exception->getHeaders());
            }

            return null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found.', [], 404);
            }

            return null;
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                report($exception);

                return ApiResponse::error('Unexpected server error.', [], 500);
            }

            return null;
        });
    })->create();
