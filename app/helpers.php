<?php

/*
|--------------------------------------------------------------------------
| GameStore — Settings helpers
|--------------------------------------------------------------------------
| Register via composer.json "autoload": { "files": ["app/helpers.php"] }
| or bootstrap/app.php ->->withRouting(...)->then(function () { require ... });
|--------------------------------------------------------------------------
*/

use App\Services\SettingsService;

if (! function_exists('setting')) {

    /**
     * Typed access to the settings registry.
     *
     * setting('invoice.prefix')        → 'INV-'
     * setting('inventory.low_stock_threshold', 5) → 5
     * setting()->set('invoice.prefix', 'F-')     → write (locked-aware)
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}
