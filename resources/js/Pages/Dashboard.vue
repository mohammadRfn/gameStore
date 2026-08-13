<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">داشبورد</h1>
            <p class="gs-subtitle">خوش آمدی، {{ $page.props.auth.user.name }}</p>
        </template>

        <!-- Stats Grid -->
        <div class="gs-stats-grid">
            <div class="gs-card gs-stat-card">
                <span class="gs-stat-icon">👤</span>
                <div>
                    <p class="gs-label">مشتریان</p>
                    <p class="gs-stat-num">{{ stats.customers_count }}</p>
                </div>
            </div>
            <div class="gs-card gs-stat-card">
                <span class="gs-stat-icon">📋</span>
                <div>
                    <p class="gs-label">درخواست‌های باز</p>
                    <p class="gs-stat-num">{{ stats.open_requests }}</p>
                </div>
            </div>
            <div class="gs-card gs-stat-card">
                <span class="gs-stat-icon">📦</span>
                <div>
                    <p class="gs-label">اقلام انبار</p>
                    <p class="gs-stat-num">{{ stats.items_count }}</p>
                </div>
            </div>
            <div class="gs-card gs-stat-card">
                <span class="gs-stat-icon">🔧</span>
                <div>
                    <p class="gs-label">سرویس‌های جاری</p>
                    <p class="gs-stat-num">{{ stats.active_service_jobs }}</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="gs-section-title">
            <span>دسترسی سریع</span>
            <div class="gs-gold-border-b" style="flex:1;margin-right:.75rem"></div>
        </div>

        <div class="gs-quick-grid">
            <Link :href="route('customers.create')" class="gs-quick-card">
                <span class="gs-quick-icon">👤</span>
                <span>مشتری جدید</span>
            </Link>
            <Link :href="route('requests.create')" class="gs-quick-card">
                <span class="gs-quick-icon">📋</span>
                <span>درخواست جدید</span>
            </Link>
            <Link :href="route('invoices.create')" class="gs-quick-card">
                <span class="gs-quick-icon">🧾</span>
                <span>فاکتور جدید</span>
            </Link>
            <Link :href="route('service-jobs.create')" class="gs-quick-card">
                <span class="gs-quick-icon">🔧</span>
                <span>سرویس جدید</span>
            </Link>
            <Link :href="route('items.create')" class="gs-quick-card">
                <span class="gs-quick-icon">📦</span>
                <span>محصول جدید</span>
            </Link>
            <Link :href="route('stock-movements.store')" class="gs-quick-card">
                <span class="gs-quick-icon">🔄</span>
                <span>ثبت ورودی انبار</span>
            </Link>
        </div>

        <!-- Two columns: recent requests + recent invoices -->
        <div class="gs-two-col">

            <!-- Recent Requests -->
            <div class="gs-card">
                <div class="gs-card-head">
                    <span class="gs-subtitle">آخرین درخواست‌ها</span>
                    <Link :href="route('requests.index')" class="gs-btn gs-btn-ghost gs-btn-sm">همه</Link>
                </div>
                <table class="gs-table" v-if="recentRequests.length">
                    <thead>
                        <tr>
                            <th>مشتری</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="req in recentRequests" :key="req.id">
                            <td>{{ req.customer_name }}</td>
                            <td>
                                <span :class="['gs-badge', statusBadge(req.status)]">
                                    {{ statusLabel(req.status) }}
                                </span>
                            </td>
                            <td>
                                <Link :href="route('requests.show', req.id)"
                                    class="gs-btn gs-btn-ghost gs-btn-sm">مشاهده</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="gs-label" style="text-align:center;padding:1rem">درخواستی ثبت نشده</p>
            </div>

            <!-- Recent Invoices -->
            <div class="gs-card">
                <div class="gs-card-head">
                    <span class="gs-subtitle">آخرین فاکتورها</span>
                    <Link :href="route('invoices.index')" class="gs-btn gs-btn-ghost gs-btn-sm">همه</Link>
                </div>
                <table class="gs-table" v-if="recentInvoices.length">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inv in recentInvoices" :key="inv.id">
                            <td class="gs-gold-text">{{ inv.invoice_number }}</td>
                            <td>{{ formatPrice(inv.total_amount) }}</td>
                            <td>
                                <span :class="['gs-badge', inv.is_confirmed === 1 ? 'gs-badge-success' : inv.is_confirmed === 0 ? 'gs-badge-error' : 'gs-badge-warning']">
                                    {{ inv.is_confirmed === 1 ? 'تأیید شده' : inv.is_confirmed === 0 ? 'رد شده' : 'در انتظار' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="gs-label" style="text-align:center;padding:1rem">فاکتوری ثبت نشده</p>
            </div>

        </div>

    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    stats: Object,
    recentRequests: Array,
    recentInvoices: Array,
})

function statusLabel(status) {
    const map = {
        pending: 'در انتظار',
        in_progress: 'در جریان',
        completed: 'تکمیل شده',
        canceled: 'لغو شده',
    }
    return map[status] ?? status
}

function statusBadge(status) {
    const map = {
        pending: 'gs-badge-warning',
        in_progress: 'gs-badge-info',
        completed: 'gs-badge-success',
        canceled: 'gs-badge-error',
    }
    return map[status] ?? 'gs-badge-gold'
}

function formatPrice(amount) {
    if (!amount) return '—'
    return Number(amount).toLocaleString('fa-IR') + ' تومان'
}
</script>

<style scoped>
.gs-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.gs-stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.gs-stat-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.gs-stat-num {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--gs-gold);
    line-height: 1;
    margin-top: .2rem;
}

.gs-section-title {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .8rem;
    font-weight: 500;
    color: var(--gs-gold);
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 1rem;
}

.gs-quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: .75rem;
    margin-bottom: 2rem;
}

.gs-quick-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding: 1.25rem .75rem;
    background: var(--gs-bg-elevated);
    border: 1px solid var(--gs-border);
    border-radius: 12px;
    text-decoration: none;
    color: var(--gs-text-secondary);
    font-size: .875rem;
    font-weight: 500;
    transition: all var(--gs-transition);
    text-align: center;
}

.gs-quick-card:hover {
    border-color: var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    transform: translateY(-2px);
    box-shadow: var(--gs-shadow-gold);
}

.gs-quick-icon {
    font-size: 1.5rem;
}

.gs-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

@media (max-width: 768px) {
    .gs-two-col {
        grid-template-columns: 1fr;
    }
}

.gs-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}
</style>
