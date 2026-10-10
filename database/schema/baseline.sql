-- baseline اسکیما (تولیدشده توسط schema:export) — دستی ویرایش نکنید
-- 2026-10-10T16:13:44+00:00 | version 1.0.0

CREATE TABLE IF NOT EXISTS "adjustment_categories" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    `key` TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    default_counts_as_revenue TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "app_settings" (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id       INTEGER      NULL,
    setting_key    VARCHAR(100) NOT NULL UNIQUE,       -- 'invoice.prefix'
    setting_value  TEXT         NULL,
    value_type     VARCHAR(20)  NOT NULL DEFAULT 'string',  -- string|integer|float|boolean|json|select
    default_value  TEXT         NULL,                  -- fallback when value is NULL
    is_locked      BOOLEAN      NOT NULL DEFAULT 0,    -- system-critical: not editable
    is_autoload    BOOLEAN      NOT NULL DEFAULT 0,    -- eager-loaded into cache
    is_encrypted   BOOLEAN      NOT NULL DEFAULT 0,    -- secrets stored encrypted
    description    TEXT         NULL,
    updated_by     INTEGER      NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id)   REFERENCES setting_groups (id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users (id)        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS "archive_actions" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    archived_record_id INTEGER,

    source_type TEXT CHECK(length(source_type) <= 20),
    source_id INTEGER,

    action TEXT NOT NULL CHECK(length(action) <= 20),

    actor_id INTEGER,

    reason TEXT,
    payload_json TEXT,

    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "archived_records" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    source_type TEXT NOT NULL CHECK(length(source_type) <= 20),
    source_table TEXT NOT NULL CHECK(length(source_table) <= 50),
    source_id INTEGER NOT NULL,

    invoice_id INTEGER,
    invoice_number TEXT,

    customer_id INTEGER,
    customer_name TEXT,

    title TEXT,

    payment_status TEXT DEFAULT 'paid' CHECK(length(payment_status) <= 20),
    total_amount REAL,
    paid_at TEXT,

    source_created_at TEXT,
    source_updated_at TEXT,

    snapshot_json TEXT NOT NULL,
    snapshot_hash TEXT NOT NULL CHECK(length(snapshot_hash) <= 64),

    archive_status TEXT DEFAULT 'copied' CHECK(length(archive_status) <= 20),

    archived_by INTEGER,
    archived_at TEXT DEFAULT (datetime('now')),

    removed_from_source_at TEXT,
    deletion_mode TEXT CHECK(length(deletion_mode) <= 20),

    reason TEXT,
    metadata_json TEXT,

    deleted_at TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_log_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid TEXT NOT NULL UNIQUE,

    status TEXT NOT NULL DEFAULT 'pending',
    log_count INTEGER NOT NULL DEFAULT 0,
    checksum TEXT,
    payload_bytes INTEGER NOT NULL DEFAULT 0,
    endpoint TEXT,

    attempts INTEGER NOT NULL DEFAULT 0,
    http_status INTEGER,
    error_code TEXT,
    error_message TEXT,
    response TEXT,

    duration_ms INTEGER,
    dispatched_at DATETIME,
    acknowledged_at DATETIME,
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid TEXT NOT NULL UNIQUE,

    channel TEXT NOT NULL,
    level TEXT NOT NULL DEFAULT 'info',
    action TEXT NOT NULL,
    description TEXT,

    entity_type TEXT,
    entity_id INTEGER,
    entity_label TEXT,

    actor_id INTEGER,
    actor_type TEXT NOT NULL DEFAULT 'system',
    actor_name TEXT,
    actor_email TEXT,

    request_id TEXT,
    correlation_id TEXT,
    session_id TEXT,
    method TEXT,
    route TEXT,
    url TEXT,
    ip TEXT,
    user_agent TEXT,
    status_code INTEGER,
    duration_ms INTEGER,
    memory_kb INTEGER,

    old_values TEXT,
    new_values TEXT,
    changed_keys TEXT,
    context TEXT,
    tags TEXT,

    environment TEXT,
    app_version TEXT,
    hostname TEXT,

    sequence INTEGER NOT NULL DEFAULT 0,
    hash TEXT,
    previous_hash TEXT,

    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_attempts INTEGER NOT NULL DEFAULT 0,
    next_attempt_at DATETIME,
    synced_at DATETIME,
    batch_uuid TEXT,
    last_error TEXT,
    remote_id TEXT,

    occurred_at DATETIME NOT NULL,
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS backup_files (
    id              INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    backup_run_id   INTEGER NOT NULL REFERENCES backup_runs (id) ON UPDATE CASCADE ON DELETE CASCADE,

    entity_key      TEXT    NULL,                 -- items / order_items / invoices
    model_type      TEXT    NULL,                 -- App\Models\Item
    model_id        INTEGER NULL,
    column_name     TEXT    NULL,                 -- image_path / receipt_image_path

    disk            TEXT    NOT NULL DEFAULT 'public',
    storage_path    TEXT    NULL,                 -- مسیر داخل دیسک: images/items/x.jpg
    relative_path   TEXT    NOT NULL,             -- media/items/12/x.jpg
    absolute_path   TEXT    NULL,

    original_name   TEXT    NULL,
    extension       TEXT    NULL,
    mime_type       TEXT    NULL,
    size_bytes      INTEGER NOT NULL DEFAULT 0,
    sha256          TEXT    NULL,

    direction       TEXT    NOT NULL DEFAULT 'export' CHECK (direction IN ('export', 'import')),
    status          TEXT    NOT NULL DEFAULT 'copied'
                    CHECK (status IN ('copied', 'skipped', 'duplicated', 'missing', 'failed', 'relinked')),
    error_message   TEXT    NULL,

    created_at      DATETIME NULL,
    updated_at      DATETIME NULL
);

CREATE TABLE IF NOT EXISTS backup_run_entities (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    backup_run_id   INTEGER NOT NULL REFERENCES backup_runs (id) ON UPDATE CASCADE ON DELETE CASCADE,

    entity_key      TEXT    NOT NULL,             -- items, invoices, ...
    table_name      TEXT    NOT NULL,
    group_name      TEXT    NULL,                 -- 20_sales, 30_services, ...
    display_name    TEXT    NULL,                 -- نام فارسی برای گزارش

    relative_path   TEXT    NULL,                 -- database/20_sales/invoices.csv
    absolute_path   TEXT    NULL,

    status          TEXT    NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending', 'running', 'completed', 'partial', 'failed', 'skipped')),

    row_count       INTEGER NOT NULL DEFAULT 0,   -- تعداد سطرهای فایل
    processed_rows  INTEGER NOT NULL DEFAULT 0,
    inserted_rows   INTEGER NOT NULL DEFAULT 0,
    updated_rows    INTEGER NOT NULL DEFAULT 0,
    skipped_rows    INTEGER NOT NULL DEFAULT 0,
    failed_rows     INTEGER NOT NULL DEFAULT 0,
    bytes           INTEGER NOT NULL DEFAULT 0,

    columns_json    TEXT    NULL,                 -- ستون‌های واقعی فایل
    checksum        TEXT    NULL,                 -- sha256 فایل CSV
    error_message   TEXT    NULL,
    meta_json       TEXT    NULL,

    started_at      DATETIME NULL,
    finished_at     DATETIME NULL,
    duration_ms     INTEGER  NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL
);

CREATE TABLE IF NOT EXISTS backup_run_events (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    backup_run_id   INTEGER NOT NULL REFERENCES backup_runs (id) ON UPDATE CASCADE ON DELETE CASCADE,
    level           TEXT    NOT NULL DEFAULT 'info'
                    CHECK (level IN ('debug', 'info', 'warning', 'error', 'critical')),
    code            TEXT    NULL,                 -- entity.started / file.missing / ...
    message         TEXT    NOT NULL,
    context_json    TEXT    NULL,
    created_at      DATETIME NULL
);

CREATE TABLE IF NOT EXISTS "backup_runs" (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid              TEXT    NOT NULL,

    direction         TEXT    NOT NULL CHECK (direction IN ('export', 'import')),
    mode              TEXT    NOT NULL DEFAULT 'full'
                      CHECK (mode IN ('full', 'database', 'media')),
    strategy          TEXT    NULL
                      CHECK (strategy IN ('merge', 'replace', 'skip_existing', 'fail_on_conflict', 'reindex')),
    status            TEXT    NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending', 'running', 'completed', 'partial', 'failed', 'canceled')),
    is_dry_run        INTEGER NOT NULL DEFAULT 0 CHECK (is_dry_run IN (0, 1)),
    is_auto           INTEGER NOT NULL DEFAULT 0 CHECK (is_auto IN (0, 1)),
    is_safety_copy    INTEGER NOT NULL DEFAULT 0 CHECK (is_safety_copy IN (0, 1)),

    label             TEXT    NULL,
    format            TEXT    NOT NULL DEFAULT 'csv' CHECK (format IN ('csv')),
    root_path         TEXT    NULL,
    run_path          TEXT    NULL,
    manifest_path     TEXT    NULL,
    archive_path      TEXT    NULL,

    options_json      TEXT    NULL,
    filters_json      TEXT    NULL,
    summary_json      TEXT    NULL,
    entities_json     TEXT    NULL,

    total_entities    INTEGER NOT NULL DEFAULT 0,
    total_rows        INTEGER NOT NULL DEFAULT 0,
    inserted_rows     INTEGER NOT NULL DEFAULT 0,
    updated_rows      INTEGER NOT NULL DEFAULT 0,
    skipped_rows      INTEGER NOT NULL DEFAULT 0,
    failed_rows       INTEGER NOT NULL DEFAULT 0,
    total_files       INTEGER NOT NULL DEFAULT 0,
    missing_files     INTEGER NOT NULL DEFAULT 0,
    total_bytes       INTEGER NOT NULL DEFAULT 0,

    checksum          TEXT    NULL,
    app_version       TEXT    NULL,
    schema_version    TEXT    NULL,
    db_driver         TEXT    NULL,
    hostname          TEXT    NULL,
    os_family         TEXT    NULL,

    started_at        DATETIME NULL,
    finished_at       DATETIME NULL,
    duration_ms       INTEGER  NULL,

    error_code        TEXT    NULL,
    error_message     TEXT    NULL,
    error_trace       TEXT    NULL,

    created_by        INTEGER NULL REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL,
    created_at        DATETIME NULL,
    updated_at        DATETIME NULL,
    deleted_at        DATETIME NULL
);

CREATE TABLE IF NOT EXISTS backup_settings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    shop_id       INTEGER NULL,
    "key"         TEXT    NOT NULL,
    "value"       TEXT    NULL,
    value_type    TEXT    NOT NULL DEFAULT 'string'
                  CHECK (value_type IN ('string', 'integer', 'boolean', 'json', 'path')),
    "group"       TEXT    NOT NULL DEFAULT 'general',
    is_locked     INTEGER NOT NULL DEFAULT 0 CHECK (is_locked IN (0, 1)),
    description   TEXT    NULL,
    updated_by    INTEGER NULL REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL
);

CREATE TABLE IF NOT EXISTS "cache_maintenance_runs" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    operation TEXT NOT NULL DEFAULT 'clear',
    status TEXT NOT NULL DEFAULT 'pending',
    is_dry_run INTEGER NOT NULL DEFAULT 0,
    targets_json TEXT DEFAULT NULL,
    options_json TEXT DEFAULT NULL,
    before_metrics_json TEXT DEFAULT NULL,
    after_metrics_json TEXT DEFAULT NULL,
    summary_json TEXT DEFAULT NULL,
    errors_json TEXT DEFAULT NULL,
    console_output TEXT DEFAULT NULL,
    user_id INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    started_at TEXT DEFAULT NULL,
    finished_at TEXT DEFAULT NULL,
    duration_ms INTEGER DEFAULT NULL,
    created_at TEXT DEFAULT NULL,
    updated_at TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "categories" ("id" integer primary key autoincrement not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime, default_tracks_stock TINYINT(1) NOT NULL DEFAULT 1);

CREATE TABLE IF NOT EXISTS "customers" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar, "phone" varchar, "address" text, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime);

CREATE TABLE IF NOT EXISTS "daily_item_stats" (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id              INTEGER NOT NULL,
    product_name         varchar,
    stat_date            date NOT NULL,
    opening_stock        INTEGER,
    closing_stock        INTEGER,
    sold_quantity        INTEGER NOT NULL DEFAULT '0',
    purchased_quantity   INTEGER NOT NULL DEFAULT '0',
    adjusted_in_quantity  INTEGER NOT NULL DEFAULT '0',
    adjusted_out_quantity INTEGER NOT NULL DEFAULT '0',
    revenue              numeric NOT NULL DEFAULT '0',
    total_cost           numeric NOT NULL DEFAULT '0',
    profit               numeric NOT NULL DEFAULT '0',
    created_at           datetime,
    updated_at           datetime,
    deleted_at           datetime,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS digital_menu_selections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    digital_menu_session_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY (digital_menu_session_id) REFERENCES digital_menu_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
);

CREATE TABLE IF NOT EXISTS digital_menu_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    code VARCHAR(4) NOT NULL,
    session_token VARCHAR(64) NULL,
    category_ids TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    entered_at DATETIME NULL,
    submitted_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "failed_jobs" (
    "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    "uuid" varchar NOT NULL UNIQUE,
    "connection" text NOT NULL,
    "queue" text NOT NULL,
    "payload" text NOT NULL,
    "exception" text NOT NULL,
    "failed_at" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "invoice_adjustments" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    type TEXT NOT NULL CHECK (
        type IN ('percentage', 'fixed')
    ),
    direction TEXT NOT NULL CHECK (
        direction IN ('increase', 'decrease')
    ),
    value DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, category_key TEXT NULL REFERENCES adjustment_categories(`key`), counts_as_revenue TINYINT(1) NOT NULL DEFAULT 1,

    FOREIGN KEY (invoice_id)
        REFERENCES invoices(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "invoices" (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id     INTEGER,
    customer_id    INTEGER,
    invoice_number varchar NOT NULL,
    total_amount   numeric NOT NULL,
    created_at     datetime,
    updated_at     datetime,
    deleted_at     datetime, payment_status varchar NOT NULL DEFAULT 'unpaid', payment_method varchar NULL, payment_terminal_mode varchar NULL, stock_deducted tinyint(1) NOT NULL DEFAULT 0, final_amount DECIMAL(15,2) NOT NULL DEFAULT 0, is_returned TINYINT(1) NOT NULL DEFAULT 0, returned_at DATETIME NULL, receipt_image_path TEXT NULL, paid_at DATETIME NULL,
    FOREIGN KEY (request_id)  REFERENCES requests(id)   ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id)  ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS item_serial_numbers (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id            INTEGER NOT NULL,
    serial_number      varchar,
    status             varchar NOT NULL DEFAULT 'in_stock',
    stock_movement_id  INTEGER,
    order_item_id      INTEGER,
    created_at         datetime,
    updated_at         datetime,
    FOREIGN KEY (item_id)            REFERENCES items(id)            ON DELETE CASCADE,
    FOREIGN KEY (stock_movement_id)  REFERENCES stock_movements(id)  ON DELETE SET NULL,
    FOREIGN KEY (order_item_id)      REFERENCES order_items(id)      ON DELETE SET NULL,
    UNIQUE (item_id, serial_number)
);

CREATE TABLE IF NOT EXISTS "items" (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        varchar NOT NULL,
    purchase_price       numeric NOT NULL,
    description TEXT,
    image_path  varchar,
    created_at  datetime,
    updated_at  datetime,
    deleted_at  datetime
, category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL, tracks_stock TINYINT(1) NOT NULL DEFAULT 1, sale_price NUMERIC NOT NULL DEFAULT 0, has_serial_number TINYINT(1) NOT NULL DEFAULT 0, has_warranty TINYINT(1) NOT NULL DEFAULT 0, is_consignment TINYINT(1) NOT NULL DEFAULT 0);

CREATE TABLE IF NOT EXISTS "job_batches" (
    "id" varchar NOT NULL PRIMARY KEY,
    "name" varchar NOT NULL,
    "total_jobs" integer NOT NULL,
    "pending_jobs" integer NOT NULL,
    "failed_jobs" integer NOT NULL,
    "failed_job_ids" text NOT NULL,
    "options" text,
    "cancelled_at" integer,
    "created_at" integer NOT NULL,
    "finished_at" integer
);

CREATE TABLE IF NOT EXISTS "jobs" (
    "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    "queue" varchar NOT NULL,
    "payload" text NOT NULL,
    "attempts" integer NOT NULL,
    "reserved_at" integer,
    "available_at" integer NOT NULL,
    "created_at" integer NOT NULL
);

CREATE TABLE IF NOT EXISTS license_module_usage (
    module TEXT PRIMARY KEY,
    hits_total INTEGER NOT NULL DEFAULT 0,
    hits_unreported INTEGER NOT NULL DEFAULT 0,
    first_used_at DATETIME,
    last_used_at DATETIME
);

CREATE TABLE IF NOT EXISTS license_state (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    fingerprint TEXT,
    license_uuid TEXT,
    token TEXT,
    status TEXT NOT NULL DEFAULT 'unactivated',
    activation_request_uuid TEXT,
    plan_code TEXT,
    entitlements TEXT,
    limits TEXT,
    reject_reason TEXT,
    lock_code TEXT,
    lock_reason TEXT,
    expires_at DATETIME,
    valid_until DATETIME,
    heartbeat_interval_minutes INTEGER NOT NULL DEFAULT 60,
    poll_after_seconds INTEGER NOT NULL DEFAULT 30,
    last_heartbeat_at DATETIME,
    last_heartbeat_ok INTEGER NOT NULL DEFAULT 0,
    updated_at DATETIME
, last_heartbeat_error TEXT NULL);

CREATE TABLE IF NOT EXISTS "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

CREATE TABLE IF NOT EXISTS "monthly_sales" (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    year                INTEGER NOT NULL,
    month               INTEGER NOT NULL,
    total_invoices      INTEGER NOT NULL DEFAULT '0',
    confirmed_invoices  INTEGER NOT NULL DEFAULT '0',
    total_revenue       numeric NOT NULL DEFAULT '0',
    products_revenue    numeric NOT NULL DEFAULT '0',
    services_revenue    numeric NOT NULL DEFAULT '0',
    total_cost          numeric NOT NULL DEFAULT '0',
    profit              numeric NOT NULL DEFAULT '0',
    unique_customers    INTEGER NOT NULL DEFAULT '0',
    new_customers       INTEGER NOT NULL DEFAULT '0',
    created_at          datetime,
    updated_at          datetime,
    deleted_at          datetime
);

CREATE TABLE IF NOT EXISTS "order_items" (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id   INTEGER NOT NULL,
    item_id      INTEGER,
    category_id  INTEGER NOT NULL,
    product_name varchar NOT NULL,
    quantity     INTEGER NOT NULL,
    price        numeric NOT NULL,
    total_price  numeric NOT NULL,
    image_path   varchar,
    created_at   datetime,
    updated_at   datetime,
    deleted_at   datetime, deduct_from_stock tinyint(1) NOT NULL DEFAULT 1, restocked_at DATETIME NULL, cost_price NUMERIC NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id)  REFERENCES invoices(id)    ON DELETE CASCADE,
    FOREIGN KEY (item_id)     REFERENCES items(id)       ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS "personal_access_tokens" ("id" integer primary key autoincrement not null, "tokenable_type" varchar not null, "tokenable_id" integer not null, "name" text not null, "token" varchar not null, "abilities" text, "last_used_at" datetime, "expires_at" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE IF NOT EXISTS "request_categories" (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    created_at datetime,
    updated_at datetime,
    FOREIGN KEY (request_id)  REFERENCES requests(id)   ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "requests" (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name varchar NOT NULL,
    description   TEXT NOT NULL,
    status        varchar NOT NULL,
    customer_id   INTEGER,
    created_at    datetime,
    updated_at    datetime,
    deleted_at    datetime,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS "service_job_items" ("id" integer primary key autoincrement not null, "service_job_id" integer not null, "item_id" integer not null, "quantity" integer not null default '1', "unit_price" numeric not null, "total_price" numeric not null, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime, cost_price NUMERIC NULL, foreign key("service_job_id") references "service_jobs"("id") on delete cascade on update cascade, foreign key("item_id") references "items"("id") on delete restrict on update cascade);

CREATE TABLE IF NOT EXISTS "service_job_service_types" (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    service_job_id   INTEGER NOT NULL,
    service_type_id  INTEGER NOT NULL,
    price            numeric,
    created_at       datetime,
    updated_at       datetime,
    FOREIGN KEY (service_job_id)  REFERENCES service_jobs(id)  ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "service_jobs" (
    id                            INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id                   INTEGER,
    request_id                    INTEGER,
    service_type_id               INTEGER,
    invoice_id                    INTEGER,
    device_type                   varchar,
    device_serial                 varchar,
    customer_problem_description  TEXT,
    diagnosis_description         TEXT,
    status                        varchar NOT NULL DEFAULT 'received',
    estimated_price               numeric,
    final_price                   numeric,
    received_at                   datetime,
    started_at                    datetime,
    completed_at                  datetime,
    delivered_at                  datetime,
    created_at                    datetime,
    updated_at                    datetime,
    deleted_at                    datetime,
    FOREIGN KEY (customer_id)     REFERENCES customers(id)     ON DELETE SET NULL,
    FOREIGN KEY (request_id)      REFERENCES requests(id)      ON DELETE SET NULL,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id)      REFERENCES invoices(id)      ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS "service_types" (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        varchar NOT NULL,
    platform    varchar,
    category    varchar,
    base_price  numeric,
    description TEXT,
    is_active   tinyint(1) NOT NULL DEFAULT '1',
    created_at  datetime,
    updated_at  datetime,
    deleted_at  datetime
);

CREATE TABLE IF NOT EXISTS "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

CREATE TABLE IF NOT EXISTS "setting_groups" (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        VARCHAR(50)  NOT NULL UNIQUE,          -- 'invoice', 'security', ...
    label       VARCHAR(100) NOT NULL,                 -- Persian label
    icon        VARCHAR(20)  NULL,
    description TEXT         NULL,
    sort_order  INTEGER      NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    [group] TEXT NOT NULL,
    [key] TEXT NOT NULL,
    [value] TEXT,
    [type] TEXT NOT NULL DEFAULT 'string',
    created_at TEXT,
    updated_at TEXT, updated_by INTEGER NULL,
    UNIQUE ([group], [key])
);

CREATE TABLE IF NOT EXISTS "stock_movements" (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id        INTEGER NOT NULL,
    invoice_id     INTEGER,
    service_job_id INTEGER,
    movement_type  varchar NOT NULL,
    quantity       INTEGER NOT NULL,
    unit_cost      numeric,
    reason         varchar,
    note           TEXT,
    created_at     datetime,
    updated_at     datetime,
    deleted_at     datetime, order_item_id INTEGER NULL REFERENCES order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id)        REFERENCES items(id)        ON DELETE RESTRICT,
    FOREIGN KEY (invoice_id)     REFERENCES invoices(id)     ON DELETE SET NULL,
    FOREIGN KEY (service_job_id) REFERENCES service_jobs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS "store_profiles" (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    legal_name         VARCHAR(255) NOT NULL,
    brand_name         VARCHAR(255) NULL,
    slug               VARCHAR(255) NOT NULL UNIQUE,
    phone              VARCHAR(50)  NULL,
    secondary_phone    VARCHAR(50)  NULL,
    email              VARCHAR(255) NULL,
    website            VARCHAR(255) NULL,
    instagram          VARCHAR(255) NULL,
    telegram           VARCHAR(255) NULL,
    address_street     VARCHAR(255) NULL,
    address_city       VARCHAR(100) NULL,
    address_province   VARCHAR(100) NULL,
    address_postal     VARCHAR(20)  NULL,
    address_country    VARCHAR(100) NOT NULL DEFAULT 'Iran',
    owner_first_name   VARCHAR(100) NULL,
    owner_last_name    VARCHAR(100) NULL,
    owner_national_id  VARCHAR(20)  NULL,
    owner_phone        VARCHAR(50)  NULL,
    owner_email        VARCHAR(255) NULL,
    logo_path          VARCHAR(500) NULL,
    cover_path         VARCHAR(500) NULL,
    is_primary         BOOLEAN      NOT NULL DEFAULT 0,
    status             VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime, username varchar);

CREATE TABLE IF NOT EXISTS warranties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_serial_number_id INTEGER NULL,
    order_item_id INTEGER NULL,
    warranty_provider_id INTEGER NULL,
    duration_value INTEGER NOT NULL,
    duration_unit VARCHAR(10) NOT NULL DEFAULT 'month',
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    FOREIGN KEY (item_serial_number_id) REFERENCES item_serial_numbers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (warranty_provider_id) REFERENCES warranty_providers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS warranty_providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    address TEXT NULL,
    description TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS archived_records_unique_active_source
    ON archived_records (source_type, source_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS backup_files_model_index        ON backup_files (model_type, model_id);

CREATE INDEX IF NOT EXISTS backup_files_run_status_index   ON backup_files (backup_run_id, status);

CREATE INDEX IF NOT EXISTS backup_files_sha256_index       ON backup_files (sha256);

CREATE INDEX IF NOT EXISTS backup_files_storage_path_index ON backup_files (storage_path);

CREATE UNIQUE INDEX IF NOT EXISTS backup_run_entities_run_entity_unique
    ON backup_run_entities (backup_run_id, entity_key);

CREATE INDEX IF NOT EXISTS backup_run_entities_status_index
    ON backup_run_entities (status);

CREATE INDEX IF NOT EXISTS backup_run_events_created_index   ON backup_run_events (created_at);

CREATE INDEX IF NOT EXISTS backup_run_events_run_level_index ON backup_run_events (backup_run_id, level);

CREATE INDEX IF NOT EXISTS backup_runs_deleted_at_index ON backup_runs (deleted_at);

CREATE INDEX IF NOT EXISTS backup_runs_direction_status_index ON backup_runs (direction, status);

CREATE INDEX IF NOT EXISTS backup_runs_started_at_index ON backup_runs (started_at);

CREATE UNIQUE INDEX IF NOT EXISTS backup_runs_uuid_unique ON backup_runs (uuid);

CREATE INDEX IF NOT EXISTS backup_settings_group_index
    ON backup_settings ("group");

CREATE UNIQUE INDEX IF NOT EXISTS backup_settings_scope_key_unique
    ON backup_settings (COALESCE(shop_id, 0), "key");

CREATE UNIQUE INDEX IF NOT EXISTS "customers_email_unique" on "customers" ("email");

CREATE INDEX IF NOT EXISTS idx_app_settings_autoload ON app_settings (is_autoload);

CREATE INDEX IF NOT EXISTS idx_app_settings_group   ON app_settings (group_id);

CREATE INDEX IF NOT EXISTS idx_app_settings_locked  ON app_settings (is_locked);

CREATE INDEX IF NOT EXISTS idx_archive_actions_action ON archive_actions (action);

CREATE INDEX IF NOT EXISTS idx_archive_actions_archived_record ON archive_actions (archived_record_id);

CREATE INDEX IF NOT EXISTS idx_archive_actions_source ON archive_actions (source_type, source_id);

CREATE INDEX IF NOT EXISTS idx_archived_at ON archived_records (archived_at);

CREATE INDEX IF NOT EXISTS idx_archived_search ON archived_records (invoice_number, customer_name, title);

CREATE INDEX IF NOT EXISTS idx_archived_source ON archived_records (source_type, source_id);

CREATE INDEX IF NOT EXISTS idx_archived_status ON archived_records (archive_status);

CREATE INDEX IF NOT EXISTS idx_audit_log_batches_status ON audit_log_batches (status);

CREATE INDEX IF NOT EXISTS idx_audit_log_batches_status_created_at ON audit_log_batches (status, created_at);

CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs (action);

CREATE INDEX IF NOT EXISTS idx_audit_logs_actor_id ON audit_logs (actor_id);

CREATE INDEX IF NOT EXISTS idx_audit_logs_batch_uuid ON audit_logs (batch_uuid);

CREATE INDEX IF NOT EXISTS idx_audit_logs_channel ON audit_logs (channel);

CREATE INDEX IF NOT EXISTS idx_audit_logs_channel_occurred_at ON audit_logs (channel, occurred_at);

CREATE INDEX IF NOT EXISTS idx_audit_logs_correlation_id ON audit_logs (correlation_id);

CREATE INDEX IF NOT EXISTS idx_audit_logs_entity_type ON audit_logs (entity_type);

CREATE INDEX IF NOT EXISTS idx_audit_logs_entity_type_entity_id ON audit_logs (entity_type, entity_id);

CREATE INDEX IF NOT EXISTS idx_audit_logs_level ON audit_logs (level);

CREATE INDEX IF NOT EXISTS idx_audit_logs_next_attempt_at ON audit_logs (next_attempt_at);

CREATE INDEX IF NOT EXISTS idx_audit_logs_occurred_at ON audit_logs (occurred_at);

CREATE INDEX IF NOT EXISTS idx_audit_logs_request_id ON audit_logs (request_id);

CREATE INDEX IF NOT EXISTS idx_audit_logs_sequence ON audit_logs (sequence);

CREATE INDEX IF NOT EXISTS idx_audit_logs_sync_status ON audit_logs (sync_status);

CREATE INDEX IF NOT EXISTS idx_audit_logs_sync_status_next_attempt_at ON audit_logs (sync_status, next_attempt_at);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_dry_run ON cache_maintenance_runs(is_dry_run);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_finished_at ON cache_maintenance_runs(finished_at);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_operation ON cache_maintenance_runs(operation);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_operation_status ON cache_maintenance_runs(operation, status);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_started_at ON cache_maintenance_runs(started_at);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_status ON cache_maintenance_runs(status);

CREATE INDEX IF NOT EXISTS idx_cache_maintenance_runs_user_created ON cache_maintenance_runs(user_id, created_at);

CREATE INDEX IF NOT EXISTS idx_daily_item_stats_date       ON daily_item_stats(stat_date);

CREATE INDEX IF NOT EXISTS idx_dms_code_status ON digital_menu_sessions(code, status);

CREATE INDEX IF NOT EXISTS idx_dms_invoice ON digital_menu_sessions(invoice_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_dms_token ON digital_menu_sessions(session_token);

CREATE INDEX IF NOT EXISTS idx_dmsel_session ON digital_menu_selections(digital_menu_session_id);

CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices(customer_id);

CREATE INDEX IF NOT EXISTS idx_invoices_request  ON invoices(request_id);

CREATE INDEX IF NOT EXISTS idx_item_serial_numbers_item_status ON item_serial_numbers(item_id, status);

CREATE INDEX IF NOT EXISTS idx_order_items_invoice         ON order_items(invoice_id);

CREATE INDEX IF NOT EXISTS idx_order_items_item            ON order_items(item_id);

CREATE INDEX IF NOT EXISTS idx_request_categories_category ON request_categories(category_id);

CREATE INDEX IF NOT EXISTS idx_request_categories_request  ON request_categories(request_id);

CREATE INDEX IF NOT EXISTS idx_service_job_service_types_job ON service_job_service_types(service_job_id);

CREATE INDEX IF NOT EXISTS idx_service_jobs_customer       ON service_jobs(customer_id);

CREATE INDEX IF NOT EXISTS idx_setting_groups_sort ON setting_groups (sort_order);

CREATE INDEX IF NOT EXISTS idx_stock_movements_item        ON stock_movements(item_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username);

CREATE INDEX IF NOT EXISTS idx_warranties_deleted_at ON warranties(deleted_at);

CREATE UNIQUE INDEX IF NOT EXISTS idx_warranties_one_active_per_order_item
    ON warranties(order_item_id) WHERE deleted_at IS NULL AND order_item_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_warranties_one_active_per_serial
    ON warranties(item_serial_number_id) WHERE deleted_at IS NULL AND item_serial_number_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_warranties_order_item_id ON warranties(order_item_id);

CREATE INDEX IF NOT EXISTS idx_warranties_serial_id ON warranties(item_serial_number_id);

CREATE INDEX IF NOT EXISTS "personal_access_tokens_expires_at_index" on "personal_access_tokens" ("expires_at");

CREATE UNIQUE INDEX IF NOT EXISTS "personal_access_tokens_token_unique" on "personal_access_tokens" ("token");

CREATE INDEX IF NOT EXISTS "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens" ("tokenable_type", "tokenable_id");

CREATE INDEX IF NOT EXISTS "service_job_items_service_job_id_item_id_index" on "service_job_items" ("service_job_id", "item_id");

CREATE INDEX IF NOT EXISTS "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX IF NOT EXISTS "sessions_user_id_index" on "sessions" ("user_id");

CREATE UNIQUE INDEX IF NOT EXISTS "users_email_unique" on "users" ("email");

