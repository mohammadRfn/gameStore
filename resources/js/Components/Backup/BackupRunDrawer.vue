<template>
  <Teleport to="body">
    <Transition name="backup-fade">
      <div v-if="modelValue" class="backup-drawer-overlay" @click.self="close">
        <Transition name="backup-drawer" appear>
          <aside v-if="modelValue" class="backup-drawer" role="dialog" aria-modal="true">
            <header class="backup-drawer__head">
              <div class="flex items-start gap-3">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-[var(--gs-gold-muted)] text-2xl">
                  {{ direction.icon }}
                </div>
                <div>
                  <p class="text-[0.74rem] font-extrabold text-[var(--gs-text-muted)]">اجرای بکاپ #{{ runId }}</p>
                  <h2 class="text-xl font-black text-[var(--gs-text-primary)]">
                    {{ direction.label }} · {{ mode.label }}
                  </h2>
                  <p class="mt-1 text-[0.78rem] text-[var(--gs-text-secondary)]">{{ run?.run?.path || data?.run_path || '—' }}</p>
                </div>
              </div>
              <button type="button" class="backup-btn backup-btn--ghost backup-btn--sm" @click="close">✕</button>
            </header>

            <div class="backup-drawer__body">
              <div v-if="loading" class="grid gap-3">
                <div class="backup-skeleton h-24"></div>
                <div class="backup-skeleton h-56"></div>
              </div>

              <template v-else>
                <section class="backup-glass backup-section">
                  <div class="backup-section__head">
                    <div>
                      <h3 class="backup-section__title">{{ status.icon }} وضعیت: {{ status.label }}</h3>
                      <p class="backup-section__desc">{{ createdAt }} · مدت: {{ duration }}</p>
                    </div>
                    <span :class="status.className">{{ status.label }}</span>
                  </div>

                  <div class="relative z-[1] grid grid-cols-2 gap-2 md:grid-cols-4">
                    <div v-for="item in metrics" :key="item.label" class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                      <p class="text-[0.7rem] font-bold text-[var(--gs-text-muted)]">{{ item.label }}</p>
                      <strong class="mt-1 block text-lg font-black text-[var(--gs-text-primary)]">{{ item.value }}</strong>
                    </div>
                  </div>
                </section>

                <section class="backup-glass backup-section">
                  <div class="backup-section__head">
                    <h3 class="backup-section__title">🧬 جدول‌ها / موجودیت‌ها</h3>
                    <button v-if="data?.run?.id" type="button" class="backup-btn backup-btn--sm backup-btn--ghost" @click="$emit('download-log', data.run.id)">
                      دانلود لاگ
                    </button>
                  </div>

                  <div class="relative z-[1] overflow-x-auto">
                    <table class="backup-table">
                      <thead>
                        <tr>
                          <th>موجودیت</th>
                          <th>وضعیت</th>
                          <th>سطر</th>
                          <th>درج</th>
                          <th>آپدیت</th>
                          <th>خطا</th>
                          <th>حجم</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="entity in entities" :key="entity.id || entity.entity_key">
                          <td>
                            <b>{{ entity.display_name || entity.entity_key }}</b>
                            <small class="block text-[var(--gs-text-muted)]" dir="ltr">{{ entity.table_name }}</small>
                          </td>
                          <td><span :class="presentStatus(entity.status).className">{{ presentStatus(entity.status).label }}</span></td>
                          <td>{{ faNumber(entity.row_count || entity.processed_rows) }}</td>
                          <td>{{ faNumber(entity.inserted_rows) }}</td>
                          <td>{{ faNumber(entity.updated_rows) }}</td>
                          <td>{{ faNumber(entity.failed_rows) }}</td>
                          <td>{{ formatBytes(entity.bytes) }}</td>
                        </tr>
                        <tr v-if="!entities.length"><td colspan="7" class="text-center text-[var(--gs-text-muted)]">جزئیاتی ثبت نشده است.</td></tr>
                      </tbody>
                    </table>
                  </div>
                </section>

                <section class="backup-glass backup-section">
                  <div class="backup-section__head">
                    <h3 class="backup-section__title">📜 رویدادهای اجرا</h3>
                    <span class="backup-pill">{{ faNumber(events.length) }} رویداد</span>
                  </div>
                  <div class="relative z-[1] grid gap-2">
                    <article v-for="event in events" :key="event.id" class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <b class="text-[var(--gs-text-primary)]">{{ event.message }}</b>
                          <p class="mt-1 text-[0.72rem] text-[var(--gs-text-muted)]" dir="ltr">{{ event.code }}</p>
                        </div>
                        <span class="backup-pill" :class="event.level === 'error' || event.level === 'critical' ? 'backup-pill--danger' : event.level === 'warning' ? 'backup-pill--warn' : 'backup-pill--info'">
                          {{ event.level }}
                        </span>
                      </div>
                    </article>
                    <p v-if="!events.length" class="py-6 text-center text-[var(--gs-text-muted)]">رویدادی ثبت نشده است.</p>
                  </div>
                </section>

                <section v-if="data?.run?.error" class="backup-glass backup-section border-[rgba(240,106,106,.35)]">
                  <h3 class="backup-section__title text-[var(--gs-error)]">⚠ خطای اجرا</h3>
                  <pre class="relative z-[1] whitespace-pre-wrap rounded-2xl bg-[var(--gs-error-soft)] p-3 text-[0.78rem] leading-7 text-[var(--gs-error)]">{{ data.run.error }}</pre>
                </section>
              </template>
            </div>
          </aside>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import {
  faNumber,
  formatBytes,
  formatDateTime,
  presentDirection,
  presentMode,
  presentStatus,
} from '@/Composables/useBackupApi'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  data: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'download-log'])

const run = computed(() => props.data?.run ?? props.data ?? null)
const runId = computed(() => run.value?.id ?? '—')
const status = computed(() => presentStatus(run.value?.status))
const mode = computed(() => presentMode(run.value?.mode))
const direction = computed(() => presentDirection(run.value?.direction))
const entities = computed(() => props.data?.entities ?? [])
const events = computed(() => props.data?.events ?? [])
const createdAt = computed(() => formatDateTime(run.value?.created_at))
const duration = computed(() => run.value?.duration_ms ? `${faNumber(Math.round(run.value.duration_ms / 1000))} ثانیه` : '—')

const metrics = computed(() => [
  { label: 'رکوردها', value: faNumber(run.value?.rows ?? run.value?.total_rows) },
  { label: 'فایل‌ها', value: faNumber(run.value?.files ?? run.value?.total_files) },
  { label: 'حجم', value: run.value?.size_mb !== undefined ? `${faNumber(run.value.size_mb)} MB` : formatBytes(run.value?.total_bytes) },
  { label: 'مسیر', value: run.value?.path ? 'ثبت شده' : '—' },
])

function close() {
  emit('update:modelValue', false)
}
</script>
