<?php

declare(strict_types=1);

namespace Modules\Setting\Services\Desktop;

use Illuminate\Support\Facades\Cache;
use Modules\Setting\Events\TrayShowRequested;
use Modules\Setting\Services\Setting\SettingService;
use Native\Laravel\Client\Client;
use Native\Laravel\Facades\App as NativeApp;
use Native\Laravel\Facades\Menu;
use Native\Laravel\Facades\MenuBar;
use Native\Laravel\Facades\Window;
use Throwable;

/**
 * رفتار پوسته‌ی دسکتاپ: اجرای خودکار با ویندوز، آیکن Tray، و بسته‌شدن پنجره به Tray.
 *
 * ایمنی: «بستن به Tray» فقط وقتی فعال می‌شود که Electron خبر ساخته‌شدن آیکن Tray را داده باشد
 * (MenuBarCreated)، تا برنامه هرگز بدون آیکن و نامرئی در پس‌زمینه نماند.
 */
final class DesktopShell
{
    private const TRAY_READY_KEY = 'gs.desktop.tray_ready';

    /** هنگام بالا آمدن برنامه‌ی Electron. */
    public static function boot(): void
    {
        self::run('boot-state', static fn () => Cache::forget(self::TRAY_READY_KEY));
        self::run('auto-launch-boot', static fn () => self::syncAutoLaunch(self::settings()->autoLaunch()));
        self::run('tray', static fn () => self::createTray());
    }

    /** Electron خبر داد آیکن Tray ساخته شد. */
    public static function onTrayCreated(): void
    {
        self::run('tray-ready', static function (): void {
            Cache::put(self::TRAY_READY_KEY, true, now()->addDay());
            self::syncHideOnClose(self::settings()->minimizeToTray());
        });
    }

    public static function syncAutoLaunch(bool $enabled): void
    {
        self::run('auto-launch', static fn () => NativeApp::openAtLogin($enabled));
    }

    /** وقتی true باشد، دکمه‌ی بستن پنجره‌ی اصلی آن را به Tray می‌برد (نیازمند پچ Electron). */
    public static function syncHideOnClose(bool $enabled): void
    {
        $enabled = $enabled && Cache::get(self::TRAY_READY_KEY, false) === true;

        self::run('hide-on-close', static fn () => app(Client::class)->post('window/hide-on-close', ['enabled' => $enabled]));
    }

    public static function showMainWindow(): void
    {
        self::run('show-window', static fn () => Window::open());
    }

    private static function createTray(): void
    {
        $icon = public_path('icon.ico');
        if (! is_file($icon)) {
            $icon = public_path('icon.png');
        }
        if (! is_file($icon)) {
            logger()->warning('DesktopShell: آیکن Tray پیدا نشد؛ بستن به Tray فعال نمی‌شود.');

            return;
        }

        MenuBar::create()
            ->icon($icon)
            ->tooltip('گیم‌استور')
            ->onlyShowContextMenu()
            ->withContextMenu(Menu::make(
                Menu::label('نمایش برنامه')->event(TrayShowRequested::class),
                Menu::separator(),
                Menu::quit('خروج کامل'),
            ));
    }

    private static function settings(): SettingService
    {
        return app('settings');
    }

    private static function run(string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            report($e);
            logger()->warning("DesktopShell [{$what}] failed: " . $e->getMessage());
        }
    }
}