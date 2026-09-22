<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Enums\SyncStatus;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // کنترل دسترسی از طریق middleware کانفیگ انجام می‌شود
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q'            => ['nullable', 'string', 'max:191'],
            'channel'      => ['nullable', 'array'],
            'channel.*'    => [Rule::in(LogChannel::values())],
            'level'        => ['nullable', 'array'],
            'level.*'      => [Rule::in(LogLevel::values())],
            'min_level'    => ['nullable', Rule::in(LogLevel::values())],
            'sync_status'  => ['nullable', 'array'],
            'sync_status.*' => [Rule::in(SyncStatus::values())],
            'action'       => ['nullable', 'string', 'max:128'],
            'actor_id'     => ['nullable', 'integer'],
            'entity_type'  => ['nullable', 'string', 'max:191'],
            'entity_id'    => ['nullable', 'integer'],
            'request_id'   => ['nullable', 'string', 'max:64'],
            'ip'           => ['nullable', 'string', 'max:45'],
            'from'         => ['nullable', 'date'],
            'to'           => ['nullable', 'date', 'after_or_equal:from'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:' . (int) config('auditlog.api.max_per_page', 200)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter($this->validated(), static fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? config('auditlog.api.per_page', 30));
    }
}
