<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">فاکتور {{ invoice.invoice_number }}</h1>
                    <p class="gs-subtitle">{{ invoice.customer?.name ?? 'بدون مشتری' }}</p>
                </div>
                <div style="display:flex;gap:.75rem">
                    <Link v-if="!isLocked" :href="route('invoices.edit', invoice.id)" class="gs-btn gs-btn-secondary">
                        ویرایش</Link>
                    <a v-if="isLocked" :href="route('invoices.pdf', invoice.id)" target="_blank"
                        class="gs-btn gs-btn-secondary">
                        📄 دریافت PDF
                    </a>
                    <span v-if="invoice.is_returned" class="gs-badge gs-badge-error" style="align-self:center">
                        مرجوع شده
                        <button v-if="!hasRestockedItems" @click="unmarkReturned" class="gs-btn gs-btn-ghost gs-btn-sm"
                            style="margin-right:.4rem" :disabled="unreturning">
                            {{ unreturning ? '...' : 'لغو' }}
                        </button>
                    </span>
                    <button @click="confirmDelete" class="gs-btn gs-btn-danger">حذف</button>
                    <Link :href="route('invoices.index')" class="gs-btn gs-btn-ghost">← بازگشت</Link>
                </div>
            </div>
        </template>



        <!-- Payment Status bar -->
        <div class="gs-status-bar gs-card" :class="'payment-' + (invoice.payment_status ?? 'unpaid')"
            style="margin-top:.75rem">
            <span style="font-size:1.25rem">{{ invoice.payment_status === 'paid' ? '💰' : (invoice.payment_status ===
                'returned'
                ? '↩️' : '⏳') }}</span>
            <div>
                <p style="font-weight:700;color:var(--gs-text-primary)">
                    {{ invoice.payment_status === 'paid' ? 'پرداخت شده' : (invoice.payment_status === 'returned' ?
                        'مرجوع شده' :
                        'پرداخت نشده') }}
                </p>
                <p class="gs-muted" v-if="invoice.payment_status === 'paid'">
                    روش پرداخت: {{ paymentMethodLabel(invoice.payment_method) }}
                    <span v-if="invoice.stock_deducted"> — از انبار کسر شد</span>
                </p>
                <p class="gs-muted" v-else-if="invoice.payment_status === 'returned'">
                    این فاکتور مرجوع شده و دیگر قابل پرداخت مجدد نیست
                </p>
                <p class="gs-muted" v-else>هنوز پرداختی برای این فاکتور ثبت نشده</p>
            </div>

            <!-- Not paid yet: show payment form -->
            <div v-if="invoice.payment_status === 'unpaid'"
                style="margin-right:auto;display:flex;align-items:center;gap:.5rem">
                <select v-model="paymentForm.payment_method" class="gs-input" style="max-width:170px">
                    <option value="cash">نقدی</option>
                    <option value="card_to_card">کارت به کارت</option>
                    <option value="pos_terminal">دستگاه خودپرداز</option>
                </select>
                <select v-if="paymentForm.payment_method === 'pos_terminal'" v-model="paymentForm.payment_terminal_mode"
                    class="gs-input" style="max-width:150px">
                    <option value="manual">ثبت دستی</option>
                    <option value="automatic" disabled>خودکار (به‌زودی)</option>
                </select>
                <button @click="markAsPaid" class="gs-btn gs-btn-primary" :disabled="markingPaid">
                    {{ markingPaid ? '...' : '💰 ثبت پرداخت' }}
                </button>
            </div>

            <!-- Already paid: offer return instead of un-paying -->
            <div v-else-if="invoice.payment_status === 'paid' && !invoice.is_returned" style="margin-right:auto">
                <button @click="markReturned" class="gs-btn gs-btn-ghost gs-btn-sm" :disabled="returning">
                    {{ returning ? '...' : 'مرجوع کردن فاکتور' }}
                </button>
            </div>
        </div>

        <div class="gs-detail-grid" style="margin-top:1.25rem">
            <!-- Order Items -->
            <div class="gs-card" style="padding:0;overflow:hidden">
                <!-- Attached Service Jobs -->
                <div v-if="invoice.service_jobs?.length"
                    style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border)">
                    <p class="gs-label" style="font-weight:700;margin-bottom:.5rem">سرویس‌های ضمیمه‌شده</p>
                    <div v-for="sj in invoice.service_jobs" :key="sj.id" class="gs-detail-row">
                        <div>
                            <Link :href="route('service-jobs.show', sj.id)" class="gs-gold-text"
                                style="text-decoration:none;font-weight:600">
                                #{{ sj.id }} — {{ sj.device_type ?? 'بدون نوع دستگاه' }}
                            </Link>
                            <div v-if="sj.service_types?.length"
                                style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.3rem">
                                <span v-for="st in sj.service_types" :key="st.id"
                                    class="gs-badge gs-badge-gold gs-badge-sm">
                                    {{ st.service_type?.name ?? '—' }}
                                </span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.75rem">
                            <span class="gs-gold-text" style="font-weight:700">{{ formatPrice(sj.final_price) }}</span>
                            <button v-if="!isLocked" @click="removeServiceJob(sj.id)"
                                class="gs-btn gs-btn-ghost gs-btn-sm" :disabled="removingServiceJobId === sj.id">
                                {{ removingServiceJobId === sj.id ? '...' : '✕' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border);display:flex;align-items:center;justify-content:space-between">
                    <span class="gs-subtitle">اقلام فاکتور</span>
                    <Link v-if="!isLocked" :href="route('order-items.create') + '?invoice_id=' + invoice.id"
                        class="gs-btn gs-btn-secondary gs-btn-sm">+ افزودن قلم/سرویس</Link>
                </div>
                <table class="gs-table" v-if="invoice.order_items?.length">
                    <thead>
                        <tr>
                            <th>نام محصول</th>
                            <th>تعداد</th>
                            <th>قیمت واحد</th>
                            <th>جمع</th>
                            <th>کسر از انبار</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in invoice.order_items" :key="item.id">
                            <td>{{ item.product_name }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>{{ formatPrice(item.price) }}</td>
                            <td class="gs-gold-text" style="font-weight:700">{{ formatPrice(item.total_price) }}</td>
                            <td>
                                <span class="gs-badge"
                                    :class="item.deduct_from_stock ? 'gs-badge-info' : 'gs-badge-gold'">
                                    {{ item.deduct_from_stock ? 'بله' : 'خیر' }}
                                </span>
                            </td>
                            <td>
                                <button v-if="!isLocked" @click="removeOrderItem(item.id)"
                                    class="gs-btn gs-btn-ghost gs-btn-sm" :disabled="removingOrderItemId === item.id">
                                    {{ removingOrderItemId === item.id ? '...' : '✕' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="gs-empty-sm">
                    <p class="gs-muted">قلمی ثبت نشده</p>
                </div>
                <!-- Adjustments -->
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--gs-border)">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                        <span class="gs-label" style="font-weight:700">تعدیلات فاکتور</span>
                    </div>

                    <div v-for="adj in invoice.adjustments" :key="adj.id" class="gs-detail-row">
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <span class="gs-badge"
                                :class="adj.direction === 'increase' ? 'gs-badge-info' : 'gs-badge-gold'">
                                {{ adj.direction === 'increase' ? '+' : '−' }}
                            </span>
                            <span>{{ adj.title }}</span>
                            <span class="gs-muted">({{ adj.type === 'percentage' ? adj.value + '%' :
                                formatPrice(adj.value)
                                }})</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:.75rem">
                            <span :class="adj.direction === 'increase' ? 'gs-gold-text' : ''"
                                :style="adj.direction === 'decrease' ? 'color:var(--gs-error)' : ''">
                                {{ adj.direction === 'increase' ? '+' : '−' }}{{
                                    formatPrice(resolveAdjustmentAmount(adj)) }}
                            </span>
                            <button v-if="!isLocked" @click="removeAdjustment(adj.id)"
                                class="gs-btn gs-btn-ghost gs-btn-sm"
                                :disabled="removingAdjustmentId === adj.id">✕</button>
                        </div>
                    </div>

                    <!-- Add adjustment form -->
                    <div v-if="!isLocked" style="display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap">
                        <input v-model="adjustmentForm.title" placeholder="عنوان (مثلا تخفیف)" class="gs-input"
                            style="flex:1;min-width:140px" />
                        <select v-model="adjustmentForm.type" class="gs-input" style="max-width:110px">
                            <option value="percentage">درصدی</option>
                            <option value="fixed">مبلغ ثابت</option>
                        </select>
                        <select v-model="adjustmentForm.direction" class="gs-input" style="max-width:100px">
                            <option value="increase">افزایش</option>
                            <option value="decrease">کاهش</option>
                        </select>
                        <input v-model.number="adjustmentForm.value" type="number" min="0" step="0.01"
                            :placeholder="adjustmentForm.type === 'percentage' ? 'درصد' : 'مبلغ'" class="gs-input"
                            style="max-width:120px" />
                        <button @click="submitAdjustment" class="gs-btn gs-btn-primary gs-btn-sm"
                            :disabled="adjustmentForm.processing">
                            + افزودن
                        </button>
                    </div>
                </div>

                <!-- Total -->
                <div class="gs-total-row" style="flex-direction:column;align-items:stretch;gap:.4rem">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <span class="gs-label">جمع اقلام</span>
                        <span>{{ formatPrice(invoice.total_amount) }}</span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <span class="gs-label" style="font-weight:700">قیمت نهایی فاکتور</span>
                        <span class="gs-total-amount">{{ formatPrice(invoice.final_amount) }}</span>
                    </div>
                </div>
            </div>

            <InvoiceRestockPanel :invoice="invoice" style="margin-top:1.25rem" />

            <!-- Sidebar -->
            <div style="display:flex;flex-direction:column;gap:1rem">
                <div class="gs-card">
                    <p class="gs-label" style="margin-bottom:.75rem">اطلاعات فاکتور</p>
                    <div class="gs-detail-row">
                        <span class="gs-label">شماره</span>
                        <span class="gs-gold-text" style="font-family:monospace">{{ invoice.invoice_number }}</span>
                    </div>
                    <div class="gs-detail-row" v-if="invoice.customer">
                        <span class="gs-label">مشتری</span>
                        <Link :href="route('customers.show', invoice.customer.id)" class="gs-gold-text"
                            style="font-size:.875rem;text-decoration:none">
                            {{ invoice.customer.name }}
                        </Link>
                    </div>
                    <div class="gs-detail-row" v-if="invoice.request">
                        <span class="gs-label">درخواست</span>
                        <Link :href="route('requests.show', invoice.request_id)" class="gs-btn gs-btn-ghost gs-btn-sm"
                            style="padding:.2rem .6rem">
                            #{{ invoice.request_id }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete confirm modal -->
        <Transition name="gs-fade">
            <div v-if="showDeleteModal" class="gs-modal-overlay" @click.self="showDeleteModal = false">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:.5rem">حذف فاکتور</h3>
                    <p class="gs-label" style="margin-bottom:1.25rem">
                        فاکتور «{{ invoice.invoice_number }}» حذف شود؟ در صورتی که موجودی انبار قبلاً کسر شده باشد،
                        بازگردانده
                        می‌شود.
                    </p>
                    <div style="display:flex;gap:.75rem;justify-content:flex-end">
                        <button @click="showDeleteModal = false" class="gs-btn gs-btn-ghost">انصراف</button>
                        <button @click="doDelete" class="gs-btn gs-btn-danger" :disabled="deleting">
                            {{ deleting ? 'در حال حذف...' : 'حذف' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
const removingAdjustmentId = ref(null)
import InvoiceRestockPanel from '@/Components/InvoiceRestockPanel.vue'
const adjustmentForm = reactive({
    title: '',
    type: 'percentage',
    direction: 'increase',
    value: null,
    processing: false,
})

function resolveAdjustmentAmount(adj) {
    const base = Number(props.invoice.total_amount) || 0
    return adj.type === 'percentage' ? base * (adj.value / 100) : Number(adj.value)
}

function submitAdjustment() {
    if (!adjustmentForm.title || !adjustmentForm.value) return
    adjustmentForm.processing = true
    router.post(route('invoice-adjustments.store', props.invoice.id), {
        title: adjustmentForm.title,
        type: adjustmentForm.type,
        direction: adjustmentForm.direction,
        value: adjustmentForm.value,
    }, {
        onSuccess: () => {
            adjustmentForm.title = ''
            adjustmentForm.value = null
        },
        onFinish: () => adjustmentForm.processing = false
    })
}
const returning = ref(false)
function markReturned() {
    returning.value = true
    router.post(route('invoices.return', props.invoice.id), {}, {
        onFinish: () => returning.value = false,
    })
}
function removeAdjustment(adjustmentId) {
    removingAdjustmentId.value = adjustmentId
    router.delete(route('invoice-adjustments.destroy', [props.invoice.id, adjustmentId]), {
        onFinish: () => removingAdjustmentId.value = null
    })
}

const props = defineProps({ invoice: Object })
const isLocked = computed(() => props.invoice.payment_status !== 'unpaid')
const hasRestockedItems = computed(() => props.invoice.order_items?.some(i => i.restocked_at))



// --- پرداخت ---
const markingPaid = ref(false)


const paymentForm = reactive({
    payment_method: 'cash',
    payment_terminal_mode: 'manual',
})
const removingOrderItemId = ref(null)

function removeOrderItem(orderItemId) {
    removingOrderItemId.value = orderItemId
    router.delete(route('order-items.destroy', orderItemId), {
        onFinish: () => removingOrderItemId.value = null
    })
}

const removingServiceJobId = ref(null)

function removeServiceJob(serviceJobId) {
    removingServiceJobId.value = serviceJobId
    router.delete(route('invoices.service-jobs.detach', [props.invoice.id, serviceJobId]), {
        onFinish: () => removingServiceJobId.value = null
    })
}
function markAsPaid() {
    markingPaid.value = true
    router.post(route('invoices.mark-paid', props.invoice.id), {
        payment_method: paymentForm.payment_method,
        payment_terminal_mode: paymentForm.payment_method === 'pos_terminal' ? paymentForm.payment_terminal_mode : null,
    }, {
        onFinish: () => markingPaid.value = false
    })
}


const unreturning = ref(false)
function unmarkReturned() {
    unreturning.value = true
    router.post(route('invoices.unreturn', props.invoice.id), {}, {
        onFinish: () => unreturning.value = false,
    })
}
function paymentMethodLabel(method) {
    return { cash: 'نقدی', card_to_card: 'کارت به کارت', pos_terminal: 'دستگاه خودپرداز' }[method] ?? '—'
}
// --- حذف فاکتور ---
const showDeleteModal = ref(false)
const deleting = ref(false)

function confirmDelete() {
    showDeleteModal.value = true
}
function doDelete() {
    deleting.value = true
    router.delete(route('invoices.destroy', props.invoice.id), {
        onFinish: () => {
            deleting.value = false
            showDeleteModal.value = false
        }
    })
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

.gs-status-bar {
    display: flex;
    align-items: center;
    gap: 1rem
}

.status-confirmed {
    border-color: rgba(76, 175, 125, .4)
}

.status-rejected {
    border-color: rgba(224, 92, 92, .4)
}

.status-pending {
    border-color: var(--gs-gold)
}

.payment-paid {
    border-color: rgba(76, 175, 125, .4)
}

.payment-unpaid {
    border-color: rgba(224, 92, 92, .3)
}

.payment-returned {
    border-color: rgba(150, 150, 160, .4)
}

.gs-detail-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 1.25rem;
    align-items: start
}

.gs-detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .55rem 0;
    border-bottom: 1px solid var(--gs-border)
}

.gs-detail-row:last-child {
    border-bottom: none
}

.gs-muted {
    color: var(--gs-text-muted);
    font-size: .875rem
}

.gs-empty-sm {
    padding: 1.5rem;
    text-align: center
}

.gs-total-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    background: var(--gs-bg-elevated);
    border-top: 1px solid var(--gs-border)
}

.gs-total-amount {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--gs-gold)
}

.gs-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .65);
    backdrop-filter: blur(3px);
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem
}

.gs-modal {
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border-strong);
    border-radius: 16px;
    padding: 1.75rem;
    max-width: 420px;
    width: 100%
}

@media(max-width:768px) {
    .gs-detail-grid {
        grid-template-columns: 1fr
    }
}
</style>