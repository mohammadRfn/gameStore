<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * جمع‌آوری مشخصات واقعی سخت‌افزار و سیستم‌عامل این دستگاه برای StoreServer.
 *
 * php_uname() فقط «معماری» (AMD64) و نسخه‌ی NT (10.0) را می‌دهد، نه برند CPU
 * و نه ویندوز ۱۱؛ به همین دلیل از CIM/PowerShell (با fallback به wmic) روی
 * ویندوز و از /proc و /sys روی لینوکس/مک استفاده می‌شود.
 *
 * نتیجه فقط وقتی کش می‌شود که کامل باشد (CPU و RAM پیدا شده باشند) تا یک
 * شکست موقت PowerShell تا ۱۲ ساعت اطلاعات ناقص ماندگار نکند.
 */
class SystemInfoCollector
{
    private const CACHE_KEY = 'licensing:system-info:v2';
    private const CACHE_TTL = 43200; // ۱۲ ساعت

    /** مقادیر بی‌ارزشی که سازنده‌ها به‌جای سریال واقعی می‌گذارند */
    private const JUNK_SERIALS = [
        '', 'none', 'n/a', 'na', 'default string', 'to be filled by o.e.m.',
        'to be filled by oem', 'not applicable', 'not specified', 'unknown',
        'system serial number', 'base board serial number', '0', '123456789',
        '00000000', 'o.e.m.', 'oem',
    ];

    /** @return array<string, mixed> */
    public function collect(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $info = match (PHP_OS_FAMILY) {
                'Windows' => $this->windows(),
                'Linux'   => $this->linux(),
                'Darwin'  => $this->darwin(),
                default   => [],
            };
        } catch (Throwable) {
            $info = [];
        }

        $info = $this->withBasics($info);

        if (! empty($info['cpu']) && ! empty($info['ram_mb'])) {
            Cache::put(self::CACHE_KEY, $info, self::CACHE_TTL);
        }

        return $info;
    }

    /* ------------------------------------------------------------------ */
    /*  Windows                                                            */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    private function windows(): array
    {
        $raw = $this->windowsViaPowerShell() ?? $this->windowsViaWmic();

        if ($raw === null) {
            return [];
        }

        $build   = (int) ($raw['os_build'] ?? 0);
        $caption = trim((string) preg_replace('/^Microsoft\s+/i', '', (string) ($raw['os_caption'] ?? '')));

        // ویندوز ۱۱ هم NT 10.0 گزارش می‌شود؛ تشخیص فقط با build >= 22000 ممکن است.
        // اگر Caption ناقص بود، از روی build اسم را می‌سازیم.
        if ($build >= 22000) {
            $caption = $caption !== '' ? preg_replace('/Windows\s+10/i', 'Windows 11', $caption) : 'Windows 11';
        } elseif ($caption === '') {
            $caption = 'Windows 10';
        }

        $boardSerial = $this->cleanSerial($raw['board_serial'] ?? null)
            ?? $this->cleanSerial($raw['bios_serial'] ?? null);

        return [
            'os'                 => $this->limit($caption, 128),
            'os_version'         => $this->limit((string) ($raw['os_version'] ?? ''), 64),
            'os_build'           => $build > 0 ? (string) $build : null,
            'cpu'                => $this->limit($this->squash((string) ($raw['cpu'] ?? '')), 191),
            'ram_mb'             => $this->bytesToMb($raw['ram_bytes'] ?? null),
            'motherboard_serial' => $boardSerial !== null ? $this->limit($boardSerial, 128) : null,
            'motherboard_model'  => $this->limit($this->squash(trim(($raw['board_maker'] ?? '') . ' ' . ($raw['board_product'] ?? ''))), 191),
            'disk_serial'        => ($d = $this->cleanSerial($raw['disk_serial'] ?? null)) !== null ? $this->limit($d, 128) : null,
            'disk_model'         => $this->limit($this->squash((string) ($raw['disk_model'] ?? '')), 191),
            'mac_address'        => $this->limit(strtoupper((string) ($raw['mac'] ?? '')), 64),
        ];
    }

    /** @return array<string, mixed>|null */
    private function windowsViaPowerShell(): ?array
    {
        $script = <<<'PS'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = 'SilentlyContinue'
$os   = Get-CimInstance Win32_OperatingSystem
$cpu  = Get-CimInstance Win32_Processor | Select-Object -First 1
$cs   = Get-CimInstance Win32_ComputerSystem
$bb   = Get-CimInstance Win32_BaseBoard | Select-Object -First 1
$bios = Get-CimInstance Win32_BIOS | Select-Object -First 1
$sys  = $env:SystemDrive.TrimEnd(':')
$disk = Get-Partition -DriveLetter $sys | Get-Disk | Select-Object -First 1
if (-not $disk) { $dd = Get-CimInstance Win32_DiskDrive | Sort-Object Index | Select-Object -First 1 }
$net  = Get-CimInstance Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled -and $_.MACAddress } | Select-Object -First 1
[pscustomobject]@{
  os_caption    = $os.Caption
  os_version    = $os.Version
  os_build      = $os.BuildNumber
  cpu           = $cpu.Name
  ram_bytes     = $cs.TotalPhysicalMemory
  board_maker   = $bb.Manufacturer
  board_product = $bb.Product
  board_serial  = $bb.SerialNumber
  bios_serial   = $bios.SerialNumber
  disk_serial   = $(if ($disk) { $disk.SerialNumber } else { $dd.SerialNumber })
  disk_model    = $(if ($disk) { $disk.FriendlyName } else { $dd.Model })
  mac           = $net.MACAddress
} | ConvertTo-Json -Compress
PS;

        $root = getenv('SystemRoot') ?: 'C:\\Windows';
        $exe  = $root . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

        $out = $this->run([
            is_file($exe) ? $exe : 'powershell.exe',
            '-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden',
            '-ExecutionPolicy', 'Bypass',
            // -EncodedCommand مشکل quoting/خط‌جدید در آرگومان‌های ویندوز را ندارد
            '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8')),
        ], 25);

        if ($out === null) {
            return null;
        }

        $json = json_decode(ltrim($out, "\xEF\xBB\xBF \t\r\n"), true);

        return is_array($json) && ! empty($json['cpu']) ? $json : null;
    }

    /** wmic روی ویندوزهای جدید (24H2+) حذف شده؛ فقط fallback است. @return array<string, mixed>|null */
    private function windowsViaWmic(): ?array
    {
        $get = function (string $alias, string $field): ?string {
            $out = $this->run(['wmic', $alias, 'get', $field, '/value'], 10);

            if ($out !== null && preg_match('/^' . preg_quote($field, '/') . '=(.*)$/mi', $out, $m)) {
                return trim($m[1]);
            }

            return null;
        };

        $cpu = $get('cpu', 'Name');

        if ($cpu === null || $cpu === '') {
            return null;
        }

        return [
            'os_caption'    => $get('os', 'Caption'),
            'os_version'    => $get('os', 'Version'),
            'os_build'      => $get('os', 'BuildNumber'),
            'cpu'           => $cpu,
            'ram_bytes'     => $get('computersystem', 'TotalPhysicalMemory'),
            'board_maker'   => $get('baseboard', 'Manufacturer'),
            'board_product' => $get('baseboard', 'Product'),
            'board_serial'  => $get('baseboard', 'SerialNumber'),
            'bios_serial'   => $get('bios', 'SerialNumber'),
            'disk_serial'   => $get('diskdrive', 'SerialNumber'),
            'disk_model'    => $get('diskdrive', 'Model'),
            'mac'           => null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Linux / macOS (اپ اصلی ویندوزی است؛ برای محیط توسعه)               */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    private function linux(): array
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo') ?: '';
        $meminfo = @file_get_contents('/proc/meminfo') ?: '';
        $osrel   = @parse_ini_file('/etc/os-release') ?: [];

        $cpu = preg_match('/^model name\s*:\s*(.+)$/mi', $cpuinfo, $m) ? $m[1] : '';
        $ram = preg_match('/^MemTotal:\s*(\d+)\s*kB/mi', $meminfo, $m) ? (int) round(((int) $m[1]) / 1024) : null;

        $dmi = static fn (string $f): ?string => ($v = @file_get_contents("/sys/class/dmi/id/{$f}")) !== false ? trim($v) : null;

        $disk = $this->run(['lsblk', '-dno', 'SERIAL'], 5);
        $mac  = null;

        foreach (glob('/sys/class/net/*/address') ?: [] as $file) {
            $addr = trim((string) @file_get_contents($file));

            if ($addr !== '' && $addr !== '00:00:00:00:00:00' && ! str_contains($file, '/lo/')) {
                $mac = strtoupper($addr);
                break;
            }
        }

        $diskSerial = $disk !== null ? $this->cleanSerial(strtok($disk, "\n") ?: null) : null;

        return [
            'os'                 => $this->limit((string) ($osrel['PRETTY_NAME'] ?? 'Linux'), 128),
            'os_version'         => $this->limit(php_uname('r'), 64),
            'cpu'                => $this->limit($this->squash($cpu), 191),
            'ram_mb'             => $ram,
            'motherboard_serial' => $this->cleanSerial($dmi('board_serial')) ?? $this->cleanSerial($dmi('product_serial')),
            'disk_serial'        => $diskSerial,
            'mac_address'        => $mac,
        ];
    }

    /** @return array<string, mixed> */
    private function darwin(): array
    {
        $sysctl = fn (string $k): ?string => ($o = $this->run(['sysctl', '-n', $k], 5)) !== null ? trim($o) : null;
        $ver    = $this->run(['sw_vers', '-productVersion'], 5);

        return [
            'os'         => 'macOS',
            'os_version' => $this->limit(trim((string) $ver) ?: php_uname('r'), 64),
            'cpu'        => $this->limit($this->squash((string) $sysctl('machdep.cpu.brand_string')), 191),
            'ram_mb'     => $this->bytesToMb($sysctl('hw.memsize')),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private function withBasics(array $info): array
    {
        // اگر جمع‌آوری شکست خورد، حداقل همان اطلاعات قبلی (معماری) فرستاده شود
        $info += [
            'os'         => PHP_OS_FAMILY,
            'os_version' => php_uname('r'),
        ];

        if (empty($info['cpu'])) {
            $info['cpu'] = php_uname('m');
        }

        $info['hostname'] = gethostname() ?: null;
        $info['timezone'] = (string) config('app.timezone', date_default_timezone_get());

        return array_filter($info, static fn ($v) => $v !== null && $v !== '');
    }

    /** @param list<string> $command */
    private function run(array $command, int $timeout): ?string
    {
        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function cleanSerial(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // سریال NVMe ویندوز مثل "0025_38D1_21B2_C3F4." است
        $serial = trim((string) $value, " \t\r\n.");

        return in_array(strtolower($serial), self::JUNK_SERIALS, true) ? null : $serial;
    }

    private function bytesToMb(mixed $bytes): ?int
    {
        return is_numeric($bytes) && (float) $bytes > 0 ? (int) round(((float) $bytes) / 1048576) : null;
    }

    private function squash(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function limit(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }
}