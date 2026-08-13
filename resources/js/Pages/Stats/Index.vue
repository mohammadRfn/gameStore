<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">گزارشات فروش</h1>
            <p class="gs-subtitle">سود کالا، درآمد سرویس و وصول فاکتور — از دادهٔ واقعی فروشگاه</p>
        </template>

        <form class="gs-filter" @submit.prevent="apply">
            <div class="gs-filter-ranges">
                <button type="button" v-for="r in ranges" :key="r.id" class="gs-btn"
                    :class="range === r.id ? 'gs-btn-primary' : 'gs-btn-ghost'" @click="setRange(r.id)">{{ r.label
                    }}</button>
            </div>
            <label class="gs-check">
                <input type="checkbox" v-model="paidOnly">
                فقط وصول‌شده
            </label>
            <JalaliDateInput v-model="from" class="gs-date" placeholder="از تاریخ" />
            <JalaliDateInput v-model="to" class="gs-date" placeholder="تا تاریخ" />
            <button type="submit" class="gs-btn gs-btn-primary">اعمال</button>
        </form>

        <div class="gs-tabs">
            <button v-for="t in tabs" :key="t.id" class="gs-tab" :class="{ active: tab === t.id }" @click="tab = t.id">
                {{ t.label }}
            </button>
        </div>

        <!-- OVERVIEW -->
        <section v-show="tab === 'overview'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi" v-for="card in kpiCards" :key="card.title">
                    <p class="gs-label">{{ card.title }}</p>
                    <p class="gs-kpi-val">{{ card.value }}</p>
                    <p class="gs-kpi-delta" :class="card.up ? 'up' : 'down'">
                        {{ card.up ? '▲' : '▼' }} {{ card.delta }} نسبت به دوره قبل
                    </p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">روند روزانه — کالا + سرویس</h3>
                    <GsChart type="bar" :stacked="true" :labels="dailyLabels" :datasets="dailyDatasets" :height="260" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">ترکیب درآمد</h3>
                    <GsChart type="doughnut" :labels="['سود کالا', 'بهای تمام‌شده', 'سرویس']"
                        :datasets="[{ data: mixData }]" :height="260" />
                </div>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">سود کالا در برابر درآمد سرویس</h3>
                    <GsChart type="line" :labels="dailyLabels" :datasets="splitDatasets" :height="240" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">روش پرداخت</h3>
                    <GsChart type="doughnut" :labels="paymentLabels" :datasets="[{ data: paymentData }]"
                        :height="240" />
                </div>
            </div>
        </section>

        <!-- PRODUCTS -->
        <section v-show="tab === 'products'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">فروش کالا</p>
                    <p class="gs-kpi-val">{{ money(kpi.product_revenue) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">بهای تمام‌شده (خرید)</p>
                    <p class="gs-kpi-val">{{ money(kpi.product_cogs) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">سود ناخالص</p>
                    <p class="gs-kpi-val">{{ money(kpi.product_profit) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">حاشیه</p>
                    <p class="gs-kpi-val">{{ fa(kpi.product_margin) }}٪</p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">سود هر کالا</h3>
                    <GsChart type="hbar" :labels="productNames" :datasets="[{ data: productProfits, color: '#4caf7d' }]"
                        :height="280" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">درآمد در برابر خرید</h3>
                    <GsChart type="bar" :labels="productNames" :datasets="productCompare" :height="280" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جزئیات کالا — وصل به آیتم، فاکتور و گردش انبار</h3>
                <p class="gs-hint">سود هر ردیف = (قیمت فروش روی فاکتور − قیمت خرید کاتالوگ) × تعداد. مرجوعی‌ها حذف
                    شده‌اند.</p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>کالا</th>
                                <th>دسته</th>
                                <th>تعداد</th>
                                <th>خرید واحد</th>
                                <th>فروش واحد</th>
                                <th>درآمد</th>
                                <th>بهای تمام‌شده</th>
                                <th>سود</th>
                                <th>حاشیه</th>
                                <th>موجودی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in products" :key="row.item_id || row.name">
                                <td class="strong">{{ row.name }}</td>
                                <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                                <td>{{ fa(row.qty) }}</td>
                                <td>{{ money(row.avg_buy) }}</td>
                                <td>{{ money(row.avg_sell) }}</td>
                                <td class="gold">{{ money(row.revenue) }}</td>
                                <td>{{ money(row.cogs) }}</td>
                                <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                                <td>{{ fa(row.margin) }}٪</td>
                                <td>{{ row.stock === null ? '—' : fa(row.stock) }}</td>
                            </tr>
                            <tr v-if="!products.length">
                                <td colspan="10" class="empty">در این بازه فروش کالایی ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SERVICES -->
        <section v-show="tab === 'services'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">درآمد سرویس</p>
                    <p class="gs-kpi-val">{{ money(kpi.service_revenue) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">هزینه قطعه</p>
                    <p class="gs-kpi-val">{{ money(kpi.service_parts) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">خالص سرویس</p>
                    <p class="gs-kpi-val">{{ money(kpi.service_net) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">تعداد کار</p>
                    <p class="gs-kpi-val">{{ fa(kpi.service_jobs) }}</p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">درآمد به تفکیک نوع سرویس</h3>
                    <GsChart type="hbar" :labels="serviceNames"
                        :datasets="[{ data: serviceRevenues, color: '#4c8fe0' }]" :height="260" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">قیف وضعیت کارها (کل فروشگاه)</h3>
                    <GsChart type="doughnut" :labels="funnelLabels" :datasets="[{ data: funnelData }]" :height="260" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جزئیات سرویس — وصل به service_jobs و قطعات انبار</h3>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>نوع سرویس</th>
                                <th>تعداد</th>
                                <th>میانگین</th>
                                <th>درآمد</th>
                                <th>قطعه (خرید)</th>
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
                                <td colspan="7" class="empty">در این بازه سرویس ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- INVOICES -->
        <section v-show="tab === 'invoices'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">صدور</p>
                    <p class="gs-kpi-val">{{ money(kpi.billed) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">وصول</p>
                    <p class="gs-kpi-val">{{ money(kpi.collected) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">معوق</p>
                    <p class="gs-kpi-val">{{ money(kpi.outstanding) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">مرجوعی</p>
                    <p class="gs-kpi-val">{{ money(kpi.returned) }}</p>
                </article>
            </div>
            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">سن مطالبات</h3>
                    <GsChart type="bar" :labels="agingLabels" :datasets="[{ data: agingData, color: '#e05c5c' }]"
                        :height="240" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">نقشهٔ فروش هفته</h3>
                    <GsChart type="bar" :labels="heatLabels" :datasets="[{ data: heatData, color: '#c9a84c' }]"
                        :height="240" />
                </div>
            </div>
            <div class="gs-card">
                <h3 class="gs-card-title">فاکتورهای بازه</h3>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>شماره</th>
                                <th>مشتری</th>
                                <th>کالا</th>
                                <th>سرویس</th>
                                <th>تعدیل</th>
                                <th>نهایی</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in invoices" :key="inv.id">
                                <td>
                                    <Link :href="route('invoices.show', inv.id)" class="gold strong">{{ inv.number }}
                                    </Link>
                                </td>
                                <td>{{ inv.customer }}</td>
                                <td>{{ money(inv.items) }}</td>
                                <td>{{ money(inv.services) }}</td>
                                <td :class="inv.adjustment < 0 ? 'bad' : 'ok'">{{ money(inv.adjustment) }}</td>
                                <td class="strong">{{ money(inv.total) }}</td>
                                <td>
                                    <span class="gs-badge" :class="statusBadge(inv)">{{ statusLabel(inv) }}</span>
                                </td>
                            </tr>
                            <tr v-if="!invoices.length">
                                <td colspan="7" class="empty">فاکتوری در این بازه نیست</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GsChart from '@/Components/GsChart.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'

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
    funnel: { type: Array, default: () => [] },
    heatmap: { type: Array, default: () => [] },
})

const tab = ref('overview')
const from = ref(props.from)
const to = ref(props.to)
const paidOnly = ref(!!props.paidOnly)
const range = ref(props.range || 'month')

const ranges = [
    { id: 'today', label: 'امروز' },
    { id: 'week', label: '۷ روز' },
    { id: 'month', label: 'این ماه' },
    { id: 'year', label: 'امسال' },
]
const tabs = [
    { id: 'overview', label: 'نمای کلی' },
    { id: 'products', label: 'سود کالا' },
    { id: 'services', label: 'درآمد سرویس' },
    { id: 'invoices', label: 'فاکتور و وصول' },
]

const query = computed(() => ({
    from: from.value,
    to: to.value,
    paid_only: paidOnly.value ? 1 : 0,
    range: range.value,
}))

function apply() {
    router.get(route('stats.index'), query.value, { preserveState: true, preserveScroll: true })
}
function setRange(id) {
    range.value = id
    router.get(route('stats.index'), { range: id, paid_only: paidOnly.value ? 1 : 0 }, { preserveState: true, preserveScroll: true })
}

function fa(n) {
    return Number(n || 0).toLocaleString('fa-IR')
}
function money(n) {
    return fa(Math.round(Number(n || 0))) + ' تومان'
}
function deltaText(key) {
    const v = Number(props.compare?.[key] || 0)
    return fa(Math.abs(v)) + '٪'
}
function deltaUp(key) {
    return Number(props.compare?.[key] || 0) >= 0
}

const kpiCards = computed(() => [
    { title: 'درآمد کل', value: money(props.kpi.gross), delta: deltaText('gross'), up: deltaUp('gross') },
    { title: 'سود کالا', value: money(props.kpi.product_profit), delta: deltaText('product_profit'), up: deltaUp('product_profit') },
    { title: 'درآمد سرویس', value: money(props.kpi.service_revenue), delta: deltaText('service_revenue'), up: deltaUp('service_revenue') },
    { title: 'مطالبات باز', value: money(props.kpi.outstanding), delta: deltaText('outstanding'), up: !deltaUp('outstanding') },
    { title: 'وصول‌شده', value: money(props.kpi.collected), delta: deltaText('collected'), up: deltaUp('collected') },
    { title: 'سود خالص', value: money(props.kpi.net_profit), delta: deltaText('net_profit'), up: deltaUp('net_profit') },
    { title: 'میانگین فاکتور', value: money(props.kpi.avg_ticket), delta: fa(props.kpi.invoice_count) + ' فاکتور', up: true },
    { title: 'حاشیه کالا', value: fa(props.kpi.product_margin) + '٪', delta: fa(props.kpi.paid_count) + ' وصول', up: true },
])

const dailyLabels = computed(() => props.daily.map((d) => d.label))
const dailyDatasets = computed(() => [
    { label: 'کالا', data: props.daily.map((d) => d.products), color: '#c9a84c' },
    { label: 'سرویس', data: props.daily.map((d) => d.services), color: '#4c8fe0' },
])
const splitDatasets = computed(() => [
    { label: 'سود کالا', data: props.daily.map((d) => d.profit), color: '#4caf7d' },
    { label: 'سرویس', data: props.daily.map((d) => d.services), color: '#4c8fe0' },
])
const mixData = computed(() => [
    Number(props.kpi.product_profit || 0),
    Number(props.kpi.product_cogs || 0),
    Number(props.kpi.service_revenue || 0),
])

const methodLabel = {
    cash: 'نقد',
    card_to_card: 'کارت‌به‌کارت',
    pos_terminal: 'کارتخوان',
    paid: 'وصول',
    unpaid: 'معوق',
    returned: 'مرجوع',
    unknown: 'نامشخص',
}
const paymentLabels = computed(() => props.payments.map((p) => methodLabel[p.method] || p.method))
const paymentData = computed(() => props.payments.map((p) => p.total))

const topProducts = computed(() => props.products.slice(0, 8))
const productNames = computed(() => topProducts.value.map((p) => p.name))
const productProfits = computed(() => topProducts.value.map((p) => p.profit))
const productCompare = computed(() => [
    { label: 'درآمد', data: topProducts.value.map((p) => p.revenue), color: '#c9a84c' },
    { label: 'خرید', data: topProducts.value.map((p) => p.cogs), color: '#e05c5c' },
])

const serviceNames = computed(() => props.services.map((s) => s.name))
const serviceRevenues = computed(() => props.services.map((s) => s.revenue))
const funnelLabels = computed(() => props.funnel.map((f) => f.label))
const funnelData = computed(() => props.funnel.map((f) => f.count))
const agingLabels = computed(() => props.aging.map((a) => a.label))
const agingData = computed(() => props.aging.map((a) => a.total))
const heatLabels = computed(() => props.heatmap.map((h) => h.label))
const heatData = computed(() => props.heatmap.map((h) => h.total))

function statusLabel(inv) {
    if (inv.is_returned || inv.status === 'returned') return 'مرجوع'
    if (inv.status === 'paid') return 'وصول'
    return 'معوق'
}
function statusBadge(inv) {
    if (inv.is_returned || inv.status === 'returned') return 'gs-badge-warning'
    if (inv.status === 'paid') return 'gs-badge-success'
    return 'gs-badge-error'
}
</script>

<style scoped>
.gs-filter {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
    margin-bottom: 1.25rem;
}

.gs-filter-ranges {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.gs-date {
    width: auto;
}

.gs-check {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .82rem;
    color: var(--gs-text-secondary);
}

.gs-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .25rem;
    border-bottom: 1px solid var(--gs-border);
    margin-bottom: 1.25rem;
}

.gs-tab {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--gs-text-secondary);
    font-family: inherit;
    font-weight: 600;
    padding: .55rem .9rem;
    cursor: pointer;
}

.gs-tab.active {
    color: var(--gs-gold);
    border-bottom-color: var(--gs-gold);
}

.gs-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
}

.gs-kpi-val {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--gs-gold);
    line-height: 1.3;
}

.gs-kpi-delta {
    font-size: .72rem;
    font-weight: 600;
}

.gs-kpi-delta.up {
    color: var(--gs-success);
}

.gs-kpi-delta.down {
    color: var(--gs-error);
}

.gs-grid-2 {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 900px) {
    .gs-grid-2 {
        grid-template-columns: 1fr;
    }
}

.gs-card-title {
    font-size: .95rem;
    font-weight: 700;
    margin: 0 0 .75rem;
}

.gs-hint {
    font-size: .78rem;
    color: var(--gs-text-secondary);
    margin: 0 0 .75rem;
}

.gs-table-wrap {
    overflow-x: auto;
}

.strong {
    font-weight: 700;
}

.gold {
    color: var(--gs-gold);
    font-weight: 700;
}

.ok {
    color: var(--gs-success);
    font-weight: 700;
}

.bad {
    color: var(--gs-error);
    font-weight: 700;
}

.empty {
    text-align: center;
    color: var(--gs-text-muted);
    padding: 1.2rem !important;
}
</style>
