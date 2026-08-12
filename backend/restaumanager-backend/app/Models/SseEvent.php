<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SseEvent extends Model
{
    public $timestamps = false;          // On gère created_at manuellement (useCurrent)
    protected $table   = 'sse_events';

    protected $fillable = [
        'event_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Écrire un événement dans la table SSE.
     * Appelé par chaque classe Event Laravel.
     */
    public static function dispatch(string $type, array $data): self
    {
        return self::create([
            'event_type' => $type,
            'payload'    => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Purger les vieux événements (> 1 heure) pour ne pas gonfler la DB.
     * À appeler dans un scheduler ou depuis StreamController.
     */
    public static function purgeOld(): int
    {
        return self::where('created_at', '<', now()->subHour())->delete();
    }
}
