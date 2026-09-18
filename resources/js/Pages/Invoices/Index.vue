<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="violet" />

            <!-- ================= هیرو ================= -->
            <LuxHero
                chip="ماژول فروش"
                chip-two="Invoices"
                title="فروش و «فاکتورها»"
                lead="مدیریت چرخهٔ فروش — صدور فاکتور، افزودن اقلام، پرداخت و مرجوعی."
                cube="🧾"
                satellite="💰"
                :stats="[
                    { label: 'کل فاکتورها', value: faInt(invoices.total ?? invoices.data.length) },
                    { label: 'در این صفحه', value: faInt(invoices.data.length) },
                    { label: 'پرداخت‌شدهٔ صفحه', value: faInt(paidOnPage) },
                ]"
            >
                <template #chip-icon><ReceiptText :size="13" /></template>
                <template #actions>
                    <Link :href="route('invoices.create')" class="a3d-btn a3d-btn--gold">
                        <Plus :size="15" />
                        فاکتور جدید
                    </Link>
                </template>
            </LuxHero>

            <!-- ================= جستجو و فیلتر ================= -->
            <div class="gx-toolbar">
                <div class="gx-search">
                    <Search :size="15" class="gx-search__icon" />
                    <input v-model="search" type="search" placeholder="جستجو شماره فاکتور..." />
                </div>
                <div class="gx-seg">
                    <button
                        v-for="opt in STATUS_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="gx-seg__btn"
                        :class="{ 'is-active': status === opt.value }"
                        @click="status = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- ================= جدول ================= -->
            <div class="gx-panel" :style="{ '--gx-i': 1 }">
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
                            <td style="font-family:monospace;direction:ltr;text-align:end;color:var(--gs-gold);font-weight:700">
                                {{ invoice.invoice_number }}
                            </td>
                            <td>{{ invoice.customer?.name ?? '—' }}</td>
                            <td>{{ faInt(invoice.order_items?.length ?? invoice.orderItems?.length ?? 0) }}</td>
                            <td class="gx-price" style="font-size:.85rem">{{ formatPrice(invoice.total_amount) }}</td>
                            <td>
                                <span class="gx-status" :class="invStatusClass(invoice.payment_status)">
                                    <i />
                                    {{ invLabel(invoice.payment_status) }}
                                </span>
                            </td>
                            <td style="text-align:end">
                                <Link :href="route('invoices.show', invoice.id)" class="a3d-btn a3d-btn--sm">
                                    <Eye :size="13" />
                                    مشاهده
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="gx-empty">
                    <span class="gx-empty__icon">🧾</span>
                    <p class="gx-empty__title">فاکتوری ثبت نشده</p>
                    <p class="gx-empty__desc">اولین فاکتور فروش را بساز و اقلام را به آن اضافه کن.</p>
                    <Link :href="route('invoices.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm" style="margin-top:.5rem">
                        <Plus :size="14" />
                        اولین فاکتور را بسازید
                    </Link>
                </div>
            </div>

            <!-- ================= پجینیشن ================= -->
            <div class="gx-pagination" v-if="invoices.last_page > 1">
                <Link v-if="invoices.prev_page_url" :href="invoices.prev_page_url" class="a3d-btn a3d-btn--sm">
                    <ChevronRight :size="14" /> قبلی
                </Link>
                <span class="gx-pagination__num">
                    صفحهٔ <b>{{ faInt(invoices.current_page) }}</b> از {{ faInt(invoices.last_page) }}
                </span>
                <Link v-if="invoices.next_page_url" :href="invoices.next_page_url" class="a3d-btn a3d-btn--sm">
                    بعدی <ChevronLeft :size="14" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Plus,
    ReceiptText,
    Search,
} from 'lucide-vue-next'

const props = defineProps({
    invoices: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

const STATUS_OPTIONS = [
    { value: '', label: 'همه' },
    { value: 'unpaid', label: 'پرداخت نشده' },
    { value: 'paid', label: 'پرداخت شده' },
    { value: 'returned', label: 'مرجوع شده' },
]

const paidOnPage = computed(() =>
    props.invoices.data.filter(i => i.payment_status === 'paid').length
)

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
function invStatusClass(v) {
    if (v === 'paid') return 'gx-status--green'
    if (v === 'returned') return 'gx-status--red'
    return 'gx-status--amber'
}
function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}
const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')
</script>
