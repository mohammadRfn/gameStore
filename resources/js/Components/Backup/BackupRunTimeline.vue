<template>
  <section class="backup-glass backup-section">
    <div class="backup-section__head">
      <div>
        <h3 class="backup-section__title">🕰 تاریخچه‌ی اجراها</h3>
        <p class="backup-section__desc">همه‌ی خروجی‌ها، ورودی‌ها، dry-runها و بکاپ‌های ایمنی در اینجا قابل ردیابی هستند.</p>
      </div>
      <button type="button" class="backup-btn backup-btn--sm backup-btn--ghost" :disabled="loading" @click="$emit('refresh')">
        <span v-if="loading" class="backup-spinner"></span>
        تازه‌سازی
      </button>
    </div>

    <div class="relative z-[1] grid gap-3">
      <div class="grid gap-2 md:grid-cols-4">
        <select :value="filters.direction" class="backup-select" @change="update('direction', $event.target.value)">
          <option value="">همه جهت‌ها</option>
          <option value="export">خروجی</option>
          <option value="import">ورودی</option>
        </select>
        <select :value="filters.status" class="backup-select" @change="update('status', $event.target.value)">
          <option value="">همه وضعیت‌ها</option>
          <option value="completed">موفق</option>
          <option value="partial">نیمه‌موفق</option>
          <option value="failed">ناموفق</option>
          <option value="running">در حال اجرا</option>
        </select>
        <select :value="filters.mode" class="backup-select" @change="update('mode', $event.target.value)">
          <option value="">همه حالت‌ها</option>
          <option value="full">کامل</option>
          <option value="database">دیتابیس</option>
          <option value="media">تصاویر</option>
        </select>
        <label class="backup-switch !py-2">
          <input :checked="filters.include_auto" type="checkbox" @change="update('include_auto', $event.target.checked)" />
          <span class="backup-switch__text"><span class="backup-switch__title">نمایش خودکارها</span></span>
          <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
        </label>
      </div>

      <div v-if="loading" class="grid gap-2">
        <div v-for="i in 5" :key="i" class="backup-skeleton h-20"></div>
      </div>

      <div v-else-if="!runs.length" class="backup-empty-state">
        <div>
          <div class="backup-empty-state__icon">🕰</div>
          <h4 class="backup-empty-state__title">هنوز اجرایی ثبت نشده</h4>
          <p class="backup-empty-state__text">اولین خروجی یا ایمپورت که انجام شود، گزارش کامل آن در این بخش نمایش داده می‌شود.</p>
        </div>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="backup-table">
          <thead>
            <tr>
              <th>شناسه</th>
              <th>نوع</th>
              <th>وضعیت</th>
              <th>حالت</th>
              <th>رکورد/فایل</th>
              <th>حجم</th>
              <th>زمان</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="run in runs" :key="run.id">
              <td>
                <b>#{{ faNumber(run.id) }}</b>
                <small v-if="run.uuid" class="block max-w-[160px] truncate text-[var(--gs-text-muted)]" dir="ltr">{{ run.uuid }}</small>
              </td>
              <td><span class="backup-pill">{{ presentDirection(run.direction).icon }} {{ presentDirection(run.direction).label }}</span></td>
              <td><span :class="presentStatus(run.status).className">{{ presentStatus(run.status).label }}</span></td>
              <td><span class="backup-pill">{{ presentMode(run.mode).icon }} {{ presentMode(run.mode).label }}</span></td>
              <td>{{ faNumber(run.total_rows ?? run.rows) }} / {{ faNumber(run.total_files ?? run.files) }}</td>
              <td>{{ run.total_bytes !== undefined ? formatBytes(run.total_bytes) : `${faNumber(run.size_mb)} MB` }}</td>
              <td>{{ formatDateTime(run.created_at) }}</td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <button type="button" class="backup-btn backup-btn--sm backup-btn--info" @click="$emit('view', run)">جزئیات</button>
                  <button type="button" class="backup-btn backup-btn--sm backup-btn--ghost" @click="$emit('log', run)">لاگ</button>
                  <button type="button" class="backup-btn backup-btn--sm backup-btn--danger" @click="$emit('delete', run)">حذف</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="pagination.last_page > 1" class="flex flex-wrap items-center justify-center gap-2">
        <button class="backup-btn backup-btn--sm" :disabled="pagination.current_page <= 1 || loading" @click="$emit('page', pagination.current_page - 1)">قبلی</button>
        <span class="backup-pill">صفحه {{ faNumber(pagination.current_page) }} از {{ faNumber(pagination.last_page) }}</span>
        <button class="backup-btn backup-btn--sm" :disabled="pagination.current_page >= pagination.last_page || loading" @click="$emit('page', pagination.current_page + 1)">بعدی</button>
      </nav>
    </div>
  </section>
</template>

<script setup>
import { faNumber, formatBytes, formatDateTime, presentDirection, presentMode, presentStatus } from '@/Composables/useBackupApi'

const props = defineProps({
  runs: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
  pagination: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:filters', 'refresh', 'view', 'log', 'delete', 'page'])

function update(key, value) {
  emit('update:filters', { ...props.filters, [key]: value })
}
</script>
