<?php

namespace App\Traits;

use App\Traits\HasDatabaseNotifications;
use Illuminate\Notifications\RoutesNotifications;

trait Notifiable
{
    use HasDatabaseNotifications, RoutesNotifications;
}
