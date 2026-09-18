<!--
  resources/js/Components/DigitalMenuPanel.vue
  ------------------------------------------------------------------
  پنل «منوی دیجیتال مشتری» — بازطراحی کامل با تایم‌لاین وضعیت،
  انتخابگر دسته‌بندی کارتی و نمایش کد به‌صورت کاشی‌های طلایی.

  ⚠️ منطق و ارتباط با بک‌اند دست‌نخورده:
     props : invoiceId | categories
     routes: invoices.digital-menu.activate (POST { category_ids })
             invoices.digital-menu.status   (GET)
     وضعیت‌ها: pending | active | submitted | expired

  props اختیاری اضافه‌شده:
     pollInterval → بروزرسانی خودکار وضعیت (ms)؛ 0 یعنی خاموش.
                    فقط وقتی تب فعال است و وضعیت pending/active باشد اجرا می‌شود.
-->
<template>
    <section class="dmp">
        <span class="dmp__aura" aria-hidden="true"></span>

        <!-- سربرگ -->
        <header class="dmp__head">
            <span class="dmp__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="6" y="2.5" width="12" height="19" rx="3" />
                    <path d="M10.5 18.5h3M9 6.5h6M9 10h6M9 13.5h4" />
                </svg>
            </span>

            <div class="dmp__head-txt">
                <h3 class="dmp__title">منوی دیجیتال مشتری</h3>
                <p class="dmp__desc">یک کد یک‌بارمصرف بسازید تا مشتری از روی موبایلش سفارش بدهد.</p>
            </div>

            <Transition name="dmp-pop">
                <span v-if="session" class="dmp__badge" :class="badgeTone">
                    <span class="dmp__badge-dot"></span>{{ statusLabel }}
                </span>
            </Transition>
        </header>

        <!-- تایم‌لاین مراحل -->
        <ol v-if="session?.status" class="dmp__steps" aria-label="مراحل منوی دیجیتال">
            <li
                v-for="(s, i) in steps"
                :key="s.key"
                class="dmp__step"
                :class="{ 'is-done': i < stepIndex, 'is-now': i === stepIndex }"
            >
                <span class="dmp__step-dot">
                    <svg v-if="i < stepIndex" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m4.5 12.5 5 5 10-11" />
                    </svg>
                    <i v-else>{{ faDigits(i + 1) }}</i>
                </span>
                <span class="dmp__step-label">{{ s.label }}</span>
            </li>
        </ol>

        <!-- بارگذاری -->
        <div v-if="loading" class="dmp__state">
            <div class="dmp__skeleton"></div>
            <div class="dmp__skeleton dmp__skeleton--sm"></div>
            <p class="dmp__muted">در حال بارگذاری وضعیت…</p>
        </div>

        <!-- انتخاب دسته و فعال‌سازی -->
        <div v-else-if="isSetupState" class="dmp__panel">
            <p v-if="session?.status === 'submitted'" class="dmp__note dmp__note--ok">
                <span>✓</span> سفارش قبلی مشتری با موفقیت ثبت شد — می‌توانید کد جدیدی بسازید.
            </p>
            <p v-else-if="session?.status === 'expired'" class="dmp__note dmp__note--warn">
                <span>⏳</span> کد قبلی منقضی شده است.
            </p>

            <div class="dmp__panel-head">
                <p class="dmp__label">دسته‌بندی‌های مجاز برای مشتری</p>
                <button
                    v-if="categories.length"
                    type="button"
                    class="dmp__link"
                    @click="toggleAllCategories"
                >
                    {{ allCategoriesSelected ? 'لغو انتخاب همه' : 'انتخاب همه' }}
                </button>
            </div>

            <div v-if="!categories.length" class="dmp__muted dmp__muted--pad">
                دسته‌بندی‌ای برای نمایش وجود ندارد.
            </div>

            <div v-else class="dmp__cats">
                <label
                    v-for="(cat, i) in categories"
                    :key="cat.id"
                    class="dmp__cat"
                    :class="{ 'is-on': selectedCategoryIds.includes(cat.id) }"
                    :style="{ '--i': i }"
                >
                    <input type="checkbox" class="dmp__native" :value="cat.id" v-model="selectedCategoryIds" />
                    <span class="dmp__cat-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="m4.5 12.5 5 5 10-11" />
                        </svg>
                    </span>
                    <span class="dmp__cat-name">{{ cat.name }}</span>
                </label>
            </div>

            <div class="dmp__actions">
                <Transition name="dmp-pop">
                    <span v-if="selectedCategoryIds.length" class="dmp__count">
                        {{ faDigits(selectedCategoryIds.length) }} دسته انتخاب شد
                    </span>
                </Transition>

                <button
                    type="button"
                    class="dmp__btn dmp__btn--gold"
                    :disabled="!selectedCategoryIds.length || activating"
                    @click="activate"
                >
                    <span class="dmp__sheen" aria-hidden="true"></span>
                    <span v-if="activating" class="dmp__spinner" aria-hidden="true"></span>
                    <span v-else class="dmp__btn-plus">+</span>
                    <span>{{ activating ? 'در حال ساخت کد…' : 'فعال‌سازی منوی دیجیتال' }}</span>
                </button>
            </div>

            <Transition name="dmp-slide">
                <p v-if="error" class="dmp__error">{{ error }}</p>
            </Transition>
        </div>

        <!-- کد در انتظار -->
        <div v-else-if="session.status === 'pending'" class="dmp__panel dmp__panel--center">
            <p class="dmp__muted">این کد را به مشتری بدهید — فقط یک‌بار قابل استفاده است</p>

            <div class="dmp__code" :title="String(session.code ?? '')">
                <span
                    v-for="(ch, i) in codeChars"
                    :key="i"
                    class="dmp__code-cell"
                    :style="{ '--i': i }"
                >{{ ch }}</span>
            </div>

            <button type="button" class="dmp__linkbox" @click="copyCode">
                <span class="dmp__linkbox-txt">{{ menuLink }}</span>
                <span class="dmp__linkbox-ico">⧉</span>
            </button>

            <div class="dmp__btn-row">
                <button type="button" class="dmp__btn dmp__btn--soft" @click="copyLink">
                    <span>{{ copied === 'link' ? '✓ کپی شد' : 'کپی لینک' }}</span>
                </button>
                <button type="button" class="dmp__btn dmp__btn--soft" @click="copyCode">
                    <span>{{ copied === 'code' ? '✓ کپی شد' : 'کپی کد' }}</span>
                </button>
                <button type="button" class="dmp__btn dmp__btn--ghost" :class="{ 'is-busy': refreshing }" @click="refreshStatus">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="dmp__btn-ico">
                        <path d="M3 12a9 9 0 1 0 2.6-6.4" /><path d="M3 4v5h5" />
                    </svg>
                    <span>بروزرسانی</span>
                </button>
            </div>
        </div>

        <!-- مشتری در حال انتخاب -->
        <div v-else-if="session.status === 'active'" class="dmp__panel dmp__panel--center">
            <div class="dmp__live">
                <span class="dmp__live-pulse" aria-hidden="true"></span>
                <span class="dmp__live-core">🛒</span>
            </div>
            <p class="dmp__live-txt">مشتری وارد منو شده و در حال انتخاب است…</p>
            <div class="dmp__wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
            <button type="button" class="dmp__btn dmp__btn--ghost" :class="{ 'is-busy': refreshing }" @click="refreshStatus">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" class="dmp__btn-ico">
                    <path d="M3 12a9 9 0 1 0 2.6-6.4" /><path d="M3 4v5h5" />
                </svg>
                <span>بروزرسانی وضعیت</span>
            </button>
        </div>

        <!-- fallback -->
        <div v-else class="dmp__panel dmp__panel--center">
            <p class="dmp__error">وضعیت نامشخص — لطفاً بروزرسانی کنید</p>
            <button type="button" class="dmp__btn dmp__btn--ghost" @click="refreshStatus">بروزرسانی وضعیت</button>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

const props = defineProps({
    invoiceId: [Number, String],
    categories: { type: Array, default: () => [] },
    /** بروزرسانی خودکار وضعیت (ms) — 0 = خاموش */
    pollInterval: { type: Number, default: 12000 },
})

const selectedCategoryIds = ref([])
const session = ref(null)
const activating = ref(false)
const error = ref('')
const loading = ref(true)
const refreshing = ref(false)
const copied = ref('')
let fetching = false
let timer = null

const statusLabel = computed(() => ({
    pending: 'در انتظار ورود مشتری',
    active: 'مشتری در حال انتخاب',
    submitted: 'ثبت شد',
    expired: 'منقضی شده',
}[session.value?.status] ?? ''))

/* نگاشت وضعیت → تم چیپ (جایگزین بصری gs-badge-* قبلی) */
const badgeTone = computed(() => ({
    pending: 'dmp__badge--gold',
    active: 'dmp__badge--info',
    submitted: 'dmp__badge--success',
    expired: 'dmp__badge--error',
}[session.value?.status] ?? 'dmp__badge--gold'))

const menuLink = computed(() => session.value?.link ?? '')

/* ── ارتباط با بک‌اند: بدون تغییر ───────────────────────── */
async function activate() {
    error.value = ''
    activating.value = true
    try {
        const { data } = await axios.post(route('invoices.digital-menu.activate', props.invoiceId), {
            category_ids: selectedCategoryIds.value,
        })
        session.value = data
        selectedCategoryIds.value = []
    } catch (e) {
        error.value = e.response?.data?.message ?? 'خطا در فعال‌سازی منوی دیجیتال'
    } finally {
        activating.value = false
    }
}

async function refreshStatus() {
    if (fetching) return
    fetching = true
    refreshing.value = true
    try {
        const { data } = await axios.get(route('invoices.digital-menu.status', props.invoiceId))
        session.value = data
    } catch (e) {
        console.error('digital-menu status fetch failed', e)
        // مقدار قبلی رو دست‌نخورده نگه می‌داریم تا کارت خالی نشه
    } finally {
        loading.value = false
        fetching = false
        refreshing.value = false
    }
}

function copyLink() {
    navigator.clipboard?.writeText(menuLink.value)
    flash('link')
}
/* ──────────────────────────────────────────────────────── */

function copyCode() {
    navigator.clipboard?.writeText(String(session.value?.code ?? ''))
    flash('code')
}

function flash(kind) {
    copied.value = kind
    setTimeout(() => { if (copied.value === kind) copied.value = '' }, 1800)
}

const isSetupState = computed(() =>
    !session.value?.status ||
    session.value.status === 'submitted' ||
    session.value.status === 'expired',
)

const codeChars = computed(() => String(session.value?.code ?? '').split(''))

const steps = [
    { key: 'setup', label: 'انتخاب دسته' },
    { key: 'pending', label: 'ساخت کد' },
    { key: 'active', label: 'انتخاب مشتری' },
    { key: 'submitted', label: 'ثبت سفارش' },
]

const stepIndex = computed(() => ({
    pending: 1,
    active: 2,
    submitted: 3,
    expired: 0,
}[session.value?.status] ?? 0))

const allCategoriesSelected = computed(() =>
    props.categories.length > 0 && selectedCategoryIds.value.length === props.categories.length,
)

function toggleAllCategories() {
    selectedCategoryIds.value = allCategoriesSelected.value
        ? []
        : props.categories.map(c => c.id)
}

function faDigits(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d])
}

/* بروزرسانی خودکار — فقط در حالت‌های زنده و وقتی تب باز است */
function startPolling() {
    if (!props.pollInterval) return
    stopPolling()
    timer = setInterval(() => {
        const s = session.value?.status
        if (document.hidden) return
        if (s === 'pending' || s === 'active') refreshStatus()
    }, props.pollInterval)
}

function stopPolling() {
    if (timer) { clearInterval(timer); timer = null }
}

onMounted(() => {
    refreshStatus()
    startPolling()
})

onBeforeUnmount(stopPolling)
</script>

<style scoped>
.dmp {
    position: relative;
    margin-top: 1.25rem;
    overflow: hidden;
    padding: 1.4rem 1.5rem 1.35rem;
    border-radius: var(--gs-radius-lg);
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-card);
    backdrop-filter: blur(16px) saturate(1.3);
    -webkit-backdrop-filter: blur(16px) saturate(1.3);
    box-shadow: var(--gs-shadow-sm);
    animation: dmp-in 0.55s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
}

.dmp__aura {
    position: absolute;
    inset: -70% -20% 50% 30%;
    background: radial-gradient(closest-side, var(--gs-gold-glow), transparent 72%);
    opacity: 0.4;
    pointer-events: none;
}

/* ── سربرگ ─────────────────────────────────────────────── */
.dmp__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gs-border-soft);
}

.dmp__icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: none;
    border-radius: 13px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    box-shadow: var(--gs-shadow-gold);
    transition: transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.dmp__icon svg { width: 22px; height: 22px; }
.dmp:hover .dmp__icon { transform: rotate(-7deg) scale(1.07); }

.dmp__head-txt { flex: 1; min-width: 0; }

.dmp__title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
    color: var(--gs-text-primary);
}

.dmp__desc {
    margin: 0.15rem 0 0;
    font-size: 0.74rem;
    line-height: 1.85;
    color: var(--gs-text-secondary);
}

.dmp__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: none;
    padding: 0.3rem 0.72rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

.dmp__badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    animation: dmp-blink 2s ease-in-out infinite;
}

.dmp__badge--gold { background: var(--gs-gold-muted); color: var(--gs-gold); border-color: var(--gs-border-hover); }
.dmp__badge--info { background: var(--gs-info-soft); color: var(--gs-info); border-color: color-mix(in srgb, var(--gs-info) 32%, transparent); }
.dmp__badge--success { background: var(--gs-success-soft); color: var(--gs-success); border-color: color-mix(in srgb, var(--gs-success) 32%, transparent); }
.dmp__badge--error { background: var(--gs-error-soft); color: var(--gs-error); border-color: color-mix(in srgb, var(--gs-error) 32%, transparent); }

/* ── تایم‌لاین ─────────────────────────────────────────── */
.dmp__steps {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.25rem;
    list-style: none;
    margin: 1rem 0 0.2rem;
    padding: 0 0.2rem;
}

.dmp__steps::before {
    content: '';
    position: absolute;
    top: 13px;
    inset-inline: 2rem;
    height: 1px;
    background: var(--gs-border-soft);
}

.dmp__step {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    flex: 1;
}

.dmp__step-dot {
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-elevated);
    color: var(--gs-text-muted);
    font-size: 0.68rem;
    font-weight: 800;
    transition: all 0.4s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.dmp__step-dot svg { width: 12px; height: 12px; }
.dmp__step-dot i { font-style: normal; }

.dmp__step.is-done .dmp__step-dot {
    background: var(--gs-success-soft);
    border-color: color-mix(in srgb, var(--gs-success) 45%, transparent);
    color: var(--gs-success);
}

.dmp__step.is-now .dmp__step-dot {
    background: var(--gs-gold-grad);
    border-color: transparent;
    color: #14100a;
    transform: scale(1.14);
    box-shadow: 0 0 0 5px var(--gs-gold-muted);
}

.dmp__step-label {
    font-size: 0.66rem;
    color: var(--gs-text-muted);
    text-align: center;
    transition: color 0.3s ease;
}

.dmp__step.is-now .dmp__step-label { color: var(--gs-gold); font-weight: 700; }
.dmp__step.is-done .dmp__step-label { color: var(--gs-text-secondary); }

/* ── پنل‌ها ────────────────────────────────────────────── */
.dmp__panel { position: relative; padding-top: 1rem; }
.dmp__panel--center { text-align: center; }

.dmp__panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.6rem;
}

.dmp__label {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--gs-text-primary);
}

.dmp__muted { margin: 0; font-size: 0.78rem; color: var(--gs-text-secondary); }
.dmp__muted--pad { padding: 1rem 0; text-align: center; }

.dmp__note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.85rem;
    padding: 0.6rem 0.85rem;
    border-radius: 11px;
    font-size: 0.76rem;
    font-weight: 600;
}

.dmp__note--ok {
    background: var(--gs-success-soft);
    border: 1px solid color-mix(in srgb, var(--gs-success) 28%, transparent);
    color: var(--gs-success);
}

.dmp__note--warn {
    background: var(--gs-warning-soft);
    border: 1px solid color-mix(in srgb, var(--gs-warning) 28%, transparent);
    color: var(--gs-warning);
}

.dmp__link {
    border: 0;
    background: none;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--gs-gold);
    cursor: pointer;
}

.dmp__link:hover { text-decoration: underline; text-underline-offset: 3px; }

/* ── دسته‌بندی‌ها ──────────────────────────────────────── */
.dmp__cats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
    gap: 0.5rem;
}

.dmp__cat {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.7rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    font-size: 0.8rem;
    color: var(--gs-text-secondary);
    cursor: pointer;
    overflow: hidden;
    user-select: none;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s ease, color 0.25s ease,
        transform 0.3s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
    animation: dmp-cat-in 0.42s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
    animation-delay: calc(var(--i) * 35ms);
}

.dmp__cat:hover {
    border-color: var(--gs-border-hover);
    background: var(--gs-glass-hover);
    color: var(--gs-text-primary);
    transform: translateY(-2px);
}

.dmp__cat.is-on {
    border-color: var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    font-weight: 600;
    box-shadow: 0 4px 16px var(--gs-gold-glow);
}

.dmp__native { position: absolute; opacity: 0; width: 0; height: 0; }

.dmp__cat-check {
    display: grid;
    place-items: center;
    width: 18px;
    height: 18px;
    flex: none;
    border-radius: 6px;
    border: 1.5px solid var(--gs-border-hover);
    background: var(--gs-bg-elevated);
    color: #14100a;
    transition: all 0.32s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.dmp__cat-check svg {
    width: 11px;
    height: 11px;
    stroke-dasharray: 26;
    stroke-dashoffset: 26;
    transition: stroke-dashoffset 0.32s 0.05s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.dmp__cat.is-on .dmp__cat-check {
    background: var(--gs-gold-grad);
    border-color: transparent;
}

.dmp__cat.is-on .dmp__cat-check svg { stroke-dashoffset: 0; }

.dmp__cat-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── اکشن‌ها ───────────────────────────────────────────── */
.dmp__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 0.9rem;
}

.dmp__count {
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font-size: 0.68rem;
    font-weight: 600;
}

.dmp__btn-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 0.9rem;
}

.dmp__btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.55rem 1.05rem;
    border-radius: 11px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.79rem;
    font-weight: 700;
    cursor: pointer;
    overflow: hidden;
    white-space: nowrap;
    transition:
        transform 0.28s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)),
        background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, color 0.25s ease, opacity 0.2s ease;
}

.dmp__btn:active:not(:disabled) { transform: scale(0.97); }

.dmp__btn--gold {
    background: var(--gs-gold-grad);
    color: #14100a;
    box-shadow: 0 6px 22px var(--gs-gold-glow);
}

.dmp__btn--gold:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 30px var(--gs-gold-glow); }
.dmp__btn--gold:disabled { opacity: 0.4; cursor: not-allowed; box-shadow: none; filter: grayscale(0.4); }

.dmp__btn-plus { font-size: 1rem; line-height: 1; }

.dmp__btn--soft {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
}

.dmp__btn--soft:hover { background: var(--gs-glass-hover); transform: translateY(-2px); }

.dmp__btn--ghost {
    background: var(--gs-glass);
    border-color: var(--gs-border);
    color: var(--gs-text-secondary);
}

.dmp__btn--ghost:hover { color: var(--gs-gold); border-color: var(--gs-border-hover); transform: translateY(-2px); }

.dmp__btn-ico { width: 15px; height: 15px; }
.dmp__btn.is-busy .dmp__btn-ico { animation: dmp-spin 0.8s linear infinite; }

.dmp__sheen {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40%;
    background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    transform: translateX(-160%);
}

.dmp__btn--gold:hover:not(:disabled) .dmp__sheen { animation: dmp-sheen 0.9s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }

.dmp__spinner {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid rgba(20, 16, 10, 0.25);
    border-top-color: #14100a;
    animation: dmp-spin 0.7s linear infinite;
}

.dmp__error {
    margin: 0.7rem 0 0;
    padding: 0.55rem 0.8rem;
    border-radius: 10px;
    background: var(--gs-error-soft);
    border: 1px solid color-mix(in srgb, var(--gs-error) 30%, transparent);
    color: var(--gs-error);
    font-size: 0.76rem;
}

/* ── کد ────────────────────────────────────────────────── */
.dmp__code {
    display: flex;
    justify-content: center;
    gap: 0.4rem;
    margin: 0.9rem 0 0.75rem;
    direction: ltr;
}

.dmp__code-cell {
    display: grid;
    place-items: center;
    min-width: 42px;
    height: 54px;
    padding: 0 0.35rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border-hover);
    background: linear-gradient(180deg, var(--gs-gold-muted), transparent 85%), var(--gs-bg-elevated);
    color: var(--gs-gold);
    font-size: 1.65rem;
    font-weight: 900;
    letter-spacing: 0;
    font-variant-numeric: tabular-nums;
    box-shadow: var(--gs-shadow-gold);
    animation: dmp-cell-in 0.5s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)) both;
    animation-delay: calc(var(--i) * 70ms);
    transition: transform 0.3s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.dmp__code:hover .dmp__code-cell { transform: translateY(-3px); }

.dmp__linkbox {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    max-width: 100%;
    padding: 0.4rem 0.75rem;
    border-radius: 10px;
    border: 1px dashed var(--gs-border-hover);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font: inherit;
    font-size: 0.72rem;
    cursor: pointer;
    direction: ltr;
    transition: all 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.dmp__linkbox:hover { border-color: var(--gs-gold); color: var(--gs-gold); background: var(--gs-gold-muted); }

.dmp__linkbox-txt {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: min(420px, 70vw);
}

.dmp__linkbox-ico { flex: none; opacity: 0.7; }

/* ── حالت زنده ─────────────────────────────────────────── */
.dmp__live {
    position: relative;
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    margin: 0.6rem auto 0.7rem;
}

.dmp__live-core {
    position: relative;
    z-index: 1;
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-info-soft);
    font-size: 1.3rem;
}

.dmp__live-pulse {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 2px solid var(--gs-info);
    opacity: 0.6;
    animation: dmp-pulse 2s ease-out infinite;
}

.dmp__live-txt { margin: 0; font-size: 0.85rem; font-weight: 600; color: var(--gs-text-primary); }

.dmp__wave {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 3px;
    height: 18px;
    margin: 0.6rem 0 0.2rem;
}

.dmp__wave i {
    width: 3px;
    height: 100%;
    border-radius: 3px;
    background: var(--gs-info);
    opacity: 0.7;
    animation: dmp-wave 1.1s ease-in-out infinite;
}

.dmp__wave i:nth-child(2) { animation-delay: 0.12s; }
.dmp__wave i:nth-child(3) { animation-delay: 0.24s; }
.dmp__wave i:nth-child(4) { animation-delay: 0.36s; }
.dmp__wave i:nth-child(5) { animation-delay: 0.48s; }

/* ── اسکلتون ──────────────────────────────────────────── */
.dmp__state { position: relative; padding: 1rem 0 0.4rem; text-align: center; }

.dmp__skeleton {
    height: 14px;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: linear-gradient(90deg, var(--gs-glass) 25%, var(--gs-glass-hover) 50%, var(--gs-glass) 75%);
    background-size: 200% 100%;
    animation: dmp-shimmer 1.4s linear infinite;
}

.dmp__skeleton--sm { width: 55%; margin-inline: auto; }

/* ── ترنزیشن‌ها ───────────────────────────────────────── */
.dmp-pop-enter-active { transition: all 0.32s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)); }
.dmp-pop-leave-active { transition: all 0.18s ease; }
.dmp-pop-enter-from, .dmp-pop-leave-to { opacity: 0; transform: scale(0.8); }

.dmp-slide-enter-active, .dmp-slide-leave-active { transition: all 0.3s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }
.dmp-slide-enter-from, .dmp-slide-leave-to { opacity: 0; transform: translateY(-5px); }

@keyframes dmp-in { from { opacity: 0; transform: translateY(14px); } }
@keyframes dmp-cat-in { from { opacity: 0; transform: translateY(7px); } }
@keyframes dmp-cell-in { from { opacity: 0; transform: translateY(-14px) rotateX(70deg); } }
@keyframes dmp-spin { to { transform: rotate(360deg); } }
@keyframes dmp-sheen { to { transform: translateX(320%); } }
@keyframes dmp-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
@keyframes dmp-pulse { 0% { transform: scale(0.7); opacity: 0.7; } 100% { transform: scale(1.25); opacity: 0; } }
@keyframes dmp-wave { 0%, 100% { height: 30%; } 50% { height: 100%; } }
@keyframes dmp-shimmer { to { background-position: -200% 0; } }

@media (max-width: 640px) {
    .dmp { padding: 1.1rem 1rem; }
    .dmp__steps { display: none; }
    .dmp__code-cell { min-width: 34px; height: 46px; font-size: 1.3rem; }
    .dmp__actions { flex-direction: column-reverse; align-items: stretch; }
    .dmp__btn--gold { width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
    .dmp *, .dmp { animation: none !important; transition-duration: 0.01ms !important; }
}
</style>
