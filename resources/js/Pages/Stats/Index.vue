<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">آمار و گزارشات</h1>
                    <p class="gs-subtitle">تحلیل فروش و عملکرد</p>
                </div>
            </div>
        </template>

        <!-- Tab switcher -->
        <div class="gs-tabs" style="margin-bottom:1.5rem">
            <button :class="['gs-tab', { active: tab === 'daily' }]" @click="tab='daily'">آمار روزانه</button>
            <button :class="['gs-tab', { active: tab === 'monthly' }]" @click="tab='monthly'">آمار ماهانه</button>
        </div>

        <!-- Daily Stats -->
        <div v-if="tab === 'daily'">
            <div class="gs-card" style="margin-bottom:1.25rem">
                <div class="gs-filter-row">
                    <div class="gs-input-group" style="margin:0">
                        <label class="gs-input-label">تاریخ</label>
                        <input v-model="dailyDate" type="date" class="gs-input" style="max-width:200px" />
                    </div>
                    <button @click="loadDaily" class="gs-btn gs-btn-primary gs-btn-sm" :disabled="loadingDaily">
                        {{ loadingDaily ? '...' : 'بارگذاری' }}
                    </button>
                </div>
            </div>

            <div v-if="dailyStats.length" class="gs-card" style="padding:0;overflow:hidden">
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border)">
                    <span class="gs-subtitle">فروش روز {{ dailyDate }}</span>
                </div>
                <table class="gs-table">
                    <thead>
                        <tr>
                            <th>رتبه</th>
                            <th>محصول</th>
                            <th>تعداد فروش</th>
                            <th>درآمد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(stat, i) in dailyStats" :key="stat.id">
                            <td>
                                <span :class="['gs-rank', 'rank-' + (i + 1)]">{{ i + 1 }}</span>
                            </td>
                            <td style="font-weight:500">{{ stat.product_name }}</td>
                            <td>
                                <span class="gs-badge gs-badge-info">{{ stat.sold_quantity }}</span>
                            </td>
                            <td class="gs-gold-text" style="font-weight:700">{{ formatPrice(stat.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
                <!-- Total -->
                <div class="gs-total-row">
                    <span class="gs-label">جمع درآمد روز</span>
                    <span class="gs-total-amount">{{ formatPrice(dailyTotal) }}</span>
                </div>
            </div>
            <div v-else-if="!loadingDaily" class="gs-card gs-empty">
                <p style="font-size:2rem">📊</p>
                <p class="gs-subtitle">تاریخ را انتخاب کنید و بارگذاری را بزنید</p>
            </div>
        </div>

        <!-- Monthly Stats -->
        <div v-if="tab === 'monthly'">
            <div class="gs-card" style="margin-bottom:1.25rem">
                <div class="gs-filter-row">
                    <div class="gs-input-group" style="margin:0">
                        <label class="gs-input-label">سال</label>
                        <input v-model="monthlyYear" type="number" class="gs-input" style="max-width:100px" min="1400" max="1420" />
                    </div>
                    <div class="gs-input-group" style="margin:0">
                        <label class="gs-input-label">ماه</label>
                        <select v-model="monthlyMonth" class="gs-input" style="max-width:130px">
                            <option v-for="m in 12" :key="m" :value="m">ماه {{ m }}</option>
                        </select>
                    </div>
                    <button @click="loadMonthly" class="gs-btn gs-btn-primary gs-btn-sm" :disabled="loadingMonthly">
                        {{ loadingMonthly ? '...' : 'بارگذاری' }}
                    </button>
                </div>
            </div>

            <div v-if="monthlySales.length">
                <!-- Summary cards -->
                <div class="gs-monthly-grid" style="margin-bottom:1.25rem">
                    <div class="gs-card gs-stat-card" v-for="sale in monthlySales" :key="sale.id">
                        <p class="gs-label">{{ sale.label ?? 'فروش' }}</p>
                        <p class="gs-stat-num">{{ formatPrice(sale.total_sales) }}</p>
                        <p class="gs-muted" v-if="sale.total_orders">{{ sale.total_orders }} سفارش</p>
                    </div>
                </div>
            </div>
            <div v-else-if="!loadingMonthly" class="gs-card gs-empty">
                <p style="font-size:2rem">📅</p>
                <p class="gs-subtitle">سال و ماه را انتخاب کنید</p>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    dailyStats: { type: Array, default: () => [] },
    monthlySales: { type: Array, default: () => [] },
    initialDate: String,
    initialYear: Number,
    initialMonth: Number,
})

const tab = ref('daily')

// Daily
const today = new Date().toISOString().slice(0, 10)
const dailyDate = ref(props.initialDate ?? today)
const loadingDaily = ref(false)
const dailyStats = ref(props.dailyStats)

const dailyTotal = computed(() => dailyStats.value.reduce((s, r) => s + Number(r.revenue ?? 0), 0))

function loadDaily() {
    loadingDaily.value = true
    router.get(route('stats.daily'), { date: dailyDate.value }, {
        preserveState: true,
        onFinish: () => loadingDaily.value = false,
        onSuccess: (page) => { dailyStats.value = page.props.dailyStats ?? [] }
    })
}

// Monthly
const now = new Date()
const monthlyYear = ref(props.initialYear ?? now.getFullYear())
const monthlyMonth = ref(props.initialMonth ?? now.getMonth() + 1)
const loadingMonthly = ref(false)
const monthlySales = ref(props.monthlySales)

function loadMonthly() {
    loadingMonthly.value = true
    router.get(route('stats.monthly'), { year: monthlyYear.value, month: monthlyMonth.value }, {
        preserveState: true,
        onFinish: () => loadingMonthly.value = false,
        onSuccess: (page) => { monthlySales.value = page.props.monthlySales ?? [] }
    })
}

const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-tabs { display:flex;gap:.25rem;border-bottom:1px solid var(--gs-border) }
.gs-tab { display:flex;align-items:center;padding:.6rem 1.25rem;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;font-family:'IRANYekan',Tahoma,Arial,sans-serif;font-size:.9rem;color:var(--gs-text-secondary);transition:all var(--gs-transition);margin-bottom:-1px }
.gs-tab:hover { color:var(--gs-text-primary) }
.gs-tab.active { color:var(--gs-gold);border-bottom-color:var(--gs-gold) }
.gs-filter-row { display:flex;align-items:flex-end;gap:.75rem;flex-wrap:wrap }
.gs-rank { display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;font-size:.75rem;font-weight:700;background:var(--gs-bg-elevated);color:var(--gs-text-muted) }
.gs-rank.rank-1 { background:var(--gs-gold);color:#0a0a0f }
.gs-rank.rank-2 { background:var(--gs-text-secondary);color:#0a0a0f }
.gs-rank.rank-3 { background:var(--gs-gold-dark);color:#fff }
.gs-total-row { display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:var(--gs-bg-elevated);border-top:1px solid var(--gs-border) }
.gs-total-amount { font-size:1.1rem;font-weight:800;color:var(--gs-gold) }
.gs-monthly-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem }
.gs-stat-card { display:flex;flex-direction:column;gap:.25rem }
.gs-stat-num { font-size:1.25rem;font-weight:800;color:var(--gs-gold) }
.gs-muted { color:var(--gs-text-muted);font-size:.8rem }
.gs-empty { padding:3rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem }
</style>
