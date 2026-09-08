/* ==========================================================================
 * GameStore · Cache Maintenance API Layer
 * --------------------------------------------------------------------------
 * مسیر فایل: resources/js/Composables/useCacheMaintenanceApi.js
 *
 * این composable دقیقاً مطابق روت‌های ماژول CacheMaintenance نوشته شده است:
 *   Modules/CacheMaintenance/routes/web.php
 *   prefix = «settings/cache» ، middleware = «auth»
 *
 *   GET    /settings/cache/overview      → overview  (inspect)
 *   GET    /settings/cache/targets       → targets   (availableTargets)
 *   POST   /settings/cache/clear         → clear     (dry-run / real)
 *   POST   /settings/cache/optimize      → optimize  (warm-up)
 *   GET    /settings/cache/runs          → index     (paginated history)
 *   GET    /settings/cache/runs/{runId}  → show
 *
 * همهٔ پاسخ‌ها ساختار { success, message?, data } دارند (مطابق CacheMaintenanceController)
 * و از axios سراسری لاراول (مدیریت CSRF + session) استفاده می‌شود.
 * ========================================================================== */

import axios from 'axios'

const BASE = '/settings/cache'

/* --------------------------------------------------------------------------
 * ثابت‌های عملیات / وضعیت / targetها — منطبق با مدل CacheMaintenanceRun
 * -------------------------------------------------------------------------- */
export const OPERATION = {
  CLEAR: 'clear',
  OPTIMIZE: 'optimize',
  INSPECT: 'inspect',
}

export const RUN_STATUS = {
  PENDING: 'pending',
  RUNNING: 'running',
  COMPLETED: 'completed',
  PARTIAL: 'partial',
  FAILED: 'failed',
}

/** متادیتای نمایشی وضعیت اجراها (برای pill های رنگی) */
export const STATUS_META = {
  [RUN_STATUS.PENDING]: { label: 'در انتظار', icon: '⏳', className: 'cm-pill cm-pill--warn' },
  [RUN_STATUS.RUNNING]: { label: 'در حال اجرا', icon: '⚡', className: 'cm-pill cm-pill--info' },
  [RUN_STATUS.COMPLETED]: { label: 'موفق', icon: '✓', className: 'cm-pill cm-pill--ok' },
  [RUN_STATUS.PARTIAL]: { label: 'نیمه‌موفق', icon: '◒', className: 'cm-pill cm-pill--warn' },
  [RUN_STATUS.FAILED]: { label: 'ناموفق', icon: '✕', className: 'cm-pill cm-pill--danger' },
}

export const OPERATION_META = {
  [OPERATION.CLEAR]: { label: 'پاکسازی', icon: '🧹', color: 'var(--gs-gold)' },
  [OPERATION.OPTIMIZE]: { label: 'بهینه‌سازی', icon: '⚡', color: 'var(--gs-accent)' },
  [OPERATION.INSPECT]: { label: 'بازبینی', icon: '🔍', color: 'var(--gs-accent-2)' },
}

/**
 * کلید targetهای قابل پاکسازی — دقیقاً همان Constهای CacheMaintenanceService.
 * از این لیست برای ساخت چک‌لیست در فرانت استفاده می‌شود؛ label و description
 * نهایی از GET /targets می‌آید و این‌جا فقط کلید و ایمنی معتبرند.
 */
export const TARGETS = {
  APP: 'app',
  SETTINGS: 'settings',
  CONFIG: 'config',
  ROUTE: 'route',
  VIEW: 'view',
  EVENT: 'event',
  COMPILED: 'compiled',
  OPTIMIZE: 'optimize',
  BOOTSTRAP: 'bootstrap',
  FRAMEWORK_FILES: 'framework_files',
  EXPIRED_DATABASE_CACHE: 'expired_database_cache',
  LOGS: 'logs',
  SESSIONS: 'sessions',
  ORPHAN_MEDIA: 'orphan_media',
  OLD_BACKUPS: 'old_backups',
  ALL: 'all',
}

/** تقدم پیشنهادی نمایش targetها در چک‌لیست */
export const TARGET_ORDER = [
  TARGETS.APP,
  TARGETS.SETTINGS,
  TARGETS.CONFIG,
  TARGETS.ROUTE,
  TARGETS.VIEW,
  TARGETS.EVENT,
  TARGETS.COMPILED,
  TARGETS.OPTIMIZE,
  TARGETS.BOOTSTRAP,
  TARGETS.FRAMEWORK_FILES,
  TARGETS.EXPIRED_DATABASE_CACHE,
  TARGETS.LOGS,
  TARGETS.SESSIONS,
  TARGETS.ORPHAN_MEDIA,
  TARGETS.OLD_BACKUPS,
]

/** متادیتای UI برای targetها (آیکون/رنگ) — وقتی بک‌اند label نداشت این‌جا fallback می‌شود */
export const TARGET_UI = {
  [TARGETS.APP]: { icon: '🗂', color: 'var(--gs-gold)' },
  [TARGETS.SETTINGS]: { icon: '⚙️', color: 'var(--gs-accent-3)' },
  [TARGETS.CONFIG]: { icon: '🧩', color: 'var(--gs-accent)' },
  [TARGETS.ROUTE]: { icon: '🛣', color: 'var(--gs-accent)' },
  [TARGETS.VIEW]: { icon: '👁', color: 'var(--gs-accent-2)' },
  [TARGETS.EVENT]: { icon: '📡', color: 'var(--gs-accent-2)' },
  [TARGETS.COMPILED]: { icon: '🧱', color: 'var(--gs-accent-3)' },
  [TARGETS.OPTIMIZE]: { icon: '🚀', color: 'var(--gs-gold)' },
  [TARGETS.BOOTSTRAP]: { icon: '📦', color: 'var(--gs-accent)' },
  [TARGETS.FRAMEWORK_FILES]: { icon: '🗃', color: 'var(--gs-accent)' },
  [TARGETS.EXPIRED_DATABASE_CACHE]: { icon: '🧬', color: 'var(--gs-accent-2)' },
  [TARGETS.LOGS]: { icon: '📜', color: 'var(--gs-warning)' },
  [TARGETS.SESSIONS]: { icon: '🔑', color: 'var(--gs-warning)' },
  [TARGETS.ORPHAN_MEDIA]: { icon: '🖼', color: 'var(--gs-error)' },
  [TARGETS.OLD_BACKUPS]: { icon: '🗄', color: 'var(--gs-warning)' },
  [TARGETS.ALL]: { icon: '✨', color: 'var(--gs-gold)' },
}

/** ابزار کوچک برای یکدست‌سازی خطاها */
function normalizeError(error) {
  const res = error?.response
  return {
    ok: false,
    status: res?.status ?? 0,
    message: res?.data?.message || error?.message || 'ارتباط با سرور برقرار نشد.',
    errors: res?.data?.errors ?? null,
    raw: error,
  }
}

export function useCacheMaintenanceApi() {
  /* ------------------------------------------------------------------ */
  /* خواندن                                                              */
  /* ------------------------------------------------------------------ */

  /** وضعیت فعلی کش‌ها + متریک‌ها + پیشنهادها (inspect) */
  async function overview() {
    try {
      const { data } = await axios.get(`${BASE}/overview`)
      return data // { success, data: { run, metrics, targets, recommendations } }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** لیست targetهای قابل پاکسازی */
  async function targets() {
    try {
      const { data } = await axios.get(`${BASE}/targets`)
      return data // { success, data: { [target]: { label, description, safe } } }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** تاریخچهٔ اجراها (صفحه‌بندی‌شده) */
  async function runs(filters = {}) {
    try {
      const { data } = await axios.get(`${BASE}/runs`, { params: filters })
      return data // { success, data: paginator }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** جزئیات یک اجرا */
  async function run(runId) {
    try {
      const { data } = await axios.get(`${BASE}/runs/${runId}`)
      return data // { success, data: run }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /* ------------------------------------------------------------------ */
  /* نوشتن / اجرا                                                         */
  /* ------------------------------------------------------------------ */

  /**
   * اجرای پاکسازی یا dry-run.
   * @param {object} payload  مطابق CacheMaintenanceRequest (targets, dry_run, ...)
   */
  async function clear(payload = {}) {
    try {
      const { data } = await axios.post(`${BASE}/clear`, payload)
      return data // { success, message, data: run }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /**
   * بهینه‌سازی / گرم‌سازی کش (بدون پاکسازی).
   * @param {object} payload  { dry_run, warm_config, warm_views, warm_settings }
   */
  async function optimize(payload = {}) {
    try {
      const { data } = await axios.post(`${BASE}/optimize`, payload)
      return data // { success, message, data: run }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  return {
    overview,
    targets,
    runs,
    run,
    clear,
    optimize,
  }
}

export default useCacheMaintenanceApi
