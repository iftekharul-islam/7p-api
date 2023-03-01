<?php

namespace App\Models;

use App\Events\BroadcastingEvent;
use Illuminate\Notifications\DatabaseNotification as Notification;

class DatabaseNotification extends Notification
{
    protected $dispatchesEvents = [
        'created' => BroadcastingEvent::class,
    ];
}
