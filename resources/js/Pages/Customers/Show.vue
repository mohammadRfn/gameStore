<script setup>
/**
 * پروندهٔ مشتری
 * مسیر: resources/js/Pages/Customers/Show.vue
 * ---------------------------------------------------------------------------
 * CustomerController@show → props.customer:
 *   { id, name, phone, email, address, notes?, created_at,
 *     requests[{ id, description, status, categories[] }], invoices[{ id, invoice_number, total_amount, is_confirmed }] }
 * روت‌ها: customers.edit / customers.destroy / customers.index / requests.show / requests.create / invoices.show
 */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ClipboardList,
    Clock,
    Coins,
    FileText,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Receipt,
    ShieldAlert,
    Trash2,
    UserRound,
    Wrench,
    XCircle,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { useToasts } from '@/Composables/useToasts'
import { faInt } from '@/Utils/format'
import { avatarHue, clip, dateFa, initials, isConfirmed, money, sum } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CrmPanel from '@/Components/Crm/CrmPanel.vue'
import CrmSegmented from '@/Components/Crm/CrmSegmented.vue'
import CrmStatusChip from '@/Components/Crm/CrmStatusChip.vue'
import CrmEmptyState from '@/Components/Crm/CrmEmptyState.vue'
import CrmConfirmDialog from '@/Components/Crm/CrmConfirmDialog.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    customer: { type: Object, required: true },
})

const { toasts, dismiss } = useToasts({ flash: true })

const requests = computed(() => props.customer.requests || [])
const invoices = computed(() => props.customer.invoices || [])
const totalAmount = computed(() => sum(invoices.value, 'total_amount'))
const confirmedCount = computed(() => invoices.value.filter((i) => isConfirmed(i.is_confirmed)).length)
const openCount = computed(() => requests.value.filter((r) => ['pending', 'in_progress'].includes(r.status)).length)

/* تب‌ها */
const tab = ref('requests')
const tabs = computed(() => [
    { value: 'requests', label: 'درخواست‌ها', icon: ClipboardList, count: requests.value.length },
    { value: 'invoices', label: 'فاکتورها', icon: Receipt, count: invoices.value.length },
])

/* فیلتر وضعیت (کلاینت‌ساید — بدون رفت‌وبرگشت) */
const statusFilter = ref('')
const statusOptions = computed(() => {
    const c = (s) => requests.value.filter((r) => r.status === s).length
    return [
        { value: '', label: 'همه', count: requests.value.length },
        { value: 'pending', label: 'در انتظار', icon: Clock, tone: 'warning', count: c('pending') },
        { value: 'in_progress', label: 'در جریان', icon: Wrench, tone: 'info', count: c('in_progress') },
        { value: 'completed', label: 'تکمیل', icon: CheckCircle2, tone: 'success', count: c('completed') },
        { value: 'canceled', label: 'لغو', icon: XCircle, tone: 'error', count: c('canceled') },
    ]
})
const filteredRequests = computed(() =>
    statusFilter.value ? requests.value.filter((r) => r.status === statusFilter.value) : requests.value,
)

/* حذف */
const confirmOpen = ref(false)
const deleting = ref(false)
function doDelete() {
    deleting.value = true
    router.delete(route('customers.destroy', props.customer.id), {
        onFinish: () => {
            deleting.value = false
            confirmOpen.value = false
        },
    })
}

const newRequestHref = computed(() => `${route('requests.create')}?customer_id=${props.customer.id}`)
</script>

<template>
    <Head :title="`پروندهٔ ${customer.name}`" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="green" />

            <!-- ================= سربرگ ================= -->
            <header class="st-shell">
                <div class="st-hero">
                    <div style="display: flex; align-items: center; gap: 1.25rem; min-width: 0">
                        <span
                            v-reveal="{ delay: 0 }"
                            class="crm-avatar crm-avatar--lg crm-avatar--ring"
                            :style="{ '--hue': avatarHue(customer.name) }"
                        >
                            {{ initials(customer.name) }}
                        </span>

                        <div style="min-width: 0">
                            <div class="crm-hero__chips">
                                <span class="st-chip"><UserRound :size="12" /> پروندهٔ مشتری</span>
                                <span class="st-chip st-chip--plain">شناسه #{{ faInt(customer.id) }}</span>
                                <span v-if="customer.created_at" class="st-chip st-chip--plain">
                                    <CalendarDays :size="12" /> عضویت {{ dateFa(customer.created_at) }}
                                </span>
                            </div>

                            <h1 class="st-hero__title" style="font-size: clamp(1.9rem, 5vw, 3.1rem); margin-top: 0.5rem">
                                <span>{{ customer.name }}</span>
                                <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                    <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                                </svg>
                            </h1>

                            <div class="crm-hero__stats" style="margin-top: 0.9rem">
                                <span class="st-stat"><ClipboardList :size="15" /> درخواست‌ها <b>{{ faInt(requests.length) }}</b></span>
                                <span class="st-stat"><Wrench :size="15" /> باز <b>{{ faInt(openCount) }}</b></span>
                                <span class="st-stat"><Receipt :size="15" /> فاکتورها <b>{{ faInt(invoices.length) }}</b></span>
                            </div>
                        </div>
                    </div>

                    <div class="crm-hero__actions">
                        <Link :href="newRequestHref" class="a3d-btn a3d-btn--gold a3d-btn--sm">
                            <Plus :size="14" /> درخواست جدید
                        </Link>
                        <Link :href="route('customers.edit', customer.id)" class="a3d-btn a3d-btn--sm">
                            <Pencil :size="14" /> ویرایش
                        </Link>
                        <Link :href="route('customers.index')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
                            <ArrowRight :size="14" /> فهرست
                        </Link>
                    </div>
                </div>
            </header>

            <!-- ================= بدنه ================= -->
            <div class="st-shell crm-body">
                <div class="crm-grid-side">
                    <!-- ---------- ستون اصلی ---------- -->
                    <div class="crm-stack">
                        <!-- شاخص‌ها -->
                        <div class="crm-grid-kpi">
                            <article v-reveal="{ delay: 60 }" v-tilt="{ max: 6, lift: 10 }" class="a3d-holo crm-metric" style="--crm-accent: var(--gs-gold)">
                                <span class="crm-metric__k"><Coins :size="15" /> مجموع فاکتورها</span>
                                <span class="crm-metric__v">{{ money(totalAmount) }}</span>
                                <span class="crm-metric__s">{{ faInt(confirmedCount) }} فاکتور تأییدشده از {{ faInt(invoices.length) }}</span>
                            </article>
                            <article v-reveal="{ delay: 120 }" v-tilt="{ max: 6, lift: 10 }" class="a3d-holo crm-metric" style="--crm-accent: var(--gs-info)">
                                <span class="crm-metric__k"><ClipboardList :size="15" /> سابقهٔ درخواست‌ها</span>
                                <span class="crm-metric__v">{{ faInt(requests.length) }} <small>درخواست</small></span>
                                <span class="crm-metric__s">{{ faInt(openCount) }} مورد هنوز باز است</span>
                            </article>
                        </div>

                        <!-- تب‌ها -->
                        <CrmPanel :icon="tab === 'requests' ? ClipboardList : Receipt" :title="tab === 'requests' ? 'درخواست‌های سرویس و تعمیر' : 'فاکتورهای فروش'" :desc="tab === 'requests' ? 'همهٔ درخواست‌های ثبت‌شده برای این مشتری' : 'صورت‌حساب‌های صادرشده برای این مشتری'" :accent="tab === 'requests' ? 'var(--gs-gold)' : 'var(--gs-success)'" :delay="180" still flush>
                            <template #actions>
                                <CrmSegmented v-model="tab" :options="tabs" />
                            </template>

                            <!-- درخواست‌ها -->
                            <div v-if="tab === 'requests'">
                                <div v-if="requests.length" style="padding: 0.9rem 1.3rem 0.4rem">
                                    <CrmSegmented v-model="statusFilter" :options="statusOptions" />
                                </div>

                                <div v-if="filteredRequests.length" class="crm-rows">
                                    <Link
                                        v-for="(req, i) in filteredRequests"
                                        :key="req.id"
                                        :href="route('requests.show', req.id)"
                                        class="crm-row"
                                        :style="{ '--i': i }"
                                    >
                                        <span class="crm-code" style="min-width: 44px">#{{ req.id }}</span>
                                        <div class="crm-row__main">
                                            <p class="crm-row__title">{{ clip(req.description, 90) || 'بدون شرح' }}</p>
                                            <div class="crm-tags" style="margin-top: 0.3rem">
                                                <span v-for="cat in req.categories || []" :key="cat.id" class="crm-tag">{{ cat.name }}</span>
                                                <span v-if="!req.categories?.length" class="crm-row__sub">بدون دسته‌بندی</span>
                                            </div>
                                        </div>
                                        <div class="crm-row__meta">
                                            <CrmStatusChip :status="req.status" sm />
                                            <ChevronLeft :size="15" class="crm-muted" />
                                        </div>
                                    </Link>
                                </div>

                                <CrmEmptyState
                                    v-else
                                    :icon="ClipboardList"
                                    :title="requests.length ? 'درخواستی با این وضعیت نیست' : 'هنوز درخواستی ثبت نشده'"
                                    :desc="requests.length ? 'وضعیت دیگری را انتخاب کنید.' : 'اولین درخواست تعمیر یا سرویس این مشتری را ثبت کنید.'"
                                >
                                    <Link v-if="!requests.length" :href="newRequestHref" class="a3d-btn a3d-btn--gold a3d-btn--sm">
                                        <Plus :size="14" /> ثبت درخواست
                                    </Link>
                                </CrmEmptyState>
                            </div>

                            <!-- فاکتورها -->
                            <div v-else>
                                <div v-if="invoices.length" class="crm-rows">
                                    <Link
                                        v-for="(inv, i) in invoices"
                                        :key="inv.id"
                                        :href="route('invoices.show', inv.id)"
                                        class="crm-row"
                                        :style="{ '--i': i }"
                                    >
                                        <span class="crm-panel__icon" style="--crm-accent: var(--gs-success); width: 36px; height: 36px; border-radius: 11px">
                                            <Receipt :size="16" />
                                        </span>
                                        <div class="crm-row__main">
                                            <p class="crm-row__title"><span class="crm-code">{{ inv.invoice_number }}</span></p>
                                            <p class="crm-row__sub">{{ dateFa(inv.created_at) || 'مبلغ کل فاکتور' }}</p>
                                        </div>
                                        <div class="crm-row__meta">
                                            <span class="crm-row__num">{{ money(inv.total_amount) }}</span>
                                            <CrmStatusChip :invoice="inv" sm :icon="false" />
                                        </div>
                                    </Link>
                                </div>

                                <CrmEmptyState v-else :icon="Receipt" title="فاکتوری برای این مشتری صادر نشده" desc="فاکتورهای فروش پس از صدور در این بخش فهرست می‌شوند." />
                            </div>
                        </CrmPanel>
                    </div>

                    <!-- ---------- ستون کناری ---------- -->
                    <div class="crm-stack">
                        <CrmPanel title="اطلاعات تماس" desc="راه‌های ارتباط با مشتری" :icon="Phone" accent="var(--gs-info)" :delay="120" still>
                            <div class="crm-details">
                                <div class="crm-detail" style="--crm-accent: var(--gs-info)">
                                    <span class="crm-detail__icon"><Phone :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">شمارهٔ تماس</p>
                                        <p class="crm-detail__v" :class="{ 'is-empty': !customer.phone }">
                                            <a v-if="customer.phone" :href="`tel:${customer.phone}`" dir="ltr">{{ customer.phone }}</a>
                                            <template v-else>ثبت نشده</template>
                                        </p>
                                    </div>
                                </div>

                                <div class="crm-detail" style="--crm-accent: var(--gs-gold)">
                                    <span class="crm-detail__icon"><Mail :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">ایمیل</p>
                                        <p class="crm-detail__v" :class="{ 'is-empty': !customer.email }">
                                            <a v-if="customer.email" :href="`mailto:${customer.email}`" dir="ltr">{{ customer.email }}</a>
                                            <template v-else>ثبت نشده</template>
                                        </p>
                                    </div>
                                </div>

                                <div class="crm-detail" style="--crm-accent: var(--gs-accent-2)">
                                    <span class="crm-detail__icon"><MapPin :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">آدرس</p>
                                        <p class="crm-detail__v" :class="{ 'is-empty': !customer.address }">
                                            {{ customer.address || 'ثبت نشده' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="crm-detail" style="--crm-accent: var(--gs-accent-3)">
                                    <span class="crm-detail__icon"><CalendarDays :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">تاریخ عضویت</p>
                                        <p class="crm-detail__v" :class="{ 'is-empty': !customer.created_at }">
                                            {{ dateFa(customer.created_at) || 'نامشخص' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="customer.notes" class="crm-note" style="margin-top: 0.9rem">
                                <FileText :size="15" style="flex: none; margin-top: 0.15rem" />
                                <span>{{ customer.notes }}</span>
                            </div>
                        </CrmPanel>

                        <!-- ناحیهٔ خطر -->
                        <CrmPanel title="ناحیهٔ خطر" desc="حذف پرونده (قابل بازیابی از سطل بازیافت)" :icon="ShieldAlert" accent="var(--gs-error)" :delay="200" still>
                            <p style="font-size: 0.78rem; line-height: 1.9; color: var(--gs-text-secondary); margin-bottom: 0.9rem">
                                با حذف مشتری، پرونده به‌صورت نرم (soft delete) حذف می‌شود و درخواست‌ها و فاکتورهای مرتبط دست‌نخورده می‌مانند.
                            </p>
                            <button type="button" class="a3d-btn a3d-btn--danger a3d-btn--sm" @click="confirmOpen = true">
                                <Trash2 :size="14" /> حذف پروندهٔ مشتری
                            </button>
                        </CrmPanel>
                    </div>
                </div>
            </div>

            <CrmConfirmDialog
                v-model="confirmOpen"
                title="حذف پروندهٔ مشتری"
                message="آیا از حذف این مشتری مطمئن هستید؟"
                :highlight="customer.name"
                :loading="deleting"
                @confirm="doDelete"
            />

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
