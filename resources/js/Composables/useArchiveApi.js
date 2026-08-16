/* global route */
/* ==========================================================================
 * GameStore · Archive API Layer
 * --------------------------------------------------------------------------
 * مسیر فایل: resources/js/Composables/useArchiveApi.js
 *
 * تمام ارتباط صفحهٔ بایگانی با بک‌اند از همین‌جا عبور می‌کند تا کامپوننت‌ها
 * کاملاً تمیز و بدون منطق شبکه بمانند (Separation of Concerns).
 *
 * این فایل دقیقاً بر اساس روت‌های موجود در routes/web.php شما نوشته شده:
 *   archives.index            GET     /archives
 *   archives.show             GET     /archives/{archivedRecordId}
 *   archives.sync-paid        POST    /archives/sync-paid
 *   archives.copy             POST    /archives/{sourceType}/{sourceId}/copy
 *   archives.transfer         POST    /archives/{sourceType}/{sourceId}/transfer
 *   archives.records.transfer POST    /archives/{archivedRecordId}/transfer
 *   archives.restore          POST    /archives/{archivedRecordId}/restore
 *   archives.destroy          DELETE  /archives/{archivedRecordId}
 *   archives.export.invoices | .requests | .service-jobs | .all   GET
 * ========================================================================== */

import { ref, reactive, computed } from 'vue'
import axios from 'axios'

/* نوع‌های داخلی بایگانی — دقیقاً مطابق ArchivedRecord::TYPE_* */
export const SOURCE_TYPES = {
  INVOICE: 'invoice',
  REQUEST: 'request',
  SERVICE_JOB: 'service_job',
}

/* وضعیت‌های بایگانی — مطابق ArchivedRecord::STATUS_* */
export const ARCHIVE_STATUS = {
  COPIED: 'copied',
  TRANSFERRED: 'transferred',
}

/* نگاشت نوع داخلی (snake_case) به اسلاگ مسیر (kebab-case) */
const ROUTE_SEGMENT = {
  [SOURCE_TYPES.INVOICE]: 'invoice',
  [SOURCE_TYPES.REQUEST]: 'request',
  [SOURCE_TYPES.SERVICE_JOB]: 'service-job',
}

/* متادیتای نمایشی هر نوع سند */
export const TYPE_META = {
  [SOURCE_TYPES.INVOICE]: {
    label: 'فاکتور',
    plural: 'فاکتورها',
    icon: '🧾',
    color: 'var(--gs-gold)',
    aura: 'var(--gs-gold-glow)',
    exportRoute: 'archives.export.invoices',
  },
  [SOURCE_TYPES.REQUEST]: {
    label: 'درخواست',
    plural: 'درخواست‌ها',
    icon: '📋',
    color: 'var(--gs-accent-3)',
    aura: 'rgba(159, 123, 246, 0.35)',
    exportRoute: 'archives.export.requests',
  },
  [SOURCE_TYPES.SERVICE_JOB]: {
    label: 'سرویس',
    plural: 'سرویس‌ها',
    icon: '🔧',
    color: 'var(--gs-accent)',
    aura: 'rgba(91, 157, 240, 0.35)',
    exportRoute: 'archives.export.service-jobs',
  },
}

/* --------------------------------------------------------------------------
 * کلاینت HTTP — از نمونهٔ سراسری Laravel استفاده می‌کند تا هدرهای CSRF حفظ شود
 * -------------------------------------------------------------------------- */
function httpClient() {
  const client = (typeof window !== 'undefined' && window.axios) ? window.axios : axios

  client.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
  client.defaults.headers.common['Accept'] = 'application/json'

  if (typeof document !== 'undefined') {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    if (token) client.defaults.headers.common['X-CSRF-TOKEN'] = token
  }

  return client
}

/* استخراج پیام خطای خوانا از پاسخ لاراول */
function readableError(error, fallback = 'خطای غیرمنتظره‌ای رخ داد.') {
  const data = error?.response?.data
  if (!data) return error?.message || fallback
  if (data.message) return data.message
  if (data.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) return first[0]
  }
  return fallback
}

/* --------------------------------------------------------------------------
 * Composable اصلی
 * -------------------------------------------------------------------------- */
export function useArchiveApi() {
  const http = httpClient()

  const records = ref([])
  const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
  })

  const filters = reactive({
    source_type: '',
    archive_status: '',
    search: '',
    from: '',
    to: '',
    per_page: 12,
  })

  const loading = ref(false)
  const busyId = ref(null)
  const error = ref(null)

  /* حذف مقادیر خالی تا کوئری‌استرینگ تمیز بماند */
  const cleanFilters = () => {
    const output = {}
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) output[key] = value
    })
    return output
  }

  /* ---------------------------- خواندن لیست ---------------------------- */
  async function fetchRecords(page = 1) {
    loading.value = true
    error.value = null

    try {
      const { data } = await http.get(route('archives.index'), {
        params: { ...cleanFilters(), page },
      })

      const paginator = data?.data ?? {}
      records.value = Array.isArray(paginator.data) ? paginator.data : []

      pagination.current_page = paginator.current_page ?? 1
      pagination.last_page = paginator.last_page ?? 1
      pagination.per_page = paginator.per_page ?? filters.per_page
      pagination.total = paginator.total ?? records.value.length

      return records.value
    } catch (e) {
      error.value = readableError(e, 'دریافت فهرست بایگانی ناموفق بود.')
      records.value = []
      throw e
    } finally {
      loading.value = false
    }
  }

  /* ------------------------- جزئیات یک رکورد -------------------------- */
  async function fetchRecord(archivedRecordId) {
    const { data } = await http.get(route('archives.show', archivedRecordId))
    return data?.data ?? null
  }

  /* -------------------- همگام‌سازی موارد پرداخت‌شده -------------------- */
  async function syncPaid() {
    busyId.value = 'sync'
    try {
      const { data } = await http.post(route('archives.sync-paid'))
      return data
    } catch (e) {
      throw new Error(readableError(e, 'همگام‌سازی بایگانی ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  /* ------------------ کپی گرفتن از رکورد مبدأ (بدون حذف) ------------------ */
  async function copyToArchive(sourceType, sourceId, reason = null) {
    const segment = ROUTE_SEGMENT[sourceType] ?? sourceType
    busyId.value = `copy-${sourceType}-${sourceId}`
    try {
      const { data } = await http.post(route('archives.copy', [segment, sourceId]), { reason })
      return data
    } catch (e) {
      throw new Error(readableError(e, 'کپی در بایگانی ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  /* ---- «انتقال به بایگانی» با شناسهٔ خودِ رکورد بایگانی (دکمهٔ اصلی) ---- */
  async function transferArchiveRecord(archivedRecordId, reason = null) {
    busyId.value = `transfer-${archivedRecordId}`
    try {
      const { data } = await http.post(route('archives.records.transfer', archivedRecordId), { reason })
      return data
    } catch (e) {
      throw new Error(readableError(e, 'انتقال به بایگانی ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  /* ------ «انتقال به بایگانی» مستقیم از روی رکورد مبدأ (فاکتور/درخواست/سرویس) ------ */
  async function transferSource(sourceType, sourceId, reason = null) {
    const segment = ROUTE_SEGMENT[sourceType] ?? sourceType
    busyId.value = `transfer-src-${sourceType}-${sourceId}`
    try {
      const { data } = await http.post(route('archives.transfer', [segment, sourceId]), { reason })
      return data
    } catch (e) {
      throw new Error(readableError(e, 'انتقال به بایگانی ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  /* --------------------- بازیابی (لغو انتقال) --------------------- */
  async function restoreRecord(archivedRecordId) {
    busyId.value = `restore-${archivedRecordId}`
    try {
      const { data } = await http.post(route('archives.restore', archivedRecordId))
      return data
    } catch (e) {
      throw new Error(readableError(e, 'بازیابی رکورد ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  /* ------------------- حذف ردیف بایگانی ------------------- */
  async function destroyRecord(archivedRecordId, reason = null) {
    busyId.value = `destroy-${archivedRecordId}`
    try {
      const { data } = await http.delete(route('archives.destroy', archivedRecordId), {
        data: { reason },
      })
      return data
    } catch (e) {
      throw new Error(readableError(e, 'حذف رکورد بایگانی ناموفق بود.'))
    } finally {
      busyId.value = null
    }
  }

  /* --------------------------- خروجی اکسل --------------------------- */
  function exportExcel(scope = 'all') {
    const routeName = scope === 'all'
      ? 'archives.export.all'
      : (TYPE_META[scope]?.exportRoute ?? 'archives.export.all')

    const url = route(routeName, cleanFilters())
    window.location.href = url
  }

  /* --------------------------- آمار زنده --------------------------- */
  const stats = computed(() => {
    const list = records.value
    const sum = (predicate) => list
      .filter(predicate)
      .reduce((total, item) => total + Number(item.total_amount || 0), 0)

    return {
      total: pagination.total,
      pageCount: list.length,
      invoices: list.filter((r) => r.source_type === SOURCE_TYPES.INVOICE).length,
      requests: list.filter((r) => r.source_type === SOURCE_TYPES.REQUEST).length,
      serviceJobs: list.filter((r) => r.source_type === SOURCE_TYPES.SERVICE_JOB).length,
      copied: list.filter((r) => r.archive_status === ARCHIVE_STATUS.COPIED).length,
      transferred: list.filter((r) => r.archive_status === ARCHIVE_STATUS.TRANSFERRED).length,
      amount: sum((r) => r.source_type === SOURCE_TYPES.INVOICE),
    }
  })

  function resetFilters() {
    filters.source_type = ''
    filters.archive_status = ''
    filters.search = ''
    filters.from = ''
    filters.to = ''
  }

  return {
    // state
    records,
    pagination,
    filters,
    loading,
    busyId,
    error,
    stats,
    // actions
    fetchRecords,
    fetchRecord,
    syncPaid,
    copyToArchive,
    transferArchiveRecord,
    transferSource,
    restoreRecord,
    destroyRecord,
    exportExcel,
    resetFilters,
  }
}

/* --------------------------------------------------------------------------
 * کمکی‌های نمایشی مشترک
 * -------------------------------------------------------------------------- */
export function formatMoney(value) {
  const number = Number(value || 0)
  return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }).format(number)
}

export function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return new Intl.DateTimeFormat('fa-IR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

export default useArchiveApi
