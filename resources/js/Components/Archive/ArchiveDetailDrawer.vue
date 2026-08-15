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

                <!-- اسنپ‌شات -->
                <section class="snapshot">
                  <div class="snapshot-head">
                    <h3>نسخهٔ منجمدشدهٔ داده (snapshot_json)</h3>
                    <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="copySnapshot">
                      {{ copied ? '✓ کپی شد' : 'کپی JSON' }}
                    </button>
                  </div>
                  <pre class="snapshot-code" dir="ltr" v-html="highlightedSnapshot"></pre>
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

/* ---------- رنگ‌بندی نحوی JSON ---------- */
function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

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

const highlightedSnapshot = computed(() => {
  const escaped = escapeHtml(snapshotText.value)
  return escaped.replace(
    /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?)/g,
    (match) => {
      let cls = 'tok-num'
      if (/^"/.test(match)) {
        cls = /:$/.test(match) ? 'tok-key' : 'tok-str'
      } else if (/true|false/.test(match)) {
        cls = 'tok-bool'
      } else if (/null/.test(match)) {
        cls = 'tok-null'
      }
      return `<span class="${cls}">${match}</span>`
    }
  )
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

.head-main { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }

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

.head-kicker { font-size: 0.68rem; color: var(--gs-text-muted); font-weight: 600; }

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

.loading-block { display: flex; flex-direction: column; gap: 0.75rem; }

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

.is-gold { color: var(--gs-gold); }

.status-row { display: flex; flex-wrap: wrap; gap: 0.45rem; }

.chip {
  padding: 0.32rem 0.7rem;
  border-radius: 99px;
  font-size: 0.68rem;
  font-weight: 700;
}

.chip--info { background: var(--gs-info-soft); color: var(--gs-info); border: 1px solid color-mix(in srgb, var(--gs-info) 30%, transparent); }
.chip--success { background: var(--gs-success-soft); color: var(--gs-success); border: 1px solid color-mix(in srgb, var(--gs-success) 30%, transparent); }
.chip--muted { background: var(--gs-glass); color: var(--gs-text-muted); border: 1px solid var(--gs-border-soft); font-variant-numeric: tabular-nums; }

/* ---------- اسنپ‌شات ---------- */
.snapshot-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  margin-bottom: 0.5rem;
}

.snapshot-head h3 { font-size: 0.78rem; font-weight: 700; color: var(--gs-text-secondary); }

.snapshot-code {
  max-height: 420px;
  overflow: auto;
  padding: 0.9rem;
  border-radius: 12px;
  background: #07070b;
  border: 1px solid var(--gs-border-soft);
  font-family: 'Fira Code', 'Cascadia Code', Consolas, monospace;
  font-size: 0.72rem;
  line-height: 1.85;
  color: #b9b3a4;
  white-space: pre;
  text-align: left;
}

.snapshot-code :deep(.tok-key)  { color: #e3bd5c; }
.snapshot-code :deep(.tok-str)  { color: #45d68b; }
.snapshot-code :deep(.tok-num)  { color: #5b9df0; }
.snapshot-code :deep(.tok-bool) { color: #9f7bf6; }
.snapshot-code :deep(.tok-null) { color: #f06a6a; }

.empty { text-align: center; padding: 3rem 1rem; color: var(--gs-text-muted); font-size: 0.85rem; }

/* ---------- ترنزیشن‌ها ---------- */
.drawer-fade-enter-active,
.drawer-fade-leave-active { transition: opacity 0.3s ease; }
.drawer-fade-enter-from,
.drawer-fade-leave-to { opacity: 0; }

.drawer-door-enter-active { transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s ease; }
.drawer-door-leave-active { transition: transform 0.35s ease-in, opacity 0.3s ease; }
.drawer-door-enter-from,
.drawer-door-leave-to {
  opacity: 0;
  transform: rotateY(-38deg) translateX(-70px);
}

@media (max-width: 620px) {
  .summary { grid-template-columns: 1fr; }
}
</style>
