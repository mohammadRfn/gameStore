<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">سود کالا</h1>
            <p class="gs-subtitle">مابه‌تفاوت قیمت خرید و فروش روی فاکتورهای واقعی — وصل به آیتم و گردش انبار</p>
        </template>

        <div class="gs-toolbar">
            <Link :href="route('stats.index', query)" class="gs-btn gs-btn-ghost">← نمای کلی</Link>
            <Link :href="route('stats.services', query)" class="gs-btn gs-btn-ghost">سرویس</Link>
            <button type="button" class="gs-btn gs-btn-primary" @click="reload">بروزرسانی</button>
        </div>

        <div class="gs-kpi-grid">
            <article class="gs-card">
                <p class="gs-label">فروش کالا</p>
                <p class="gs-num">{{ money(kpi.product_revenue) }}</p>
            </article>
            <article class="gs-card">
                <p class="gs-label">بهای تمام‌شده</p>
                <p class="gs-num">{{ money(kpi.product_cogs) }}</p>
            </article>
            <article class="gs-card">
                <p class="gs-label">سود ناخالص</p>
                <p class="gs-num">{{ money(kpi.product_profit) }}</p>
            </article>
            <article class="gs-card">
                <p class="gs-label">حاشیه</p>
                <p class="gs-num">{{ fa(kpi.product_margin) }}٪</p>
            </article>
        </div>

        <div class="gs-grid">
            <div class="gs-card">
                <h3 class="gs-card-title">سود هر کالا</h3>
                <GsChart type="hbar" :labels="names" :datasets="[{ data: profits, color: '#4caf7d' }]" :height="300" />
            </div>
            <div class="gs-card">
                <h3 class="gs-card-title">درآمد در برابر خرید</h3>
                <GsChart
                    type="bar"
                    :labels="names"
                    :datasets="[
                        { label: 'درآمد', data: revenues, color: '#c9a84c' },
                        { label: 'خرید', data: costs, color: '#e05c5c' },
                    ]"
                    :height="300"
                />
            </div>
        </div>

        <div class="gs-card">
            <h3 class="gs-card-title">جدول کالا</h3>
            <p class="gs-hint">
                فرمول: (order_items.price − items.purchase_price) × quantity.
                فاکتور مرجوع و ردیف مرجوع حذف می‌شود. موجودی از جمع stock_movements است.
            </p>
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
                            <td>
                                <Link v-if="row.item_id" :href="route('items.edit', row.item_id)" class="strong gold">
                                    {{ row.name }}
                                </Link>
                                <span v-else class="strong">{{ row.name }}</span>
                            </td>
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
    products: { type: Array, default: () => [] },
})

const query = computed(() => ({
    from: props.from,
    to: props.to,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const top = computed(() => props.products.slice(0, 10))
const names = computed(() => top.value.map((p) => p.name))
const profits = computed(() => top.value.map((p) => p.profit))
const revenues = computed(() => top.value.map((p) => p.revenue))
const costs = computed(() => top.value.map((p) => p.cogs))

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
.gold { color: var(--gs-gold); }
.ok { color: var(--gs-success); font-weight: 700; }
.bad { color: var(--gs-error); font-weight: 700; }
.empty { text-align: center; color: var(--gs-text-muted); padding: 1.2rem !important; }
</style>
