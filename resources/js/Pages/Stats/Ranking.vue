<template>
    <AppLayout>
        <StatHero title="رتبه‌بندی فروش" subtitle="پرفروش‌ترین و پرسودترین محصول و سرویس — برای هر بازهٔ دلخواه"
            :from="from" :to="to" :range="range">
            <template #actions>
                <Link :href="route('stats.index', query)" class="gs-btn gs-btn-soft">مرکز گزارشات</Link>
                <Link :href="route('stats.products', query)" class="gs-btn gs-btn-soft">کالا</Link>
                <Link :href="route('stats.services', query)" class="gs-btn gs-btn-soft">سرویس</Link>
            </template>
        </StatHero>

        <RangeFilter :from="from" :to="to" :paid-only="paidOnly" :range="range" route-name="stats.ranking" class="mb" />

        <!-- کنترل‌ها -->
        <div class="gs-tabs">
            <button class="gs-tab" :class="{ active: pane === 'product' }" @click="pane = 'product'">
                <Package :size="15" /> محصولات
            </button>
            <button class="gs-tab" :class="{ active: pane === 'service' }" @click="pane = 'service'">
                <Wrench :size="15" /> سرویس‌ها
            </button>
            <button class="gs-tab" :class="{ active: pane === 'slow' }" @click="pane = 'slow'">
                <Snowflake :size="15" /> کندفروش
            </button>
            <button class="gs-tab" :class="{ active: pane === 'category' }" @click="pane = 'category'">
                <PieChart :size="15" /> تفکیک دسته
            </button>
        </div>

        <!-- ============ محصولات ============ -->
        <section v-show="pane === 'product'">
            <div class="rk-toolbar">
                <label class="rk-field">
                    <span class="rk-label">مرتب‌سازی</span>
                    <select class="gs-select" v-model="sort">
                        <option value="profit">🏆 پرسودترین</option>
                        <option value="revenue">💰 بیشترین فروش</option>
                        <option value="qty">📊 پرفروش‌ترین (تعداد)</option>
                        <option value="margin">📈 بالاترین حاشیه</option>
                        <option value="avg_sell">💎 گران‌ترین</option>
                    </select>
                </label>
                <label class="rk-field">
                    <span class="rk-label">دسته‌بندی</span>
                    <select class="gs-select" v-model="category">
                        <option value="">همهٔ دسته‌ها</option>
                        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                    </select>
                </label>
                <label class="rk-field">
                    <span class="rk-label">تعداد</span>
                    <select class="gs-select" v-model="limit">
                        <option :value="10">۱۰</option>
                        <option :value="20">۲۰</option>
                        <option :value="50">۵۰</option>
                        <option :value="0">همه</option>
                    </select>
                </label>
            </div>

            <Podium3D :items="podiumProducts" />

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Trophy class="ic" :size="17" /> {{ sortLabel }} — ۵ تای اول
                    </h3>
                </div>
                <Bar3D :labels="topNames" :values="topValues" :color="sortColor"
                    :money="!['qty', 'margin'].includes(sort)" :unit="sort === 'margin' ? '٪' : ''" :height="250" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Table class="ic" :size="17" /> جدول کامل رتبه‌بندی
                    </h3>
                    <span class="gs-label">{{ faInt(rankedProducts.length) }} کالا</span>
                </div>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>رتبه</th>
                                <th>کالا</th>
                                <th>دسته</th>
                                <th>تعداد</th>
                                <th>فروش واحد</th>
                                <th>درآمد</th>
                                <th>سود</th>
                                <th>حاشیه</th>
                                <th>موجودی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in rankedProducts" :key="row.item_id || row.name"
                                :class="medalClass(i)">
                                <td class="rank-cell">{{ faInt(i + 1) }}</td>
                                <td class="strong">{{ row.name }}</td>
                                <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                                <td>{{ faInt(row.qty) }}</td>
                                <td>{{ money(row.avg_sell) }}</td>
                                <td class="gold">{{ money(row.revenue) }}</td>
                                <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                                <td>{{ percent(row.margin) }}</td>
                                <td>
                                    <span v-if="row.stock !== null && row.stock <= 2" class="gs-badge gs-badge-error">کم
                                        {{
                                        faInt(row.stock) }}</span>
                                    <span v-else-if="row.stock !== null">{{ faInt(row.stock) }}</span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                            <tr v-if="!rankedProducts.length">
                                <td colspan="9" class="gs-empty">در این بازه و دسته فروش کالایی ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <!-- ============ سرویس‌ها ============ -->
        <section v-show="pane === 'service'">
            <Podium3D :items="podiumServices" />

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Trophy class="ic" :size="17" /> پردرآمدترین سرویس‌ها
                    </h3>
                </div>
                <Bar3D :labels="topServiceNames" :values="topServiceRevenues" color="#5b9df0" :money="true"
                    :height="250" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Table class="ic" :size="17" /> جدول رتبه‌بندی سرویس
                    </h3>
                </div>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>رتبه</th>
                                <th>نوع سرویس</th>
                                <th>تعداد کار</th>
                                <th>میانگین</th>
                                <th>درآمد</th>
                                <th>قطعه</th>
                                <th>خالص</th>
                                <th>باز</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in rankedServices" :key="row.service_type_id || row.name"
                                :class="medalClass(i)">
                                <td class="rank-cell">{{ faInt(i + 1) }}</td>
                                <td class="strong">{{ row.name }}</td>
                                <td>{{ faInt(row.jobs) }}</td>
                                <td>{{ money(row.avg) }}</td>
                                <td class="gold">{{ money(row.revenue) }}</td>
                                <td>{{ money(row.parts_cost) }}</td>
                                <td class="ok">{{ money(row.net) }}</td>
                                <td>
                                    <span v-if="(row.open ?? 0) > 0" class="gs-badge gs-badge-warning">{{
                                        faInt(row.open)
                                        }}</span>
                                    <span v-else class="gs-badge gs-badge-success">۰</span>
                                </td>
                            </tr>
                            <tr v-if="!rankedServices.length">
                                <td colspan="8" class="gs-empty">سرویسی در این بازه ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <!-- ============ کندفروش ============ -->
        <section v-show="pane === 'slow'">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Snowflake class="ic" :size="17" /> محصولات کندفروش
                    </h3>
                    <span class="gs-label">موجودی ≥ ۳ برابر فروش بازه</span>
                </div>
                <p class="gs-hint" style="margin-bottom: 0.9rem;">
                    کالاهایی که فروش داشته‌اند اما موجودی انبارشان نسبت به فروش بازه زیاد است؛ «ارزش منجمد» = موجودی ×
                    قیمت
                    فروش.
                </p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>کالا</th>
                                <th>دسته</th>
                                <th>فروش بازه</th>
                                <th>موجودی</th>
                                <th>نسبت</th>
                                <th>ارزش منجمد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in slowMovers" :key="row.item_id || row.name">
                                <td class="strong">{{ row.name }}</td>
                                <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                                <td>{{ faInt(row.qty) }}</td>
                                <td>
                                    <span class="gs-badge"
                                        :class="row.stock >= 10 ? 'gs-badge-error' : 'gs-badge-warning'">{{
                                        faInt(row.stock) }}</span>
                                </td>
                                <td>{{ row.ratio }}×</td>
                                <td class="gold">{{ money(row.frozen) }}</td>
                            </tr>
                            <tr v-if="!slowMovers.length">
                                <td colspan="6" class="gs-empty">محصول کندفروشی یافت نشد ✓</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <!-- ============ تفکیک دسته ============ -->
        <section v-show="pane === 'category'">
            <div class="gs-grid-2">
                <section class="gs-card">
                    <div class="gs-card-head">
                        <h3 class="gs-card-title">
                            <PieChart class="ic" :size="17" /> درآمد به تفکیک دسته
                        </h3>
                    </div>
                    <GsChart type="doughnut" :labels="categoryLabels" :datasets="[{ data: categoryRevenues }]"
                        :height="270" />
                </section>
                <section class="gs-card">
                    <div class="gs-card-head">
                        <h3 class="gs-card-title">
                            <Coins class="ic" :size="17" /> سود به تفکیک دسته
                        </h3>
                    </div>
                    <GsChart type="doughnut" :labels="categoryLabels" :datasets="[{ data: categoryProfits }]"
                        :height="270" />
                </section>
            </div>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title">
                        <Table class="ic" :size="17" /> جدول دسته‌ها
                    </h3>
                </div>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>دسته</th>
                                <th>SKU</th>
                                <th>تعداد فروش</th>
                                <th>درآمد</th>
                                <th>سود</th>
                                <th>حاشیه</th>
                                <th>سهم از درآمد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in categoryRows" :key="row.name">
                                <td class="strong">{{ row.name }}</td>
                                <td>{{ faInt(row.count) }}</td>
                                <td>{{ faInt(row.qty) }}</td>
                                <td class="gold">{{ money(row.revenue) }}</td>
                                <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                                <td>{{ percent(row.margin) }}</td>
                                <td>
                                    <div class="share-bar">
                                        <div class="share-fill" :style="{ width: row.share + '%' }"></div>
                                        <span>{{ faInt(row.share) }}٪</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!categoryRows.length">
                                <td colspan="7" class="gs-empty">دسته‌بندی‌ای ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatHero from '@/Components/Stats/StatHero.vue'
import RangeFilter from '@/Components/Stats/RangeFilter.vue'
import Podium3D from '@/Components/Stats/Podium3D.vue'
import GsChart from '@/Components/GsChart.vue'
import Bar3D from '@/Components/Stats/Bar3D.vue'
import { Package, Wrench, Snowflake, PieChart, Trophy, Table, Coins } from '@lucide/vue'
import { faInt, money, percent } from '@/Utils/format'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range: { type: String, default: 'month' },
    kpi: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    stock: { type: Object, default: () => ({}) },
    funnel: { type: Array, default: () => [] },
    rankings: { type: Object, default: () => ({}) },
})

const pane = ref('product')
const sort = ref('profit')
const category = ref('')
const limit = ref(10)

const query = computed(() => ({
    from: props.from || undefined,
    to: props.to || undefined,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const sortLabel = computed(() => ({
    qty: 'تعداد فروش', revenue: 'درآمد ریالی', profit: 'سود مطلق', margin: 'حاشیهٔ سود', avg_sell: 'قیمت فروش',
}[sort.value] || 'سود'))

const sortColor = computed(() => ({
    qty: '#e3bd5c', revenue: '#e3bd5c', profit: '#45d68b', margin: '#9f7bf6', avg_sell: '#5b9df0',
}[sort.value] || '#45d68b'))

const categories = computed(() => {
    const set = new Set(props.products.map((p) => p.category).filter(Boolean))
    return [...set].sort()
})

/* ─── محصولات ─── */
const rankedProducts = computed(() => {
    let arr = [...props.products]
    if (category.value) arr = arr.filter((p) => p.category === category.value)
    const key = sort.value
    arr.sort((a, b) => (Number(b[key]) || 0) - (Number(a[key]) || 0))
    if (limit.value > 0) arr = arr.slice(0, limit.value)
    return arr
})

const podiumProducts = computed(() => rankedProducts.value.slice(0, 3).map((p) => ({
    name: p.name, value: p.profit, sub: 'سود',
})))

const top5 = computed(() => rankedProducts.value.slice(0, 5))
const topNames = computed(() => top5.value.map((p) => p.name))
const topValues = computed(() => top5.value.map((p) => p[sort.value] ?? 0))

/* ─── سرویس‌ها ─── */
const rankedServices = computed(() => {
    const fromServer = props.rankings?.services?.top_revenue
    return fromServer?.length
        ? fromServer
        : [...props.services].sort((a, b) => (b.revenue || 0) - (a.revenue || 0))
})

const podiumServices = computed(() => rankedServices.value.slice(0, 3).map((s) => ({
    name: s.name, value: s.revenue, sub: 'درآمد',
})))

const top5Services = computed(() => rankedServices.value.slice(0, 5))
const topServiceNames = computed(() => top5Services.value.map((s) => s.name))
const topServiceRevenues = computed(() => top5Services.value.map((s) => s.revenue))

/* ─── کندفروش ─── */
const slowMovers = computed(() => {
    const stockMap = props.stock || {}
    return props.products
        .map((p) => ({ ...p, stock: stockMap[p.item_id] ?? p.stock ?? 0 }))
        .filter((p) => p.stock > 0 && p.qty > 0 && p.stock >= p.qty * 3)
        .map((p) => ({
            ...p,
            ratio: p.qty > 0 ? (p.stock / p.qty).toFixed(1) : '—',
            frozen: (p.stock || 0) * (p.avg_sell || 0),
        }))
        .sort((a, b) => b.frozen - a.frozen)
})

/* ─── دسته‌ها ─── */
const categoryRows = computed(() => {
    const fromServer = props.rankings?.categories?.top_revenue
    if (fromServer?.length) return fromServer

    const map = {}
    const totalRevenue = props.products.reduce((s, p) => s + (p.revenue || 0), 0) || 1
    props.products.forEach((p) => {
        const cat = p.category || 'بدون دسته'
        if (!map[cat]) map[cat] = { name: cat, count: 0, qty: 0, revenue: 0, cogs: 0, profit: 0 }
        map[cat].count++
        map[cat].qty += p.qty || 0
        map[cat].revenue += p.revenue || 0
        map[cat].cogs += p.cogs || 0
        map[cat].profit += p.profit || 0
    })
    return Object.values(map)
        .map((c) => ({
            ...c,
            revenue: Math.round(c.revenue),
            profit: Math.round(c.profit),
            margin: c.revenue > 0 ? Math.round(c.profit / c.revenue * 100) : 0,
            share: Math.round(c.revenue / totalRevenue * 100),
        }))
        .sort((a, b) => b.revenue - a.revenue)
})

const categoryLabels = computed(() => categoryRows.value.map((c) => c.name))
const categoryRevenues = computed(() => categoryRows.value.map((c) => c.revenue))
const categoryProfits = computed(() => categoryRows.value.map((c) => c.profit))

function medalClass(i) {
    return i === 0 ? 'rank-gold' : i === 1 ? 'rank-silver' : i === 2 ? 'rank-bronze' : ''
}
</script>

<style scoped>
.mb {
    margin-bottom: 1rem;
}

.rk-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin: 1rem 0;
    padding: 0.8rem 1rem;
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border);
    border-radius: var(--gs-radius);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.rk-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.rk-field .gs-select {
    min-width: 180px;
}

.rk-label {
    font-size: 0.7rem;
    color: var(--gs-text-muted);
    font-weight: 600;
}

.gs-tabs {
    margin-bottom: 1rem;
}

.rank-cell {
    font-weight: 800;
    font-size: 0.9rem;
    width: 46px;
    text-align: center;
    color: var(--gs-gold);
}

.rank-gold {
    background: rgba(227, 189, 92, 0.12);
}

.rank-silver {
    background: rgba(192, 192, 192, 0.08);
}

.rank-bronze {
    background: rgba(205, 127, 50, 0.08);
}

.share-bar {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 110px;
}

.share-fill {
    height: 6px;
    border-radius: 4px;
    background: var(--gs-gold-grad);
    min-width: 4px;
    transition: width 0.4s var(--gs-ease-spring);
}

.share-bar span {
    font-size: 0.72rem;
    color: var(--gs-text-muted);
    white-space: nowrap;
}
</style>
