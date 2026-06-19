<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appIdHeader = (string) config('payment.auth.app_id_header', 'X-App-ID');
        $secretKeyHeader = (string) config('payment.auth.secret_key_header', 'X-Secret-Key');
        $appId = (string) $request->header($appIdHeader);
        $secretKey = (string) $request->header($secretKeyHeader);

        if (blank($appId) || blank($secretKey)) {
            return response()->json([
                'message' => 'Missing project authentication headers.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $project = Project::active()
            ->where('app_id', $appId)
            ->first();

        if (! $project || ! hash_equals((string) $project->secret_key, $secretKey)) {
            return response()->json([
                'message' => 'Invalid project credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('project', $project);

        return $next($request);
    }
}
