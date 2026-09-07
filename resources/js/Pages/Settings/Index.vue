<script setup>
/**
 * صفحهٔ تنظیمات گیم‌استور — بازطراحی‌شده بر پایهٔ ماژول Setting بک‌اند
 * مسیر: resources/js/Pages/Settings/Index.vue
 * ---------------------------------------------------------------------------
 * این نسخه، برخلاف نسخهٔ نمایشی قبلی، کاملاً به بک‌اند وصل است:
 *   • ساختار فرم به‌صورت داینامیک از /settings (values + meta) ساخته می‌شود.
 *   • گروه‌ها دقیقاً همان SettingGroup بک‌اند هستند: general / invoice / desktop.
 *   • نوع هر کنترل از روی meta.type و rules تصمیم‌گیری می‌شود.
 *   • ذخیره فقط کلیدهای تغییرکرده را با PUT /settings می‌فرستد.
 *
 * جلوهٔ بصری، انیمیشن‌ها و کلاس‌های st-* / a3d-* عیناً حفظ شده‌اند
 * (وابسته به resources/css/settings-3d.css که باید در app.css import شود).
 *
 * وابستگی‌ها (همه از قبل در پروژه موجودند):
 *   • @/Composables/useTilt        → v-tilt / v-reveal
 *   • @/Composables/useSettingsApi → پل بک‌اند
 *   • @/Utils/format               → faInt
 *   • lucide-vue-next              → آیکون‌ها
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
    AlertTriangle,
    Check,
    Clock,
    DatabaseBackup,
    Download,
    Gamepad2,
    Globe,
    HardDrive,
    Info,
    Layers,
    MonitorSmartphone,
    Palette,
    Printer,
    ReceiptText,
    RefreshCw,
    RotateCcw,
    Save,
    Settings as SettingsIcon,
    Upload,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'
import { useSettingsApi } from '@/Composables/useSettingsApi'

import SettingsScene from '@/Components/Settings/SettingsScene.vue'
import GsSectionHead from '@/Components/Settings/GsSectionHead.vue'
import GsRow from '@/Components/Settings/GsRow.vue'
import GsToggle from '@/Components/Settings/GsToggle.vue'
import GsSlider from '@/Components/Settings/GsSlider.vue'
import GsSegmented from '@/Components/Settings/GsSegmented.vue'
import GsSelect from '@/Components/Settings/GsSelect.vue'
import GsField from '@/Components/Settings/GsField.vue'
import GsTextArea from '@/Components/Settings/GsTextArea.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'
import GearsCluster from '@/Components/Settings/GearsCluster.vue'

const api = useSettingsApi()

/* =========================================================================
 * ۱) پیکربندی نمایشی گروه‌ها/بخش‌ها (مطابق SettingGroup و section بک‌اند)
 * ========================================================================= */
const GROUP_UI = {
    general: { label: 'عمومی و محلی‌سازی', icon: Globe },
    invoice: { label: 'مالیات و فاکتور', icon: ReceiptText },
    desktop: { label: 'دسکتاپ', icon: MonitorSmartphone },
}

const SECTION_UI = {
    locale: { label: 'محلی‌سازی', icon: Globe, desc: 'تقویم، ارز، جداکننده‌ها و زبان' },
    appearance: { label: 'ظاهر', icon: Palette, desc: 'تم روشن/تیره رابط کاربری' },
    tax: { label: 'مالیات', icon: ReceiptText, desc: 'نرخ و نحوهٔ محاسبهٔ مالیات بر ارزش افزوده' },
    invoice: { label: 'فاکتور', icon: Layers, desc: 'شماره‌گذاری، اطلاعات قانونی و متن‌های فاکتور' },
    print: { label: 'چاپ', icon: Printer, desc: 'نوع چاپگر و اندازهٔ کاغذ' },
    startup: { label: 'راه‌اندازی', icon: MonitorSmartphone, desc: 'رفتار برنامه هنگام روشن‌شدن سیستم' },
    paths: { label: 'مسیرها', icon: HardDrive, desc: 'محل ذخیرهٔ دیتابیس و بکاپ‌ها روی دیسک' },
    updates: { label: 'بروزرسانی', icon: RefreshCw, desc: 'بررسی و نصب بروزرسانی خودکار' },
    backup: { label: 'پشتیبان‌گیری', icon: DatabaseBackup, desc: 'زمان‌بندی و نگهداری بکاپ‌ها' },
}

/** توضیح کمکی برای هر کلید (UX بهتر — بک‌اند فقط label دارد) */
const KEY_DESC = {
    'general.calendar': 'مبنای نمایش تاریخ‌ها در کل برنامه',
    'general.decimal_separator': 'نویسهٔ جداکنندهٔ بخش اعشاری اعداد',
    'general.thousand_separator': 'نویسهٔ جداکنندهٔ هزارگان در اعداد',
    'general.time_format': 'نمایش ساعت به‌صورت ۱۲ یا ۲۴ ساعته',
    'general.currency': 'واحد پولی که کنار قیمت‌ها نوشته می‌شود',
    'general.currency_code': 'کد سه‌حرفی ارز مطابق استاندارد ISO',
    'general.price_display': 'قالب نمایش قیمت‌ها در فاکتور و لیست‌ها',
    'general.theme': 'ظاهر کلی برنامه',
    'general.locale': 'زبان پیش‌فرض رابط کاربری',
    'invoice.tax_rate': 'درصد مالیات بر ارزش افزوده',
    'invoice.tax_mode': 'مالیات جدا از قیمت باشد یا شامل آن',
    'invoice.tax_enabled': 'محاسبهٔ خودکار مالیات روی فاکتورها',
    'invoice.prefix': 'پیشوندی که ابتدای شمارهٔ فاکتور می‌آید',
    'invoice.counter': 'آخرین شمارهٔ صادرشدهٔ فاکتور',
    'invoice.counter_padding': 'طول عددی شمارهٔ فاکتور با صفرِ ابتدایی',
    'invoice.business_registration': 'شمارهٔ ثبت رسمی کسب‌وکار',
    'invoice.economic_code': 'کد اقتصادی مالیاتی',
    'invoice.footer_text': 'متنی که در پاورقی همهٔ فاکتورها چاپ می‌شود',
    'invoice.warranty_terms': 'شرایط گارانتی پیش‌فرض روی فاکتور',
    'invoice.logo_path': 'مسیر فایل لوگو برای چاپ روی فاکتور',
    'invoice.printer_type': 'چاپگر پیش‌فرض برای رسید و فاکتور',
    'invoice.paper_size': 'اندازهٔ کاغذ چاپگر',
    'desktop.auto_launch': 'اجرای برنامه هم‌زمان با روشن‌شدن سیستم',
    'desktop.minimize_to_tray': 'کوچک‌شدن به Tray به‌جای بستن کامل',
    'desktop.database_path': 'محل فایل دیتابیس روی دیسک',
    'desktop.backup_path': 'پوشهٔ ذخیرهٔ بکاپ‌ها',
    'desktop.auto_update_url': 'آدرس سرور بروزرسانی خودکار',
    'desktop.auto_update_check': 'بررسی بروزرسانی هنگام اجرای برنامه',
    'desktop.default_printer_name': 'نام چاپگر پیش‌فرض سیستم‌عامل',
    'desktop.backup_schedule': 'دورهٔ زمانی اجرای بکاپ خودکار',
    'desktop.backup_retention': 'چند روز بکاپ‌ها نگهداری شوند',
}

/* =========================================================================
 * ۲) وضعیت داده‌ها
 * ========================================================================= */
const loading = ref(true)
const loadError = ref('')

/** meta[key] = { key, group, section, type, label, default, rules, options[] } */
const meta = reactive({})
/** groups: { general: 'عمومی...', ... } از بک‌اند */
const groupsMap = reactive({})

const form = reactive({})
const baseline = ref({})

const dirtyKeys = computed(() =>
    Object.keys(form).filter(
        (k) => JSON.stringify(form[k]) !== JSON.stringify(baseline.value[k]),
    ),
)
const dirtyCount = computed(() => dirtyKeys.value.length)
const totalCount = computed(() => Object.keys(meta).length)

/* =========================================================================
 * ۳) ساختار درختی گروه → بخش → آیتم‌ها برای رندر
 * ========================================================================= */
const tree = computed(() => {
    const out = []
    for (const gKey of Object.keys(GROUP_UI)) {
        const sections = {}
        for (const key of Object.keys(meta)) {
            const m = meta[key]
            if (m.group !== gKey) continue
            const sec = m.section || 'other'
            ;(sections[sec] ||= []).push(m)
        }
        const sectionList = Object.keys(sections).map((sKey) => ({
            key: sKey,
            ui: SECTION_UI[sKey] || { label: sKey, icon: SettingsIcon, desc: '' },
            items: sections[sKey],
        }))
        if (sectionList.length) {
            out.push({
                key: gKey,
                label: groupsMap[gKey] || GROUP_UI[gKey].label,
                icon: GROUP_UI[gKey].icon,
                sections: sectionList,
            })
        }
    }
    return out
})

/* =========================================================================
 * ۴) انتخاب نوع کنترل از روی meta
 * ========================================================================= */
function parseRules(rules = []) {
    const out = { min: null, max: null, nullable: false, url: false }
    for (const r of rules) {
        if (typeof r !== 'string') continue
        if (r === 'nullable') out.nullable = true
        else if (r === 'url') out.url = true
        else if (r.startsWith('min:')) out.min = Number(r.slice(4))
        else if (r.startsWith('max:')) out.max = Number(r.slice(4))
    }
    return out
}

function controlOf(m) {
    const r = parseRules(m.rules)
    if (m.type === 'bool') return 'toggle'
    if (m.type === 'enum') return (m.options?.length || 0) <= 4 ? 'segmented' : 'select'
    if (m.type === 'int' || m.type === 'float') {
        return r.min !== null && r.max !== null ? 'slider' : 'number'
    }
    // string
    if (r.max !== null && r.max >= 500) return 'textarea'
    return 'field'
}

function sliderProps(m) {
    const r = parseRules(m.rules)
    const step = m.type === 'float' ? 0.5 : 1
    return { min: r.min ?? 0, max: r.max ?? 100, step }
}

function isPathLike(m) {
    const r = parseRules(m.rules)
    return r.url || /path|url/.test(m.key)
}

function optionsFor(m) {
    return (m.options || []).map((o) => ({ value: o.value, label: o.label }))
}

function descFor(m) {
    return KEY_DESC[m.key] || ''
}

/* =========================================================================
 * ۵) بارگذاری از بک‌اند
 * ========================================================================= */
async function load() {
    loading.value = true
    loadError.value = ''
    try {
        const data = await api.fetchAll() // { ok, values, meta, groups }
        Object.keys(meta).forEach((k) => delete meta[k])
        Object.assign(meta, data.meta || {})
        Object.keys(groupsMap).forEach((k) => delete groupsMap[k])
        Object.assign(groupsMap, data.groups || {})

        Object.keys(form).forEach((k) => delete form[k])
        Object.assign(form, data.values || {})
        baseline.value = JSON.parse(JSON.stringify(form))
    } catch (e) {
        loadError.value = e.message || 'خطا در بارگذاری تنظیمات.'
        pushToast('danger', loadError.value)
    } finally {
        loading.value = false
    }
}

/* =========================================================================
 * ۶) توست‌ها
 * ========================================================================= */
const toasts = ref([])
const toastTimers = new Map()

function pushToast(kind, msg) {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`
    toasts.value = [...toasts.value.slice(-2), { id, kind, msg }]
    toastTimers.set(id, setTimeout(() => dismissToast(id), 3600))
}

function dismissToast(id) {
    clearTimeout(toastTimers.get(id))
    toastTimers.delete(id)
    toasts.value = toasts.value.filter((t) => t.id !== id)
}

/* =========================================================================
 * ۷) ذخیره / بازنشانی
 * ========================================================================= */
const saving = ref(false)

async function save() {
    if (saving.value || dirtyCount.value === 0) return
    saving.value = true
    const changes = {}
    for (const k of dirtyKeys.value) changes[k] = form[k]
    try {
        const res = await api.update(changes)
        baseline.value = JSON.parse(JSON.stringify(form))
        pushToast('success', res.message || 'تنظیمات با موفقیت ذخیره شد')
    } catch (e) {
        if (e.errors) {
            const first = Object.values(e.errors)[0]
            pushToast('danger', Array.isArray(first) ? first[0] : e.message)
        } else {
            pushToast('danger', e.message || 'ذخیره ناموفق بود')
        }
    } finally {
        saving.value = false
    }
}

function resetChanges() {
    Object.assign(form, JSON.parse(JSON.stringify(baseline.value)))
    pushToast('info', 'تغییرات ذخیره‌نشده بازنشانی شد')
}

const resettingGroup = ref('')
async function resetGroup(gKey) {
    resettingGroup.value = gKey
    try {
        const res = await api.resetGroup(gKey)
        await load()
        pushToast('info', res.message || 'گروه به مقادیر پیش‌فرض بازگشت')
    } catch (e) {
        pushToast('danger', e.message)
    } finally {
        resettingGroup.value = ''
    }
}

/* =========================================================================
 * ۸) Import / Export
 * ========================================================================= */
async function doExport() {
    try {
        const res = await api.exportSettings()
        const blob = new Blob([JSON.stringify(res, null, 2)], {
            type: 'application/json',
        })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `gamestore-settings-${new Date().toISOString().slice(0, 10)}.json`
        a.click()
        URL.revokeObjectURL(url)
        pushToast('success', 'خروجی تنظیمات دانلود شد')
    } catch (e) {
        pushToast('danger', e.message)
    }
}

const importInput = ref(null)
function triggerImport() {
    importInput.value?.click()
}
async function onImportFile(event) {
    const file = event.target.files?.[0]
    if (!file) return
    try {
        const text = await file.text()
        const parsed = JSON.parse(text)
        const payload = parsed.settings ?? parsed
        const res = await api.importSettings(payload)
        await load()
        pushToast('success', res.message || 'تنظیمات وارد شد')
    } catch (e) {
        pushToast('danger', e.message || 'فایل نامعتبر است')
    } finally {
        event.target.value = ''
    }
}

/* =========================================================================
 * ۹) عملیات دسکتاپ / چاپ
 * ========================================================================= */
const busyOp = ref('')
async function runOp(name, fn, okKind = 'success') {
    busyOp.value = name
    try {
        const res = await fn()
        pushToast(res.ok ? okKind : 'info', res.message || 'انجام شد')
    } catch (e) {
        pushToast('danger', e.message)
    } finally {
        busyOp.value = ''
    }
}
const testPrinter = () => runOp('printer', api.testPrinter)
const triggerBackup = () => runOp('backup', api.triggerBackup)
const checkUpdates = () => runOp('updates', api.checkForUpdates, 'info')

/* =========================================================================
 * ۱۰) ناوبری + Scroll Spy
 * ========================================================================= */
const activeSection = ref('general')
const activeIndex = computed(() => {
    const i = tree.value.findIndex((g) => g.key === activeSection.value)
    return i < 0 ? 0 : i
})
const navPillStyle = computed(() => ({
    transform: `translateY(calc(${activeIndex.value} * (var(--st-nav-h) + 2px)))`,
}))

let spy = null
function initSpy() {
    spy?.disconnect()
    spy = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) activeSection.value = e.target.id
            })
        },
        { rootMargin: '-28% 0px -62% 0px' },
    )
    tree.value.forEach((g) => {
        const el = document.getElementById(g.key)
        if (el) spy.observe(el)
    })
}

function goTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(async () => {
    await load()
    // پس از رندر شدن سکشن‌ها، اسپای را وصل کن
    setTimeout(initSpy, 60)
})

onBeforeUnmount(() => {
    spy?.disconnect()
    toastTimers.forEach((t) => clearTimeout(t))
})
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
                            <span class="st-chip st-chip--plain">ماژول Setting</span>
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
                            پیکربندی مرکزی فروشگاه — محلی‌سازی، مالیات و فاکتور، و رفتار
                            نسخهٔ دسکتاپ. همهٔ مقادیر از دیتابیس خوانده و در همان‌جا ذخیره می‌شوند.
                        </p>

                        <div class="st-hero__stats">
                            <span class="st-stat">
                                <Layers :size="15" />
                                گروه‌ها
                                <b>{{ faInt(tree.length) }}</b>
                            </span>
                            <span class="st-stat">
                                <SettingsIcon :size="15" />
                                تنظیمات
                                <b>{{ faInt(totalCount) }}</b>
                            </span>
                            <span class="st-stat">
                                <Save :size="15" />
                                تغییر ذخیره‌نشده
                                <b>{{ faInt(dirtyCount) }}</b>
                            </span>
                        </div>
                    </div>
                                <GearsCluster />

                    <div class="st-hero__actions">
                        <button
                            type="button"
                            class="a3d-btn a3d-btn--ghost a3d-btn--sm"
                            @click="doExport"
                        >
                            <Download :size="14" /> خروجی
                        </button>
                        <button
                            type="button"
                            class="a3d-btn a3d-btn--ghost a3d-btn--sm"
                            @click="triggerImport"
                        >
                            <Upload :size="14" /> ورودی
                        </button>
                        <input
                            ref="importInput"
                            type="file"
                            accept="application/json"
                            hidden
                            @change="onImportFile"
                        />
                    </div>
                </div>
            </header>

            <!-- ================= بدنه ================= -->
            <div class="st-shell st-body">
                <!-- حالت بارگذاری -->
                <div v-if="loading" class="st-loading">
                    <RefreshCw :size="22" class="st-spinner" />
                    در حال بارگذاری تنظیمات…
                </div>

                <div v-else-if="loadError" class="st-empty">
                    <AlertTriangle :size="26" />
                    <p>{{ loadError }}</p>
                    <button class="a3d-btn a3d-btn--sm" @click="load">تلاش دوباره</button>
                </div>

                <div v-else class="st-grid">
                    <!-- --- ناوبری دسکتاپ --- -->
                    <nav class="st-nav st-nav--desktop">
                        <div class="st-nav__list">
                            <span class="st-nav__pill" :style="navPillStyle" aria-hidden="true" />
                            <button
                                v-for="g in tree"
                                :key="g.key"
                                type="button"
                                class="st-nav__item"
                                :class="{ 'is-active': activeSection === g.key }"
                                @click="goTo(g.key)"
                            >
                                <component :is="g.icon" :size="16" />
                                {{ g.label }}
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
                                <Info :size="14" /> نکته
                            </span>
                            هر گروه را می‌توانی جداگانه به مقادیر پیش‌فرض بازگردانی کنی.
                        </div>
                    </nav>

                    <!-- --- محتوا --- -->
                    <div style="min-width: 0">
                        <!-- ناوبری موبایل -->
                        <div class="st-navbar-mobile">
                            <button
                                v-for="g in tree"
                                :key="g.key"
                                type="button"
                                class="st-nav__item"
                                :class="{ 'is-active': activeSection === g.key }"
                                @click="goTo(g.key)"
                            >
                                <component :is="g.icon" :size="15" />
                                {{ g.label }}
                            </button>
                        </div>

                        <div class="st-sections">
                            <section
                                v-for="g in tree"
                                :id="g.key"
                                :key="g.key"
                                class="st-section"
                            >
                                <div v-reveal="{ delay: 0 }" class="st-group-head">
                                    <GsSectionHead
                                        :icon="g.icon"
                                        :title="g.label"
                                        :desc="`گروه ${g.key} — ${g.sections.length} بخش`"
                                    />
                                    <button
                                        type="button"
                                        class="a3d-btn a3d-btn--ghost a3d-btn--sm"
                                        :disabled="resettingGroup === g.key"
                                        @click="resetGroup(g.key)"
                                    >
                                        <RotateCcw
                                            :size="14"
                                            :class="{ 'st-spinner': resettingGroup === g.key }"
                                        />
                                        بازگردانی گروه
                                    </button>
                                </div>

                                <!-- کارت هر بخش -->
                                <div
                                    v-for="sec in g.sections"
                                    :key="sec.key"
                                    v-reveal="{ delay: 90 }"
                                    v-tilt="{ max: 3, lift: 6, scale: 1.003 }"
                                    class="a3d-holo st-card st-card--sec"
                                >
                                    <div class="st-sec-title">
                                        <component :is="sec.ui.icon" :size="16" />
                                        <div>
                                            <p class="st-sec-title__t">{{ sec.ui.label }}</p>
                                            <p v-if="sec.ui.desc" class="st-sec-title__d">
                                                {{ sec.ui.desc }}
                                            </p>
                                        </div>
                                    </div>

                                    <GsRow
                                        v-for="m in sec.items"
                                        :key="m.key"
                                        :title="m.label"
                                        :desc="descFor(m)"
                                    >
                                        <!-- toggle -->
                                        <GsToggle
                                            v-if="controlOf(m) === 'toggle'"
                                            v-model="form[m.key]"
                                            accent="green"
                                        />

                                        <!-- segmented -->
                                        <GsSegmented
                                            v-else-if="controlOf(m) === 'segmented'"
                                            v-model="form[m.key]"
                                            :options="optionsFor(m)"
                                            style="min-width: 240px"
                                        />

                                        <!-- select -->
                                        <GsSelect
                                            v-else-if="controlOf(m) === 'select'"
                                            v-model="form[m.key]"
                                            :options="optionsFor(m)"
                                        />

                                        <!-- slider -->
                                        <div
                                            v-else-if="controlOf(m) === 'slider'"
                                            style="min-width: 240px"
                                        >
                                            <GsSlider
                                                v-model="form[m.key]"
                                                v-bind="sliderProps(m)"
                                                :formatter="(v) => faInt(v)"
                                            />
                                        </div>

                                        <!-- number -->
                                        <GsField
                                            v-else-if="controlOf(m) === 'number'"
                                            v-model="form[m.key]"
                                            type="number"
                                        />

                                        <!-- textarea -->
                                        <GsTextArea
                                            v-else-if="controlOf(m) === 'textarea'"
                                            v-model="form[m.key]"
                                            :rows="3"
                                            style="min-width: min(420px, 60vw)"
                                        />

                                        <!-- field -->
                                        <GsField
                                            v-else
                                            v-model="form[m.key]"
                                            :dir="isPathLike(m) ? 'ltr' : 'auto'"
                                            :placeholder="m.default ?? ''"
                                        />
                                    </GsRow>

                                    <!-- عملیات ویژهٔ هر بخش -->
                                    <div v-if="sec.key === 'print'" class="st-sec-ops">
                                        <button
                                            type="button"
                                            class="a3d-btn a3d-btn--sm"
                                            :disabled="busyOp === 'printer'"
                                            @click="testPrinter"
                                        >
                                            <Printer
                                                :size="14"
                                                :class="{ 'st-spinner': busyOp === 'printer' }"
                                            />
                                            تست چاپگر
                                        </button>
                                    </div>

                                    <div v-if="sec.key === 'backup'" class="st-sec-ops">
                                        <button
                                            type="button"
                                            class="a3d-btn a3d-btn--sm"
                                            :disabled="busyOp === 'backup'"
                                            @click="triggerBackup"
                                        >
                                            <DatabaseBackup
                                                :size="14"
                                                :class="{ 'st-spinner': busyOp === 'backup' }"
                                            />
                                            اجرای بکاپ دستی
                                        </button>
                                    </div>

                                    <div v-if="sec.key === 'updates'" class="st-sec-ops">
                                        <button
                                            type="button"
                                            class="a3d-btn a3d-btn--sm"
                                            :disabled="busyOp === 'updates'"
                                            @click="checkUpdates"
                                        >
                                            <RefreshCw
                                                :size="14"
                                                :class="{ 'st-spinner': busyOp === 'updates' }"
                                            />
                                            بررسی بروزرسانی
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
                            <span>
                                <Clock :size="13" />
                                همگام با ماژول Setting
                            </span>
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

.st-hero__actions {
    display: flex;
    gap: 0.5rem;
    flex: none;
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

.st-group-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.st-card--sec {
    margin-top: 1.15rem;
}

.st-sec-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding-bottom: 0.9rem;
    margin-bottom: 0.4rem;
    border-bottom: 1px dashed var(--gs-border);
    color: var(--gs-gold);
}

.st-sec-title__t {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--gs-text-primary);
}

.st-sec-title__d {
    font-size: 0.74rem;
    color: var(--gs-text-muted);
    margin-top: 0.1rem;
}

.st-sec-ops {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 0.9rem;
    padding-top: 0.9rem;
    border-top: 1px dashed var(--gs-border);
}

.st-loading,
.st-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.9rem;
    min-height: 40vh;
    color: var(--gs-text-secondary);
    text-align: center;
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

@keyframes st-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
