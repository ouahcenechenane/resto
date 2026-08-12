<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('printed_by')->constrained('users')->onDelete('cascade');
            $table->string('ticket_number')->unique(); // ex: TICK-20240115-0042
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'other'])->nullable();
            $table->enum('status', ['printed', 'paid', 'cancelled'])->default('printed');
            $table->json('snapshot')->nullable(); // Snapshot JSON du ticket au moment impression
            $table->timestamp('printed_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
