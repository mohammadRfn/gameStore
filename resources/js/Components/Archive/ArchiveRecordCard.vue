<!-- ==========================================================================
     GameStore · ArchiveRecordCard
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveRecordCard.vue

     کارت سه‌بعدی هر ردیف بایگانی:
       • تیلت واقعی + هالهٔ رنگی بر اساس نوع سند
       • ریبون وضعیت (کپی‌شده / منتقل‌شده)
       • دکمهٔ «انتقال به بایگانی» فقط برای رکوردهای کپی‌شده
       • دکمهٔ «بازیابی» فقط برای رکوردهای منتقل‌شده
     ========================================================================== -->
<template>
  <article
    v-tilt="{ max: 8, scale: 1.015, lift: 14 }"
    v-reveal="{ delay }"
    class="a3d-holo a3d-aura record-card"
    :style="{ '--a3d-aura-color': meta.aura, '--accent': meta.color }"
  >
    <!-- ریبون وضعیت -->
    <div class="ribbon" :class="isTransferred ? 'ribbon--transferred' : 'ribbon--copied'">
      {{ isTransferred ? 'منتقل‌شده' : 'کپی‌شده' }}
    </div>

    <div class="a3d-layer card-inner">
      <!-- سربرگ -->
      <header class="card-head">
        <div class="type-badge a3d-z-45">
          <span class="type-icon">{{ meta.icon }}</span>
        </div>

        <div class="head-text a3d-z-20">
          <p class="type-name">
            {{ meta.label }}
            <span class="src-id">#{{ record.source_id }}</span>
          </p>
          <h3 class="card-title" :title="record.title">{{ record.title || '—' }}</h3>
        </div>
      </header>

      <!-- بدنه -->
      <dl class="card-grid a3d-z-10">
        <div class="cell">
          <dt>مشتری</dt>
          <dd>{{ record.customer_name || 'نامشخص' }}</dd>
        </div>
        <div class="cell">
          <dt>شمارهٔ فاکتور</dt>
          <dd class="mono">{{ record.invoice_number || '—' }}</dd>
        </div>
        <div class="cell">
          <dt>مبلغ کل</dt>
          <dd class="amount">{{ money }} <small>تومان</small></dd>
        </div>
        <div class="cell">
          <dt>تاریخ بایگانی</dt>
          <dd>{{ archivedAt }}</dd>
        </div>
      </dl>

      <p v-if="record.reason" class="reason a3d-z-10">
        <span>📝</span> {{ record.reason }}
      </p>

      <!-- اکشن‌ها -->
      <footer class="card-actions a3d-z-30">
        <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="$emit('view', record)">
          جزئیات
        </button>

        <button
          v-if="!isTransferred"
          type="button"
          class="a3d-btn a3d-btn--gold a3d-btn--sm transfer-btn"
          :disabled="isBusy"
          @click="$emit('transfer', record)"
        >
          <span v-if="isBusy" class="dot-loader"></span>
          <span v-else>⇥</span>
          انتقال به بایگانی
        </button>

        <button
          v-else
          type="button"
          class="a3d-btn a3d-btn--sm"
          :disabled="isBusy"
          @click="$emit('restore', record)"
        >
          <span v-if="isBusy" class="dot-loader"></span>
          <span v-else>↺</span>
          بازیابی
        </button>

        <button
          type="button"
          class="a3d-btn a3d-btn--danger a3d-btn--sm"
          :disabled="isBusy"
          title="حذف ردیف بایگانی"
          @click="$emit('destroy', record)"
        >
          🗑
        </button>
      </footer>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { vTilt, vReveal } from '@/Composables/useTilt'
import { TYPE_META, ARCHIVE_STATUS, formatMoney, formatDateTime } from '@/Composables/useArchiveApi'

const props = defineProps({
  record: { type: Object, required: true },
  busyId: { type: [String, null], default: null },
  delay: { type: Number, default: 0 },
})

defineEmits(['view', 'transfer', 'restore', 'destroy'])

const meta = computed(() => TYPE_META[props.record.source_type] ?? {
  label: 'سند',
  icon: '◈',
  color: 'var(--gs-gold)',
  aura: 'var(--gs-gold-glow)',
})

const isTransferred = computed(() => props.record.archive_status === ARCHIVE_STATUS.TRANSFERRED)

const isBusy = computed(() => {
  const id = props.record.id
  return props.busyId === `transfer-${id}`
    || props.busyId === `restore-${id}`
    || props.busyId === `destroy-${id}`
})

const money = computed(() => formatMoney(props.record.total_amount))
const archivedAt = computed(() => formatDateTime(props.record.archived_at))
</script>

<style scoped>
.record-card {
  position: relative;
  padding: 1.1rem 1.15rem 1rem;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.card-inner {
  position: relative;
  z-index: 4;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  height: 100%;
  transform-style: preserve-3d;
}

/* ---------- ریبون ---------- */
.ribbon {
  position: absolute;
  top: 0.85rem;
  left: 0.85rem;
  z-index: 6;
  padding: 0.2rem 0.6rem;
  border-radius: 99px;
  font-size: 0.63rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  backdrop-filter: blur(6px);
}

.ribbon--copied {
  background: var(--gs-info-soft);
  color: var(--gs-info);
  border: 1px solid color-mix(in srgb, var(--gs-info) 34%, transparent);
}

.ribbon--transferred {
  background: var(--gs-success-soft);
  color: var(--gs-success);
  border: 1px solid color-mix(in srgb, var(--gs-success) 34%, transparent);
}

/* ---------- سربرگ ---------- */
.card-head {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
}

.type-badge {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: color-mix(in srgb, var(--accent) 13%, transparent);
  border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
  box-shadow: 0 10px 24px color-mix(in srgb, var(--accent) 20%, transparent);
}

.type-icon { font-size: 1.2rem; }

.head-text { min-width: 0; flex: 1; }

.type-name {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--accent);
  letter-spacing: 0.04em;
}

.src-id {
  color: var(--gs-text-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.card-title {
  margin-top: 0.2rem;
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--gs-text-primary);
  line-height: 1.55;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ---------- جدول اطلاعات ---------- */
.card-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.55rem 0.7rem;
  padding: 0.75rem;
  border-radius: 12px;
  background: color-mix(in srgb, var(--gs-bg) 42%, transparent);
  border: 1px solid var(--gs-border-soft);
}

.cell { min-width: 0; }

.cell dt {
  font-size: 0.64rem;
  font-weight: 600;
  color: var(--gs-text-muted);
  margin-bottom: 0.12rem;
}

.cell dd {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--gs-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mono { font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }

.amount { color: var(--gs-gold); }
.amount small { font-size: 0.62rem; font-weight: 600; color: var(--gs-text-muted); }

.reason {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  font-size: 0.71rem;
  line-height: 1.7;
  color: var(--gs-text-secondary);
  padding: 0.45rem 0.6rem;
  border-radius: 10px;
  background: var(--gs-gold-muted);
  border: 1px dashed var(--gs-border);
}

/* ---------- اکشن‌ها ---------- */
.card-actions {
  margin-top: auto;
  padding-top: 0.35rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.transfer-btn { flex: 1; min-width: 150px; }

/* لودر نقطه‌ای */
.dot-loader {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid currentColor;
  border-top-color: transparent;
  display: inline-block;
  animation: dot-spin 0.7s linear infinite;
}

@keyframes dot-spin { to { transform: rotate(360deg); } }

@media (max-width: 480px) {
  .card-grid { grid-template-columns: 1fr; }
  .transfer-btn { min-width: 100%; }
}
</style>
