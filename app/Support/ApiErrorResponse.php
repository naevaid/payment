<?php

namespace App\Support;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public static function make(string $message, string $code, int $status, array $details = []): array
    {
        $response = [
            'message' => $message,
            'error' => [
                'code' => $code,
                'status' => $status,
            ],
        ];

        if ($details !== []) {
            $response['error']['details'] = $details;
        }

        return $response;
    }
}
