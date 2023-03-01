<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogPreference
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->logName ?? 'default')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();

        // Chain fluent methods for configuration options
    }
}
