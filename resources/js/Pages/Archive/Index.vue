<!-- ==========================================================================
     GameStore · صفحهٔ بایگانی
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Pages/Archive/Index.vue

     صفحهٔ اصلی بخش بایگانی با تجربهٔ کاربری سه‌بعدی:
       • هدر قهرمان با مکعب چرخان و صحنهٔ پرسپکتیو
       • کارت‌های آمار زندهٔ تیلت‌شونده
       • نوار فرمان (فیلتر/جستجو/خروجی اکسل/همگام‌سازی)
       • شبکهٔ کارت‌های هولوگرافیک با دکمهٔ «انتقال به بایگانی»
       • کشوی جزئیات، مودال تأیید و نوتیفیکیشن سه‌بعدی
     ========================================================================== -->
<template>
  <AppLayout>
    <template #header>
      <div class="page-head">
        <div>
          <h1 class="gs-title">بایگانی</h1>
          <p class="gs-subtitle">
            آرشیو دائمی فاکتورها، درخواست‌ها و سرویس‌های پرداخت‌شده
          </p>
        </div>

        <div class="head-badges">
          <span class="head-badge">
            <b>{{ faNumber(pagination.total) }}</b> رکورد بایگانی
          </span>
        </div>
      </div>
    </template>

    <!-- ================= HERO سه‌بعدی ================= -->
    <section class="hero a3d-stage">
      <ArchiveScene :dust-count="20" />

      <div class="hero-content">
        <div class="hero-text">
          <p class="hero-kicker">GAMESTORE · ARCHIVE VAULT</p>
          <h2 class="hero-title">
            گاوصندوق دیجیتال
            <span class="hero-title-accent">اسناد فروشگاه</span>
          </h2>
          <p class="hero-desc">
            هر سند پرداخت‌شده یک نسخهٔ منجمد و تغییرناپذیر در بایگانی دارد.
            با «انتقال به بایگانی» رکورد از بخش اصلی برداشته می‌شود، اما
            اطلاعات کامل آن برای همیشه اینجا محفوظ می‌ماند.
          </p>

          <div class="hero-actions">
            <button
              type="button"
              class="a3d-btn a3d-btn--gold"
              :disabled="busyId === 'sync'"
              @click="handleSync"
            >
              <span :class="{ 'is-spinning': busyId === 'sync' }">⟳</span>
              همگام‌سازی موارد پرداخت‌شده
            </button>

            <button type="button" class="a3d-btn" @click="api.exportExcel('all')">
              ⬇ خروجی اکسل کامل
            </button>
          </div>
        </div>

        <!-- مکعب سه‌بعدی -->
        <div class="hero-cube-wrap">
          <div class="a3d-cube" style="--cube-size: 96px">
            <div class="a3d-cube__face a3d-cube__face--front">🗄</div>
            <div class="a3d-cube__face a3d-cube__face--back">🧾</div>
            <div class="a3d-cube__face a3d-cube__face--right">📋</div>
            <div class="a3d-cube__face a3d-cube__face--left">🔧</div>
            <div class="a3d-cube__face a3d-cube__face--top">✦</div>
            <div class="a3d-cube__face a3d-cube__face--bottom">🎮</div>
          </div>
          <div class="cube-shadow"></div>
        </div>
      </div>
    </section>

    <!-- ================= کارت‌های آمار ================= -->
    <section class="stats-grid a3d-stage">
      <ArchiveStatCard
        label="کل رکوردهای بایگانی"
        :value="pagination.total"
        icon="🗄"
        :delay="0"
        :ratio="1"
      />
      <ArchiveStatCard
        label="فاکتورهای این صفحه"
        :value="stats.invoices"
        icon="🧾"
        accent="var(--gs-gold)"
        aura="var(--gs-gold-glow)"
        :delay="80"
        :ratio="ratioOf(stats.invoices)"
      />
      <ArchiveStatCard
        label="درخواست‌های این صفحه"
        :value="stats.requests"
        icon="📋"
        accent="var(--gs-accent-3)"
        aura="rgba(159,123,246,.35)"
        :delay="160"
        :ratio="ratioOf(stats.requests)"
      />
      <ArchiveStatCard
        label="سرویس‌های این صفحه"
        :value="stats.serviceJobs"
        icon="🔧"
        accent="var(--gs-accent)"
        aura="rgba(91,157,240,.35)"
        :delay="240"
        :ratio="ratioOf(stats.serviceJobs)"
      />
      <ArchiveStatCard
        label="مجموع مبلغ این صفحه"
        :value="stats.amount"
        icon="💰"
        suffix="تومان"
        accent="var(--gs-accent-2)"
        aura="rgba(69,214,139,.32)"
        :delay="320"
        :ratio="0.85"
      />
    </section>

    <!-- ================= نوار فرمان ================= -->
    <ArchiveFilterBar
      class="command-bar"
      :model-value="filters"
      :counts="tabCounts"
      :busy="busyId"
      @update:model-value="onFiltersUpdate"
      @apply="() => load(1)"
      @reset="handleReset"
      @export="api.exportExcel"
      @sync="handleSync"
    />

    <!-- ================= شبکهٔ رکوردها ================= -->
    <section class="records a3d-stage">
      <!-- حالت لودینگ -->
      <div v-if="loading" class="records-grid">
        <div v-for="n in 6" :key="`sk-${n}`" class="a3d-skeleton skeleton-card"></div>
      </div>

      <!-- حالت خطا -->
      <div v-else-if="error" class="state-box state-box--error">
        <span class="state-icon">⚠</span>
        <p class="state-title">دریافت اطلاعات ناموفق بود</p>
        <p class="state-text">{{ error }}</p>
        <button type="button" class="a3d-btn a3d-btn--sm" @click="load(pagination.current_page)">
          تلاش دوباره
        </button>
      </div>

      <!-- حالت خالی -->
      <div v-else-if="!records.length" class="state-box">
        <span class="state-icon">🗄</span>
        <p class="state-title">هنوز رکوردی در بایگانی نیست</p>
        <p class="state-text">
          با زدن دکمهٔ «همگام‌سازی موارد پرداخت‌شده»، تمام فاکتورها، درخواست‌ها و
          سرویس‌هایی که تسویه شده‌اند به‌صورت خودکار در بایگانی کپی می‌شوند.
        </p>
        <button
          type="button"
          class="a3d-btn a3d-btn--gold a3d-btn--sm"
          :disabled="busyId === 'sync'"
          @click="handleSync"
        >
          شروع همگام‌سازی
        </button>
      </div>

      <!-- شبکهٔ کارت‌ها -->
      <div v-else class="records-grid">
        <ArchiveRecordCard
          v-for="(record, index) in records"
          :key="record.id"
          :record="record"
          :busy-id="busyId"
          :delay="Math.min(index * 55, 400)"
          @view="openDetail"
          @transfer="askTransfer"
          @restore="askRestore"
          @destroy="askDestroy"
        />
      </div>

      <!-- صفحه‌بندی -->
      <nav v-if="pagination.last_page > 1" class="pagination">
        <button
          type="button"
          class="a3d-btn a3d-btn--sm"
          :disabled="pagination.current_page <= 1 || loading"
          @click="load(pagination.current_page - 1)"
        >
          → قبلی
        </button>

        <div class="page-dots">
          <button
            v-for="page in visiblePages"
            :key="page"
            type="button"
            class="page-dot"
            :class="{ 'is-active': page === pagination.current_page }"
            :disabled="loading"
            @click="load(page)"
          >
            {{ faNumber(page) }}
          </button>
        </div>

        <button
          type="button"
          class="a3d-btn a3d-btn--sm"
          :disabled="pagination.current_page >= pagination.last_page || loading"
          @click="load(pagination.current_page + 1)"
        >
          بعدی ←
        </button>
      </nav>
    </section>

    <!-- ================= کشوی جزئیات ================= -->
    <ArchiveDetailDrawer
      v-model="detailOpen"
      :record="detailRecord"
      :loading="detailLoading"
    />

    <!-- ================= مودال تأیید ================= -->
    <ArchiveConfirmDialog
      v-model="confirmOpen"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :highlight="confirmConfig.highlight"
      :confirm-label="confirmConfig.confirmLabel"
      :tone="confirmConfig.tone"
      :icon="confirmConfig.icon"
      :with-reason="confirmConfig.withReason"
      :loading="confirmLoading"
      @confirm="runConfirmedAction"
    />

    <!-- ================= نوتیفیکیشن‌ها ================= -->
    <ArchiveToaster :toasts="toasts" @dismiss="dismissToast" />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

import ArchiveScene from '@/Components/Archive/ArchiveScene.vue'
import ArchiveStatCard from '@/Components/Archive/ArchiveStatCard.vue'
import ArchiveFilterBar from '@/Components/Archive/ArchiveFilterBar.vue'
import ArchiveRecordCard from '@/Components/Archive/ArchiveRecordCard.vue'
import ArchiveDetailDrawer from '@/Components/Archive/ArchiveDetailDrawer.vue'
import ArchiveConfirmDialog from '@/Components/Archive/ArchiveConfirmDialog.vue'
import ArchiveToaster from '@/Components/Archive/ArchiveToaster.vue'

import { useArchiveApi, TYPE_META } from '@/Composables/useArchiveApi'

/* ---------------------------------------------------------------- API ---- */
const api = useArchiveApi()
const { records, pagination, filters, loading, busyId, error, stats } = api

/* ------------------------------------------------------------- Helpers --- */
const faNumber = (value) =>
  new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }).format(Number(value || 0))

const ratioOf = (count) => {
  const total = records.value.length || 1
  return Math.min(count / total, 1)
}

const tabCounts = computed(() => ({
  '': pagination.total,
  invoice: stats.value.invoices,
  request: stats.value.requests,
  service_job: stats.value.serviceJobs,
}))

const visiblePages = computed(() => {
  const { current_page: current, last_page: last } = pagination
  const pages = []
  const start = Math.max(1, current - 2)
  const end = Math.min(last, start + 4)
  for (let page = start; page <= end; page += 1) pages.push(page)
  return pages
})

/* --------------------------------------------------------------- Toast --- */
const toasts = ref([])
let toastSeed = 0

function pushToast(message, type = 'success', duration = 4200) {
  const id = ++toastSeed
  toasts.value.push({ id, message, type, duration })
  setTimeout(() => dismissToast(id), duration)
}

function dismissToast(id) {
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
}

/* ---------------------------------------------------------------- Load --- */
async function load(page = 1) {
  try {
    await api.fetchRecords(page)
  } catch {
    /* پیام خطا از طریق api.error نمایش داده می‌شود */
  }
}

function onFiltersUpdate(next) {
  Object.assign(filters, next)
}

function handleReset() {
  api.resetFilters()
  load(1)
}

async function handleSync() {
  try {
    const result = await api.syncPaid()
    const counts = result?.data ?? {}
    const summary = [
      `${faNumber(counts.invoice ?? 0)} فاکتور`,
      `${faNumber(counts.request ?? 0)} درخواست`,
      `${faNumber(counts.service_job ?? 0)} سرویس`,
    ].join(' · ')

    pushToast(`همگام‌سازی انجام شد — ${summary}`, 'success', 5200)
    await load(1)
  } catch (e) {
    pushToast(e.message, 'error', 5200)
  }
}

/* -------------------------------------------------------------- Detail --- */
const detailOpen = ref(false)
const detailRecord = ref(null)
const detailLoading = ref(false)

async function openDetail(record) {
  detailRecord.value = record
  detailOpen.value = true
  detailLoading.value = true

  try {
    const full = await api.fetchRecord(record.id)
    if (full) detailRecord.value = full
  } catch {
    /* در صورت خطا همان دادهٔ لیست نمایش داده می‌شود */
  } finally {
    detailLoading.value = false
  }
}

/* ------------------------------------------------------------- Confirm --- */
const confirmOpen = ref(false)
const confirmLoading = ref(false)
const pendingAction = ref(null)

const confirmConfig = reactive({
  title: '',
  message: '',
  highlight: '',
  confirmLabel: 'تأیید',
  tone: 'gold',
  icon: '⇥',
  withReason: true,
})

function describe(record) {
  const label = TYPE_META[record.source_type]?.label ?? 'سند'
  return `${label} «${record.title || '—'}» — مشتری: ${record.customer_name || 'نامشخص'}`
}

function askTransfer(record) {
  Object.assign(confirmConfig, {
    title: 'انتقال به بایگانی',
    message:
      'این رکورد از بخش اصلی خود (فاکتور / درخواست / سرویس) حذف می‌شود و فقط در بایگانی باقی می‌ماند. در صورت نیاز می‌توانید بعداً آن را بازیابی کنید.',
    highlight: describe(record),
    confirmLabel: 'انتقال بده',
    tone: 'gold',
    icon: '⇥',
    withReason: true,
  })

  pendingAction.value = {
    type: 'transfer',
    run: (reason) => api.transferArchiveRecord(record.id, reason),
    success: 'رکورد با موفقیت به بایگانی منتقل و از بخش اصلی حذف شد.',
  }

  confirmOpen.value = true
}

function askRestore(record) {
  Object.assign(confirmConfig, {
    title: 'بازیابی رکورد',
    message:
      'رکورد مبدأ دوباره در بخش اصلی خود فعال می‌شود و وضعیت بایگانی به «کپی‌شده» تغییر می‌کند.',
    highlight: describe(record),
    confirmLabel: 'بازیابی کن',
    tone: 'gold',
    icon: '↺',
    withReason: false,
  })

  pendingAction.value = {
    type: 'restore',
    run: () => api.restoreRecord(record.id),
    success: 'رکورد از بایگانی بازیابی شد و در بخش اصلی فعال است.',
  }

  confirmOpen.value = true
}

function askDestroy(record) {
  Object.assign(confirmConfig, {
    title: 'حذف ردیف بایگانی',
    message:
      'این عملیات فقط ردیف بایگانی را حذف می‌کند و روی رکورد مبدأ اثری ندارد. برای ادامه مطمئن شوید.',
    highlight: describe(record),
    confirmLabel: 'حذف کن',
    tone: 'danger',
    icon: '🗑',
    withReason: true,
  })

  pendingAction.value = {
    type: 'destroy',
    run: (reason) => api.destroyRecord(record.id, reason),
    success: 'ردیف بایگانی حذف شد.',
  }

  confirmOpen.value = true
}

async function runConfirmedAction(reason) {
  if (!pendingAction.value) return

  confirmLoading.value = true
  try {
    await pendingAction.value.run(reason)
    pushToast(pendingAction.value.success, 'success')
    confirmOpen.value = false
    pendingAction.value = null
    await load(pagination.current_page)
  } catch (e) {
    pushToast(e.message, 'error', 5600)
  } finally {
    confirmLoading.value = false
  }
}

/* ---------------------------------------------------------------- Init --- */
onMounted(() => load(1))
</script>

<style scoped>
/* ---------------- سربرگ صفحه ---------------- */
.page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding-bottom: 1.1rem;
}

.head-badges { display: flex; gap: 0.5rem; }

.head-badge {
  padding: 0.35rem 0.8rem;
  border-radius: 99px;
  background: var(--gs-gold-muted);
  border: 1px solid var(--gs-border);
  font-size: 0.74rem;
  color: var(--gs-text-secondary);
}

.head-badge b { color: var(--gs-gold); font-variant-numeric: tabular-nums; }

/* ---------------- HERO ---------------- */
.hero {
  position: relative;
  overflow: hidden;
  border-radius: var(--gs-radius-lg);
  border: 1px solid var(--gs-border);
  background: linear-gradient(140deg,
    color-mix(in srgb, var(--gs-bg-card-strong) 92%, transparent),
    color-mix(in srgb, var(--gs-bg-soft) 88%, transparent));
  padding: 1.9rem 1.6rem;
  margin-bottom: 1.25rem;
  box-shadow: var(--gs-shadow-sm);
}

.hero-content {
  position: relative;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
  flex-wrap: wrap;
}

.hero-text { max-width: 620px; }

.hero-kicker {
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.22em;
  color: var(--gs-gold);
  opacity: 0.85;
  margin-bottom: 0.5rem;
}

.hero-title {
  font-size: clamp(1.5rem, 3.2vw, 2.1rem);
  font-weight: 900;
  line-height: 1.45;
  color: var(--gs-text-primary);
  letter-spacing: -0.02em;
}

.hero-title-accent {
  background: var(--gs-gold-grad);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-desc {
  margin-top: 0.7rem;
  font-size: 0.84rem;
  line-height: 2;
  color: var(--gs-text-secondary);
  max-width: 560px;
}

.hero-actions {
  display: flex;
  gap: 0.6rem;
  margin-top: 1.15rem;
  flex-wrap: wrap;
}

.is-spinning { display: inline-block; animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.hero-cube-wrap {
  position: relative;
  display: grid;
  place-items: center;
  padding: 1rem 2.2rem;
  perspective: 800px;
}

.cube-shadow {
  position: absolute;
  bottom: 4px;
  width: 96px;
  height: 16px;
  border-radius: 50%;
  background: var(--gs-gold-glow);
  filter: blur(14px);
  opacity: 0.55;
}

/* ---------------- آمار ---------------- */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 0.9rem;
  margin-bottom: 1.25rem;
}

/* ---------------- نوار فرمان ---------------- */
.command-bar { margin-bottom: 1.25rem; }

/* ---------------- رکوردها ---------------- */
.records { position: relative; }

.records-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
  gap: 1rem;
}

.skeleton-card { height: 268px; border-radius: var(--gs-radius-lg); }

.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.55rem;
  padding: 3.4rem 1.5rem;
  border-radius: var(--gs-radius-lg);
  border: 1px dashed var(--gs-border);
  background: color-mix(in srgb, var(--gs-bg-card-strong) 60%, transparent);
  text-align: center;
}

.state-box--error { border-color: color-mix(in srgb, var(--gs-error) 40%, transparent); }

.state-icon { font-size: 2.4rem; opacity: 0.8; }

.state-title { font-size: 0.95rem; font-weight: 800; color: var(--gs-text-primary); }

.state-text {
  font-size: 0.78rem;
  line-height: 1.9;
  color: var(--gs-text-secondary);
  max-width: 460px;
}

/* ---------------- صفحه‌بندی ---------------- */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.8rem;
  margin-top: 1.5rem;
  flex-wrap: wrap;
}

.page-dots { display: flex; gap: 0.3rem; }

.page-dot {
  min-width: 34px;
  height: 34px;
  padding: 0 0.4rem;
  border-radius: 10px;
  border: 1px solid var(--gs-border-soft);
  background: var(--gs-bg-elevated);
  color: var(--gs-text-secondary);
  font-family: inherit;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  font-variant-numeric: tabular-nums;
  transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.page-dot:hover:not(:disabled) {
  transform: translateY(-2px);
  border-color: var(--gs-border-hover);
  color: var(--gs-gold);
}

.page-dot.is-active {
  background: var(--gs-gold-grad);
  color: #14100a;
  border-color: transparent;
  box-shadow: 0 6px 18px var(--gs-gold-glow);
}

@media (max-width: 900px) {
  .hero { padding: 1.5rem 1.15rem; }
  .hero-cube-wrap { display: none; }
  .records-grid { grid-template-columns: 1fr; }
}
</style>
