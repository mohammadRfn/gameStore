<?php

namespace App\Listeners;

use App\Events\SettingUpdated;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ClearSettingsCache implements ShouldQueue
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    public function handle(SettingUpdated $event): void
    {
        $this->settings->flush();

        Log::channel('settings')->info('Setting updated', [
            'key'      => $event->settingKey,
            'old'      => $event->oldValue,
            'new'      => $event->newValue,
            'user'     => $event->updatedBy,
            'at'       => now()->toDateTimeString(),
        ]);
    }
}
