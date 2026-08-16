<script setup>
/**
 * صفحهٔ تنظیمات گیم‌استور
 * مسیر: resources/js/Pages/Settings/Index.vue
 *
 * وابستگی‌ها (همه از قبل در پروژه موجودند):
 *   • @/Composables/useTilt  →  v-tilt / v-reveal
 *   • @/Utils/format         →  fa / faInt / percent
 *   • lucide-vue-next        →  آیکون‌ها
 *   • resources/css/settings-3d.css  ← حتماً در app.css ایمپورت شود
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
    Bell,
    Check,
    Gamepad2,
    Globe,
    HardDrive,
    Database,
    LogOut,
    Monitor,
    RefreshCw,
    Settings as SettingsIcon,
    Shield,
    SlidersHorizontal,
    Sparkles,
    Volume2,
    Wifi,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'

import SettingsScene from '@/Components/Settings/SettingsScene.vue'
import GearsCluster from '@/Components/Settings/GearsCluster.vue'
import GsSectionHead from '@/Components/Settings/GsSectionHead.vue'
import GsRow from '@/Components/Settings/GsRow.vue'
import GsToggle from '@/Components/Settings/GsToggle.vue'
import GsSlider from '@/Components/Settings/GsSlider.vue'
import GsSegmented from '@/Components/Settings/GsSegmented.vue'
import GsSelect from '@/Components/Settings/GsSelect.vue'
import GfxPreview from '@/Components/Settings/GfxPreview.vue'
import CacheCenter from '@/Components/Settings/CacheCenter.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

/* =========================================================================
 * ۱) props — می‌توانی مقادیر اولیه را از کنترلر لاراول بفرستی
 * ========================================================================= */
const props = defineProps({
    settings: { type: Object, default: () => ({}) },
})

const DEFAULTS = {
    lang: 'fa',
    theme: 'dark',
    region: 'me',
    reduceMotion: false,

    quality: 'high',
    fps: 144,
    vsync: true,
    lowPower: false,

    autoClean: true,
    cleanInterval: '30',
    cacheCap: 10,
    keepDownloads: true,

    volume: 72,
    ambience: true,
    menuMusic: true,
    output: 'stereo',

    notifDiscounts: true,
    notifUpdates: true,
    notifFriends: false,
    digest: 'weekly',

    onlineStatus: true,
    telemetry: false,
    ghostMode: false,
}

const form = reactive({ ...DEFAULTS, ...props.settings })

/** عکس فوری از وضعیت ذخیره‌شده برای تشخیص تغییرات */
const baseline = ref(JSON.parse(JSON.stringify(form)))

const dirtyKeys = computed(() =>
    Object.keys(form).filter((k) => form[k] !== baseline.value[k]),
)

const dirtyCount = computed(() => dirtyKeys.value.length)

/* =========================================================================
 * ۲) توست‌ها
 * ========================================================================= */
const toasts = ref([])
const toastTimers = new Map()

function pushToast(kind, msg) {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`
    toasts.value = [...toasts.value.slice(-2), { id, kind, msg }]

    toastTimers.set(
        id,
        setTimeout(() => dismissToast(id), 3600),
    )
}

function dismissToast(id) {
    clearTimeout(toastTimers.get(id))
    toastTimers.delete(id)
    toasts.value = toasts.value.filter((t) => t.id !== id)
}

/** رویداد toast از CacheCenter */
function onCacheToast({ kind, msg }) {
    pushToast(kind, msg)
}

/* =========================================================================
 * ۳) ذخیره / بازنشانی
 * ========================================================================= */
const saving = ref(false)
let saveTimer = null

function save() {
    if (saving.value) return
    saving.value = true

    /* --- اتصال واقعی به لاراول -------------------------------------------
     * import { router } from '@inertiajs/vue3'
     * router.put(route('settings.update'), { ...form }, {
     *     preserveScroll: true,
     *     onSuccess: () => { baseline.value = JSON.parse(JSON.stringify(form)) },
     *     onFinish:  () => { saving.value = false },
     * })
     * -------------------------------------------------------------------- */

    saveTimer = setTimeout(() => {
        baseline.value = JSON.parse(JSON.stringify(form))
        saving.value = false
        pushToast('success', 'تنظیمات با موفقیت ذخیره شد')
    }, 900)
}

function resetChanges() {
    Object.assign(form, JSON.parse(JSON.stringify(baseline.value)))
    pushToast('info', 'تغییرات ذخیره‌نشده بازنشانی شد')
}

/* =========================================================================
 * ۴) ناوبری + Scroll Spy
 * ========================================================================= */
const SECTIONS = [
    { id: 'general', label: 'عمومی', icon: SettingsIcon },
    { id: 'graphics', label: 'گرافیک و عملکرد', icon: SlidersHorizontal },
    { id: 'cache', label: 'کش و فضای ذخیره', icon: Database },
    { id: 'audio', label: 'صدا', icon: Volume2 },
    { id: 'notifications', label: 'اعلان‌ها', icon: Bell },
    { id: 'privacy', label: 'حریم خصوصی', icon: Shield },
]

const activeSection = ref('general')

const activeIndex = computed(() => {
    const i = SECTIONS.findIndex((s) => s.id === activeSection.value)
    return i < 0 ? 0 : i
})

/** قرص ناوبری با ارتفاع ثابت ردیف (۴۶px + ۲px گپ) جابه‌جا می‌شود */
const navPillStyle = computed(() => ({
    transform: `translateY(calc(${activeIndex.value} * (var(--st-nav-h) + 2px)))`,
}))

let spy = null

onMounted(() => {
    spy = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) activeSection.value = e.target.id
            })
        },
        { rootMargin: '-28% 0px -62% 0px' },
    )

    SECTIONS.forEach((s) => {
        const el = document.getElementById(s.id)
        if (el) spy.observe(el)
    })

    pingTimer = setInterval(() => {
        ping.value = Math.round(22 + Math.random() * 18)
    }, 2400)
})

onBeforeUnmount(() => {
    spy?.disconnect()
    clearTimeout(saveTimer)
    clearInterval(pingTimer)
    toastTimers.forEach((t) => clearTimeout(t))
})

function goTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

/* =========================================================================
 * ۵) پینگ زنده
 * ========================================================================= */
const ping = ref(28)
let pingTimer = null

/* =========================================================================
 * ۶) گزینه‌های ثابت
 * ========================================================================= */
const LANGS = [
    { value: 'fa', label: 'فارسی' },
    { value: 'en', label: 'English' },
    { value: 'ar', label: 'العربية' },
]

const REGIONS = [
    { value: 'me', label: 'خاورمیانه' },
    { value: 'eu', label: 'اروپا' },
    { value: 'asia', label: 'آسیای شرقی' },
    { value: 'na', label: 'آمریکای شمالی' },
]

const THEMES = [
    { value: 'dark', label: 'تیره', swatch: '#14141f' },
    { value: 'light', label: 'روشن', swatch: '#f5f0e6' },
    { value: 'amoled', label: 'AMOLED', swatch: '#000000' },
]

const QUALITIES = [
    { value: 'low', label: 'پایین' },
    { value: 'mid', label: 'متوسط' },
    { value: 'high', label: 'بالا' },
    { value: 'ultra', label: 'اولترا' },
]

const OUTPUTS = [
    { value: 'stereo', label: 'استریو' },
    { value: 'surround', label: 'محیطی ۵٫۱' },
    { value: 'headphone', label: 'هدفون' },
]

const DIGESTS = [
    { value: 'instant', label: 'لحظه‌ای' },
    { value: 'daily', label: 'روزانه' },
    { value: 'weekly', label: 'هفتگی' },
]

/** ارتفاع میله‌های اکولایزر */
const EQ_BARS = [9, 15, 11, 19, 13]

const effectiveFps = computed(() => (form.vsync ? Math.min(form.fps, 60) : form.fps))
</script>

<template>
    <Head title="تنظیمات" />

    <AppLayout>
        <div class="st-page">
            <SettingsScene />

            <!-- ================= سربرگ ================= -->
            <header class="st-shell">
                <div class="st-hero">
                    <div style="min-width: 0">
                        <div class="st-hero__chips">
                            <span class="st-chip">
                                <SettingsIcon :size="13" />
                                پنل کنترل فروشگاه
                            </span>
                            <span class="st-chip st-chip--plain">v2.4.1</span>
                        </div>

                        <h1 class="st-hero__title">
                            <span>تنظیمات</span>

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
                            تجربهٔ فروشگاه را دقیقاً همان‌طور که می‌خواهی کوک کن — از گرافیک
                            و عملکرد تا کش و حافظه.
                        </p>

                        <div class="st-hero__stats">
                            <span class="st-stat">
                                <Wifi :size="15" />
                                پینگ
                                <b>{{ faInt(ping) }} ms</b>
                            </span>
                            <span class="st-stat">
                                <HardDrive :size="15" />
                                فضای آزاد
                                <b>۴۱٫۶ GB</b>
                            </span>
                            <span class="st-stat">
                                <Monitor :size="15" />
                                سرور
                                <b style="color: var(--gs-success)">
                                    <span class="st-dot" style="display: inline-block" />
                                    آنلاین
                                </b>
                            </span>
                        </div>
                    </div>

                    <GearsCluster />
                </div>
            </header>

            <!-- ================= بدنه ================= -->
            <div class="st-shell st-body">
                <div class="st-grid">
                    <!-- --- ناوبری دسکتاپ --- -->
                    <nav class="st-nav st-nav--desktop" aria-label="بخش‌های تنظیمات">
                        <div class="st-nav__list">
                            <span
                                class="st-nav__pill"
                                :style="navPillStyle"
                                aria-hidden="true"
                            />

                            <button
                                v-for="s in SECTIONS"
                                :key="s.id"
                                type="button"
                                class="st-nav__item"
                                :class="{ 'is-active': activeSection === s.id }"
                                @click="goTo(s.id)"
                            >
                                <component :is="s.icon" :size="17" />
                                {{ s.label }}
                            </button>
                        </div>

                        <div class="st-nav__tip">
                            <span
                                style="
                                    display: flex;
                                    align-items: center;
                                    gap: 0.4rem;
                                    color: var(--gs-gold);
                                    margin-bottom: 0.35rem;
                                "
                            >
                                <Sparkles :size="14" />
                                نکته
                            </span>
                            پاک‌سازی ماهانهٔ کش می‌تواند تا <b>۲۰٪</b> زمان بارگذاری
                            فروشگاه را کم کند.
                        </div>
                    </nav>

                    <!-- --- محتوا --- -->
                    <div style="min-width: 0">
                        <!-- ناوبری موبایل -->
                        <div class="st-navbar-mobile">
                            <button
                                v-for="s in SECTIONS"
                                :key="s.id"
                                type="button"
                                class="st-nav__item"
                                :class="{ 'is-active': activeSection === s.id }"
                                @click="goTo(s.id)"
                            >
                                <component :is="s.icon" :size="15" />
                                {{ s.label }}
                            </button>
                        </div>

                        <div class="st-sections">
                            <!-- ========== عمومی ========== -->
                            <section id="general" class="st-section">
                                <div v-reveal="{ delay: 0 }">
                                    <GsSectionHead
                                        :icon="Globe"
                                        title="عمومی"
                                        desc="زبان، پوسته و رفتار کلی فروشگاه"
                                    />
                                </div>

                                <div
                                    v-reveal="{ delay: 90 }"
                                    v-tilt="{ max: 3, lift: 6, scale: 1.003 }"
                                    class="a3d-holo st-card"
                                >
                                    <GsRow
                                        title="زبان رابط کاربری"
                                        desc="زبان منوها، قیمت‌ها و اعلان‌ها"
                                    >
                                        <GsSelect v-model="form.lang" :options="LANGS" />
                                    </GsRow>

                                    <GsRow title="پوسته" desc="ظاهر کلی فروشگاه">
                                        <GsSegmented
                                            v-model="form.theme"
                                            :options="THEMES"
                                            style="min-width: 262px"
                                        />
                                    </GsRow>

                                    <GsRow
                                        title="منطقهٔ سرور"
                                        desc="نزدیک‌ترین منطقه برای کاهش تأخیر"
                                    >
                                        <GsSelect v-model="form.region" :options="REGIONS" />
                                    </GsRow>

                                    <GsRow
                                        title="کاهش حرکت"
                                        desc="انیمیشن‌های رابط سبک‌تر می‌شوند"
                                    >
                                        <GsToggle
                                            v-model="form.reduceMotion"
                                            accent="green"
                                        />
                                    </GsRow>

                                    <GsRow
                                        title="نسخهٔ کلاینت"
                                        desc="آخرین نسخهٔ نصب‌شده روی دستگاه تو"
                                    >
                                        <div class="st-version">
                                            <span class="st-chip st-chip--plain">
                                                v2.4.1 · build 8812
                                            </span>
                                            <button
                                                type="button"
                                                class="a3d-btn a3d-btn--sm"
                                                @click="
                                                    pushToast(
                                                        'success',
                                                        'شما روی آخرین نسخه هستید',
                                                    )
                                                "
                                            >
                                                <RefreshCw :size="14" />
                                                بررسی آپدیت
                                            </button>
                                        </div>
                                    </GsRow>
                                </div>
                            </section>

                            <!-- ========== گرافیک ========== -->
                            <section id="graphics" class="st-section">
                                <div v-reveal="{ delay: 0 }">
                                    <GsSectionHead
                                        :icon="SlidersHorizontal"
                                        title="گرافیک و عملکرد"
                                        desc="تعادل بین زیبایی و روانی — نتیجه را زنده در مکعب ببین"
                                    />
                                </div>

                                <div v-reveal="{ delay: 90 }" class="st-gfx-grid">
                                    <div
                                        v-tilt="{ max: 3, lift: 6, scale: 1.003 }"
                                        class="a3d-holo st-card"
                                    >
                                        <GsRow
                                            title="کیفیت بافت‌ها"
                                            desc="جزئیات بصری بازی‌ها و کاورها"
                                        >
                                            <GsSegmented
                                                v-model="form.quality"
                                                :options="QUALITIES"
                                                style="min-width: 300px"
                                            />
                                        </GsRow>

                                        <div class="st-row" style="flex-direction: column; align-items: stretch">
                                            <div class="st-cap__head">
                                                <div>
                                                    <p class="st-row__title">نرخ فریم هدف</p>
                                                    <p class="st-row__desc">
                                                        سقف فریم بر ثانیه برای پیش‌نمایش‌ها
                                                    </p>
                                                </div>
                                                <span class="st-chip st-chip--live">
                                                    {{ faInt(effectiveFps) }} FPS
                                                </span>
                                            </div>

                                            <GsSlider
                                                v-model="form.fps"
                                                :min="30"
                                                :max="240"
                                                :step="6"
                                                :formatter="(v) => `${faInt(v)} FPS`"
                                            />
                                        </div>

                                        <GsRow
                                            title="V-Sync"
                                            desc="همگام‌سازی فریم با نمایشگر و حذف پرش تصویر"
                                        >
                                            <GsToggle v-model="form.vsync" accent="green" />
                                        </GsRow>

                                        <GsRow
                                            title="حالت کم‌مصرف"
                                            desc="کاهش مصرف باتری و دمای پردازندهٔ گرافیکی"
                                        >
                                            <GsToggle v-model="form.lowPower" />
                                        </GsRow>
                                    </div>

                                    <div v-tilt="{ max: 7, lift: 14, scale: 1.01 }">
                                        <GfxPreview
                                            :quality="form.quality"
                                            :fps="form.fps"
                                            :vsync="form.vsync"
                                        />
                                    </div>
                                </div>
                            </section>

                            <!-- ========== کش ========== -->
                            <section id="cache" class="st-section">
                                <div v-reveal="{ delay: 0 }">
                                    <GsSectionHead
                                        :icon="Database"
                                        title="کش و فضای ذخیره"
                                        desc="هر گیگابایت را حساب‌شده خرج کن — پاک‌سازی، سقف و زمان‌بندی"
                                    />
                                </div>

                                <div v-reveal="{ delay: 90 }">
                                    <CacheCenter
                                        v-model:auto-clean="form.autoClean"
                                        v-model:clean-interval="form.cleanInterval"
                                        v-model:cache-cap="form.cacheCap"
                                        v-model:keep-downloads="form.keepDownloads"
                                        @toast="onCacheToast"
                                    />
                                </div>
                            </section>

                            <!-- ========== صدا ========== -->
                            <section id="audio" class="st-section">
                                <div v-reveal="{ delay: 0 }">
                                    <GsSectionHead
                                        :icon="Volume2"
                                        title="صدا"
                                        desc="بلندی، موسیقی منو و خروجی صوتی"
                                    />
                                </div>

                                <div
                                    v-reveal="{ delay: 90 }"
                                    v-tilt="{ max: 3, lift: 6, scale: 1.003 }"
                                    class="a3d-holo st-card"
                                >
                                    <div class="st-row" style="flex-direction: column; align-items: stretch">
                                        <div class="st-cap__head">
                                            <div class="st-audio__head">
                                                <p class="st-row__title">بلندی صدای اصلی</p>

                                                <div
                                                    class="st-eq"
                                                    :class="{ 'is-muted': form.volume === 0 }"
                                                >
                                                    <span
                                                        v-for="(h, i) in EQ_BARS"
                                                        :key="i"
                                                        :style="{
                                                            height: `${h}px`,
                                                            animationDuration: `${0.5 + i * 0.13}s`,
                                                            animationDelay: `${i * 0.07}s`,
                                                        }"
                                                    />
                                                </div>
                                            </div>

                                            <span class="st-chip">
                                                {{ faInt(form.volume) }}٪
                                            </span>
                                        </div>

                                        <GsSlider
                                            v-model="form.volume"
                                            :min="0"
                                            :max="100"
                                            :formatter="(v) => `${faInt(v)}٪`"
                                        />
                                    </div>

                                    <GsRow
                                        title="صدای محیطی فروشگاه"
                                        desc="همهمهٔ ملایم هنگام مرور بازی‌ها"
                                    >
                                        <GsToggle v-model="form.ambience" accent="green" />
                                    </GsRow>

                                    <GsRow
                                        title="موسیقی منو"
                                        desc="پخش موسیقی هنگام حضور در منوها"
                                    >
                                        <GsToggle v-model="form.menuMusic" />
                                    </GsRow>

                                    <GsRow title="خروجی صدا" desc="پیکربندی بلندگوها">
                                        <GsSelect v-model="form.output" :options="OUTPUTS" />
                                    </GsRow>
                                </div>
                            </section>

                            <!-- ========== اعلان‌ها ========== -->
                            <section id="notifications" class="st-section">
                                <div v-reveal="{ delay: 0 }">
                                    <GsSectionHead
                                        :icon="Bell"
                                        title="اعلان‌ها"
                                        desc="فقط چیزهایی که واقعاً مهم‌اند خبرت کنند"
                                    />
                                </div>

                                <div
                                    v-reveal="{ delay: 90 }"
                                    v-tilt="{ max: 3, lift: 6, scale: 1.003 }"
                                    class="a3d-holo st-card"
                                >
                                    <GsRow
                                        title="تخفیف‌ها و پیشنهادهای ویژه"
                                        desc="خبر فوری وقتی بازی موردعلاقه‌ات ارزان می‌شود"
                                    >
                                        <GsToggle v-model="form.notifDiscounts" />
                                    </GsRow>

                                    <GsRow
                                        title="به‌روزرسانی بازی‌ها"
                                        desc="اعلان هنگام انتشار پچ و آپدیت"
                                    >
                                        <GsToggle
                                            v-model="form.notifUpdates"
                                            accent="green"
                                        />
                                    </GsRow>

                                    <GsRow
                                        title="پیام‌های دوستان"
                                        desc="درخواست دوستی و پیام‌های گروهی"
                                    >
                                        <GsToggle v-model="form.notifFriends" />
                                    </GsRow>

                                    <GsRow
                                        title="خلاصهٔ ایمیلی"
                                        desc="چند وقت یک‌بار ایمیل بزنیم؟"
                                    >
                                        <GsSegmented
                                            v-model="form.digest"
                                            :options="DIGESTS"
                                            style="min-width: 250px"
                                        />
                                    </GsRow>
                                </div>
                            </section>

                            <!-- ========== حریم خصوصی ========== -->
                            <section id="privacy" class="st-section">
                                <div v-reveal="{ delay: 0 }">
                                    <GsSectionHead
                                        :icon="Shield"
                                        title="حریم خصوصی"
                                        desc="دیده‌بانیِ آنچه دیگران از تو می‌بینند"
                                    />
                                </div>

                                <div
                                    v-reveal="{ delay: 90 }"
                                    v-tilt="{ max: 3, lift: 6, scale: 1.003 }"
                                    class="a3d-holo st-card"
                                >
                                    <GsRow
                                        title="نمایش وضعیت آنلاین"
                                        desc="دوستانت ببینند کی آنلاینی"
                                    >
                                        <GsToggle v-model="form.onlineStatus" />
                                    </GsRow>

                                    <GsRow
                                        title="اشتراک داده‌های تشخیصی"
                                        desc="گزارش ناشناس خطاها برای بهبود فروشگاه"
                                    >
                                        <GsToggle v-model="form.telemetry" accent="green" />
                                    </GsRow>

                                    <GsRow
                                        title="حالت روح"
                                        desc="آفلاین به‌نظر برس، حتی وقتی آنلاینی"
                                    >
                                        <GsToggle v-model="form.ghostMode" />
                                    </GsRow>

                                    <div class="st-danger">
                                        <div>
                                            <p class="st-danger__title">نشست‌های فعال</p>
                                            <p class="st-danger__desc">
                                                ۳ دستگاه دیگر به حساب تو وصل‌اند
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="a3d-btn a3d-btn--danger"
                                            @click="
                                                pushToast(
                                                    'danger',
                                                    'از همهٔ دستگاه‌های دیگر خارج شدی',
                                                )
                                            "
                                        >
                                            <LogOut :size="15" />
                                            خروج از همهٔ دستگاه‌ها
                                        </button>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <footer class="st-footer">
                            <span>
                                <Gamepad2 :size="14" />
                                گیم‌استور — پنل تنظیمات
                            </span>
                            <span>BUILD 8812 · v2.4.1</span>
                        </footer>
                    </div>
                </div>
            </div>

            <!-- ================= نوار ذخیره ================= -->
            <div class="st-savebar" :class="{ 'is-shown': dirtyCount > 0 }">
                <div class="st-savebar__inner">
                    <span class="st-ping" />

                    <span class="st-savebar__text">
                        {{ faInt(dirtyCount) }} تغییر ذخیره‌نشده
                    </span>

                    <span class="st-savebar__sep" />

                    <button
                        type="button"
                        class="a3d-btn a3d-btn--ghost a3d-btn--sm"
                        @click="resetChanges"
                    >
                        بازنشانی
                    </button>

                    <button
                        type="button"
                        class="a3d-btn a3d-btn--gold a3d-btn--sm"
                        :disabled="saving"
                        @click="save"
                    >
                        <RefreshCw v-if="saving" :size="15" class="st-spinner" />
                        <Check v-else :size="15" />
                        {{ saving ? 'در حال ذخیره…' : 'ذخیرهٔ تغییرات' }}
                    </button>
                </div>
            </div>

            <ToastHost :toasts="toasts" @close="dismissToast" />
        </div>
    </AppLayout>
</template>

<style scoped>
.st-body {
    padding-block: 2.2rem 11rem;
}

.st-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.st-hero__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-top: 1.3rem;
}

.st-nav--desktop {
    display: none;
}

@media (min-width: 1100px) {
    .st-nav--desktop {
        display: block;
    }

    .st-navbar-mobile {
        display: none;
    }
}

.st-version {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.st-gfx-grid {
    display: grid;
    gap: 1.3rem;
}

@media (min-width: 1280px) {
    .st-gfx-grid {
        grid-template-columns: minmax(0, 1fr) 384px;
    }
}

.st-audio__head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.st-cap__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
}

.st-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-top: 3rem;
    padding-top: 1.3rem;
    border-top: 1px solid var(--gs-border);
    font-size: 0.72rem;
    color: var(--gs-text-muted);
}

.st-footer span {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.st-spinner {
    animation: st-spin 0.9s linear infinite;
}
</style>
