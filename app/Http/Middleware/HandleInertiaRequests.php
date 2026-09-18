<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id'    => $request->user()->id,
                    'name'  => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],

            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],

            // مقدار واقعی general.theme از ماژول Setting، برای اینکه فرانت
            // بدون نیاز به فراخوانی جدا از "منبع حقیقت" واقعی باخبر باشه
            'theme' => fn () => app('settings')->getString('general.theme', 'light'),

            // نام تجاری پرفایل اصلیِ فروشگاه؛ تا وقتی هیچ پرفایلی ثبت نشده
            // (یا پرفایل اصلی نام تجاری خالی داره)، مقدار پیش‌فرض GameShop می‌مونه.
            'shopName' => fn () => app(\Modules\Profile\Services\StoreProfileService::class)
                ->findPrimary()
                ?->brand_name ?: 'GameShop',
        ]);
    }
}
