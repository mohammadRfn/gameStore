<script setup>
/**
 * پرونده کامل مشتری — بازطراحی سه‌بعدی
 * مسیر: resources/js/Pages/Customers/Show.vue
 */
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    Users,
    Phone,
    Mail,
    MapPin,
    FileText,
    Receipt,
    Plus,
    Edit3,
    ArrowRight,
    CheckCircle2,
    Clock,
    DollarSign,
    Shield,
    Gamepad2,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
})

const activeTab = ref('requests') // 'requests' | 'invoices'

function formatPrice(amount) {
    if (!amount) return '۰ تومان'
    return Number(amount).toLocaleString('fa-IR') + ' تومان'
}

const totalInvoicesSum = computed(() => {
    if (!props.customer.invoices) return 0
    return props.customer.invoices.reduce((acc, curr) => acc + Number(curr.total_amount || 0), 0)
})
</script>

<template>
    <AppLayout>
        <Head :title="'پرونده ' + customer.name" />

        <div class="st-page relative z-10 space-y-6 pb-12">
            <!-- سربرگ پرونده -->
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-amber-500/20 to-neutral-800 border-2 border-amber-500/40 flex items-center justify-center font-black text-amber-300 text-2xl shadow-xl shadow-amber-500/10">
                        {{ customer.name.slice(0, 1) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="st-chip st-chip--gold text-xs">پرونده مشتری</span>
                            <span class="text-xs text-neutral-400">شناسه: #{{ faInt(customer.id) }}</span>
                        </div>
                        <h1 class="text-2xl lg:text-3xl font-black text-neutral-100 mt-1">{{ customer.name }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="route('customers.edit', customer.id)" class="gs-btn-ghost text-xs">
                        <Edit3 :size="15" /> ویرایش
                    </Link>
                    <Link :href="route('customers.index')" class="px-3 py-2 rounded-xl bg-neutral-800 text-neutral-300 text-xs hover:bg-neutral-700 flex items-center gap-1">
                        بازگشت <ArrowRight :size="14" />
                    </Link>
                </div>
            </header>

            <!-- اطلاعات و کارت‌های خلاصه -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" v-reveal="{ delay: 100 }">
                <!-- کارت مشخصات تماس -->
                <div class="st-card p-5 rounded-2xl space-y-4">
                    <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">مشخصات تماس و آدرس</h3>
                    
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-neutral-900/60 border border-neutral-800">
                            <Phone :size="16" class="text-amber-400" />
                            <div>
                                <p class="text-[10px] text-neutral-400">شماره تلفن همراه</p>
                                <p class="font-mono text-neutral-200 mt-0.5" dir="ltr">{{ customer.phone || 'ثبت نشده' }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-neutral-900/60 border border-neutral-800">
                            <Mail :size="16" class="text-blue-400" />
                            <div>
                                <p class="text-[10px] text-neutral-400">پست الکترونیک</p>
                                <p class="text-neutral-200 mt-0.5 truncate max-w-[220px]">{{ customer.email || 'ثبت نشده' }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-neutral-900/60 border border-neutral-800">
                            <MapPin :size="16" class="text-emerald-400 mt-0.5" />
                            <div>
                                <p class="text-[10px] text-neutral-400">آدرس پستی</p>
                                <p class="text-neutral-200 mt-0.5 leading-relaxed">{{ customer.address || 'آدرسی ثبت نشده است' }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="customer.notes" class="p-3 rounded-xl bg-amber-500/5 border border-amber-500/20 text-xs">
                        <p class="text-[10px] text-amber-400 font-bold mb-1">یادداشت مدیر:</p>
                        <p class="text-neutral-300 leading-relaxed">{{ customer.notes }}</p>
                    </div>
                </div>

                <!-- ۲ کارت آمار مالی و درخواست‌ها -->
                <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-tilt="{ max: 8, scale: 1.02, lift: 12 }" class="st-card p-5 rounded-2xl flex flex-col justify-between">
                        <div>
                            <span class="st-chip st-chip--info text-xs">سابقه فنی</span>
                            <h4 class="text-2xl font-black text-blue-400 mt-3">{{ faInt(customer.requests?.length || 0) }} درخواست</h4>
                            <p class="text-xs text-neutral-400 mt-1">تعداد درخواست‌های سرویس و تعمیرات ثبت‌شده</p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-neutral-800">
                            <Link :href="route('requests.create') + '?customer_id=' + customer.id" class="gs-btn-gold text-xs w-full justify-center">
                                <Plus :size="15" /> ثبت درخواست برای این مشتری
                            </Link>
                        </div>
                    </div>

                    <div v-tilt="{ max: 8, scale: 1.02, lift: 12 }" class="st-card p-5 rounded-2xl flex flex-col justify-between">
                        <div>
                            <span class="st-chip st-chip--gold text-xs">خلاصه مالی</span>
                            <h4 class="text-2xl font-black text-amber-300 mt-3">{{ formatPrice(totalInvoicesSum) }}</h4>
                            <p class="text-xs text-neutral-400 mt-1">مجموع ارزش فاکتورهای صادر شده</p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-neutral-800 flex items-center justify-between text-xs text-neutral-400">
                            <span>تعداد فاکتورها:</span>
                            <span class="font-bold text-neutral-200">{{ faInt(customer.invoices?.length || 0) }} عدد</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تب‌های سابقه درخواست‌ها و فاکتورها -->
            <section class="st-card rounded-2xl overflow-hidden p-6 space-y-4" v-reveal="{ delay: 150 }">
                <div class="flex items-center gap-2 border-b border-neutral-800 pb-3">
                    <button
                        @click="activeTab = 'requests'"
                        :class="activeTab === 'requests' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'text-neutral-400 hover:text-neutral-200'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-2"
                    >
                        <Gamepad2 :size="15" /> درخواست‌های تعمیر و سرویس ({{ faInt(customer.requests?.length || 0) }})
                    </button>
                    <button
                        @click="activeTab = 'invoices'"
                        :class="activeTab === 'invoices' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'text-neutral-400 hover:text-neutral-200'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-2"
                    >
                        <Receipt :size="15" /> فاکتورهای فروش ({{ faInt(customer.invoices?.length || 0) }})
                    </button>
                </div>

                <!-- لیست درخواست‌ها -->
                <div v-if="activeTab === 'requests'">
                    <div v-if="customer.requests?.length" class="space-y-3">
                        <div
                            v-for="req in customer.requests"
                            :key="req.id"
                            class="p-4 rounded-xl bg-neutral-900/60 border border-neutral-800 flex items-center justify-between gap-4 hover:border-amber-500/30 transition-all"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-amber-400 text-xs font-bold">#{{ req.id }}</span>
                                    <span class="st-chip st-chip--plain text-[10px]">{{ req.status }}</span>
                                </div>
                                <p class="text-xs text-neutral-200 mt-1 font-medium">{{ req.description }}</p>
                            </div>
                            <Link :href="route('requests.show', req.id)" class="px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-xs text-neutral-300">
                                جزئیات
                            </Link>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 text-center py-6">درخواستی برای این مشتری ثبت نشده است</p>
                </div>

                <!-- لیست فاکتورها -->
                <div v-else>
                    <div v-if="customer.invoices?.length" class="space-y-3">
                        <div
                            v-for="inv in customer.invoices"
                            :key="inv.id"
                            class="p-4 rounded-xl bg-neutral-900/60 border border-neutral-800 flex items-center justify-between gap-4"
                        >
                            <div>
                                <p class="font-mono text-amber-300 text-xs font-bold">{{ inv.invoice_number }}</p>
                                <p class="text-xs text-neutral-200 mt-1 font-bold">{{ formatPrice(inv.total_amount) }}</p>
                            </div>
                            <span class="st-chip text-[11px]" :class="inv.is_confirmed === 1 ? 'st-chip--success' : 'st-chip--warning'">
                                {{ inv.is_confirmed === 1 ? 'تأیید شده' : 'در انتظار' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 text-center py-6">فاکتوری برای این مشتری ثبت نشده است</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
