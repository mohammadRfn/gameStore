<?php

declare(strict_types=1);

namespace Modules\AuditLog\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * زمینه‌ی جاری اجرا (singleton). یک request_id واحد به تمام لاگ‌های
 * یک درخواست/دستور می‌چسبد تا ردیابی end-to-end ممکن شود.
 */
class AuditContext
{
    private string $requestId;
    private ?string $correlationId = null;
    private ?string $sessionId = null;
    private ?string $method = null;
    private ?string $route = null;
    private ?string $url = null;
    private ?string $ip = null;
    private ?string $userAgent = null;

    private ?int $actorId = null;
    private string $actorType = 'system';
    private ?string $actorName = null;
    private ?string $actorEmail = null;

    /** @var array<string, mixed> */
    private array $extra = [];

    public function __construct(private readonly ClientIdentity $identity)
    {
        $this->requestId = (string) Str::uuid();
        $this->actorType = app()->runningInConsole() ? 'console' : 'system';
    }

    public function hydrateFromRequest(Request $request): void
    {
        $this->requestId = (string) ($request->headers->get('X-Request-Id') ?: $this->requestId);
        $this->correlationId = $request->headers->get('X-Correlation-Id') ?: $this->correlationId;
        $this->method = $request->getMethod();
        $this->url = mb_substr($request->fullUrl(), 0, 1024);
        $this->route = $request->route() !== null ? (string) ($request->route()->getName() ?: $request->route()->uri()) : $request->path();
        $this->ip = $request->ip();
        $this->userAgent = mb_substr((string) $request->userAgent(), 0, 512);

        if ($request->hasSession()) {
            $this->sessionId = mb_substr((string) $request->session()->getId(), 0, 64);
        }

        $this->resolveActor($request->user());
    }

    public function resolveActor(?Authenticatable $user = null): void
    {
        $user ??= Auth::user();

        if ($user === null) {
            if ($this->actorType === 'system' && ! app()->runningInConsole()) {
                $this->actorType = 'guest';
            }

            return;
        }

        $this->actorId = is_numeric($user->getAuthIdentifier()) ? (int) $user->getAuthIdentifier() : null;
        $this->actorType = 'user';
        $this->actorName = (string) ($user->name ?? $user->getAuthIdentifier());
        $this->actorEmail = isset($user->email) ? (string) $user->email : null;
    }

    public function forActor(?int $id, ?string $name, string $type = 'user', ?string $email = null): void
    {
        $this->actorId = $id;
        $this->actorName = $name;
        $this->actorType = $type;
        $this->actorEmail = $email;
    }

    public function setConsoleCommand(string $command): void
    {
        $this->actorType = 'console';
        $this->actorName = $this->actorName ?? 'artisan';
        $this->route = mb_substr($command, 0, 191);
        $this->method = 'CLI';
    }

    public function correlate(string $correlationId): void
    {
        $this->correlationId = mb_substr($correlationId, 0, 64);
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /** @param array<string, mixed> $values */
    public function merge(array $values): void
    {
        $this->extra = array_merge($this->extra, $values);
    }

    /** @return array<string, mixed> */
    public function extra(): array
    {
        return $this->extra;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'request_id'     => $this->requestId,
            'correlation_id' => $this->correlationId,
            'session_id'     => $this->sessionId,
            'method'         => $this->method,
            'route'          => $this->route,
            'url'            => $this->url,
            'ip'             => $this->ip,
            'user_agent'     => $this->userAgent,
            'actor_id'       => $this->actorId,
            'actor_type'     => $this->actorType,
            'actor_name'     => $this->actorName,
            'actor_email'    => $this->actorEmail,
            'environment'    => $this->identity->environment(),
            'app_version'    => $this->identity->appVersion(),
            'hostname'       => $this->identity->hostname(),
        ];
    }
}
