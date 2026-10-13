<?php

declare(strict_types=1);

namespace Modules\Setting\Services\Desktop;

/**
 * پچ سمت Electron برای «بستن پنجره به Tray».
 *
 * NativePHP نسخه‌ی ۱.۳ جلوی بسته‌شدن پنجره را از سمت PHP نمی‌دهد و با بسته‌شدن آخرین پنجره برنامه را می‌بندد.
 * این کلاس فایل‌های کامپایل‌شده‌ی vendor/nativephp/electron را قبل از ساخت نصب‌کننده تغییر می‌دهد:
 *   - endpoint جدید  POST /api/window/hide-on-close  {enabled: bool}
 *   - وقتی فعال باشد، close پنجره‌ی main لغو و پنجره hide می‌شود (مگر موقع quit/خاموش‌شدن ویندوز)
 *   - اجرای دوباره‌ی برنامه (دابل‌کلیک روی شورتکات) پنجره‌ی مخفی را نشان می‌دهد
 *
 * چندبار اجرا کردنش بی‌خطر است (idempotent).
 */
final class ElectronPatcher
{
    private const MARKER = 'GS-TRAY-PATCH';

    public static function defaultDistPath(): string
    {
        return base_path('vendor/nativephp/electron/resources/js/electron-plugin/dist');
    }

    /**
     * @return array<string,array{ok:bool,message:string}> نتیجه به‌ازای هر فایل
     */
    public function patch(string $dist): array
    {
        $dist = rtrim(str_replace('\\', '/', $dist), '/');

        return [
            'server/api/window.js' => $this->patchFile("$dist/server/api/window.js", [
                ['/import \{ BrowserWindow \} from [\'"]electron[\'"];/', "import { app, BrowserWindow } from 'electron';"],
                ['/const router = express\.Router\(\);/', <<<'JS'
const router = express.Router();
// GS-TRAY-PATCH: بستن پنجره‌ی اصلی به Tray
app.on('before-quit', () => { state.gsQuitting = true; });
router.post('/hide-on-close', (req, res) => {
    state.gsHideOnClose = !!(req.body && req.body.enabled);
    res.sendStatus(200);
});
JS],
                ['/window\.on\(\'close\', \(evt\) => \{(\s*)if \(state\.windows\[id\]\) \{/', <<<'JS'
window.on('session-end', () => { state.gsQuitting = true; });
    window.on('close', (evt) => {
        if (state.gsHideOnClose && !state.gsQuitting && id === 'main') {
            evt.preventDefault();
            window.hide();
            return;
        }
        if (state.windows[id]) {
JS],
            ]),
            'index.js' => $this->patchFile("$dist/index.js", [
                ['/if \(this\.mainWindow\) \{\s*if \(this\.mainWindow\.isMinimized\(\)\)\s*this\.mainWindow\.restore\(\);\s*this\.mainWindow\.focus\(\);\s*\}/', <<<'JS'
// GS-TRAY-PATCH: نمایش پنجره‌ی مخفی‌شده در Tray
                        const gsWin = this.mainWindow || state.windows["main"];
                        if (gsWin) {
                            if (gsWin.isMinimized())
                                gsWin.restore();
                            gsWin.show();
                            gsWin.focus();
                        }
JS],
            ]),
        ];
    }

    /**
     * @param array<int,array{0:string,1:string}> $rules [regex, جایگزین]
     * @return array{ok:bool,message:string}
     */
    private function patchFile(string $file, array $rules): array
    {
        if (! is_file($file)) {
            return ['ok' => false, 'message' => "پیدا نشد: $file"];
        }

        $src = (string) file_get_contents($file);
        if (str_contains($src, self::MARKER)) {
            return ['ok' => true, 'message' => 'قبلاً پچ شده'];
        }

        foreach ($rules as $i => [$regex, $replace]) {
            $src = preg_replace_callback($regex, static fn (): string => $replace, $src, 1, $count);
            if ($src === null || $count !== 1) {
                return ['ok' => false, 'message' => 'قاعده‌ی ' . ($i + 1) . ' جا نیفتاد (نسخه‌ی NativePHP فرق دارد؟)؛ هیچ تغییری ذخیره نشد.'];
            }
        }

        if (! is_file($file . '.gs-orig')) {
            copy($file, $file . '.gs-orig');
        }
        file_put_contents($file, $src);

        return ['ok' => true, 'message' => 'پچ شد'];
    }
}