<!-- ==========================================================================
     GameStore · ArchiveDetailDrawer
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveDetailDrawer.vue

     کشوی جزئیات با افکت باز شدنِ درِ سه‌بعدی (rotateY) که کل محتوای
     snapshot_json را با رنگ‌بندی نحوی نمایش می‌دهد.
     ========================================================================== -->
<template>
  <Teleport to="body">
    <Transition name="drawer-fade">
      <div v-if="modelValue" class="drawer-overlay" @click.self="close">
        <Transition name="drawer-door" appear>
          <aside v-if="modelValue" class="drawer" role="dialog" aria-modal="true">
            <!-- سربرگ -->
            <header class="drawer-head">
              <div class="head-main">
                <span class="head-icon" :style="{ '--accent': meta.color }">{{ meta.icon }}</span>
                <div>
                  <p class="head-kicker">{{ meta.label }} · شناسهٔ بایگانی {{ record?.id ?? '—' }}</p>
                  <h2 class="head-title">{{ record?.title || 'جزئیات رکورد بایگانی' }}</h2>
                </div>
              </div>
              <button type="button" class="close-btn" aria-label="بستن" @click="close">✕</button>
            </header>

            <!-- بدنه -->
            <div class="drawer-body">
              <div v-if="loading" class="loading-block">
                <div class="a3d-skeleton" style="height:74px"></div>
                <div class="a3d-skeleton" style="height:74px"></div>
                <div class="a3d-skeleton" style="height:220px"></div>
              </div>

              <template v-else-if="record">
                <!-- خلاصهٔ کلیدی -->
                <section class="summary">
                  <div v-for="row in summaryRows" :key="row.label" class="summary-item">
                    <span class="summary-label">{{ row.label }}</span>
                    <span class="summary-value" :class="row.className">{{ row.value }}</span>
                  </div>
                </section>

                <!-- وضعیت -->
                <section class="status-row">
                  <span class="chip" :class="isTransferred ? 'chip--success' : 'chip--info'">
                    {{ isTransferred ? 'منتقل‌شده — رکورد مبدأ حذف شده' : 'کپی‌شده — رکورد مبدأ هنوز فعال است' }}
                  </span>
                  <span v-if="record.snapshot_hash" class="chip chip--muted" :title="record.snapshot_hash">
                    اثر انگشت: {{ String(record.snapshot_hash).slice(0, 16) }}…
                  </span>
                </section>

                <!-- خود درخواست (فقط برای نوع request) -->
                <section v-if="requestInfo" class="detail-block">
                  <h3 class="block-title">جزئیات درخواست</h3>
                  <div class="kv-grid">
                    <div class="kv-item">
                      <span class="kv-label">وضعیت درخواست</span>
                      <span class="kv-value">{{ requestInfo.statusLabel }}</span>
                    </div>
                    <div v-if="requestInfo.categories.length" class="kv-item">
                      <span class="kv-label">دسته‌بندی‌ها</span>
                      <span class="kv-value">
                        <span class="chip-list">
                          <span v-for="(c, i) in requestInfo.categories" :key="i" class="chip chip--muted">{{ c }}</span>
                        </span>
                      </span>
                    </div>
                  </div>
                  <div class="text-blocks">
                    <div class="text-block">
                      <span class="kv-label">شرح درخواست</span>
                      <p>{{ requestInfo.description }}</p>
                    </div>
                  </div>
                </section>

                <!-- مشخصات دستگاه (فقط سرویس) -->
                <section v-if="deviceInfo.length" class="detail-block">
                  <h3 class="block-title">مشخصات دستگاه</h3>
                  <div class="kv-grid">
                    <div v-for="row in deviceInfo" :key="row.label" class="kv-item">
                      <span class="kv-label">{{ row.label }}</span>
                      <span class="kv-value">{{ row.value }}</span>
                    </div>
                  </div>
                </section>

                <!-- نوع سرویس‌ها -->
                <section v-if="serviceTypes.length" class="detail-block">
                  <h3 class="block-title">نوع سرویس‌ها</h3>
                  <div class="chip-list">
                    <span v-for="(t, i) in serviceTypes" :key="i" class="chip chip--muted">
                      {{ t.name }}<template v-if="t.price"> — {{ formatMoney(t.price) }} تومان</template>
                    </span>
                  </div>
                </section>

                <!-- قطعات مصرفی سرویس -->
                <section v-if="serviceItems.length" class="detail-block">
                  <h3 class="block-title">قطعات مصرفی</h3>
                  <table class="items-table">
                    <thead>
                      <tr>
                        <th>نام</th>
                        <th>تعداد</th>
                        <th>قیمت واحد</th>
                        <th>جمع</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(it, i) in serviceItems" :key="i">
                        <td>{{ it.name }}</td>
                        <td>{{ it.quantity }}</td>
                        <td>{{ formatMoney(it.unitPrice) }}</td>
                        <td>{{ formatMoney(it.totalPrice) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <!-- شرح ایراد و تشخیص -->
                <section v-if="problemInfo.length" class="detail-block">
                  <h3 class="block-title">شرح ایراد و تشخیص</h3>
                  <div class="text-blocks">
                    <div v-for="row in problemInfo" :key="row.label" class="text-block">
                      <span class="kv-label">{{ row.label }}</span>
                      <p>{{ row.value }}</p>
                    </div>
                  </div>
                </section>

                <!-- اقلام فاکتور -->
                <section v-if="orderItems.length" class="detail-block">
                  <h3 class="block-title">اقلام فاکتور</h3>
                  <table class="items-table">
                    <thead>
                      <tr>
                        <th>نام</th>
                        <th>تعداد</th>
                        <th>قیمت واحد</th>
                        <th>جمع</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(it, i) in orderItems" :key="i">
                        <td>{{ it.name }}</td>
                        <td>{{ it.quantity }}</td>
                        <td>{{ formatMoney(it.unitPrice) }}</td>
                        <td>{{ formatMoney(it.totalPrice) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <!-- تعدیلات فاکتور -->
                <section v-if="adjustments.length" class="detail-block">
                  <h3 class="block-title">تعدیلات فاکتور</h3>
                  <ul class="adjustments-list">
                    <li v-for="(a, i) in adjustments" :key="i">
                      <span>{{ a.title }}</span>
                      <span :class="a.direction === 'increase' ? 'is-up' : 'is-down'">
                        {{ a.direction === 'increase' ? '➕' : '➖' }}
                        {{ formatMoney(a.value) }}{{ a.type === 'percentage' ? '٪' : ' تومان' }}
                      </span>
                    </li>
                  </ul>
                </section>

                <!-- دادهٔ خام: فقط قابل کپی، بدون نمایش روی صفحه -->
                <section class="snapshot">
                  <div class="snapshot-head">
                    <h3>دادهٔ خام بایگانی</h3>
                    <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="copySnapshot">
                      {{ copied ? '✓ کپی شد' : 'کپی JSON خام' }}
                    </button>
                  </div>
                </section>
              </template>

              <p v-else class="empty">رکوردی برای نمایش انتخاب نشده است.</p>
            </div>
          </aside>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { TYPE_META, ARCHIVE_STATUS, formatMoney, formatDateTime } from '@/Composables/useArchiveApi'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  record: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const copied = ref(false)

function close() {
  emit('update:modelValue', false)
}

const meta = computed(() => TYPE_META[props.record?.source_type] ?? {
  label: 'سند',
  icon: '◈',
  color: 'var(--gs-gold)',
})

const isTransferred = computed(() => props.record?.archive_status === ARCHIVE_STATUS.TRANSFERRED)

const summaryRows = computed(() => {
  const r = props.record
  if (!r) return []
  return [
    { label: 'مشتری', value: r.customer_name || 'نامشخص' },
    { label: 'شمارهٔ فاکتور', value: r.invoice_number || '—' },
    { label: 'مبلغ کل', value: `${formatMoney(r.total_amount)} تومان`, className: 'is-gold' },
    { label: 'وضعیت پرداخت', value: r.payment_status === 'paid' ? 'پرداخت‌شده' : (r.payment_status || '—') },
    { label: 'تاریخ پرداخت', value: formatDateTime(r.paid_at) },
    { label: 'تاریخ بایگانی', value: formatDateTime(r.archived_at) },
    { label: 'ایجاد رکورد مبدأ', value: formatDateTime(r.source_created_at) },
    { label: 'جدول مبدأ', value: r.source_table || '—' },
  ]
})

/* ---------- دادهٔ خام (فقط برای کپی، دیگر روی صفحه رندر نمی‌شود) ---------- */
const snapshotText = computed(() => {
  const snapshot = props.record?.snapshot_json
  if (!snapshot) return '{}'
  if (typeof snapshot === 'string') {
    try {
      return JSON.stringify(JSON.parse(snapshot), null, 2)
    } catch {
      return snapshot
    }
  }
  return JSON.stringify(snapshot, null, 2)
})

/* ---------- شیء snapshot به‌صورت آبجکت (برای استخراج بخش‌های شیک) ---------- */
const snapshotObj = computed(() => {
  const snapshot = props.record?.snapshot_json
  if (!snapshot) return {}
  if (typeof snapshot === 'string') {
    try {
      return JSON.parse(snapshot)
    } catch {
      return {}
    }
  }
  return snapshot
})

const isServiceJob = computed(() => props.record?.source_type === 'service_job')
const isRequest = computed(() => props.record?.source_type === 'request')

const REQUEST_STATUS_LABELS = {
  processing: 'در حال بررسی',
  in_progress: 'در حال انجام',
  completed: 'تکمیل‌شده',
  canceled: 'لغو‌شده',
}

/* جزئیات خود درخواست — فقط برای رکوردهای نوع request */
const requestInfo = computed(() => {
  const s = snapshotObj.value?.source
  if (!s || !isRequest.value) return null
  const status = s.status
  return {
    statusLabel: REQUEST_STATUS_LABELS[status] || status || '—',
    description: s.description || '—',
    categories: Array.isArray(s.categories) ? s.categories.map((c) => c?.name).filter(Boolean) : [],
  }
})

/* مشخصات دستگاه — فقط برای رکوردهای نوع سرویس */
const deviceInfo = computed(() => {
  const s = snapshotObj.value?.source
  if (!s || !isServiceJob.value) return []
  return [
    { label: 'نوع دستگاه', value: s.device_type || '—' },
    { label: 'سریال دستگاه', value: s.device_serial || '—' },
    { label: 'تاریخ دریافت دستگاه', value: formatDateTime(s.received_at) },
    { label: 'تاریخ تحویل دستگاه', value: formatDateTime(s.delivered_at) },
  ]
})

/* شرح ایراد مشتری و تشخیص تکنسین */
const problemInfo = computed(() => {
  const s = snapshotObj.value?.source
  if (!s || !isServiceJob.value) return []
  const rows = []
  if (s.customer_problem_description) rows.push({ label: 'شرح ایراد مشتری', value: s.customer_problem_description })
  if (s.diagnosis_description) rows.push({ label: 'شرح تشخیص تکنسین', value: s.diagnosis_description })
  return rows
})

/* نوع سرویس‌ها (هر کدام می‌تواند قیمت مستقل خودش را داشته باشد) */
const serviceTypes = computed(() => {
  const types = snapshotObj.value?.source?.service_types
  if (!Array.isArray(types)) return []
  return types
    .map((st) => ({ name: st?.service_type?.name || '—', price: Number(st?.price) || 0 }))
    .filter((t) => t.name !== '—')
})

/* قطعات مصرفی سرویس — کلید قیمت واقعی unit_price است نه price */
const serviceItems = computed(() => {
  const items = snapshotObj.value?.source?.items
  if (!Array.isArray(items)) return []
  return items.map((it) => ({
    name: it?.item?.name || it?.product_name || '—',
    quantity: it?.quantity ?? 1,
    unitPrice: Number(it?.unit_price) || 0,
    totalPrice: Number(it?.total_price) || 0,
  }))
})

/* اقلام فاکتور پرداخت‌شده — همیشه از paid_invoice، مستقل از نوع رکورد */
const orderItems = computed(() => {
  const items = snapshotObj.value?.paid_invoice?.order_items
  if (!Array.isArray(items)) return []
  return items.map((it) => ({
    name: it?.product_name || it?.item?.name || '—',
    quantity: it?.quantity ?? 1,
    unitPrice: Number(it?.price) || 0,
    totalPrice: Number(it?.total_price) || 0,
  }))
})

/* تعدیلات فاکتور (تخفیف/اضافه) */
const adjustments = computed(() => {
  const list = snapshotObj.value?.paid_invoice?.adjustments
  if (!Array.isArray(list)) return []
  return list.map((a) => ({
    title: a?.title || '—',
    direction: a?.direction,
    type: a?.type,
    value: Number(a?.value) || 0,
  }))
})

async function copySnapshot() {
  try {
    await navigator.clipboard.writeText(snapshotText.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1800)
  } catch {
    copied.value = false
  }
}

/* بستن با کلید Escape + قفل اسکرول صفحه */
function onKeydown(event) {
  if (event.key === 'Escape') close()
}

watch(() => props.modelValue, (open) => {
  if (typeof document === 'undefined') return
  if (open) {
    document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
  } else {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
  }
})
</script>

<style scoped>
.drawer-overlay {
  position: fixed;
  inset: 0;
  z-index: 900;
  display: flex;
  justify-content: flex-start;
  background: rgba(0, 0, 0, 0.62);
  backdrop-filter: blur(6px);
  perspective: 1800px;
}

.drawer {
  width: min(560px, 100%);
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--gs-bg-card-strong);
  border-left: 1px solid var(--gs-border-hover);
  box-shadow: 24px 0 70px rgba(0, 0, 0, 0.6);
  transform-origin: left center;
}

/* ---------- سربرگ ---------- */
.drawer-head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid var(--gs-border);
  background: var(--gs-bg-soft);
}

.head-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.head-icon {
  width: 46px;
  height: 46px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  font-size: 1.25rem;
  border-radius: 14px;
  background: color-mix(in srgb, var(--accent) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--accent) 32%, transparent);
}

.head-kicker {
  font-size: 0.68rem;
  color: var(--gs-text-muted);
  font-weight: 600;
}

.head-title {
  font-size: 1rem;
  font-weight: 800;
  color: var(--gs-text-primary);
  line-height: 1.5;
}

.close-btn {
  flex-shrink: 0;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid var(--gs-border);
  background: transparent;
  color: var(--gs-text-secondary);
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.25s ease;
}

.close-btn:hover {
  background: var(--gs-error-soft);
  color: var(--gs-error);
  border-color: color-mix(in srgb, var(--gs-error) 40%, transparent);
  transform: rotate(90deg);
}

/* ---------- بدنه ---------- */
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.1rem 1.25rem 2rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.loading-block {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.55rem;
}

.summary-item {
  padding: 0.6rem 0.7rem;
  border-radius: 12px;
  background: var(--gs-bg-elevated);
  border: 1px solid var(--gs-border-soft);
}

.summary-label {
  display: block;
  font-size: 0.65rem;
  color: var(--gs-text-muted);
  font-weight: 600;
  margin-bottom: 0.2rem;
}

.summary-value {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--gs-text-primary);
  word-break: break-word;
}

.is-gold {
  color: var(--gs-gold);
}

.status-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.chip {
  padding: 0.32rem 0.7rem;
  border-radius: 99px;
  font-size: 0.68rem;
  font-weight: 700;
}

.chip--info {
  background: var(--gs-info-soft);
  color: var(--gs-info);
  border: 1px solid color-mix(in srgb, var(--gs-info) 30%, transparent);
}

.chip--success {
  background: var(--gs-success-soft);
  color: var(--gs-success);
  border: 1px solid color-mix(in srgb, var(--gs-success) 30%, transparent);
}

.chip--muted {
  background: var(--gs-glass);
  color: var(--gs-text-muted);
  border: 1px solid var(--gs-border-soft);
  font-variant-numeric: tabular-nums;
}

/* ---------- بخش‌های جزئیات (مشترک) ---------- */
.detail-block {
  display: flex;
  flex-direction: column;
}

.block-title {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--gs-text-secondary);
  margin-bottom: 0.5rem;
}

.kv-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.55rem;
}

.kv-item {
  padding: 0.6rem 0.7rem;
  border-radius: 12px;
  background: var(--gs-bg-elevated);
  border: 1px solid var(--gs-border-soft);
}

.kv-label {
  display: block;
  font-size: 0.65rem;
  color: var(--gs-text-muted);
  font-weight: 600;
  margin-bottom: 0.2rem;
}

.kv-value {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--gs-text-primary);
  word-break: break-word;
}

.chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.76rem;
}

.items-table th,
.items-table td {
  padding: 0.45rem 0.5rem;
  border: 1px solid var(--gs-border-soft);
  text-align: center;
}

.items-table th {
  background: var(--gs-bg-soft);
  color: var(--gs-text-secondary);
  font-weight: 700;
}

.items-table td {
  color: var(--gs-text-primary);
}

.adjustments-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.adjustments-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.45rem 0.6rem;
  border-radius: 10px;
  background: var(--gs-bg-elevated);
  border: 1px solid var(--gs-border-soft);
  font-size: 0.76rem;
  color: var(--gs-text-primary);
}

.is-up {
  color: var(--gs-success);
  font-weight: 700;
}

.is-down {
  color: var(--gs-error);
  font-weight: 700;
}

.text-blocks {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.text-block p {
  margin-top: 0.25rem;
  font-size: 0.78rem;
  line-height: 1.7;
  color: var(--gs-text-primary);
  background: var(--gs-bg-elevated);
  border: 1px solid var(--gs-border-soft);
  border-radius: 10px;
  padding: 0.6rem 0.7rem;
  white-space: pre-wrap;
}

/* ---------- دادهٔ خام (فقط دکمهٔ کپی، بدون نمایش) ---------- */
.snapshot-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
}

.snapshot-head h3 {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--gs-text-secondary);
}

.empty {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--gs-text-muted);
  font-size: 0.85rem;
}

@media (max-width: 620px) {
  .kv-grid {
    grid-template-columns: 1fr;
  }
}

/* ---------- ترنزیشن‌ها ---------- */
.drawer-fade-enter-active,
.drawer-fade-leave-active {
  transition: opacity 0.3s ease;
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
  opacity: 0;
}

.drawer-door-enter-active {
  transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s ease;
}

.drawer-door-leave-active {
  transition: transform 0.35s ease-in, opacity 0.3s ease;
}

.drawer-door-enter-from,
.drawer-door-leave-to {
  opacity: 0;
  transform: rotateY(-38deg) translateX(-70px);
}

@media (max-width: 620px) {
  .summary {
    grid-template-columns: 1fr;
  }
}
</style>
