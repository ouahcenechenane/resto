<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['order.table.section', 'printedBy:id,name'])
            ->orderByDesc('printed_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date')) {
            $query->whereDate('printed_at', $request->date);
        }

        $perPage = min((int) ($request->per_page ?? 50), 200);
        return response()->json($query->paginate($perPage));
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json(
            $ticket->load(['order.table.section', 'order.persons.items', 'printedBy:id,name'])
        );
    }

    public function generate(Request $request): JsonResponse
    {
        $data  = $request->validate(['order_id' => 'required|integer|exists:orders,id']);
        $order = Order::findOrFail($data['order_id']);

        try {
            $ticket = $this->ticketService->generate($order);
            return response()->json($ticket->load(['order.table.section']), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function pay(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'paid_amount'    => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,transfer,other',
        ]);

        try {
            $result = $this->ticketService->pay(
                $ticket, (float) $data['paid_amount'], $data['payment_method']
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Ticket $ticket): JsonResponse
    {
        try {
            return response()->json($this->ticketService->cancel($ticket));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);
        return response()->json(
            $this->ticketService->getDashboardStats($data['from'] ?? null, $data['to'] ?? null)
        );
    }
    // ── Compatibilité v2 : POST /orders/{id}/ticket ───────────────────
    public function generateFromOrder(Request $request, \App\Models\Order $order): JsonResponse
    {
        try {
            $ticket = $this->ticketService->generate($order);
            return response()->json($ticket->load(['order.table.section']), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Stubs compatibilité v2 (endpoints qui existaient) ────────────
    public function exportCsv(Request $request): JsonResponse
    {
        // TODO: implémenter l'export CSV si nécessaire
        return response()->json(['error' => 'Export CSV non implémenté dans la v3.'], 501);
    }

    public function markPrinted(Ticket $ticket): JsonResponse
    {
        // Dans la v3 le statut 'printed' est défini à la génération
        return response()->json($ticket);
    }

    public function generateForPerson(Request $request, \App\Models\Order $order, int $personIndex): JsonResponse
    {
        // TODO: implémenter la facturation par personne si nécessaire
        return response()->json(['error' => 'Facturation par personne non implémentée dans la v3.'], 501);
    }

    public function printForPerson(Ticket $ticket, int $index): JsonResponse
    {
        return response()->json($ticket);
    }
}
