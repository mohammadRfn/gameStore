<!--
  resources/js/Components/SerialNumberManager.vue
  ------------------------------------------------------------------
  مدیریت شماره سریال — بازطراحی کامل (مودال شیشه‌ای، جستجوی زنده،
  فیلتر سگمنتی، ویرایش درجا، تأییدِ حذفِ درون‌ردیفی به‌جای confirm مرورگر).

  ⚠️ همهٔ مسیرها و منطق بک‌اند دست‌نخورده:
     GET    items.available-serials
     GET    items.missing-serials
     GET    items.warranty-candidates      (فقط برای برچسب گارانتی)
     PUT    serial-numbers.update
     PATCH  item-serial-numbers.assign
     DELETE serial-numbers.destroy
     props  : show | itemId | itemName        emits: close | changed
-->
<template>
    <Teleport to="body">
        <Transition name="snm-fade">
            <div v-if="show" class="snm-overlay" @click.self="close">
                <Transition name="snm-zoom" appear>
                    <div class="snm" role="dialog" aria-modal="true">
                        <span class="snm__aura" aria-hidden="true"></span>

                        <!-- سربرگ -->
                        <header class="snm__head">
                            <span class="snm__icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2.5" y="6" width="19" height="12" rx="2.5" />
                                    <path d="M6 9.5v5M9 9.5v5M12 9.5v5M15 9.5v5M18 9.5v5" />
                                </svg>
                            </span>

                            <div class="snm__head-txt">
                                <h3 class="snm__title">مدیریت شماره سریال</h3>
                                <p class="snm__subtitle">{{ itemName }}</p>
                            </div>

                            <button type="button" class="snm__close" title="بستن" @click="close">✕</button>
                        </header>

                        <!-- آمار -->
                        <div v-if="!loading && !loadError && rows.length" class="snm__stats">
                            <span class="snm__stat snm__stat--ok">
                                <b>{{ fa(filledCount) }}</b> ثبت‌شده
                            </span>
                            <span class="snm__stat">
                                <b>{{ fa(emptyCount) }}</b> بدون سریال
                            </span>
                            <span class="snm__stat snm__stat--gold">
                                <b>{{ fa(warrantyCount) }}</b> گارانتی‌دار
                            </span>
                        </div>

                        <!-- جستجو + فیلتر -->
                        <div v-if="!loading && !loadError && rows.length" class="snm__tools">
                            <label class="snm__search">
                                <span class="snm__search-ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.2-3.2" /></svg>
                                </span>
                                <input v-model="query" type="text" placeholder="جستجوی شماره سریال…" />
                                <button v-if="query" type="button" class="snm__search-x" @click="query = ''">✕</button>
                            </label>

                            <div class="snm__seg" :style="{ '--n': 3, '--idx': segIndex }">
                                <span class="snm__seg-pill" aria-hidden="true"></span>
                                <button
                                    v-for="f in filters"
                                    :key="f.key"
                                    type="button"
                                    class="snm__seg-btn"
                                    :class="{ 'is-active': filter === f.key }"
                                    @click="filter = f.key"
                                >{{ f.label }}</button>
                            </div>
                        </div>

                        <!-- بدنه -->
                        <div class="snm__body">
                            <!-- اسکلتون -->
                            <div v-if="loading" class="snm__skeletons">
                                <div v-for="n in 4" :key="n" class="snm__skeleton" :style="{ '--i': n }"></div>
                                <p class="snm__status">در حال دریافت…</p>
                            </div>

                            <!-- خطا -->
                            <div v-else-if="loadError" class="snm__banner">
                                <strong>خطا در دریافت اطلاعات</strong>
                                <span>{{ loadError }}</span>
                                <button type="button" class="snm__retry" @click="load">تلاش دوباره</button>
                            </div>

                            <!-- لیست -->
                            <TransitionGroup v-else tag="ul" name="snm-list" class="snm__list">
                                <li
                                    v-for="(row, i) in visibleRows"
                                    :key="row.id"
                                    class="snm__row"
                                    :class="{
                                        'is-editing': editingId === row.id,
                                        'is-empty': !row.serial_number,
                                        'is-danger': confirmId === row.id,
                                    }"
                                    :style="{ '--i': i }"
                                >
                                    <span class="snm__rail" aria-hidden="true"></span>

                                    <!-- تأیید حذف -->
                                    <template v-if="confirmId === row.id">
                                        <span class="snm__confirm-txt">
                                            {{ row.serial_number
                                                ? `سریال «${row.serial_number}» حذف شود؟`
                                                : 'این جایگاه حذف شود؟' }}
                                            <i>یک واحد از موجودی کم می‌شود</i>
                                        </span>
                                        <button
                                            type="button"
                                            class="snm__btn snm__btn--danger"
                                            :disabled="deletingId === row.id"
                                            @click="removeRow(row)"
                                        >
                                            <span v-if="deletingId === row.id" class="snm__spin" aria-hidden="true"></span>
                                            <span>{{ deletingId === row.id ? 'در حال حذف…' : 'بله، حذف کن' }}</span>
                                        </button>
                                        <button type="button" class="snm__btn snm__btn--plain" @click="confirmId = null">انصراف</button>
                                    </template>

                                    <!-- ویرایش -->
                                    <template v-else-if="editingId === row.id">
                                        <label class="snm__field">
                                            <input
                                                ref="editInput"
                                                v-model="editValue"
                                                type="text"
                                                dir="ltr"
                                                placeholder="شماره سریال…"
                                                @keydown.enter.prevent="saveEdit(row)"
                                                @keydown.esc.prevent="cancelEdit"
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            class="snm__btn snm__btn--gold"
                                            :disabled="savingId === row.id"
                                            @click="saveEdit(row)"
                                        >
                                            <span v-if="savingId === row.id" class="snm__spin snm__spin--dark" aria-hidden="true"></span>
                                            <span>{{ savingId === row.id ? '…' : 'ذخیره' }}</span>
                                        </button>
                                        <button type="button" class="snm__btn snm__btn--plain" @click="cancelEdit">انصراف</button>
                                    </template>

                                    <!-- نمایش -->
                                    <template v-else>
                                        <span class="snm__serial" :class="row.serial_number ? 'is-filled' : 'is-blank'">
                                            <svg v-if="row.serial_number" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m4.5 12.5 5 5 10-11" />
                                            </svg>
                                            <span class="snm__serial-txt">{{ row.serial_number ?? 'بدون شماره سریال' }}</span>
                                        </span>

                                        <span v-if="row.hasWarranty" class="snm__warranty" title="این واحد گارانتی ثبت‌شده دارد">
                                            🛡️ گارانتی‌دار
                                        </span>

                                        <button type="button" class="snm__btn snm__btn--soft" @click="startEdit(row)">
                                            {{ row.serial_number ? 'ویرایش' : 'ثبت' }}
                                        </button>

                                        <button type="button" class="snm__btn snm__btn--ghost-danger" @click="askRemove(row)">
                                            حذف
                                        </button>
                                    </template>
                                </li>
                            </TransitionGroup>

                            <p v-if="!loading && !loadError && !rows.length" class="snm__status">
                                <span class="snm__empty-ico">🗃️</span>
                                هیچ واحدی از این کالا در انبار نیست.
                            </p>

                            <p v-else-if="!loading && !loadError && rows.length && !visibleRows.length" class="snm__status">
                                نتیجه‌ای مطابق جستجو یافت نشد.
                            </p>
                        </div>

                        <Transition name="snm-slide">
                            <p v-if="actionError" class="snm__inline-error">{{ actionError }}</p>
                        </Transition>

                        <footer class="snm__foot">
                            <span class="snm__foot-hint">تغییرات بلافاصله روی موجودی انبار اعمال می‌شود</span>
                            <button type="button" class="snm__btn snm__btn--plain" @click="close">بستن</button>
                        </footer>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    show: { type: Boolean, default: false },
    itemId: { type: [Number, String], default: null },
    itemName: { type: String, default: '' },
})
const emit = defineEmits(['close', 'changed'])

const rows = ref([])
const loading = ref(false)
const loadError = ref('')
const actionError = ref('')

const editingId = ref(null)
const editValue = ref('')
const savingId = ref(null)
const deletingId = ref(null)
const confirmId = ref(null)

/* ── منطق و مسیرها: بدون تغییر ─────────────────────────── */
async function load() {
    if (!props.itemId) return
    loading.value = true
    loadError.value = ''
    actionError.value = ''
    rows.value = []

    let named = []
    let empty = []

    try {
        const { data } = await axios.get(route('items.available-serials', props.itemId))
        named = (data.serial_numbers ?? []).map(s => ({ id: s.id, serial_number: s.serial_number }))
    } catch (e) {
        console.error('available-serials fetch failed:', e)
        loadError.value = `دریافت سریال‌های ثبت‌شده ناموفق بود (${e.response?.status ?? e.message}).`
        loading.value = false
        return
    }

    try {
        const { data } = await axios.get(route('items.missing-serials', props.itemId))
        empty = (data.slots ?? []).map(s => ({ id: s.id, serial_number: null }))
    } catch (e) {
        console.error('missing-serials fetch failed:', e)
        loadError.value = `دریافت جایگاه‌های بدون سریال ناموفق بود (${e.response?.status ?? e.message}).`
        loading.value = false
        return
    }

    // اطلاعات گارانتی صرفاً برای نمایش لیبل است؛ اگر دریافتش شکست بخورد
    // نباید کل مدیریت سریال از کار بیفتد، پس خطایش را نادیده می‌گیریم.
    let warrantyMap = {}
    try {
        const { data } = await axios.get(route('items.warranty-candidates', props.itemId))
        warrantyMap = (data.rows ?? [])
            .filter(r => r.anchor === 'serial')
            .reduce((map, r) => {
                map[r.item_serial_number_id] = !!r.warranty
                return map
            }, {})
    } catch (e) {
        console.error('warranty-candidates fetch failed:', e)
    }

    rows.value = [...named, ...empty].map(r => ({ ...r, hasWarranty: !!warrantyMap[r.id] }))
    loading.value = false
}

watch(() => [props.show, props.itemId], ([visible]) => {
    if (visible) load()
    else {
        editingId.value = null
        confirmId.value = null
        query.value = ''
        filter.value = 'all'
        loadError.value = ''
        actionError.value = ''
    }
})

function startEdit(row) {
    editingId.value = row.id
    editValue.value = row.serial_number ?? ''
    actionError.value = ''
    confirmId.value = null
    nextTick(() => {
        const el = document.querySelector('.snm__field input')
        el?.focus()
        el?.select()
    })
}

function cancelEdit() {
    editingId.value = null
    editValue.value = ''
}

async function saveEdit(row) {
    const value = editValue.value.trim()
    if (!value) {
        actionError.value = 'شماره سریال نمی‌تواند خالی باشد.'
        return
    }
    savingId.value = row.id
    actionError.value = ''
    try {
        if (row.serial_number) {
            await axios.put(route('serial-numbers.update', row.id), { serial_number: value })
        } else {
            await axios.patch(route('item-serial-numbers.assign', row.id), { serial_number: value })
        }
        row.serial_number = value
        editingId.value = null
        emit('changed')
    } catch (e) {
        console.error('saveEdit failed:', e)
        if (e.response) {
            const status = e.response.status
            const body = e.response.data
            actionError.value = `خطا (کد ${status}): ${(body && typeof body === 'object' && body.message) ? body.message : JSON.stringify(body).slice(0, 200)
                }`
        } else {
            actionError.value = `خطای شبکه/جاوااسکریپت قبل از ارسال درخواست: ${e.message}`
        }
    } finally {
        savingId.value = null
    }
}

/** تأیید حذف حالا درون خود ردیف انجام می‌شود (به‌جای confirm مرورگر) */
function askRemove(row) {
    confirmId.value = row.id
    editingId.value = null
    actionError.value = ''
}

async function removeRow(row) {
    deletingId.value = row.id
    actionError.value = ''
    try {
        await axios.delete(route('serial-numbers.destroy', row.id))
        rows.value = rows.value.filter(r => r.id !== row.id)
        confirmId.value = null
        emit('changed')
    } catch (e) {
        actionError.value = e.response?.data?.message ?? 'خطا در حذف.'
    } finally {
        deletingId.value = null
    }
}

function close() {
    emit('close')
}
/* ──────────────────────────────────────────────────────── */

/* ── لایهٔ نمایشی ──────────────────────────────────────── */
const query = ref('')
const filter = ref('all')

const filters = [
    { key: 'all', label: 'همه' },
    { key: 'filled', label: 'ثبت‌شده' },
    { key: 'empty', label: 'بدون سریال' },
]

const segIndex = computed(() => filters.findIndex(f => f.key === filter.value))

const visibleRows = computed(() => {
    const q = query.value.trim().toLowerCase()
    return rows.value.filter(r => {
        if (filter.value === 'filled' && !r.serial_number) return false
        if (filter.value === 'empty' && r.serial_number) return false
        if (!q) return true
        return String(r.serial_number ?? '').toLowerCase().includes(q)
    })
})

const filledCount = computed(() => rows.value.filter(r => r.serial_number).length)
const emptyCount = computed(() => rows.value.length - filledCount.value)
const warrantyCount = computed(() => rows.value.filter(r => r.hasWarranty).length)

function fa(n) {
    return Number(n ?? 0).toLocaleString('fa-IR')
}
</script>

<style scoped>
.snm-overlay {
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

.snm {
    position: relative;
    display: flex;
    flex-direction: column;
    width: min(620px, 100%);
    max-height: 88vh;
    overflow: hidden;
    padding: 1.4rem 1.5rem 1.15rem;
    border-radius: 20px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-bg-card-strong);
    box-shadow: var(--gs-shadow-md), 0 0 60px -20px var(--gs-gold-glow);
    color: var(--gs-text-primary);
    direction: rtl;
}

.snm *, .snm *::before, .snm *::after { box-sizing: border-box; min-width: 0; }

.snm__aura {
    position: absolute;
    inset: -55% 25% 70% -25%;
    background: radial-gradient(closest-side, var(--gs-gold-glow), transparent 72%);
    opacity: 0.35;
    pointer-events: none;
}

/* ── سربرگ ─────────────────────────────────────────────── */
.snm__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding-bottom: 0.95rem;
    border-bottom: 1px solid var(--gs-border-soft);
}

.snm__icon {
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

.snm__icon svg { width: 21px; height: 21px; }
.snm:hover .snm__icon { transform: rotate(-7deg) scale(1.06); }

.snm__head-txt { flex: 1; }

.snm__title { margin: 0; font-size: 1.02rem; font-weight: 800; }

.snm__subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: var(--gs-text-secondary);
}

.snm__close {
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

.snm__close:hover {
    background: var(--gs-error-soft);
    border-color: color-mix(in srgb, var(--gs-error) 40%, transparent);
    color: var(--gs-error);
    transform: rotate(90deg);
}

/* ── آمار ──────────────────────────────────────────────── */
.snm__stats {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.85rem;
}

.snm__stat {
    padding: 0.28rem 0.6rem;
    border-radius: 9px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font-size: 0.7rem;
    font-weight: 600;
}

.snm__stat b { font-weight: 800; color: var(--gs-text-primary); font-variant-numeric: tabular-nums; }

.snm__stat--ok {
    border-color: color-mix(in srgb, var(--gs-success) 28%, transparent);
    background: var(--gs-success-soft);
    color: var(--gs-success);
}
.snm__stat--ok b { color: var(--gs-success); }

.snm__stat--gold {
    border-color: var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
}
.snm__stat--gold b { color: var(--gs-gold); }

/* ── ابزارها ───────────────────────────────────────────── */
.snm__tools {
    position: relative;
    display: flex;
    gap: 0.55rem;
    margin-top: 0.7rem;
    flex-wrap: wrap;
}

.snm__search {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1 1 200px;
    height: 38px;
    padding: 0 0.7rem;
    border-radius: 11px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    transition: all 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.snm__search:focus-within {
    border-color: var(--gs-border-strong);
    background: var(--gs-glass-hover);
    box-shadow: 0 0 0 4px var(--gs-gold-muted);
}

.snm__search-ico { display: grid; place-items: center; width: 15px; height: 15px; color: var(--gs-text-muted); flex: none; }
.snm__search-ico svg { width: 100%; height: 100%; }

.snm__search input {
    flex: 1;
    border: 0;
    outline: none;
    background: transparent;
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.8rem;
}

.snm__search input::placeholder { color: var(--gs-text-muted); }

.snm__search-x {
    border: 0;
    background: none;
    color: var(--gs-text-muted);
    font-size: 0.7rem;
    cursor: pointer;
    transition: color 0.2s ease, transform 0.25s ease;
}

.snm__search-x:hover { color: var(--gs-error); transform: rotate(90deg); }

/* سگمنت */
.snm__seg {
    position: relative;
    display: grid;
    grid-template-columns: repeat(var(--n), 1fr);
    flex: none;
    padding: 3px;
    border-radius: 11px;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-soft);
}

.snm__seg-pill {
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

.snm__seg-btn {
    position: relative;
    z-index: 1;
    padding: 0.35rem 0.7rem;
    border: 0;
    background: none;
    border-radius: 9px;
    font: inherit;
    font-size: 0.74rem;
    color: var(--gs-text-secondary);
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.25s ease;
}

.snm__seg-btn:hover { color: var(--gs-text-primary); }
.snm__seg-btn.is-active { color: #14100a; font-weight: 800; }

/* ── بدنه ──────────────────────────────────────────────── */
.snm__body {
    position: relative;
    flex: 1;
    min-height: 90px;
    overflow-y: auto;
    margin-top: 0.75rem;
    padding-inline-end: 3px;
}

.snm__list {
    position: relative;
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.snm__row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.58rem 0.75rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    overflow: hidden;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s ease, transform 0.3s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    animation: snm-row-in 0.45s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
    animation-delay: calc(var(--i) * 40ms);
}

.snm__row:hover { border-color: var(--gs-border-hover); background: var(--gs-glass-hover); }

.snm__rail {
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--gs-gold-grad);
    transform: scaleY(0);
    transition: transform 0.35s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.snm__row:hover .snm__rail,
.snm__row.is-editing .snm__rail { transform: scaleY(1); }

.snm__row.is-editing { border-color: var(--gs-border-strong); background: var(--gs-gold-muted); }

.snm__row.is-danger {
    border-color: color-mix(in srgb, var(--gs-error) 45%, transparent);
    background: var(--gs-error-soft);
}

.snm__row.is-danger .snm__rail { background: var(--gs-error); transform: scaleY(1); }

/* سریال */
.snm__serial {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1;
    padding: 0.26rem 0.65rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    font-family: ui-monospace, "SFMono-Regular", monospace;
    overflow-wrap: anywhere;
}

.snm__serial svg { width: 12px; height: 12px; flex: none; }

.snm__serial.is-filled {
    background: var(--gs-success-soft);
    color: var(--gs-success);
    border: 1px solid color-mix(in srgb, var(--gs-success) 34%, transparent);
}

.snm__serial.is-blank {
    background: var(--gs-glass-hover);
    color: var(--gs-text-muted);
    border: 1px dashed var(--gs-border);
    font-style: italic;
    font-family: inherit;
}

.snm__warranty {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    flex: none;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

/* فیلد ویرایش */
.snm__field {
    display: flex;
    flex: 1;
    height: 34px;
    padding: 0 0.6rem;
    border-radius: 9px;
    border: 1px solid var(--gs-border-strong);
    background: var(--gs-bg-elevated);
    box-shadow: 0 0 0 3px var(--gs-gold-muted);
}

.snm__field input {
    flex: 1;
    border: 0;
    outline: none;
    background: transparent;
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.82rem;
    font-family: ui-monospace, "SFMono-Regular", monospace;
    letter-spacing: 0.04em;
}

.snm__confirm-txt {
    flex: 1;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gs-error);
}

.snm__confirm-txt i {
    display: block;
    font-style: normal;
    font-size: 0.68rem;
    font-weight: 400;
    opacity: 0.8;
}

/* دکمه‌ها */
.snm__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    flex: none;
    padding: 0.38rem 0.75rem;
    border-radius: 9px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition:
        transform 0.26s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)),
        background 0.24s ease, border-color 0.24s ease, color 0.24s ease, box-shadow 0.24s ease;
}

.snm__btn:active:not(:disabled) { transform: scale(0.95); }
.snm__btn:disabled { opacity: 0.6; cursor: progress; }

.snm__btn--gold {
    background: var(--gs-gold-grad);
    color: #14100a;
    box-shadow: 0 4px 16px var(--gs-gold-glow);
}
.snm__btn--gold:hover:not(:disabled) { transform: translateY(-1.5px); box-shadow: 0 8px 22px var(--gs-gold-glow); }

.snm__btn--soft {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
}
.snm__btn--soft:hover { background: var(--gs-glass-hover); transform: translateY(-1.5px); }

.snm__btn--plain {
    background: var(--gs-glass);
    border-color: var(--gs-border);
    color: var(--gs-text-secondary);
}
.snm__btn--plain:hover { color: var(--gs-text-primary); border-color: var(--gs-border-hover); }

.snm__btn--danger {
    background: var(--gs-error);
    color: #fff;
    box-shadow: 0 4px 16px color-mix(in srgb, var(--gs-error) 35%, transparent);
}
.snm__btn--danger:hover:not(:disabled) { transform: translateY(-1.5px); filter: brightness(1.08); }

.snm__btn--ghost-danger {
    background: transparent;
    border-color: var(--gs-border);
    color: var(--gs-text-muted);
}
.snm__btn--ghost-danger:hover {
    background: var(--gs-error-soft);
    border-color: color-mix(in srgb, var(--gs-error) 35%, transparent);
    color: var(--gs-error);
}

.snm__spin {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    animation: snm-spin 0.7s linear infinite;
}

.snm__spin--dark { border-color: rgba(20, 16, 10, 0.25); border-top-color: #14100a; }

/* وضعیت‌ها */
.snm__status {
    text-align: center;
    padding: 1.6rem 0;
    color: var(--gs-text-muted);
    font-size: 0.84rem;
}

.snm__empty-ico { display: block; font-size: 1.8rem; margin-bottom: 0.35rem; }

.snm__skeletons { display: flex; flex-direction: column; gap: 0.45rem; }

.snm__skeleton {
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(90deg, var(--gs-glass) 25%, var(--gs-glass-hover) 50%, var(--gs-glass) 75%);
    background-size: 200% 100%;
    animation: snm-shimmer 1.4s linear infinite;
    animation-delay: calc(var(--i) * 90ms);
}

.snm__banner {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.9rem 1rem;
    border-radius: 13px;
    border: 1px solid color-mix(in srgb, var(--gs-error) 45%, transparent);
    background: var(--gs-error-soft);
    color: var(--gs-error);
    font-size: 0.82rem;
}

.snm__retry {
    padding: 0.32rem 0.8rem;
    border: 0;
    border-radius: 8px;
    background: var(--gs-error);
    color: #fff;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.25s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.snm__retry:hover { transform: translateY(-1.5px); }

.snm__inline-error {
    position: relative;
    margin: 0.6rem 0 0;
    padding: 0.5rem 0.75rem;
    border-radius: 10px;
    background: var(--gs-error-soft);
    border: 1px solid color-mix(in srgb, var(--gs-error) 30%, transparent);
    color: var(--gs-error);
    font-size: 0.76rem;
}

/* فوتر */
.snm__foot {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-top: 0.9rem;
    padding-top: 0.8rem;
    border-top: 1px solid var(--gs-border-soft);
}

.snm__foot-hint { font-size: 0.68rem; color: var(--gs-text-muted); }

/* ترنزیشن‌ها */
.snm-fade-enter-active, .snm-fade-leave-active { transition: opacity 0.26s ease; }
.snm-fade-enter-from, .snm-fade-leave-to { opacity: 0; }

.snm-zoom-enter-active { transition: all 0.42s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)); }
.snm-zoom-leave-active { transition: all 0.2s ease; }
.snm-zoom-enter-from, .snm-zoom-leave-to { opacity: 0; transform: translateY(18px) scale(0.95); }

.snm-list-move { transition: transform 0.4s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }
.snm-list-enter-from, .snm-list-leave-to { opacity: 0; transform: translateX(-14px); }
.snm-list-leave-active { position: absolute; width: 100%; }

.snm-slide-enter-active, .snm-slide-leave-active { transition: all 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }
.snm-slide-enter-from, .snm-slide-leave-to { opacity: 0; transform: translateY(-5px); }

@keyframes snm-row-in { from { opacity: 0; transform: translateY(7px); } }
@keyframes snm-spin { to { transform: rotate(360deg); } }
@keyframes snm-shimmer { to { background-position: -200% 0; } }

@media (max-width: 600px) {
    .snm { padding: 1.1rem 1rem; max-height: 92vh; }
    .snm__row { flex-wrap: wrap; }
    .snm__serial { flex: 1 1 100%; }
}

@media (prefers-reduced-motion: reduce) {
    .snm *, .snm { animation: none !important; transition-duration: 0.01ms !important; }
}
</style>
