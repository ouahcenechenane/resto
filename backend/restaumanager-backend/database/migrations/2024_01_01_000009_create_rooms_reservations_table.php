<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── CHAMBRES ──────────────────────────────────────────────
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();        // 101, 102, Suite A...
            $table->string('name')->nullable();        // "Chambre Vue Mer", "Suite Présidentielle"
            $table->enum('type', [
                'standard',
                'superieure',
                'suite',
                'familiale',
            ])->default('standard');
            $table->integer('capacity')->default(2);   // Nombre de personnes max
            $table->decimal('price_per_night', 10, 2); // Prix par nuit
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();      // ["WiFi","TV","Climatisation","Minibar"]
            $table->string('image')->nullable();
            $table->enum('status', [
                'available',    // Libre
                'occupied',     // Occupée
                'maintenance',  // En entretien
                'reserved',     // Réservée (future réservation confirmée)
            ])->default('available');
            $table->integer('floor')->default(1);      // Étage
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── RÉSERVATIONS ──────────────────────────────────────────
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->onDelete('set null');

            // Informations client
            $table->string('guest_name');
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_id_number')->nullable();   // Numéro CNI / Passeport

            // Dates
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('nights');                        // Calculé : checkout - checkin
            $table->timestamp('actual_check_in')->nullable(); // Heure réelle d'arrivée
            $table->timestamp('actual_check_out')->nullable();// Heure réelle de départ

            // Tarification
            $table->decimal('price_per_night', 10, 2);       // Copie du prix chambre
            $table->decimal('total_price', 10, 2);            // nights × price_per_night
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);            // total_price - discount
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2)->default(0);

            // Extras (room service, minibar, etc.)
            $table->decimal('extras_amount', 10, 2)->default(0);

            // Statut
            $table->enum('status', [
                'pending',      // En attente de confirmation
                'confirmed',    // Confirmée
                'checked_in',   // Client arrivé
                'checked_out',  // Client parti
                'cancelled',    // Annulée
                'no_show',      // Client ne s'est pas présenté
            ])->default('pending');

            $table->enum('payment_status', [
                'unpaid',
                'partial',
                'paid',
                'refunded',
            ])->default('unpaid');

            $table->enum('payment_method', ['cash','card','transfer','other'])->nullable();
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->text('special_requests')->nullable();     // Demandes spéciales
            $table->text('internal_notes')->nullable();       // Notes internes (admin)
            $table->string('reservation_number')->unique();   // RES-20240115-0001

            $table->timestamps();
        });

        // ── EXTRAS DE RÉSERVATION (room service, etc.) ────────────
        Schema::create('reservation_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_item_id')->nullable()->constrained()->onDelete('set null');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('line_total', 10, 2);
            $table->enum('type', ['room_service','minibar','laundry','parking','other'])->default('other');
            $table->boolean('is_free')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_extras');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('rooms');
    }
};
