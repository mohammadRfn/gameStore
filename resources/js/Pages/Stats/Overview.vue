<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">نمای کلی گزارشات</h1>
            <p class="gs-subtitle">درآمد کالا + سرویس و وضعیت وصول</p>
        </template>

        <div class="gs-toolbar">
            <Link :href="route('stats.index', query)" class="gs-btn gs-btn-primary">مرکز گزارشات</Link>
            <Link :href="route('stats.products', query)" class="gs-btn gs-btn-ghost">کالا</Link>
            <Link :href="route('stats.services', query)" class="gs-btn gs-btn-ghost">سرویس</Link>
        </div>

        <div class="gs-kpi-grid">
            <article class="gs-card" v-for="card in cards" :key="card.title">
                <p class="gs-label">{{ card.title }}</p>
                <p class="gs-num">{{ card.value }}</p>
            </article>
        </div>

        <div class="gs-grid">
            <div class="gs-card wide">
                <h3 class="gs-card-title">روند روزانه</h3>
                <GsChart
                    type="bar"
                    :stacked="true"
                    :labels="dailyLabels"
                    :datasets="[
                        { label: 'کالا', data: dailyProducts, color: '#c9a84c' },
                        { label: 'سرویس', data: dailyServices, color: '#4c8fe0' },
                    ]"
                    :height="260"
                />
            </div>
            <div class="gs-card">
                <h3 class="gs-card-title">ترکیب</h3>
                <GsChart
                    type="doughnut"
                    :labels="['سود کالا', 'بهای تمام‌شده', 'سرویس']"
                    :datasets="[{ data: mix }]"
                    :height="260"
                />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GsChart from '@/Components/GsChart.vue'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range: { type: String, default: 'month' },
    kpi: { type: Object, default: () => ({}) },
    daily: { type: Array, default: () => [] },
})

const query = computed(() => ({
    from: props.from,
    to: props.to,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const cards = computed(() => [
    { title: 'درآمد کل', value: money(props.kpi.gross) },
    { title: 'سود کالا', value: money(props.kpi.product_profit) },
    { title: 'درآمد سرویس', value: money(props.kpi.service_revenue) },
    { title: 'معوق', value: money(props.kpi.outstanding) },
])

const dailyLabels = computed(() => props.daily.map((d) => d.label))
const dailyProducts = computed(() => props.daily.map((d) => d.products))
const dailyServices = computed(() => props.daily.map((d) => d.services))
const mix = computed(() => [
    Number(props.kpi.product_profit || 0),
    Number(props.kpi.product_cogs || 0),
    Number(props.kpi.service_revenue || 0),
])

function fa(n) {
    return Number(n || 0).toLocaleString('fa-IR')
}
function money(n) {
    return fa(Math.round(Number(n || 0))) + ' تومان'
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
    grid-template-columns: 1.4fr 1fr;
    gap: 1rem;
}
@media (max-width: 900px) { .gs-grid { grid-template-columns: 1fr; } }
.gs-card-title { margin: 0 0 .75rem; font-size: .95rem; }
</style>
