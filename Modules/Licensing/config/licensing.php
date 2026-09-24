<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| پیکربندی ماژول Licensing
|--------------------------------------------------------------------------
| فلوی فعال‌سازی: درخواست بدون کد (client.sig) -> ادمین در StoreServer تأیید
| می‌کند و یک کد یک‌بارمصرف صادر می‌کند -> مشتری همان کد را اینجا وارد می‌کند
| (client.sig، بدون توکن) -> از این پس هر ساعت heartbeat (client.sig +
| license.token:allow_expired) برای تمدید توکن و اطلاع از قفل‌شدن لایسنس.
|
| از همان STORE_SERVER_URL که ماژول AuditLog استفاده می‌کند استفاده می‌شود
| چون هر دو به یک StoreServer وصل می‌شوند.
*/

return [

    'server' => [
        'base_url' => rtrim((string) env('STORE_SERVER_URL', 'https://store-server.local'), '/'),

        'endpoints' => [
            'activation_request' => '/api/v1/activation/request',
            'activation_status'  => '/api/v1/activation/status/{uuid}',
            'activation_redeem'  => '/api/v1/activation/redeem',
            'heartbeat'          => '/api/v1/heartbeat',
        ],

        'timeout'         => (int) env('LICENSING_HTTP_TIMEOUT', 15),
        'connect_timeout' => (int) env('LICENSING_HTTP_CONNECT_TIMEOUT', 5),
        'retries'         => (int) env('LICENSING_HTTP_RETRIES', 1),
        'verify_ssl'      => (bool) env('LICENSING_VERIFY_SSL', true),
    ],

    // هدرهای امنیتی دقیقاً مطابق ClientApi\Http\Middleware\VerifyClientSignature +
    // AuthenticateLicenseToken روی StoreServer (بدون HMAC/راز مشترک؛ فقط
    // ضد-replay با timestamp/nonce + توکن Bearer وقتی موجود باشد)
    'headers' => [
        'timestamp'   => 'X-GS-Timestamp',
        'nonce'       => 'X-GS-Nonce',
        'fingerprint' => 'X-GS-Fingerprint',
        'client'      => 'X-GS-Client',
    ],

    // فاصله‌ی پیش‌فرض heartbeat تا وقتی سرور عدد دیگری برنگردانده (سرور خودش هر بار
    // heartbeat_interval_minutes واقعی را برمی‌گرداند و در LicenseState ذخیره می‌شود)
    'default_heartbeat_minutes' => (int) env('LICENSING_DEFAULT_HEARTBEAT_MINUTES', 60),

    // فاصله‌ی پیش‌فرض poll وضعیت درخواست فعال‌سازی
    'default_poll_seconds' => (int) env('LICENSING_DEFAULT_POLL_SECONDS', 30),
        // کلید(های) عمومی Ed25519 سرور: { "kid": "publicKeyBase64Url" }
    'public_keys' => json_decode((string) env('LICENSING_PUBLIC_KEYS', '{}'), true) ?: [],
];