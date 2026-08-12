<?php
namespace App\Services;

use App\Events\MenuUpdated;
use App\Services\SseEventService;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Section;

class MenuService
{
    // ── Articles ───────────────────────────────────────────────────
public function createItem(array $data): MenuItem
{
    $item = MenuItem::create([
        'category_id'  => $data['category_id'],
        'name'         => $data['name'],
        'description'  => $data['description'] ?? null,
        'price'        => $data['price'],
        'image'        => $data['image'] ?? null,
        'is_available' => $data['is_available'] ?? true,
        'order'        => $data['order'] ?? 0,
    ]);

    MenuUpdated::dispatch(
        MenuUpdated::ACTION_ITEM_CREATED,
        $item->id, $item->name, $item->price, $item->is_available
    );
    
    // ← AJOUTER CES LIGNES:
    SseEventService::dispatch('menu.item_created', [
        'menu_item_id'  => $item->id,
        'item_name'     => $item->name,
        'category_id'   => $item->category_id,
        'price'         => $item->price,
        'is_available'  => $item->is_available,
        'created_at'    => now()->toIso8601String(),
    ]);
    
    return $item;
}

 public function updateItem(MenuItem $item, array $data): MenuItem
{
    $oldPrice = $item->price;  // ← AJOUTER CETTE LIGNE (avant update)
    
    $item->update(array_filter([
        'category_id' => $data['category_id'] ?? null,
        'name'        => $data['name']        ?? null,
        'description' => $data['description'] ?? null,
        'price'       => $data['price']       ?? null,
        'image'       => $data['image']       ?? null,
        'order'       => $data['order']       ?? null,
    ], fn($v) => !is_null($v)));

    MenuUpdated::dispatch(
        MenuUpdated::ACTION_ITEM_UPDATED,
        $item->id, $item->fresh()->name,
        $item->fresh()->price, $item->fresh()->is_available
    );
    
    // ← AJOUTER CES LIGNES (après dispatch):
    SseEventService::dispatch('menu.item_updated', [
        'menu_item_id'  => $item->id,
        'item_name'     => $item->fresh()->name,
        'old_price'     => $oldPrice,
        'new_price'     => $item->fresh()->price,
        'category_id'   => $item->category_id,
        'is_available'  => $item->fresh()->is_available,
        'updated_at'    => now()->toIso8601String(),
    ]);
    
    return $item->fresh();
}

public function toggleItem(MenuItem $item): MenuItem
{
    $oldStatus = $item->is_available;  // ← AJOUTER
    
    $item->update(['is_available' => !$item->is_available]);
    MenuUpdated::dispatch(
        MenuUpdated::ACTION_ITEM_TOGGLED,
        $item->id, $item->name, $item->price, $item->fresh()->is_available
    );
    
    // ← AJOUTER CES LIGNES:
    SseEventService::dispatch('menu.item_toggled', [
        'menu_item_id'   => $item->id,
        'item_name'      => $item->name,
        'old_status'     => $oldStatus,
        'new_status'     => $item->fresh()->is_available,
        'action'         => $item->fresh()->is_available ? 'enabled' : 'disabled',
        'updated_at'     => now()->toIso8601String(),
    ]);
    
    return $item->fresh();
}
public function deleteItem(MenuItem $item): void
{
    $inUse = \App\Models\OrderItem::where('menu_item_id', $item->id)
        ->whereHas('orderPerson.order', fn($q) => $q->whereIn('status', ['open', 'validated', 'billed']))
        ->exists();
    if ($inUse) {
        throw new \Exception('Cet article est présent dans une commande en cours.');
    }
    MenuUpdated::dispatch(MenuUpdated::ACTION_ITEM_DELETED, $item->id, $item->name);
    
    // ← AJOUTER CES LIGNES (avant delete):
    SseEventService::dispatch('menu.item_deleted', [
        'menu_item_id'  => $item->id,
        'item_name'     => $item->name,
        'category_id'   => $item->category_id,
        'deleted_at'    => now()->toIso8601String(),
    ]);
    
    $item->delete();
}

    // ── Catégories ─────────────────────────────────────────────────
public function createCategory(array $data): Category
{
    $category = Category::create([
        'section_id'  => $data['section_id'],
        'name'        => $data['name'],
        'icon'        => $data['icon'] ?? null,
        'type'        => $data['type'] ?? null,
        'order'       => $data['order'] ?? 0,
        'is_active'   => $data['is_active'] ?? true,
    ]);
    MenuUpdated::dispatch(MenuUpdated::ACTION_CAT_CREATED, $category->id, $category->name);
    
    // ← AJOUTER CES LIGNES:
    SseEventService::dispatch('menu.category_created', [
        'category_id' => $category->id,
        'category_name' => $category->name,
        'section_id'  => $category->section_id,
        'icon'        => $category->icon,
        'is_active'   => $category->is_active,
        'created_at'  => now()->toIso8601String(),
    ]);
    
    return $category;
}
public function updateCategory(Category $category, array $data): Category
{
    $oldName = $category->name;  // ← AJOUTER
    
    $category->update(array_filter([
        'name'      => $data['name']      ?? null,
        'icon'      => $data['icon']      ?? null,
        'order'     => $data['order']     ?? null,
        'is_active' => $data['is_active'] ?? null,
    ], fn($v) => !is_null($v)));
    MenuUpdated::dispatch(MenuUpdated::ACTION_CAT_UPDATED, $category->id, $category->fresh()->name);
    
    // ← AJOUTER CES LIGNES:
    SseEventService::dispatch('menu.category_updated', [
        'category_id' => $category->id,
        'old_name'    => $oldName,
        'new_name'    => $category->fresh()->name,
        'icon'        => $category->fresh()->icon,
        'is_active'   => $category->fresh()->is_active,
        'updated_at'  => now()->toIso8601String(),
    ]);
    
    return $category->fresh();
}

public function deleteCategory(Category $category): void
{
    if ($category->items()->exists()) {
        throw new \Exception('Impossible de supprimer une catégorie contenant des articles.');
    }
    MenuUpdated::dispatch(MenuUpdated::ACTION_CAT_DELETED, $category->id, $category->name);
    
    // ← AJOUTER CES LIGNES (avant delete):
    SseEventService::dispatch('menu.category_deleted', [
        'category_id'   => $category->id,
        'category_name' => $category->name,
        'section_id'    => $category->section_id,
        'deleted_at'    => now()->toIso8601String(),
    ]);
    
    $category->delete();
}
    // ── Menu complet ───────────────────────────────────────────────
    public function getFullMenu(?int $sectionId = null): array
    {
        $query = Section::where('is_active', true)
            ->with(['categories' => function ($q) {
                $q->where('is_active', true)->orderBy('order')
                  ->with(['items' => fn($q) => $q->where('is_available', true)->orderBy('order')]);
            }])->orderBy('order');

        if ($sectionId) $query->where('id', $sectionId);

        return $query->get()->toArray();
    }
}
