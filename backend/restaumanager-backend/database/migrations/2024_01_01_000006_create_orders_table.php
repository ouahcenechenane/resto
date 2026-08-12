<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->nullable()->constrained()->onDelete('cascade'); // nullable pour emporter
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // caissier, serveur ou réception
            $table->enum('type', [
                'sur_place',  // Commande sur table
                'emporter',   // Commande à emporter (réception)
            ])->default('sur_place');
            $table->string('client_name')->nullable();     // Nom client pour emporter
            $table->string('order_number')->nullable();    // Ex: #001 pour emporter
            $table->integer('persons_count')->default(1); // Nombre de personnes à la table
            $table->enum('status', [
                'open',       // En cours / Nouveau
                'validated',  // Validée (envoyée en cuisine)
                'billed',     // Facturée (ticket imprimé)
                'paid',       // Payée / Récupérée
                'cancelled',  // Annulée
            ])->default('open');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
