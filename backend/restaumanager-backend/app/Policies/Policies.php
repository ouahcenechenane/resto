<?php
// ══════════════════════════════════════════════════════════════
// app/Policies/OrderPolicy.php
// ══════════════════════════════════════════════════════════════
namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Un serveur ne peut voir/modifier que SES propres commandes.
     * Un caissier et admin voient tout.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin() || $user->isCaissier()) return true;
        return $order->user_id === $user->id;
    }

    /** Caissier + serveur peuvent créer des commandes */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'caissier', 'serveur']);
    }

    /** Seul l'auteur de la commande (ou admin/caissier) peut modifier */
    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin() || $user->isCaissier()) return true;
        return $order->user_id === $user->id;
    }

    /** Seul caissier/admin peut offrir un article gratuit */
    public function offerItem(User $user): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }

    /** Seul caissier/admin peut appliquer une remise */
    public function applyDiscount(User $user): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }

    /** Seul caissier/admin peut annuler une commande */
    public function cancel(User $user, Order $order): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }
}


// ══════════════════════════════════════════════════════════════
// app/Policies/TicketPolicy.php
// ══════════════════════════════════════════════════════════════
namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /** Seul caissier/admin peut générer un ticket */
    public function generate(User $user): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }

    /** Seul caissier/admin peut encaisser */
    public function pay(User $user): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }

    /** Voir les tickets : caissier/admin uniquement */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $user->isCaissier();
    }
}


// ══════════════════════════════════════════════════════════════
// app/Policies/MenuItemPolicy.php
// ══════════════════════════════════════════════════════════════
namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    /** Tout le monde peut voir le menu */
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, MenuItem $item): bool { return true; }

    /** Seul admin peut créer/modifier/supprimer */
    public function create(User $user): bool  { return $user->isAdmin(); }
    public function update(User $user): bool  { return $user->isAdmin(); }
    public function delete(User $user): bool  { return $user->isAdmin(); }
}


// ══════════════════════════════════════════════════════════════
// app/Policies/UserPolicy.php
// ══════════════════════════════════════════════════════════════
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Seul admin peut gérer les utilisateurs */
    public function viewAny(User $user): bool  { return $user->isAdmin(); }
    public function view(User $user, User $model): bool {
        return $user->isAdmin() || $user->id === $model->id;
    }
    public function create(User $user): bool   { return $user->isAdmin(); }
    public function update(User $user, User $model): bool {
        // Un user peut se modifier lui-même, un admin peut tout modifier
        return $user->isAdmin() || $user->id === $model->id;
    }
    public function delete(User $user, User $model): bool {
        // Pas de suicide d'admin
        return $user->isAdmin() && $user->id !== $model->id;
    }
    public function toggleActive(User $user): bool { return $user->isAdmin(); }
}
