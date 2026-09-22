<?php

declare(strict_types=1);

namespace Modules\AuditLog\Listeners;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Services\AuditLogger;
use Modules\AuditLog\Support\AuditContext;

/**
 * رصد کامل چرخه‌ی احراز هویت (ماژول Authentication گیم‌استور).
 */
class AuthEventSubscriber
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuditContext $context,
    ) {
    }

    public function handleLogin(Login $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->context->resolveActor($event->user);

        $this->audit->auth(AuditAction::Login, 'ورود موفق به سیستم', [
            'guard'    => $event->guard,
            'remember' => $event->remember,
            'user_id'  => $event->user->getAuthIdentifier(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $this->enabled() || ! (bool) config('auditlog.capture.auth.log_logout', true)) {
            return;
        }

        $this->audit->auth(AuditAction::Logout, 'خروج از سیستم', [
            'guard'   => $event->guard,
            'user_id' => $event->user?->getAuthIdentifier(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        if (! $this->enabled() || ! (bool) config('auditlog.capture.auth.log_failed', true)) {
            return;
        }

        $credentials = array_diff_key($event->credentials, array_flip(['password', 'password_confirmation']));

        $description = 'تلاش ناموفق برای ورود';

        if ((bool) config('auditlog.capture.auth.failed_is_security', true)) {
            $this->audit->security('login_failed', $description, [
                'guard'       => $event->guard,
                'credentials' => $credentials,
            ]);

            return;
        }

        $this->audit->auth(AuditAction::LoginFailed, $description, ['credentials' => $credentials], LogLevel::Warning);
    }

    public function handleLockout(Lockout $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->audit->security('lockout', 'حساب به دلیل تلاش‌های مکرر قفل شد', [
            'input' => $event->request->except(['password']),
        ], LogLevel::Critical);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->audit->auth(AuditAction::PasswordReset, 'رمز عبور بازنشانی شد', [
            'user_id' => $event->user->getAuthIdentifier(),
        ], LogLevel::Notice);
    }

    public function handleRegistered(Registered $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->audit->auth(AuditAction::Registered, 'کاربر جدید ثبت شد', [
            'user_id' => $event->user->getAuthIdentifier(),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class         => 'handleLogin',
            Logout::class        => 'handleLogout',
            Failed::class        => 'handleFailed',
            Lockout::class       => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
            Registered::class    => 'handleRegistered',
            Attempting::class    => 'handleAttempting',
        ];
    }

    public function handleAttempting(Attempting $event): void
    {
        // فقط برای همبستگی زمانی؛ در سطح debug ثبت می‌شود
        if (! $this->enabled()) {
            return;
        }

        $this->audit->auth('auth.attempting', 'تلاش برای ورود', [
            'guard' => $event->guard,
        ], LogLevel::Debug);
    }

    private function enabled(): bool
    {
        return (bool) config('auditlog.capture.auth.enabled', true);
    }
}
