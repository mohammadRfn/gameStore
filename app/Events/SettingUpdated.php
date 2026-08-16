<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $settingKey,
        public mixed $oldValue,
        public mixed $newValue,
        public ?int $updatedBy = null,
    ) {}
}
