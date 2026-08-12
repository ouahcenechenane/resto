<?php
namespace App\Services;

use App\Events\EmporterCreated;
use App\Events\OrderCancelled;
use App\Events\OrderItemChanged;
use App\Events\OrderReady;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPerson;
use Illuminate\Support\Facades\DB;

class EmporterService
{
    public function create(array $data, int $userId, string $userName): Order
    {
        if (empty($data['items'])) {
            throw new \Exception('Une commande à emporter doit contenir au moins un article.');
        }

        DB::beginTransaction();
        try {
            // Générer un numéro de commande emporter
            $count  = Order::where('type', 'emporter')->whereDate('created_at', today())->count() + 1;
            $number = '#' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $order = Order::create([
                'user_id'        => $userId,
                'table_id'       => null,
                'type'           => 'emporter',
                'status'         => 'validated',
                'persons_count'  => 1,
                'client_name'    => $data['client_name'] ?? null,
                'order_number'   => $number,
                'notes'          => $data['notes'] ?? null,
                'total_amount'   => 0,
                'discount_amount'=> 0,
                'opened_at'      => now(),
            ]);

            $person = OrderPerson::create([
                'order_id'     => $order->id,
                'person_index' => 0,
                'label'        => $data['client_name'] ?? 'Emporter',
                'subtotal'     => 0,
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $itemData) {
                $menuItem  = MenuItem::findOrFail($itemData['menu_item_id']);
                $qty       = max(1, (int) ($itemData['quantity'] ?? 1));
                $lineTotal = $menuItem->price * $qty;

                OrderItem::create([
                    'order_person_id'  => $person->id,
                    'menu_item_id'     => $menuItem->id,
                    'item_name'        => $menuItem->name,
                    'unit_price'       => $menuItem->price,
                    'quantity'         => $qty,
                    'discount_percent' => 0,
                    'is_free'          => false,
                    'is_returned'      => false,
                    'kitchen_note'     => $itemData['kitchen_note'] ?? null,
                    'line_total'       => $lineTotal,
                ]);
                $subtotal += $lineTotal;
            }

            $person->update(['subtotal' => $subtotal]);
            $order->update(['total_amount' => $subtotal]);
            DB::commit();

            EmporterCreated::dispatch($order->fresh()->load('persons.items'), $userName);
            return $order->fresh()->load(['persons.items']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markReady(Order $order): Order
    {
        if ($order->type !== 'emporter') {
            throw new \Exception('Cette commande n\'est pas une commande à emporter.');
        }
        if ($order->status !== 'validated') {
            throw new \Exception('Seule une commande validée peut être marquée prête.');
        }
        // On dispatch l'event ready (statut reste validated dans l'enum)
        OrderReady::dispatch($order->fresh());
        return $order->fresh();
    }

    public function addItem(Order $order, int $menuItemId, int $quantity, ?string $kitchenNote = null): OrderItem
    {
        $menuItem = MenuItem::findOrFail($menuItemId);
        DB::beginTransaction();
        try {
            $person = $order->persons()->where('person_index', 0)->firstOrFail();
            $item   = OrderItem::create([
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

            $sub = $person->items()->sum('line_total');
            $person->update(['subtotal' => $sub]);
            $order->update(['total_amount' => $sub]);
            DB::commit();

            OrderItemChanged::dispatch($order->fresh(), $item, OrderItemChanged::ACTION_ADDED, $menuItem->name, 0);
            return $item;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function cancel(Order $order, string $cancelledBy): Order
    {
        if (in_array($order->status, ['paid', 'cancelled'])) {
            throw new \Exception('Cette commande est déjà clôturée.');
        }
        $order->update(['status' => 'cancelled', 'closed_at' => now()]);
        OrderCancelled::dispatch($order->fresh(), $cancelledBy);
        return $order->fresh();
    }
}
