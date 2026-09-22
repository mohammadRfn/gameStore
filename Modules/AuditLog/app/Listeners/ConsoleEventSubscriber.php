<?php

declare(strict_types=1);

namespace Modules\AuditLog\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Services\AuditLogger;
use Modules\AuditLog\Support\AuditContext;

/**
 * رصد دستورات آرتیزان (بکاپ، آرشیو، پاک‌سازی کش، migrate و ...).
 */
class ConsoleEventSubscriber
{
    private float $startedAt = 0.0;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuditContext $context,
    ) {
    }

    public function handleStarting(CommandStarting $event): void
    {
        $this->startedAt = microtime(true);

        if ($event->command !== null) {
            $this->context->setConsoleCommand($event->command);
        }
    }

    public function handleFinished(CommandFinished $event): void
    {
        $command = (string) ($event->command ?? 'unknown');

        if (! $this->shouldLog($command, $event->exitCode)) {
            return;
        }

        $durationMs = $this->startedAt > 0 ? (int) round((microtime(true) - $this->startedAt) * 1000) : null;

        $this->audit->console(
            command: $command,
            description: sprintf('اجرای دستور %s (کد خروج %d)', $command, $event->exitCode),
            context: [
                'exit_code'   => $event->exitCode,
                'arguments'   => $this->safeArguments($event),
                'duration_ms' => $durationMs,
            ],
            level: $event->exitCode === 0 ? LogLevel::Info : LogLevel::Error,
        );

        $this->audit->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function safeArguments(CommandFinished $event): array
    {
        try {
            $arguments = $event->input->__toString();
        } catch (\Throwable) {
            return [];
        }

        return ['input' => mb_substr($arguments, 0, 500)];
    }

    private function shouldLog(string $command, int $exitCode): bool
    {
        if (! (bool) config('auditlog.capture.console.enabled', true)) {
            return false;
        }

        foreach ((array) config('auditlog.capture.console.except', []) as $pattern) {
            if (Str::is((string) $pattern, $command)) {
                return false;
            }
        }

        if ($exitCode !== 0) {
            return true;
        }

        if (! (bool) config('auditlog.capture.console.only_failed_or_mutating', true)) {
            return true;
        }

        // دستورات تغییردهنده‌ی وضعیت سیستم
        return Str::is(
            ['migrate*', 'db:*', 'backup*', 'archive*', 'cache:*', 'config:*', 'optimize*', 'settings*', '*:run', '*:sync', '*:import', '*:export'],
            $command,
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            CommandStarting::class => 'handleStarting',
            CommandFinished::class => 'handleFinished',
        ];
    }
}
