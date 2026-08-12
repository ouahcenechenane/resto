<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * GET /api/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::when($request->role, fn($q) => $q->where('role', $request->role))
                     ->when($request->active !== null, fn($q) => $q->where('is_active', $request->boolean('active')))
                     ->orderBy('role')->orderBy('name')
                     ->get(['id','name','username','role','is_active','permissions','created_at']);

        return response()->json([
            'data'    => $users,
            'summary' => [
                'total'          => $users->count(),
                'admin'          => $users->where('role','admin')->count(),
                'caissier_restau'=> $users->where('role','caissier_restau')->count(),
                'caissier_caffet'=> $users->where('role','caissier_caffet')->count(),
                'serveur_restau' => $users->where('role','serveur_restau')->count(),
                'serveur_caffet' => $users->where('role','serveur_caffet')->count(),
                'reception'      => $users->where('role','reception')->count(),
                'cuisiner'       => $users->where('role','cuisiner')->count(),
                'inactive'       => $users->where('is_active',false)->count(),
            ],
        ]);
    }

    /**
     * POST /api/admin/users
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'username'    => 'required|string|max:50|unique:users,username|alpha_dash',
            'password'    => ['required', Password::min(8)->letters()->numbers()],
            'role'        => 'required|in:admin,caissier_restau,caissier_caffet,serveur_restau,serveur_caffet,reception,cuisiner',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $user = User::create([
            'name'        => $data['name'],
            'username'    => $data['username'],
            'password'    => Hash::make($data['password']),
            'role'        => $data['role'],
            'is_active'   => true,
            'permissions' => $data['permissions'] ?? [],   // le cast 'array' gère la sérialisation
        ]);

        return response()->json(['data' => $user->only(['id','name','username','role','is_active','permissions'])], 201);
    }

    /**
     * GET /api/admin/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $user->only(['id','name','username','role','is_active','permissions','created_at'])]);
    }

    /**
     * PUT /api/admin/users/{user}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'username'      => 'sometimes|string|max:50|alpha_dash|unique:users,username,'.$user->id,
            'role'          => 'sometimes|in:admin,caissier_restau,caissier_caffet,serveur_restau,serveur_caffet,reception,cuisiner',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        // Le cast 'array' du modèle sérialise automatiquement en JSON —
        // ne pas appeler json_encode() manuellement sinon les données seraient double-encodées.
        $user->update($data);

        // fresh() sans argument recharge le modèle complet depuis la BDD
        $fresh = $user->fresh();

        return response()->json(['data' => $fresh->only(['id','name','username','role','is_active','permissions'])]);
    }

    /**
     * DELETE /api/admin/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Un admin ne peut pas se supprimer lui-même
        if ($request->user()->id === $user->id) {
            return response()->json(['error' => 'Impossible de supprimer votre propre compte.'], 422);
        }
        $user->tokens()->delete();
        $user->delete();
        return response()->json(['success' => true]);
    }

    /**
     * PATCH /api/admin/users/{user}/toggle
     * Active ou désactive un compte
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['error' => 'Impossible de désactiver votre propre compte.'], 422);
        }
        $user->update(['is_active' => !$user->is_active]);
        // Si on désactive, révoquer tous les tokens
        if (!$user->is_active) {
            $user->tokens()->delete();
        }
        return response()->json([
            'data'    => $user->only(['id','username','role','is_active']),
            'message' => $user->is_active ? 'Compte activé.' : 'Compte désactivé et sessions révoquées.',
        ]);
    }

    /**
     * PATCH /api/admin/users/{user}/role
     * Changer uniquement le rôle d'un utilisateur
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => 'required|in:admin,caissier_restau,caissier_caffet,serveur_restau,serveur_caffet,reception,cuisiner',
        ]);

        $user->update(['role' => $data['role']]);

        return response()->json([
            'data'    => $user->fresh()->only(['id','name','username','role','is_active','permissions']),
            'message' => 'Rôle mis à jour.',
        ]);
    }

    /**
     * PATCH /api/admin/users/{user}/permissions
     * Mettre à jour uniquement les autorisations d'un utilisateur
     */
    public function updatePermissions(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string',
        ]);

        $user->update(['permissions' => $data['permissions']]);

        return response()->json([
            'data'    => $user->fresh()->only(['id','name','username','role','is_active','permissions']),
            'message' => 'Autorisations mises à jour.',
        ]);
    }

    /**
     * POST /api/admin/users/{user}/reset-password
     * Admin réinitialise le mot de passe d'un employé
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => Hash::make($data['new_password'])]);
        $user->tokens()->delete(); // Forcer reconnexion

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé. Toutes les sessions ont été révoquées.',
        ]);
    }
}