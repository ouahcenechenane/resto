<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\Order;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(private readonly TableService $tableService) {}

    public function index(Request $request): JsonResponse
    {
        $query = RestaurantTable::with(['section:id,name,code'])
            ->orderBy('section_id')
            ->orderBy('number');

        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tables = $query->get()->map(function($table) {
            // Charger l'order active manuellement (évite les boucles infinies)
            $activeOrder = Order::where('table_id', $table->id)
                ->whereIn('status', ['open', 'validated', 'ready', 'billed'])
                ->latest()
                ->first(['id', 'table_id', 'status', 'persons_count', 'total_amount', 'created_at', 'notes']);

            return [
                'id' => $table->id,
                'number' => $table->number,
                'capacity' => $table->capacity,
                'persons_count' => $table->persons_count,
                'status' => $table->status,
                'section' => $table->section,
                'active_order' => $activeOrder ? [
                    'id' => $activeOrder->id,
                    'table_id' => $activeOrder->table_id,
                    'status' => $activeOrder->status,
                    'persons_count' => $activeOrder->persons_count,
                    'total_amount' => $activeOrder->total_amount,
                    'created_at' => $activeOrder->created_at,
                    'notes' => $activeOrder->notes,
                ] : null,
            ];
        });

        return response()->json($tables);
    }

    public function show(RestaurantTable $table): JsonResponse
    {
        $activeOrder = Order::where('table_id', $table->id)
            ->whereIn('status', ['open', 'validated', 'ready', 'billed'])
            ->latest()
            ->first(['id', 'table_id', 'status', 'persons_count', 'total_amount', 'created_at', 'notes']);

        return response()->json([
            'id' => $table->id,
            'number' => $table->number,
            'capacity' => $table->capacity,
            'persons_count' => $table->persons_count,
            'status' => $table->status,
            'section' => $table->section,
            'active_order' => $activeOrder ? [
                'id' => $activeOrder->id,
                'table_id' => $activeOrder->table_id,
                'status' => $activeOrder->status,
                'persons_count' => $activeOrder->persons_count,
                'total_amount' => $activeOrder->total_amount,
                'created_at' => $activeOrder->created_at,
                'notes' => $activeOrder->notes,
            ] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'number'     => 'required|string|max:10',
            'capacity'   => 'required|integer|min:1|max:50',
        ]);

        try {
            return response()->json($this->tableService->create($data), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, RestaurantTable $table): JsonResponse
    {
        $data = $request->validate([
            'number'     => 'sometimes|string|max:10',
            'capacity'   => 'sometimes|integer|min:1|max:50',
            'section_id' => 'sometimes|integer|exists:sections,id',
        ]);

        try {
            return response()->json($this->tableService->update($table, $data));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateStatus(Request $request, RestaurantTable $table): JsonResponse
    {
        $data = $request->validate([
            'status'   => 'required|string|in:available,occupied,reserved,closed',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        try {
            return response()->json(
                $this->tableService->updateStatus($table, $data['status'], $data['order_id'] ?? null)
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    public function close(Request $request, RestaurantTable $table): JsonResponse
    {
        try {
            // 1. Chercher une commande active (tous statuts non-terminaux)
            $order = Order::where('table_id', $table->id)
                ->whereIn('status', ['open', 'validated', 'ready', 'billed'])
                ->latest()
                ->first();

            if ($order) {
                // 2. Valider si besoin
                if (in_array($order->status, ['open', 'ready'])) {
                    $order->update(['status' => 'validated']);
                    $order->refresh();
                }

                // 3. Récupérer ou créer le ticket (idempotent)
                $ticket = $order->tickets()->where('status', '!=', 'cancelled')->first();
                if (!$ticket) {
                    $ticketService = app(\App\Services\TicketService::class);
                    $ticket = $ticketService->generate($order);
                }

                // 4. Payer si pas encore payé
                if ($ticket->status === 'printed') {
                    $ticketService = app(\App\Services\TicketService::class);
                    $result = $ticketService->pay($ticket, (float) $ticket->total_amount, 'cash');
                    return response()->json(['success' => true, 'ticket' => $result['ticket'], 'change' => $result['change']]);
                }
            }

            // Pas de commande ou déjà payé → libérer directement
            $table->update(['status' => 'available']);
            \App\Events\TableStatusChanged::dispatch($table->fresh(), 'occupied', 'available', null);

            return response()->json(['success' => true, 'message' => 'Table libérée']);
        } catch (\Exception $e) {
            // Filet de sécurité : libérer la table quoi qu'il arrive
            try { $table->update(['status' => 'available']); } catch (\Exception $e2) {}
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(RestaurantTable $table): JsonResponse
    {
        try {
            $this->tableService->delete($table);
            return response()->json(['message' => 'Table supprimée.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}