<script setup>
/**
 * صفحهٔ داشبورد گیم‌استور — بازطراحی سه‌بعدی مطابق استانداردهای صفحه تنظیمات
 * مسیر: resources/js/Pages/Dashboard.vue
 * ---------------------------------------------------------------------------
 * داده‌ها و روتا کاملاً با ساختار کنترلر ماژول Dashboard هماهنگ است.
 * وابستگی‌ها:
 *   • @/Layouts/AppLayout.vue
 *   • @/Composables/useTilt (vTilt, vReveal)
 *   • @/Utils/format (faInt, money, jalali, jalaliLong)
 *   • lucide-vue-next
 */
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    Users,
    ClipboardList,
    Boxes,
    Wrench,
    ArrowUpRight,
    Plus,
    Receipt,
    FileText,
    TrendingUp,
    Sparkles,
    ShieldCheck,
    Clock,
    Flame,
    Gamepad2,
    ChevronLeft,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            customers_count: 0,
            open_requests: 0,
            items_count: 0,
            active_service_jobs: 0,
        }),
    },
    recentRequests: {
        type: Array,
        default: () => [],
    },
    recentInvoices: {
        type: Array,
        default: () => [],
    },
})

/* وضعیت و برچسب‌های درخواست */
function statusLabel(status) {
    const map = {
        pending: 'در انتظار',
        in_progress: 'در جریان',
        completed: 'تکمیل شده',
        canceled: 'لغو شده',
    }
    return map[status] ?? status
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

function formatPrice(amount) {
    if (!amount) return '—'
    return Number(amount).toLocaleString('fa-IR') + ' تومان'
}

/* اکشن‌های دسترسی سریع */
const quickActions = [
    { title: 'مشتری جدید', desc: 'ثبت پرونده مشتری', route: 'customers.create', icon: Users, color: '#5b9df0' },
    { title: 'درخواست جدید', desc: 'تعمیر یا سرویس', route: 'requests.create', icon: ClipboardList, color: '#e3bd5c' },
    { title: 'فاکتور فروش', desc: 'صدور فاکتور جدید', route: 'invoices.create', icon: Receipt, color: '#45d68b' },
    { title: 'سرویس سخت‌افزار', desc: 'تسک فنی کارگاه', route: 'service-jobs.create', icon: Wrench, color: '#9f7bf6' },
    { title: 'محصول و کالا', desc: 'افزودن به موجودی', route: 'items.create', icon: Boxes, color: '#38bdf8' },
    { title: 'ورودی انبار', desc: 'شارژ کالا و قطعات', route: 'stock-movements.store', icon: ArrowUpRight, color: '#f59e0b' },
]
</script>

<template>
    <AppLayout>
        <Head title="داشبورد مدیریت" />

        <!-- پس‌زمینه محیطی سه‌بعدی -->
        <div class="a3d-scene st-scene" aria-hidden="true">
            <span class="a3d-grid-floor" />
            <span class="a3d-orb a3d-orb--gold a3d-float-a" style="width: 480px; height: 480px; top: -180px; inset-inline-end: 4%" />
            <span class="a3d-orb a3d-orb--blue a3d-float-b" style="width: 420px; height: 420px; bottom: -160px; inset-inline-start: 3%" />
        </div>

        <div class="st-page relative z-10 space-y-8 pb-12">
            <!-- سربرگ شیشه‌ای لوکس -->
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="st-chip st-chip--live">
                            <span class="st-dot"></span>
                            سیستم عملیاتی آنلاین
                        </span>
                        <span class="text-xs text-neutral-400">GameStore Control Center</span>
                    </div>

                    <h1 class="st-hero__title">
                        داشبورد <span>مدیریت فروشگاه</span>
                    </h1>
                    <p class="st-hero__lead">
                        خوش آمدید، {{ $page.props.auth?.user?.name || 'مدیر سیستم' }} — نمای جامع عملکرد، مشتریان و سرویس‌ها
                    </p>
                    <svg class="st-underline" viewBox="0 0 230 11" fill="none">
                        <path d="M2 8.5C65 2.5 165 2.5 228 8.5" stroke="url(#goldGrad)" stroke-width="3.5" stroke-linecap="round" />
                        <defs>
                            <linearGradient id="goldGrad" x1="0" y1="0" x2="1" y2="0">
                                <stop stop-color="#f3d98a" />
                                <stop offset="0.5" stop-color="#e3bd5c" />
                                <stop offset="1" stop-color="#b08c34" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <!-- وضعیت سریع سمت چپ هیرو -->
                <div class="hidden lg:flex items-center gap-3">
                    <div class="st-card p-3 px-4 flex items-center gap-3 border border-amber-500/20 bg-black/40 backdrop-blur-md rounded-2xl">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400">
                            <Gamepad2 :size="22" />
                        </div>
                        <div>
                            <p class="text-xs text-neutral-400">سرویس‌های در دست اقدام</p>
                            <p class="text-lg font-bold text-amber-400">{{ faInt(stats.active_service_jobs) }} دستگاه</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ۴ کارت آمار سه‌بعدی KPI -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" v-reveal="{ delay: 120 }">
                <!-- مشتریان -->
                <article v-tilt="{ max: 9, scale: 1.02, lift: 14 }" class="st-card group relative p-5 overflow-hidden transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-neutral-400">کل مشتریان ثبت‌شده</p>
                            <h3 class="text-2xl font-black text-amber-300 mt-2 tracking-tight">
                                {{ faInt(stats.customers_count) }}
                            </h3>
                            <span class="inline-flex items-center gap-1 text-[11px] text-emerald-400 mt-2 font-medium">
                                <TrendingUp :size="12" /> فعال در باشگاه
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/25 flex items-center justify-center text-blue-400 shadow-lg shadow-blue-500/10 group-hover:scale-110 transition-transform">
                            <Users :size="24" />
                        </div>
                    </div>
                    <div class="mt-4 h-1 w-full bg-neutral-800 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-amber-400 w-3/4 rounded-full"></div>
                    </div>
                </article>

                <!-- درخواست‌های باز -->
                <article v-tilt="{ max: 9, scale: 1.02, lift: 14 }" class="st-card group relative p-5 overflow-hidden transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-neutral-400">درخواست‌های باز</p>
                            <h3 class="text-2xl font-black text-amber-400 mt-2 tracking-tight">
                                {{ faInt(stats.open_requests) }}
                            </h3>
                            <span class="inline-flex items-center gap-1 text-[11px] text-amber-400 mt-2 font-medium">
                                <Clock :size="12" /> نیازمند پیگیری
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/25 flex items-center justify-center text-amber-400 shadow-lg shadow-amber-500/10 group-hover:scale-110 transition-transform">
                            <ClipboardList :size="24" />
                        </div>
                    </div>
                    <div class="mt-4 h-1 w-full bg-neutral-800 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-amber-500 to-amber-300 w-2/3 rounded-full"></div>
                    </div>
                </article>

                <!-- اقلام انبار -->
                <article v-tilt="{ max: 9, scale: 1.02, lift: 14 }" class="st-card group relative p-5 overflow-hidden transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-neutral-400">تنوع اقلام انبار</p>
                            <h3 class="text-2xl font-black text-emerald-400 mt-2 tracking-tight">
                                {{ faInt(stats.items_count) }}
                            </h3>
                            <span class="inline-flex items-center gap-1 text-[11px] text-emerald-400 mt-2 font-medium">
                                <ShieldCheck :size="12" /> موجودی انبار پایدار
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-500/10 group-hover:scale-110 transition-transform">
                            <Boxes :size="24" />
                        </div>
                    </div>
                    <div class="mt-4 h-1 w-full bg-neutral-800 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-300 w-4/5 rounded-full"></div>
                    </div>
                </article>

                <!-- سرویس‌های جاری -->
                <article v-tilt="{ max: 9, scale: 1.02, lift: 14 }" class="st-card group relative p-5 overflow-hidden transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-neutral-400">سرویس‌های جاری کارگاه</p>
                            <h3 class="text-2xl font-black text-purple-400 mt-2 tracking-tight">
                                {{ faInt(stats.active_service_jobs) }}
                            </h3>
                            <span class="inline-flex items-center gap-1 text-[11px] text-purple-400 mt-2 font-medium">
                                <Flame :size="12" /> در خط تعمیرات
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/25 flex items-center justify-center text-purple-400 shadow-lg shadow-purple-500/10 group-hover:scale-110 transition-transform">
                            <Wrench :size="24" />
                        </div>
                    </div>
                    <div class="mt-4 h-1 w-full bg-neutral-800 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-purple-500 to-pink-400 w-1/2 rounded-full"></div>
                    </div>
                </article>
            </section>

            <!-- دسترسی سریع گیمینگ -->
            <section v-reveal="{ delay: 180 }" class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Sparkles :size="18" class="text-amber-400" />
                        <h2 class="text-sm font-bold tracking-wide text-amber-400 uppercase">دسترسی سریع عملیاتی</h2>
                    </div>
                    <div class="h-[1px] flex-1 bg-gradient-to-r from-amber-500/20 to-transparent mr-4"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <Link
                        v-for="action in quickActions"
                        :key="action.route"
                        :href="route(action.route)"
                        v-tilt="{ max: 12, scale: 1.04, lift: 12 }"
                        class="st-card p-4 rounded-xl flex flex-col items-center text-center group hover:border-amber-500/50 hover:bg-amber-500/5 transition-all duration-200"
                    >
                        <div
                            class="w-11 h-11 rounded-xl flex items-center justify-center mb-2.5 transition-transform group-hover:scale-110 shadow-md"
                            :style="{ backgroundColor: action.color + '18', color: action.color, borderColor: action.color + '40' }"
                        >
                            <component :is="action.icon" :size="20" />
                        </div>
                        <span class="text-xs font-bold text-neutral-200 group-hover:text-amber-300">{{ action.title }}</span>
                        <span class="text-[10px] text-neutral-400 mt-0.5">{{ action.desc }}</span>
                    </Link>
                </div>
            </section>

            <!-- دو ستون: آخرین درخواست‌ها و آخرین فاکتورها -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-6" v-reveal="{ delay: 240 }">
                <!-- آخرین درخواست‌ها -->
                <div class="st-card rounded-2xl overflow-hidden border border-neutral-800 flex flex-col justify-between">
                    <div class="p-4 border-b border-neutral-800 flex items-center justify-between bg-white/[0.02]">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center">
                                <ClipboardList :size="18" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-neutral-200">آخرین درخواست‌های ثبت‌شده</h3>
                                <p class="text-[11px] text-neutral-400">وضعیت رسیدگی به دستگاه‌های ورودی</p>
                            </div>
                        </div>
                        <Link :href="route('requests.index')" class="st-chip st-chip--plain hover:border-amber-500/40 text-xs">
                            مشاهده همه <ChevronLeft :size="14" />
                        </Link>
                    </div>

                    <div class="p-2 flex-1">
                        <table v-if="recentRequests.length" class="w-full text-right text-xs">
                            <thead>
                                <tr class="text-neutral-400 border-b border-neutral-800/60 text-[11px]">
                                    <th class="py-2.5 px-3">مشتری</th>
                                    <th class="py-2.5 px-3">وضعیت</th>
                                    <th class="py-2.5 px-3 text-left">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-800/40">
                                <tr v-for="req in recentRequests" :key="req.id" class="hover:bg-white/[0.03] transition-colors">
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-neutral-200">{{ req.customer_name }}</p>
                                        <p class="text-[10px] text-neutral-400 truncate max-w-[180px]">{{ req.description }}</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="st-chip text-[11px]" :class="statusBadgeClass(req.status)">
                                            {{ statusLabel(req.status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-left">
                                        <Link :href="route('requests.show', req.id)" class="px-2.5 py-1 rounded-lg bg-neutral-800/80 hover:bg-amber-500/20 hover:text-amber-300 text-neutral-300 text-[11px] font-medium transition-colors">
                                            جزئیات
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-else class="text-center py-8 text-neutral-400 text-xs">
                            درخواستی ثبت نشده است
                        </div>
                    </div>
                </div>

                <!-- آخرین فاکتورها -->
                <div class="st-card rounded-2xl overflow-hidden border border-neutral-800 flex flex-col justify-between">
                    <div class="p-4 border-b border-neutral-800 flex items-center justify-between bg-white/[0.02]">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center">
                                <Receipt :size="18" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-neutral-200">آخرین فاکتورهای فروش</h3>
                                <p class="text-[11px] text-neutral-400">صورت‌حساب‌های صادره اخیر</p>
                            </div>
                        </div>
                        <Link :href="route('invoices.index')" class="st-chip st-chip--plain hover:border-amber-500/40 text-xs">
                            مشاهده همه <ChevronLeft :size="14" />
                        </Link>
                    </div>

                    <div class="p-2 flex-1">
                        <table v-if="recentInvoices.length" class="w-full text-right text-xs">
                            <thead>
                                <tr class="text-neutral-400 border-b border-neutral-800/60 text-[11px]">
                                    <th class="py-2.5 px-3">شماره فاکتور</th>
                                    <th class="py-2.5 px-3">مبلغ کل</th>
                                    <th class="py-2.5 px-3 text-left">وضعیت تأیید</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-800/40">
                                <tr v-for="inv in recentInvoices" :key="inv.id" class="hover:bg-white/[0.03] transition-colors">
                                    <td class="py-3 px-3 font-mono font-medium text-amber-300">
                                        {{ inv.invoice_number }}
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-neutral-200">
                                        {{ formatPrice(inv.total_amount) }}
                                    </td>
                                    <td class="py-3 px-3 text-left">
                                        <span
                                            class="st-chip text-[11px]"
                                            :class="inv.is_confirmed === 1 ? 'st-chip--success' : inv.is_confirmed === 0 ? 'st-chip--error' : 'st-chip--warning'"
                                        >
                                            {{ inv.is_confirmed === 1 ? 'تأیید شده' : inv.is_confirmed === 0 ? 'رد شده' : 'در انتظار' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-else class="text-center py-8 text-neutral-400 text-xs">
                            فاکتوری ثبت نشده است
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
