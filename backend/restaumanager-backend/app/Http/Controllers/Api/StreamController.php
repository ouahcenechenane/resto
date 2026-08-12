<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SseEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * StreamController — Endpoint SSE (Server-Sent Events)
 *
 * GET /api/stream?token=SANCTUM_TOKEN
 *
 * Maintient une connexion HTTP ouverte et pousse les nouveaux événements
 * au fur et à mesure qu'ils apparaissent dans la table `sse_events`.
 * Fonctionne sans Redis, sans Node.js, sans Pusher. SQLite uniquement.
 */
class StreamController extends Controller
{
    /**
     * Durée max d'une connexion SSE (secondes).
     * Le client se reconnecte automatiquement après ce délai.
     */
    private const MAX_RUNTIME = 55;

    /**
     * Intervalle de polling interne sur la table sse_events (secondes).
     */
    private const POLL_INTERVAL = 2;

    /**
     * GET /api/stream?token=XXX[&last_id=N]
     *
     * @param  Request  $request
     */
    public function stream(Request $request)
    {
        // ── 1. Authentification par token Bearer passé en query string ──────
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['error' => 'Token manquant.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || !$accessToken->tokenable) {
            return response()->json(['error' => 'Token invalide.'], 401);
        }

        // ── 2. Headers SSE ────────────────────────────────────────────────
        $lastId = (int) $request->query('last_id', 0);

        // On purge opportunément (pas à chaque connexion, 1 chance sur 20)
        if (rand(1, 20) === 1) {
            SseEventService::purgeOld();
        }

        return response()->stream(function () use ($lastId) {

            // Désactiver le buffering de sortie
            if (ob_get_level()) {
                ob_end_clean();
            }

            set_time_limit(self::MAX_RUNTIME + 5);
            $startTime = time();

            // Heartbeat initial pour confirmer la connexion
            $this->sendComment('connected');

            $currentLastId = $lastId;

            while (true) {
                // ── Timeout protection ────────────────────────────────────
                if ((time() - $startTime) >= self::MAX_RUNTIME) {
                    $this->sendComment('reconnect');
                    break;
                }

                // ── Vérifier si la connexion est toujours active ──────────
                if (connection_aborted()) {
                    break;
                }

                // ── Récupérer les nouveaux événements ─────────────────────
                $events = DB::table('sse_events')
                    ->where('id', '>', $currentLastId)
                    ->orderBy('id')
                    ->limit(50)
                    ->get(['id', 'event_type', 'payload', 'related_id', 'created_at']);

                foreach ($events as $event) {
                    $this->sendEvent(
                        (string) $event->event_type,
                        json_decode($event->payload, true) ?? [],
                        (int) $event->id
                    );
                    $currentLastId = $event->id;
                }

                // ── Heartbeat toutes les 15 secondes (évite les timeouts proxy) ──
                static $lastHeartbeat = null;
                if ($lastHeartbeat === null) {
                    $lastHeartbeat = time();
                }
                if ((time() - $lastHeartbeat) >= 15) {
                    $this->sendComment('heartbeat ' . date('H:i:s'));
                    $lastHeartbeat = time();
                }

                sleep(self::POLL_INTERVAL);
            }

        }, 200, [
            'Content-Type'                => 'text/event-stream',
            'Cache-Control'               => 'no-cache, no-store',
            'X-Accel-Buffering'           => 'no',      // Désactive le buffer Nginx
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers'=> 'Authorization, Content-Type',
            'Connection'                  => 'keep-alive',
        ]);
    }

    /**
     * GET /api/events/latest?since=TIMESTAMP[&limit=50]
     *
     * Endpoint de polling (fallback si SSE bloqué par un proxy).
     * Retourne les événements depuis un timestamp ISO8601 ou unix.
     */
    public function latest(Request $request)
    {
        $since = $request->query('since');
        $lastId = $request->query('last_id');

        if ($lastId !== null) {
            // Mode ID (plus fiable)
            $events = DB::table('sse_events')
                ->where('id', '>', (int) $lastId)
                ->orderBy('id')
                ->limit(100)
                ->get(['id', 'event_type', 'payload', 'related_id', 'created_at'])
                ->map(fn($e) => [
                    'id'         => $e->id,
                    'event_type' => $e->event_type,
                    'payload'    => json_decode($e->payload, true),
                    'related_id' => $e->related_id,
                    'created_at' => $e->created_at,
                ]);
        } elseif ($since) {
            $events = SseEventService::since($since);
        } else {
            // Retourner le dernier ID connu sans événements
            $lastEvent = DB::table('sse_events')->latest('id')->first(['id']);
            return response()->json([
                'events'  => [],
                'last_id' => $lastEvent ? $lastEvent->id : 0,
            ]);
        }

        return response()->json([
            'events'  => $events,
            'last_id' => collect($events)->max('id') ?? $lastId ?? 0,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS SSE
    // ──────────────────────────────────────────────────────────────────────

    private function sendEvent(string $type, array $data, int $id): void
    {
        echo "id: {$id}\n";
        echo "event: {$type}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        $this->flush();
    }

    private function sendComment(string $comment): void
    {
        echo ": {$comment}\n\n";
        $this->flush();
    }

    private function flush(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
