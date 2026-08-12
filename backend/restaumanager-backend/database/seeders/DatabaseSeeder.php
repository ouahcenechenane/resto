<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Menu (sections + catégories + articles) ───────────────────
        $this->call(MenuSeeder::class);

        // ── 2. Tables du restaurant ───────────────────────────────────────
        $this->call(TableSeeder::class);

        // ── 3. Comptes utilisateurs ───────────────────────────────────────
        $users = [
            ['name'=>'Administrateur',  'username'=>'admin',     'password'=>Hash::make('admin123'),     'role'=>'admin'],
            ['name'=>'Caissier Restau', 'username'=>'caissier',  'password'=>Hash::make('caissier123'),  'role'=>'caissier_restau'],
            ['name'=>'Caissier Caffet', 'username'=>'caffet',    'password'=>Hash::make('caffet123'),    'role'=>'caissier_caffet'],
            ['name'=>'Serveur Ahmed',   'username'=>'serveur',   'password'=>Hash::make('serveur123'),   'role'=>'serveur_restau'],
            ['name'=>'Serveur Café',    'username'=>'srvcafe',   'password'=>Hash::make('srvcafe123'),   'role'=>'serveur_caffet'],
            ['name'=>'Réception',       'username'=>'reception', 'password'=>Hash::make('reception123'), 'role'=>'reception'],
        ];

        foreach ($users as $u) {
            \App\Models\User::firstOrCreate(
                ['username' => $u['username']],
                array_merge($u, ['is_active' => true])
            );
        }

        $count = \App\Models\User::count();
        $this->command->info("✓ Utilisateurs : {$count} comptes");
    }
}
