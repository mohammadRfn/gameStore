<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">فاکتورها</h1>
                    <p class="gs-subtitle">{{ invoices.total ?? invoices.data.length }} فاکتور ثبت شده</p>
                </div>
                <Link :href="route('invoices.create')" class="gs-btn gs-btn-primary">+ فاکتور جدید</Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="gs-card gs-filters-row">
            <input v-model="search" type="search" class="gs-input" placeholder="جستجو شماره فاکتور..." style="flex:1" />
            <select v-model="status" class="gs-input" style="max-width:200px">
                <option value="">همه وضعیت‌ها</option>
                <option value="unpaid">پرداخت نشده</option>
                <option value="paid">پرداخت شده</option>
                <option value="returned">مرجوع شده</option>
            </select>
        </div>

        <!-- Table -->
        <div class="gs-card" style="padding:0;overflow:hidden;margin-top:1.25rem">
            <table class="gs-table" v-if="invoices.data.length">
                <thead>
                    <tr>
                        <th>شماره فاکتور</th>
                        <th>مشتری</th>
                        <th>تعداد اقلام</th>
                        <th>مبلغ کل</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="invoice in invoices.data" :key="invoice.id">
                        <td style="font-family:monospace">{{ invoice.invoice_number }}</td>
                        <td>{{ invoice.customer?.name ?? '—' }}</td>
                        <td>{{ invoice.order_items?.length ?? invoice.orderItems?.length ?? 0 }}</td>
                        <td class="gs-gold-text" style="font-weight:700">{{ formatPrice(invoice.total_amount) }}</td>
                        <td><span class="gs-badge" :class="invBadge(invoice.payment_status)">{{
                            invLabel(invoice.payment_status)
                        }}</span></td>
                        <td>
                            <Link :href="route('invoices.show', invoice.id)" class="gs-btn gs-btn-secondary gs-btn-sm">
                                مشاهده
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p style="font-size:2rem">🧾</p>
                <p class="gs-subtitle">فاکتوری ثبت نشده</p>
                <Link :href="route('invoices.create')" class="gs-btn gs-btn-primary gs-btn-sm"
                    style="margin-top:.75rem">
                    اولین فاکتور را بسازید
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div class="gs-pagination" v-if="invoices.last_page > 1">
            <Link v-if="invoices.prev_page_url" :href="invoices.prev_page_url"
                class="gs-btn gs-btn-secondary gs-btn-sm">قبلی
            </Link>
            <span class="gs-label">{{ invoices.current_page }} / {{ invoices.last_page }}</span>
            <Link v-if="invoices.next_page_url" :href="invoices.next_page_url"
                class="gs-btn gs-btn-secondary gs-btn-sm">بعدی
            </Link>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    invoices: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

let debounceTimer = null
watch([search, status], () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('invoices.index'), { search: search.value, status: status.value }, {
            preserveState: true,
            replace: true,
        })
    }, 350)
})

function invLabel(v) {
    if (v === 'paid') return 'پرداخت شده'
    if (v === 'returned') return 'مرجوع شده'
    return 'پرداخت نشده'
}
function invBadge(v) {
    if (v === 'paid') return 'gs-badge-success'
    if (v === 'returned') return 'gs-badge-error'
    return 'gs-badge-warning'
}
function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}
</script>

<style scoped>
.gs-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between
}

.gs-filters-row {
    display: flex;
    gap: .75rem
}

.gs-empty {
    padding: 3rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem
}

.gs-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.25rem
}
</style>