<?php

declare(strict_types=1);

namespace Modules\AuditLog\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\AuditLog\Listeners\ApplicationLogSubscriber;
use Modules\AuditLog\Listeners\AuthEventSubscriber;
use Modules\AuditLog\Listeners\ConsoleEventSubscriber;
use Modules\AuditLog\Listeners\QueueEventSubscriber;
use Modules\AuditLog\Listeners\SystemEventSubscriber;

class EventServiceProvider extends ServiceProvider
{
    /**
     * تمام رصدها از طریق subscriber انجام می‌شود تا این کلاس تمیز بماند.
     *
     * @var list<class-string>
     */
    protected $subscribe = [
        AuthEventSubscriber::class,
        QueueEventSubscriber::class,
        ConsoleEventSubscriber::class,
        ApplicationLogSubscriber::class,
        SystemEventSubscriber::class,
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
