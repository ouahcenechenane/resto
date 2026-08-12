<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service central pour la gestion des événements SSE.
 * Persiste les événements en base SQLite et les diffuse via le StreamController.
 */
class SseEventService
{
    /**
     * Enregistre un nouvel événement dans la table sse_events.
     * Appelé par les Controllers après chaque action métier.
     *
     * @param  string  $type       ex: "order.created", "order.ready"
     * @param  array   $payload    données à envoyer au frontend
     * @param  int|null $relatedId  ID de la ressource principale (order_id, table_id…)
     */
    public static function dispatch(string $type, array $payload, ?int $relatedId = null): void
    {
        try {
            DB::table('sse_events')->insert([
                'event_type' => $type,
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'related_id' => $relatedId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Ne jamais bloquer une action métier à cause de SSE
            Log::error('[SSE] Impossible de persister l\'événement: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les événements depuis un timestamp donné (pour polling).
     *
     * @param  string  $since  timestamp ISO8601 ou unix timestamp
     * @return array
     */
    public static function since(string $since): array
    {
        return DB::table('sse_events')
            ->where('created_at', '>', $since)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'event_type', 'payload', 'related_id', 'created_at'])
            ->map(function ($row) {
                return [
                    'id'         => $row->id,
                    'event_type' => $row->event_type,
                    'payload'    => json_decode($row->payload, true),
                    'related_id' => $row->related_id,
                    'created_at' => $row->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Purge les événements de plus de 24 heures (SQLite ne tourne pas en arrière plan,
     * on appelle cela opportunément depuis le StreamController).
     */
    public static function purgeOld(): void
    {
        DB::table('sse_events')
            ->where('created_at', '<', now()->subHours(24))
            ->delete();
    }
}
