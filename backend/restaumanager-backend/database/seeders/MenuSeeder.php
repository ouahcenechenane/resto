<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // ── Sections ─────────────────────────────────────────────────────
        $sections = [
            ['code'=>'salle',     'name'=>'Restaurant',  'icon'=>'🍴', 'order'=>1],
            ['code'=>'terrasse',  'name'=>'Terrasse',    'icon'=>'🌿', 'order'=>2],
            ['code'=>'caffet',    'name'=>'Cafétéria',   'icon'=>'☕', 'order'=>3],
            ['code'=>'emporter',  'name'=>'À Emporter',  'icon'=>'🧾', 'order'=>4],
        ];

        foreach ($sections as $s) {
            DB::table('sections')->updateOrInsert(
                ['code' => $s['code']],
                array_merge($s, ['is_active'=>true,'created_at'=>now(),'updated_at'=>now()])
            );
        }

        $salleId    = DB::table('sections')->where('code','salle')->value('id');
        $cafetId    = DB::table('sections')->where('code','caffet')->value('id');
        $terrId     = DB::table('sections')->where('code','terrasse')->value('id');
        $emporterId = DB::table('sections')->where('code','emporter')->value('id');

        // ── Catégories + articles ────────────────────────────────────────
        $menu = [

            // ═══ RESTAURANT (salle + terrasse) ═══════════════════════════
            [
                'section_id' => $salleId,
                'name' => 'Soupes & Entrées', 'icon' => '🍲', 'type' => 'food', 'order' => 1,
                'items' => [
                    ['name'=>'Chorba Frik',         'price'=>350,  'desc'=>'Soupe traditionnelle au blé concassé'],
                    ['name'=>'Harira',               'price'=>320,  'desc'=>'Soupe de tomates et lentilles'],
                    ['name'=>"Brick à l'œuf",        'price'=>280,  'desc'=>'Brick croustillante garnie'],
                    ['name'=>'Salade César',         'price'=>450,  'desc'=>'Laitue, parmesan, croûtons'],
                    ['name'=>'Salade maison',        'price'=>380,  'desc'=>'Légumes frais de saison'],
                ],
            ],
            [
                'section_id' => $salleId,
                'name' => 'Plats principaux', 'icon' => '🍽', 'type' => 'food', 'order' => 2,
                'items' => [
                    ['name'=>'Couscous agneau',      'price'=>1200, 'desc'=>'Semoule fine, légumes, agneau'],
                    ['name'=>'Couscous poulet',      'price'=>980,  'desc'=>'Semoule fine, légumes, poulet'],
                    ['name'=>'Tajine poulet',        'price'=>980,  'desc'=>'Tajine aux olives et citron'],
                    ['name'=>'Tajine agneau',        'price'=>1250, 'desc'=>'Tajine aux pruneaux'],
                    ['name'=>'Brochettes agneau',   'price'=>1250, 'desc'=>'Grillées, servies avec frites'],
                    ['name'=>'Brochettes poulet',   'price'=>950,  'desc'=>'Marinées aux épices'],
                    ['name'=>'Pizza maison',         'price'=>980,  'desc'=>'Pâte fine, garniture au choix'],
                    ['name'=>'Escalope milanaise',  'price'=>1100, 'desc'=>'Panée, citron, câpres'],
                    ['name'=>'Steak frites',        'price'=>1300, 'desc'=>'Steak grillé, frites maison'],
                ],
            ],
            [
                'section_id' => $salleId,
                'name' => 'Desserts', 'icon' => '🍮', 'type' => 'food', 'order' => 3,
                'items' => [
                    ['name'=>'Baklawa',             'price'=>320,  'desc'=>'Assortiment de pâtisseries'],
                    ['name'=>'Crème caramel',       'price'=>280,  'desc'=>'Faite maison'],
                    ['name'=>'Mousse chocolat',     'price'=>300,  'desc'=>'Onctueuse et légère'],
                    ['name'=>'Glace boule',         'price'=>150,  'desc'=>'Parfum au choix'],
                    ['name'=>'Cornes de gazelle',   'price'=>200,  'desc'=>'3 pièces'],
                ],
            ],
            [
                'section_id' => $salleId,
                'name' => 'Boissons', 'icon' => '🥤', 'type' => 'drink', 'order' => 4,
                'items' => [
                    ['name'=>'Eau minérale',        'price'=>80,   'desc'=>'50cl'],
                    ['name'=>'Eau minérale 1L',     'price'=>120,  'desc'=>'1 litre'],
                    ['name'=>'Soda',                'price'=>150,  'desc'=>'Coca, Pepsi, Fanta'],
                    ['name'=>'Jus orange',          'price'=>250,  'desc'=>'Frais pressé'],
                    ['name'=>'Jus citron menthe',   'price'=>280,  'desc'=>'Fait maison'],
                    ['name'=>'Smoothie fruits',     'price'=>350,  'desc'=>'Fruits de saison'],
                    ['name'=>'Limonade maison',     'price'=>220,  'desc'=>'Fraîche et pétillante'],
                ],
            ],

            // ═══ CAFÉTÉRIA ════════════════════════════════════════════════
            [
                'section_id' => $cafetId,
                'name' => 'Cafés & Thés', 'icon' => '☕', 'type' => 'drink', 'order' => 1,
                'items' => [
                    ['name'=>'Café express',        'price'=>120,  'desc'=>'Serré ou allongé'],
                    ['name'=>'Cappuccino',           'price'=>220,  'desc'=>'Mousse de lait onctueuse'],
                    ['name'=>'Café latte',          'price'=>250,  'desc'=>'Doux et crémeux'],
                    ['name'=>'Café noisette',       'price'=>150,  'desc'=>'Express avec lait chaud'],
                    ['name'=>'Thé vert menthe',     'price'=>180,  'desc'=>'Traditionnel'],
                    ['name'=>'Thé noir',            'price'=>150,  'desc'=>'Darjeeling, Earl Grey'],
                    ['name'=>'Chocolat chaud',      'price'=>220,  'desc'=>'Onctueux'],
                ],
            ],
            [
                'section_id' => $cafetId,
                'name' => 'Viennoiseries', 'icon' => '🥐', 'type' => 'food', 'order' => 2,
                'items' => [
                    ['name'=>'Croissant beurre',    'price'=>120,  'desc'=>'Pur beurre, feuilleté'],
                    ['name'=>'Pain au chocolat',    'price'=>140,  'desc'=>'Double barre de chocolat'],
                    ['name'=>'Chausson pommes',     'price'=>130,  'desc'=>'Compote maison'],
                    ['name'=>'Muffin myrtilles',    'price'=>160,  'desc'=>'Moelleux'],
                    ['name'=>'Cookie chocolat',     'price'=>100,  'desc'=>'Croquant, pépites'],
                ],
            ],
            [
                'section_id' => $cafetId,
                'name' => 'Sandwichs', 'icon' => '🥪', 'type' => 'food', 'order' => 3,
                'items' => [
                    ['name'=>'Sandwich poulet',     'price'=>380,  'desc'=>'Poulet grillé, légumes'],
                    ['name'=>'Sandwich thon',       'price'=>350,  'desc'=>'Thon, tomate, maïs'],
                    ['name'=>'Club sandwich',       'price'=>450,  'desc'=>'Poulet, bacon, tomate'],
                    ['name'=>'Panini fromage',      'price'=>320,  'desc'=>'Fromage fondu, tomate'],
                ],
            ],
            [
                'section_id' => $cafetId,
                'name' => 'Jus & Sodas', 'icon' => '🧃', 'type' => 'drink', 'order' => 4,
                'items' => [
                    ['name'=>'Jus orange pressé',  'price'=>250,  'desc'=>'Frais'],
                    ['name'=>'Jus pomme',           'price'=>180,  'desc'=>'Naturel'],
                    ['name'=>'Soda canette',        'price'=>120,  'desc'=>'Coca, Pepsi, Fanta'],
                    ['name'=>'Eau minérale',        'price'=>80,   'desc'=>'50cl'],
                ],
            ],

            // ═══ TERRASSE (partage le menu salle) ════════════════════════
            [
                'section_id' => $terrId,
                'name' => 'Grillades', 'icon' => '🔥', 'type' => 'food', 'order' => 1,
                'items' => [
                    ['name'=>'Mix Grillade',        'price'=>1500, 'desc'=>'Agneau, poulet, merguez'],
                    ['name'=>'Brochettes mix',      'price'=>1200, 'desc'=>'Agneau et poulet'],
                    ['name'=>'Merguez grillées',    'price'=>800,  'desc'=>'6 pièces, harissa'],
                    ['name'=>'Côtelettes agneau',   'price'=>1400, 'desc'=>'Grillées, herbes'],
                ],
            ],
            [
                'section_id' => $terrId,
                'name' => 'Boissons', 'icon' => '🥤', 'type' => 'drink', 'order' => 2,
                'items' => [
                    ['name'=>'Jus orange',          'price'=>250,  'desc'=>'Pressé frais'],
                    ['name'=>'Eau minérale',        'price'=>80,   'desc'=>'50cl'],
                    ['name'=>'Soda',                'price'=>150,  'desc'=>'Coca, Fanta, Pepsi'],
                    ['name'=>'Limonade',            'price'=>200,  'desc'=>'Maison'],
                ],
            ],

            // ═══ À EMPORTER (même menu que restaurant) ═══════════════════
            [
                'section_id' => $emporterId,
                'name' => 'Plats', 'icon' => '🍽', 'type' => 'food', 'order' => 1,
                'items' => [
                    ['name'=>'Couscous agneau',     'price'=>1200, 'desc'=>'Emporté dans barquette'],
                    ['name'=>'Couscous poulet',     'price'=>980,  'desc'=>'Emporté dans barquette'],
                    ['name'=>'Tajine poulet',       'price'=>980,  'desc'=>'Emporté'],
                    ['name'=>'Brochettes agneau',   'price'=>1250, 'desc'=>'Avec pain et frites'],
                    ['name'=>'Pizza maison',        'price'=>980,  'desc'=>'En boîte'],
                ],
            ],
            [
                'section_id' => $emporterId,
                'name' => 'Boissons', 'icon' => '🥤', 'type' => 'drink', 'order' => 2,
                'items' => [
                    ['name'=>'Eau minérale',        'price'=>80,   'desc'=>'50cl'],
                    ['name'=>'Soda canette',        'price'=>120,  'desc'=>'Coca, Pepsi, Fanta'],
                    ['name'=>'Jus orange',          'price'=>250,  'desc'=>'Frais pressé'],
                ],
            ],
        ];

        foreach ($menu as $catData) {
            $items = $catData['items'];
            unset($catData['items']);

            $categoryData = array_merge($catData, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('categories')->updateOrInsert([
                'section_id' => $catData['section_id'],
                'name'       => $catData['name'],
            ], $categoryData);

            $catId = DB::table('categories')
                ->where('section_id', $catData['section_id'])
                ->where('name', $catData['name'])
                ->value('id');

            foreach ($items as $order => $item) {
                DB::table('menu_items')->updateOrInsert([
                    'category_id' => $catId,
                    'name'        => $item['name'],
                ], [
                    'description'  => $item['desc'] ?? null,
                    'price'        => $item['price'],
                    'is_available' => true,
                    'order'        => $order,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        $cats  = DB::table('categories')->count();
        $items = DB::table('menu_items')->count();
        $this->command->info("✓ Menu créé : {$cats} catégories, {$items} articles");
    }
}
