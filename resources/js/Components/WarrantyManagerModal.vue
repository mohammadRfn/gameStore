<!--
  resources/js/Components/WarrantyManagerModal.vue
  ------------------------------------------------------------------
  مدیریت گارانتی — بازطراحی کامل (مودال شیشه‌ای، فیلتر سگمنتی وضعیت،
  جستجوی زنده، آکاردئون نرم، فرم لوکس و پنل افزودن ارائه‌دهنده).

  ⚠️ همهٔ مسیرها و منطق بک‌اند دست‌نخورده:
     GET  items.warranty-candidates
     GET  warranty-providers.index
     POST item-serial-numbers.warranty.store   (anchor === 'serial')
     POST order-items.warranty.store           (anchor !== 'serial')
     POST warranty-providers.store
     props: show | itemId | itemName | focusOrderItemId     emits: close
-->
<template>
    <Teleport to="body">
        <Transition name="gsw-fade">
            <div v-if="show" class="gsw-overlay" @click.self="close">
                <Transition name="gsw-zoom" appear>
                    <div class="gsw" role="dialog" aria-modal="true">
                        <span class="gsw__aura" aria-hidden="true"></span>

                        <!-- سربرگ -->
                        <header class="gsw__head">
                            <span class="gsw__icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2.8 4.5 6v6.2c0 4.5 3.1 7.9 7.5 9 4.4-1.1 7.5-4.5 7.5-9V6L12 2.8Z" />
                                    <path d="m8.8 12 2.2 2.2 4.4-4.6" />
                                </svg>
                            </span>

                            <div class="gsw__head-txt">
                                <h3 class="gsw__title">مدیریت گارانتی</h3>
                                <p class="gsw__subtitle">{{ itemName }}</p>
                            </div>

                            <button type="button" class="gsw__close" title="بستن" @click="close">✕</button>
                        </header>

                        <!-- بارگذاری -->
                        <div v-if="loading" class="gsw__skeletons">
                            <div v-for="n in 4" :key="n" class="gsw__skeleton" :style="{ '--i': n }"></div>
                            <p class="gsw__status">در حال دریافت اطلاعات…</p>
                        </div>

                        <!-- خطا -->
                        <div v-else-if="loadError" class="gsw__banner">
                            <strong>خطا در دریافت اطلاعات</strong>
                            <span>{{ loadError }}</span>
                            <button type="button" class="gsw__retry" @click="fetchCandidates">تلاش دوباره</button>
                        </div>

                        <!-- هیچ فروشی ثبت نشده -->
                        <div v-else-if="!candidates.length" class="gsw__empty">
                            <span class="gsw__empty-ico">🛡️</span>
                            <p class="gsw__empty-title">هنوز هیچ فروشی برای این کالا ثبت نشده</p>
                            <p class="gsw__muted">گارانتی روی قلم‌های فروخته‌شده (order item) ثبت می‌شود.</p>
                        </div>

                        <template v-else>
                            <!-- ابزار: جستجو + فیلتر -->
                            <div class="gsw__tools">
                                <label class="gsw__search">
                                    <span class="gsw__search-ico">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.2-3.2" /></svg>
                                    </span>
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="جستجو: شماره سریال، مشتری یا شماره فاکتور…"
                                    />
                                    <button v-if="searchQuery" type="button" class="gsw__search-x" @click="searchQuery = ''">✕</button>
                                </label>
                            </div>

                            <div class="gsw__filters">
                                <button
                                    v-for="f in statusFilters"
                                    :key="f.key"
                                    type="button"
                                    class="gsw__filter"
                                    :class="[`gsw__filter--${f.tone}`, { 'is-active': statusFilter === f.key }]"
                                    @click="statusFilter = f.key"
                                >
                                    <span class="gsw__filter-dot"></span>
                                    {{ f.label }}
                                    <b>{{ fa(countOf(f.key)) }}</b>
                                </button>
                            </div>

                            <div v-if="!filteredCandidates.length" class="gsw__status">
                                نتیجه‌ای مطابق فیلتر یافت نشد.
                            </div>

                            <!-- لیست -->
                            <div v-else class="gsw__list">
                                <div
                                    v-for="(c, i) in filteredCandidates"
                                    :key="c.key"
                                    class="gsw__row"
                                    :class="{ 'is-open': expanded === c.key, 'is-locked': !c.editable }"
                                    :style="{ '--i': i }"
                                >
                                    <span class="gsw__rail" :class="railTone(c)" aria-hidden="true"></span>

                                    <!-- هدر ردیف -->
                                    <button type="button" class="gsw__row-main" @click="toggle(c.key)">
                                        <span class="gsw__row-anchor" :class="c.anchor === 'serial' ? 'is-serial' : 'is-invoice'">
                                            {{ c.anchor === 'serial' ? 'سریال' : 'فاکتور' }}
                                        </span>

                                        <span class="gsw__row-info">
                                            <span class="gsw__item-name">
                                                <template v-if="c.anchor === 'serial'">
                                                    <b class="gsw__mono">{{ c.serial_number ?? '—' }}</b>
                                                    <span v-if="c.in_stock" class="gsw__pill">در انبار</span>
                                                </template>
                                                <template v-else>
                                                    <b class="gsw__mono">{{ c.invoice_number ?? '—' }}</b>
                                                </template>
                                                <span v-if="c.customer_name" class="gsw__cust">{{ c.customer_name }}</span>
                                            </span>

                                            <span class="gsw__row-sub">
                                                <template v-if="c.anchor !== 'serial'">تعداد: {{ fa(c.quantity) }}</template>
                                                <template v-else-if="c.invoice_number">فاکتور: {{ c.invoice_number }}</template>
                                                <template v-if="c.payment_status"> · {{ paymentLabel(c.payment_status) }}</template>
                                            </span>
                                        </span>

                                        <span class="gsw__badge" :class="statusBadgeClass(c)">{{ statusLabel(c) }}</span>

                                        <span class="gsw__chev" :class="{ 'is-open': expanded === c.key }">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                                                 stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                                        </span>
                                    </button>

                                    <!-- جزئیات -->
                                    <Transition name="gsw-expand">
                                        <div v-if="expanded === c.key" class="gsw__detail">
                                            <p v-if="!c.editable" class="gsw__locked">
                                                🔒 چون فاکتور این قلم پرداخت‌شده و هنوز مرجوع نشده، گارانتی آن قابل ویرایش نیست.
                                            </p>

                                            <!-- نمایش گارانتی موجود -->
                                            <template v-if="c.warranty && editingId !== c.key">
                                                <div class="gsw__facts">
                                                    <div class="gsw__fact">
                                                        <span>مدت گارانتی</span>
                                                        <b>{{ fa(c.warranty.duration_value) }} {{ unitLabel(c.warranty.duration_unit) }}</b>
                                                    </div>
                                                    <div v-if="c.warranty.provider" class="gsw__fact">
                                                        <span>ارائه‌دهنده</span>
                                                        <b>{{ c.warranty.provider.name }}</b>
                                                    </div>
                                                    <div v-if="c.warranty.starts_at" class="gsw__fact">
                                                        <span>شروع</span>
                                                        <b>{{ formatDate(c.warranty.starts_at) }}</b>
                                                    </div>
                                                    <div v-if="c.warranty.expires_at" class="gsw__fact">
                                                        <span>انقضا</span>
                                                        <b>{{ formatDate(c.warranty.expires_at) }}</b>
                                                    </div>
                                                    <div v-if="c.warranty.notes" class="gsw__fact gsw__fact--wide">
                                                        <span>یادداشت</span>
                                                        <b>{{ c.warranty.notes }}</b>
                                                    </div>
                                                </div>

                                                <button
                                                    v-if="c.editable"
                                                    type="button"
                                                    class="gsw__btn gsw__btn--soft"
                                                    @click="startEdit(c)"
                                                >ویرایش گارانتی</button>
                                            </template>

                                            <template v-else-if="c.editable">
                                                <div v-if="editingId !== c.key" class="gsw__none">
                                                    <p class="gsw__muted">هنوز گارانتی‌ای برای این قلم ثبت نشده.</p>
                                                    <button type="button" class="gsw__btn gsw__btn--gold" @click="startEdit(c)">
                                                        <span class="gsw__sheen" aria-hidden="true"></span>
                                                        + ثبت گارانتی
                                                    </button>
                                                </div>

                                                <!-- فرم -->
                                                <div v-else class="gsw__form">
                                                    <div class="gsw__grid">
                                                        <label class="gsw__group">
                                                            <span class="gsw__label">مدت <i>*</i></span>
                                                            <input v-model="form.duration_value" type="number" min="1" class="gsw__input" />
                                                        </label>

                                                        <div class="gsw__group">
                                                            <span class="gsw__label">واحد</span>
                                                            <div class="gsw__seg" :style="{ '--n': 3, '--idx': unitIndex }">
                                                                <span class="gsw__seg-pill" aria-hidden="true"></span>
                                                                <button
                                                                    v-for="u in units"
                                                                    :key="u.v"
                                                                    type="button"
                                                                    class="gsw__seg-btn"
                                                                    :class="{ 'is-active': form.duration_unit === u.v }"
                                                                    @click="form.duration_unit = u.v"
                                                                >{{ u.l }}</button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <label class="gsw__group">
                                                        <span class="gsw__label">ارائه‌دهنده گارانتی (اختیاری)</span>
                                                        <div class="gsw__selectwrap">
                                                            <select v-model="form.warranty_provider_id" class="gsw__input gsw__select">
                                                                <option :value="null">بدون ارائه‌دهنده</option>
                                                                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                                                            </select>
                                                            <span class="gsw__selectchev">▾</span>
                                                        </div>
                                                    </label>

                                                    <button
                                                        type="button"
                                                        class="gsw__link"
                                                        @click="showNewProvider = !showNewProvider"
                                                    >{{ showNewProvider ? '− انصراف' : '+ افزودن ارائه‌دهنده جدید' }}</button>

                                                    <Transition name="gsw-expand">
                                                        <div v-if="showNewProvider" class="gsw__provider">
                                                            <label class="gsw__group">
                                                                <span class="gsw__label">نام ارائه‌دهنده <i>*</i></span>
                                                                <input v-model="newProvider.name" type="text" class="gsw__input" />
                                                            </label>

                                                            <div class="gsw__grid">
                                                                <label class="gsw__group">
                                                                    <span class="gsw__label">تلفن</span>
                                                                    <input v-model="newProvider.phone" type="text" dir="ltr" class="gsw__input" />
                                                                </label>
                                                                <label class="gsw__group">
                                                                    <span class="gsw__label">ایمیل</span>
                                                                    <input v-model="newProvider.email" type="text" dir="ltr" class="gsw__input" />
                                                                </label>
                                                            </div>

                                                            <div class="gsw__grid">
                                                                <label class="gsw__group">
                                                                    <span class="gsw__label">سایت</span>
                                                                    <input v-model="newProvider.website" type="text" dir="ltr" class="gsw__input" />
                                                                </label>
                                                                <label class="gsw__group">
                                                                    <span class="gsw__label">اینستاگرام</span>
                                                                    <input v-model="newProvider.instagram" type="text" dir="ltr" class="gsw__input" />
                                                                </label>
                                                            </div>

                                                            <label class="gsw__group">
                                                                <span class="gsw__label">آدرس</span>
                                                                <input v-model="newProvider.address" type="text" class="gsw__input" />
                                                            </label>

                                                            <label class="gsw__group">
                                                                <span class="gsw__label">توضیحات</span>
                                                                <input v-model="newProvider.description" type="text" class="gsw__input" />
                                                            </label>

                                                            <button
                                                                type="button"
                                                                class="gsw__btn gsw__btn--soft"
                                                                :disabled="!newProvider.name.trim() || savingProvider"
                                                                @click="createProvider"
                                                            >
                                                                <span v-if="savingProvider" class="gsw__spin" aria-hidden="true"></span>
                                                                <span>{{ savingProvider ? 'در حال افزودن…' : 'افزودن و انتخاب' }}</span>
                                                            </button>
                                                        </div>
                                                    </Transition>

                                                    <label class="gsw__group">
                                                        <span class="gsw__label">یادداشت</span>
                                                        <input v-model="form.notes" type="text" class="gsw__input" placeholder="توضیح اختیاری…" />
                                                    </label>

                                                    <Transition name="gsw-slide">
                                                        <p v-if="formError" class="gsw__inline-error">{{ formError }}</p>
                                                    </Transition>

                                                    <div class="gsw__form-actions">
                                                        <button type="button" class="gsw__btn gsw__btn--plain" @click="cancelEdit">انصراف</button>
                                                        <button
                                                            type="button"
                                                            class="gsw__btn gsw__btn--gold"
                                                            :disabled="saving"
                                                            @click="saveWarranty(c)"
                                                        >
                                                            <span class="gsw__sheen" aria-hidden="true"></span>
                                                            <span v-if="saving" class="gsw__spin gsw__spin--dark" aria-hidden="true"></span>
                                                            <span>{{ saving ? 'در حال ذخیره…' : 'ذخیره' }}</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                        </template>

                        <footer class="gsw__foot">
                            <span class="gsw__foot-hint">گارانتی پس از پرداخت فاکتور به‌صورت خودکار فعال می‌شود</span>
                            <button type="button" class="gsw__btn gsw__btn--plain" @click="close">بستن</button>
                        </footer>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    show: { type: Boolean, default: false },
    itemId: { type: [Number, String], default: null },
    itemName: { type: String, default: '' },
    focusOrderItemId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const candidates = ref([])
const providers = ref([])
const loading = ref(false)
const loadError = ref('')
const expanded = ref(null)
const editingId = ref(null)

const form = ref({ duration_value: 12, duration_unit: 'month', warranty_provider_id: null, notes: '' })
const saving = ref(false)
const formError = ref('')

const searchQuery = ref('')
const statusFilter = ref('all')

const filteredCandidates = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()

    return candidates.value.filter((c) => {
        if (statusFilter.value !== 'all') {
            const status = c.warranty ? c.warranty.status : 'unregistered'
            if (status !== statusFilter.value) return false
        }

        if (!q) return true

        const haystack = [c.serial_number, c.customer_name, c.invoice_number]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return haystack.includes(q)
    })
})

const showNewProvider = ref(false)
const newProvider = ref({ name: '', phone: '', email: '', website: '', instagram: '', address: '', description: '' })
const savingProvider = ref(false)

/* ── منطق و مسیرها: بدون تغییر ─────────────────────────── */
function close() {
    emit('close')
}

async function fetchCandidates() {
    if (!props.itemId) return
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await axios.get(route('items.warranty-candidates', props.itemId))
        candidates.value = (data.rows ?? []).map(c => ({
            ...c,
            key: c.anchor === 'serial' ? `serial_${c.item_serial_number_id}` : `oi_${c.order_item_id}`,
        }))

        // اگر برای یک order item مشخص باز شده (از صفحه‌ی فاکتور)، مستقیم همون ردیف را باز کن
        if (props.focusOrderItemId) {
            const target = candidates.value.find(c => c.order_item_id == props.focusOrderItemId)
            if (target) expanded.value = target.key
        }
    } catch (e) {
        console.error('warranty-candidates fetch failed:', e)
        loadError.value = `${e.response?.status ?? e.message}`
    } finally {
        loading.value = false
    }
}

async function fetchProviders() {
    try {
        const { data } = await axios.get(route('warranty-providers.index'))
        providers.value = data.providers ?? []
    } catch (e) {
        console.error(e)
    }
}

watch(() => [props.show, props.itemId], ([visible]) => {
    if (visible) {
        expanded.value = null
        editingId.value = null
        showNewProvider.value = false
        searchQuery.value = ''
        statusFilter.value = 'all'
        fetchCandidates()
        fetchProviders()
    }
})

function toggle(id) {
    expanded.value = expanded.value === id ? null : id
    editingId.value = null
    formError.value = ''
    showNewProvider.value = false
}

function startEdit(candidate) {
    editingId.value = candidate.key
    formError.value = ''
    showNewProvider.value = false
    form.value = candidate.warranty
        ? {
            duration_value: candidate.warranty.duration_value,
            duration_unit: candidate.warranty.duration_unit,
            warranty_provider_id: candidate.warranty.warranty_provider_id,
            notes: candidate.warranty.notes ?? '',
        }
        : { duration_value: 12, duration_unit: 'month', warranty_provider_id: null, notes: '' }
}

function cancelEdit() {
    editingId.value = null
    formError.value = ''
}

async function saveWarranty(candidate) {
    saving.value = true
    formError.value = ''
    try {
        const { data } = candidate.anchor === 'serial'
            ? await axios.post(route('item-serial-numbers.warranty.store', candidate.item_serial_number_id), form.value)
            : await axios.post(route('order-items.warranty.store', candidate.order_item_id), form.value)

        candidate.warranty = data.warranty
        editingId.value = null
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'خطا در ذخیره‌ی گارانتی.'
    } finally {
        saving.value = false
    }
}

async function createProvider() {
    savingProvider.value = true
    try {
        const { data } = await axios.post(route('warranty-providers.store'), newProvider.value)
        providers.value.push(data.provider)
        form.value.warranty_provider_id = data.provider.id
        showNewProvider.value = false
        newProvider.value = { name: '', phone: '', email: '', website: '', instagram: '', address: '', description: '' }
    } catch (e) {
        console.error(e)
    } finally {
        savingProvider.value = false
    }
}

function unitLabel(u) { return { day: 'روز', month: 'ماه', year: 'سال' }[u] ?? u }
function paymentLabel(s) { return { unpaid: 'پرداخت‌نشده', paid: 'پرداخت‌شده', returned: 'مرجوع‌شده' }[s] ?? s }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('fa-IR') : '—' }

function statusLabel(c) {
    if (!c.warranty) return 'ثبت‌نشده'
    if (c.warranty.status === 'pending') return 'در انتظار پرداخت'
    if (c.warranty.status === 'expired') return 'منقضی‌شده'
    return 'فعال'
}

function statusBadgeClass(c) {
    if (!c.warranty) return 'gsw-badge-muted'
    if (c.warranty.status === 'pending') return 'gsw-badge-muted'
    if (c.warranty.status === 'expired') return 'gsw-badge-danger'
    return 'gsw-badge-success'
}
/* ──────────────────────────────────────────────────────── */

/* ── لایهٔ نمایشی ──────────────────────────────────────── */
const units = [
    { v: 'day', l: 'روز' },
    { v: 'month', l: 'ماه' },
    { v: 'year', l: 'سال' },
]

const unitIndex = computed(() => Math.max(0, units.findIndex(u => u.v === form.value.duration_unit)))

const statusFilters = [
    { key: 'all', label: 'همه', tone: 'neutral' },
    { key: 'active', label: 'فعال', tone: 'success' },
    { key: 'pending', label: 'در انتظار پرداخت', tone: 'warn' },
    { key: 'expired', label: 'منقضی‌شده', tone: 'danger' },
    { key: 'unregistered', label: 'ثبت‌نشده', tone: 'neutral' },
]

function countOf(key) {
    if (key === 'all') return candidates.value.length
    return candidates.value.filter(c => (c.warranty ? c.warranty.status : 'unregistered') === key).length
}

function railTone(c) {
    if (!c.warranty) return 'is-muted'
    if (c.warranty.status === 'expired') return 'is-danger'
    if (c.warranty.status === 'pending') return 'is-warn'
    return 'is-success'
}

function fa(n) {
    return Number(n ?? 0).toLocaleString('fa-IR')
}
</script>

<style scoped>
.gsw-overlay {
    position: fixed;
    inset: 0;
    z-index: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(4, 4, 8, 0.62);
    backdrop-filter: blur(7px) saturate(0.85);
    -webkit-backdrop-filter: blur(7px) saturate(0.85);
}

.gsw {
    position: relative;
    display: flex;
    flex-direction: column;
    width: min(700px, 100%);
    max-height: 90vh;
    overflow: hidden;
    padding: 1.4rem 1.5rem 1.15rem;
    border-radius: 20px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-bg-card-strong);
    box-shadow: var(--gs-shadow-md), 0 0 70px -22px var(--gs-gold-glow);
    color: var(--gs-text-primary);
    direction: rtl;
}

.gsw *, .gsw *::before, .gsw *::after {
    box-sizing: border-box;
    min-width: 0;
    overflow-wrap: break-word;
    word-break: break-word;
}

.gsw__aura {
    position: absolute;
    inset: -55% 20% 72% -25%;
    background: radial-gradient(closest-side, var(--gs-gold-glow), transparent 72%);
    opacity: 0.35;
    pointer-events: none;
}

/* ── سربرگ ─────────────────────────────────────────────── */
.gsw__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding-bottom: 0.95rem;
    border-bottom: 1px solid var(--gs-border-soft);
}

.gsw__icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    flex: none;
    border-radius: 12px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    box-shadow: var(--gs-shadow-gold);
    transition: transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.gsw__icon svg { width: 21px; height: 21px; }
.gsw:hover .gsw__icon { transform: rotate(-7deg) scale(1.06); }

.gsw__head-txt { flex: 1; }
.gsw__title { margin: 0; font-size: 1.02rem; font-weight: 800; }
.gsw__subtitle { margin: 0.15rem 0 0; font-size: 0.78rem; color: var(--gs-text-secondary); }

.gsw__close {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    flex: none;
    border-radius: 9px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.gsw__close:hover {
    background: var(--gs-error-soft);
    border-color: color-mix(in srgb, var(--gs-error) 40%, transparent);
    color: var(--gs-error);
    transform: rotate(90deg);
}

/* ── ابزارها ───────────────────────────────────────────── */
.gsw__tools { position: relative; margin-top: 0.85rem; }

.gsw__search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    height: 40px;
    padding: 0 0.8rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    transition: all 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.gsw__search:focus-within {
    border-color: var(--gs-border-strong);
    background: var(--gs-glass-hover);
    box-shadow: 0 0 0 4px var(--gs-gold-muted);
}

.gsw__search-ico { display: grid; place-items: center; width: 16px; height: 16px; flex: none; color: var(--gs-text-muted); }
.gsw__search-ico svg { width: 100%; height: 100%; }

.gsw__search input {
    flex: 1;
    border: 0;
    outline: none;
    background: transparent;
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.82rem;
}

.gsw__search input::placeholder { color: var(--gs-text-muted); }

.gsw__search-x {
    border: 0;
    background: none;
    color: var(--gs-text-muted);
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.22s ease;
}
.gsw__search-x:hover { color: var(--gs-error); transform: rotate(90deg); }

/* چیپ‌های فیلتر */
.gsw__filters {
    position: relative;
    display: flex;
    gap: 0.4rem;
    margin-top: 0.6rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    scrollbar-width: none;
}

.gsw__filters::-webkit-scrollbar { display: none; }

.gsw__filter {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex: none;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font: inherit;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.26s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.gsw__filter b {
    font-size: 0.66rem;
    font-weight: 800;
    padding: 0 0.3rem;
    border-radius: 6px;
    background: var(--gs-glass-hover);
    font-variant-numeric: tabular-nums;
}

.gsw__filter-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: 0.55; }

.gsw__filter:hover { border-color: var(--gs-border-hover); color: var(--gs-text-primary); transform: translateY(-1.5px); }

.gsw__filter.is-active {
    border-color: var(--gs-border-strong);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    box-shadow: 0 3px 14px var(--gs-gold-glow);
}

.gsw__filter.is-active .gsw__filter-dot { opacity: 1; }
.gsw__filter--success.is-active { background: var(--gs-success-soft); color: var(--gs-success); border-color: color-mix(in srgb, var(--gs-success) 40%, transparent); box-shadow: none; }
.gsw__filter--warn.is-active { background: var(--gs-warning-soft); color: var(--gs-warning); border-color: color-mix(in srgb, var(--gs-warning) 40%, transparent); box-shadow: none; }
.gsw__filter--danger.is-active { background: var(--gs-error-soft); color: var(--gs-error); border-color: color-mix(in srgb, var(--gs-error) 40%, transparent); box-shadow: none; }

/* ── لیست ──────────────────────────────────────────────── */
.gsw__list {
    position: relative;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-inline-end: 3px;
}

.gsw__row {
    position: relative;
    border-radius: 14px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    overflow: hidden;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s ease, box-shadow 0.28s ease;
    animation: gsw-row-in 0.45s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
    animation-delay: calc(var(--i) * 45ms);
}

.gsw__row:hover { border-color: var(--gs-border-hover); background: var(--gs-glass-hover); }

.gsw__row.is-open {
    border-color: var(--gs-border-strong);
    background: var(--gs-bg-elevated);
    box-shadow: var(--gs-shadow-sm);
}

.gsw__rail {
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    transition: opacity 0.3s ease;
}

.gsw__rail.is-success { background: var(--gs-success); }
.gsw__rail.is-warn { background: var(--gs-warning); }
.gsw__rail.is-danger { background: var(--gs-error); }
.gsw__rail.is-muted { background: var(--gs-border); }

.gsw__row-main {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.7rem 0.9rem;
    border: 0;
    background: none;
    color: inherit;
    font: inherit;
    text-align: start;
    cursor: pointer;
}

.gsw__row-anchor {
    flex: none;
    padding: 0.14rem 0.45rem;
    border-radius: 7px;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.02em;
}

.gsw__row-anchor.is-serial {
    background: var(--gs-info-soft);
    color: var(--gs-info);
    border: 1px solid color-mix(in srgb, var(--gs-info) 30%, transparent);
}

.gsw__row-anchor.is-invoice {
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    border: 1px solid var(--gs-border);
}

.gsw__row-info { flex: 1; display: flex; flex-direction: column; gap: 0.15rem; }

.gsw__item-name {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    font-size: 0.84rem;
    font-weight: 600;
}

.gsw__mono {
    font-family: ui-monospace, "SFMono-Regular", monospace;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--gs-text-primary);
}

.gsw__cust { color: var(--gs-text-secondary); font-weight: 500; font-size: 0.78rem; }

.gsw__pill {
    padding: 0.05rem 0.4rem;
    border-radius: 6px;
    background: var(--gs-glass-hover);
    border: 1px solid var(--gs-border-soft);
    color: var(--gs-text-muted);
    font-size: 0.64rem;
    font-weight: 600;
}

.gsw__row-sub { font-size: 0.72rem; color: var(--gs-text-muted); }

.gsw__badge {
    flex: none;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
    border: 1px solid transparent;
}

.gsw-badge-success { background: var(--gs-success-soft); color: var(--gs-success); border-color: color-mix(in srgb, var(--gs-success) 32%, transparent); }
.gsw-badge-danger { background: var(--gs-error-soft); color: var(--gs-error); border-color: color-mix(in srgb, var(--gs-error) 32%, transparent); }
.gsw-badge-muted { background: var(--gs-glass-hover); color: var(--gs-text-muted); border-color: var(--gs-border-soft); }

.gsw__chev {
    display: grid;
    place-items: center;
    width: 16px;
    height: 16px;
    flex: none;
    color: var(--gs-text-muted);
    transition: transform 0.34s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)), color 0.25s ease;
}

.gsw__chev svg { width: 100%; height: 100%; }
.gsw__chev.is-open { transform: rotate(180deg); color: var(--gs-gold); }

/* ── جزئیات ────────────────────────────────────────────── */
.gsw__detail {
    padding: 0 0.9rem 0.9rem;
    border-top: 1px dashed var(--gs-border-soft);
    margin-top: 0.1rem;
    padding-top: 0.8rem;
}

.gsw__locked {
    margin: 0 0 0.7rem;
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    background: var(--gs-warning-soft);
    border: 1px solid color-mix(in srgb, var(--gs-warning) 28%, transparent);
    color: var(--gs-warning);
    font-size: 0.74rem;
    line-height: 1.8;
}

.gsw__facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.45rem;
    margin-bottom: 0.8rem;
}

.gsw__fact {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.5rem 0.7rem;
    border-radius: 11px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    transition: border-color 0.25s ease, transform 0.3s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.gsw__fact:hover { border-color: var(--gs-border-hover); transform: translateY(-2px); }
.gsw__fact--wide { grid-column: 1 / -1; }
.gsw__fact span { font-size: 0.66rem; color: var(--gs-text-muted); }
.gsw__fact b { font-size: 0.82rem; font-weight: 700; color: var(--gs-text-primary); }

.gsw__none { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
.gsw__muted { margin: 0; color: var(--gs-text-secondary); font-size: 0.78rem; }

/* ── فرم ───────────────────────────────────────────────── */
.gsw__form { display: flex; flex-direction: column; gap: 0.7rem; }

.gsw__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.6rem;
}

.gsw__group { display: flex; flex-direction: column; gap: 0.3rem; }

.gsw__label { font-size: 0.72rem; font-weight: 600; color: var(--gs-text-secondary); }
.gsw__label i { color: var(--gs-gold); font-style: normal; }

.gsw__input {
    width: 100%;
    height: 38px;
    padding: 0 0.75rem;
    border-radius: 11px;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-elevated);
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.82rem;
    outline: none;
    transition: all 0.26s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.gsw__input::placeholder { color: var(--gs-text-muted); }

.gsw__input:hover { border-color: var(--gs-border-hover); }

.gsw__input:focus {
    border-color: var(--gs-border-strong);
    box-shadow: 0 0 0 4px var(--gs-gold-muted);
}

.gsw__selectwrap { position: relative; }

.gsw__select { appearance: none; padding-inline-end: 1.8rem; cursor: pointer; }

.gsw__selectchev {
    position: absolute;
    inset-inline-end: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gs-text-muted);
    pointer-events: none;
    font-size: 0.75rem;
}

/* سگمنت واحد */
.gsw__seg {
    position: relative;
    display: grid;
    grid-template-columns: repeat(var(--n), 1fr);
    height: 38px;
    padding: 3px;
    border-radius: 11px;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-soft);
}

.gsw__seg-pill {
    position: absolute;
    top: 3px;
    bottom: 3px;
    width: calc((100% - 6px) / var(--n));
    inset-inline-start: calc(3px + var(--idx) * ((100% - 6px) / var(--n)));
    border-radius: 9px;
    background: var(--gs-gold-grad);
    box-shadow: 0 3px 12px var(--gs-gold-glow);
    transition: inset-inline-start 0.4s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    pointer-events: none;
}

.gsw__seg-btn {
    position: relative;
    z-index: 1;
    border: 0;
    background: none;
    border-radius: 9px;
    font: inherit;
    font-size: 0.76rem;
    color: var(--gs-text-secondary);
    cursor: pointer;
    transition: color 0.25s ease;
}

.gsw__seg-btn:hover { color: var(--gs-text-primary); }
.gsw__seg-btn.is-active { color: #14100a; font-weight: 800; }

.gsw__link {
    align-self: flex-start;
    border: 0;
    background: none;
    padding: 0;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--gs-gold);
    cursor: pointer;
}

.gsw__link:hover { text-decoration: underline; text-underline-offset: 3px; }

.gsw__provider {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.85rem;
    border-radius: 13px;
    border: 1px dashed var(--gs-border-hover);
    background: var(--gs-glass);
}

.gsw__form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.2rem; }

/* ── دکمه‌ها ───────────────────────────────────────────── */
.gsw__btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.45rem 0.95rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    overflow: hidden;
    white-space: nowrap;
    transition:
        transform 0.26s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)),
        background 0.24s ease, border-color 0.24s ease, color 0.24s ease, box-shadow 0.24s ease, opacity 0.2s ease;
}

.gsw__btn:active:not(:disabled) { transform: scale(0.96); }
.gsw__btn:disabled { opacity: 0.5; cursor: not-allowed; }

.gsw__btn--gold {
    background: var(--gs-gold-grad);
    color: #14100a;
    box-shadow: 0 5px 18px var(--gs-gold-glow);
}
.gsw__btn--gold:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 9px 26px var(--gs-gold-glow); }

.gsw__btn--soft {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
}
.gsw__btn--soft:hover:not(:disabled) { background: var(--gs-glass-hover); transform: translateY(-2px); }

.gsw__btn--plain {
    background: var(--gs-glass);
    border-color: var(--gs-border);
    color: var(--gs-text-secondary);
}
.gsw__btn--plain:hover { color: var(--gs-text-primary); border-color: var(--gs-border-hover); }

.gsw__sheen {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40%;
    background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    transform: translateX(-160%);
}

.gsw__btn--gold:hover:not(:disabled) .gsw__sheen { animation: gsw-sheen 0.9s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }

.gsw__spin {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid currentColor;
    border-top-color: transparent;
    animation: gsw-spin 0.7s linear infinite;
    opacity: 0.8;
}

.gsw__spin--dark { border-color: rgba(20, 16, 10, 0.3); border-top-color: transparent; }

/* ── وضعیت‌ها ──────────────────────────────────────────── */
.gsw__status { text-align: center; padding: 1.6rem 0; color: var(--gs-text-muted); font-size: 0.84rem; }

.gsw__empty { text-align: center; padding: 2.2rem 1rem 1.6rem; }
.gsw__empty-ico { display: block; font-size: 2.4rem; margin-bottom: 0.5rem; animation: gsw-float 3.6s ease-in-out infinite; }
.gsw__empty-title { margin: 0 0 0.25rem; font-size: 0.9rem; font-weight: 700; color: var(--gs-text-primary); }

.gsw__skeletons { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }

.gsw__skeleton {
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(90deg, var(--gs-glass) 25%, var(--gs-glass-hover) 50%, var(--gs-glass) 75%);
    background-size: 200% 100%;
    animation: gsw-shimmer 1.4s linear infinite;
    animation-delay: calc(var(--i) * 90ms);
}

.gsw__banner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.45rem;
    margin-top: 1rem;
    padding: 0.9rem 1rem;
    border-radius: 13px;
    border: 1px solid color-mix(in srgb, var(--gs-error) 45%, transparent);
    background: var(--gs-error-soft);
    color: var(--gs-error);
    font-size: 0.82rem;
}

.gsw__retry {
    padding: 0.32rem 0.8rem;
    border: 0;
    border-radius: 8px;
    background: var(--gs-error);
    color: #fff;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
}

.gsw__inline-error {
    margin: 0;
    padding: 0.5rem 0.75rem;
    border-radius: 10px;
    background: var(--gs-error-soft);
    border: 1px solid color-mix(in srgb, var(--gs-error) 30%, transparent);
    color: var(--gs-error);
    font-size: 0.76rem;
}

/* ── فوتر ──────────────────────────────────────────────── */
.gsw__foot {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-top: 0.9rem;
    padding-top: 0.8rem;
    border-top: 1px solid var(--gs-border-soft);
}

.gsw__foot-hint { font-size: 0.68rem; color: var(--gs-text-muted); }

/* ── ترنزیشن‌ها ───────────────────────────────────────── */
.gsw-fade-enter-active, .gsw-fade-leave-active { transition: opacity 0.26s ease; }
.gsw-fade-enter-from, .gsw-fade-leave-to { opacity: 0; }

.gsw-zoom-enter-active { transition: all 0.42s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)); }
.gsw-zoom-leave-active { transition: all 0.2s ease; }
.gsw-zoom-enter-from, .gsw-zoom-leave-to { opacity: 0; transform: translateY(18px) scale(0.95); }

.gsw-expand-enter-active, .gsw-expand-leave-active {
    transition: all 0.34s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    overflow: hidden;
}
.gsw-expand-enter-from, .gsw-expand-leave-to { opacity: 0; max-height: 0; transform: translateY(-6px); }
.gsw-expand-enter-to, .gsw-expand-leave-from { opacity: 1; max-height: 900px; }

.gsw-slide-enter-active, .gsw-slide-leave-active { transition: all 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }
.gsw-slide-enter-from, .gsw-slide-leave-to { opacity: 0; transform: translateY(-5px); }

@keyframes gsw-row-in { from { opacity: 0; transform: translateY(8px); } }
@keyframes gsw-spin { to { transform: rotate(360deg); } }
@keyframes gsw-sheen { to { transform: translateX(320%); } }
@keyframes gsw-shimmer { to { background-position: -200% 0; } }
@keyframes gsw-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }

@media (max-width: 640px) {
    .gsw { padding: 1.1rem 1rem; max-height: 93vh; }
    .gsw__row-main { flex-wrap: wrap; }
    .gsw__badge { order: 3; }
}

@media (prefers-reduced-motion: reduce) {
    .gsw *, .gsw { animation: none !important; transition-duration: 0.01ms !important; }
}
</style>
