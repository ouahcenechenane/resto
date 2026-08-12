<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EmporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmporterController extends Controller
{
    public function __construct(private readonly EmporterService $emporterService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user:id,name', 'persons.items'])
            ->where('type', 'emporter');

        if ($request->has('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function show(Order $order): JsonResponse
    {
        if ($order->type !== 'emporter') {
            return response()->json(['error' => 'Commande non trouvée.'], 404);
        }
        return response()->json($order->load(['user:id,name', 'persons.items', 'tickets']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_name'          => 'nullable|string|max:100',
            'notes'                => 'nullable|string|max:500',
            'items'                => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|integer|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.kitchen_note' => 'nullable|string|max:255',
        ]);

        try {
            $order = $this->emporterService->create(
                $data, $request->user()->id, $request->user()->name
            );
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function markReady(Order $order): JsonResponse
    {
        try {
            return response()->json($this->emporterService->markReady($order));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addItem(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'menu_item_id' => 'required|integer|exists:menu_items,id',
            'quantity'     => 'required|integer|min:1',
            'kitchen_note' => 'nullable|string|max:255',
        ]);

        try {
            $item = $this->emporterService->addItem(
                $order, $data['menu_item_id'], $data['quantity'], $data['kitchen_note'] ?? null
            );
            return response()->json(['item' => $item, 'order' => $order->fresh()->load('persons.items')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            return response()->json(
                $this->emporterService->cancel($order, $request->user()->name)
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
