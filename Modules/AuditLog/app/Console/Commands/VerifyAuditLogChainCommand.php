<?php

declare(strict_types=1);

namespace Modules\AuditLog\Console\Commands;

use Illuminate\Console\Command;
use Modules\AuditLog\Services\IntegrityVerifier;

/**
 * php artisan auditlog:verify --from=1 --limit=10000
 */
class VerifyAuditLogChainCommand extends Command
{
    protected $signature = 'auditlog:verify {--from= : شروع از این آیدی} {--limit= : حداکثر تعداد رکورد}';

    protected $description = 'بررسی دست‌نخوردگی زنجیره‌ی هش لاگ‌ها (tamper detection)';

    public function handle(IntegrityVerifier $verifier): int
    {
        $result = $verifier->verify(
            $this->option('from') !== null ? (int) $this->option('from') : null,
            $this->option('limit') !== null ? (int) $this->option('limit') : null,
        );

        $this->info(sprintf('%d رکورد بررسی شد (از %s تا %s).', $result['checked'], $result['first_id'] ?? '—', $result['last_id'] ?? '—'));

        if ($result['valid']) {
            $this->info('✔ زنجیره سالم است؛ هیچ نشانه‌ای از دستکاری یافت نشد.');

            return self::SUCCESS;
        }

        $this->error(sprintf('✘ %d ناسازگاری یافت شد:', count($result['broken'])));

        $this->table(
            ['id', 'uuid', 'sequence', 'دلیل'],
            array_map(static fn (array $row): array => [
                $row['id'], $row['uuid'], $row['sequence'], $row['reason'],
            ], array_slice($result['broken'], 0, 50)),
        );

        return self::FAILURE;
    }
}
