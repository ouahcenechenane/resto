<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sse_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80)->index();      // ex: order.created
            $table->json('payload');                         // données de l'événement
            $table->unsignedBigInteger('related_id')->nullable()->index(); // order_id, table_id…
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sse_events');
    }
};
