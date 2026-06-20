<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\ApiErrorResponse;
use App\Support\ProjectRequestSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectRequest
{
    public function __construct(
        protected ProjectRequestSignature $requestSignature,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appIdHeader = (string) config('payment.auth.app_id_header', 'X-App-ID');
        $secretKeyHeader = (string) config('payment.auth.secret_key_header', 'X-Secret-Key');
        $signatureHeader = (string) config('payment.auth.signature_header', 'X-Payment-Signature');
        $timestampHeader = (string) config('payment.auth.timestamp_header', 'X-Timestamp');
        $appId = (string) $request->header($appIdHeader);
        $secretKey = (string) $request->header($secretKeyHeader);
        $signature = (string) $request->header($signatureHeader);
        $timestamp = (string) $request->header($timestampHeader);

        if (blank($appId)) {
            return response()->json(
                ApiErrorResponse::make(
                    message: 'Missing project authentication app id header.',
                    code: 'missing_project_app_id',
                    status: Response::HTTP_UNAUTHORIZED,
                ),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $project = Project::query()
            ->where('app_id', $appId)
            ->first();

        if ($project && ! $project->is_active) {
            return response()->json(
                ApiErrorResponse::make(
                    message: 'Project is inactive.',
                    code: 'project_inactive',
                    status: Response::HTTP_FORBIDDEN,
                ),
                Response::HTTP_FORBIDDEN,
            );
        }

        if (filled($signature) || filled($timestamp)) {
            if (blank($signature) || blank($timestamp)) {
                return response()->json(
                    ApiErrorResponse::make(
                        message: 'Missing project HMAC authentication headers.',
                        code: 'missing_project_hmac_headers',
                        status: Response::HTTP_UNAUTHORIZED,
                    ),
                    Response::HTTP_UNAUTHORIZED,
                );
            }

            if (! $project) {
                return response()->json(
                    ApiErrorResponse::make(
                        message: 'Invalid project credentials.',
                        code: 'invalid_project_credentials',
                        status: Response::HTTP_UNAUTHORIZED,
                    ),
                    Response::HTTP_UNAUTHORIZED,
                );
            }

            $allowedSkewSeconds = (int) config('payment.auth.timestamp_tolerance_seconds', 300);

            if (! $this->requestSignature->hasValidTimestamp($timestamp, $allowedSkewSeconds)) {
                return response()->json(
                    ApiErrorResponse::make(
                        message: 'Invalid or expired project request timestamp.',
                        code: 'invalid_project_timestamp',
                        status: Response::HTTP_UNAUTHORIZED,
                    ),
                    Response::HTTP_UNAUTHORIZED,
                );
            }

            $expectedSignature = $this->requestSignature->forRequest(
                request: $request,
                appId: (string) $project->app_id,
                secretKey: (string) $project->secret_key,
                timestamp: $timestamp,
            );

            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json(
                    ApiErrorResponse::make(
                        message: 'Invalid project request signature.',
                        code: 'invalid_project_signature',
                        status: Response::HTTP_UNAUTHORIZED,
                    ),
                    Response::HTTP_UNAUTHORIZED,
                );
            }

            $request->attributes->set('project', $project);
            $request->attributes->set('project_auth_mode', 'hmac');

            return $next($request);
        }

        $allowLegacySecretHeader = (bool) config('payment.auth.allow_legacy_secret_header', true);

        if (! $allowLegacySecretHeader || blank($secretKey)) {
            return response()->json(
                ApiErrorResponse::make(
                    message: 'Missing project authentication headers.',
                    code: 'missing_project_auth_headers',
                    status: Response::HTTP_UNAUTHORIZED,
                ),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if (! $project || ! hash_equals((string) $project->secret_key, $secretKey)) {
            return response()->json(
                ApiErrorResponse::make(
                    message: 'Invalid project credentials.',
                    code: 'invalid_project_credentials',
                    status: Response::HTTP_UNAUTHORIZED,
                ),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $request->attributes->set('project', $project);
        $request->attributes->set('project_auth_mode', 'legacy_header');

        return $next($request);
    }
}
