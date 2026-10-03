<script setup>
/**
 * PatchUpdates — بخش «بروزرسانی» تنظیمات (پچ‌های StoreServer)
 * مسیر: resources/js/Components/Settings/PatchUpdates.vue
 * emit: toast(kind, message)
 *
 * جریان: بررسی → فهرست → نصب (پس‌زمینه) → polling پیشرفت → نتیجه / نیاز به ری‌استارت / rollback
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import {
    AlertTriangle, CheckCircle2, Download, History, Loader2, PackageCheck,
    RefreshCw, RotateCcw, ShieldAlert, XCircle,
} from 'lucide-vue-next'

const emit = defineEmits(['toast'])

const data = ref(null)
const loading = ref(true)
const checking = ref(false)
const acting = ref('') // کد پچی که درخواستش در حال ارسال است
let timer = null

const busyCode = computed(() => data.value?.busy ?? null)
const canAct = computed(
    () => !!data.value?.license?.active && !!data.value?.target?.safe && !busyCode.value,
)

const STAGES = {
    queued: 'در صف نصب',
    preflight: 'بررسی پیش‌نیازها',
    downloading: 'دانلود بسته',
    verifying: 'بررسی امضا و صحت',
    staging: 'استخراج',
    applying: 'ایجاد بکاپ',
    files: 'جایگزینی فایل‌ها',
    lint: 'بررسی سینتکس',
    sql: 'به‌روزرسانی دیتابیس',
    health: 'بررسی سلامت',
    done: 'پایان',
}

const ERRORS = {
    SIGNATURE_INVALID: 'امضای بسته معتبر نیست',
    HASH_MISMATCH: 'فایل دانلودشده سالم نیست',
    UNTRUSTED_KEY: 'کلید امضای سرور در برنامه تعریف نشده',
    VERSION_OUT_OF_RANGE: 'این بروزرسانی برای نسخه‌ی فعلی شما نیست',
    DEPENDENCY_MISSING: 'ابتدا بروزرسانی قبلی باید نصب شود',
    CODE_DIR_READONLY: 'دسترسی نوشتن در پوشه‌ی برنامه وجود ندارد',
    TARGET_IS_GIT_REPO: 'مسیر هدف مخزن git است (حفاظت سورس)',
    ROW_LOSS: 'کاهش غیرمجاز داده‌ها؛ تغییرات برگردانده شد',
    HEALTHCHECK_FAILED: 'برنامه پس از پچ درست بالا نیامد؛ برگردانده شد',
    PHP_SYNTAX: 'خطای سینتکس در فایل پچ؛ برگردانده شد',
    DOWNLOAD_FAILED: 'دانلود قطع شد؛ دوباره تلاش کنید (از ادامه‌ی همان‌جا)',
    LINK_EXPIRED: 'لینک دانلود منقضی شد؛ دوباره تلاش کنید',
    STALE: 'نصب نیمه‌کاره ماند',
}

function fmtSize(b) {
    if (!b) return '—'
    if (b < 1024) return `${b} B`
    if (b < 1048576) return `${(b / 1024).toFixed(1)} KB`
    return `${(b / 1048576).toFixed(1)} MB`
}

function fmtDate(iso) {
    if (!iso) return '—'
    try {
        return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
    } catch {
        return iso
    }
}

function apply(res, quiet = false) {
    data.value = res
    if (!quiet && res.message) {
        emit('toast', res.action_ok === false || res.sync_ok === false ? 'danger' : 'info', res.message)
    }
    schedulePoll()
}

function schedulePoll() {
    clearTimeout(timer)
    if (busyCode.value) timer = setTimeout(poll, 1200)
}

async function poll() {
    try {
        const prev = busyCode.value
        const { data: res } = await axios.get(route('settings.updates.status'))
        data.value = res
        if (prev && !res.busy) finished(res, prev)
        schedulePoll()
    } catch {
        timer = setTimeout(poll, 3000)
    }
}

function finished(res, code) {
    const row = [...res.history, ...res.patches].find((p) => p.code === code)
    if (!row) return
    if (row.status === 'applied') emit('toast', 'success', row.message || 'بروزرسانی نصب شد.')
    else emit('toast', 'danger', row.message || 'نصب ناموفق بود.')
}

async function load() {
    loading.value = true
    try {
        const { data: res } = await axios.get(route('settings.updates.status'))
        apply(res, true)
    } catch {
        emit('toast', 'danger', 'دریافت وضعیت بروزرسانی ناموفق بود.')
    } finally {
        loading.value = false
    }
}

async function check() {
    checking.value = true
    try {
        const { data: res } = await axios.post(route('settings.updates.check'))
        apply(res)
    } catch (e) {
        emit('toast', 'danger', e.response?.data?.message || 'بررسی بروزرسانی ناموفق بود.')
    } finally {
        checking.value = false
    }
}

async function install(p) {
    acting.value = p.code
    try {
        const { data: res } = await axios.post(route('settings.updates.install', { code: p.code }))
        apply(res)
    } catch (e) {
        emit('toast', 'danger', e.response?.data?.message || 'شروع نصب ناموفق بود.')
    } finally {
        acting.value = ''
    }
}

async function rollback(h) {
    if (!window.confirm(`بروزرسانی «${h.title}» برگردانده شود؟\nفقط فایل‌های برنامه به حالت قبل برمی‌گردند؛ داده‌های شما (فاکتورها و ...) دست نمی‌خورد.`)) return
    acting.value = h.code
    try {
        const { data: res } = await axios.post(route('settings.updates.rollback', { code: h.code }))
        apply(res)
    } catch (e) {
        emit('toast', 'danger', e.response?.data?.message || 'بازگردانی ناموفق بود.')
    } finally {
        acting.value = ''
    }
}

async function restartApp() {
    try {
        const { data: res } = await axios.post(route('settings.updates.restart'))
        emit('toast', 'info', res.message)
        await load()
    } catch {
        emit('toast', 'danger', 'راه‌اندازی مجدد ناموفق بود.')
    }
}

onMounted(load)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
    <div class="pu">
        <div v-if="loading" class="pu-muted"><Loader2 :size="15" class="pu-spin" /> در حال دریافت وضعیت…</div>

        <template v-else-if="data">
            <!-- وضعیت کلی -->
            <div class="pu-summary">
                <div>
                    <p class="pu-ver">نسخه‌ی فعلی: <b dir="ltr">{{ data.version }}</b></p>
                    <p class="pu-muted">
                        نصب‌کننده <span dir="ltr">{{ data.installer_version }}</span>
                        <template v-if="data.patch_level"> · آخرین بروزرسانی <span dir="ltr">{{ data.patch_level }}</span></template>
                        · آخرین بررسی: {{ data.last_sync_at ? fmtDate(data.last_sync_at) : 'هنوز انجام نشده' }}
                    </p>
                </div>
                <button type="button" class="a3d-btn a3d-btn--sm" :disabled="checking || !!busyCode || !data.license.active" @click="check">
                    <RefreshCw :size="14" :class="{ 'pu-spin': checking }" /> بررسی بروزرسانی
                </button>
            </div>

            <!-- هشدارها -->
            <div v-if="!data.license.active" class="pu-note pu-note--warn">
                <ShieldAlert :size="16" /> لایسنس فعال نیست؛ دریافت بروزرسانی ممکن نیست.
            </div>
            <div v-if="data.target.problem" class="pu-note pu-note--warn">
                <AlertTriangle :size="16" /> {{ data.target.problem }}
            </div>
            <div v-else-if="!data.target.is_default" class="pu-note">
                <AlertTriangle :size="16" /> حالت آزمایشی: پچ‌ها روی <code dir="ltr">{{ data.target.path }}</code> اعمال می‌شوند، نه پروژه‌ی در حال اجرا.
            </div>
            <div v-if="data.restart_pending" class="pu-note pu-note--info">
                <PackageCheck :size="16" /> برای اعمال کامل بروزرسانی، برنامه باید دوباره اجرا شود.
                <button type="button" class="a3d-btn a3d-btn--sm a3d-btn--gold" @click="restartApp">راه‌اندازی مجدد</button>
            </div>

            <!-- بروزرسانی‌های موجود -->
            <h4 class="pu-h">بروزرسانی‌های موجود</h4>
            <p v-if="!data.patches.length" class="pu-muted">بروزرسانی جدیدی موجود نیست.</p>

            <ul v-else class="pu-list">
                <li v-for="p in data.patches" :key="p.code" class="pu-item" :class="`pu-item--${p.status}`">
                    <div class="pu-item__main">
                        <p class="pu-item__title">
                            {{ p.title }}
                            <span class="pu-tag" dir="ltr">{{ p.to_version }}</span>
                            <span v-if="p.mandatory" class="pu-tag pu-tag--warn">ضروری</span>
                            <span v-if="p.requires_restart" class="pu-tag">نیاز به راه‌اندازی مجدد</span>
                        </p>
                        <p v-if="p.description" class="pu-muted">{{ p.description }}</p>
                        <p class="pu-muted">حجم: {{ fmtSize(p.size) }} · برای نسخه‌های <span dir="ltr">{{ p.from_min }} – {{ p.from_max }}</span></p>

                        <!-- پیشرفت -->
                        <div v-if="p.status === 'queued' || p.status === 'running'" class="pu-progress">
                            <div class="pu-bar"><i :style="{ width: p.progress + '%' }" /></div>
                            <p class="pu-muted"><Loader2 :size="13" class="pu-spin" /> {{ STAGES[p.stage] || p.message }} ({{ p.progress }}٪)</p>
                        </div>

                        <!-- خطا -->
                        <p v-if="p.status === 'failed' || p.status === 'rolled_back'" class="pu-err">
                            <XCircle :size="14" />
                            {{ ERRORS[p.error_code] || 'نصب ناموفق بود' }}
                            <span class="pu-muted">— {{ p.message }}</span>
                        </p>
                    </div>

                    <button
                        type="button"
                        class="a3d-btn a3d-btn--sm a3d-btn--gold"
                        :disabled="!canAct || acting === p.code || p.status === 'queued' || p.status === 'running'"
                        @click="install(p)"
                    >
                        <Download :size="14" />
                        {{ p.status === 'failed' || p.status === 'rolled_back' ? 'تلاش مجدد' : 'نصب' }}
                    </button>
                </li>
            </ul>

            <!-- تاریخچه -->
            <template v-if="data.history.length">
                <h4 class="pu-h"><History :size="15" /> تاریخچه</h4>
                <ul class="pu-list pu-list--compact">
                    <li v-for="h in data.history" :key="h.code + h.finished_at" class="pu-item">
                        <div class="pu-item__main">
                            <p class="pu-item__title">
                                <CheckCircle2 v-if="h.status === 'applied'" :size="15" class="pu-ok" />
                                <XCircle v-else :size="15" class="pu-bad" />
                                {{ h.title }} <span class="pu-tag" dir="ltr">{{ h.version_after || h.to_version }}</span>
                            </p>
                            <p class="pu-muted">
                                {{ h.status === 'applied' ? 'نصب‌شده' : 'برگردانده‌شده' }} · {{ fmtDate(h.finished_at) }}
                                <template v-if="h.files_count"> · {{ h.files_count }} فایل</template>
                            </p>
                        </div>
                        <button
                            v-if="data.can_rollback === h.code && h.status === 'applied'"
                            type="button"
                            class="a3d-btn a3d-btn--sm a3d-btn--ghost"
                            :disabled="!!busyCode || acting === h.code"
                            @click="rollback(h)"
                        >
                            <RotateCcw :size="14" /> بازگردانی
                        </button>
                    </li>
                </ul>
            </template>
        </template>
    </div>
</template>

<style scoped>
.pu { display: flex; flex-direction: column; gap: 0.8rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--gs-border); }
.pu-summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.pu-ver { margin: 0 0 0.2rem; color: var(--gs-text-primary); font-size: 0.95rem; }
.pu-muted { margin: 0; color: var(--gs-text-muted); font-size: 0.78rem; line-height: 1.7; display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
.pu-h { margin: 0.4rem 0 0; display: flex; align-items: center; gap: 0.4rem; color: var(--gs-text-secondary); font-size: 0.85rem; font-weight: 700; }
.pu-note { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; padding: 0.55rem 0.8rem; border-radius: 10px; font-size: 0.78rem; color: var(--gs-text-secondary); background: var(--gs-bg-card); border: 1px solid var(--gs-border); }
.pu-note--warn { color: var(--gs-warning); border-color: var(--gs-warning); background: var(--gs-warning-soft); }
.pu-note--info { color: var(--gs-info); }
.pu-note code { font-size: 0.72rem; }
.pu-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.55rem; }
.pu-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 0.75rem 0.9rem; border-radius: 12px; border: 1px solid var(--gs-border); background: var(--gs-bg-card); }
.pu-item--failed, .pu-item--rolled_back { border-color: var(--gs-error); }
.pu-item--running, .pu-item--queued { border-color: var(--gs-gold-muted); }
.pu-list--compact .pu-item { padding: 0.5rem 0.8rem; align-items: center; }
.pu-item__main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pu-item__title { margin: 0; display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem; color: var(--gs-text-primary); font-size: 0.88rem; font-weight: 700; }
.pu-tag { padding: 0.05rem 0.5rem; border-radius: 999px; font-size: 0.68rem; font-weight: 600; color: var(--gs-text-secondary); border: 1px solid var(--gs-border); }
.pu-tag--warn { color: var(--gs-warning); border-color: var(--gs-warning); }
.pu-progress { margin-top: 0.3rem; }
.pu-bar { height: 6px; border-radius: 999px; background: var(--gs-border); overflow: hidden; margin-bottom: 0.3rem; }
.pu-bar i { display: block; height: 100%; background: var(--gs-gold-grad, var(--gs-gold)); transition: width 0.4s ease; }
.pu-err { margin: 0.2rem 0 0; display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; color: var(--gs-error); font-size: 0.78rem; }
.pu-ok { color: var(--gs-success); }
.pu-bad { color: var(--gs-error); }
.pu-spin { animation: pu-spin 0.9s linear infinite; }
@keyframes pu-spin { to { transform: rotate(360deg); } }
</style>
