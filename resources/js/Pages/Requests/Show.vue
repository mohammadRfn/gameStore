<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">درخواست #{{ request.id }}</h1>
                    <p class="gs-subtitle">{{ request.customer_name }}</p>
                </div>
                <div style="display:flex;gap:.75rem">
                    <Link :href="route('requests.edit', request.id)" class="gs-btn gs-btn-secondary">ویرایش</Link>
                    <Link :href="route('requests.index')" class="gs-btn gs-btn-ghost">← بازگشت</Link>
                </div>
            </div>
        </template>

        <div class="gs-detail-grid">
            <!-- Main Info -->
            <div class="gs-card gs-card-elevated">
                <p class="gs-label" style="margin-bottom:1rem">اطلاعات درخواست</p>

                <div class="gs-detail-row">
                    <span class="gs-label">وضعیت</span>
                    <span :class="['gs-badge', statusBadge(request.status)]">{{ statusLabel(request.status) }}</span>
                </div>
                <div class="gs-detail-row">
                    <span class="gs-label">مشتری</span>
                    <Link v-if="request.customer" :href="route('customers.show', request.customer.id)"
                        class="gs-gold-text" style="font-size:.875rem;text-decoration:none">
                        {{ request.customer_name }}
                    </Link>
                    <span v-else style="font-size:.875rem;color:var(--gs-text-secondary)">{{ request.customer_name }}</span>
                </div>
                <div class="gs-detail-row">
                    <span class="gs-label">دسته‌بندی‌ها</span>
                    <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <span v-for="cat in request.categories" :key="cat.id" class="gs-badge gs-badge-gold gs-badge-sm">
                            {{ cat.name }}
                        </span>
                        <span v-if="!request.categories?.length" class="gs-muted">—</span>
                    </div>
                </div>
                <div class="gs-divider"></div>
                <p class="gs-label" style="margin-bottom:.5rem">توضیحات</p>
                <p style="font-size:.875rem;color:var(--gs-text-secondary);line-height:1.8">{{ request.description }}</p>
            </div>

            <!-- Related Invoice -->
            <div class="gs-card">
                <p class="gs-label" style="margin-bottom:1rem">فاکتور مرتبط</p>
                <div v-if="request.invoice">
                    <div class="gs-detail-row">
                        <span class="gs-label">شماره</span>
                        <span class="gs-gold-text" style="font-family:monospace">{{ request.invoice.invoice_number }}</span>
                    </div>
                    <div class="gs-detail-row">
                        <span class="gs-label">مبلغ</span>
                        <span style="font-weight:700;color:var(--gs-text-primary)">{{ formatPrice(request.invoice.total_amount) }}</span>
                    </div>
                    <div class="gs-detail-row">
                        <span class="gs-label"> وضعیت کلی </span>
                        <span :class="['gs-badge', invBadge(request.invoice.payment_status)]">
                            {{ invLabel(request.invoice.payment_status) }}
                        </span>
                    </div>
                    <Link :href="route('invoices.show', request.invoice.id)"
                        class="gs-btn gs-btn-secondary gs-btn-sm" style="margin-top:1rem">
                        مشاهده فاکتور
                    </Link>
                </div>
                <div v-else>
                    <p class="gs-muted" style="margin-bottom:1rem">فاکتوری صادر نشده</p>
                    <Link :href="route('invoices.create') + '?request_id=' + request.id"
                        class="gs-btn gs-btn-primary gs-btn-sm">
                        + ایجاد فاکتور
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({ request: Object })

const statusLabel = s => ({ pending: 'در انتظار', in_progress: 'در جریان', completed: 'تکمیل', canceled: 'لغو' }[s] ?? s)
const statusBadge = s => ({ pending: 'gs-badge-warning', in_progress: 'gs-badge-info', completed: 'gs-badge-success', canceled: 'gs-badge-error' }[s] ?? 'gs-badge-gold')
const invLabel = v => v === 'paid' ? 'پرداخت شده' : 'پرداخت نشده'
const invBadge = v => v === 'paid' ? 'gs-badge-success' : 'gs-badge-warning'
const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-detail-grid { display:grid;grid-template-columns:1fr 340px;gap:1.25rem;align-items:start }
.gs-detail-row { display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--gs-border) }
.gs-detail-row:last-of-type { border-bottom:none }
.gs-muted { color:var(--gs-text-muted);font-size:.875rem }
@media(max-width:768px) { .gs-detail-grid { grid-template-columns:1fr } }
</style>
