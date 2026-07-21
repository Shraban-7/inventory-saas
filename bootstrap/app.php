<?php

use App\Domain\Exceptions\CreditQuantityExceededException;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InvalidCreditNoteStateException;
use App\Domain\Exceptions\InvalidInvoiceStateException;
use App\Domain\Exceptions\InvalidJournalEntryException;
use App\Domain\Exceptions\InvalidSalesDataException;
use App\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Presentation\Middleware\EnforceIdempotencyKey;
use App\Presentation\Middleware\SetTenantContext;
use App\Presentation\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => EnforceIdempotencyKey::class,
            'tenant' => SetTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            $failed = $exception->validator->failed();
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $rules = array_keys($failed[$field] ?? []);
                $errors[$field] = [];

                foreach (array_values($messages) as $index => $message) {
                    $rule = $rules[$index] ?? $rules[0] ?? 'invalid';
                    $errors[$field][] = [
                        'code' => 'validation.'.Str::snake($rule),
                        'message' => $message,
                    ];
                }
            }

            return ProblemDetails::response(
                $request,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Validation failed',
                'One or more fields failed validation.',
                $errors,
                'urn:problem:validation',
            );
        });

        $exceptions->render(fn (AuthenticationException $exception, Request $request) => ProblemDetails::response(
            $request,
            Response::HTTP_UNAUTHORIZED,
            'Unauthenticated',
            'Authentication is required to access this resource.',
        ));

        $exceptions->render(fn (AuthorizationException $exception, Request $request) => ProblemDetails::response(
            $request,
            Response::HTTP_FORBIDDEN,
            'Forbidden',
            'You are not authorized to perform this action.',
        ));

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== Response::HTTP_FORBIDDEN) {
                return null;
            }

            return ProblemDetails::response(
                $request,
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                'You are not authorized to perform this action.',
            );
        });

        $exceptions->render(fn (ModelNotFoundException $exception, Request $request) => ProblemDetails::response(
            $request,
            Response::HTTP_NOT_FOUND,
            'Resource not found',
            'The requested resource was not found.',
        ));

        $exceptions->render(function (DomainException $exception, Request $request) {
            $processable = [
                InvalidSalesDataException::class,
                InvalidInvoiceStateException::class,
                InvalidCreditNoteStateException::class,
                CreditQuantityExceededException::class,
                InsufficientStockException::class,
                InvalidJournalEntryException::class,
                UnbalancedJournalEntryException::class,
            ];

            if (! in_array($exception::class, $processable, true)) {
                return null;
            }

            $detail = $exception->getMessage();

            if (app()->isProduction() && ($exception instanceof InvalidJournalEntryException || $exception instanceof UnbalancedJournalEntryException)) {
                $detail = 'The sales transaction could not be processed.';
            }

            return ProblemDetails::response(
                $request,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Sales transaction rejected',
                $detail,
                [],
                'urn:problem:sales-transaction',
            );
        });
    })->create();
