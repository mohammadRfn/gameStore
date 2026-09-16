<script setup>
/**
 * نمایش کامل درخواست — اتاق فرمان ۳D
 * مسیر: resources/js/Pages/Requests/Show.vue
 */
import { Head, Link } from '@inertiajs/vue3'
import {
    ClipboardList,
    ArrowRight,
    Edit3,
    User,
    Tag,
    Receipt,
    CheckCircle2,
    Clock,
    Wrench,
    AlertCircle,
    ChevronLeft,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'

const props = defineProps({
    request: {
        type: Object,
        required: true,
    },
})

function formatPrice(amount) {
    if (!amount) return '۰ تومان'
    return Number(amount).toLocaleString('fa-IR') + ' تومان'
}

function statusBadgeClass(status) {
    const map = {
        pending: 'st-chip--warning',
        in_progress: 'st-chip--info',
        completed: 'st-chip--success',
        canceled: 'st-chip--error',
    }
    return map[status] ?? 'st-chip--plain'
}

function statusLabel(status) {
    const map = {
        pending: 'در انتظار رسیدگی',
        in_progress: 'در جریان تعمیر و بررسی',
        completed: 'تکمیل و آماده تحویل',
        canceled: 'لغو شده',
    }
    return map[status] ?? status
}
</script>

<template>
    <AppLayout>
        <Head :title="'درخواست #' + request.id" />

        <div class="st-page relative z-10 space-y-6 pb-12">
            <!-- سربرگ -->
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="st-chip text-xs" :class="statusBadgeClass(request.status)">
                            {{ statusLabel(request.status) }}
                        </span>
                        <span class="text-xs text-neutral-400">کد رهگیری: #{{ faInt(request.id) }}</span>
                    </div>

                    <h1 class="st-hero__title">
                        درخواست <span>{{ request.customer_name }}</span>
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="route('requests.edit', request.id)" class="gs-btn-ghost text-xs">
                        <Edit3 :size="15" /> ویرایش درخواست
                    </Link>
                    <Link :href="route('requests.index')" class="px-3 py-2 rounded-xl bg-neutral-800 text-neutral-300 text-xs hover:bg-neutral-700 flex items-center gap-1">
                        بازگشت <ArrowRight :size="14" />
                    </Link>
                </div>
            </header>

            <!-- شبکه دو ستونی جزئیات -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" v-reveal="{ delay: 100 }">
                <!-- ستون اصلی توضیحات -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="st-card p-6 rounded-2xl space-y-4">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">شرح کامل درخواست مشتری</h3>
                        <p class="text-sm text-neutral-200 leading-relaxed whitespace-pre-line bg-neutral-900/40 p-4 rounded-xl border border-neutral-800">
                            {{ request.description }}
                        </p>

                        <div class="pt-3 border-t border-neutral-800 flex items-center gap-3">
                            <span class="text-xs text-neutral-400">دسته‌بندی‌های تخصصی:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="cat in request.categories"
                                    :key="cat.id"
                                    class="st-chip st-chip--gold text-[11px]"
                                >
                                    {{ cat.name }}
                                </span>
                                <span v-if="!request.categories?.length" class="text-neutral-500 text-xs">بدون دسته‌بندی</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ستون کناری: مشتری و فاکتور -->
                <div class="space-y-6">
                    <!-- مشخصات مشتری -->
                    <div class="st-card p-5 rounded-2xl space-y-3">
                        <h4 class="text-xs font-bold text-amber-400 uppercase">اطلاعات مشتری</h4>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-300 flex items-center justify-center font-bold">
                                <User :size="18" />
                            </div>
                            <div>
                                <p class="font-bold text-neutral-200 text-xs">{{ request.customer_name }}</p>
                                <Link v-if="request.customer" :href="route('customers.show', request.customer.id)" class="text-[11px] text-amber-400 hover:underline">
                                    مشاهده پرونده کامل مشتری ←
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- فاکتور مرتبط -->
                    <div class="st-card p-5 rounded-2xl space-y-3">
                        <h4 class="text-xs font-bold text-emerald-400 uppercase">فاکتور مالی مرتبط</h4>
                        <div v-if="request.invoice" class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-400">شماره:</span>
                                <span class="font-mono font-bold text-amber-300">{{ request.invoice.invoice_number }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-400">مبلغ:</span>
                                <span class="font-bold text-neutral-200">{{ formatPrice(request.invoice.total_amount) }}</span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-neutral-500">فاکتوری برای این درخواست صادر نشده است</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
