<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'admin',
            'caissier_restau',
            'caissier_caffet',
            'serveur_restau',
            'serveur_caffet',
            'reception',
            'cuisiner'
        ) NOT NULL DEFAULT 'serveur_restau'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'admin',
            'caissier_restau',
            'caissier_caffet',
            'serveur_restau',
            'serveur_caffet',
            'reception'
        ) NOT NULL DEFAULT 'serveur_restau'");
    }
};
