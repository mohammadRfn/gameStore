<script setup>
/**
 * صفحهٔ نگهداری کش — GameStore
 * مسیر: resources/js/Pages/CacheMaintenance/Index.vue
 * ---------------------------------------------------------------------------
 * این صفحه کاملاً به بک‌اند ماژول CacheMaintenance وصل است و از
 * Composables/useCacheMaintenanceApi برای ارتباط با endpointهای
 * GET/POST /settings/cache/* استفاده می‌کند. ساختار پاسخ‌ها دقیقاً مطابق
 * CacheMaintenanceController و CacheMaintenanceService است.
 *
 * وابستگی‌ها (از قبل در پروژه موجودند):
 *   • @/Layouts/AppLayout.vue
 *   • @/Composables/useTilt        → v-tilt / v-reveal
 *   • @/Composables/useCacheMaintenanceApi
 *   • @/Components/Settings/ToastHost.vue
 *   • @/Utils/format               → faInt
 *   • lucide-vue-next              → آیکون‌ها
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
    Gauge,
    Trash2,
    Zap,
    History,
    RefreshCw,
    HardDrive,
    Boxes,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'
import { useCacheMaintenanceApi } from '@/Composables/useCacheMaintenanceApi'

import CacheScene from '@/Components/CacheMaintenance/CacheScene.vue'
import MetricCard from '@/Components/CacheMaintenance/MetricCard.vue'
import EnvironmentCard from '@/Components/CacheMaintenance/EnvironmentCard.vue'
import RecommendationsCard from '@/Components/CacheMaintenance/RecommendationsCard.vue'
import ClearPanel from '@/Components/CacheMaintenance/ClearPanel.vue'
import OptimizePanel from '@/Components/CacheMaintenance/OptimizePanel.vue'
import RunHistory from '@/Components/CacheMaintenance/RunHistory.vue'
import RunDrawer from '@/Components/CacheMaintenance/RunDrawer.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const api = useCacheMaintenanceApi()

/* ------------------------------------------------------------------ */
/* ناوبری بخش‌ها                                                        */
/* ------------------------------------------------------------------ */
const NAV = [
    { id: 'overview', label: 'نمای کلی', icon: Gauge },
    { id: 'clear', label: 'پاکسازی', icon: Trash2 },
    { id: 'optimize', label: 'بهینه‌سازی', icon: Zap },
    { id: 'history', label: 'تاریخچه', icon: History },
]

const activeSection = ref('overview')

/* ------------------------------------------------------------------ */
/* وضعیت داده‌ها                                                        */
/* ------------------------------------------------------------------ */
const loading = ref(true)
const metrics = ref(null)
const targetsMap = ref({})
const recommendations = ref([])
const generatedAt = ref('')

const clearBusy = ref(false)
const clearResult = ref(null)

const optimizeBusy = ref(false)
const optimizeResult = ref(null)

const paginator = ref({ data: [] })
const runsLoading = ref(false)
const runFilters = ref({})

const activeRun = ref(null)

/* ------------------------------------------------------------------ */
/* توست‌ها                                                              */
/* ------------------------------------------------------------------ */
const toasts = ref([])
let toastId = 0

function pushToast(kind, msg) {
    const id = ++toastId
    toasts.value.push({ id, kind, msg })
    setTimeout(() => dismissToast(id), 5200)
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
}

/* ------------------------------------------------------------------ */
/* بارگذاری                                                             */
/* ------------------------------------------------------------------ */
async function loadOverview(silent = false) {
    if (!silent) loading.value = true
    try {
        const res = await api.overview()
        metrics.value = res.data.metrics
        targetsMap.value = res.data.targets
        recommendations.value = res.data.recommendations
        generatedAt.value = res.data.metrics.generated_at
    } catch (e) {
        pushToast('danger', e.message || 'خطا در بارگذاری وضعیت کش.')
    } finally {
        loading.value = false
    }
}

async function loadRuns(filters = {}, page = 1) {
    runsLoading.value = true
    runFilters.value = filters
    try {
        const res = await api.runs({ ...filters, page })
        paginator.value = res.data
    } catch (e) {
        pushToast('danger', e.message || 'خطا در بارگذاری تاریخچه.')
    } finally {
        runsLoading.value = false
    }
}

async function handleClear(payload) {
    clearBusy.value = true
    try {
        const res = await api.clear(payload)
        clearResult.value = res.data
        pushToast(res.success ? 'success' : 'danger', res.message)
        if (!payload.dry_run && res.success) {
            await loadOverview(true)
        }
        await loadRuns(runFilters.value, 1)
    } catch (e) {
        pushToast('danger', e.message || 'عملیات پاکسازی ناموفق بود.')
    } finally {
        clearBusy.value = false
    }
}

async function handleOptimize(payload) {
    optimizeBusy.value = true
    try {
        const res = await api.optimize(payload)
        optimizeResult.value = res.data
        pushToast(res.success ? 'success' : 'danger', res.message)
        if (!payload.dry_run && res.success) {
            await loadOverview(true)
        }
        await loadRuns(runFilters.value, 1)
    } catch (e) {
        pushToast('danger', e.message || 'عملیات بهینه‌سازی ناموفق بود.')
    } finally {
        optimizeBusy.value = false
    }
}

function openRun(run) {
    activeRun.value = run
}

/* ------------------------------------------------------------------ */
/* متریک‌های نمایشی                                                     */
/* ------------------------------------------------------------------ */
const metricCards = computed(() => {
    const m = metrics.value
    if (!m) return []
    return [
        { key: 'bootstrap', icon: '📦', label: 'Bootstrap Cache', value: faInt(m.bootstrap_cache?.files), sub: m.bootstrap_cache?.human_size, accent: 'var(--gs-gold-glow)' },
        { key: 'views', icon: '👁', label: 'Compiled Views', value: faInt(m.compiled_views?.files), sub: m.compiled_views?.human_size, accent: 'var(--gs-accent)' },
        { key: 'framework', icon: '🗃', label: 'Framework Cache', value: faInt(m.framework_cache?.files), sub: m.framework_cache?.human_size, accent: 'var(--gs-accent)' },
        { key: 'logs', icon: '📜', label: 'Logs', value: faInt(m.logs?.files), sub: m.logs?.human_size, accent: 'var(--gs-warning)' },
        { key: 'sessions', icon: '🔑', label: 'Sessions', value: faInt(m.sessions?.files), sub: m.sessions?.human_size, accent: 'var(--gs-warning)' },
        { key: 'dbcache', icon: '🧬', label: 'DB Cache Expired', value: m.database_cache?.available ? faInt(m.database_cache.expired_rows) : '—', sub: m.database_cache?.available ? `${faInt(m.database_cache.rows)} رکورد` : 'بدون جدول', accent: 'var(--gs-accent-2)' },
        { key: 'orphan', icon: '🖼', label: 'Orphan Media', value: faInt(m.orphan_media?.orphan_files), sub: m.orphan_media?.exists ? 'فایل بلااستفاده' : '—', accent: 'var(--gs-error)' },
        { key: 'backups', icon: '🗄', label: 'Backups', value: faInt(m.backups?.items), sub: m.backups?.exists ? 'بستهٔ بکاپ' : '—', accent: 'var(--gs-accent-3)' },
    ]
})

/* ------------------------------------------------------------------ */
/* اسکرول‌اسپای                                                         */
/* ------------------------------------------------------------------ */
function onScroll() {
    let current = 'overview'
    for (const item of NAV) {
        const el = document.getElementById(`cm-${item.id}`)
        if (!el) continue
        if (el.getBoundingClientRect().top <= 140) current = item.id
    }
    activeSection.value = current
}

function scrollTo(id) {
    document.getElementById(`cm-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => {
    loadOverview()
    loadRuns({}, 1)
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
})
</script>

<template>
    <AppLayout>
        <Head title="نگهداری کش" />

        <div class="st-page">
            <CacheScene />

            <div class="st-shell" style="position:relative; z-index:1">
                <!-- هدر صفحه -->
                <header class="st-hero">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap">
                            <span class="st-chip">
                                <span class="st-dot" />
                                Live
                            </span>
                            <span v-if="metrics" class="st-chip st-chip--plain">
                                درایور کش: {{ metrics.environment.cache_driver }}
                            </span>
                            <button type="button" class="cm-btn cm-btn--ghost" style="min-height:34px; padding:0.2rem 0.7rem" @click="loadOverview(true)">
                                <RefreshCw :size="14" :class="{ 'is-loading': loading }" />
                                بازبینی
                            </button>
                        </div>
                        <h1 class="st-hero__title">نگهداری <span>کش</span></h1>
                        <svg class="st-underline" viewBox="0 0 230 12" fill="none" aria-hidden="true">
                            <path d="M2 9C60 2 150 2 228 8" stroke="var(--gs-gold)" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <p class="st-hero__lead">
                            بازبینی وضعیت کش‌ها، پاکسازی هدفمند با dry-run، گرم‌سازی امن و ثبت تاریخچهٔ کامل اجراها — همه در یک‌جا.
                        </p>
                    </div>
                </header>

                <div class="st-grid">
                    <!-- ناوبری کناری -->
                    <nav class="st-nav" aria-label="بخش‌ها">
                        <div class="st-nav__list">
                            <span class="st-nav__pill" :style="{ transform: `translateY(${NAV.findIndex(n => n.id === activeSection) * 46}px)` }" />
                            <button
                                v-for="item in NAV"
                                :key="item.id"
                                type="button"
                                class="st-nav__item"
                                :class="{ 'is-active': activeSection === item.id }"
                                @click="scrollTo(item.id)"
                            >
                                <component :is="item.icon" :size="16" />
                                {{ item.label }}
                            </button>
                        </div>
                        <div class="st-nav__tip">
                            💡 <b>نکته:</b> همیشه اول «بررسی آزمایشی» را اجرا کنید؛ سپس در صورت اطمینان، «پاکسازی واقعی».
                        </div>
                    </nav>

                    <!-- ناوبری موبایل -->
                    <div class="st-navbar-mobile" style="display:none">
                        <button
                            v-for="item in NAV"
                            :key="item.id"
                            type="button"
                            class="st-nav__item"
                            :class="{ 'is-active': activeSection === item.id }"
                            @click="scrollTo(item.id)"
                        >
                            <component :is="item.icon" :size="16" />
                            {{ item.label }}
                        </button>
                    </div>

                    <!-- بخش‌ها -->
                    <div class="st-sections">
                        <!-- نمای کلی -->
                        <section id="cm-overview" class="st-section">
                            <div v-if="loading" style="padding:3rem; text-align:center; color:var(--gs-text-muted)">
                                در حال بازبینی وضعیت کش…
                            </div>
                            <template v-else>
                                <div class="cm-metrics" v-reveal="{ delay: 40 }">
                                    <MetricCard
                                        v-for="c in metricCards"
                                        :key="c.key"
                                        :icon="c.icon"
                                        :label="c.label"
                                        :value="c.value"
                                        :sub="c.sub"
                                        :accent="c.accent"
                                    />
                                </div>

                                <div class="st-grid" style="grid-template-columns:1fr 1fr; gap:1.2rem; margin-top:1.2rem">
                                    <div class="st-card a3d-holo" v-reveal="{ delay: 80 }">
                                        <div class="st-sechead">
                                            <span class="st-sechead__icon"><HardDrive :size="21" /></span>
                                            <div>
                                                <h2 class="st-sechead__title" style="font-size:1.05rem">محیط اجرا</h2>
                                                <p class="st-sechead__desc">نسخه‌ها و درایورهای فعال</p>
                                            </div>
                                        </div>
                                        <EnvironmentCard :env="metrics.environment" />
                                    </div>

                                    <div class="st-card a3d-holo" v-reveal="{ delay: 120 }">
                                        <div class="st-sechead">
                                            <span class="st-sechead__icon"><Boxes :size="21" /></span>
                                            <div>
                                                <h2 class="st-sechead__title" style="font-size:1.05rem">پیشنهادهای هوشمند</h2>
                                                <p class="st-sechead__desc">بر اساس متریک‌های فعلی</p>
                                            </div>
                                        </div>
                                        <RecommendationsCard :items="recommendations" />
                                    </div>
                                </div>
                            </template>
                        </section>

                        <!-- پاکسازی -->
                        <section id="cm-clear" class="st-section" v-reveal>
                            <ClearPanel
                                :targets="targetsMap"
                                :busy="clearBusy"
                                :result="clearResult"
                                @clear="handleClear"
                            />
                        </section>

                        <!-- بهینه‌سازی -->
                        <section id="cm-optimize" class="st-section" v-reveal>
                            <OptimizePanel
                                :busy="optimizeBusy"
                                :result="optimizeResult"
                                @optimize="handleOptimize"
                            />
                        </section>

                        <!-- تاریخچه -->
                        <section id="cm-history" class="st-section" v-reveal>
                            <RunHistory
                                :paginator="paginator"
                                :loading="runsLoading"
                                @filter="(f) => loadRuns(f, 1)"
                                @page="(p) => loadRuns(runFilters, p)"
                                @select="openRun"
                            />
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <!-- کشوی جزئیات -->
        <RunDrawer :run="activeRun" @close="activeRun = null" />

        <!-- توست‌ها -->
        <ToastHost :toasts="toasts" @close="dismissToast" />
    </AppLayout>
</template>
