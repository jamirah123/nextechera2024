<?php

namespace App\Support\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class UserFacingExceptionRenderer
{
    public function render(Throwable $exception, Request $request): ?Response
    {
        if ($exception instanceof ValidationException) {
            return null;
        }

        if ($exception instanceof AuthenticationException) {
            return $this->unauthenticated($request);
        }

        if ($exception instanceof TokenMismatchException) {
            return $this->page($request, 'errors.419', 419);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->forbidden($request);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->page($request, 'errors.404', 404);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $this->httpException($exception, $request);
        }

        if ($exception instanceof QueryException) {
            return $this->queryException($exception, $request);
        }

        if ($exception instanceof InvalidArgumentException && $this->isSafeMessage($exception->getMessage())) {
            Log::info('Business rule rejected the action.', [
                'message' => $exception->getMessage(),
                ...$this->context($request),
            ]);

            return $this->business($request, $exception->getMessage());
        }

        return $this->unexpected($request);
    }

    public static function shouldSilenceReport(Throwable $exception): bool
    {
        if ($exception instanceof InvalidArgumentException && self::messageIsSafe($exception->getMessage())) {
            return true;
        }

        return $exception instanceof QueryException
            && DatabaseFailureMessage::isExpectedConflict($exception);
    }

    /**
     * @return array<string, mixed>
     */
    public static function logContext(): array
    {
        $request = request();

        return [
            'request_id' => $request->attributes->get('request_id'),
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
        ];
    }

    private function unauthenticated(Request $request): Response
    {
        $message = 'Your session has ended. Please sign in again.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->guest(route('login'))->with('error', $message);
    }

    private function forbidden(Request $request): Response
    {
        $message = 'You do not have permission to perform this action.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 403);
        }

        if ($this->canReturnToForm($request)) {
            return redirect()->back()->with('error', $message);
        }

        return $this->page($request, 'errors.403', 403);
    }

    private function httpException(HttpExceptionInterface $exception, Request $request): Response
    {
        $status = $exception->getStatusCode();
        $view = match ($status) {
            401 => 'errors.401',
            403 => 'errors.403',
            404 => 'errors.404',
            419 => 'errors.419',
            422 => 'errors.422',
            429 => 'errors.429',
            503 => 'errors.503',
            default => 'errors.500',
        };

        if ($status === 403 && $this->canReturnToForm($request)) {
            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }

        if ($status >= 500) {
            return $this->unexpected($request, $status === 503 ? 503 : 500);
        }

        return $this->page($request, $view, $status);
    }

    private function queryException(QueryException $exception, Request $request): Response
    {
        $classified = DatabaseFailureMessage::classify($exception);

        if ($classified === null) {
            return $this->unexpected($request);
        }

        if ($classified['kind'] === 'conflict') {
            Log::warning('Database constraint rejected the action.', [
                'sqlstate' => $exception->errorInfo[0] ?? null,
                'driver_code' => $exception->errorInfo[1] ?? null,
                'detail' => $exception->errorInfo[2] ?? null,
                ...$this->context($request),
            ]);

            return $this->business($request, $classified['message'], $classified['status']);
        }

        Log::error('Database connection failed.', [
            'sqlstate' => $exception->errorInfo[0] ?? null,
            'driver_code' => $exception->errorInfo[1] ?? null,
            ...$this->context($request),
        ]);

        if ($this->canReturnToForm($request)) {
            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', $classified['message']);
        }

        return $this->page($request, 'errors.503', 503);
    }

    private function business(Request $request, string $message, int $status = 422): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], $status);
        }

        if ($this->canReturnToForm($request)) {
            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', $message);
        }

        return response()->view('errors.422', ['detail' => $message], $status);
    }

    private function unexpected(Request $request, int $status = 500): Response
    {
        $reference = (string) $request->attributes->get('request_id', '');
        $message = 'Unable to save changes. The server could not complete your request. Please try again.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Something went wrong. We couldn\'t complete this action. Please try again. If the problem continues, contact the administrator.',
                'reference' => $reference !== '' ? $reference : null,
            ], $status);
        }

        if ($this->canReturnToForm($request)) {
            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', $message.($reference !== '' ? ' Reference '.$reference.'.' : ''));
        }

        $view = $status === 503 ? 'errors.503' : 'errors.500';

        return response()->view($view, [
            'reference' => $reference !== '' ? $reference : null,
        ], $status);
    }

    private function page(Request $request, string $view, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->jsonMessage($status),
            ], $status);
        }

        return response()->view($view, [], $status);
    }

    private function canReturnToForm(Request $request): bool
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return false;
        }

        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $request->hasSession();
    }

    private function jsonMessage(int $status): string
    {
        return match ($status) {
            401 => 'Your session has ended. Please sign in again.',
            403 => 'You do not have permission to perform this action.',
            404 => 'The requested record could not be found.',
            419 => 'This page has expired. Refresh it and try again.',
            422 => 'Some of the information could not be saved.',
            429 => 'Too many requests. Please wait a moment and try again.',
            503 => 'The system is temporarily unavailable. Please try again in a few minutes.',
            default => 'Something went wrong. We couldn\'t complete this action. Please try again.',
        };
    }

    /**
     * @return array{request_id: mixed, user_id: mixed, method: string, path: string}
     */
    private function context(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get('request_id'),
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
        ];
    }

    private function isSafeMessage(string $message): bool
    {
        return self::messageIsSafe($message);
    }

    private static function messageIsSafe(string $message): bool
    {
        $message = trim($message);

        if ($message === '' || strlen($message) > 300) {
            return false;
        }

        $blocked = ['SQLSTATE', 'Stack trace', 'vendor/', 'PDOException', 'Undefined ', 'syntax error', '.php', 'Argument #', 'must be of type'];

        foreach ($blocked as $needle) {
            if (str_contains($message, $needle)) {
                return false;
            }
        }

        return ! str_contains($message, base_path());
    }
}
