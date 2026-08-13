<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">رتبه‌بندی فروش</h1>
            <p class="gs-subtitle">پرفروش‌ترین و پرسودترین محصول و سرویس — برای روز، ماه، سال یا هر بازهٔ دلخواه</p>
        </template>

        <!-- نوار ناوبری -->
        <div class="gs-toolbar">
            <Link :href="route('stats.index', query)" class="gs-btn gs-btn-ghost">← مرکز گزارشات</Link>
            <Link :href="route('stats.products', query)" class="gs-btn gs-btn-ghost">کالا</Link>
            <Link :href="route('stats.services', query)" class="gs-btn gs-btn-ghost">سرویس</Link>
            <button type="button" class="gs-btn gs-btn-primary" @click="reload">بروزرسانی</button>
        </div>

        <!-- فیلتر بازه -->
        <form class="gs-filter" @submit.prevent="apply">
            <div class="gs-filter-ranges">
                <button type="button" v-for="r in ranges" :key="r.id" class="gs-btn"
                    :class="range === r.id ? 'gs-btn-primary' : 'gs-btn-ghost'" @click="setRange(r.id)">{{ r.label }}</button>
            </div>
            <label class="gs-check">
                <input type="checkbox" v-model="paidOnly">
                فقط وصول‌شده
            </label>
            <JalaliDateInput v-model="from" class="gs-date" placeholder="از تاریخ" />
            <JalaliDateInput v-model="to" class="gs-date" placeholder="تا تاریخ" />
            <button type="submit" class="gs-btn gs-btn-primary">اعمال</button>
        </form>

        <!-- سورت و فیلتر -->
        <div class="rk-toolbar">
            <div class="rk-toolbar-group">
                <label class="rk-label">مرتب‌سازی:</label>
                <select class="gs-input rk-select" v-model="sort">
                    <option value="qty">📊 پرفروش‌ترین (تعداد)</option>
                    <option value="revenue">💰 بیشترین فروش (ریالی)</option>
                    <option value="profit">🏆 پرسودترین</option>
                    <option value="margin">📈 بالاترین حاشیه</option>
                    <option value="avg_sell">💎 گران‌ترین</option>
                </select>
            </div>
            <div class="rk-toolbar-group">
                <label class="rk-label">دسته‌بندی:</label>
                <select class="gs-input rk-select" v-model="category">
                    <option value="">همهٔ دسته‌ها</option>
                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>
            <div class="rk-toolbar-group">
                <label class="rk-label">تعداد:</label>
                <select class="gs-input rk-select rk-limit" v-model="limit">
                    <option :value="10">۱۰</option>
                    <option :value="20">۲۰</option>
                    <option :value="50">۵۰</option>
                    <option :value="0">همه</option>
                </select>
            </div>
        </div>

        <!-- تب‌های رتبه‌بندی -->
        <div class="gs-tabs" style="margin-bottom:1rem">
            <button type="button" class="gs-tab" :class="{ active: pane === 'product' }" @click="pane = 'product'">📦 محصولات</button>
            <button type="button" class="gs-tab" :class="{ active: pane === 'service' }" @click="pane = 'service'">🔧 سرویس‌ها</button>
            <button type="button" class="gs-tab" :class="{ active: pane === 'slow' }" @click="pane = 'slow'">🧊 کندفروش</button>
            <button type="button" class="gs-tab" :class="{ active: pane === 'category' }" @click="pane = 'category'">📊 تفکیک دسته</button>
        </div>

        <!-- ================= محصولات ================= -->
        <section v-show="pane === 'product'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">تعداد کالای رتبه‌بندی‌شده</p>
                    <p class="gs-num">{{ fa(rankedProducts.length) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">درآمد همین لیست</p>
                    <p class="gs-num">{{ money(rankedProducts.reduce((s, p) => s + (p.revenue || 0), 0)) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">سود همین لیست</p>
                    <p class="gs-num">{{ money(rankedProducts.reduce((s, p) => s + (p.profit || 0), 0)) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">مرتب‌سازی</p>
                    <p class="gs-num">{{ sortLabel }}</p>
                </article>
            </div>

            <div class="gs-grid">
                <div class="gs-card">
                    <h3 class="gs-card-title">🏆 ۵ تای اول — {{ sortLabel }}</h3>
                    <GsChart type="hbar" :labels="topNames" :datasets="[{ data: topValues, color: sortColor }]" :height="230" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">درآمد در برابر سود — ۵ تای اول</h3>
                    <GsChart type="bar" :labels="topNames"
                        :datasets="[
                            { label: 'درآمد', data: topRevenues, color: '#c9a84c' },
                            { label: 'سود', data: topProfits, color: '#4caf7d' },
                        ]" :height="230" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جدول رتبه‌بندی محصولات</h3>
                <p class="gs-hint">
                    ردیف‌های ۱ تا ۳ با رنگ مدال مشخص‌اند. ⚠ یعنی موجودی کم است و در آستانهٔ اتمام.
                </p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>رتبه</th>
                                <th>کالا</th>
                                <th>دسته</th>
                                <th>تعداد فروش</th>
                                <th>فروش واحد</th>
                                <th>درآمد</th>
                                <th>سود</th>
                                <th>حاشیه</th>
                                <th>موجودی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in rankedProducts" :key="p.item_id || p.name"
                                :class="medalClass(i)">
                                <td class="rk-rank">{{ fa(i + 1) }}</td>
                                <td class="strong">{{ p.name }}</td>
                                <td><span class="gs-badge gs-badge-gold">{{ p.category }}</span></td>
                                <td>{{ fa(p.qty) }}</td>
                                <td>{{ money(p.avg_sell) }}</td>
                                <td class="gold">{{ money(p.revenue) }}</td>
                                <td :class="p.profit >= 0 ? 'ok' : 'bad'">{{ money(p.profit) }}</td>
                                <td>{{ fa(p.margin) }}٪</td>
                                <td>
                                    <span v-if="p.stock !== null && p.stock <= 2" class="gs-badge gs-badge-error">⚠ {{ fa(p.stock) }}</span>
                                    <span v-else-if="p.stock !== null">{{ fa(p.stock) }}</span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                            <tr v-if="!rankedProducts.length">
                                <td colspan="9" class="empty">در این بازه و دسته فروش کالایی ثبت نشده — بازهٔ تاریخ را عوض کن</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= سرویس‌ها ================= -->
        <section v-show="pane === 'service'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">درآمد سرویس</p>
                    <p class="gs-num">{{ money(kpi.service_revenue) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">خالص سرویس</p>
                    <p class="gs-num">{{ money(kpi.service_net) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">تعداد کار</p>
                    <p class="gs-num">{{ fa(kpi.service_jobs) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">پردرآمدترین نوع</p>
                    <p class="gs-num rk-small">{{ topService?.name ?? '—' }}</p>
                </article>
            </div>

            <div class="gs-grid">
                <div class="gs-card">
                    <h3 class="gs-card-title">🏆 پردرآمدترین سرویس‌ها</h3>
                    <GsChart type="hbar" :labels="topServiceNames" :datasets="[{ data: topServiceRevenues, color: '#4c8fe0' }]" :height="230" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">درآمد در برابر خالص</h3>
                    <GsChart type="bar" :labels="topServiceNames"
                        :datasets="[
                            { label: 'درآمد', data: topServiceRevenues, color: '#4c8fe0' },
                            { label: 'خالص', data: topServiceNets, color: '#4caf7d' },
                        ]" :height="230" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جدول رتبه‌بندی سرویس‌ها</h3>
                <p class="gs-hint">درآمد از final_price سرویس‌جاب. خالص = درآمد − جمع قطعات مصرفی از انبار.</p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>رتبه</th>
                                <th>نوع سرویس</th>
                                <th>تعداد کار</th>
                                <th>میانگین</th>
                                <th>درآمد</th>
                                <th>هزینه قطعه</th>
                                <th>خالص</th>
                                <th>باز</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in rankedServices" :key="s.service_type_id || s.name" :class="medalClass(i)">
                                <td class="rk-rank">{{ fa(i + 1) }}</td>
                                <td class="strong">{{ s.name }}</td>
                                <td>{{ fa(s.jobs) }}</td>
                                <td>{{ money(s.avg) }}</td>
                                <td class="gold">{{ money(s.revenue) }}</td>
                                <td>{{ money(s.parts_cost) }}</td>
                                <td class="ok">{{ money(s.net) }}</td>
                                <td>
                                    <span v-if="(s.open ?? 0) > 0" class="gs-badge gs-badge-warning">{{ fa(s.open ?? 0) }}</span>
                                    <span v-else class="gs-badge gs-badge-success">۰</span>
                                </td>
                            </tr>
                            <tr v-if="!rankedServices.length">
                                <td colspan="8" class="empty">در این بازه سرویسی ثبت نشده — بازهٔ تاریخ را عوض کن</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= کندفروش ================= -->
        <section v-show="pane === 'slow'">
            <div class="gs-card">
                <h3 class="gs-card-title">🧊 محصولات کندفروش — موجودی بالا نسبت به فروش</h3>
                <p class="gs-hint">
                    محصولاتی که فروش داشته‌اند ولی موجودی انبارشان حداقل ۳ برابر فروشِ بازه است.
                    ارزش منجمد = موجودی × قیمت فروش واحد.
                </p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>کالا</th>
                                <th>دسته</th>
                                <th>فروش در بازه</th>
                                <th>موجودی</th>
                                <th>نسبت</th>
                                <th>ارزش منجمد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in slowMovers" :key="p.item_id || p.name">
                                <td class="strong">{{ p.name }}</td>
                                <td><span class="gs-badge gs-badge-gold">{{ p.category }}</span></td>
                                <td>{{ fa(p.qty) }}</td>
                                <td>
                                    <span class="gs-badge" :class="p.stock >= 10 ? 'gs-badge-error' : 'gs-badge-warning'">{{ fa(p.stock) }}</span>
                                </td>
                                <td>{{ p.ratio }}×</td>
                                <td class="gold">{{ money(p.frozen) }}</td>
                            </tr>
                            <tr v-if="!slowMovers.length">
                                <td colspan="6" class="empty">محصول کندفروشی یافت نشد ✓</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= تفکیک دسته ================= -->
        <section v-show="pane === 'category'">
            <div class="gs-grid">
                <div class="gs-card">
                    <h3 class="gs-card-title">📊 درآمد به تفکیک دسته</h3>
                    <GsChart type="doughnut" :labels="categoryLabels" :datasets="[{ data: categoryRevenues }]" :height="260" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">💰 سود به تفکیک دسته</h3>
                    <GsChart type="doughnut" :labels="categoryLabels" :datasets="[{ data: categoryProfits }]" :height="260" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جدول دسته‌بندی‌ها</h3>
                <p class="gs-hint">مناسب فروشگاه کنسول، گیم، لوازم جانبی، موبایل و دیجیتال — از دستهٔ آیتم‌ها خوانده می‌شود.</p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>دسته</th>
                                <th>تعداد کالا</th>
                                <th>کل فروش</th>
                                <th>درآمد</th>
                                <th>سود</th>
                                <th>حاشیه</th>
                                <th>سهم از درآمد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in categoryRows" :key="c.name">
                                <td class="strong">{{ c.name }}</td>
                                <td>{{ fa(c.count) }}</td>
                                <td>{{ fa(c.qty) }}</td>
                                <td class="gold">{{ money(c.revenue) }}</td>
                                <td :class="c.profit >= 0 ? 'ok' : 'bad'">{{ money(c.profit) }}</td>
                                <td>{{ fa(c.margin) }}٪</td>
                                <td>
                                    <div class="rk-bar">
                                        <div class="rk-bar-fill" :style="{ width: c.share + '%' }"></div>
                                        <span>{{ fa(c.share) }}٪</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!categoryRows.length">
                                <td colspan="7" class="empty">دسته‌بندی‌ای ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import GsChart from '@/Components/GsChart.vue'

const props = defineProps({
    from:     { type: String, default: '' },
    to:       { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range:    { type: String, default: 'month' },
    kpi:      { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    stock:    { type: Object, default: () => ({}) },
    funnel:   { type: Array, default: () => [] },
})

/* ─── State ─── */
const pane     = ref('product')
const sort     = ref('profit')
const category = ref('')
const limit    = ref(10)

const range    = ref(props.range)
const paidOnly = ref(props.paidOnly)
const from     = ref(props.from)
const to       = ref(props.to)

const ranges = [
    { id: 'today', label: 'امروز' },
    { id: 'week',  label: 'هفته' },
    { id: 'month', label: 'ماه' },
    { id: 'year',  label: 'سال' },
]

/* ─── Helpers ─── */
function fa(n)    { return Number(n || 0).toLocaleString('fa-IR') }
function money(n) { return fa(Math.round(Number(n || 0))) + ' تومان' }

const query = computed(() => ({
    from: from.value || undefined,
    to: to.value || undefined,
    paid_only: paidOnly.value ? 1 : 0,
    range: range.value,
}))

function setRange(id) {
    range.value = id
    from.value = ''
    to.value = ''
    apply()
}

function apply() {
    router.get(route('stats.ranking'), {
        from: from.value || undefined,
        to: to.value || undefined,
        paid_only: paidOnly.value ? 1 : 0,
        range: range.value,
    }, { preserveState: true, replace: true })
}

function reload() {
    router.reload({ preserveScroll: true })
}

/* ─── سورت ─── */
const sortLabel = computed(() => ({
    qty: 'تعداد فروش',
    revenue: 'درآمد ریالی',
    profit: 'سود مطلق',
    margin: 'حاشیه سود',
    avg_sell: 'قیمت فروش',
}[sort.value] || 'سود'))

const sortColor = computed(() => ({
    qty: '#c9a84c',
    revenue: '#c9a84c',
    profit: '#4caf7d',
    margin: '#4c8fe0',
    avg_sell: '#8b5cf6',
}[sort.value] || '#4caf7d'))

const categories = computed(() => {
    const set = new Set(props.products.map(p => p.category).filter(Boolean))
    return [...set].sort()
})

/* ─── محصولات: فیلتر + سورت + محدودیت ─── */
const rankedProducts = computed(() => {
    let arr = [...props.products]
    if (category.value) {
        arr = arr.filter(p => p.category === category.value)
    }
    const key = sort.value
    arr.sort((a, b) => (Number(b[key]) || 0) - (Number(a[key]) || 0))
    if (limit.value > 0) {
        arr = arr.slice(0, limit.value)
    }
    return arr
})

const top5       = computed(() => rankedProducts.value.slice(0, 5))
const topNames   = computed(() => top5.value.map(p => p.name))
const topValues  = computed(() => top5.value.map(p => p[sort.value] ?? 0))
const topRevenues = computed(() => top5.value.map(p => p.revenue))
const topProfits  = computed(() => top5.value.map(p => p.profit))

/* ─── سرویس‌ها ─── */
const rankedServices = computed(() =>
    [...props.services].sort((a, b) => (b.revenue || 0) - (a.revenue || 0)))

const topService = computed(() => rankedServices.value[0] ?? null)
const top5Services   = computed(() => rankedServices.value.slice(0, 5))
const topServiceNames    = computed(() => top5Services.value.map(s => s.name))
const topServiceRevenues = computed(() => top5Services.value.map(s => s.revenue))
const topServiceNets     = computed(() => top5Services.value.map(s => s.net))

/* ─── کندفروش: موجودی ≥ ۳ برابر فروش بازه ─── */
const slowMovers = computed(() => {
    const stockMap = props.stock || {}
    return props.products
        .map(p => {
            const stock = stockMap[p.item_id] ?? p.stock ?? 0
            return { ...p, stock }
        })
        .filter(p => p.stock > 0 && p.qty > 0 && p.stock >= p.qty * 3)
        .map(p => ({
            ...p,
            ratio: p.qty > 0 ? (p.stock / p.qty).toFixed(1) : '—',
            frozen: (p.stock || 0) * (p.avg_sell || 0),
        }))
        .sort((a, b) => b.frozen - a.frozen)
})

/* ─── تفکیک دسته ─── */
const categoryRows = computed(() => {
    const map = {}
    const totalRevenue = props.products.reduce((s, p) => s + (p.revenue || 0), 0) || 1
    props.products.forEach(p => {
        const cat = p.category || 'بدون دسته'
        if (!map[cat]) map[cat] = { name: cat, count: 0, qty: 0, revenue: 0, cogs: 0, profit: 0 }
        map[cat].count++
        map[cat].qty     += p.qty || 0
        map[cat].revenue += p.revenue || 0
        map[cat].cogs    += p.cogs || 0
        map[cat].profit  += p.profit || 0
    })
    return Object.values(map)
        .map(c => ({
            ...c,
            revenue: Math.round(c.revenue),
            profit:  Math.round(c.profit),
            margin:  c.revenue > 0 ? Math.round(c.profit / c.revenue * 100) : 0,
            share:   Math.round(c.revenue / totalRevenue * 100),
        }))
        .sort((a, b) => b.revenue - a.revenue)
})

const categoryLabels   = computed(() => categoryRows.value.map(c => c.name))
const categoryRevenues = computed(() => categoryRows.value.map(c => c.revenue))
const categoryProfits  = computed(() => categoryRows.value.map(c => c.profit))

function medalClass(i) {
    return i === 0 ? 'rk-gold' : i === 1 ? 'rk-silver' : i === 2 ? 'rk-bronze' : ''
}
</script>

<style scoped>
.gs-toolbar  { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
.gs-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
.gs-num      { font-size: 1.25rem; font-weight: 800; color: var(--gs-gold); }
.rk-small    { font-size: 1rem; }
.gs-grid     { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 900px) { .gs-grid { grid-template-columns: 1fr; } }
.gs-card-title { margin: 0 0 .75rem; font-size: .95rem; }
.gs-hint       { font-size: .78rem; color: var(--gs-text-secondary); margin: 0 0 .75rem; }
.gs-table-wrap { overflow-x: auto; }
.strong { font-weight: 700; }
.gold   { color: var(--gs-gold); font-weight: 700; }
.ok     { color: var(--gs-success); font-weight: 700; }
.bad    { color: var(--gs-error); font-weight: 700; }
.empty  { text-align: center; color: var(--gs-text-muted); padding: 1.2rem !important; }

/* ─── نوار سورت ─── */
.rk-toolbar {
    display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;
    margin-bottom: 1rem; padding: .75rem 1rem;
    background: var(--gs-bg-elevated);
    border: 1px solid var(--gs-border);
    border-radius: 10px;
}
.rk-toolbar-group { display: flex; flex-direction: column; gap: .3rem; }
.rk-label { font-size: .72rem; color: var(--gs-text-muted); }
.rk-select { min-width: 170px; cursor: pointer; }
.rk-limit { min-width: 70px; }

/* ─── جدول مدال‌دار ─── */
.rk-rank {
    font-weight: 800; font-size: .9rem; width: 40px; text-align: center;
    color: var(--gs-gold);
}
.rk-gold   { background: rgba(201, 168, 76, .12); }
.rk-silver { background: rgba(192, 192, 192, .08); }
.rk-bronze { background: rgba(205, 127, 50, .07); }

/* ─── نوار سهم ─── */
.rk-bar {
    display: flex; align-items: center; gap: .4rem; min-width: 110px;
}
.rk-bar-fill {
    height: 6px;
    background: linear-gradient(90deg, var(--gs-gold-dark), var(--gs-gold-light));
    border-radius: 4px; min-width: 4px;
    transition: width .3s ease;
}
.rk-bar span { font-size: .75rem; color: var(--gs-text-muted); white-space: nowrap; }

.gs-date { width: 155px; }
</style>
