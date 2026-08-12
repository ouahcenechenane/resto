<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\OrderCreated;
use App\Events\OrderValidated;
use App\Events\OrderReady;
use App\Events\OrderBilled;
use App\Events\OrderCancelled;
use App\Events\OrderItemChanged;
use App\Events\EmporterCreated;
use App\Events\TicketPaid;
use App\Events\TableStatusChanged;
use App\Events\TableCreated;
use App\Events\MenuUpdated;
use App\Listeners\BroadcastOrderCreated;
use App\Listeners\BroadcastOrderValidated;
use App\Listeners\BroadcastOrderReady;
use App\Listeners\BroadcastOrderBilled;
use App\Listeners\BroadcastOrderCancelled;
use App\Listeners\BroadcastOrderItemChanged;
use App\Listeners\BroadcastEmporterCreated;
use App\Listeners\BroadcastTicketPaid;
use App\Listeners\BroadcastTableStatusChanged;
use App\Listeners\BroadcastTableCreated;
use App\Listeners\BroadcastMenuUpdated;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class     => [BroadcastOrderCreated::class],
        OrderValidated::class   => [BroadcastOrderValidated::class],
        OrderReady::class       => [BroadcastOrderReady::class],
        OrderBilled::class      => [BroadcastOrderBilled::class],
        OrderCancelled::class   => [BroadcastOrderCancelled::class],
        OrderItemChanged::class => [BroadcastOrderItemChanged::class],
        EmporterCreated::class  => [BroadcastEmporterCreated::class],
        TicketPaid::class       => [BroadcastTicketPaid::class],
        TableStatusChanged::class => [BroadcastTableStatusChanged::class],
        TableCreated::class     => [BroadcastTableCreated::class],
        MenuUpdated::class      => [BroadcastMenuUpdated::class],
    ];

    public function boot(): void {}
    public function shouldDiscoverEvents(): bool { return false; }
}
