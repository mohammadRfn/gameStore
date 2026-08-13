<template>
    <AppLayout>
        <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <h1 class="gs-title">{{ customer.name }}</h1>
                    <p class="gs-subtitle">پروفایل مشتری</p>
                </div>
                <div style="display:flex;gap:.75rem">
                    <Link :href="route('customers.edit', customer.id)" class="gs-btn gs-btn-secondary">ویرایش</Link>
                    <Link :href="route('customers.index')" class="gs-btn gs-btn-ghost">← بازگشت</Link>
                </div>
            </div>
        </template>

        <!-- Info Cards -->
        <div class="gs-info-grid">
            <div class="gs-card">
                <p class="gs-label" style="margin-bottom:.75rem">اطلاعات تماس</p>
                <div class="gs-info-row">
                    <span class="gs-info-icon">📧</span>
                    <span>{{ customer.email ?? '—' }}</span>
                </div>
                <div class="gs-info-row">
                    <span class="gs-info-icon">📞</span>
                    <span>{{ customer.phone ?? '—' }}</span>
                </div>
                <div class="gs-info-row" v-if="customer.address">
                    <span class="gs-info-icon">📍</span>
                    <span>{{ customer.address }}</span>
                </div>
                <div v-if="customer.notes" style="margin-top:.75rem">
                    <p class="gs-label" style="margin-bottom:.3rem">یادداشت</p>
                    <p style="font-size:.875rem;color:var(--gs-text-secondary)">{{ customer.notes }}</p>
                </div>
            </div>

            <div class="gs-card">
                <p class="gs-label" style="margin-bottom:.75rem">آمار</p>
                <div class="gs-stat-row">
                    <span class="gs-label">درخواست‌ها</span>
                    <span class="gs-badge gs-badge-info">{{ customer.requests?.length ?? 0 }}</span>
                </div>
                <div class="gs-stat-row">
                    <span class="gs-label">فاکتورها</span>
                    <span class="gs-badge gs-badge-gold">{{ customer.invoices?.length ?? 0 }}</span>
                </div>
                <div class="gs-stat-row">
                    <span class="gs-label">مجموع پرداخت</span>
                    <span class="gs-gold-text" style="font-weight:700;font-size:.875rem">
                        {{ totalPaid }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick actions for this customer -->
        <div style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
            <Link :href="route('requests.create') + '?customer_id=' + customer.id"
                class="gs-btn gs-btn-secondary gs-btn-sm">
                + درخواست جدید
            </Link>
            <Link :href="route('invoices.create') + '?customer_id=' + customer.id"
                class="gs-btn gs-btn-secondary gs-btn-sm">
                + فاکتور جدید
            </Link>
            <Link :href="route('service-jobs.create') + '?customer_id=' + customer.id"
                class="gs-btn gs-btn-secondary gs-btn-sm">
                + سرویس جدید
            </Link>
        </div>

        <!-- Tabs -->
        <div class="gs-tabs">
            <button v-for="tab in tabs" :key="tab.key"
                :class="['gs-tab', { 'active': activeTab === tab.key }]"
                @click="activeTab = tab.key">
                {{ tab.label }}
                <span class="gs-badge gs-badge-gold" style="margin-right:.4rem;font-size:.7rem">
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Requests Tab -->
        <div v-if="activeTab === 'requests'" class="gs-card" style="padding:0;overflow:hidden">
            <table class="gs-table" v-if="customer.requests?.length">
                <thead>
                    <tr>
                        <th>توضیحات</th>
                        <th>دسته‌بندی‌ها</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="req in customer.requests" :key="req.id">
                        <td style="max-width:240px">
                            <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                {{ req.description }}
                            </span>
                        </td>
                        <td>
                            <span v-for="cat in req.categories" :key="cat.id"
                                class="gs-badge gs-badge-gold" style="margin-left:.3rem;font-size:.7rem">
                                {{ cat.name }}
                            </span>
                        </td>
                        <td>
                            <span :class="['gs-badge', statusBadge(req.status)]">
                                {{ statusLabel(req.status) }}
                            </span>
                        </td>
                        <td>
                            <Link :href="route('requests.show', req.id)" class="gs-btn gs-btn-ghost gs-btn-sm">
                                مشاهده
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p class="gs-subtitle">درخواستی ثبت نشده</p>
            </div>
        </div>

        <!-- Invoices Tab -->
        <div v-if="activeTab === 'invoices'" class="gs-card" style="padding:0;overflow:hidden">
            <table class="gs-table" v-if="customer.invoices?.length">
                <thead>
                    <tr>
                        <th>شماره فاکتور</th>
                        <th>مبلغ کل</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="inv in customer.invoices" :key="inv.id">
                        <td class="gs-gold-text" style="font-family:monospace">{{ inv.invoice_number }}</td>
                        <td>{{ formatPrice(inv.total_amount) }}</td>
                        <td>
                            <span :class="['gs-badge', invoiceBadge(inv.is_confirmed)]">
                                {{ invoiceLabel(inv.is_confirmed) }}
                            </span>
                        </td>
                        <td>
                            <Link :href="route('invoices.show', inv.id)" class="gs-btn gs-btn-ghost gs-btn-sm">
                                مشاهده
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p class="gs-subtitle">فاکتوری ثبت نشده</p>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    customer: Object,
})

const activeTab = ref('requests')

const tabs = computed(() => [
    { key: 'requests', label: 'درخواست‌ها', count: props.customer.requests?.length ?? 0 },
    { key: 'invoices', label: 'فاکتورها', count: props.customer.invoices?.length ?? 0 },
])

const totalPaid = computed(() => {
    const confirmed = (props.customer.invoices ?? []).filter(i => i.is_confirmed === 'confirmed')
    const total = confirmed.reduce((s, i) => s + Number(i.total_amount ?? 0), 0)
    return total > 0 ? Number(total).toLocaleString('fa-IR') + ' تومان' : '—'
})

function statusLabel(status) {
    return { pending: 'در انتظار', in_progress: 'در جریان', completed: 'تکمیل', canceled: 'لغو' }[status] ?? status
}
function statusBadge(status) {
    return { pending: 'gs-badge-warning', in_progress: 'gs-badge-info', completed: 'gs-badge-success', canceled: 'gs-badge-error' }[status] ?? 'gs-badge-gold'
}
function invoiceLabel(v) {
    return v === 'confirmed' ? 'تأیید شده' : v === 'not_confirmed' ? 'رد شده' : 'در انتظار'
}
function invoiceBadge(v) {
    return v === 'confirmed' ? 'gs-badge-success' : v === 'not_confirmed' ? 'gs-badge-error' : 'gs-badge-warning'
}
function formatPrice(amount) {
    if (!amount) return '—'
    return Number(amount).toLocaleString('fa-IR') + ' تومان'
}
</script>

<style scoped>
.gs-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 600px) {
    .gs-info-grid {
        grid-template-columns: 1fr;
    }
}

.gs-info-row {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: .875rem;
    color: var(--gs-text-secondary);
    margin-bottom: .5rem;
}

.gs-info-icon {
    font-size: 1rem;
    flex-shrink: 0;
}

.gs-stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .5rem 0;
    border-bottom: 1px solid var(--gs-border);
}

.gs-stat-row:last-child {
    border-bottom: none;
}

.gs-tabs {
    display: flex;
    gap: .25rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--gs-border);
    padding-bottom: 0;
}

.gs-tab {
    display: flex;
    align-items: center;
    padding: .6rem 1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-family: 'IRANYekan', Tahoma, Arial, sans-serif;
    font-size: .875rem;
    color: var(--gs-text-secondary);
    transition: all var(--gs-transition);
    margin-bottom: -1px;
}

.gs-tab:hover {
    color: var(--gs-text-primary);
}

.gs-tab.active {
    color: var(--gs-gold);
    border-bottom-color: var(--gs-gold);
}

.gs-empty {
    padding: 2.5rem;
    text-align: center;
}
</style>
