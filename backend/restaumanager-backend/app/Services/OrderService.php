<?php
namespace App\Services;

use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderItemChanged;
use App\Events\OrderReady;
use App\Events\OrderValidated;
use App\Events\TableStatusChanged;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPerson;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;

class OrderService
{
    // ── Ouvrir une commande sur place ──────────────────────────────
    public function createDineIn(array $data, int $userId, string $userName): Order
    {
        DB::beginTransaction();
        try {
            $table = RestaurantTable::lockForUpdate()->findOrFail($data['table_id']);

            if ($table->status === 'occupied') {
                throw new \Exception('La table est déjà occupée.');
            }

            $order = Order::create([
                'user_id'       => $userId,
                'table_id'      => $table->id,
                'type'          => 'sur_place',
                'status'        => 'open',
                'persons_count' => $data['persons_count'] ?? 1,
                'notes'         => $data['notes'] ?? null,
                'total_amount'  => 0,
                'discount_amount' => 0,
                'opened_at'     => now(),
            ]);

            for ($i = 0; $i < $order->persons_count; $i++) {
                OrderPerson::create([
                    'order_id'     => $order->id,
                    'person_index' => $i,
                    'label'        => 'Personne ' . ($i + 1),
                    'subtotal'     => 0,
                ]);
            }

            $oldStatus = $table->status;
            $table->update(['status' => 'occupied']);
            DB::commit();

            OrderCreated::dispatch($order->load(['table.section', 'persons']), $userName);
            TableStatusChanged::dispatch($table->fresh(), $oldStatus, 'occupied', $order->id);

            return $order->fresh()->load(['table.section', 'persons']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Ajouter un article ─────────────────────────────────────────
    public function addItem(Order $order, int $personIndex, int $menuItemId, int $quantity, ?string $kitchenNote = null): OrderItem
    {
        if (!in_array($order->status, ['open', 'validated'])) {
            throw new \Exception('Impossible d\'ajouter un article à une commande ' . $order->status . '.');
        }

        $menuItem = MenuItem::findOrFail($menuItemId);

        DB::beginTransaction();
        try {
            $person = $order->persons()->where('person_index', $personIndex)->firstOrFail();

            $item = OrderItem::create([
                'order_person_id'  => $person->id,
                'menu_item_id'     => $menuItemId,
                'item_name'        => $menuItem->name,
                'unit_price'       => $menuItem->price,
                'quantity'         => $quantity,
                'discount_percent' => 0,
                'is_free'          => false,
                'is_returned'      => false,
                'kitchen_note'     => $kitchenNote,
                'line_total'       => $menuItem->price * $quantity,
            ]);

            $this->recalculateTotals($order);

            $wasOpen = $order->status === 'open';
            if ($wasOpen) {
                $order->update(['status' => 'validated']);
            }

            DB::commit();

            OrderItemChanged::dispatch(
                $order->fresh(),
                $item,
                OrderItemChanged::ACTION_ADDED,
                $menuItem->name,
                $personIndex
            );

            if ($wasOpen) {
                OrderValidated::dispatch($order->fresh(), true);
            }

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Modifier la quantité ───────────────────────────────────────
    public function updateItemQuantity(Order $order, int $itemId, int $quantity): OrderItem
    {
        if (!in_array($order->status, ['open', 'validated'])) {
            throw new \Exception('Impossible de modifier une commande ' . $order->status . '.');
        }

        DB::beginTransaction();
        try {
            $item = OrderItem::whereHas('orderPerson', fn($q) => $q->where('order_id', $order->id))
                ->findOrFail($itemId);

            $item->quantity = $quantity;
            $item->save(); // booted() recalcule line_total

            $this->recalculateTotals($order);
            DB::commit();

            $personIndex = $item->orderPerson->person_index;
            OrderItemChanged::dispatch($order->fresh(), $item->fresh(), OrderItemChanged::ACTION_UPDATED, $item->item_name, $personIndex);

            return $item->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Supprimer un article ───────────────────────────────────────
    public function removeItem(Order $order, int $itemId): void
    {
        if (!in_array($order->status, ['open', 'validated'])) {
            throw new \Exception('Impossible de supprimer un article d\'une commande ' . $order->status . '.');
        }

        DB::beginTransaction();
        try {
            $item        = OrderItem::whereHas('orderPerson', fn($q) => $q->where('order_id', $order->id))->findOrFail($itemId);
            $itemName    = $item->item_name;
            $personIndex = $item->orderPerson->person_index;

            $item->delete();
            $this->recalculateTotals($order);
            DB::commit();

            OrderItemChanged::dispatch($order->fresh(), null, OrderItemChanged::ACTION_REMOVED, $itemName, $personIndex);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Offrir un article ──────────────────────────────────────────
    public function offerItem(Order $order, int $itemId, string $reason): OrderItem
    {
        DB::beginTransaction();
        try {
            $item = OrderItem::whereHas('orderPerson', fn($q) => $q->where('order_id', $order->id))->findOrFail($itemId);
            $item->is_free    = true;
            $item->free_reason = $reason;
            $item->save();

            $this->recalculateTotals($order);
            DB::commit();

            $personIndex = $item->orderPerson->person_index;
            OrderItemChanged::dispatch($order->fresh(), $item->fresh(), OrderItemChanged::ACTION_OFFERED, $item->item_name, $personIndex);

            return $item->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Appliquer une remise ───────────────────────────────────────
    public function applyDiscount(Order $order, int $itemId, float $discountPercent): OrderItem
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new \Exception('La remise doit être entre 0 et 100 %.');
        }

        DB::beginTransaction();
        try {
            $item = OrderItem::whereHas('orderPerson', fn($q) => $q->where('order_id', $order->id))->findOrFail($itemId);
            $item->discount_percent = $discountPercent;
            $item->save();

            $this->recalculateTotals($order);
            DB::commit();

            $personIndex = $item->orderPerson->person_index;
            OrderItemChanged::dispatch($order->fresh(), $item->fresh(), OrderItemChanged::ACTION_DISCOUNT, $item->item_name, $personIndex);

            return $item->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Note cuisine ───────────────────────────────────────────────
    public function addItemNote(Order $order, int $itemId, string $note): OrderItem
    {
        $item = OrderItem::whereHas('orderPerson', fn($q) => $q->where('order_id', $order->id))->findOrFail($itemId);
        $item->update(['kitchen_note' => $note]);
        $personIndex = $item->orderPerson->person_index;
        OrderItemChanged::dispatch($order->fresh(), $item->fresh(), OrderItemChanged::ACTION_NOTE, $item->item_name, $personIndex);
        return $item->fresh();
    }

    // ── Retourner un article (traçabilité) ─────────────────────────
    public function returnItem(Order $order, int $itemId, string $reason): OrderItem
    {
        DB::beginTransaction();
        try {
            $item = OrderItem::whereHas('orderPerson', fn($q) => $q->where('order_id', $order->id))->findOrFail($itemId);
            $item->is_returned   = true;
            $item->return_reason = $reason;
            $item->save();

            $this->recalculateTotals($order);
            DB::commit();

            $personIndex = $item->orderPerson->person_index;
            OrderItemChanged::dispatch($order->fresh(), $item->fresh(), OrderItemChanged::ACTION_REMOVED, $item->item_name, $personIndex);

            return $item->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Valider manuellement ───────────────────────────────────────
    public function validateOrder(Order $order): Order
    {
        if ($order->status !== 'open') {
            throw new \Exception('Seule une commande ouverte peut être validée manuellement.');
        }
        $order->update(['status' => 'validated']);
        OrderValidated::dispatch($order->fresh(), false);
        return $order->fresh();
    }

    // ── Marquer prête (cuisine) ────────────────────────────────────
    public function markReady(Order $order): Order
    {
        if ($order->status !== 'validated') {
            throw new \Exception('Seule une commande validée peut être marquée prête.');
        }
        $order->update(['status' => 'validated']); // statut "ready" n'existe pas dans ton enum — on garde validated + SSE
        OrderReady::dispatch($order->fresh());
        return $order->fresh();
    }

    // ── Annuler ────────────────────────────────────────────────────
    public function cancel(Order $order, string $cancelledBy): Order
    {
        if (in_array($order->status, ['paid', 'cancelled'])) {
            throw new \Exception('Cette commande est déjà clôturée.');
        }

        DB::beginTransaction();
        try {
            $order->update(['status' => 'cancelled', 'closed_at' => now()]);

            if ($order->table_id) {
                $table     = $order->table;
                $oldStatus = $table->status;
                $table->update(['status' => 'available']);
                TableStatusChanged::dispatch($table->fresh(), $oldStatus, 'available', $order->id);
            }

            DB::commit();
            OrderCancelled::dispatch($order->fresh(), $cancelledBy);
            return $order->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Note commande ──────────────────────────────────────────────
    public function addNote(Order $order, string $note): Order
    {
        $order->update(['notes' => $note]);
        return $order->fresh();
    }

    // ── Recalcul des totaux ────────────────────────────────────────
    private function recalculateTotals(Order $order): void
    {
        $order->load('persons.items');
        foreach ($order->persons as $person) {
            $sub = $person->items->where('is_returned', false)->sum('line_total');
            $person->update(['subtotal' => $sub]);
        }
        $total = $order->persons->sum('subtotal');
        $order->update(['total_amount' => $total]);
    }
}
