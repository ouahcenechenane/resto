<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'is_returned')) {
                $table->boolean('is_returned')->default(false)->after('is_free');
            }
            if (!Schema::hasColumn('order_items', 'returned_reason')) {
                $table->string('returned_reason')->nullable()->after('is_returned');
            }
        });

        // Ajouter persons_count à tables si absent
        Schema::table('tables', function (Blueprint $table) {
            if (!Schema::hasColumn('tables', 'persons_count')) {
                $table->unsignedTinyInteger('persons_count')->default(1)->after('capacity');
            }
        });

        // Ajouter cuisiner au enum role de users si nécessaire
        // (Géré au niveau applicatif — pas de modification d'enum MySQL)
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumnIfExists('is_returned');
            $table->dropColumnIfExists('returned_reason');
        });
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumnIfExists('persons_count');
        });
    }
};
