<?php

namespace App\Http\Middleware;

use App\Services\RequestLogger;
use Closure;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Throwable;

class RequestIdMiddleware
{
    public function __construct(
        private readonly RequestLogger $requestLogger
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $requestId = trim((string) $request->header('X-Request-Id'));
        if ($requestId === '') {
            $requestId = Uuid::uuid4()->toString();
        }

        $request->attributes->set('request_id', $requestId);

        $startedAt = microtime(true);
        $this->requestLogger->log('http_request_started', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->path(),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->requestLogger->log('http_request_failed', [
                'request_id' => $requestId,
                'method' => $request->getMethod(),
                'path' => $request->path(),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $response->headers->set('X-Request-Id', $requestId);

        $this->requestLogger->log('http_request_finished', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $response;
    }
}
