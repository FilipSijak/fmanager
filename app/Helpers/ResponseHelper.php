<?php

namespace App\Helpers;

class ResponseHelper
{
    /**
     * @param array $data
     * @param int   $statusCode
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success(array $data = [], int $statusCode = 200)
    {
        return response()->json(
            [
                "data" => $data,
            ],
            $statusCode
        );
    }

    /**
     * @param string $message
     * @param string $exception
     * @param int    $statusCode
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error(string $message, string $exception, int $statusCode = 400)
    {
        return response()->json(
            [
                "error"     => $message,
                "exception" => $exception,
            ],
            $statusCode);
    }

}
