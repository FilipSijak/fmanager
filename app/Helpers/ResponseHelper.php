<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

abstract class ResponseHelper
{
    const RESPONSE_SUCCESS_CODE = 200;
    const RESPONSE_ERROR_CODE = 500;

    public static function success(array $data = [], int $statusCode = 200): JsonResponse
    {
        return response()->json(
            [
                "data" => $data,
            ],
            $statusCode
        );
    }

    public static function error(string $message, string $exception, int $statusCode = 400): JsonResponse
    {
        return response()->json(
            [
                "error"     => $message,
                "exception" => $exception,
            ],
            $statusCode);
    }

}
