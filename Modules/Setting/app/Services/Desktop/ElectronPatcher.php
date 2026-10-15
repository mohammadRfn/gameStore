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
 *   - (single) فقط یک نمونه از برنامه اجرا می‌شود؛ اجرای دوباره پنجره‌ی نمونه‌ی اول را نشان می‌دهد
 *     (قفل تک‌نمونه‌ی خود NativePHP فقط وقتی NATIVEPHP_DEEPLINK_SCHEME ست باشد فعال می‌شود)
 *   - (noproxy) درخواست‌های لوکال (Electron ↔ PHP روی 127.0.0.1) هرگز از پروکسی/VPN سیستم عبور نمی‌کنند؛
 *     وگرنه موقع بوت سرد که کلاینت پروکسی هنوز بالا نیامده، پیام «booted» با ECONNREFUSED به پورت پروکسی شکست می‌خورد
 *   - (boot) درخواست «booted» به لاراول موقع بوت سرد سیستم (اجرای خودکار) اگر به خاطر خطای اتصال شکست بخورد
 *     دوباره تلاش می‌شود، و مراحل راه‌اندازی در  %APPDATA%/<app>/native-boot.log ثبت می‌شود
 *
 * چندبار اجرا کردنش بی‌خطر است (idempotent).
 */
final class ElectronPatcher
{
    private const MARKER_TRAY = 'GS-TRAY-PATCH';

    private const MARKER_BOOT = 'GS-BOOT-PATCH';

    private const MARKER_SINGLE = 'GS-SINGLE-PATCH';

    private const MARKER_NOPROXY = 'GS-NOPROXY-PATCH';

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
            'window.js [tray]' => $this->patchFile("$dist/server/api/window.js", self::MARKER_TRAY, [
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
            'index.js [tray]' => $this->patchFile("$dist/index.js", self::MARKER_TRAY, [
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
            'index.js [single]' => $this->patchFile("$dist/index.js", self::MARKER_SINGLE, [
                ['/initialize\(\);(\s*)state\.icon = icon;/', <<<'JS'
// GS-SINGLE-PATCH: فقط یک نمونه از برنامه؛ نمونه‌ی دوم بلافاصله بسته می‌شود
        if (!app.requestSingleInstanceLock()) {
            app.quit();
            return;
        }
        app.on('second-instance', () => {
            const gsWin = state.windows["main"];
            if (gsWin) {
                if (gsWin.isMinimized())
                    gsWin.restore();
                gsWin.show();
                gsWin.focus();
            }
        });
        initialize();
        state.icon = icon;
JS],
            ]),
            'index.js [noproxy]' => $this->patchFile("$dist/index.js", self::MARKER_NOPROXY, [
                ['/initialize\(\);(\s*)state\.icon = icon;/', <<<'JS'
// GS-NOPROXY-PATCH: ترافیک لوکال از پروکسی/VPN رد نشود (به PHP هم به ارث می‌رسد)
        process.env.NO_PROXY = '127.0.0.1,localhost,::1' + (process.env.NO_PROXY ? ',' + process.env.NO_PROXY : '');
        process.env.no_proxy = process.env.NO_PROXY;
        initialize();
        state.icon = icon;
JS],
            ]),
            'utils.js [boot]' => $this->patchFile("$dist/server/utils.js", self::MARKER_BOOT, [
                ['/import \{ session \} from \'electron\';/', <<<'JS'
import { app as gsApp, session } from 'electron';
import * as gsFs from 'fs';
import * as gsPath from 'path';
JS],
                ['/import axios from \'axios\';/', <<<'JS'
import axios from 'axios';
// GS-BOOT-PATCH: لاگ راه‌اندازی در userData/native-boot.log
export function gsLog(msg) {
    try {
        gsFs.appendFileSync(gsPath.join(gsApp.getPath('userData'), 'native-boot.log'), new Date().toISOString() + ' ' + msg + '\n');
    }
    catch (e) {
    }
}
JS],
                ['~try \{\s*yield axios\.post\(`http://127\.0\.0\.1:\$\{state\.phpPort\}/_native/api/\$\{endpoint\}`, payload, \{\s*headers: \{\s*"X-NativePHP-Secret": state\.randomSecret,\s*\},\s*\}\);\s*\}\s*catch \(e\) \{\s*\}~', <<<'JS'
const gsBoot = endpoint === 'booted';
        for (let gsTry = 1;; gsTry++) {
            try {
                yield axios.post(`http://127.0.0.1:${state.phpPort}/_native/api/${endpoint}`, payload, {
                    headers: {
                        "X-NativePHP-Secret": state.randomSecret,
                    },
                });
                if (gsBoot)
                    gsLog('booted: ok (attempt ' + gsTry + ')');
                break;
            }
            catch (e) {
                if (!gsBoot)
                    break;
                const gsStatus = e && e.response ? e.response.status : null;
                gsLog('booted: attempt ' + gsTry + ' failed: ' + (e && e.message) + (gsStatus ? ' [HTTP ' + gsStatus + ']' : ''));
                // خطای HTTP یعنی درخواست به لاراول رسیده؛ تلاش دوباره boot() را دوباره اجرا می‌کرد
                if (gsStatus || gsTry >= 30)
                    break;
                yield new Promise((r) => setTimeout(r, 2000));
            }
        }
JS],
            ]),
            'index.js [boot]' => $this->patchFile("$dist/index.js", self::MARKER_BOOT, [
                ['/import \{ notifyLaravel \} from "\.\/server\/utils\.js";/', 'import { notifyLaravel, gsLog } from "./server/utils.js";'],
                ['/this\.bootstrapApp\(app\);/', <<<'JS'
// GS-BOOT-PATCH
        gsLog('app start (pid ' + process.pid + ')');
        this.bootstrapApp(app).catch((e) => gsLog('bootstrapApp failed: ' + (e && e.stack ? e.stack : e)));
JS],
                ['/yield this\.startPhpApp\(\);/', "gsLog('php: starting');\n            yield this.startPhpApp();\n            gsLog('php: started on port ' + state.phpPort);"],
                ['/yield notifyLaravel\("booted"\);/', "gsLog('booted: notifying');\n            yield notifyLaravel(\"booted\");"],
            ]),
            // باید بعد از utils.js [boot] بیاید، چون به متن پچ‌شده‌ی آن تکیه دارد
            'utils.js [noproxy]' => $this->patchFile("$dist/server/utils.js", self::MARKER_NOPROXY, [
                ['/yield axios\.post\(`http:\/\/127\.0\.0\.1:\$\{state\.phpPort\}\/_native\/api\/\$\{endpoint\}`, payload, \{/', <<<'JS'
// GS-NOPROXY-PATCH
                yield axios.post(`http://127.0.0.1:${state.phpPort}/_native/api/${endpoint}`, payload, {
                    proxy: false,
JS],
            ]),
        ];
    }

    /**
     * @param array<int,array{0:string,1:string}> $rules [regex, جایگزین]
     * @return array{ok:bool,message:string}
     */
    private function patchFile(string $file, string $marker, array $rules): array
    {
        if (! is_file($file)) {
            return ['ok' => false, 'message' => "پیدا نشد: $file"];
        }

        $src = (string) file_get_contents($file);
        if (str_contains($src, $marker)) {
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