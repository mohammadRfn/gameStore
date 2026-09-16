<script setup>
/**
 * داشبورد گیم‌استور — بازطراحی هم‌سطح با صفحهٔ تنظیمات
 * مسیر: resources/js/Pages/Dashboard.vue
 * ---------------------------------------------------------------------------
 * props دقیقاً همان خروجی Modules\Dashboard\Http\Controllers\DashboardController:
 *   stats           { customers_count, open_requests, items_count, active_service_jobs }
 *   recentRequests  [{ id, customer_name, description, status, categories[] }]
 *   recentInvoices  [{ id, invoice_number, total_amount, is_confirmed }]
 *
 * روت‌ها: customers.* / requests.* / invoices.* / service-jobs.* / items.* /
 *         stock-movements.index  (همه در ماژول‌های مربوطه تعریف شده‌اند)
 *
 * وابستگی‌ها: resources/css/crm-3d.css (ایمپورت در app.css) + Components/Crm/*
 */
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeftRight,
    Boxes,
    ChevronLeft,
    ClipboardList,
    Clock,
    Flame,
    Gamepad2,
    Layers,
    Plus,
    Receipt,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    UserPlus,
    Users,
    Wrench,
    Zap,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'
import { avatarHue, clip, greeting, initials, money, todayFa } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CrmOrbit from '@/Components/Crm/CrmOrbit.vue'
import CrmKpiCard from '@/Components/Crm/CrmKpiCard.vue'
import CrmPanel from '@/Components/Crm/CrmPanel.vue'
import CrmStatusChip from '@/Components/Crm/CrmStatusChip.vue'
import CrmEmptyState from '@/Components/Crm/CrmEmptyState.vue'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({ customers_count: 0, open_requests: 0, items_count: 0, active_service_jobs: 0 }),
    },
    recentRequests: { type: Array, default: () => [] },
    recentInvoices: { type: Array, default: () => [] },
})

const page = usePage()
const userName = computed(() => page.props.auth?.user?.name || 'مدیر سیستم')
const today = todayFa()
const hello = greeting()

/* ---------------- شاخص‌ها ---------------- */
const kpis = computed(() => [
    {
        key: 'customers',
        label: 'کل مشتریان',
        value: props.stats.customers_count,
        icon: Users,
        accent: 'var(--gs-info)',
        hint: 'اعضای باشگاه مشتریان',
        hintIcon: TrendingUp,
        to: route('customers.index'),
        fill: 78,
    },
    {
        key: 'requests',
        label: 'درخواست‌های باز',
        value: props.stats.open_requests,
        icon: ClipboardList,
        accent: 'var(--gs-gold)',
        hint: 'در انتظار یا در جریان',
        hintIcon: Clock,
        to: route('requests.index'),
        fill: 64,
    },
    {
        key: 'items',
        label: 'تنوع اقلام انبار',
        value: props.stats.items_count,
        icon: Boxes,
        accent: 'var(--gs-accent-2)',
        hint: 'کالاهای تعریف‌شده در انبار',
        hintIcon: ShieldCheck,
        to: route('items.index'),
        fill: 84,
    },
    {
        key: 'service',
        label: 'سرویس‌های فعال کارگاه',
        value: props.stats.active_service_jobs,
        icon: Wrench,
        accent: 'var(--gs-accent-3)',
        hint: 'در خط تعمیرات',
        hintIcon: Flame,
        to: route('service-jobs.index'),
        fill: 52,
    },
])

/* ---------------- دسترسی سریع ---------------- */
const quickActions = [
    { title: 'مشتری جدید', desc: 'ثبت پروندهٔ مشتری', route: 'customers.create', icon: UserPlus, accent: 'var(--gs-info)' },
    { title: 'درخواست جدید', desc: 'تعمیر یا سرویس', route: 'requests.create', icon: ClipboardList, accent: 'var(--gs-gold)' },
    { title: 'فاکتور فروش', desc: 'صدور فاکتور جدید', route: 'invoices.create', icon: Receipt, accent: 'var(--gs-success)' },
    { title: 'سرویس سخت‌افزار', desc: 'تسک فنی کارگاه', route: 'service-jobs.create', icon: Wrench, accent: 'var(--gs-accent-3)' },
    { title: 'محصول و کالا', desc: 'افزودن به موجودی', route: 'items.create', icon: Boxes, accent: '#38bdf8' },
    { title: 'گردش انبار', desc: 'ورود و خروج کالا', route: 'stock-movements.index', icon: ArrowLeftRight, accent: 'var(--gs-warning)' },
]

/* ---------------- نبض کارگاه (از داده‌های واقعی) ---------------- */
const workload = computed(() => {
    const open = Number(props.stats.open_requests || 0)
    const active = Number(props.stats.active_service_jobs || 0)
    const max = Math.max(open, active, 1)
    return [
        { label: 'درخواست‌های باز', value: open, pct: (open / max) * 100, accent: 'var(--gs-gold)', to: route('requests.index') },
        { label: 'سرویس‌های در جریان', value: active, pct: (active / max) * 100, accent: 'var(--gs-accent-3)', to: route('service-jobs.index') },
    ]
})

const orbitSats = [
    { icon: Users, color: 'var(--gs-info)' },
    { icon: ClipboardList, color: 'var(--gs-gold)', reverse: true },
    { icon: Wrench, color: 'var(--gs-accent-3)' },
]
</script>

<template>
    <Head title="داشبورد مدیریت" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="blue" />

            <!-- ================= سربرگ ================= -->
            <header class="st-shell">
                <div class="st-hero">
                    <div style="min-width: 0">
                        <div class="crm-hero__chips">
                            <span class="st-chip st-chip--live">
                                <span class="st-dot" />
                                LIVE · سیستم آنلاین
                            </span>
                            <span class="st-chip">
                                <Gamepad2 :size="13" />
                                مرکز فرمان گیم‌استور
                            </span>
                            <span class="st-chip st-chip--plain">
                                <Clock :size="13" />
                                {{ today }}
                            </span>
                        </div>

                        <h1 class="st-hero__title">
                            <span>داشبورد</span>
                            <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                <path
                                    d="M4 10 C 60 2, 150 2, 216 8"
                                    fill="none"
                                    stroke="var(--gs-gold)"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </h1>

                        <p class="st-hero__lead">
                            {{ hello }}، <b style="color: var(--gs-text-primary)">{{ userName }}</b> — نمای زندهٔ مشتریان،
                            درخواست‌ها، انبار و کارگاه در یک نگاه.
                        </p>

                        <div class="crm-hero__stats">
                            <span class="st-stat">
                                <Users :size="15" />
                                مشتریان
                                <b>{{ faInt(stats.customers_count) }}</b>
                            </span>
                            <span class="st-stat">
                                <ClipboardList :size="15" />
                                درخواست باز
                                <b>{{ faInt(stats.open_requests) }}</b>
                            </span>
                            <span class="st-stat">
                                <Wrench :size="15" />
                                سرویس فعال
                                <b>{{ faInt(stats.active_service_jobs) }}</b>
                            </span>
                        </div>
                    </div>

                    <div class="crm-hero__side">
                        <CrmOrbit :icon="Gamepad2" :satellites="orbitSats" />
                        <div class="crm-hero__actions" style="flex-direction: column">
                            <Link :href="route('requests.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm">
                                <Plus :size="14" /> درخواست جدید
                            </Link>
                            <Link :href="route('customers.create')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
                                <UserPlus :size="14" /> مشتری جدید
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ================= بدنه ================= -->
            <div class="st-shell crm-body crm-stack">
                <!-- شاخص‌ها -->
                <section class="crm-grid-kpi">
                    <CrmKpiCard
                        v-for="(k, i) in kpis"
                        :key="k.key"
                        :label="k.label"
                        :value="k.value"
                        :icon="k.icon"
                        :accent="k.accent"
                        :hint="k.hint"
                        :hint-icon="k.hintIcon"
                        :to="k.to"
                        :fill="k.fill"
                        :delay="60 + i * 70"
                    />
                </section>

                <!-- دسترسی سریع -->
                <section v-reveal="{ delay: 120 }">
                    <div class="st-sechead" style="margin-bottom: 0.9rem">
                        <span class="st-sechead__icon"><Sparkles :size="21" /></span>
                        <div>
                            <h2 class="st-sechead__title">دسترسی سریع</h2>
                            <p class="st-sechead__desc">پرتکرارترین عملیات روزانهٔ فروشگاه</p>
                        </div>
                    </div>

                    <div class="crm-tiles">
                        <Link
                            v-for="(a, i) in quickActions"
                            :key="a.route"
                            v-reveal="{ delay: 140 + i * 50 }"
                            v-tilt="{ max: 12, lift: 16, scale: 1.04 }"
                            :href="route(a.route)"
                            class="a3d-holo a3d-aura crm-tile"
                            :style="{ '--crm-accent': a.accent, '--a3d-aura-color': a.accent }"
                        >
                            <span class="crm-tile__icon"><component :is="a.icon" :size="22" /></span>
                            <span class="crm-tile__title">{{ a.title }}</span>
                            <span class="crm-tile__desc">{{ a.desc }}</span>
                        </Link>
                    </div>
                </section>

                <!-- آخرین‌ها -->
                <section class="crm-grid-2">
                    <!-- آخرین درخواست‌ها -->
                    <CrmPanel
                        title="آخرین درخواست‌ها"
                        desc="وضعیت رسیدگی به دستگاه‌های ورودی"
                        :icon="ClipboardList"
                        accent="var(--gs-gold)"
                        :delay="180"
                        flush
                    >
                        <template #actions>
                            <Link :href="route('requests.index')" class="crm-more">
                                مشاهدهٔ همه <ChevronLeft :size="14" />
                            </Link>
                        </template>

                        <div v-if="recentRequests.length" class="crm-rows">
                            <Link
                                v-for="(req, i) in recentRequests"
                                :key="req.id"
                                :href="route('requests.show', req.id)"
                                class="crm-row"
                                :style="{ '--i': i }"
                            >
                                <span class="crm-avatar crm-avatar--sm" :style="{ '--hue': avatarHue(req.customer_name) }">
                                    {{ initials(req.customer_name) }}
                                </span>
                                <div class="crm-row__main">
                                    <p class="crm-row__title">{{ req.customer_name }}</p>
                                    <p class="crm-row__sub">
                                        <template v-if="req.categories?.length">
                                            {{ req.categories.map((c) => c.name).join('، ') }} ·
                                        </template>
                                        {{ clip(req.description, 70) || 'بدون شرح' }}
                                    </p>
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
                            title="هنوز درخواستی ثبت نشده"
                            desc="اولین درخواست تعمیر یا سرویس را ثبت کنید تا این‌جا نمایش داده شود."
                        >
                            <Link :href="route('requests.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm">
                                <Plus :size="14" /> ثبت درخواست
                            </Link>
                        </CrmEmptyState>
                    </CrmPanel>

                    <!-- آخرین فاکتورها -->
                    <CrmPanel
                        title="آخرین فاکتورهای فروش"
                        desc="صورت‌حساب‌های صادرشدهٔ اخیر"
                        :icon="Receipt"
                        accent="var(--gs-success)"
                        :delay="240"
                        flush
                    >
                        <template #actions>
                            <Link :href="route('invoices.index')" class="crm-more">
                                مشاهدهٔ همه <ChevronLeft :size="14" />
                            </Link>
                        </template>

                        <div v-if="recentInvoices.length" class="crm-rows">
                            <Link
                                v-for="(inv, i) in recentInvoices"
                                :key="inv.id"
                                :href="route('invoices.show', inv.id)"
                                class="crm-row"
                                :style="{ '--i': i }"
                            >
                                <span
                                    class="crm-panel__icon"
                                    style="--crm-accent: var(--gs-success); width: 36px; height: 36px; border-radius: 11px"
                                >
                                    <Receipt :size="16" />
                                </span>
                                <div class="crm-row__main">
                                    <p class="crm-row__title"><span class="crm-code">{{ inv.invoice_number }}</span></p>
                                    <p class="crm-row__sub">مبلغ کل فاکتور</p>
                                </div>
                                <div class="crm-row__meta">
                                    <span class="crm-row__num">{{ money(inv.total_amount) }}</span>
                                    <CrmStatusChip :invoice="inv" sm :icon="false" />
                                </div>
                            </Link>
                        </div>

                        <CrmEmptyState
                            v-else
                            :icon="Receipt"
                            title="فاکتوری صادر نشده"
                            desc="با ثبت اولین فروش، فاکتورهای اخیر این‌جا فهرست می‌شوند."
                        >
                            <Link :href="route('invoices.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm">
                                <Plus :size="14" /> صدور فاکتور
                            </Link>
                        </CrmEmptyState>
                    </CrmPanel>
                </section>

                <!-- نبض کارگاه -->
                <CrmPanel
                    title="نبض کارگاه"
                    desc="بار کاری جاری بر اساس درخواست‌های باز و سرویس‌های فعال"
                    :icon="Activity"
                    accent="var(--gs-accent-3)"
                    :delay="300"
                >
                    <template #actions>
                        <span class="st-chip st-chip--plain"><Zap :size="12" /> به‌روزرسانی زنده با هر بازدید</span>
                    </template>

                    <div class="crm-load">
                        <Link
                            v-for="w in workload"
                            :key="w.label"
                            :href="w.to"
                            class="crm-load__row"
                            :style="{ '--crm-accent': w.accent, '--crm-w': w.pct + '%' }"
                            style="text-decoration: none; color: inherit"
                        >
                            <div class="crm-load__top">
                                <span>{{ w.label }}</span>
                                <b>{{ faInt(w.value) }}</b>
                            </div>
                            <div class="crm-load__track"><div class="crm-load__fill" /></div>
                        </Link>
                    </div>
                </CrmPanel>

                <footer class="crm-footer">
                    <span><Gamepad2 :size="14" /> گیم‌استور — داشبورد مدیریت</span>
                    <span><Layers :size="13" /> همگام با ماژول Dashboard</span>
                </footer>
            </div>
        </div>
    </AppLayout>
</template>
