<script setup>
import { computed } from 'vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import { faNumber, formatBytes, formatDateTime, presentDirection, presentMode, presentStatus } from '@/Composables/useBackupCenter'

/**
 * تاریخچه‌ی اجراها.
 * تغییرات: ردیف‌ها با فاصله‌ی یکسان، ستون‌ها هم‌تراز و برچسب وضعیت‌ها فارسی است.
 */
const props = defineProps({
  runs: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0 }) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:filters', 'refresh', 'page', 'view', 'log', 'delete'])

const statusTones = {
  emerald: 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/20',
  amber: 'bg-amber-400/10 text-amber-300 ring-amber-400/20',
  rose: 'bg-rose-400/10 text-rose-300 ring-rose-400/20',
  sky: 'bg-sky-400/10 text-sky-300 ring-sky-400/20',
  slate: 'bg-white/5 text-slate-300 ring-white/10',
}

const canPrev = computed(() => (props.pagination.current_page || 1) > 1)
const canNext = computed(() => (props.pagination.current_page || 1) < (props.pagination.last_page || 1))

function patch(key, value) {
  emit('update:filters', { ...props.filters, [key]: value })
}

function statusClass(status) {
  return statusTones[presentStatus(status).tone] || statusTones.slate
}
</script>

<template>
  <BackupCard
    title="تاریخچه‌ی پشتیبان‌گیری"
    description="همه‌ی خروجی‌ها، بازیابی‌ها و اجراهای آزمایشی به‌ترتیب زمان."
    icon="🕰"
  >
    <template #actions>
      <button
        type="button"
        class="h-8 rounded-lg bg-white/5 px-3 text-xs font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10"
        @click="emit('refresh')"
      >
        به‌روزرسانی
      </button>
    </template>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <select
        class="h-11 w-full rounded-xl bg-slate-950/50 px-3 text-[13px] text-slate-200 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60"
        :value="filters.direction"
        @change="patch('direction', $event.target.value)"
      >
        <option value="">همه‌ی عملیات‌ها</option>
        <option value="export">خروجی گرفتن</option>
        <option value="import">بازیابی</option>
      </select>

      <select
        class="h-11 w-full rounded-xl bg-slate-950/50 px-3 text-[13px] text-slate-200 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60"
        :value="filters.status"
        @change="patch('status', $event.target.value)"
      >
        <option value="">همه‌ی وضعیت‌ها</option>
        <option value="completed">موفق</option>
        <option value="partial">نیمه‌موفق</option>
        <option value="failed">ناموفق</option>
        <option value="running">در حال اجرا</option>
      </select>

      <select
        class="h-11 w-full rounded-xl bg-slate-950/50 px-3 text-[13px] text-slate-200 ring-1 ring-white/10 outline-none focus:ring-2 focus:ring-amber-400/60"
        :value="filters.mode"
        @change="patch('mode', $event.target.value)"
      >
        <option value="">همه‌ی نوع‌ها</option>
        <option value="full">بسته کامل</option>
        <option value="database">فقط اطلاعات</option>
        <option value="media">فقط تصاویر</option>
      </select>

      <label class="flex h-11 items-center gap-2 rounded-xl bg-white/[0.03] px-3.5 text-[13px] text-slate-200 ring-1 ring-white/5">
        <input
          type="checkbox"
          class="size-4 accent-amber-400"
          :checked="filters.include_auto"
          @change="patch('include_auto', $event.target.checked)"
        >
        نمایش نسخه‌های خودکار
      </label>
    </div>

    <div class="flex flex-col gap-3">
      <p v-if="loading" class="rounded-xl bg-white/[0.03] px-4 py-6 text-center text-xs text-slate-400">
        در حال دریافت تاریخچه…
      </p>

      <p v-else-if="!runs.length" class="rounded-xl bg-white/[0.03] px-4 py-6 text-center text-xs text-slate-400">
        هنوز هیچ عملیاتی ثبت نشده است.
      </p>

      <template v-else>
      <article
        v-for="run in runs"
        :key="run.id"
        class="flex flex-col gap-4 rounded-xl bg-white/[0.03] p-4 ring-1 ring-white/5 transition hover:bg-white/[0.06] lg:flex-row lg:items-center"
      >
        <div class="flex min-w-0 flex-1 items-center gap-3">
          <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/5 text-base" aria-hidden="true">
            {{ presentDirection(run.direction).icon }}
          </span>

          <div class="min-w-0">
            <p class="truncate text-[13px] font-bold text-slate-100">
              {{ presentDirection(run.direction).label }} · {{ presentMode(run.mode).label }}
              <span v-if="run.is_dry_run" class="text-amber-300/90">(آزمایشی)</span>
            </p>
            <p class="mt-0.5 truncate text-[11px] leading-5 text-slate-400">
              {{ formatDateTime(run.started_at || run.created_at) }}
              <span v-if="run.label"> · {{ run.label }}</span>
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
          <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold leading-4 ring-1" :class="statusClass(run.status)">
            {{ presentStatus(run.status).icon }} {{ presentStatus(run.status).label }}
          </span>

          <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] leading-4 text-slate-300 ring-1 ring-white/10">
            {{ faNumber(run.records_count || 0) }} رکورد
          </span>

          <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] leading-4 text-slate-300 ring-1 ring-white/10">
            {{ formatBytes(run.size_bytes || run.total_bytes || 0) }}
          </span>

          <span class="mx-1 hidden h-6 w-px bg-white/10 lg:block" aria-hidden="true" />

          <button type="button" class="h-8 rounded-lg bg-white/5 px-3 text-xs font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10" @click="emit('view', run)">جزئیات</button>
          <button type="button" class="h-8 rounded-lg bg-white/5 px-3 text-xs font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10" @click="emit('log', run)">گزارش</button>
          <button type="button" class="h-8 rounded-lg bg-rose-400/10 px-3 text-xs font-semibold text-rose-300 ring-1 ring-rose-400/20 transition hover:bg-rose-400/20" @click="emit('delete', run)">حذف</button>
        </div>
      </article>
      </template>
    </div>

    <template #footer>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[11px] text-slate-400">
          صفحه {{ faNumber(pagination.current_page) }} از {{ faNumber(pagination.last_page) }} ·
          {{ faNumber(pagination.total) }} مورد
        </p>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="h-9 rounded-lg bg-white/5 px-3 text-xs font-semibold text-slate-200 ring-1 ring-white/10 transition enabled:hover:bg-white/10 disabled:opacity-40"
            :disabled="!canPrev"
            @click="emit('page', pagination.current_page - 1)"
          >قبلی</button>

          <button
            type="button"
            class="h-9 rounded-lg bg-white/5 px-3 text-xs font-semibold text-slate-200 ring-1 ring-white/10 transition enabled:hover:bg-white/10 disabled:opacity-40"
            :disabled="!canNext"
            @click="emit('page', pagination.current_page + 1)"
          >بعدی</button>
        </div>
      </div>
    </template>
  </BackupCard>
</template>
