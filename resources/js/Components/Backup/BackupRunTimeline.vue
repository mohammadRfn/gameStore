<script setup>
import { computed } from 'vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import { faNumber, formatBytes, formatDateTime, presentDirection, presentMode, presentStatus } from '@/Composables/useBackupCenter'

/**
 * تاریخچه‌ی اجراها — نسخه‌ی هماهنگ با تم (dark/light).
 * منطق و emit مثل قبل؛ رنگ‌های ثابت با توکن --gs-* جایگزین شد.
 */
const props = defineProps({
  runs: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0 }) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:filters', 'refresh', 'page', 'view', 'log', 'delete'])

/* هر تون به یک متغیر --gs-* نگاشت می‌شود (به‌جای رنگ ثابت Tailwind) */
const statusToneColor = {
  emerald: 'var(--gs-success)',
  amber: 'var(--gs-warning)',
  rose: 'var(--gs-error)',
  sky: 'var(--gs-info)',
  slate: 'var(--gs-text-secondary)',
}

const canPrev = computed(() => (props.pagination.current_page || 1) > 1)
const canNext = computed(() => (props.pagination.current_page || 1) < (props.pagination.last_page || 1))

function patch(key, value) {
  emit('update:filters', { ...props.filters, [key]: value })
}

function statusStyle(status) {
  const c = statusToneColor[presentStatus(status).tone] || statusToneColor.slate
  return {
    background: `color-mix(in srgb, ${c} 12%, transparent)`,
    borderColor: `color-mix(in srgb, ${c} 26%, transparent)`,
    color: c,
  }
}
</script>

<template>
  <BackupCard
    title="تاریخچه‌ی پشتیبان‌گیری"
    description="همه‌ی خروجی‌ها، بازیابی‌ها و اجراهای آزمایشی به‌ترتیب زمان."
    icon="🕰"
  >
    <template #actions>
      <button type="button" class="bk-btn-soft" @click="emit('refresh')">به‌روزرسانی</button>
    </template>

    <div class="bk-filters">
      <select class="bk-select" :value="filters.direction" @change="patch('direction', $event.target.value)">
        <option value="">همه‌ی عملیات‌ها</option>
        <option value="export">خروجی گرفتن</option>
        <option value="import">بازیابی</option>
      </select>

      <select class="bk-select" :value="filters.status" @change="patch('status', $event.target.value)">
        <option value="">همه‌ی وضعیت‌ها</option>
        <option value="completed">موفق</option>
        <option value="partial">نیمه‌موفق</option>
        <option value="failed">ناموفق</option>
        <option value="running">در حال اجرا</option>
      </select>

      <select class="bk-select" :value="filters.mode" @change="patch('mode', $event.target.value)">
        <option value="">همه‌ی نوع‌ها</option>
        <option value="full">بسته کامل</option>
        <option value="database">فقط اطلاعات</option>
        <option value="media">فقط تصاویر</option>
      </select>

      <label class="bk-check">
        <input
          type="checkbox"
          :checked="filters.include_auto"
          @change="patch('include_auto', $event.target.checked)"
        >
        نمایش نسخه‌های خودکار
      </label>
    </div>

    <div class="bk-runs">
      <p v-if="loading" class="bk-empty">در حال دریافت تاریخچه…</p>
      <p v-else-if="!runs.length" class="bk-empty">هنوز هیچ عملیاتی ثبت نشده است.</p>

      <template v-else>
        <article v-for="run in runs" :key="run.id" class="bk-run">
          <div class="bk-run__main">
            <span class="bk-run__icon" aria-hidden="true">{{ presentDirection(run.direction).icon }}</span>

            <div class="bk-run__info">
              <p class="bk-run__title">
                {{ presentDirection(run.direction).label }} · {{ presentMode(run.mode).label }}
                <span v-if="run.is_dry_run" class="bk-run__dry">(آزمایشی)</span>
              </p>
              <p class="bk-run__time">
                {{ formatDateTime(run.started_at || run.created_at) }}
                <span v-if="run.label"> · {{ run.label }}</span>
              </p>
            </div>
          </div>

          <div class="bk-run__side">
            <span class="bk-status" :style="statusStyle(run.status)">
              {{ presentStatus(run.status).icon }} {{ presentStatus(run.status).label }}
            </span>

            <span class="bk-tag">{{ faNumber(run.records_count || 0) }} رکورد</span>
            <span class="bk-tag">{{ formatBytes(run.size_bytes || run.total_bytes || 0) }}</span>

            <span class="bk-run__divider" aria-hidden="true" />

            <button type="button" class="bk-btn-soft bk-btn-soft--sm" @click="emit('view', run)">جزئیات</button>
            <button type="button" class="bk-btn-soft bk-btn-soft--sm" @click="emit('log', run)">گزارش</button>
            <button type="button" class="bk-btn-danger" @click="emit('delete', run)">حذف</button>
          </div>
        </article>
      </template>
    </div>

    <template #footer>
      <div class="bk-pager">
        <p class="bk-pager__info">
          صفحه {{ faNumber(pagination.current_page) }} از {{ faNumber(pagination.last_page) }} ·
          {{ faNumber(pagination.total) }} مورد
        </p>

        <div class="bk-pager__nav">
          <button type="button" class="bk-btn-soft bk-btn-soft--sm" :disabled="!canPrev" @click="emit('page', pagination.current_page - 1)">قبلی</button>
          <button type="button" class="bk-btn-soft bk-btn-soft--sm" :disabled="!canNext" @click="emit('page', pagination.current_page + 1)">بعدی</button>
        </div>
      </div>
    </template>
  </BackupCard>
</template>

<style scoped>
.bk-btn-soft {
  height: 2rem;
  border-radius: .5rem;
  border: 1px solid var(--gs-border-soft);
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  padding: 0 .75rem;
  font-size: .75rem;
  font-weight: 600;
  color: var(--gs-text-primary);
  transition: background-color .2s ease, border-color .2s ease;
}
.bk-btn-soft:hover:enabled {
  background: color-mix(in srgb, var(--gs-text-primary) 11%, transparent);
  border-color: var(--gs-border);
}
.bk-btn-soft:disabled { opacity: .4; cursor: not-allowed; }
.bk-btn-soft--sm { height: 2.25rem; }

.bk-btn-danger {
  height: 2rem;
  border-radius: .5rem;
  border: 1px solid color-mix(in srgb, var(--gs-error) 24%, transparent);
  background: var(--gs-error-soft);
  padding: 0 .75rem;
  font-size: .75rem;
  font-weight: 600;
  color: var(--gs-error);
  transition: background-color .2s ease;
}
.bk-btn-danger:hover { background: color-mix(in srgb, var(--gs-error) 20%, transparent); }

.bk-filters {
  display: grid;
  gap: .75rem;
  grid-template-columns: 1fr;
}
@media (min-width: 640px) { .bk-filters { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1280px) { .bk-filters { grid-template-columns: repeat(4, 1fr); } }

.bk-select {
  height: 2.75rem;
  width: 100%;
  border-radius: .75rem;
  border: 1px solid var(--gs-border);
  background: var(--gs-bg-soft);
  padding: 0 .75rem;
  font-size: 13px;
  color: var(--gs-text-primary);
  outline: none;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.bk-select:focus {
  border-color: var(--gs-border-hover);
  box-shadow: 0 0 0 3px var(--gs-gold-muted);
}

.bk-check {
  display: flex;
  height: 2.75rem;
  align-items: center;
  gap: .5rem;
  border-radius: .75rem;
  border: 1px solid var(--gs-border-soft);
  background: color-mix(in srgb, var(--gs-text-primary) 3%, transparent);
  padding: 0 .875rem;
  font-size: 13px;
  color: var(--gs-text-primary);
}
.bk-check input { width: 1rem; height: 1rem; accent-color: var(--gs-gold); }

.bk-runs {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}

.bk-empty {
  border-radius: .75rem;
  background: color-mix(in srgb, var(--gs-text-primary) 3%, transparent);
  padding: 1.5rem 1rem;
  text-align: center;
  font-size: .75rem;
  color: var(--gs-text-muted);
}

.bk-run {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  border-radius: .75rem;
  border: 1px solid var(--gs-border-soft);
  background: color-mix(in srgb, var(--gs-text-primary) 3%, transparent);
  padding: 1rem;
  transition: background-color .2s ease;
}
.bk-run:hover { background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent); }
@media (min-width: 1024px) { .bk-run { flex-direction: row; align-items: center; } }

.bk-run__main {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: center;
  gap: .75rem;
}
.bk-run__icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  border-radius: .75rem;
  font-size: 1rem;
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  border: 1px solid var(--gs-border-soft);
}
.bk-run__info { min-width: 0; }
.bk-run__title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 700;
  color: var(--gs-text-primary);
}
.bk-run__dry { color: var(--gs-warning); }
.bk-run__time {
  margin-top: .125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 11px;
  line-height: 1.25rem;
  color: var(--gs-text-muted);
}

.bk-run__side {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem;
}
@media (min-width: 1024px) { .bk-run__side { justify-content: flex-end; } }

.bk-status {
  border-radius: 999px;
  border: 1px solid transparent;
  padding: .25rem .625rem;
  font-size: 11px;
  font-weight: 600;
  line-height: 1rem;
}

.bk-tag {
  border-radius: 999px;
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  border: 1px solid var(--gs-border-soft);
  padding: .25rem .625rem;
  font-size: 11px;
  line-height: 1rem;
  color: var(--gs-text-secondary);
}

.bk-run__divider {
  margin: 0 .25rem;
  display: none;
  height: 1.5rem;
  width: 1px;
  background: var(--gs-border);
}
@media (min-width: 1024px) { .bk-run__divider { display: block; } }

.bk-pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
}
.bk-pager__info { font-size: 11px; color: var(--gs-text-muted); }
.bk-pager__nav { display: flex; align-items: center; gap: .5rem; }
</style>
