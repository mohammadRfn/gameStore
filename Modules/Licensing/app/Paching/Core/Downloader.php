<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Core;

use Closure;

/**
 * دانلود با قابلیت ادامه (Range). فایل ناقص با پسوند .part می‌ماند تا تلاش بعدی از همان‌جا ادامه دهد.
 * نکته: download_url امضاشده است و نباید دست‌کاری شود (حتی ترتیب query).
 */
final class Downloader
{
    public function __construct(private readonly bool $verifySsl = true) {}

    /**
     * @param list<string>                 $headers
     * @param Closure(int,int):void|null   $onProgress (دریافت‌شده، کل)
     */
    public function download(string $url, string $dest, ?int $expectedSize, array $headers, ?Closure $onProgress = null): int
    {
        $part = $dest . '.part';
        $have = is_file($part) ? (int) filesize($part) : 0;

        if ($expectedSize !== null && $have > $expectedSize) {
            @unlink($part); // خراب است
            $have = 0;
        }
        if ($expectedSize !== null && $have === $expectedSize && $have > 0) {
            rename($part, $dest); // دانلود قبلاً کامل شده بود و فقط rename مانده بود

            return (int) filesize($dest);
        }

        $fh = fopen($part, $have > 0 ? 'ab' : 'wb');
        if ($fh === false) {
            throw new PatchFailure('DOWNLOAD_FAILED', 'نوشتن فایل دانلود ممکن نیست: ' . $part);
        }
        if ($have > 0) {
            $headers[] = "Range: bytes={$have}-";
        }

        $status = 0;
        $offset = $have;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_HEADERFUNCTION => function ($c, string $line) use (&$status, &$fh, &$offset): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                    $status = (int) $m[1];
                    if ($status === 200) { // سرور Range را نادیده گرفت → از اول
                        ftruncate($fh, 0);
                        fseek($fh, 0);
                        $offset = 0;
                    }
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION  => function ($c, string $data) use (&$fh, &$status): int {
                if ($status >= 400) {
                    return strlen($data); // بدنه‌ی خطا داخل فایل نرود
                }

                return (int) fwrite($fh, $data);
            },
            CURLOPT_PROGRESSFUNCTION => function ($c, $dlTotal, $dlNow) use ($onProgress, &$offset): int {
                if ($onProgress !== null && $dlTotal > 0) {
                    $onProgress((int) ($offset + $dlNow), (int) ($offset + $dlTotal));
                }

                return 0;
            },
        ]);
        $ok  = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($ok === false) {
            throw new PatchFailure('DOWNLOAD_FAILED', "دانلود قطع شد ({$err}). فایل ناقص برای ادامه نگه داشته شد.");
        }
        if ($status >= 400) {
            @unlink($part);
            throw new PatchFailure(
                $status === 410 ? 'LINK_EXPIRED' : 'DOWNLOAD_FAILED',
                $status === 410 ? 'لینک دانلود منقضی شده است.' : "سرور پاسخ خطا داد (HTTP {$status}).",
            );
        }
        if (! rename($part, $dest)) {
            throw new PatchFailure('DOWNLOAD_FAILED', 'نهایی‌کردن فایل دانلودشده ممکن نشد.');
        }

        return (int) filesize($dest);
    }
}
