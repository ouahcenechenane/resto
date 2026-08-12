<?php
namespace App\Services;

use App\Events\OrderBilled;
use App\Events\TableStatusChanged;
use App\Events\TicketPaid;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketService
{
    // ── Générer un ticket ──────────────────────────────────────────
    public function generate(Order $order): Ticket
    {
        // Idempotent : si un ticket non-annulé existe déjà, le retourner directement
        $existingTicket = $order->tickets()->where('status', '!=', 'cancelled')->first();
        if ($existingTicket) {
            return $existingTicket;
        }

        if (!in_array($order->status, ['validated', 'open', 'billed'])) {
            throw new \Exception('Seule une commande validée ou facturée peut être encaissée.');
        }

        DB::beginTransaction();
        try {
            $order->load('persons.items');

            // Snapshot JSON des lignes (nommé 'snapshot' dans ta table)
            $lines = [];
            foreach ($order->persons as $person) {
                foreach ($person->items as $item) {
                    if ($item->is_returned) continue;
                    $lines[] = [
                        'person_index' => $person->person_index,
                        'person_label' => $person->label,
                        'menu_item_id' => $item->menu_item_id,
                        'item_name'    => $item->item_name,
                        'quantity'     => $item->quantity,
                        'unit_price'   => $item->unit_price,
                        'discount_pct' => $item->discount_percent,
                        'is_free'      => $item->is_free,
                        'line_total'   => $item->line_total,
                    ];
                }
            }

            $ticket = Ticket::create([
                'order_id'      => $order->id,
                'printed_by'    => auth()->id(),          // colonne réelle
                'ticket_number' => Ticket::generateNumber(),
                'snapshot'      => json_encode($lines),   // colonne réelle
                'total_amount'  => $order->total_amount,
                'paid_amount'   => 0,
                'status'        => 'printed',             // enum réel
                'printed_at'    => now(),                 // colonne réelle
            ]);

            $order->update(['status' => 'billed']);
            DB::commit();

            // Cuisine retire la commande de son écran
            OrderBilled::dispatch($order->fresh(), $ticket);

            return $ticket;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Encaisser ──────────────────────────────────────────────────
    public function pay(Ticket $ticket, float $paidAmount, string $paymentMethod): array
    {
        if ($ticket->status !== 'printed') {     // enum réel
            throw new \Exception('Ce ticket est déjà ' . $ticket->status . '.');
        }

        if ($paidAmount < $ticket->total_amount) {
            throw new \Exception(
                'Montant insuffisant. Total : ' . $ticket->total_amount . ' — reçu : ' . $paidAmount
            );
        }

        DB::beginTransaction();
        try {
            $change = round($paidAmount - $ticket->total_amount, 2);

            $ticket->update([
                'paid_amount'    => $paidAmount,
                'payment_method' => $paymentMethod,
                'status'         => 'paid',
                'paid_at'        => now(),
            ]);

            $order = $ticket->order->load('table');
            $order->update(['status' => 'paid', 'closed_at' => now()]);

            $tableOldStatus = null;
            if ($order->table_id) {
                $tableOldStatus = $order->table->status;
                $order->table->update(['status' => 'available']);
            }

            DB::commit();

            TicketPaid::dispatch($ticket->fresh(), $order->fresh(), $change);

            if ($order->table_id && $tableOldStatus) {
                TableStatusChanged::dispatch(
                    $order->table->fresh(),
                    $tableOldStatus,
                    'available',
                    $order->id
                );
            }

            return ['ticket' => $ticket->fresh(), 'change' => $change];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Annuler un ticket ──────────────────────────────────────────
    public function cancel(Ticket $ticket): Ticket
    {
        if ($ticket->status === 'paid') {
            throw new \Exception('Impossible d\'annuler un ticket déjà payé.');
        }

        DB::beginTransaction();
        try {
            $ticket->update(['status' => 'cancelled']);
            $ticket->order->update(['status' => 'validated']);
            DB::commit();
            return $ticket->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Statistiques ───────────────────────────────────────────────
    public function getDashboardStats(?string $from = null, ?string $to = null): array
    {
        $query = Ticket::where('status', 'paid');
        if ($from) $query->whereDate('paid_at', '>=', $from);
        if ($to)   $query->whereDate('paid_at', '<=', $to);

        $tickets      = $query->with('order')->get();
        $totalRevenue = $tickets->sum('total_amount');
        $totalTickets = $tickets->count();
        $avgTicket    = $totalTickets > 0 ? $totalRevenue / $totalTickets : 0;

        $byMethod = $tickets->groupBy('payment_method')->map(fn($g) => [
            'count' => $g->count(), 'amount' => $g->sum('total_amount'),
        ]);
        $byDay = $tickets->groupBy(fn($t) => $t->paid_at?->toDateString())->map(fn($g) => [
            'count' => $g->count(), 'amount' => $g->sum('total_amount'),
        ]);

        return [
            'total_revenue'     => round($totalRevenue, 2),
            'total_tickets'     => $totalTickets,
            'average_ticket'    => round($avgTicket, 2),
            'by_payment_method' => $byMethod,
            'by_day'            => $byDay,
        ];
    }
}