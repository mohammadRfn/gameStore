<?php

declare(strict_types=1);

namespace Modules\Licensing\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Licensing\Casts\UtcDateTimeCast;

/**
 * یک پچ پیشنهادشده/نصب‌شده روی این دستگاه.
 *
 * status: available | queued | running | applied | failed | rolled_back
 * stage : preflight|downloading|verifying|staging|applying|files|lint|sql|health|done
 *
 * @property int         $id
 * @property string      $patch_code
 * @property string      $status
 * @property int         $progress
 * @property array|null  $depends_on
 * @property array|null  $applied_files
 */
class LicensePatch extends Model
{
    public const AVAILABLE   = 'available';
    public const QUEUED      = 'queued';
    public const RUNNING     = 'running';
    public const APPLIED     = 'applied';
    public const FAILED      = 'failed';
    public const ROLLED_BACK = 'rolled_back';

    public const BUSY = [self::QUEUED, self::RUNNING];

    protected $table = 'license_patches';

    protected $guarded = [];

    protected $casts = [
        'requires_restart' => 'boolean',
        'mandatory'        => 'boolean',
        'restart_pending'  => 'boolean',
        'size'             => 'integer',
        'progress'         => 'integer',
        'attempts'         => 'integer',
        'depends_on'       => 'array',
        'applied_files'    => 'array',
        'pending_report'   => 'array',
        'last_seen_at'     => UtcDateTimeCast::class,
        'started_at'       => UtcDateTimeCast::class,
        'finished_at'      => UtcDateTimeCast::class,
    ];

    public function isBusy(): bool
    {
        return in_array($this->status, self::BUSY, true);
    }

    /** @return array<string,mixed> */
    public function toUi(): array
    {
        return [
            'code'             => $this->patch_code,
            'title'            => $this->title,
            'description'      => $this->description,
            'type'             => $this->type,
            'from_min'         => $this->from_min,
            'from_max'         => $this->from_max,
            'to_version'       => $this->to_version,
            'requires_restart' => $this->requires_restart,
            'mandatory'        => $this->mandatory,
            'size'             => $this->size,
            'status'           => $this->status,
            'stage'            => $this->stage,
            'progress'         => $this->progress,
            'message'          => $this->message,
            'error_code'       => $this->error_code,
            'version_before'   => $this->version_before,
            'version_after'    => $this->version_after,
            'restart_pending'  => $this->restart_pending,
            'files_count'      => is_array($this->applied_files) ? count($this->applied_files) : null,
            'started_at'       => $this->started_at?->toIso8601String(),
            'finished_at'      => $this->finished_at?->toIso8601String(),
        ];
    }
}
