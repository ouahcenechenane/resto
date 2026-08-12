<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReservationController;

/*
|─────────────────────────────────────────────────────────────────────────────
|  RestauManager v2 — Routes API
|─────────────────────────────────────────────────────────────────────────────
|
|  Rôles : admin | caissier_restau | caissier_caffet | serveur_restau | serveur_caffet | reception | cuisiner
|  Sections : salle | terrasse | caffet | emporter
|
*/

// AUTH (public)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout']);
        Route::get('me',               [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('refresh',         [AuthController::class, 'refresh']);
    });
});

Route::middleware(['auth:sanctum', 'activity.log'])->group(function () {

    $allRoles      = 'admin,caissier_restau,caissier_caffet,serveur_restau,serveur_caffet,reception,cuisiner';
    $caissiersAdmin = 'admin,caissier_restau,caissier_caffet,serveur_restau,serveur_caffet';
    $receptionAdmin = 'admin,reception';

    // MENU (tous)
    Route::middleware("role:$allRoles")->prefix('menu')->group(function () {
        Route::get('/',             [MenuController::class, 'fullMenu']);
        Route::get('/{code}',       [MenuController::class, 'bySection']);
        Route::get('/items/{item}', [MenuController::class, 'showItem']);
    });

    // TABLES — lecture (tous sauf réception)
    Route::middleware("role:$allRoles")->prefix('tables')->group(function () {
        Route::get('/',         [TableController::class, 'index']);
        Route::get('/{table}',  [TableController::class, 'show']);
    });

    // TABLES — créer/modifier (caissiers + admin)
    Route::middleware("role:$caissiersAdmin")->prefix('tables')->group(function () {
        Route::post('/',                    [TableController::class, 'store']);
        Route::put('/{table}',              [TableController::class, 'update']);
        Route::patch('/{table}/status',     [TableController::class, 'updateStatus']);
        Route::patch('/{table}/persons',    [TableController::class, 'updatePersons']);
    });

    // TABLES — supprimer (admin seulement)
    Route::middleware('role:admin')->prefix('tables')->group(function () {
        Route::delete('/{table}', [TableController::class, 'destroy']);
    });

    // COMMANDES SUR PLACE — tous les rôles (sauf réception)
    $sallRoles = 'admin,caissier_restau,caissier_caffet,serveur_restau,serveur_caffet';
    Route::middleware("role:$sallRoles")->prefix('orders')->group(function () {
        Route::get('/',                               [OrderController::class, 'index']);
        Route::get('/{order}',                        [OrderController::class, 'show']);
        Route::get('/{id}/items',                     [OrderController::class, 'items']); // ← AJOUTÉ ICI
        Route::post('/',                              [OrderController::class, 'store']);
        Route::post('/{order}/items',                 [OrderController::class, 'addItem']);
        Route::put('/{order}/items/{item}',           [OrderController::class, 'updateItemQty']);
        Route::delete('/{order}/items/{item}',        [OrderController::class, 'removeItem']);
        Route::patch('/{order}/validate',             [OrderController::class, 'validateOrder']);
        Route::post('/{order}/note',                  [OrderController::class, 'addNote']);
        Route::post('/{order}/items/{item}/note',     [OrderController::class, 'addItemNote']);
        Route::patch('/{order}/items/{item}/return',  [OrderController::class, 'returnItem']);
    });

    // COMMANDES — actions caissiers uniquement
    Route::middleware("role:$caissiersAdmin")->prefix('orders')->group(function () {
        Route::post('/{order}/offer',                          [OrderController::class, 'offerItem']);
        Route::patch('/{order}/items/{item}/discount',         [OrderController::class, 'applyDiscount']);
        Route::patch('/{order}/cancel',                        [OrderController::class, 'cancel']);
        Route::post('/{order}/ticket',                         [TicketController::class, 'generateFromOrder']);
        Route::post('/{order}/ticket/person/{personIndex}',    [TicketController::class, 'generateForPerson']);
    });

    // ── COMMANDES À EMPORTER (réception + admin) ──────────────────────────
    Route::middleware("role:$receptionAdmin")->prefix('emporter')->group(function () {
        Route::get('/',                    [OrderController::class, 'emporterIndex']);    // Liste des commandes à emporter
        Route::post('/',                   [OrderController::class, 'emporterStore']);    // Créer une commande à emporter
        Route::get('/{order}',             [OrderController::class, 'show']);             // Détail
        Route::post('/{order}/items',      [OrderController::class, 'addItem']);          // Ajouter article
        Route::put('/{order}/items/{item}',[OrderController::class, 'updateItemQty']);    // Modifier qté
        Route::delete('/{order}/items/{item}',[OrderController::class, 'removeItem']);   // Supprimer article
        Route::patch('/{order}/validate',  [OrderController::class, 'validateOrder']);   // Envoyer en cuisine
        Route::patch('/{order}/ready',     [OrderController::class, 'markReady']);       // Prêt
        Route::patch('/{order}/cancel',    [OrderController::class, 'cancel']);          // Annuler
        Route::post('/{order}/ticket',     [TicketController::class, 'generateFromOrder']);       // Générer ticket & payer
        Route::post('/{order}/note',       [OrderController::class, 'addNote']);         // Ajouter note
    });

    // VUE CUISINE (tous)
    Route::middleware("role:$allRoles")->prefix('cuisine')->group(function () {
        Route::get('/orders',          [OrderController::class, 'kitchenView']);
        Route::patch('/{order}/ready', [OrderController::class, 'markReady']);
    });

    // TICKETS (caissiers + admin + réception pour emporter)
    Route::middleware("role:$caissiersAdmin")->prefix('tickets')->group(function () {
        Route::get('/',                                  [TicketController::class, 'index']);
        Route::get('/summary',                           [TicketController::class, 'summary']);
        Route::get('/{ticket}',                          [TicketController::class, 'show']);
        Route::post('/{ticket}/pay',                     [TicketController::class, 'pay']);
        Route::get('/export/csv',                        [TicketController::class, 'exportCsv']);
        Route::post('/{ticket}/print',                   [TicketController::class, 'markPrinted']);
        Route::post('/{ticket}/print/person/{index}',    [TicketController::class, 'printForPerson']);
    });

    // RÉSERVATIONS — Réception peut lire et créer
    Route::middleware('role:reception')->prefix('reservations')->group(function () {
        Route::get('/',      [ReservationController::class, 'index']);
        Route::post('/',     [ReservationController::class, 'store']);
        Route::get('/{reservation}', [ReservationController::class, 'show']);
    });

    // RÉSERVATIONS (tous sauf réception) — accès complet
    Route::middleware("role:$sallRoles")->prefix('reservations')->group(function () {
        Route::get('/',                         [ReservationController::class, 'index']);
        Route::post('/',                        [ReservationController::class, 'store']);
        Route::get('/{reservation}',            [ReservationController::class, 'show']);
        Route::patch('/{reservation}/checkin',  [ReservationController::class, 'checkin']);
        Route::patch('/{reservation}/checkout', [ReservationController::class, 'checkout']);
    });

    Route::middleware("role:$caissiersAdmin")->prefix('reservations')->group(function () {
        Route::put('/{reservation}',            [ReservationController::class, 'update']);
        Route::patch('/{reservation}/cancel',   [ReservationController::class, 'cancel']);
        Route::delete('/{reservation}',         [ReservationController::class, 'destroy']);
    });

    // ADMIN — Gestion menu (admin complet, cuisiner peut ajouter/modifier items)
    Route::middleware('role:admin')->prefix('admin/menu')->group(function () {
        Route::post('categories',           [MenuController::class, 'storeCategory']);
        Route::put('categories/{category}',      [MenuController::class, 'updateCategory']);
        Route::delete('categories/{category}',   [MenuController::class, 'destroyCategory']);
        Route::post('items/upload-image',        [MenuController::class, 'uploadImage']); 
        Route::put('items/{item}',          [MenuController::class, 'updateItem']);
        Route::delete('items/{item}',       [MenuController::class, 'destroyItem']);
        Route::patch('items/{item}/toggle', [MenuController::class, 'toggleItem']);
    });

    // Cuisinier peut ajouter/modifier des plats au menu
    Route::middleware('role:admin,cuisiner')->prefix('admin/menu')->group(function () {
        Route::post('items', [MenuController::class, 'storeItem']);
    });

    // ADMIN — Gestion comptes employés
    Route::middleware('role:admin')->prefix('admin/users')->group(function () {
        Route::get('/',                       [UserController::class, 'index']);
        Route::post('/',                      [UserController::class, 'store']);
        Route::get('/{user}',                 [UserController::class, 'show']);
        Route::put('/{user}',                 [UserController::class, 'update']);
        Route::delete('/{user}',              [UserController::class, 'destroy']);
        Route::patch('/{user}/toggle',        [UserController::class, 'toggleActive']);
        Route::patch('/{user}/role',          [UserController::class, 'updateRole']);
        Route::patch('/{user}/permissions',   [UserController::class, 'updatePermissions']);
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    // ADMIN + CAISSIERS — Statistiques
    Route::middleware("role:$caissiersAdmin")->prefix('admin/stats')->group(function () {
        Route::get('/dashboard',   [TicketController::class, 'dashboardStats']);
        Route::get('/daily',       [TicketController::class, 'dailyStats']);
        Route::get('/weekly',      [TicketController::class, 'weeklyStats']);
        Route::get('/top-items',   [TicketController::class, 'topItems']);
        Route::get('/by-section',  [TicketController::class, 'statsBySection']);
    });
});


// ═══════════════════════════════════════════════════════════════════════════
//  SSE — Server-Sent Events (temps réel sans Redis ni Pusher)
// ═══════════════════════════════════════════════════════════════════════════
use App\Http\Controllers\Api\StreamController;

// Flux SSE persistant — auth via ?token=XXX (EventSource ne supporte pas les headers)
Route::get('/stream', [StreamController::class, 'stream'])
    ->withoutMiddleware(['auth:sanctum', 'activity.log']);

// Endpoint polling fallback — auth Sanctum classique
Route::middleware('auth:sanctum')->get('/events/latest', [StreamController::class, 'latest']);
