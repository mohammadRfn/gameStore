<template>
    <AppLayout>
        <StatHero :title="heroTitle" :subtitle="heroSubtitle" :from="from" :to="to" :range="range">
            <template #actions>
                <Link :href="route('stats.overview', query)" class="gs-btn gs-btn-soft">نمای کلی</Link>
                <Link :href="route('stats.products', query)" class="gs-btn gs-btn-soft">کالا</Link>
                <Link :href="route('stats.services', query)" class="gs-btn gs-btn-soft">سرویس</Link>
                <Link :href="route('stats.ranking', query)" class="gs-btn gs-btn-primary">رتبه‌بندی</Link>
            </template>
        </StatHero>

        <RangeFilter
            :from="from"
            :to="to"
            :paid-only="paidOnly"
            :range="range"
            route-name="stats.index"
            class="mb"
        />

        <!-- KPI -->
        <div class="gs-kpi-grid gs-stagger">
            <KpiCard
                label="درآمد کل"
                :value="compactMoney(kpi.gross)"
                :delta="compare.gross"
                :icon="Wallet"
                tone="gold"
                :spark="dailyTotals"
            />
            <KpiCard
                label="سود خالص"
                :value="compactMoney(kpi.net_profit)"
                :delta="compare.net_profit"
                :icon="TrendingUp"
                tone="green"
                :spark="dailyProfits"
            />
            <KpiCard
                label="درآمد کالا"
                :value="compactMoney(kpi.product_revenue)"
                :icon="Package"
                tone="gold"
                :footnote="'بهای تمام‌شده: ' + compactMoney(kpi.product_cogs)"
            />
            <KpiCard
                label="سود کالا"
                :value="compactMoney(kpi.product_profit)"
                :delta="compare.product_profit"
                :icon="Layers"
                tone="green"
                :spark="dailyProfits"
            />
            <KpiCard
                label="درآمد سرویس"
                :value="compactMoney(kpi.service_revenue)"
                :delta="compare.service_revenue"
                :icon="Wrench"
                tone="blue"
                :spark="dailyServices"
            />
            <KpiCard
                label="وصول‌شده"
                :value="compactMoney(kpi.collected)"
                :delta="compare.collected"
                :icon="Banknote"
                tone="green"
            />
            <KpiCard
                label="معوق"
                :value="compactMoney(kpi.outstanding)"
                :delta="compare.outstanding"
                :invert="true"
                :icon="Clock"
                tone="red"
                :footnote="'مرجوعی: ' + compactMoney(kpi.returned)"
            />
            <KpiCard
                label="فاکتورها"
                :value="faInt(kpi.invoice_count)"
                :icon="Receipt"
                tone="violet"
                :footnote="'وصول‌شده: ' + faInt(kpi.paid_count)"
            />
        </div>

        <!-- روند + ترکیب -->
        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><BarChart3 class="ic" :size="17" /> روند روزانهٔ درآمد</h3>
                    <span class="gs-label">کالا + سرویس</span>
                </div>
                <GsChart
                    type="bar"
                    :stacked="true"
                    :labels="dailyLabels"
                    :datasets="[
                        { label: 'کالا', data: dailyProducts, color: '#e3bd5c' },
                        { label: 'سرویس', data: dailyServices, color: '#5b9df0' },
                    ]"
                    :height="270"
                />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><PieChart class="ic" :size="17" /> ترکیب درآمد</h3>
                </div>
                <GsChart
                    type="doughnut"
                    :labels="['سود کالا', 'بهای تمام‌شده', 'درآمد سرویس']"
                    :datasets="[{ data: mixData }]"
                    :height="270"
                />
            </section>
        </div>

        <!-- خط + پرداخت -->
        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><Activity class="ic" :size="17" /> سود کالا در برابر سرویس</h3>
                </div>
                <GsChart
                    type="line"
                    :labels="dailyLabels"
                    :datasets="[
                        { label: 'سود کالا', data: dailyProfits, color: '#45d68b' },
                        { label: 'درآمد سرویس', data: dailyServices, color: '#5b9df0' },
                    ]"
                    :height="240"
                />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><CreditCard class="ic" :size="17" /> روش پرداخت</h3>
                </div>
                <GsChart
                    type="doughnut"
                    :labels="paymentLabels"
                    :datasets="[{ data: paymentData }]"
                    :height="240"
                />
            </section>
        </div>

        <!-- برترین‌ها -->
        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><Trophy class="ic" :size="17" /> پرسودترین کالاها</h3>
                    <Link :href="route('stats.ranking', query)" class="gs-btn gs-btn-ghost gs-btn-sm">مشاهدهٔ کامل</Link>
                </div>
                <Bar3D :labels="topProductNames" :values="topProductProfits" color="#45d68b" :money="true" :height="250" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><Crown class="ic" :size="17" /> برترین سرویس‌ها</h3>
                    <Link :href="route('stats.ranking', query)" class="gs-btn gs-btn-ghost gs-btn-sm">مشاهدهٔ کامل</Link>
                </div>
                <Bar3D :labels="topServiceNames" :values="topServiceRevenues" color="#5b9df0" :money="true" :height="250" />
            </section>
        </div>

        <!-- فاکتورهای اخیر -->
        <section class="gs-card">
            <div class="gs-card-head">
                <h3 class="gs-card-title"><FileText class="ic" :size="17" /> آخرین فاکتورها</h3>
                <span class="gs-label">{{ faInt(invoices.length) }} فاکتور</span>
            </div>
            <div class="gs-table-wrap">
                <table class="gs-table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>مشتری</th>
                            <th>کالا</th>
                            <th>سرویس</th>
                            <th>نهایی</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inv in invoices" :key="inv.id">
                            <td class="strong gold">{{ inv.number }}</td>
                            <td>{{ inv.customer }}</td>
                            <td>{{ money(inv.items) }}</td>
                            <td>{{ money(inv.services) }}</td>
                            <td class="strong">{{ money(inv.total) }}</td>
                            <td>
                                <span class="gs-badge" :class="statusBadge(inv.status)">{{ statusLabel(inv.status) }}</span>
                            </td>
                        </tr>
                        <tr v-if="!invoices.length">
                            <td colspan="6" class="gs-empty">فاکتوری در این بازه ثبت نشده</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
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
import Bar3D from '@/Components/Stats/Bar3D.vue'
import {
    Wallet, TrendingUp, Package, Layers, Wrench, Banknote, Clock, Receipt,
    BarChart3, PieChart, Activity, CreditCard, Trophy, Crown, FileText,
} from '@lucide/vue'
import { faInt, money, compactMoney } from '@/Utils/format'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range: { type: String, default: 'month' },
    kpi: { type: Object, default: () => ({}) },
    compare: { type: Object, default: () => ({}) },
    daily: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    aging: { type: Array, default: () => [] },
    stock: { type: Object, default: () => ({}) },
    funnel: { type: Array, default: () => [] },
    heatmap: { type: Array, default: () => [] },
})

const heroTitle = 'مرکز گزارشات'
const heroSubtitle = 'نمای یک‌پارچه از درآمد، سود و وصول فروشگاه — با دادهٔ واقعی فاکتورها، کالا و سرویس'

const query = computed(() => ({
    from: props.from || undefined,
    to: props.to || undefined,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

/* ─── Daily ─── */
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

/* ─── Payments ─── */
const METHOD_LABELS = { cash: 'نقدی', pos: 'کارت‌خوان', credit: 'اعتباری', unknown: 'نامشخص' }
const paymentLabels = computed(() => (props.payments || []).map((p) => METHOD_LABELS[p.method] || p.method || 'نامشخص'))
const paymentData = computed(() => (props.payments || []).map((p) => p.total))

/* ─── Top lists ─── */
const topProducts = computed(() => [...props.products].sort((a, b) => (b.profit || 0) - (a.profit || 0)).slice(0, 5))
const topProductNames = computed(() => topProducts.value.map((p) => p.name))
const topProductProfits = computed(() => topProducts.value.map((p) => p.profit))

const topServices = computed(() => [...props.services].sort((a, b) => (b.revenue || 0) - (a.revenue || 0)).slice(0, 5))
const topServiceNames = computed(() => topServices.value.map((s) => s.name))
const topServiceRevenues = computed(() => topServices.value.map((s) => s.revenue))

/* ─── Status ─── */
function statusLabel(s) {
    return { paid: 'وصول‌شده', unpaid: 'در انتظار', returned: 'مرجوع' }[s] ?? s
}
function statusBadge(s) {
    return s === 'paid' ? 'gs-badge-success' : s === 'returned' ? 'gs-badge-error' : 'gs-badge-warning'
}
</script>

<style scoped>
.mb { margin-bottom: 1rem; }
</style>
