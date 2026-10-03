<?php

return [
    'name' => 'SchemaManager',

    // GS_SCHEMA_ENABLED=false در .env → ساخت/هم‌سطح‌سازی خودکار اسکیما خاموش می‌شود
    'enabled' => (bool) env('GS_SCHEMA_ENABLED', true),

    // محل baseline.sql / seed.sql / baseline.meta.json / upgrades/*.sql
    'dir' => database_path('schema'),

    // جدول‌هایی که ردیف‌هایشان در نصب تازه کپی می‌شود. 'migrations' باید باشد تا `migrate`
    // جدول‌های استاندارد (که baseline ساخته) را دوباره نسازد. جدول users را اینجا نگذار.
    'seed_tables' => ['migrations'],

    // جدول‌های اضافه‌ی دیتابیس توسعه که نباید به مشتری برسد
    'exclude' => [],
];