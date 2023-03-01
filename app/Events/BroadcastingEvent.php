<?php

namespace App\Events;

use App\Models\DatabaseNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class BroadcastingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $databaseNotification;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(DatabaseNotification $databaseNotification)
    {
        $this->databaseNotification = $databaseNotification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // return new Channel('prokpi');
        return new Channel('channel-name');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return "notification-" . $this->databaseNotification->notifiable_id;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return $this->databaseNotification->toArray();
    }
}
