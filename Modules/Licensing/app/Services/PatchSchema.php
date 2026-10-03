<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Licensing\Patching\Core\Installer;

/**
 * جدول‌های پچ به‌صورت idempotent ساخته می‌شوند (CREATE IF NOT EXISTS).
 * این پروژه جدول‌هایش را دستی مدیریت می‌کند و migration ندارد؛ پس خود سرویس در اولین
 * استفاده جدول را می‌سازد و روی نصب تازه‌ی مشتری هم بدون هیچ قدم دستی کار می‌کند.
 * فایل migration ماژول فقط همین متد را صدا می‌زند.
 */
final class PatchSchema
{
    private static bool $done = false;

    public static function ensure(): void
    {
        if (self::$done) {
            return;
        }

        if (! Schema::hasTable('license_patches')) {
            Schema::create('license_patches', function (Blueprint $t): void {
                $t->id();
                $t->string('patch_code', 64)->unique();
                $t->string('title', 255)->default('');
                $t->text('description')->nullable();
                $t->string('type', 16)->default('files');
                $t->string('from_min', 16)->default('0.0.0');
                $t->string('from_max', 16)->default('999.999.999');
                $t->string('to_version', 16)->default('0.0.0');
                $t->boolean('requires_restart')->default(false);
                $t->boolean('mandatory')->default(false);
                $t->unsignedBigInteger('size')->default(0);
                $t->string('sha256', 64)->nullable();
                $t->json('depends_on')->nullable();
                $t->string('status', 16)->default('available')->index(); // available|queued|running|applied|failed|rolled_back
                $t->string('stage', 24)->nullable();
                $t->unsignedTinyInteger('progress')->default(0);
                $t->text('message')->nullable();
                $t->string('error_code', 48)->nullable();
                $t->string('version_before', 16)->nullable();
                $t->string('version_after', 16)->nullable();
                $t->json('applied_files')->nullable();
                $t->text('backup_path')->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->boolean('restart_pending')->default(false);
                $t->json('pending_report')->nullable();
                $t->dateTime('last_seen_at')->nullable();
                $t->dateTime('started_at')->nullable();
                $t->dateTime('finished_at')->nullable();
                $t->timestamps();
            });
        }

        // ledger اسکریپت‌های SQL اجراشده (idempotency)
        Installer::ensureLedger(\Illuminate\Support\Facades\DB::connection()->getPdo());

        self::$done = true;
    }
}
