<?php

// ══════════════════════════════════════════════════════════════
// app/Models/Room.php
// ══════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Room extends Model
{
    protected $fillable = [
        'number','name','type','capacity','price_per_night',
        'description','amenities','image','status','floor','is_active',
    ];

    protected $casts = [
        'price_per_night' => 'float',
        'amenities'       => 'array',
        'is_active'       => 'boolean',
    ];

    // ── Relations ───────────────────────────────────────────
    public function reservations() {
        return $this->hasMany(Reservation::class);
    }

    public function activeReservation() {
        return $this->hasOne(Reservation::class)
                    ->whereIn('status', ['confirmed','checked_in'])
                    ->orderByDesc('check_in_date');
    }

    // ── Scopes ──────────────────────────────────────────────

    /** Chambres disponibles pour une période donnée */
    public function scopeAvailableFor(Builder $q, string $checkIn, string $checkOut): Builder
    {
        return $q->where('status', '!=', 'maintenance')
                 ->where('is_active', true)
                 ->whereDoesntHave('reservations', function ($r) use ($checkIn, $checkOut) {
                     $r->whereNotIn('status', ['cancelled','no_show'])
                       ->where(function ($inner) use ($checkIn, $checkOut) {
                           // Chevauchement de dates
                           $inner->whereBetween('check_in_date',  [$checkIn, $checkOut])
                                 ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                                 ->orWhere(function ($wrap) use ($checkIn, $checkOut) {
                                     $wrap->where('check_in_date',  '<=', $checkIn)
                                          ->where('check_out_date', '>=', $checkOut);
                                 });
                       });
                 });
    }

    public function scopeOfType(Builder $q, string $type): Builder {
        return $q->where('type', $type);
    }

    // ── Helpers ─────────────────────────────────────────────
    public function isAvailableFor(string $checkIn, string $checkOut, ?int $excludeReservationId = null): bool
    {
        $query = $this->reservations()
                      ->whereNotIn('status', ['cancelled','no_show'])
                      ->where(function ($q) use ($checkIn, $checkOut) {
                          $q->whereBetween('check_in_date',  [$checkIn, $checkOut])
                            ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                            ->orWhere(function ($w) use ($checkIn, $checkOut) {
                                $w->where('check_in_date', '<=', $checkIn)
                                  ->where('check_out_date', '>=', $checkOut);
                            });
                      });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->count() === 0;
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'standard'   => 'Standard',
            'superieure' => 'Supérieure',
            'suite'      => 'Suite',
            'familiale'  => 'Familiale',
            default      => $this->type,
        };
    }
}


// ══════════════════════════════════════════════════════════════
// app/Models/Reservation.php
// ══════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'room_id','created_by','checked_in_by','checked_out_by',
        'guest_name','guest_phone','guest_email','guest_id_number',
        'check_in_date','check_out_date','nights',
        'actual_check_in','actual_check_out',
        'price_per_night','total_price','discount_amount','final_price',
        'paid_amount','remaining_amount','extras_amount',
        'status','payment_status','payment_method',
        'adults','children','special_requests','internal_notes','reservation_number',
    ];

    protected $casts = [
        'check_in_date'    => 'date',
        'check_out_date'   => 'date',
        'actual_check_in'  => 'datetime',
        'actual_check_out' => 'datetime',
        'price_per_night'  => 'float',
        'total_price'      => 'float',
        'discount_amount'  => 'float',
        'final_price'      => 'float',
        'paid_amount'      => 'float',
        'remaining_amount' => 'float',
        'extras_amount'    => 'float',
    ];

    // ── Relations ───────────────────────────────────────────
    public function room()          { return $this->belongsTo(Room::class); }
    public function createdBy()     { return $this->belongsTo(User::class, 'created_by'); }
    public function checkedInBy()   { return $this->belongsTo(User::class, 'checked_in_by'); }
    public function checkedOutBy()  { return $this->belongsTo(User::class, 'checked_out_by'); }
    public function extras()        { return $this->hasMany(ReservationExtra::class); }

    // ── Calculs ─────────────────────────────────────────────
    public function recalculate(): void
    {
        $nights      = $this->check_in_date->diffInDays($this->check_out_date);
        $total       = $nights * $this->price_per_night;
        $extrasTotal = $this->extras()->where('is_free', false)->sum('line_total');
        $final       = $total + $extrasTotal - $this->discount_amount;
        $remaining   = max(0, $final - $this->paid_amount);

        $this->update([
            'nights'           => $nights,
            'total_price'      => $total,
            'extras_amount'    => $extrasTotal,
            'final_price'      => $final,
            'remaining_amount' => $remaining,
        ]);
    }

    public function updatePaymentStatus(): void
    {
        $status = match(true) {
            $this->paid_amount <= 0              => 'unpaid',
            $this->paid_amount >= $this->final_price => 'paid',
            default                              => 'partial',
        };
        $this->update(['payment_status' => $status]);
    }

    /** Génère un numéro de réservation unique */
    public static function generateNumber(): string
    {
        $date  = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return sprintf('RES-%s-%04d', $date, $count);
    }
}


// ══════════════════════════════════════════════════════════════
// app/Models/ReservationExtra.php
// ══════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationExtra extends Model
{
    protected $table    = 'reservation_extras';
    protected $fillable = [
        'reservation_id','menu_item_id','description',
        'amount','quantity','line_total','type','is_free',
    ];
    protected $casts = [
        'amount'     => 'float',
        'line_total' => 'float',
        'is_free'    => 'boolean',
    ];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function menuItem()    { return $this->belongsTo(MenuItem::class); }

    protected static function booted(): void
    {
        static::saving(function (self $extra) {
            $extra->line_total = $extra->is_free ? 0 : $extra->amount * $extra->quantity;
        });
    }
}
