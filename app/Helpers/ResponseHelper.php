<?php

namespace App\Helpers;

abstract class ResponseHelper
{
    const RESPONSE_SUCCESS_CODE = 200;

    public static function success(array $data = [], int $statusCode = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            [
                "data" => $data,
            ],
            $statusCode
        );
    }

    public static function error(string $message, string $exception, int $statusCode = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            [
                "error"     => $message,
                "exception" => $exception,
            ],
            $statusCode);
    }

}
