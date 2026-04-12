<?php

namespace App\Support\Concerns;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

trait ApiResponse
{
    protected function apiResponse(
        mixed $metadata = null,
        string $message = 'Thành công.',
        int $statusCode = Response::HTTP_OK,
        ?Throwable $exception = null
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'statusCode' => $statusCode,
            'metadata' => $metadata,
            'path' => request()->getPathInfo(),
            'timestamp' => now()->toISOString(),
        ];

        if ($exception && (app()->hasDebugModeEnabled() || config('app.debug'))) {
            $payload['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
            $payload['stack'] = $exception->getTraceAsString();
        }

        return response()->json($payload, $statusCode);
    }

    protected function success(
        mixed $metadata = null,
        string $message = 'Thành công.',
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return $this->apiResponse($metadata, $message, $statusCode);
    }

    protected function error(
        mixed $metadata = null,
        string $message = 'Có lỗi xảy ra.',
        int $statusCode = Response::HTTP_BAD_REQUEST,
        ?Throwable $exception = null
    ): JsonResponse {
        return $this->apiResponse($metadata, $message, $statusCode, $exception);
    }
}
