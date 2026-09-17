<script setup>
/**
 * پروندهٔ درخواست
 * مسیر: resources/js/Pages/Requests/Show.vue
 * ---------------------------------------------------------------------------
 * RequestController@show → props.request:
 *   { id, customer_id, customer_name, description, status, created_at, updated_at,
 *     categories[], customer{ id, name, phone, email, address, ... } | null,
 *     invoice{ id, invoice_number, total_amount, payment_status, is_confirmed, created_at } | null }
 * روت‌ها: requests.edit / requests.destroy / requests.index / customers.show /
 *         invoices.show / invoices.create (با کوئری request_id برای صدور فاکتور جدید)
 */
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowRight,
    CalendarDays,
    ClipboardList,
    FileText,
    Layers,
    Mail,
    Pencil,
    Phone,
    Plus,
    Receipt,
    ShieldAlert,
    Trash2,
    UserRound,
    Wrench,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { useToasts } from '@/Composables/useToasts'
import { faInt } from '@/Utils/format'
import { avatarHue, dateFa, initials, money, statusMeta } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CrmPanel from '@/Components/Crm/CrmPanel.vue'
import CrmStatusChip from '@/Components/Crm/CrmStatusChip.vue'
import CrmStatusSteps from '@/Components/Crm/CrmStatusSteps.vue'
import CrmEmptyState from '@/Components/Crm/CrmEmptyState.vue'
import CrmConfirmDialog from '@/Components/Crm/CrmConfirmDialog.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    request: { type: Object, required: true },
})

const { toasts, dismiss } = useToasts({ flash: true })

/* ------------------------------------------------------------------ */
/* فاکتور مرتبط                                                          */
/* ------------------------------------------------------------------ */
const invoice = computed(() => props.request.invoice || null)
const createInvoiceHref = computed(() => `${route('invoices.create')}?request_id=${props.request.id}`)

const PAYMENT_META = {
    unpaid: { label: 'پرداخت‌نشده', tone: 'warning' },
    paid: { label: 'پرداخت‌شده', tone: 'success' },
    returned: { label: 'مرجوع‌شده', tone: 'error' },
}
const paymentMeta = computed(() => PAYMENT_META[invoice.value?.payment_status] || null)

/* ------------------------------------------------------------------ */
/* حذف                                                                   */
/* ------------------------------------------------------------------ */
const confirmOpen = ref(false)
const deleting = ref(false)
function doDelete() {
    deleting.value = true
    router.delete(route('requests.destroy', props.request.id), {
        onFinish: () => {
            deleting.value = false
            confirmOpen.value = false
        },
    })
}
</script>

<template>
    <Head :title="`درخواست #${request.id} — ${request.customer_name}`" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="gold" />

            <!-- ================= سربرگ ================= -->
            <header class="st-shell">
                <div class="st-hero">
                    <div style="display: flex; align-items: center; gap: 1.25rem; min-width: 0">
                        <span
                            v-reveal="{ delay: 0 }"
                            class="crm-avatar crm-avatar--lg crm-avatar--ring"
                            :style="{ '--hue': avatarHue(request.customer_name) }"
                        >
                            {{ initials(request.customer_name) }}
                        </span>

                        <div style="min-width: 0">
                            <div class="crm-hero__chips">
                                <span class="st-chip"><ClipboardList :size="12" /> پروندهٔ درخواست</span>
                                <span class="st-chip st-chip--plain">کد #{{ faInt(request.id) }}</span>
                                <span v-if="request.created_at" class="st-chip st-chip--plain">
                                    <CalendarDays :size="12" /> ثبت {{ dateFa(request.created_at) }}
                                </span>
                            </div>

                            <h1 class="st-hero__title" style="font-size: clamp(1.9rem, 5vw, 3.1rem); margin-top: 0.5rem">
                                <span>{{ request.customer_name }}</span>
                                <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                    <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                                </svg>
                            </h1>

                            <div class="crm-hero__stats" style="margin-top: 0.9rem">
                                <CrmStatusChip :status="request.status" long />
                                <span v-if="request.categories?.length" class="st-stat">
                                    <Layers :size="15" /> دسته‌بندی <b>{{ faInt(request.categories.length) }}</b>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="crm-hero__actions">
                        <Link :href="route('requests.edit', request.id)" class="a3d-btn a3d-btn--sm">
                            <Pencil :size="14" /> ویرایش
                        </Link>
                        <Link :href="route('requests.index')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
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
                            <article v-reveal="{ delay: 60 }" v-tilt="{ max: 6, lift: 10 }" class="a3d-holo crm-metric" style="--crm-accent: var(--gs-info)">
                                <span class="crm-metric__k"><Wrench :size="15" /> وضعیت فعلی</span>
                                <span class="crm-metric__v" style="font-size: 1.05rem">{{ statusMeta(request.status).long }}</span>
                                <span v-if="request.updated_at" class="crm-metric__s">آخرین به‌روزرسانی: {{ dateFa(request.updated_at) }}</span>
                            </article>
                            <article v-reveal="{ delay: 120 }" v-tilt="{ max: 6, lift: 10 }" class="a3d-holo crm-metric" style="--crm-accent: var(--gs-success)">
                                <span class="crm-metric__k"><Receipt :size="15" /> فاکتور مرتبط</span>
                                <span class="crm-metric__v">{{ invoice ? money(invoice.total_amount) : '—' }}</span>
                                <span class="crm-metric__s">
                                    {{ invoice ? invoice.invoice_number : 'هنوز فاکتوری صادر نشده' }}
                                </span>
                            </article>
                        </div>

                        <!-- شرح درخواست -->
                        <CrmPanel title="شرح کامل درخواست" desc="متن ثبت‌شده توسط مشتری یا ثبت‌کننده" :icon="ClipboardList" accent="var(--gs-gold)" :delay="160" still>
                            <p style="font-size: 0.85rem; line-height: 1.9; color: var(--gs-text-primary); white-space: pre-line; background: rgba(0,0,0,0.18); padding: 1rem; border-radius: 0.9rem; border: 1px solid var(--gs-border)">
                                {{ request.description || 'بدون شرح' }}
                            </p>

                            <div style="margin-top: 0.9rem; padding-top: 0.9rem; border-top: 1px solid var(--gs-border); display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap">
                                <span style="font-size: 0.75rem; color: var(--gs-text-secondary)">دسته‌بندی‌های تخصصی:</span>
                                <div class="crm-tags">
                                    <span v-for="cat in request.categories || []" :key="cat.id" class="crm-tag"><Layers :size="11" /> {{ cat.name }}</span>
                                    <span v-if="!request.categories?.length" class="crm-row__sub">بدون دسته‌بندی</span>
                                </div>
                            </div>
                        </CrmPanel>

                        <!-- روند رسیدگی -->
                        <CrmPanel title="روند رسیدگی" desc="این مراحل به‌صورت خودکار توسط فاکتور و سرویس به‌روز می‌شوند" :icon="Wrench" accent="var(--gs-gold)" :delay="200" still>
                            <CrmStatusSteps :status="request.status" />
                        </CrmPanel>
                    </div>

                    <!-- ---------- ستون کناری ---------- -->
                    <div class="crm-stack">
                        <!-- اطلاعات مشتری -->
                        <CrmPanel title="اطلاعات مشتری" desc="مشخصات ثبت‌شدهٔ درخواست‌دهنده" :icon="UserRound" accent="var(--gs-info)" :delay="140" still>
                            <div class="crm-details">
                                <div class="crm-detail" style="--crm-accent: var(--gs-gold)">
                                    <span class="crm-detail__icon"><UserRound :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">نام مشتری</p>
                                        <p class="crm-detail__v">
                                            <Link v-if="request.customer" :href="route('customers.show', request.customer.id)">
                                                {{ request.customer_name }}
                                            </Link>
                                            <template v-else>{{ request.customer_name }}</template>
                                        </p>
                                    </div>
                                </div>

                                <div class="crm-detail" style="--crm-accent: var(--gs-info)">
                                    <span class="crm-detail__icon"><Phone :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">شمارهٔ تماس</p>
                                        <p class="crm-detail__v" :class="{ 'is-empty': !request.customer?.phone }">
                                            <a v-if="request.customer?.phone" :href="`tel:${request.customer.phone}`" dir="ltr">{{ request.customer.phone }}</a>
                                            <template v-else>ثبت نشده</template>
                                        </p>
                                    </div>
                                </div>

                                <div class="crm-detail" style="--crm-accent: var(--gs-accent-2)">
                                    <span class="crm-detail__icon"><Mail :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">ایمیل</p>
                                        <p class="crm-detail__v" :class="{ 'is-empty': !request.customer?.email }">
                                            <a v-if="request.customer?.email" :href="`mailto:${request.customer.email}`" dir="ltr">{{ request.customer.email }}</a>
                                            <template v-else>ثبت نشده</template>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Link v-if="request.customer" :href="route('customers.show', request.customer.id)" class="a3d-btn a3d-btn--ghost a3d-btn--sm" style="margin-top: 0.9rem; width: 100%; justify-content: center">
                                مشاهده پروندهٔ کامل مشتری
                            </Link>
                            <p v-else style="margin-top: 0.9rem; font-size: 0.75rem; color: var(--gs-text-secondary)">
                                این درخواست بدون پروندهٔ مشتری ثبت شده است.
                            </p>
                        </CrmPanel>

                        <!-- فاکتور مالی مرتبط -->
                        <CrmPanel title="فاکتور مالی مرتبط" desc="وضعیت صدور و پرداخت فاکتور این درخواست" :icon="Receipt" accent="var(--gs-success)" :delay="180" still>
                            <div v-if="invoice" class="crm-details">
                                <div class="crm-detail" style="--crm-accent: var(--gs-success)">
                                    <span class="crm-detail__icon"><Receipt :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">شماره فاکتور</p>
                                        <p class="crm-detail__v"><span class="crm-code">{{ invoice.invoice_number }}</span></p>
                                    </div>
                                </div>

                                <div class="crm-detail" style="--crm-accent: var(--gs-gold)">
                                    <span class="crm-detail__icon"><FileText :size="16" /></span>
                                    <div style="min-width: 0">
                                        <p class="crm-detail__k">مبلغ کل</p>
                                        <p class="crm-detail__v">{{ money(invoice.total_amount) }}</p>
                                    </div>
                                </div>

                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.2rem">
                                    <CrmStatusChip :invoice="invoice" sm />
                                    <span v-if="paymentMeta" class="st-chip" :class="`st-chip--${paymentMeta.tone}`" style="font-size: 11px">
                                        {{ paymentMeta.label }}
                                    </span>
                                </div>

                                <Link :href="route('invoices.show', invoice.id)" class="a3d-btn a3d-btn--gold a3d-btn--sm" style="margin-top: 0.4rem; width: 100%; justify-content: center">
                                    <Receipt :size="14" /> مشاهدهٔ فاکتور کامل
                                </Link>
                            </div>

                            <CrmEmptyState
                                v-else
                                :icon="Receipt"
                                title="فاکتوری برای این درخواست صادر نشده"
                                desc="با صدور فاکتور، این درخواست به‌صورت خودکار به «در جریان» تغییر وضعیت می‌دهد."
                            >
                                <Link :href="createInvoiceHref" class="a3d-btn a3d-btn--gold a3d-btn--sm">
                                    <Plus :size="14" /> صدور فاکتور جدید
                                </Link>
                            </CrmEmptyState>
                        </CrmPanel>

                        <!-- ناحیهٔ خطر -->
                        <CrmPanel title="ناحیهٔ خطر" desc="حذف درخواست (قابل بازیابی از سطل بازیافت)" :icon="ShieldAlert" accent="var(--gs-error)" :delay="220" still>
                            <p style="font-size: 0.78rem; line-height: 1.9; color: var(--gs-text-secondary); margin-bottom: 0.9rem">
                                با حذف درخواست، پرونده به‌صورت نرم (soft delete) حذف می‌شود و ارتباط دسته‌بندی‌ها پاک می‌گردد.
                            </p>
                            <button type="button" class="a3d-btn a3d-btn--danger a3d-btn--sm" @click="confirmOpen = true">
                                <Trash2 :size="14" /> حذف درخواست
                            </button>
                        </CrmPanel>
                    </div>
                </div>
            </div>

            <CrmConfirmDialog
                v-model="confirmOpen"
                title="حذف درخواست"
                message="درخواست به سطل بازیافت منتقل می‌شود و ارتباط دسته‌بندی‌ها حذف می‌شود. ادامه می‌دهید؟"
                :highlight="`#${request.id} — ${request.customer_name}`"
                :loading="deleting"
                @confirm="doDelete"
            />

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>