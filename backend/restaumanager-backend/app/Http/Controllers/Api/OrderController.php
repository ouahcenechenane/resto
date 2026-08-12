<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['table.section', 'user:id,name', 'persons.items'])
            ->where('type', 'sur_place');

        if ($request->has('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }
        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    // NOUVEAU : Récupérer les items d'une commande
    public function items($id): JsonResponse
    {
        $order = Order::with(['persons.items.menuItem'])->findOrFail($id);
        $items = [];
        
        foreach ($order->persons as $person) {
            foreach ($person->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'item_name' => $item->menuItem->name ?? 'Article',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'person_index' => $person->index ?? 0,
                    'kitchen_note' => $item->kitchen_note ?? '',
                    'free' => $item->is_free ?? false,
                    'returned' => $item->is_returned ?? false,
                    'discount' => $item->discount_percent ?? 0,
                ];
            }
        }
        
        return response()->json($items);
    }

    // Vue cuisine : uniquement les commandes actives avec articles
    public function kitchenView(): JsonResponse
    {
        $orders = Order::with(['table.section', 'persons.items', 'user:id,name'])
            ->whereIn('status', ['open', 'validated'])
            ->whereHas('persons.items')
            ->orderBy('updated_at')
            ->get();

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(
            $order->load(['table.section', 'user:id,name', 'persons.items', 'tickets'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_id'      => 'required|integer|exists:tables,id',
            'persons_count' => 'required|integer|min:1|max:20',
            'notes'         => 'nullable|string|max:500',
        ]);

        try {
            $order = $this->orderService->createDineIn(
                $data, $request->user()->id, $request->user()->name
            );
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addItem(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'person_index'  => 'required|integer|min:0',
            'menu_item_id'  => 'required|integer|exists:menu_items,id',
            'quantity'      => 'required|integer|min:1',
            'kitchen_note'  => 'nullable|string|max:255',
        ]);

        try {
            $item = $this->orderService->addItem(
                $order, $data['person_index'], $data['menu_item_id'],
                $data['quantity'], $data['kitchen_note'] ?? null
            );
            return response()->json([
                'item'  => $item,
                'order' => $order->fresh()->load('persons.items'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateItemQty(Request $request, Order $order, int $itemId): JsonResponse
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1']);
        try {
            $item = $this->orderService->updateItemQuantity($order, $itemId, $data['quantity']);
            return response()->json(['item' => $item, 'order' => $order->fresh()->load('persons.items')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function removeItem(Order $order, int $itemId): JsonResponse
    {
        try {
            $this->orderService->removeItem($order, $itemId);
            return response()->json(['order' => $order->fresh()->load('persons.items')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function offerItem(Request $request, Order $order, int $itemId): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        try {
            $item = $this->orderService->offerItem($order, $itemId, $data['reason']);
            return response()->json(['item' => $item, 'order' => $order->fresh()->load('persons.items')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function applyDiscount(Request $request, Order $order, int $itemId): JsonResponse
    {
        $data = $request->validate(['discount_percent' => 'required|numeric|min:0|max:100']);
        try {
            $item = $this->orderService->applyDiscount($order, $itemId, $data['discount_percent']);
            return response()->json(['item' => $item, 'order' => $order->fresh()->load('persons.items')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addItemNote(Request $request, Order $order, int $itemId): JsonResponse
    {
        $data = $request->validate(['note' => 'required|string|max:255']);
        try {
            $item = $this->orderService->addItemNote($order, $itemId, $data['note']);
            return response()->json(['item' => $item]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function returnItem(Request $request, Order $order, int $itemId): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        try {
            $item = $this->orderService->returnItem($order, $itemId, $data['reason']);
            return response()->json(['item' => $item, 'order' => $order->fresh()->load('persons.items')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function validateOrder(Order $order): JsonResponse
    {
        try {
            return response()->json($this->orderService->validateOrder($order));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function markReady(Order $order): JsonResponse
    {
        try {
            return response()->json($this->orderService->markReady($order));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addNote(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['note' => 'required|string|max:1000']);
        return response()->json($this->orderService->addNote($order, $data['note']));
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            return response()->json(
                $this->orderService->cancel($order, $request->user()->name)
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}