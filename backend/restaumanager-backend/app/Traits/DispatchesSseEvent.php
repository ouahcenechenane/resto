<?php

namespace App\Traits;

use App\Models\SseEvent;

/**
 * Trait DispatchesSseEvent
 *
 * Utilisé dans les Controllers pour écrire un événement SSE
 * en base de données après chaque action métier importante.
 *
 * Usage :
 *   $this->sseEvent('order.created', ['id' => $order->id, ...]);
 */
trait DispatchesSseEvent
{
    protected function sseEvent(string $type, array $payload): void
    {
        SseEvent::dispatch($type, $payload);
    }
}
