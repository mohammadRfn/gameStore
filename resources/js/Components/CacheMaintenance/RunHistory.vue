<script setup>
/**
 * RunHistory — جدول تاریخچهٔ اجراها با فیلتر و صفحه‌بندی
 * مسیر: resources/js/Components/CacheMaintenance/RunHistory.vue
 *
 * ورودی‌ها:
 *   paginator : object  → خروجی paginateRuns (شامل data, current_page, last_page, total)
 *   loading   : boolean
 *
 * خروجی‌ها:
 *   @filter(filters)  → { operation, status }
 *   @page(page)       → شمارهٔ صفحه
 *   @select(run)      → انتخاب یک اجرا برای نمایش جزئیات
 */
import { computed, reactive } from 'vue'
import { RefreshCw, Search, History, ChevronRight, ChevronLeft } from 'lucide-vue-next'

import { OPERATION_META, STATUS_META, OPERATION } from '@/Composables/useCacheMaintenanceApi'
import { jalali } from '@/Utils/format'

const props = defineProps({
    paginator: { type: Object, default: () => ({ data: [] }) },
    loading: { type: Boolean, default: false },
})

const emit = defineEmits(['filter', 'page', 'select'])

const filters = reactive({
    operation: '',
    status: '',
})

function applyFilters() {
    emit('filter', {
        operation: filters.operation || undefined,
        status: filters.status || undefined,
    })
}

function resetFilters() {
    filters.operation = ''
    filters.status = ''
    emit('filter', {})
}

function goPage(page) {
    emit('page', page)
}

const rows = computed(() => props.paginator?.data || [])
const total = computed(() => props.paginator?.total || 0)
const currentPage = computed(() => props.paginator?.current_page || 1)
const lastPage = computed(() => props.paginator?.last_page || 1)

function opLabel(op) {
    return OPERATION_META[op]?.label || op
}

function opIcon(op) {
    return OPERATION_META[op]?.icon || '•'
}

function statusPill(status) {
    return STATUS_META[status] || STATUS_META.failed
}

function dateOf(run) {
    return jalali((run.started_at || run.created_at || '').slice(0, 10))
}

function timeOf(run) {
    const iso = run.started_at || run.created_at || ''
    return iso.slice(11, 19)
}

function duration(run) {
    if (!run.duration_ms) return '—'
    return `${Math.round(run.duration_ms / 100) / 10}ث`
}
</script>

<template>
    <div class="st-card a3d-holo">
        <div class="st-sechead">
            <span class="st-sechead__icon"><History :size="21" /></span>
            <div>
                <h2 class="st-sechead__title">تاریخچهٔ اجراها</h2>
                <p class="st-sechead__desc">
                    <b style="color:var(--gs-gold)">{{ total }}</b> اجرا ثبت شده
                </p>
            </div>
        </div>

        <!-- فیلترها -->
        <div style="display:flex; gap:0.7rem; margin-bottom:1rem; flex-wrap:wrap">
            <select v-model="filters.operation" class="cm-select" @change="applyFilters">
                <option value="">همهٔ عملیات</option>
                <option :value="OPERATION.CLEAR">پاکسازی</option>
                <option :value="OPERATION.OPTIMIZE">بهینه‌سازی</option>
                <option :value="OPERATION.INSPECT">بازبینی</option>
            </select>
            <select v-model="filters.status" class="cm-select" @change="applyFilters">
                <option value="">همهٔ وضعیت‌ها</option>
                <option v-for="(m, key) in STATUS_META" :key="key" :value="key">
                    {{ m.label }}
                </option>
            </select>
            <button type="button" class="cm-btn cm-btn--ghost" @click="resetFilters">
                <RefreshCw :size="15" />
                پاک‌کردن فیلتر
            </button>
        </div>

        <!-- جدول -->
        <div class="cm-table-wrap">
            <table class="cm-table">
                <thead>
                    <tr>
                        <th>عملیات</th>
                        <th>وضعیت</th>
                        <th>نوع</th>
                        <th>تاریخ</th>
                        <th>ساعت</th>
                        <th>مدت</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" style="text-align:center; padding:2rem">
                            در حال بارگذاری…
                        </td>
                    </tr>
                    <tr v-else-if="rows.length === 0">
                        <td colspan="6" style="text-align:center; padding:2rem">
                            <Search :size="16" style="vertical-align:middle" />
                            اجرایی یافت نشد
                        </td>
                    </tr>
                    <tr v-for="run in rows" :key="run.id" @click="emit('select', run)">
                        <td>
                            <span style="display:inline-flex; align-items:center; gap:0.45rem">
                                <span>{{ opIcon(run.operation) }}</span>
                                {{ opLabel(run.operation) }}
                            </span>
                        </td>
                        <td>
                            <span class="cm-pill" :class="statusPill(run.status).className">
                                {{ statusPill(run.status).icon }} {{ statusPill(run.status).label }}
                            </span>
                        </td>
                        <td>
                            <span v-if="run.is_dry_run" class="cm-pill cm-pill--info">آزمایشی</span>
                            <span v-else class="cm-pill cm-pill--muted">واقعی</span>
                        </td>
                        <td>{{ dateOf(run) }}</td>
                        <td class="cm-table__mono">{{ timeOf(run) }}</td>
                        <td class="cm-table__mono">{{ duration(run) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- صفحه‌بندی -->
        <div v-if="lastPage > 1" style="display:flex; align-items:center; justify-content:space-between; margin-top:1rem; flex-wrap:wrap; gap:0.6rem">
            <span class="st-chip st-chip--plain">صفحهٔ {{ currentPage }} از {{ lastPage }}</span>
            <div style="display:flex; gap:0.5rem">
                <button type="button" class="cm-btn cm-btn--ghost" :disabled="currentPage <= 1" @click="goPage(currentPage - 1)">
                    <ChevronRight :size="15" /> قبلی
                </button>
                <button type="button" class="cm-btn cm-btn--ghost" :disabled="currentPage >= lastPage" @click="goPage(currentPage + 1)">
                    بعدی <ChevronLeft :size="15" />
                </button>
            </div>
        </div>
    </div>
</template>
