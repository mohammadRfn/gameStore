<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">درآمد سرویس</h1>
            <p class="gs-subtitle">تفکیک نوع سرویس، قطعات مصرفی از انبار و کارهای باز</p>
        </template>

        <div class="gs-toolbar">
            <Link :href="route('stats.index', query)" class="gs-btn gs-btn-ghost">← نمای کلی</Link>
            <Link :href="route('stats.products', query)" class="gs-btn gs-btn-ghost">کالا</Link>
            <button type="button" class="gs-btn gs-btn-primary" @click="reload">بروزرسانی</button>
        </div>

        <div class="gs-kpi-grid">
            <article class="gs-card">
                <p class="gs-label">درآمد سرویس</p>
                <p class="gs-num">{{ money(kpi.service_revenue) }}</p>
            </article>
            <article class="gs-card">
                <p class="gs-label">هزینه قطعه</p>
                <p class="gs-num">{{ money(kpi.service_parts) }}</p>
            </article>
            <article class="gs-card">
                <p class="gs-label">خالص سرویس</p>
                <p class="gs-num">{{ money(kpi.service_net) }}</p>
            </article>
            <article class="gs-card">
                <p class="gs-label">کارها</p>
                <p class="gs-num">{{ fa(kpi.service_jobs) }}</p>
            </article>
        </div>

        <div class="gs-grid">
            <div class="gs-card">
                <h3 class="gs-card-title">درآمد به تفکیک نوع</h3>
                <GsChart type="hbar" :labels="names" :datasets="[{ data: revenues, color: '#4c8fe0' }]" :height="280" />
            </div>
            <div class="gs-card">
                <h3 class="gs-card-title">قیف وضعیت — همهٔ کارها</h3>
                <GsChart type="doughnut" :labels="funnelLabels" :datasets="[{ data: funnelData }]" :height="280" />
            </div>
        </div>

        <div class="gs-card">
            <h3 class="gs-card-title">جدول سرویس</h3>
            <p class="gs-hint">
                درآمد از service_jobs.final_price است.
                هزینه قطعه از service_job_items × items.purchase_price (همان گردش انبار سرویس).
            </p>
            <div class="gs-table-wrap">
                <table class="gs-table">
                    <thead>
                        <tr>
                            <th>نوع سرویس</th>
                            <th>تعداد</th>
                            <th>میانگین</th>
                            <th>درآمد</th>
                            <th>قطعه</th>
                            <th>خالص</th>
                            <th>باز</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in services" :key="row.service_type_id || row.name">
                            <td class="strong">{{ row.name }}</td>
                            <td>{{ fa(row.jobs) }}</td>
                            <td>{{ money(row.avg) }}</td>
                            <td class="gold">{{ money(row.revenue) }}</td>
                            <td>{{ money(row.parts_cost) }}</td>
                            <td class="ok">{{ money(row.net) }}</td>
                            <td>
                                <span v-if="row.open" class="gs-badge gs-badge-warning">{{ fa(row.open) }}</span>
                                <span v-else class="gs-badge gs-badge-success">۰</span>
                            </td>
                        </tr>
                        <tr v-if="!services.length">
                            <td colspan="7" class="empty">در این بازه سرویسی ثبت نشده</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GsChart from '@/Components/GsChart.vue'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range: { type: String, default: 'month' },
    kpi: { type: Object, default: () => ({}) },
    services: { type: Array, default: () => [] },
    funnel: { type: Array, default: () => [] },
})

const query = computed(() => ({
    from: props.from,
    to: props.to,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const names = computed(() => props.services.map((s) => s.name))
const revenues = computed(() => props.services.map((s) => s.revenue))
const funnelLabels = computed(() => props.funnel.map((f) => f.label))
const funnelData = computed(() => props.funnel.map((f) => f.count))

function fa(n) {
    return Number(n || 0).toLocaleString('fa-IR')
}
function money(n) {
    return fa(Math.round(Number(n || 0))) + ' تومان'
}
function reload() {
    router.reload({ preserveScroll: true })
}
</script>

<style scoped>
.gs-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
.gs-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
}
.gs-num { font-size: 1.25rem; font-weight: 800; color: var(--gs-gold); }
.gs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 900px) { .gs-grid { grid-template-columns: 1fr; } }
.gs-card-title { margin: 0 0 .75rem; font-size: .95rem; }
.gs-hint { font-size: .78rem; color: var(--gs-text-secondary); margin: 0 0 .75rem; }
.gs-table-wrap { overflow-x: auto; }
.strong { font-weight: 700; }
.gold { color: var(--gs-gold); font-weight: 700; }
.ok { color: var(--gs-success); font-weight: 700; }
.empty { text-align: center; color: var(--gs-text-muted); padding: 1.2rem !important; }
</style>
