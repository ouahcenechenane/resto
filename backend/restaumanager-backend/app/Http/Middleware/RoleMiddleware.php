<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /** Mapping rôle → sections autorisées */
    private const ROLE_SECTIONS = [
        'admin'           => ['salle', 'terrasse', 'caffet', 'emporter'],
        'caissier_restau' => ['salle', 'terrasse'],
        'caissier_caffet' => ['caffet'],
        'serveur_restau'  => ['salle', 'terrasse'],
        'serveur_caffet'  => ['caffet'],
        'reception'       => ['emporter'],
        'cuisiner'        => ['salle', 'terrasse', 'caffet', 'emporter'],
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Non authentifié.',
                'code'  => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'error' => 'Votre compte a été désactivé. Contactez l\'administrateur.',
                'code'  => 'ACCOUNT_DISABLED',
            ], 403);
        }

        $allowed = [];
foreach ($roles as $r) {
    foreach (explode(',', $r) as $single) {
        $allowed[] = trim($single);
    }
}

if (!in_array($user->role, $allowed)) {
            return response()->json([
                'error'     => 'Action non autorisée pour votre rôle.',
                'code'      => 'FORBIDDEN',
                'your_role' => $user->role,
                'required'  => $roles,
            ], 403);
        }

        $request->merge([
            'allowed_sections' => self::ROLE_SECTIONS[$user->role] ?? [],
        ]);

        return $next($request);
    }

    public static function getSectionsForRole(string $role): array
    {
        return self::ROLE_SECTIONS[$role] ?? [];
    }

    public static function isCaissier(string $role): bool
    {
        return in_array($role, ['caissier_restau', 'caissier_caffet', 'admin']);
    }

    public static function isServeur(string $role): bool
    {
        return in_array($role, ['serveur_restau', 'serveur_caffet']);
    }
}