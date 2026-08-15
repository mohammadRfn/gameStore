<template>
    <AppLayout>
        <StatHero title="نمای کلی گزارشات" subtitle="درآمد کالا + سرویس، روند روزانه و وضعیت وصول — در یک نگاه"
            :from="from" :to="to" :range="range">
            <template #actions>
                <Link :href="route('stats.index', query)" class="gs-btn gs-btn-soft">مرکز گزارشات</Link>
                <Link :href="route('stats.products', query)" class="gs-btn gs-btn-soft">کالا</Link>
                <Link :href="route('stats.services', query)" class="gs-btn gs-btn-soft">سرویس</Link>
            </template>
        </StatHero>

        <RangeFilter :from="from" :to="to" :paid-only="paidOnly" :range="range" route-name="stats.overview"
            class="mb" />

        <div class="gs-kpi-grid gs-stagger">
            <KpiCard label="درآمد کل" :value="compactMoney(kpi.gross)" :delta="compare.gross" :icon="Wallet" tone="gold"
                :spark="dailyTotals" />
            <KpiCard label="سود خالص" :value="compactMoney(kpi.net_profit)" :delta="compare.net_profit"
                :icon="TrendingUp" tone="green" :spark="dailyProfits" />
            <KpiCard label="وصول‌شده" :value="compactMoney(kpi.collected)" :delta="compare.collected" :icon="Banknote"
                tone="green" />
            <KpiCard label="معوق" :value="compactMoney(kpi.outstanding)" :delta="compare.outstanding" :invert="true"
                :icon="Clock" tone="red" />
            <KpiCard label="مشتریان جدید" :value="faInt(kpi.new_customers)" :icon="UserPlus" tone="gold" />
        </div>

        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <BarChart3 class="ic" :size="17" /> روند روزانهٔ درآمد
                    </h3>
                </div>
                <GsChart type="bar" :stacked="true" :labels="dailyLabels" :datasets="[
                    { label: 'کالا', data: dailyProducts, color: '#e3bd5c' },
                    { label: 'سرویس', data: dailyServices, color: '#5b9df0' },
                ]" :height="280" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <PieChart class="ic" :size="17" /> ترکیب درآمد
                    </h3>
                </div>
                <GsChart type="doughnut" :labels="['سود کالا', 'بهای تمام‌شده', 'درآمد سرویس']"
                    :datasets="[{ data: mixData }]" :height="280" />
            </section>
        </div>

        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Activity class="ic" :size="17" /> سود کالا در برابر سرویس
                    </h3>
                </div>
                <GsChart type="line" :labels="dailyLabels" :datasets="[
                    { label: 'سود کالا', data: dailyProfits, color: '#45d68b' },
                    { label: 'درآمد سرویس', data: dailyServices, color: '#5b9df0' },
                ]" :height="250" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <CreditCard class="ic" :size="17" /> روش پرداخت
                    </h3>
                </div>
                <GsChart type="doughnut" :labels="paymentLabels" :datasets="[{ data: paymentData }]" :height="250" />
            </section>
        </div>

        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Hourglass class="ic" :size="17" /> سن مطالبات
                    </h3>
                </div>
                <div class="aging-list">
                    <div v-for="b in aging" :key="b.label" class="aging-item">
                        <div class="aging-top">
                            <span class="aging-label">{{ b.label }}</span>
                            <span class="aging-amount">{{ compactMoney(b.amount) }}</span>
                        </div>
                        <div class="aging-bar">
                            <div class="aging-fill" :style="{ width: agingWidth(b.amount) }"></div>
                        </div>
                        <span class="aging-count">{{ faInt(b.count) }} فاکتور</span>
                    </div>
                    <p v-if="!aging.length" class="gs-empty">مطالبه‌ای ثبت نشده</p>
                </div>
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <CalendarDays class="ic" :size="17" /> فروش روزهای هفته
                    </h3>
                </div>
                <div class="heatmap">
                    <div v-for="h in heatmap" :key="h.label" class="heat-cell" :style="heatStyle(h.amount)">
                        <span class="heat-day">{{ h.label }}</span>
                        <span class="heat-amount">{{ compactMoney(h.amount) }}</span>
                        <span class="heat-count">{{ faInt(h.count) }}</span>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatHero from '@/Components/Stats/StatHero.vue'
import RangeFilter from '@/Components/Stats/RangeFilter.vue'
import KpiCard from '@/Components/Stats/KpiCard.vue'
import GsChart from '@/Components/GsChart.vue'
import {
    Wallet, TrendingUp, Banknote, Clock, BarChart3, PieChart, Activity, CreditCard, Hourglass, CalendarDays, UserPlus,
} from '@lucide/vue'
import { faInt, compactMoney } from '@/Utils/format'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range: { type: String, default: 'month' },
    kpi: { type: Object, default: () => ({}) },
    compare: { type: Object, default: () => ({}) },
    daily: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    aging: { type: Array, default: () => [] },
    heatmap: { type: Array, default: () => [] },
})

const query = computed(() => ({
    from: props.from || undefined,
    to: props.to || undefined,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const dailyLabels = computed(() => props.daily.map((d) => d.label))
const dailyProducts = computed(() => props.daily.map((d) => d.products))
const dailyServices = computed(() => props.daily.map((d) => d.services))
const dailyProfits = computed(() => props.daily.map((d) => d.profit))
const dailyTotals = computed(() => props.daily.map((d) => d.total))

const mixData = computed(() => [
    Number(props.kpi.product_profit || 0),
    Number(props.kpi.product_cogs || 0),
    Number(props.kpi.service_revenue || 0),
])

const METHOD_LABELS = { cash: 'نقدی', pos: 'کارت‌خوان', credit: 'اعتباری', unknown: 'نامشخص' }
const paymentLabels = computed(() => (props.payments || []).map((p) => METHOD_LABELS[p.method] || p.method || 'نامشخص'))
const paymentData = computed(() => (props.payments || []).map((p) => p.total))

const maxAging = computed(() => Math.max(...(props.aging || []).map((a) => a.amount || 0), 1))
function agingWidth(amount) {
    return Math.max(3, ((amount || 0) / maxAging.value) * 100) + '%'
}

const maxHeat = computed(() => Math.max(...(props.heatmap || []).map((h) => h.amount || 0), 1))
function heatStyle(amount) {
    const t = (amount || 0) / maxHeat.value
    return { '--heat': t }
}
</script>

<style scoped>
.mb {
    margin-bottom: 1rem;
}

/* Aging */
.aging-list {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.aging-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.aging-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.aging-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gs-text-secondary);
}

.aging-amount {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--gs-error);
    font-variant-numeric: tabular-nums;
}

.aging-bar {
    height: 8px;
    border-radius: 999px;
    background: var(--gs-bg-soft);
    overflow: hidden;
}

.aging-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--gs-warning), var(--gs-error));
    transition: width 0.6s var(--gs-ease-spring);
}

.aging-count {
    font-size: 0.72rem;
    color: var(--gs-text-muted);
}

/* Heatmap */
.heatmap {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    height: 100%;
    align-items: stretch;
}

.heat-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    padding: 0.7rem 0.3rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border);
    background: linear-gradient(180deg, rgba(227, 189, 92, calc(0.06 + var(--heat) * 0.35)), transparent 120%);
    min-height: 120px;
    transition: transform 0.2s ease;
}

.heat-cell:hover {
    transform: translateY(-3px);
}

.heat-day {
    font-size: 0.68rem;
    color: var(--gs-text-secondary);
    font-weight: 600;
}

.heat-amount {
    font-size: 0.68rem;
    color: var(--gs-gold);
    font-weight: 700;
    white-space: nowrap;
}

.heat-count {
    font-size: 0.62rem;
    color: var(--gs-text-muted);
}

@media (max-width: 720px) {
    .heatmap {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
