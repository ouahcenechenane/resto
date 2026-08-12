<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration de mise à jour — version SQLite compatible
 *  1. Le rôle 'reception' est géré en TEXT sous SQLite (pas d'ENUM)
 *  2. Ajoute le champ type ('sur_place'|'emporter') à la table orders
 *  3. Rend table_id nullable dans orders (pour les commandes à emporter)
 *  4. Ajoute client_name et order_number dans orders
 *  5. Ajoute la section 'emporter' dans sections
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. SQLite ne supporte pas MODIFY COLUMN ni ENUM ──────────────────
        // Le rôle est déjà stocké en TEXT sous SQLite → rien à faire,
        // la valeur 'reception' est acceptée sans modification de schéma.

        // ── 2. Modifier la table orders ───────────────────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            // Rendre table_id nullable (les commandes à emporter n'ont pas de table)
            $table->foreignId('table_id')->nullable()->change();

            // Type de commande
            if (!Schema::hasColumn('orders', 'type')) {
                $table->string('type')->default('sur_place')->after('user_id');
            }

            // Nom du client (pour à emporter)
            if (!Schema::hasColumn('orders', 'client_name')) {
                $table->string('client_name')->nullable()->after('type');
            }

            // Numéro de commande à emporter (ex: #001)
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number')->nullable()->after('client_name');
            }
        });

        // Mettre à jour les commandes existantes → sur_place
        DB::table('orders')->whereNull('type')->update(['type' => 'sur_place']);

        // ── 3. Ajouter la section 'emporter' ─────────────────────────────────
        $exists = DB::table('sections')->where('code', 'emporter')->exists();
        if (!$exists) {
            DB::table('sections')->insert([
                'code'       => 'emporter',
                'name'       => 'À Emporter',
                'icon'       => '🧾',
                'order'      => 4,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['type', 'client_name', 'order_number']);
            $table->foreignId('table_id')->nullable(false)->change();
        });

        DB::table('sections')->where('code', 'emporter')->delete();
    }
};