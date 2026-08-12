<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $salle   = DB::table('sections')->where('code','salle')->value('id');
        $terr    = DB::table('sections')->where('code','terrasse')->value('id');
        $caffet  = DB::table('sections')->where('code','caffet')->value('id');

        $tables = [];

        // Salle — tables 1 à 12
        for ($i = 1; $i <= 12; $i++) {
            $tables[] = [
                'section_id' => $salle,
                'number'     => (string)$i,
                'capacity'   => ($i <= 4) ? 2 : (($i <= 8) ? 4 : 6),
                'status'     => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Terrasse — tables T1 à T8
        for ($i = 1; $i <= 8; $i++) {
            $tables[] = [
                'section_id' => $terr,
                'number'     => 'T'.$i,
                'capacity'   => 4,
                'status'     => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Cafétéria — tables C1 à C6
        for ($i = 1; $i <= 6; $i++) {
            $tables[] = [
                'section_id' => $caffet,
                'number'     => 'C'.$i,
                'capacity'   => ($i <= 2) ? 2 : 4,
                'status'     => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($tables as $tableData) {
            DB::table('tables')->updateOrInsert([
                'section_id' => $tableData['section_id'],
                'number'     => $tableData['number'],
            ], $tableData);
        }

        $count = DB::table('tables')->count();
        $this->command->info("✓ Tables créées ou mises à jour : {$count} tables");
    }
}
