<?php

namespace App\Support;

use Illuminate\Http\Request;

class ProjectRequestSignature
{
    public function forRequest(
        Request $request,
        string $appId,
        string $secretKey,
        string $timestamp,
    ): string {
        return $this->make(
            appId: $appId,
            secretKey: $secretKey,
            timestamp: $timestamp,
            method: $request->method(),
            path: $request->getPathInfo(),
            body: $request->getContent(),
        );
    }

    public function make(
        string $appId,
        string $secretKey,
        string $timestamp,
        string $method,
        string $path,
        string $body = '',
    ): string {
        $payload = implode("\n", [
            strtoupper($method),
            $path,
            $appId,
            $timestamp,
            hash('sha256', $body),
        ]);

        return hash_hmac(
            (string) config('payment.auth.signature_algorithm', 'sha256'),
            $payload,
            $secretKey,
        );
    }

    public function hasValidTimestamp(string $timestamp, int $allowedSkewSeconds): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(now()->timestamp - (int) $timestamp) <= $allowedSkewSeconds;
    }
}
