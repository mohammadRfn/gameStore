/* global route */
/* ==========================================================================
 * GameStore · Backup Center API Layer
 * --------------------------------------------------------------------------
 * مسیر: resources/js/Composables/useBackupCenter.js
 *
 * این لایه دقیقاً مطابق قرارداد BackupController نوشته شده و هیچ وابستگی‌ای
 * به فایل‌های قبلی ندارد؛ بنابراین چیزی از ماژول قبلی خراب نمی‌شود.
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

/* برچسب‌ها همگی فارسی هستند؛ هیچ نام جدول یا اصطلاح فنی نمایش داده نمی‌شود. */
export const MODE_META = {
  full: { label: 'بسته کامل', icon: '👑', hint: 'اطلاعات و تصاویر با هم' },
  database: { label: 'فقط اطلاعات', icon: '🧾', hint: 'بدون فایل‌های تصویری' },
  media: { label: 'فقط تصاویر', icon: '🖼', hint: 'تصاویر کالاها و رسیدها' },
}

export const STRATEGY_META = {
  merge: { label: 'ادغام هوشمند', icon: '🧩', hint: 'موارد موجود به‌روز و موارد تازه افزوده می‌شوند' },
  replace: { label: 'جایگزینی کامل', icon: '♻️', hint: 'اطلاعات فعلی پاک و از روی بسته بازسازی می‌شود' },
  skip_existing: { label: 'رد کردن تکراری‌ها', icon: '⏭', hint: 'فقط موارد جدید افزوده می‌شوند' },
  fail_on_conflict: { label: 'توقف روی تداخل', icon: '🛡', hint: 'با اولین تداخل، همه‌چیز به حالت قبل برمی‌گردد' },
  reindex: { label: 'مرتب‌سازی بر پایه تاریخ', icon: '📅', hint: 'ادغام بر اساس تاریخ ثبت؛ قدیمی‌ها اول' },
}

export const STATUS_META = {
  pending: { label: 'در انتظار', icon: '⏳', tone: 'amber' },
  running: { label: 'در حال اجرا', icon: '⚡', tone: 'sky' },
  completed: { label: 'موفق', icon: '✓', tone: 'emerald' },
  partial: { label: 'نیمه‌موفق', icon: '◒', tone: 'amber' },
  failed: { label: 'ناموفق', icon: '✕', tone: 'rose' },
  canceled: { label: 'لغو شده', icon: '⊘', tone: 'slate' },
}

export const DIRECTION_META = {
  export: { label: 'خروجی', icon: '⬆' },
  import: { label: 'ورودی', icon: '⬇' },
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

export function routeUrl(name, params = undefined) {
  if (typeof route === 'function') {
    try {
      const router = route()
      if (!router?.has || router.has(name)) return route(name, params)
    } catch (e) {
      try { return route(name, params) } catch (_) { /* fallback */ }
    }
  }

  if (name === 'backups.show') return '/backups/' + params
  if (name === 'backups.files') return '/backups/' + params + '/files'
  if (name === 'backups.log') return '/backups/' + params + '/log'
  if (name === 'backups.destroy') return '/backups/' + params

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
  const units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت']
  const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1)
  const amount = value / Math.pow(1024, index)
  return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: index ? 1 : 0 }).format(amount) + ' ' + units[index]
}

export function formatDateTime(value) {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
  } catch (e) {
    return String(value)
  }
}

export function presentStatus(status) {
  return STATUS_META[status] ?? { label: status || '—', icon: '•', tone: 'slate' }
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

export function useBackupCenter() {
  const http = httpClient()

  const overview = ref(null)
  const entities = ref([])
  const runs = ref([])
  const selectedRun = ref(null)
  const inspection = ref(null)
  const lastResult = ref(null)
  const error = ref(null)

  const loading = reactive({
    overview: false,
    runs: false,
    run: false,
    validate: false,
    inspect: false,
    export: false,
    import: false,
    settings: false,
  })

  const pagination = reactive({ current_page: 1, last_page: 1, per_page: 10, total: 0 })

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
    const { data } = await http.get(routeUrl('backups.entities'))
    entities.value = data?.data ?? []
    return entities.value
  }

  async function fetchRuns(page = 1) {
    loading.runs = true
    try {
      const { data } = await http.get(routeUrl('backups.index'), {
        params: { ...cleanPayload({ ...runFilters, include_auto: runFilters.include_auto ? 1 : 0 }), page },
      })
      const payload = data?.data ?? data ?? {}
      runs.value = payload.data ?? []
      pagination.current_page = payload.current_page ?? 1
      pagination.last_page = payload.last_page ?? 1
      pagination.per_page = payload.per_page ?? runFilters.per_page
      pagination.total = payload.total ?? runs.value.length
      return runs.value
    } catch (e) {
      error.value = readableError(e, 'دریافت تاریخچه ناموفق بود.')
      throw e
    } finally {
      loading.runs = false
    }
  }

  async function fetchRun(id) {
    loading.run = true
    try {
      const { data } = await http.get(routeUrl('backups.show', id))
      selectedRun.value = data?.data ?? null
      return selectedRun.value
    } finally {
      loading.run = false
    }
  }

  async function validateDestination(path) {
    loading.validate = true
    try {
      const { data } = await http.post(routeUrl('backups.export.validate-path'), { path })
      return data?.data ?? {}
    } finally {
      loading.validate = false
    }
  }

  async function runExport(payload) {
    loading.export = true
    try {
      const { data } = await http.post(routeUrl('backups.export.run'), cleanPayload(payload))
      lastResult.value = data?.data ?? null
      return data
    } finally {
      loading.export = false
    }
  }

  async function inspectPackage(sourcePath) {
    loading.inspect = true
    try {
      const { data } = await http.post(routeUrl('backups.import.inspect'), { source_path: sourcePath })
      inspection.value = data?.data ?? null
      return inspection.value
    } finally {
      loading.inspect = false
    }
  }

  async function runImport(payload, { dryRun = false } = {}) {
    loading.import = true
    try {
      const url = dryRun ? routeUrl('backups.import.dry-run') : routeUrl('backups.import.run')
      const { data } = await http.post(url, cleanPayload({ ...payload, dry_run: dryRun }))
      lastResult.value = data?.data ?? null
      return data
    } finally {
      loading.import = false
    }
  }

  async function deleteRun(id, deleteFiles = false) {
    const { data } = await http.delete(routeUrl('backups.destroy', id), { data: { delete_files: deleteFiles } })
    return data
  }

  function downloadLog(id) {
    if (typeof window !== 'undefined') window.open(routeUrl('backups.log', id), '_blank')
  }

  async function saveSettings(payload) {
    loading.settings = true
    try {
      const url = routeUrl('backups.settings.update')
      let data
      try {
        ({ data } = await http.put(url, cleanPayload(payload)))
      } catch (e) {
        if (e?.response?.status === 405) {
          ({ data } = await http.post(url, cleanPayload(payload)))
        } else {
          throw e
        }
      }
      if (overview.value) overview.value.settings = { ...(overview.value.settings ?? {}), ...(data?.data ?? {}) }
      return data
    } finally {
      loading.settings = false
    }
  }

  return {
    // state
    overview, entities, runs, selectedRun, inspection, lastResult, error,
    loading, pagination, runFilters,
    // derived
    statistics, settings, defaults, groups,
    // actions
    fetchOverview, fetchEntities, fetchRuns, fetchRun,
    validateDestination, runExport, inspectPackage, runImport,
    deleteRun, downloadLog, saveSettings,
  }
}
