<template>
    <AppLayout>
        <StatHero
            title="درآمد سرویس"
            subtitle="تفکیک نوع سرویس، هزینهٔ قطعات مصرفی و کارهای باز"
            :from="from" :to="to" :range="range"
        >
            <template #actions>
                <Link :href="route('stats.index', query)" class="gs-btn gs-btn-soft">مرکز گزارشات</Link>
                <Link :href="route('stats.products', query)" class="gs-btn gs-btn-soft">کالا</Link>
                <Link :href="route('stats.ranking', query)" class="gs-btn gs-btn-soft">رتبه‌بندی</Link>
            </template>
        </StatHero>

        <RangeFilter
            :from="from" :to="to" :paid-only="paidOnly" :range="range"
            route-name="stats.services" class="mb"
        />

        <div class="gs-kpi-grid gs-stagger">
            <KpiCard label="درآمد سرویس" :value="compactMoney(kpi.service_revenue)" :icon="Wrench" tone="blue" />
            <KpiCard label="هزینهٔ قطعه" :value="compactMoney(kpi.service_parts)" :icon="Cog" tone="gold" />
            <KpiCard label="خالص سرویس" :value="compactMoney(kpi.service_net)" :icon="TrendingUp" tone="green" />
            <KpiCard label="تعداد کار" :value="faInt(kpi.service_jobs)" :icon="ClipboardList" tone="violet" />
        </div>

        <div class="gs-grid-2">
            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><BarChart3 class="ic" :size="17" /> درآمد به تفکیک نوع سرویس</h3>
                </div>
                <Bar3D :labels="serviceNames" :values="serviceRevenues" color="#5b9df0" :money="true" :height="290" />
            </section>

            <section class="gs-card">
                <div class="gs-card-head">
                    <h3 class="gs-card-title"><Filter class="ic" :size="17" /> قیف وضعیت کارها</h3>
                </div>
                <GsChart type="doughnut" :labels="funnelLabels" :datasets="[{ data: funnelData }]" :height="290" />
            </section>
        </div>

        <section class="gs-card">
            <div class="gs-card-head">
                <h3 class="gs-card-title"><Table class="ic" :size="17" /> جدول سرویس</h3>
                <span class="gs-label">{{ faInt(services.length) }} نوع سرویس</span>
            </div>
            <p class="gs-hint" style="margin-bottom: 0.9rem;">
                درآمد از service_jobs.final_price و هزینهٔ قطعه از service_job_items × قیمت خرید آیتم محاسبه می‌شود.
            </p>
            <div class="gs-table-wrap">
                <table class="gs-table">
                    <thead>
                        <tr>
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
                        <tr v-for="row in services" :key="row.service_type_id || row.name">
                            <td class="strong">{{ row.name }}</td>
                            <td>{{ faInt(row.jobs) }}</td>
                            <td>{{ money(row.avg) }}</td>
                            <td class="gold">{{ money(row.revenue) }}</td>
                            <td>{{ money(row.parts_cost) }}</td>
                            <td class="ok">{{ money(row.net) }}</td>
                            <td>
                                <span v-if="(row.open ?? 0) > 0" class="gs-badge gs-badge-warning">{{ faInt(row.open) }}</span>
                                <span v-else class="gs-badge gs-badge-success">۰</span>
                            </td>
                        </tr>
                        <tr v-if="!services.length">
                            <td colspan="7" class="gs-empty">در این بازه سرویسی ثبت نشده</td>
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
import { Wrench, Cog, TrendingUp, ClipboardList, BarChart3, Filter, Table } from '@lucide/vue'
import { faInt, money, compactMoney } from '@/Utils/format'

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
    from: props.from || undefined,
    to: props.to || undefined,
    paid_only: props.paidOnly ? 1 : 0,
    range: props.range,
}))

const serviceNames = computed(() => props.services.map((s) => s.name))
const serviceRevenues = computed(() => props.services.map((s) => s.revenue))
const funnelLabels = computed(() => (props.funnel || []).map((f) => f.label))
const funnelData = computed(() => (props.funnel || []).map((f) => f.count))
</script>

<style scoped>
.mb { margin-bottom: 1rem; }
</style>
