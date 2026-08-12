<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ActivityLogMiddleware
 * Enregistre chaque action API dans les logs Laravel.
 * Activer sur toutes les routes auth:sanctum.
 */
class ActivityLogMiddleware
{
    // Routes dont on ne logge pas le body (données sensibles)
    private const SENSITIVE_ROUTES = [
        'auth/login',
        'auth/logout',
        'admin/users',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (!$user) return $response;

        $path    = $request->path();
        $method  = $request->method();
        $status  = $response->getStatusCode();
        $isSensitive = $this->isSensitivePath($path);

        Log::channel('activity')->info('API_ACTION', [
            'user_id'  => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'method'   => $method,
            'path'     => $path,
            'status'   => $status,
            'ip'       => $request->ip(),
            'body'     => $isSensitive ? '[HIDDEN]' : $this->sanitizeBody($request->all()),
            'at'       => now()->toDateTimeString(),
        ]);

        return $response;
    }

    private function isSensitivePath(string $path): bool
    {
        foreach (self::SENSITIVE_ROUTES as $sensitive) {
            if (str_contains($path, $sensitive)) return true;
        }
        return false;
    }

    private function sanitizeBody(array $data): array
    {
        // Masquer les mots de passe s'ils traînent dans un body
        foreach (['password', 'password_confirmation', 'current_password'] as $key) {
            if (isset($data[$key])) $data[$key] = '***';
        }
        return $data;
    }
}
