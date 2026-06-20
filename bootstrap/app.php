<?php

use App\Http\Middleware\AuthenticateProjectRequest;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'project.auth' => AuthenticateProjectRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isPaymentApi = static fn (Request $request): bool => $request->is('api/v1/*');

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isPaymentApi) {
            if (! $isPaymentApi($request)) {
                return null;
            }

            return response()->json(
                ApiErrorResponse::make(
                    message: 'The given data was invalid.',
                    code: 'validation_failed',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    details: [
                        'errors' => $exception->errors(),
                    ],
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isPaymentApi) {
            if (! $isPaymentApi($request)) {
                return null;
            }

            return response()->json(
                ApiErrorResponse::make(
                    message: 'Resource not found.',
                    code: 'resource_not_found',
                    status: Response::HTTP_NOT_FOUND,
                ),
                Response::HTTP_NOT_FOUND,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($isPaymentApi) {
            if (! $isPaymentApi($request)) {
                return null;
            }

            if ($exception->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(
                    ApiErrorResponse::make(
                        message: 'Resource not found.',
                        code: 'resource_not_found',
                        status: Response::HTTP_NOT_FOUND,
                    ),
                    Response::HTTP_NOT_FOUND,
                );
            }

            return response()->json(
                ApiErrorResponse::make(
                    message: 'Endpoint not found.',
                    code: 'endpoint_not_found',
                    status: Response::HTTP_NOT_FOUND,
                ),
                Response::HTTP_NOT_FOUND,
            );
        });
    })->create();
