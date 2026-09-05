<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import BackupEntityMatrix from '@/Components/Backup/BackupEntityMatrix.vue'
import BackupOrbitScene from '@/Components/Backup/BackupOrbitScene.vue'
import BackupPathPicker from '@/Components/Backup/BackupPathPicker.vue'
import BackupRunTimeline from '@/Components/Backup/BackupRunTimeline.vue'
import BackupStatCard from '@/Components/Backup/BackupStatCard.vue'
import BackupToggleRow from '@/Components/Backup/BackupToggleRow.vue'
import BackupToaster from '@/Components/Backup/BackupToaster.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import { faNumber, MODE_META, readableError, STRATEGY_META, useBackupCenter } from '@/Composables/useBackupCenter'

/* --------------------------------------------------------------------------
 * مرکز پشتیبان‌گیری — نسخه‌ی Royal
 *
 * چه چیزی نسبت به نسخه‌ی قبلی عوض شد؟
 * ۱) کل تمپلیت از utility های Tailwind به کلاس‌های backup-* منتقل شد؛
 *    یعنی همان چیزی که resources/css/backup-royal.css تعریف کرده و تا امروز
 *    حتی یک بار هم روی این صفحه استفاده نشده بود.
 * ۲) انیمیشن‌ها فعال شدند: backup-reveal روی هر بلوک، backup-hero-scan روی
 *    هدر، backup-float روی گوی سه‌بعدی و backup-pop روی نوتیفیکیشن‌ها.
 * ۳) BackupToaster که ساخته شده بود ولی هرگز mount نمی‌شد، وصل شد.
 * -------------------------------------------------------------------------- */

const api = useBackupCenter()

const tabs = [
  { key: 'export', label: 'خروجی گرفتن', icon: '⬆' },
  { key: 'import', label: 'بازیابی', icon: '⬇' },
  { key: 'history', label: 'تاریخچه', icon: '🕰' },
  { key: 'settings', label: 'تنظیمات', icon: '⚙' },
]

const activeTab = ref('export')
const selectedEntities = ref([])

/* نوتیفیکیشن: قبلاً یک آبجکت تکی بود و به BackupToaster وصل نمی‌شد.
   حالا صف است تا transition های backup-pop واقعاً دیده شوند. */
const toasts = ref([])
let toastSeq = 0

const EXPORT_TOGGLES_KEY = 'backup:export-toggles'

function loadExportToggles() {
  try {
    const raw = window.localStorage.getItem(EXPORT_TOGGLES_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch (e) {
    return {}
  }
}

const savedExportToggles = loadExportToggles()

const exportForm = reactive({
  destination_path: '',
  mode: 'full',
  label: '',
  from_date: '',
  to_date: '',
  include_media: true, // مقدار واقعی در applyDefaults() از settings.include_media ست میشه
  include_soft_deleted: false, // همینطور از settings.include_soft_deleted
  include_orphan_media: savedExportToggles.include_orphan_media ?? false,
  remember_path: savedExportToggles.remember_path ?? true,
})

const IMPORT_TOGGLES_KEY = 'backup:import-toggles'

function loadImportToggles() {
  try {
    const raw = window.localStorage.getItem(IMPORT_TOGGLES_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch (e) {
    return {}
  }
}

const savedImportToggles = loadImportToggles()

const importForm = reactive({
  source_path: '',
  strategy: 'merge',
  safety_backup: true, // فقط محلی؛ معادل سراسری در تنظیمات نداره (قبلاً حذف شد)
  verify_checksums: true, // مقدار واقعی در applyDefaults() از settings.verify_checksums ست میشه
  relink: savedImportToggles.relink ?? true,
  remember_path: savedImportToggles.remember_path ?? true,
  confirmation: '',
})

const settingsForm = reactive({
  export_root_path: '',
  import_root_path: '',
  retention_copies: 10,
  chunk_size: 1000,
  csv_null_marker: '',
  include_media: true,
  include_soft_deleted: true,
  verify_checksums: true,
})

const exportPath = reactive({ validated: false, freeSpace: null })

const stat = computed(() => api.statistics.value || {})
const defaults = computed(() => api.defaults.value || {})
const modeList = computed(() => Object.entries(MODE_META).map(([key, meta]) => ({ key, ...meta })))
const strategyList = computed(() => Object.entries(STRATEGY_META).map(([key, meta]) => ({ key, ...meta })))
const activeStrategy = computed(() => STRATEGY_META[importForm.strategy] || {})
const needsConfirmation = computed(() => importForm.strategy === 'replace')

const selectionSummary = computed(() =>
  selectedEntities.value.length
    ? faNumber(selectedEntities.value.length) + ' بخش انتخاب شده'
    : 'همه‌ی بخش‌ها',
)

function dismissToast(id) {
  toasts.value = toasts.value.filter((item) => item.id !== id)
}

function notify(text, tone = 'ok') {
  const type = { ok: 'success', warn: 'warning', error: 'error', info: 'info' }[tone] || 'info'
  const id = ++toastSeq
  const title = { success: 'انجام شد', warning: 'هشدار', error: 'خطا', info: 'اطلاع' }[type]

  toasts.value = [...toasts.value, { id, type, title, message: text }]
  window.setTimeout(() => dismissToast(id), 4200)
}

function bridgeMissing(detail) {
  notify(detail || 'پنجره‌ی انتخاب پوشه در دسترس نیست؛ مسیر را دستی وارد کن.', 'warn')
}

function applyDefaults() {
  const settings = api.settings.value || {}
  exportForm.destination_path = settings.export_root_path || defaults.value.export_root || ''
  importForm.source_path = settings.import_root_path || defaults.value.import_root || ''

  exportForm.include_media = settings.include_media !== false
  exportForm.include_soft_deleted = settings.include_soft_deleted !== false
  importForm.verify_checksums = settings.verify_checksums !== false

  settingsForm.export_root_path = settings.export_root_path || defaults.value.export_root || ''
  settingsForm.import_root_path = settings.import_root_path || defaults.value.import_root || ''
  settingsForm.retention_copies = Number(settings.retention_copies ?? 10)
  settingsForm.chunk_size = Number(settings.chunk_size ?? 1000)
  settingsForm.csv_null_marker = settings.csv_null_marker ?? ''
  settingsForm.include_media = settings.include_media !== false
  settingsForm.include_soft_deleted = settings.include_soft_deleted !== false
  settingsForm.verify_checksums = settings.verify_checksums !== false
}

onMounted(async () => {
  try {
    await api.fetchOverview()
    applyDefaults()
    await api.fetchRuns(1)
  } catch (e) {
    notify(readableError(e, 'دریافت اطلاعات ناموفق بود.'), 'error')
  }
})

watch(() => exportForm.mode, (mode) => {
  if (mode === 'database') exportForm.include_media = false
  if (mode === 'media') exportForm.include_media = true
})

watch(() => exportForm.destination_path, () => { exportPath.validated = false })

watch(
  () => ({
    include_orphan_media: exportForm.include_orphan_media,
    remember_path: exportForm.remember_path,
  }),
  (toggles) => {
    try {
      window.localStorage.setItem(EXPORT_TOGGLES_KEY, JSON.stringify(toggles))
    } catch (e) {
      /* localStorage در دسترس نبود؛ بی‌خیال شو */
    }
  },
  { deep: true },
)

watch(
  () => ({
    relink: importForm.relink,
    remember_path: importForm.remember_path,
  }),
  (toggles) => {
    try {
      window.localStorage.setItem(IMPORT_TOGGLES_KEY, JSON.stringify(toggles))
    } catch (e) {
      /* localStorage در دسترس نبود؛ بی‌خیال شو */
    }
  },
  { deep: true },
)

async function validateExportPath() {
  try {
    const result = await api.validateDestination(exportForm.destination_path)
    exportPath.validated = Boolean(result?.writable)
    exportPath.freeSpace = result?.free_space_mb ?? null
    if (result?.path) exportForm.destination_path = result.path
    notify('مسیر خروجی معتبر است.')
  } catch (e) {
    exportPath.validated = false
    notify(readableError(e, 'مسیر خروجی معتبر نیست.'), 'error')
  }
}

async function submitExport() {
  try {
    const response = await api.runExport({ ...exportForm, entities: selectedEntities.value })
    notify(response?.message || 'پشتیبان‌گیری انجام شد.')
    await api.fetchRuns(1)
  } catch (e) {
    notify(readableError(e, 'خروجی گرفتن ناموفق بود.'), 'error')
  }
}

async function inspectPackage() {
  try {
    await api.inspectPackage(importForm.source_path)
    notify('بسته بررسی شد.')
  } catch (e) {
    notify(readableError(e, 'بررسی بسته ناموفق بود.'), 'error')
  }
}

async function submitImport(dryRun) {
  try {
    const response = await api.runImport({ ...importForm, entities: selectedEntities.value }, { dryRun })
    notify(response?.message || (dryRun ? 'اجرای آزمایشی انجام شد.' : 'بازیابی انجام شد.'))
    await api.fetchRuns(1)
  } catch (e) {
    notify(readableError(e, 'عملیات بازیابی ناموفق بود.'), 'error')
  }
}

async function submitSettings() {
  try {
    const response = await api.saveSettings(settingsForm)
    notify(response?.message || 'تنظیمات ذخیره شد.')
  } catch (e) {
    notify(readableError(e, 'ذخیره‌ی تنظیمات ناموفق بود.'), 'error')
  }
}

function updateRunFilters(next) {
  Object.assign(api.runFilters, next)
  api.fetchRuns(1)
}

async function askDeleteRun(run) {
  if (!window.confirm('این رکورد از تاریخچه حذف شود؟')) return
  try {
    await api.deleteRun(run.id, false)
    notify('رکورد حذف شد.')
    await api.fetchRuns(api.pagination.current_page)
  } catch (e) {
    notify(readableError(e, 'حذف ناموفق بود.'), 'error')
  }
}

async function openRun(run) {
  try {
    await api.fetchRun(run.id)
    activeTab.value = 'history'
  } catch (e) {
    notify(readableError(e, 'دریافت جزئیات ناموفق بود.'), 'error')
  }
}

const inspection = api.inspection

/* کمک‌کننده‌ی تاخیر انیمیشن ورود؛ backup-reveal متغیر --delay را می‌خواند. */
function reveal(ms) {
  return { '--delay': ms + 'ms' }
}
</script>

<template>
  <AppLayout>
    <Head title="پشتیبان‌گیری" />

    <div class="backup-page" dir="rtl">
      <!-- سربرگ کوچک بالای صفحه -->
      <div class="backup-topline backup-reveal" :style="reveal(0)">
        <div>
          <span class="backup-kicker">مرکز پشتیبان‌گیری</span>
          <p class="backup-section__desc">وضعیت لحظه‌ای نسخه‌های پشتیبان فروشگاه</p>
        </div>

        <div class="backup-head-badges">
          <span class="backup-pill backup-pill--ok">سامانه فعال</span>
          <span class="backup-pill backup-pill--info">{{ selectionSummary }}</span>
          <span v-if="api.loading.runs" class="backup-pill backup-pill--warn">
            <i class="backup-spinner" aria-hidden="true" /> در حال هم‌گام‌سازی
          </span>
        </div>
      </div>

      <!-- هدر اصلی: انیمیشن backup-hero-scan روی این کادر اجرا می‌شود -->
      <section class="backup-hero backup-reveal" :style="reveal(70)">
        <div class="backup-hero__content">
          <div>
            <span class="backup-kicker">بکاپ  هوشمند</span>

            <h1 class="backup-hero__title">
              نگهداری اطلاعات،
              <span class="backup-gradient-text">با خیال راحت</span>
            </h1>

            <p class="backup-hero__desc">
              یک نسخه‌ی مرتب از اطلاعات فروشگاه و تصاویر آن بگیر، هر زمان خواستی همان نسخه را برگردان.
              پیش از هر بازیابی یک نسخه‌ی ایمنی از وضعیت فعلی ساخته می‌شود تا همیشه راه برگشت داشته باشی.
            </p>

            <div class="backup-hero__actions">
              <button type="button" class="backup-btn backup-btn--gold backup-btn--wide" @click="activeTab = 'export'">
                شروع خروجی گرفتن
              </button>

              <button type="button" class="backup-btn backup-btn--ghost" @click="activeTab = 'import'">
                بازیابی از یک بسته
              </button>
            </div>
          </div>

          <div class="backup-orb-stage">
            <BackupOrbitScene />
          </div>
        </div>
      </section>

      <!-- آمار -->
      <section class="backup-grid backup-grid--4 backup-reveal" :style="reveal(140)">
        <BackupStatCard label="کل عملیات‌ها" :value="stat.total_runs || 0" icon="🕰" hint="خروجی‌ها، بازیابی‌ها و اجراهای آزمایشی" :delay="40" />
        <BackupStatCard label="بخش‌های قابل پشتیبان" :value="stat.entities_count || api.entities.value.length || 0" icon="🗂" hint="بخش‌های اطلاعاتی فروشگاه" tone="sky" :delay="100" />
        <BackupStatCard label="عملیات ناموفق" :value="stat.failed_runs || 0" icon="⚠" hint="برای بررسی، وارد تاریخچه شو" tone="rose" :delay="160" />
        <BackupStatCard label="فضای آزاد مسیر خروجی" :value="Number(stat.disk_free_mb || 0) * 1048576" icon="💾" hint="برای نگهداری بسته‌های حجیم" tone="emerald" bytes :delay="220" />
      </section>

      <!-- زبانه‌ها -->
      <nav class="backup-tabs backup-reveal" :style="reveal(200)" aria-label="بخش‌های پشتیبان‌گیری">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="backup-tab"
          :class="{ 'is-active': activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <span aria-hidden="true">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </nav>

      <Transition name="backup-fade" mode="out-in">
        <!-- ================= خروجی ================= -->
        <div v-if="activeTab === 'export'" key="export" class="backup-grid backup-grid--2">
          <div class="backup-grid">
            <BackupCard title="نوع خروجی" description="بسته‌ی کامل، فقط اطلاعات، یا فقط تصاویر." icon="👑" tone="gold" :delay="60">
              <div class="backup-grid backup-grid--3">
                <button
                  v-for="mode in modeList"
                  :key="mode.key"
                  type="button"
                  class="backup-mode-card"
                  :class="{ 'is-active': exportForm.mode === mode.key }"
                  @click="exportForm.mode = mode.key"
                >
                  <span class="backup-mode-card__icon" aria-hidden="true">{{ mode.icon }}</span>
                  <span class="backup-mode-card__title">{{ mode.label }}</span>
                  <span class="backup-mode-card__hint">{{ mode.hint }}</span>
                </button>
              </div>
            </BackupCard>

            <BackupPathPicker
              v-model="exportForm.destination_path"
              title="مسیر ذخیره‌ی خروجی"
              icon="📤"
              description="پوشه‌ای که بسته‌ی پشتیبان داخل آن ساخته می‌شود."
              :default-path="defaults.export_root || ''"
              :validated="exportPath.validated"
              :free-space="exportPath.freeSpace"
              :loading="api.loading.validate"
              hint="بهتر است پوشه‌ای بیرون از محل نصب برنامه (مثلاً روی درایو دیگر) انتخاب شود."
              @validate="validateExportPath"
              @bridge-missing="bridgeMissing"
            />

            <BackupEntityMatrix
              v-model="selectedEntities"
              :entities="api.entities.value"
              :groups="api.groups.value"
            />
          </div>

          <div class="backup-grid">
            <BackupCard title="تنظیمات این خروجی" description="نام بسته، بازه‌ی زمانی و گزینه‌های تکمیلی." icon="⚙" :delay="120">
              <label class="backup-field">
                <span class="backup-field__label">نام بسته</span>
                <input v-model="exportForm.label" type="text" class="backup-input" placeholder="مثلاً: پشتیبان پایان هفته">
              </label>

              <div class="backup-grid backup-grid--2">
                <label class="backup-field">
                  <span class="backup-field__label">از تاریخ</span>
                  <JalaliDateInput v-model="exportForm.from_date" placeholder="از تاریخ" />
                </label>

                <label class="backup-field">
                  <span class="backup-field__label">تا تاریخ</span>
                  <JalaliDateInput v-model="exportForm.to_date" placeholder="تا تاریخ" />
                </label>
              </div>

              <div class="backup-grid">
                <BackupToggleRow v-model="exportForm.include_media" label="همراه با تصاویر" hint="تصاویر کالاها، اقلام فاکتور و رسیدها" :disabled="exportForm.mode === 'media'" />
                <BackupToggleRow v-model="exportForm.include_soft_deleted" label="شامل موارد حذف‌شده" hint="برای اینکه سوابق قدیمی هم قابل بازگردانی باشند" />
                <BackupToggleRow v-model="exportForm.include_orphan_media" label="تصاویر بدون مالک" hint="فایل‌هایی که به هیچ رکوردی وصل نیستند" />
                <BackupToggleRow v-model="exportForm.remember_path" label="این مسیر را به خاطر بسپار" hint="دفعه‌ی بعد همین مسیر پیشنهاد می‌شود" />
              </div>

              <template #footer>
                <div class="backup-topline">
                  <p class="backup-section__desc">{{ selectionSummary }}</p>

                  <button type="button" class="backup-btn backup-btn--gold" :disabled="api.loading.export" @click="submitExport">
                    <i v-if="api.loading.export" class="backup-spinner" aria-hidden="true" />
                    {{ api.loading.export ? 'در حال ساخت بسته…' : 'ساخت بسته‌ی پشتیبان' }}
                  </button>
                </div>
              </template>
            </BackupCard>

            <BackupCard title="یادآوری کوتاه" description="سه نکته که جلوی دردسر را می‌گیرد." icon="🧾" :delay="180">
              <div class="backup-grid">
                <p class="backup-section__desc">۱. هفته‌ای یک بسته‌ی کامل روی یک درایو جدا بگیر.</p>
                <p class="backup-section__desc">۲. پیش از به‌روزرسانی برنامه، تصاویر را هم در بسته بگذار.</p>
                <p class="backup-section__desc">۳. مسیر خروجی را بیرون از پوشه‌ی نصب برنامه انتخاب کن.</p>
              </div>
            </BackupCard>
          </div>
        </div>

        <!-- ================= بازیابی ================= -->
        <div v-else-if="activeTab === 'import'" key="import" class="backup-grid backup-grid--2">
          <div class="backup-grid">
            <BackupPathPicker
              v-model="importForm.source_path"
              title="مسیر بسته‌ی ورودی"
              icon="📥"
              description="پوشه‌ی بسته‌ی پشتیبانی که می‌خواهی برگردانی."
              :default-path="defaults.import_root || ''"
              action-label="بررسی بسته"
              :loading="api.loading.inspect"
              hint="اگر پوشه‌ی والد را انتخاب کنی، تازه‌ترین بسته‌ی معتبر به‌صورت خودکار پیدا می‌شود."
              @validate="inspectPackage"
              @bridge-missing="bridgeMissing"
            />

            <BackupCard title="نتیجه‌ی بررسی بسته" description="پیش از بازیابی، محتوای بسته را ببین." icon="🔎" :delay="120">
              <div v-if="inspection" class="backup-grid backup-grid--3">
                <div class="backup-entity-card">
                  <div class="backup-entity-card__head">
                    <span class="backup-entity-card__title">تعداد بخش‌ها</span>
                  </div>
                  <span class="backup-entity-card__meta">{{ faNumber(inspection.entities_count || (inspection.entities || []).length) }}</span>
                </div>

                <div class="backup-entity-card">
                  <div class="backup-entity-card__head">
                    <span class="backup-entity-card__title">تعداد رکوردها</span>
                  </div>
                  <span class="backup-entity-card__meta">{{ faNumber(inspection.records_count || 0) }}</span>
                </div>

                <div class="backup-entity-card">
                  <div class="backup-entity-card__head">
                    <span class="backup-entity-card__title">تعداد تصاویر</span>
                  </div>
                  <span class="backup-entity-card__meta">{{ faNumber(inspection.media_count || 0) }}</span>
                </div>
              </div>

              <div v-else class="backup-empty-state">
                <span class="backup-empty-state__icon" aria-hidden="true">🗃</span>
                <p class="backup-empty-state__title">هنوز بسته‌ای بررسی نشده است</p>
                <p class="backup-empty-state__text">مسیر بسته را وارد کن و دکمه‌ی «بررسی بسته» را بزن.</p>
              </div>
            </BackupCard>
          </div>

          <div class="backup-grid">
            <BackupCard title="روش بازیابی" description="مشخص کن با اطلاعات فعلی چه کار شود." icon="🧩" :delay="60">
              <div class="backup-grid">
                <button
                  v-for="strategy in strategyList"
                  :key="strategy.key"
                  type="button"
                  class="backup-mode-card"
                  :class="{ 'is-active': importForm.strategy === strategy.key }"
                  @click="importForm.strategy = strategy.key"
                >
                  <span class="backup-mode-card__icon" aria-hidden="true">{{ strategy.icon }}</span>
                  <span class="backup-mode-card__title">{{ strategy.label }}</span>
                  <span class="backup-mode-card__hint">{{ strategy.hint }}</span>
                </button>
              </div>
            </BackupCard>

            <BackupCard title="گزینه‌های ایمنی" :description="activeStrategy.hint || ''" icon="🛡" tone="emerald" :delay="140">
              <div class="backup-grid">
                <BackupToggleRow v-model="importForm.safety_backup" label="نسخه‌ی ایمنی پیش از بازیابی" hint="اگر نتیجه مطلوب نبود، همه‌چیز برمی‌گردد" />
                <BackupToggleRow v-model="importForm.verify_checksums" label="بررسی سلامت فایل‌ها" hint="تشخیص فایل ناقص یا دست‌کاری‌شده" />
                <BackupToggleRow v-model="importForm.relink" label="اصلاح مسیر تصاویر" hint="تصاویر دوباره به رکوردهای خودشان وصل می‌شوند" />
                <BackupToggleRow v-model="importForm.remember_path" label="این مسیر را به خاطر بسپار" hint="دفعه‌ی بعد همین مسیر پیشنهاد می‌شود" />
              </div>

              <label v-if="needsConfirmation" class="backup-field">
                <span class="backup-field__label">برای جایگزینی کامل، عبارت REPLACE را وارد کن</span>
                <input v-model="importForm.confirmation" type="text" dir="ltr" class="backup-input" placeholder="REPLACE">
              </label>

              <template #footer>
                <div class="backup-hero__actions">
                  <button type="button" class="backup-btn backup-btn--ghost" :disabled="api.loading.import" @click="submitImport(true)">
                    اجرای آزمایشی
                  </button>

                  <button type="button" class="backup-btn backup-btn--success" :disabled="api.loading.import" @click="submitImport(false)">
                    <i v-if="api.loading.import" class="backup-spinner" aria-hidden="true" />
                    {{ api.loading.import ? 'در حال بازیابی…' : 'شروع بازیابی' }}
                  </button>
                </div>
              </template>
            </BackupCard>
          </div>
        </div>

        <!-- ================= تاریخچه ================= -->
        <BackupRunTimeline
          v-else-if="activeTab === 'history'"
          key="history"
          :runs="api.runs.value"
          :filters="api.runFilters"
          :pagination="api.pagination"
          :loading="api.loading.runs"
          @update:filters="updateRunFilters"
          @refresh="api.fetchRuns(api.pagination.current_page)"
          @page="api.fetchRuns"
          @view="openRun"
          @log="(run) => api.downloadLog(run.id)"
          @delete="askDeleteRun"
        />

        <!-- ================= تنظیمات ================= -->
        <div v-else key="settings" class="backup-grid backup-grid--2">
          <BackupCard title="تنظیمات پیش‌فرض" description="مسیرها و رفتار همیشگی پشتیبان‌گیری." icon="⚙" :delay="60">
            <label class="backup-field">
              <span class="backup-field__label">مسیر پیش‌فرض خروجی</span>
              <input v-model="settingsForm.export_root_path" type="text" dir="ltr" class="backup-input">
            </label>

            <label class="backup-field">
              <span class="backup-field__label">مسیر پیش‌فرض ورودی</span>
              <input v-model="settingsForm.import_root_path" type="text" dir="ltr" class="backup-input">
            </label>

            <div class="backup-grid backup-grid--3">
              <label class="backup-field">
                <span class="backup-field__label">تعداد نسخه‌های نگهداری</span>
                <input v-model.number="settingsForm.retention_copies" type="number" min="0" max="200" class="backup-input">
              </label>

              <label class="backup-field">
                <span class="backup-field__label">اندازه‌ی هر دسته پردازش</span>
                <input v-model.number="settingsForm.chunk_size" type="number" min="100" max="20000" class="backup-input">
              </label>

              <label class="backup-field">
                <span class="backup-field__label">نشانه‌ی مقدار خالی</span>
                <input v-model="settingsForm.csv_null_marker" type="text" dir="ltr" class="backup-input" placeholder="NULL">
              </label>
            </div>

            <template #footer>
              <button type="button" class="backup-btn backup-btn--gold backup-btn--wide" @click="submitSettings">
                ذخیره‌ی تنظیمات
              </button>
            </template>
          </BackupCard>

          <BackupCard title="رفتار پیش‌فرض" description="این گزینه‌ها برای همه‌ی عملیات‌های بعدی اعمال می‌شوند." icon="🛡" tone="emerald" :delay="120">
            <div class="backup-grid">
              <BackupToggleRow v-model="settingsForm.include_media" label="همیشه تصاویر را هم بگیر" hint="بسته سنگین‌تر ولی کامل‌تر می‌شود" />
              <BackupToggleRow v-model="settingsForm.include_soft_deleted" label="شامل موارد حذف‌شده" hint="پیش‌فرض خروجی گرفتن؛ در تب خروجی هم قابل تغییره" />
              <BackupToggleRow v-model="settingsForm.verify_checksums" label="بررسی سلامت فایل‌ها هنگام بازیابی" hint="پیش‌فرض بازیابی؛ در تب بازیابی هم قابل تغییره" />
            </div>
          </BackupCard>
        </div>
      </Transition>
    </div>

    <!-- این کامپوننت ساخته شده بود ولی هیچ‌جا mount نمی‌شد -->
    <BackupToaster :toasts="toasts" @dismiss="dismissToast" />
  </AppLayout>
</template>
