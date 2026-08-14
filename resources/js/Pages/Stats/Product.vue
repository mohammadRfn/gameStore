<template>
    <AppLayout>
        <StatHero
            title="سود کالا"
            subtitle="مابه‌تفاوت قیمت خرید و فروش روی فاکتورهای واقعی — متصل به آیتم و گردش انبار"
            :from="from" :to="to" :range="range"
        >
            <template #actions>
                <Link :href="route('stats.index', query)" class="gs-btn gs-btn-soft">مرکز گزارشات</Link>
                <Link :href="route('stats.services', query)" class="gs-btn gs-btn-soft">سرویس</Link>
                <Link :href="route('stats.ranking', query)" class="gs-btn gs-btn-soft">رتبه‌بندی</Link>
            </template>
        </StatHero>

        <RangeFilter
            :from="from" :to="to" :paid-only="paidOnly" :range="range"
            route-name="stats.products" class="mb"
        />

        <div class="gs-kpi-grid gs-stagger">
            <KpiCard label="فروش کالا" :value="compactMoney(kpi.product_revenue)" :icon="Package" tone="gold" />
            <KpiCard label="بهای تمام‌شده" :value="compactMoney(kpi.product_cogs)" :icon="Coins" tone="gold" />
            <KpiCard label="سود ناخالص" :value="compactMoney(kpi.product_profit)" :icon="TrendingUp" tone="green" />
            <KpiCard label="حاشیهٔ سود" :value="percent(kpi.product_margin)" :icon="Percent" tone="violet" />
        </div>

        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><BarChart3 class="ic" :size="17" /> سود هر کالا — ۱۰ تای اول</h3>
                </div>
                <Bar3D :labels="topNames" :values="topProfits" color="#45d68b" :money="true" :height="290" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><Scale class="ic" :size="17" /> درآمد در برابر خرید</h3>
                </div>
                <GsChart type="bar" :labels="topNames"
                    :datasets="[
                        { label: 'درآمد', data: topRevenues, color: '#e3bd5c' },
                        { label: 'خرید', data: topCosts, color: '#f06a6a' },
                    ]" :height="290" />
            </section>
        </div>

        <section class="gs-card">
            <div class="gs-card-head">
                <h3 class="gs-card-title"><Table class="ic" :size="17" /> جزئیات کالا</h3>
                <span class="gs-label">{{ faInt(products.length) }} کالا</span>
            </div>
            <p class="gs-hint" style="margin-bottom: 0.9rem;">
                فرمول سود: (قیمت فروش روی فاکتور − قیمت خرید کاتالوگ) × تعداد. مرجوعی‌ها حذف شده و موجودی از گردش انبار است.
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
                                <Link v-if="row.item_id" :href="route('items.edit', row.item_id)" class="strong gold" style="text-decoration: none;">
                                    {{ row.name }}
                                </Link>
                                <span v-else class="strong">{{ row.name }}</span>
                            </td>
                            <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                            <td>{{ faInt(row.qty) }}</td>
                            <td>{{ money(row.avg_buy) }}</td>
                            <td>{{ money(row.avg_sell) }}</td>
                            <td class="gold">{{ money(row.revenue) }}</td>
                            <td>{{ money(row.cogs) }}</td>
                            <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                            <td>{{ percent(row.margin) }}</td>
                            <td>
                                <span v-if="row.stock !== null && row.stock <= 2" class="gs-badge gs-badge-error">کم {{ faInt(row.stock) }}</span>
                                <span v-else-if="row.stock !== null">{{ faInt(row.stock) }}</span>
                                <span v-else class="gs-label">—</span>
                            </td>
                        </tr>
                        <tr v-if="!products.length">
                            <td colspan="10" class="gs-empty">در این بازه فروش کالایی ثبت نشده</td>
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
import { Package, Coins, TrendingUp, Percent, BarChart3, Scale, Table } from '@lucide/vue'
import { faInt, money, compactMoney, percent } from '@/Utils/format'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range: { type: String, default: 'month' },
    kpi: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
})

const query = computed(() => ({
    from: props.from || undefined,
    to: props.to || undefined,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const top = computed(() => props.products.slice(0, 10))
const topNames = computed(() => top.value.map((p) => p.name))
const topProfits = computed(() => top.value.map((p) => p.profit))
const topRevenues = computed(() => top.value.map((p) => p.revenue))
const topCosts = computed(() => top.value.map((p) => p.cogs))
</script>

<style scoped>
.mb { margin-bottom: 1rem; }
</style>
