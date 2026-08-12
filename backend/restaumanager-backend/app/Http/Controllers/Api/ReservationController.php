<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationExtra;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    // ────────────────────────────────────────────────────────
    // LISTE
    // ────────────────────────────────────────────────────────

    /**
     * GET /api/reservations?status=confirmed&date=2024-01-15&room_id=3
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['room', 'createdBy:id,name,username'])
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->room_id,  fn($q) => $q->where('room_id', $request->room_id))
            ->when($request->date,     fn($q) => $q->whereDate('check_in_date', $request->date))
            ->when($request->guest,    fn($q) => $q->where('guest_name', 'like', '%'.$request->guest.'%'))
            ->when($request->today_arrivals, fn($q) =>
                $q->whereDate('check_in_date', today())->whereIn('status', ['confirmed','pending']))
            ->when($request->today_departures, fn($q) =>
                $q->whereDate('check_out_date', today())->where('status', 'checked_in'));

        // Serveur voit seulement ses propres réservations
        if ($request->user()->isServeur()) {
            $query->where('created_by', $request->user()->id);
        }

        $reservations = $query->orderByDesc('check_in_date')->paginate(20);

        return response()->json($reservations);
    }

    /**
     * GET /api/reservations/rooms/availability?check_in=2024-01-15&check_out=2024-01-18&type=standard
     * Chercher les chambres disponibles pour une période
     */
    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'type'      => 'nullable|in:standard,superieure,suite,familiale',
            'capacity'  => 'nullable|integer|min:1',
        ]);

        $rooms = Room::availableFor($request->check_in, $request->check_out)
            ->when($request->type,     fn($q) => $q->where('type', $request->type))
            ->when($request->capacity, fn($q) => $q->where('capacity', '>=', $request->capacity))
            ->get();

        $nights = now()->parse($request->check_in)->diffInDays($request->check_out);

        return response()->json([
            'data'        => $rooms,
            'nights'      => $nights,
            'check_in'    => $request->check_in,
            'check_out'   => $request->check_out,
            'total_rooms' => $rooms->count(),
        ]);
    }

    // ────────────────────────────────────────────────────────
    // CRÉER UNE RÉSERVATION
    // ────────────────────────────────────────────────────────

    /**
     * POST /api/reservations
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id'          => 'required|exists:rooms,id',
            'guest_name'       => 'required|string|max:150',
            'guest_phone'      => 'nullable|string|max:20',
            'guest_email'      => 'nullable|email|max:150',
            'guest_id_number'  => 'nullable|string|max:50',
            'check_in_date'    => 'required|date|after_or_equal:today',
            'check_out_date'   => 'required|date|after:check_in_date',
            'adults'           => 'required|integer|min:1|max:10',
            'children'         => 'integer|min:0|max:10',
            'discount_amount'  => 'numeric|min:0',
            'paid_amount'      => 'numeric|min:0',
            'payment_method'   => 'nullable|in:cash,card,transfer,other',
            'special_requests' => 'nullable|string|max:500',
            'internal_notes'   => 'nullable|string|max:500',
            'status'           => 'in:pending,confirmed',
        ]);

        $room = Room::findOrFail($data['room_id']);

        // Vérifier la disponibilité
        if (!$room->isAvailableFor($data['check_in_date'], $data['check_out_date'])) {
            return response()->json([
                'error' => 'Cette chambre n\'est pas disponible pour les dates sélectionnées.',
                'code'  => 'ROOM_UNAVAILABLE',
            ], 422);
        }

        // Calculs financiers
        $nights     = now()->parse($data['check_in_date'])->diffInDays($data['check_out_date']);
        $totalPrice = $nights * $room->price_per_night;
        $discount   = $data['discount_amount'] ?? 0;
        $finalPrice = $totalPrice - $discount;
        $paid       = $data['paid_amount'] ?? 0;

        DB::beginTransaction();
        try {
            $reservation = Reservation::create([
                ...$data,
                'created_by'       => $request->user()->id,
                'price_per_night'  => $room->price_per_night,
                'nights'           => $nights,
                'total_price'      => $totalPrice,
                'discount_amount'  => $discount,
                'final_price'      => $finalPrice,
                'paid_amount'      => $paid,
                'remaining_amount' => max(0, $finalPrice - $paid),
                'extras_amount'    => 0,
                'status'           => $data['status'] ?? 'confirmed',
                'payment_status'   => $paid >= $finalPrice ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'reservation_number' => Reservation::generateNumber(),
            ]);

            // Mettre à jour le statut de la chambre si check-in aujourd'hui
            if ($reservation->check_in_date->isToday() && $reservation->status === 'confirmed') {
                $room->update(['status' => 'reserved']);
            }

            DB::commit();

            return response()->json([
                'data'    => $reservation->load(['room', 'createdBy:id,name']),
                'message' => 'Réservation créée avec succès.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ────────────────────────────────────────────────────────
    // DÉTAIL
    // ────────────────────────────────────────────────────────

    /**
     * GET /api/reservations/{reservation}
     */
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        // Un serveur ne voit que ses propres réservations
        if ($request->user()->isServeur() && $reservation->created_by !== $request->user()->id) {
            return response()->json(['error' => 'Accès non autorisé.'], 403);
        }

        return response()->json([
            'data' => $reservation->load(['room', 'createdBy:id,name,username', 'checkedInBy:id,name', 'checkedOutBy:id,name', 'extras']),
        ]);
    }

    // ────────────────────────────────────────────────────────
    // MODIFIER
    // ────────────────────────────────────────────────────────

    /**
     * PUT /api/reservations/{reservation}  [caissier + admin]
     */
    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        if (in_array($reservation->status, ['checked_out','cancelled','no_show'])) {
            return response()->json(['error' => 'Impossible de modifier une réservation clôturée.'], 422);
        }

        $data = $request->validate([
            'guest_name'       => 'sometimes|string|max:150',
            'guest_phone'      => 'nullable|string|max:20',
            'guest_email'      => 'nullable|email',
            'guest_id_number'  => 'nullable|string|max:50',
            'check_in_date'    => 'sometimes|date',
            'check_out_date'   => 'sometimes|date|after:check_in_date',
            'adults'           => 'sometimes|integer|min:1',
            'children'         => 'integer|min:0',
            'discount_amount'  => 'numeric|min:0',
            'special_requests' => 'nullable|string',
            'internal_notes'   => 'nullable|string',
        ]);

        // Vérifier la dispo si les dates changent
        if (isset($data['check_in_date']) || isset($data['check_out_date'])) {
            $checkIn  = $data['check_in_date']  ?? $reservation->check_in_date->format('Y-m-d');
            $checkOut = $data['check_out_date'] ?? $reservation->check_out_date->format('Y-m-d');

            if (!$reservation->room->isAvailableFor($checkIn, $checkOut, $reservation->id)) {
                return response()->json([
                    'error' => 'La chambre n\'est pas disponible pour ces nouvelles dates.',
                    'code'  => 'ROOM_UNAVAILABLE',
                ], 422);
            }
        }

        $reservation->update($data);
        $reservation->recalculate();

        return response()->json(['data' => $reservation->fresh(['room','extras'])]);
    }

    // ────────────────────────────────────────────────────────
    // CHECK-IN
    // ────────────────────────────────────────────────────────

    /**
     * PATCH /api/reservations/{reservation}/checkin
     */
    public function checkin(Request $request, Reservation $reservation): JsonResponse
    {
        if (!in_array($reservation->status, ['pending','confirmed'])) {
            return response()->json(['error' => 'Impossible de faire un check-in sur cette réservation.'], 422);
        }

        DB::beginTransaction();
        try {
            $reservation->update([
                'status'          => 'checked_in',
                'checked_in_by'   => $request->user()->id,
                'actual_check_in' => now(),
            ]);

            // Occuper la chambre
            $reservation->room->update(['status' => 'occupied']);

            DB::commit();

            return response()->json([
                'data'    => $reservation->load(['room','checkedInBy:id,name']),
                'message' => "✓ Check-in effectué — Chambre {$reservation->room->number}",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ────────────────────────────────────────────────────────
    // CHECK-OUT
    // ────────────────────────────────────────────────────────

    /**
     * PATCH /api/reservations/{reservation}/checkout
     */
    public function checkout(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'checked_in') {
            return response()->json(['error' => 'Impossible de faire un check-out : le client n\'est pas enregistré.'], 422);
        }

        $data = $request->validate([
            'paid_amount'    => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,transfer,other',
        ]);

        DB::beginTransaction();
        try {
            $reservation->update([
                'status'           => 'checked_out',
                'checked_out_by'   => $request->user()->id,
                'actual_check_out' => now(),
                'paid_amount'      => $data['paid_amount'],
                'payment_method'   => $data['payment_method'],
            ]);
            $reservation->recalculate();
            $reservation->updatePaymentStatus();

            // Libérer la chambre
            $reservation->room->update(['status' => 'available']);

            DB::commit();

            return response()->json([
                'data'    => $reservation->fresh(['room','checkedOutBy:id,name']),
                'message' => "✓ Check-out effectué — Chambre {$reservation->room->number} libérée.",
                'balance' => [
                    'final_price'      => $reservation->final_price,
                    'paid_amount'      => $data['paid_amount'],
                    'remaining'        => max(0, $reservation->final_price - $data['paid_amount']),
                    'change'           => max(0, $data['paid_amount'] - $reservation->final_price),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ────────────────────────────────────────────────────────
    // ANNULER  [caissier + admin]
    // ────────────────────────────────────────────────────────

    /**
     * PATCH /api/reservations/{reservation}/cancel
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        if (in_array($reservation->status, ['checked_out','cancelled'])) {
            return response()->json(['error' => 'Cette réservation est déjà clôturée.'], 422);
        }

        $reservation->update([
            'status'         => 'cancelled',
            'internal_notes' => ($reservation->internal_notes ? $reservation->internal_notes."\n" : '')
                                 . 'Annulé le '.now()->format('d/m/Y H:i').' par '.$request->user()->name,
        ]);

        // Libérer la chambre si elle était réservée pour cette resa
        if (in_array($reservation->room->status, ['reserved','occupied'])) {
            $reservation->room->update(['status' => 'available']);
        }

        return response()->json(['data' => $reservation->fresh(), 'message' => 'Réservation annulée.']);
    }

    /**
     * DELETE /api/reservations/{reservation}  [admin uniquement]
     */
    public function destroy(Reservation $reservation): JsonResponse
    {
        if ($reservation->status === 'checked_in') {
            return response()->json(['error' => 'Impossible de supprimer une réservation active.'], 422);
        }
        $reservation->extras()->delete();
        $reservation->delete();
        return response()->json(['success' => true]);
    }

    // ────────────────────────────────────────────────────────
    // EXTRAS (room service, minibar, etc.)
    // ────────────────────────────────────────────────────────

    /**
     * POST /api/reservations/{reservation}/extras
     */
    public function addExtra(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'checked_in') {
            return response()->json(['error' => 'Les extras ne peuvent être ajoutés qu\'à un séjour en cours.'], 422);
        }

        $data = $request->validate([
            'description'  => 'required|string|max:200',
            'amount'       => 'required|numeric|min:0',
            'quantity'     => 'integer|min:1',
            'type'         => 'required|in:room_service,minibar,laundry,parking,other',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'is_free'      => 'boolean',
        ]);

        $extra = ReservationExtra::create([
            ...$data,
            'reservation_id' => $reservation->id,
            'quantity'       => $data['quantity'] ?? 1,
        ]);

        $reservation->recalculate();

        return response()->json(['data' => $extra, 'reservation' => $reservation->fresh()]);
    }

    /**
     * DELETE /api/reservations/{reservation}/extras/{extra}
     */
    public function removeExtra(Reservation $reservation, ReservationExtra $extra): JsonResponse
    {
        $extra->delete();
        $reservation->recalculate();
        return response()->json(['reservation' => $reservation->fresh()]);
    }
}
