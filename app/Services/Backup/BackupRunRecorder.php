<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use App\Models\BackupRunEntity;
use App\Models\BackupRunEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ثبت وضعیت، متریک‌ها و لاگ ساختاریافته‌ی هر اجرا.
 * (جداسازی این مسئولیت باعث می‌شود سرویس‌های اکسپورت/ایمپورت تمیز بمانند.)
 */
class BackupRunRecorder
{
    /** @var resource|null */
    private $logHandle = null;

    public function attachLogFile(?string $path): void
    {
        $this->closeLogFile();

        if ($path) {
            $handle = @fopen($path, 'ab');
            $this->logHandle = $handle === false ? null : $handle;
        }
    }

    public function start(BackupRun $run): BackupRun
    {
        $run->forceFill([
            'status'     => BackupRun::STATUS_RUNNING,
            'started_at' => now(),
        ])->save();

        $this->event($run, 'info', 'run.started', 'اجرای بکاپ آغاز شد.', [
            'direction' => $run->direction,
            'mode'      => $run->mode,
            'dry_run'   => $run->is_dry_run,
        ]);

        return $run;
    }

    public function finish(BackupRun $run, string $status, array $summary = []): BackupRun
    {
        $finishedAt = now();

        $run->forceFill([
            'status'      => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => $run->started_at ? (int) $run->started_at->diffInMilliseconds($finishedAt) : null,
            'summary_json'=> $summary ?: $run->summary_json,
        ])->save();

        $this->event($run, $status === BackupRun::STATUS_FAILED ? 'error' : 'info', 'run.finished', 'اجرا پایان یافت.', [
            'status'      => $status,
            'duration_ms' => $run->duration_ms,
        ]);

        $this->closeLogFile();

        return $run;
    }

    public function fail(BackupRun $run, Throwable $e): BackupRun
    {
        $run->forceFill([
            'error_code'    => class_basename($e),
            'error_message' => mb_substr($e->getMessage(), 0, 2000),
            'error_trace'   => mb_substr($e->getTraceAsString(), 0, 8000),
        ])->save();

        $this->event($run, 'critical', 'run.failed', $e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
        ]);

        Log::error('[backup] run failed', ['run_id' => $run->id, 'exception' => $e]);

        return $this->finish($run, BackupRun::STATUS_FAILED);
    }

    public function entity(BackupRun $run, string $entityKey, array $attributes): BackupRunEntity
    {
        return BackupRunEntity::query()->updateOrCreate(
            ['backup_run_id' => $run->id, 'entity_key' => $entityKey],
            $attributes,
        );
    }

    public function event(BackupRun $run, string $level, string $code, string $message, array $context = []): void
    {
        BackupRunEvent::query()->create([
            'backup_run_id' => $run->id,
            'level'         => $level,
            'code'          => $code,
            'message'       => mb_substr($message, 0, 2000),
            'context_json'  => $context ?: null,
            'created_at'    => now(),
        ]);

        $this->writeLogLine($level, $code, $message, $context);
    }

    private function writeLogLine(string $level, string $code, string $message, array $context): void
    {
        if (! is_resource($this->logHandle)) {
            return;
        }

        fwrite($this->logHandle, sprintf(
            "[%s] %-8s %-18s %s %s\n",
            now()->format('Y-m-d H:i:s'),
            strtoupper($level),
            $code,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
        ));
    }

    private function closeLogFile(): void
    {
        if (is_resource($this->logHandle)) {
            fclose($this->logHandle);
        }

        $this->logHandle = null;
    }
}
