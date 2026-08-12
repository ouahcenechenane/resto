<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->string('number');                     // Numéro ou nom de la table
            $table->integer('capacity')->default(4);      // Capacité max
            $table->enum('status', [
                'available',  // Libre
                'occupied',   // Occupée
                'reserved',   // Réservée
                'closed',     // Clôturée
            ])->default('available');
            $table->timestamps();

            $table->unique(['section_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
