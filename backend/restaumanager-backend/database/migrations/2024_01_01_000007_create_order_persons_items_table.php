<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Une ligne par personne dans la commande
        Schema::create('order_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->integer('person_index');   // 0, 1, 2... (Personne 1, 2, 3...)
            $table->string('label')->nullable(); // ex: "M. Ahmed"
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['order_id', 'person_index']);
        });

        // Un article par ligne pour chaque personne
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_person_id')->constrained('order_persons')->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->string('item_name');                              // Copie du nom
            $table->decimal('unit_price', 10, 2);                    // Prix au moment commande
            $table->integer('quantity')->default(1);
            $table->decimal('discount_percent', 5, 2)->default(0);   // Remise %
            $table->boolean('is_free')->default(false);               // Article offert
            $table->string('free_reason')->nullable();
            $table->boolean('is_returned')->default(false);           // ← NOUVEAU: retour plat
            $table->string('return_reason')->nullable();              // ← NOUVEAU: raison du retour
            $table->text('kitchen_note')->nullable();                 // ← NOUVEAU: note cuisine (piment, etc.)
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_persons');
    }
};
