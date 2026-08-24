/* global route */
/* ==========================================================================
 * GameStore · Backup API Layer
 * --------------------------------------------------------------------------
 * مسیر پیشنهادی: resources/js/Composables/useBackupApi.js
 * این composable تمام ارتباطات صفحه‌ی بکاپ با بک‌اند Laravel را متمرکز می‌کند.
 * ========================================================================== */

import { computed, reactive, ref } from 'vue'
import axios from 'axios'

export const BACKUP_MODES = {
  FULL: 'full',
  DATABASE: 'database',
  MEDIA: 'media',
}

export const IMPORT_STRATEGIES = {
  MERGE: 'merge',
  REPLACE: 'replace',
  SKIP_EXISTING: 'skip_existing',
  FAIL_ON_CONFLICT: 'fail_on_conflict',
  REINDEX: 'reindex',
}

export const RUN_STATUS = {
  PENDING: 'pending',
  RUNNING: 'running',
  COMPLETED: 'completed',
  PARTIAL: 'partial',
  FAILED: 'failed',
  CANCELED: 'canceled',
}

export const MODE_META = {
  [BACKUP_MODES.FULL]: { label: 'کامل', icon: '👑', hint: 'دیتابیس CSV + تصاویر', color: 'var(--gs-gold)' },
  [BACKUP_MODES.DATABASE]: { label: 'دیتابیس', icon: '🧬', hint: 'فقط فایل‌های CSV', color: 'var(--gs-accent)' },
  [BACKUP_MODES.MEDIA]: { label: 'تصاویر', icon: '🖼', hint: 'فقط فایل‌های تصویری', color: 'var(--gs-accent-2)' },
}

export const STRATEGY_META = {
  [IMPORT_STRATEGIES.MERGE]: { label: 'ادغام هوشمند', icon: '🧩', hint: 'رکوردهای موجود آپدیت، جدیدها درج می‌شوند' },
  [IMPORT_STRATEGIES.REPLACE]: { label: 'جایگزینی کامل', icon: '♻️', hint: 'داده‌ی قبلی حذف و از بسته بازسازی می‌شود' },
  [IMPORT_STRATEGIES.SKIP_EXISTING]: { label: 'رد تکراری‌ها', icon: '⏭', hint: 'فقط رکوردهای جدید درج می‌شوند' },
  [IMPORT_STRATEGIES.FAIL_ON_CONFLICT]: { label: 'توقف روی تداخل', icon: '🛡', hint: 'هر تداخل باعث rollback کامل می‌شود' },
  [IMPORT_STRATEGIES.REINDEX]: { label: 'بازشماره‌گذاری تاریخی', icon: '📅', hint: 'ادغام بر اساس تاریخ ثبت؛ قدیمی‌ها ID پایین‌تر، جدیدها بالاتر' },
}

export const STATUS_META = {
  pending: { label: 'در انتظار', icon: '⏳', className: 'backup-pill backup-pill--warn' },
  running: { label: 'در حال اجرا', icon: '⚡', className: 'backup-pill backup-pill--info' },
  completed: { label: 'موفق', icon: '✓', className: 'backup-pill backup-pill--ok' },
  partial: { label: 'نیمه‌موفق', icon: '◒', className: 'backup-pill backup-pill--warn' },
  failed: { label: 'ناموفق', icon: '✕', className: 'backup-pill backup-pill--danger' },
  canceled: { label: 'لغو شده', icon: '⊘', className: 'backup-pill' },
}

export const DIRECTION_META = {
  export: { label: 'خروجی', icon: '⬆', color: 'var(--gs-gold)' },
  import: { label: 'ورودی', icon: '⬇', color: 'var(--gs-accent)' },
}

const FALLBACKS = {
  'backups.overview': '/backups/overview',
  'backups.entities': '/backups/entities',
  'backups.export.validate-path': '/backups/export/validate-path',
  'backups.export.run': '/backups/export',
  'backups.export.database': '/backups/export/database',
  'backups.export.media': '/backups/export/media',
  'backups.import.inspect': '/backups/import/inspect',
  'backups.import.dry-run': '/backups/import/dry-run',
  'backups.import.run': '/backups/import',
  'backups.import.upload': '/backups/import/upload',
  'backups.index': '/backups',
  'backups.settings.show': '/backups/settings',
  'backups.settings.update': '/backups/settings',
}

function routeUrl(name, params = undefined) {
  if (typeof route === 'function') {
    try {
      const router = route()
      if (!router?.has || router.has(name)) {
        return route(name, params)
      }
    } catch (e) {
      try { return route(name, params) } catch (_) { /* fallback */ }
    }
  }

  if (name === 'backups.show') return `/backups/${params}`
  if (name === 'backups.files') return `/backups/${params}/files`
  if (name === 'backups.log') return `/backups/${params}/log`
  if (name === 'backups.destroy') return `/backups/${params}`
  if (name === 'backups.export.entity-csv') return `/backups/export/entity/${params}.csv`

  return FALLBACKS[name] ?? '#'
}

function httpClient() {
  const client = (typeof window !== 'undefined' && window.axios) ? window.axios : axios

  client.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
  client.defaults.headers.common.Accept = 'application/json'

  if (typeof document !== 'undefined') {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    if (token) client.defaults.headers.common['X-CSRF-TOKEN'] = token
  }

  return client
}

export function readableError(error, fallback = 'خطای غیرمنتظره‌ای رخ داد.') {
  const data = error?.response?.data
  if (!data) return error?.message || fallback
  if (data.message) return data.message
  if (data.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) return first[0]
  }
  return fallback
}

export function faNumber(value) {
  if (value === null || value === undefined || value === '') return '—'
  return new Intl.NumberFormat('fa-IR').format(Number(value) || 0)
}

export function formatBytes(bytes) {
  const value = Number(bytes || 0)
  if (value <= 0) return '۰ بایت'
  const units = ['بایت', 'KB', 'MB', 'GB', 'TB']
  const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1)
  const amount = value / Math.pow(1024, index)
  return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: index ? 1 : 0 }).format(amount)} ${units[index]}`
}

export function formatDateTime(value) {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('fa-IR', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch (e) {
    return String(value)
  }
}

export function presentStatus(status) {
  return STATUS_META[status] ?? { label: status || '—', icon: '•', className: 'backup-pill' }
}

export function presentMode(mode) {
  return MODE_META[mode] ?? { label: mode || '—', icon: '◇', hint: '' }
}

export function presentDirection(direction) {
  return DIRECTION_META[direction] ?? { label: direction || '—', icon: '↔' }
}

function cleanPayload(payload) {
  const out = {}
  Object.entries(payload || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    if (Array.isArray(value) && value.length === 0) return
    out[key] = value
  })
  return out
}

export function useBackupApi() {
  const http = httpClient()

  const overview = ref(null)
  const entities = ref([])
  const runs = ref([])
  const selectedRun = ref(null)
  const selectedFiles = ref(null)
  const importInspection = ref(null)

  const loading = reactive({
    overview: false,
    entities: false,
    runs: false,
    run: false,
    files: false,
    inspect: false,
    export: false,
    import: false,
    settings: false,
    validate: false,
  })

  const error = ref(null)
  const busyId = ref(null)

  const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  })

  const runFilters = reactive({
    direction: '',
    status: '',
    mode: '',
    include_auto: true,
    per_page: 10,
  })

  const statistics = computed(() => overview.value?.statistics ?? {})
  const settings = computed(() => overview.value?.settings ?? {})
  const defaults = computed(() => overview.value?.defaults ?? {})
  const groups = computed(() => overview.value?.groups ?? {})

  async function fetchOverview() {
    loading.overview = true
    error.value = null
    try {
      const { data } = await http.get(routeUrl('backups.overview'))
      overview.value = data?.data ?? data ?? {}
      entities.value = Array.isArray(overview.value?.entities) ? overview.value.entities : []
      return overview.value
    } catch (e) {
      error.value = readableError(e, 'دریافت اطلاعات مرکز پشتیبان‌گیری ناموفق بود.')
      throw e
    } finally {
      loading.overview = false
    }
  }

  async function fetchEntities() {
    loading.entities = true
    try {
      const { data } = await http.get(routeUrl('backups.entities'))
      entities.value = Array.isArray(data?.data) ? data.data : []
      return entities.value
    } catch (e) {
      throw new Error(readableError(e, 'دریافت لیست موجودیت‌ها ناموفق بود.'))
    } finally {
      loading.entities = false
    }
  }

  async function validateDestination(path) {
    loading.validate = true
    busyId.value = 'validate-path'
    try {
      const { data } = await http.post(routeUrl('backups.export.validate-path'), { path })
      return data?.data ?? data
    } catch (e) {
      throw new Error(readableError(e, 'مسیر انتخاب‌شده معتبر نیست.'))
    } finally {
      loading.validate = false
      busyId.value = null
    }
  }

  async function exportBackup(payload) {
    loading.export = true
    busyId.value = 'export'
    try {
      const { data } = await http.post(routeUrl('backups.export.run'), cleanPayload(payload))
      await fetchRuns(1)
      await fetchOverview()
      return data
    } catch (e) {
      throw new Error(readableError(e, 'گرفتن خروجی با خطا مواجه شد.'))
    } finally {
      loading.export = false
      busyId.value = null
    }
  }

  async function inspectImport(payload) {
    loading.inspect = true
    busyId.value = 'inspect'
    try {
      const { data } = await http.post(routeUrl('backups.import.inspect'), cleanPayload(payload))
      importInspection.value = data?.data ?? data
      return importInspection.value
    } catch (e) {
      throw new Error(readableError(e, 'بررسی بسته‌ی ورودی ناموفق بود.'))
    } finally {
      loading.inspect = false
      busyId.value = null
    }
  }

  async function dryRunImport(payload) {
    loading.import = true
    busyId.value = 'dry-run'
    try {
      const { data } = await http.post(routeUrl('backups.import.dry-run'), cleanPayload({ ...payload, dry_run: true }))
      await fetchRuns(1)
      return data
    } catch (e) {
      throw new Error(readableError(e, 'اجرای آزمایشی ناموفق بود.'))
    } finally {
      loading.import = false
      busyId.value = null
    }
  }

  async function importBackup(payload) {
    loading.import = true
    busyId.value = 'import'
    try {
      const { data } = await http.post(routeUrl('backups.import.run'), cleanPayload(payload))
      await fetchRuns(1)
      await fetchOverview()
      return data
    } catch (e) {
      throw new Error(readableError(e, 'بازیابی اطلاعات ناموفق بود.'))
    } finally {
      loading.import = false
      busyId.value = null
    }
  }

  async function fetchRuns(page = 1) {
    loading.runs = true
    try {
      const { data } = await http.get(routeUrl('backups.index'), {
        params: cleanPayload({ ...runFilters, page }),
      })
      const paginator = data?.data ?? data ?? {}
      runs.value = Array.isArray(paginator.data) ? paginator.data : []
      pagination.current_page = paginator.current_page ?? 1
      pagination.last_page = paginator.last_page ?? 1
      pagination.per_page = paginator.per_page ?? runFilters.per_page
      pagination.total = paginator.total ?? runs.value.length
      return runs.value
    } catch (e) {
      throw new Error(readableError(e, 'دریافت تاریخچه‌ی بکاپ ناموفق بود.'))
    } finally {
      loading.runs = false
    }
  }

  async function fetchRun(runId) {
    loading.run = true
    busyId.value = `run-${runId}`
    try {
      const { data } = await http.get(routeUrl('backups.show', runId))
      selectedRun.value = data?.data ?? data
      return selectedRun.value
    } catch (e) {
      throw new Error(readableError(e, 'دریافت جزئیات اجرا ناموفق بود.'))
    } finally {
      loading.run = false
      busyId.value = null
    }
  }

  async function fetchFiles(runId, params = {}) {
    loading.files = true
    try {
      const { data } = await http.get(routeUrl('backups.files', runId), { params: cleanPayload(params) })
      selectedFiles.value = data?.data ?? data
      return selectedFiles.value
    } catch (e) {
      throw new Error(readableError(e, 'دریافت فایل‌های اجرا ناموفق بود.'))
    } finally {
      loading.files = false
    }
  }

  async function deleteRun(runId, deleteFiles = false) {
    busyId.value = `delete-${runId}`
    try {
      const { data } = await http.delete(routeUrl('backups.destroy', runId), { data: { delete_files: deleteFiles } })
      await fetchRuns(pagination.current_page)
      await fetchOverview()
      return data
    } catch (e) {
      throw new Error(readableError(e, 'حذف رکورد بکاپ ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  async function updateSettings(payload) {
    loading.settings = true
    busyId.value = 'settings'
    try {
      const { data } = await http.put(routeUrl('backups.settings.update'), cleanPayload(payload))
      await fetchOverview()
      return data
    } catch (e) {
      throw new Error(readableError(e, 'ذخیره تنظیمات ناموفق بود.'))
    } finally {
      loading.settings = false
      busyId.value = null
    }
  }

  function downloadLog(runId) {
    window.location.href = routeUrl('backups.log', runId)
  }

  function downloadEntityCsv(entityKey) {
    window.location.href = routeUrl('backups.export.entity-csv', entityKey)
  }

  return {
    overview,
    entities,
    runs,
    selectedRun,
    selectedFiles,
    importInspection,
    statistics,
    settings,
    defaults,
    groups,
    loading,
    busyId,
    error,
    pagination,
    runFilters,
    fetchOverview,
    fetchEntities,
    validateDestination,
    exportBackup,
    inspectImport,
    dryRunImport,
    importBackup,
    fetchRuns,
    fetchRun,
    fetchFiles,
    deleteRun,
    updateSettings,
    downloadLog,
    downloadEntityCsv,
  }
}
