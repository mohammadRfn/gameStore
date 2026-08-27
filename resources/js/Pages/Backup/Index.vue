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
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import { faNumber, MODE_META, readableError, STRATEGY_META, useBackupCenter } from '@/Composables/useBackupCenter'

/* --------------------------------------------------------------------------
 * صفحه‌ی مرکز پشتیبان‌گیری
 * بازطراحی‌شده: فاصله‌ها یکنواخت، کادرها اندازه‌ی محتوای خودشان،
 * بدون نمایش نام جدول‌ها، وضعیت حذف نرم و اصطلاحات انگلیسی.
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
const toast = reactive({ show: false, tone: 'ok', text: '' })
let toastTimer = null

const exportForm = reactive({
  destination_path: '',
  mode: 'full',
  label: '',
  from_date: '',
  to_date: '',
  include_media: true,
  include_soft_deleted: false,
  include_orphan_media: false,
  remember_path: true,
})

const importForm = reactive({
  source_path: '',
  strategy: 'merge',
  safety_backup: true,
  verify_checksums: true,
  relink: true,
  remember_path: true,
  confirmation: '',
})

const settingsForm = reactive({
  export_root_path: '',
  import_root_path: '',
  retention_copies: 10,
  chunk_size: 1000,
  csv_null_marker: '',
  include_media: true,
  auto_safety_backup: true,
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

function notify(text, tone = 'ok') {
  toast.text = text
  toast.tone = tone
  toast.show = true
  window.clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => { toast.show = false }, 4200)
}

function bridgeMissing() {
  notify('پنجره‌ی انتخاب پوشه در دسترس نیست؛ مسیر را دستی وارد کن.', 'warn')
}

function applyDefaults() {
  const settings = api.settings.value || {}
  exportForm.destination_path = settings.export_root_path || defaults.value.export_root || ''
  importForm.source_path = settings.import_root_path || defaults.value.import_root || ''

  settingsForm.export_root_path = settings.export_root_path || defaults.value.export_root || ''
  settingsForm.import_root_path = settings.import_root_path || defaults.value.import_root || ''
  settingsForm.retention_copies = Number(settings.retention_copies ?? 10)
  settingsForm.chunk_size = Number(settings.chunk_size ?? 1000)
  settingsForm.csv_null_marker = settings.csv_null_marker ?? ''
  settingsForm.include_media = settings.include_media !== false
  settingsForm.auto_safety_backup = settings.auto_safety_backup !== false
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
</script>

<template>
  <AppLayout>
    <Head title="پشتیبان‌گیری" />

    <!-- ریتم فاصله‌ی واحد برای کل صفحه: هیچ کارتی به کارت دیگر نمی‌چسبد -->
    <div class="mx-auto flex w-full max-w-[1180px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8" dir="rtl">
      <!-- سربرگ -->
      <section class="grid items-center gap-6 rounded-2xl bg-gradient-to-bl from-amber-400/10 via-slate-900/60 to-slate-900/60 p-6 ring-1 ring-white/10 lg:grid-cols-[minmax(0,1fr)_260px]">
        <div class="flex flex-col gap-3">
          <span class="w-fit rounded-full bg-white/5 px-3 py-1 text-[11px] font-semibold leading-5 text-amber-300 ring-1 ring-amber-400/20">
            مرکز پشتیبان‌گیری فروشگاه
          </span>

          <h2 class="text-2xl font-black leading-9 text-slate-50 sm:text-3xl sm:leading-11">
            نگهداری اطلاعات، به‌سادگی و با خیال راحت
          </h2>

          <p class="max-w-2xl text-[13px] leading-7 text-slate-300">
            یک نسخه‌ی مرتب از اطلاعات فروشگاه و تصاویر آن بگیر، هر زمان خواستی همان نسخه را برگردان.
            پیش از هر بازیابی، یک نسخه‌ی ایمنی از وضعیت فعلی ساخته می‌شود تا همیشه راه برگشت داشته باشی.
          </p>

          <div class="mt-1 flex flex-wrap gap-2">
            <button
              type="button"
              class="h-11 rounded-xl bg-amber-400/90 px-5 text-[13px] font-bold text-slate-900 transition hover:bg-amber-300"
              @click="activeTab = 'export'"
            >
              شروع خروجی گرفتن
            </button>

            <button
              type="button"
              class="h-11 rounded-xl bg-white/5 px-5 text-[13px] font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10"
              @click="activeTab = 'import'"
            >
              بازیابی از یک بسته
            </button>
          </div>
        </div>

        <BackupOrbitScene />
      </section>

      <!-- آمار -->
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <BackupStatCard label="کل عملیات‌ها" :value="stat.total_runs || 0" icon="🕰" hint="خروجی‌ها، بازیابی‌ها و اجراهای آزمایشی" :delay="40" />
        <BackupStatCard label="بخش‌های قابل پشتیبان" :value="stat.entities_count || api.entities.value.length || 0" icon="🗂" hint="بخش‌های اطلاعاتی فروشگاه" tone="sky" :delay="100" />
        <BackupStatCard label="عملیات ناموفق" :value="stat.failed_runs || 0" icon="⚠" hint="برای بررسی، وارد تاریخچه شو" tone="rose" :delay="160" />
        <BackupStatCard label="فضای آزاد مسیر خروجی" :value="Number(stat.disk_free_mb || 0) * 1048576" icon="💾" hint="برای نگهداری بسته‌های حجیم" tone="emerald" bytes :delay="220" />
      </section>

      <!-- زبانه‌ها -->
      <nav class="flex flex-wrap gap-1.5 rounded-2xl bg-slate-900/60 p-1.5 ring-1 ring-white/10" aria-label="بخش‌های پشتیبان‌گیری">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="h-10 flex-1 rounded-xl px-4 text-[13px] font-semibold transition-colors duration-200 sm:flex-none"
          :class="activeTab === tab.key
            ? 'bg-amber-400/90 text-slate-900'
            : 'text-slate-300 hover:bg-white/5'"
          @click="activeTab = tab.key"
        >
          <span class="ml-1.5" aria-hidden="true">{{ tab.icon }}</span>{{ tab.label }}
        </button>
      </nav>

      <!-- خروجی -->
      <div v-if="activeTab === 'export'" class="grid items-start gap-6 xl:grid-cols-12">
        <div class="flex flex-col gap-6 xl:col-span-7">
          <BackupCard title="نوع خروجی" description="بسته‌ی کامل، فقط اطلاعات، یا فقط تصاویر." icon="👑" tone="gold">
            <div class="grid gap-3 sm:grid-cols-3">
              <button
                v-for="mode in modeList"
                :key="mode.key"
                type="button"
                class="flex flex-col gap-1 rounded-xl px-4 py-3 text-right ring-1 transition-colors duration-200"
                :class="exportForm.mode === mode.key
                  ? 'bg-amber-400/10 ring-amber-400/40'
                  : 'bg-white/[0.03] ring-white/5 hover:bg-white/[0.06]'"
                @click="exportForm.mode = mode.key"
              >
                <span class="text-base leading-none" aria-hidden="true">{{ mode.icon }}</span>
                <span class="text-[13px] font-bold text-slate-100">{{ mode.label }}</span>
                <span class="text-[11px] leading-5 text-slate-400">{{ mode.hint }}</span>
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

        <div class="flex flex-col gap-6 xl:col-span-5">
          <BackupCard title="تنظیمات این خروجی" description="نام بسته، بازه‌ی زمانی و گزینه‌های تکمیلی." icon="⚙">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-300">نام بسته</label>
              <input
                v-model="exportForm.label"
                type="text"
                placeholder="مثلاً: پشتیبان پایان هفته"
                class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none transition placeholder:text-slate-500 focus:ring-2 focus:ring-amber-400/60"
              >
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-300">از تاریخ</label>
                <JalaliDateInput v-model="exportForm.from_date" placeholder="از تاریخ" />
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-300">تا تاریخ</label>
                <JalaliDateInput v-model="exportForm.to_date" placeholder="تا تاریخ" />
              </div>
            </div>

            <div class="grid gap-3">
              <BackupToggleRow
                v-model="exportForm.include_media"
                label="همراه با تصاویر"
                hint="تصاویر کالاها، اقلام فاکتور و رسیدها"
                :disabled="exportForm.mode === 'media'"
              />
              <BackupToggleRow
                v-model="exportForm.include_soft_deleted"
                label="شامل موارد حذف‌شده"
                hint="برای اینکه سوابق قدیمی هم قابل بازگردانی باشند"
              />
              <BackupToggleRow
                v-model="exportForm.include_orphan_media"
                label="تصاویر بدون مالک"
                hint="فایل‌هایی که به هیچ رکوردی وصل نیستند"
              />
              <BackupToggleRow
                v-model="exportForm.remember_path"
                label="این مسیر را به خاطر بسپار"
                hint="دفعه‌ی بعد همین مسیر پیشنهاد می‌شود"
              />
            </div>

            <template #footer>
              <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-[11px] leading-5 text-slate-400">{{ selectionSummary }}</p>

                <button
                  type="button"
                  class="h-11 rounded-xl bg-amber-400/90 px-5 text-[13px] font-bold text-slate-900 transition hover:bg-amber-300 disabled:opacity-60"
                  :disabled="api.loading.export"
                  @click="submitExport"
                >
                  {{ api.loading.export ? 'در حال ساخت بسته…' : 'ساخت بسته‌ی پشتیبان' }}
                </button>
              </div>
            </template>
          </BackupCard>

          <BackupCard title="یادآوری کوتاه" description="سه نکته که جلوی دردسر را می‌گیرد." icon="🧾">
            <ul class="flex flex-col gap-2.5 text-[13px] leading-6 text-slate-300">
              <li class="flex gap-2"><span class="text-amber-300">۱.</span> هفته‌ای یک بسته‌ی کامل روی یک درایو جدا بگیر.</li>
              <li class="flex gap-2"><span class="text-amber-300">۲.</span> پیش از به‌روزرسانی برنامه، تصاویر را هم در بسته بگذار.</li>
              <li class="flex gap-2"><span class="text-amber-300">۳.</span> مسیر خروجی را بیرون از پوشه‌ی نصب برنامه انتخاب کن.</li>
            </ul>
          </BackupCard>
        </div>
      </div>

      <!-- بازیابی -->
      <div v-else-if="activeTab === 'import'" class="grid items-start gap-6 xl:grid-cols-12">
        <div class="flex flex-col gap-6 xl:col-span-7">
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

          <BackupCard title="نتیجه‌ی بررسی بسته" description="پیش از بازیابی، محتوای بسته را ببین." icon="🔎">
            <div v-if="inspection" class="grid gap-3 sm:grid-cols-3">
              <div class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">
                <p class="text-[11px] text-slate-400">تعداد بخش‌ها</p>
                <p class="mt-1 text-lg font-black text-slate-100">{{ faNumber(inspection.entities_count || (inspection.entities || []).length) }}</p>
              </div>
              <div class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">
                <p class="text-[11px] text-slate-400">تعداد رکوردها</p>
                <p class="mt-1 text-lg font-black text-slate-100">{{ faNumber(inspection.records_count || 0) }}</p>
              </div>
              <div class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">
                <p class="text-[11px] text-slate-400">تعداد تصاویر</p>
                <p class="mt-1 text-lg font-black text-slate-100">{{ faNumber(inspection.media_count || 0) }}</p>
              </div>
            </div>

            <p v-else class="rounded-xl bg-white/[0.03] px-4 py-6 text-center text-xs text-slate-400">
              هنوز بسته‌ای بررسی نشده است.
            </p>
          </BackupCard>
        </div>

        <div class="flex flex-col gap-6 xl:col-span-5">
          <BackupCard title="روش بازیابی" description="مشخص کن با اطلاعات فعلی چه کار شود." icon="🧩">
            <div class="grid gap-3">
              <button
                v-for="strategy in strategyList"
                :key="strategy.key"
                type="button"
                class="flex items-start gap-3 rounded-xl px-4 py-3 text-right ring-1 transition-colors duration-200"
                :class="importForm.strategy === strategy.key
                  ? 'bg-amber-400/10 ring-amber-400/40'
                  : 'bg-white/[0.03] ring-white/5 hover:bg-white/[0.06]'"
                @click="importForm.strategy = strategy.key"
              >
                <span class="mt-0.5 text-base leading-none" aria-hidden="true">{{ strategy.icon }}</span>
                <span class="min-w-0 flex-1">
                  <span class="block text-[13px] font-bold text-slate-100">{{ strategy.label }}</span>
                  <span class="mt-0.5 block text-[11px] leading-5 text-slate-400">{{ strategy.hint }}</span>
                </span>
              </button>
            </div>
          </BackupCard>

          <BackupCard title="گزینه‌های ایمنی" :description="activeStrategy.hint || ''" icon="🛡" tone="emerald">
            <div class="grid gap-3">
              <BackupToggleRow v-model="importForm.safety_backup" label="نسخه‌ی ایمنی پیش از بازیابی" hint="اگر نتیجه مطلوب نبود، همه‌چیز برمی‌گردد" />
              <BackupToggleRow v-model="importForm.verify_checksums" label="بررسی سلامت فایل‌ها" hint="تشخیص فایل ناقص یا دست‌کاری‌شده" />
              <BackupToggleRow v-model="importForm.relink" label="اصلاح مسیر تصاویر" hint="تصاویر دوباره به رکوردهای خودشان وصل می‌شوند" />
              <BackupToggleRow v-model="importForm.remember_path" label="این مسیر را به خاطر بسپار" hint="دفعه‌ی بعد همین مسیر پیشنهاد می‌شود" />
            </div>

            <div v-if="needsConfirmation" class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-rose-300">برای جایگزینی کامل، عبارت REPLACE را وارد کن</label>
              <input
                v-model="importForm.confirmation"
                type="text"
                dir="ltr"
                placeholder="REPLACE"
                class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-rose-400/30 outline-none transition placeholder:text-slate-500 focus:ring-2 focus:ring-rose-400/60"
              >
            </div>

            <template #footer>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="h-11 flex-1 rounded-xl bg-white/5 px-4 text-[13px] font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10 disabled:opacity-60"
                  :disabled="api.loading.import"
                  @click="submitImport(true)"
                >
                  اجرای آزمایشی
                </button>

                <button
                  type="button"
                  class="h-11 flex-1 rounded-xl bg-emerald-400/90 px-4 text-[13px] font-bold text-slate-900 transition hover:bg-emerald-300 disabled:opacity-60"
                  :disabled="api.loading.import"
                  @click="submitImport(false)"
                >
                  {{ api.loading.import ? 'در حال بازیابی…' : 'شروع بازیابی' }}
                </button>
              </div>
            </template>
          </BackupCard>
        </div>
      </div>

      <!-- تاریخچه -->
      <BackupRunTimeline
        v-else-if="activeTab === 'history'"
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

      <!-- تنظیمات -->
      <div v-else class="grid items-start gap-6 lg:grid-cols-2">
        <BackupCard title="تنظیمات پیش‌فرض" description="مسیرها و رفتار همیشگی پشتیبان‌گیری." icon="⚙">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-300">مسیر پیش‌فرض خروجی</label>
            <input v-model="settingsForm.export_root_path" type="text" dir="ltr" class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60">
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-300">مسیر پیش‌فرض ورودی</label>
            <input v-model="settingsForm.import_root_path" type="text" dir="ltr" class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60">
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-300">تعداد نسخه‌های نگهداری</label>
              <input v-model.number="settingsForm.retention_copies" type="number" min="0" max="200" class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60">
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-300">اندازه‌ی هر دسته پردازش</label>
              <input v-model.number="settingsForm.chunk_size" type="number" min="100" max="20000" class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60">
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-300">نشانه‌ی مقدار خالی</label>
              <input v-model="settingsForm.csv_null_marker" type="text" maxlength="5" dir="ltr" class="h-11 w-full rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60">
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <BackupToggleRow v-model="settingsForm.include_media" label="تصاویر به‌صورت پیش‌فرض" hint="در بسته‌های بعدی هم گرفته شود" />
            <BackupToggleRow v-model="settingsForm.auto_safety_backup" label="نسخه‌ی ایمنی خودکار" hint="پیش از هر بازیابی ساخته شود" />
          </div>

          <template #footer>
            <button
              type="button"
              class="h-11 w-full rounded-xl bg-amber-400/90 px-5 text-[13px] font-bold text-slate-900 transition hover:bg-amber-300 disabled:opacity-60 sm:w-auto"
              :disabled="api.loading.settings"
              @click="submitSettings"
            >
              {{ api.loading.settings ? 'در حال ذخیره…' : 'ذخیره‌ی تنظیمات' }}
            </button>
          </template>
        </BackupCard>

        <BackupCard title="راهنمای سریع" description="بهترین روال پیشنهادی برای فروشگاه." icon="🧾">
          <ol class="flex flex-col gap-3 text-[13px] leading-7 text-slate-300">
            <li class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">هفته‌ای حداقل یک بسته‌ی کامل روی درایو جداگانه بگیر.</li>
            <li class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">پیش از به‌روزرسانی برنامه، تصاویر را هم در بسته قرار بده.</li>
            <li class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">قبل از بازیابی واقعی، یک بار اجرای آزمایشی بگیر.</li>
            <li class="rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5">مسیرها را بیرون از پوشه‌ی نصب برنامه نگه دار.</li>
          </ol>
        </BackupCard>
      </div>
    </div>

    <!-- پیام‌ها -->
    <transition
      enter-active-class="transition duration-200"
      enter-from-class="translate-y-2 opacity-0"
      leave-active-class="transition duration-200"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed inset-x-0 bottom-5 z-50 mx-auto w-fit max-w-[90vw] rounded-xl px-4 py-3 text-[13px] font-semibold shadow-lg ring-1"
        :class="{
          'bg-emerald-500 text-slate-950 ring-emerald-300/40': toast.tone === 'ok',
          'bg-amber-400 text-slate-950 ring-amber-300/40': toast.tone === 'warn',
          'bg-rose-500 text-white ring-rose-300/40': toast.tone === 'error',
        }"
        role="status"
      >
        {{ toast.text }}
      </div>
    </transition>
  </AppLayout>
</template>
