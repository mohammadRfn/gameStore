<script setup>
/**
 * CacheCenter — مرکز فرمان کش
 * مسیر: resources/js/Components/Settings/CacheCenter.vue
 *
 * شامل: گیج حلقه‌ای انیمیشنی، تفکیک دسته‌های کش، شبیه‌سازی پاک‌سازی با
 * انفجار ذرات، پاک‌سازی خودکار، بازهٔ زمان‌بندی و سقف کش.
 *
 * برای اتصال به بک‌اند لاراول کافی است متد runFullClean را با یک
 * درخواست axios/Inertia جایگزین کنی (نمونه در انتهای فایل کامنت شده).
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import {
    Check,
    Clock,
    Download,
    FileImage,
    FileText,
    Info,
    Layers,
    RefreshCw,
    Trash2,
    Zap,
} from 'lucide-vue-next'
import { faInt, percent } from '@/Utils/format'
import GsRow from './GsRow.vue'
import GsSelect from './GsSelect.vue'
import GsSlider from './GsSlider.vue'
import GsToggle from './GsToggle.vue'

/* -------------------------------------------------------------------------
 * props / emits  — همهٔ تنظیمات با v-model از صفحهٔ والد می‌آیند
 * ------------------------------------------------------------------------- */
const props = defineProps({
    autoClean: { type: Boolean, default: true },
    cleanInterval: { type: String, default: '30' },
    cacheCap: { type: Number, default: 10 },
    keepDownloads: { type: Boolean, default: true },
    /** ظرفیت کل دیسک اختصاص‌یافته (گیگابایت) */
    total: { type: Number, default: 12 },
})

const emit = defineEmits([
    'update:autoClean',
    'update:cleanInterval',
    'update:cacheCap',
    'update:keepDownloads',
    'toast',
])

/* -------------------------------------------------------------------------
 * دسته‌های کش
 * ------------------------------------------------------------------------- */
const categories = reactive([
    { id: 'dl', label: 'کش دانلودها', icon: Download, color: 'var(--gs-gold)', size: 3.4, rest: 0.15 },
    { id: 'sh', label: 'شیدر و تکسچر', icon: Layers, color: 'var(--gs-accent)', size: 2.1, rest: 0.08 },
    { id: 'img', label: 'تصاویر و آیکون‌ها', icon: FileImage, color: 'var(--gs-accent-2)', size: 1.7, rest: 0.12 },
    { id: 'log', label: 'لاگ و دادهٔ موقت', icon: FileText, color: 'var(--gs-accent-3)', size: 1.2, rest: 0.05 },
])

const used = computed(() =>
    categories.reduce((sum, c) => sum + c.size, 0),
)

/* -------------------------------------------------------------------------
 * شمارندهٔ نرم (count-up) برای عدد وسط حلقه
 * ------------------------------------------------------------------------- */
const animatedUsed = ref(used.value)
let countFrame = null

watch(used, (target) => {
    const from = animatedUsed.value
    const start = performance.now()
    const duration = 900

    if (countFrame) cancelAnimationFrame(countFrame)

    const tick = (now) => {
        const t = Math.min(1, (now - start) / duration)
        const eased = 1 - (1 - t) ** 3
        animatedUsed.value = from + (target - from) * eased
        if (t < 1) countFrame = requestAnimationFrame(tick)
    }

    countFrame = requestAnimationFrame(tick)
})

/* -------------------------------------------------------------------------
 * حلقهٔ گیج
 * ------------------------------------------------------------------------- */
const RADIUS = 88
const CIRCUM = 2 * Math.PI * RADIUS

const fraction = computed(() =>
    Math.min(1, Math.max(0, animatedUsed.value / props.total)),
)

const dashOffset = computed(() => CIRCUM * (1 - fraction.value))

/** یک رقم اعشار با ارقام فارسی */
function gb(value) {
    return faInt(Math.floor(value)) + '٫' + faInt(Math.round((value % 1) * 10))
}

/* -------------------------------------------------------------------------
 * شبیه‌سازی پاک‌سازی
 * ------------------------------------------------------------------------- */
const phase = ref('idle') // idle | running | done
const progress = ref(0)
const freed = ref(0)
const lastClean = ref('۳ روز پیش')
const burst = ref([])

let progressTimer = null
let resetTimer = null
let burstTimer = null

const BURST_COLORS = [
    'var(--gs-gold)',
    'var(--gs-gold-light)',
    'var(--gs-accent)',
    'var(--gs-accent-2)',
    'var(--gs-accent-3)',
]

function spawnBurst() {
    burst.value = Array.from({ length: 20 }, (_, i) => ({
        id: `${Date.now()}-${i}`,
        dx: `${((Math.random() - 0.5) * 320).toFixed(0)}px`,
        dy: `${((Math.random() - 0.62) * 300).toFixed(0)}px`,
        color: BURST_COLORS[i % BURST_COLORS.length],
        size: `${(4 + Math.random() * 5).toFixed(1)}px`,
    }))

    clearTimeout(burstTimer)
    burstTimer = setTimeout(() => (burst.value = []), 950)
}

function finishClean() {
    categories.forEach((c) => {
        // کش دانلودها در صورت فعال بودن نگه‌داری، دست‌نخورده می‌ماند
        if (c.id === 'dl' && props.keepDownloads) return
        c.size = c.rest
    })

    spawnBurst()
    lastClean.value = 'همین حالا'
    phase.value = 'done'
    emit('toast', { kind: 'success', msg: `${gb(freed.value)} گیگابایت فضا آزاد شد` })

    resetTimer = setTimeout(() => {
        phase.value = 'idle'
        progress.value = 0
    }, 1700)
}

function runFullClean() {
    if (phase.value === 'running') return

    // مقدار قابل آزادسازی را قبل از شروع محاسبه می‌کنیم
    freed.value = categories.reduce((sum, c) => {
        if (c.id === 'dl' && props.keepDownloads) return sum
        return sum + Math.max(0, c.size - c.rest)
    }, 0)

    if (freed.value <= 0.05) {
        emit('toast', { kind: 'info', msg: 'کش از قبل تمیز است' })
        return
    }

    phase.value = 'running'
    progress.value = 0

    progressTimer = setInterval(() => {
        progress.value = Math.min(100, progress.value + 1.6 + Math.random() * 3.4)

        if (progress.value >= 100) {
            clearInterval(progressTimer)
            setTimeout(finishClean, 120)
        }
    }, 42)

    /* --- اتصال واقعی به بک‌اند (نمونه) ------------------------------------
     * import axios from 'axios'
     * axios.post(route('settings.cache.clear'), { keep_downloads: props.keepDownloads })
     *   .then(({ data }) => { freed.value = data.freed_gb; finishClean() })
     *   .catch(() => emit('toast', { kind: 'danger', msg: 'پاک‌سازی ناموفق بود' }))
     * -------------------------------------------------------------------- */
}

function runQuickClean() {
    if (phase.value === 'running') return

    const log = categories.find((c) => c.id === 'log')
    const amount = Math.max(0, log.size - log.rest)

    if (amount <= 0.02) {
        emit('toast', { kind: 'info', msg: 'فایل موقتی برای پاک کردن نیست' })
        return
    }

    log.size = log.rest
    lastClean.value = 'همین حالا'
    emit('toast', { kind: 'info', msg: `فایل‌های موقت پاک شد — ${gb(amount)} گیگابایت` })
}

onBeforeUnmount(() => {
    clearInterval(progressTimer)
    clearTimeout(resetTimer)
    clearTimeout(burstTimer)
    if (countFrame) cancelAnimationFrame(countFrame)
})

/* -------------------------------------------------------------------------
 * هشدار سقف
 * ------------------------------------------------------------------------- */
const capExceeded = computed(() => props.cacheCap < used.value)

const INTERVALS = [
    { value: '7', label: 'هر ۷ روز' },
    { value: '30', label: 'هر ۳۰ روز' },
    { value: '90', label: 'هر ۹۰ روز' },
]
</script>

<template>
    <div v-tilt="{ max: 4, lift: 8, scale: 1.004 }" class="a3d-holo a3d-aura st-cache">
        <!-- سربرگ -->
        <div class="st-cache__head a3d-z-20">
            <div>
                <h3 class="st-cache__title">مرکز فرمان کش</h3>
                <p class="st-sechead__desc">
                    مصرف فضا را زنده ببین و با یک کلیک آزادش کن
                </p>
            </div>

            <span class="st-chip st-chip--live">
                <span class="st-dot" />
                LIVE MONITOR
            </span>
        </div>

        <!-- گیج + تفکیک -->
        <div class="st-cache__body a3d-z-30">
            <div class="st-ring">
                <svg viewBox="0 0 214 214">
                    <defs>
                        <linearGradient id="stCacheGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="var(--gs-gold-light)" />
                            <stop offset="55%" stop-color="var(--gs-gold)" />
                            <stop offset="100%" stop-color="var(--gs-gold-dark)" />
                        </linearGradient>
                    </defs>

                    <circle
                        cx="107"
                        cy="107"
                        :r="RADIUS"
                        fill="none"
                        stroke="var(--gs-bg-elevated)"
                        stroke-width="15"
                    />
                    <circle
                        cx="107"
                        cy="107"
                        :r="RADIUS"
                        fill="none"
                        stroke="url(#stCacheGrad)"
                        stroke-width="15"
                        stroke-linecap="round"
                        :stroke-dasharray="CIRCUM"
                        :stroke-dashoffset="dashOffset"
                        style="filter: drop-shadow(0 0 9px var(--gs-gold-glow))"
                    />
                </svg>

                <div class="st-ring__center">
                    <div>
                        <p class="st-ring__pct">{{ percent(fraction * 100) }}</p>
                        <p class="st-ring__sub">
                            {{ gb(animatedUsed) }} از {{ faInt(total) }} گیگابایت
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <div v-for="c in categories" :key="c.id" class="st-cat">
                    <span class="st-cat__icon" :style="{ color: c.color }">
                        <component :is="c.icon" :size="17" />
                    </span>

                    <div style="flex: 1; min-width: 0">
                        <div class="st-cat__top">
                            <p class="st-cat__label">{{ c.label }}</p>
                            <p class="st-cat__size">{{ gb(c.size) }} GB</p>
                        </div>

                        <div class="st-cat__track">
                            <div
                                class="st-cat__fill"
                                :style="{
                                    width: `${Math.min(100, (c.size / 4) * 100)}%`,
                                    background: `linear-gradient(90deg, ${c.color}, color-mix(in srgb, ${c.color} 55%, transparent))`,
                                }"
                            />
                        </div>
                    </div>
                </div>

                <p class="st-cache__last">
                    <Clock :size="13" />
                    آخرین پاک‌سازی: {{ lastClean }}
                </p>
            </div>
        </div>

        <!-- دکمه‌های عملیات -->
        <div class="st-cache__actions a3d-z-20">
            <div class="st-clean-wrap">
                <button
                    type="button"
                    class="a3d-btn st-clean"
                    :class="phase === 'done' ? 'st-clean--done' : 'a3d-btn--gold'"
                    :disabled="phase === 'running'"
                    @click="runFullClean"
                >
                    <span
                        v-if="phase === 'running'"
                        class="st-clean__progress"
                        :style="{ width: `${100 - progress}%` }"
                    />

                    <span class="st-clean__label">
                        <template v-if="phase === 'idle'">
                            <Trash2 :size="17" />
                            پاک‌سازی کامل کش
                        </template>

                        <template v-else-if="phase === 'running'">
                            <RefreshCw :size="17" class="st-spinner" />
                            در حال پاک‌سازی… {{ faInt(progress) }}٪
                        </template>

                        <template v-else>
                            <Check :size="17" />
                            {{ gb(freed) }} گیگابایت آزاد شد
                        </template>
                    </span>
                </button>

                <!-- انفجار ذرات -->
                <span
                    v-for="p in burst"
                    :key="p.id"
                    class="st-burst"
                    :style="{
                        width: p.size,
                        height: p.size,
                        background: p.color,
                        boxShadow: `0 0 9px ${p.color}`,
                        '--st-dx': p.dx,
                        '--st-dy': p.dy,
                    }"
                />
            </div>

            <button
                type="button"
                class="a3d-btn"
                :disabled="phase === 'running'"
                @click="runQuickClean"
            >
                <Zap :size="16" />
                پاک‌سازی سریع موقت‌ها
            </button>
        </div>

        <!-- تنظیمات کش -->
        <div class="st-cache__settings a3d-z-10">
            <div>
                <GsRow
                    title="پاک‌سازی خودکار"
                    desc="کش‌های قدیمی بدون دخالت تو حذف می‌شوند"
                >
                    <GsToggle
                        :model-value="autoClean"
                        accent="green"
                        @update:model-value="emit('update:autoClean', $event)"
                    />
                </GsRow>

                <GsRow
                    title="بازهٔ پاک‌سازی"
                    desc="زمان‌بندی اجرای پاک‌سازی خودکار"
                    :disabled="!autoClean"
                >
                    <GsSelect
                        :model-value="cleanInterval"
                        :options="INTERVALS"
                        @update:model-value="emit('update:cleanInterval', $event)"
                    />
                </GsRow>
            </div>

            <div>
                <GsRow
                    title="نگه‌داری کش دانلودها"
                    desc="فایل‌های نصب برای نصب دوبارهٔ سریع‌تر باقی بمانند"
                >
                    <GsToggle
                        :model-value="keepDownloads"
                        @update:model-value="emit('update:keepDownloads', $event)"
                    />
                </GsRow>

                <div class="st-row" style="flex-direction: column; align-items: stretch">
                    <div class="st-cap__head">
                        <p class="st-row__title">سقف کش</p>
                        <p class="st-row__desc">حداکثر فضای مجاز روی دیسک</p>
                    </div>

                    <GsSlider
                        :model-value="cacheCap"
                        :min="2"
                        :max="16"
                        :formatter="(v) => `${faInt(v)} GB`"
                        @update:model-value="emit('update:cacheCap', $event)"
                    />
                </div>
            </div>
        </div>

        <Transition name="st-alert-fade">
            <p v-if="capExceeded" class="st-alert">
                <Info :size="17" style="flex-shrink: 0; margin-top: 2px" />
                <span>
                    سقف تعیین‌شده کمتر از مصرف فعلی ({{ gb(used) }} گیگابایت) است —
                    پاک‌سازی خودکار در اولین فرصت اجرا خواهد شد.
                </span>
            </p>
        </Transition>
    </div>
</template>

<style scoped>
.st-cache__last {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.5rem 0;
    font-size: 0.72rem;
    color: var(--gs-text-muted);
}

.st-cache__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    margin-top: 1.8rem;
}

.st-clean-wrap {
    position: relative;
    flex: 1;
    min-width: 240px;
}

.st-clean {
    width: 100%;
    padding-block: 0.85rem;
    font-size: 0.87rem;
}

.st-clean--done {
    background: linear-gradient(135deg, var(--gs-success), #1f9d63);
    color: #08150f;
    border-color: transparent;
}

.st-spinner {
    animation: st-spin 0.9s linear infinite;
}

.st-cache__settings {
    display: grid;
    gap: 0 2.5rem;
    margin-top: 1.9rem;
    padding-top: 1.4rem;
    border-top: 1px solid var(--gs-border);
}

@media (min-width: 900px) {
    .st-cache__settings {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.st-cap__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.6rem;
}

.st-alert-fade-enter-active,
.st-alert-fade-leave-active {
    transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.st-alert-fade-enter-from,
.st-alert-fade-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
