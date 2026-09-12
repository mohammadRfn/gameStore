<template>
    <Transition name="gs-fade">
        <div v-if="show" class="gsw-overlay" @click.self="close">
            <div class="gsw-modal">
                <div class="gsw-header">
                    <div>
                        <h3 class="gsw-title">مدیریت گارانتی</h3>
                        <p class="gsw-subtitle">{{ itemName }}</p>
                    </div>
                    <button type="button" class="gsw-close" @click="close">✕</button>
                </div>

                <p v-if="loading" class="gsw-status">در حال دریافت اطلاعات...</p>

                <div v-else-if="loadError" class="gsw-error-banner">
                    <strong>خطا در دریافت اطلاعات:</strong> {{ loadError }}
                    <button type="button" class="gsw-retry" @click="fetchCandidates">تلاش دوباره</button>
                </div>

                <div v-else-if="!candidates.length" class="gsw-status">
                    <p style="font-size:2rem;margin:0 0 .4rem">🛡️</p>
                    هنوز هیچ فروشی برای این کالا ثبت نشده.<br />
                    <span class="gsw-muted">گارانتی روی قلم‌های فروخته‌شده (order item) ثبت می‌شود.</span>
                </div>

                <template v-else>
                    <div class="gsw-search-bar">
                        <div class="gsw-search-field">
                            <span class="gsw-search-icon">🔍</span>
                            <input
                                v-model="searchQuery"
                                type="text"
                                class="gsw-search-input"
                                placeholder="جستجو بر اساس شماره سریال، مشتری یا شماره فاکتور..."
                            />
                        </div>
                        <select v-model="statusFilter" class="gsw-status-select">
                            <option value="all">همه وضعیت‌ها</option>
                            <option value="active">فعال</option>
                            <option value="pending">در انتظار پرداخت</option>
                            <option value="expired">منقضی‌شده</option>
                            <option value="unregistered">ثبت‌نشده</option>
                        </select>
                    </div>

                    <div v-if="!filteredCandidates.length" class="gsw-status">
                        نتیجه‌ای مطابق فیلتر یافت نشد.
                    </div>

                    <div v-else class="gsw-list">
                        <div v-for="c in filteredCandidates" :key="c.key" class="gsw-row">
                        <div class="gsw-row-main" @click="toggle(c.key)">
                            <div class="gsw-row-info">
                                <p class="gsw-item-name">
                                    <template v-if="c.anchor === 'serial'">
                                        سریال: {{ c.serial_number ?? '—' }}
                                        <span v-if="c.in_stock" class="gsw-muted">— در انبار</span>
                                    </template>
                                    <template v-else>
                                        فاکتور {{ c.invoice_number ?? '—' }}
                                    </template>
                                    <span v-if="c.customer_name" class="gsw-muted">— {{ c.customer_name }}</span>
                                </p>
                                <p class="gsw-muted">
                                    <template v-if="c.anchor !== 'serial'">تعداد: {{ c.quantity }}</template>
                                    <template v-else-if="c.invoice_number">فاکتور: {{ c.invoice_number }}</template>
                                    <template v-if="c.payment_status"> — {{ paymentLabel(c.payment_status) }}</template>
                                </p>
                            </div>
                            <span class="gsw-badge" :class="statusBadgeClass(c)">{{ statusLabel(c) }}</span>
                        </div>

                        <div v-if="expanded === c.key" class="gsw-row-detail">
                            <p v-if="!c.editable" class="gsw-locked-note">
                                🔒 چون فاکتور این قلم پرداخت‌شده و هنوز مرجوع نشده، گارانتی آن قابل ویرایش نیست.
                            </p>

                            <template v-if="c.warranty && editingId !== c.key">
                                <div class="gsw-view">
                                    <p><span class="gsw-muted">مدت گارانتی:</span> {{ c.warranty.duration_value }} {{
                                        unitLabel(c.warranty.duration_unit) }}</p>
                                    <p v-if="c.warranty.provider"><span class="gsw-muted">ارائه‌دهنده:</span> {{
                                        c.warranty.provider.name }}</p>
                                    <p v-if="c.warranty.starts_at"><span class="gsw-muted">شروع:</span> {{
                                        formatDate(c.warranty.starts_at) }}</p>
                                    <p v-if="c.warranty.expires_at"><span class="gsw-muted">انقضا:</span> {{
                                        formatDate(c.warranty.expires_at) }}</p>
                                    <p v-if="c.warranty.notes"><span class="gsw-muted">یادداشت:</span> {{
                                        c.warranty.notes }}</p>
                                    <button v-if="c.editable" type="button" class="gsw-btn gsw-btn-secondary"
                                        @click="startEdit(c)">
                                        ویرایش گارانتی
                                    </button>
                                </div>
                            </template>

                            <template v-else-if="c.editable">
                                <div v-if="editingId !== c.key" class="gsw-view">
                                    <p class="gsw-muted">هنوز گارانتی‌ای برای این قلم ثبت نشده.</p>
                                    <button type="button" class="gsw-btn gsw-btn-primary" @click="startEdit(c)">+ ثبت
                                        گارانتی</button>
                                </div>

                                <div v-else class="gsw-form">
                                    <div class="gsw-form-grid">
                                        <div class="gsw-input-group">
                                            <label class="gsw-label">مدت *</label>
                                            <input v-model="form.duration_value" type="number" min="1"
                                                class="gsw-input" />
                                        </div>
                                        <div class="gsw-input-group">
                                            <label class="gsw-label">واحد</label>
                                            <select v-model="form.duration_unit" class="gsw-input">
                                                <option value="day">روز</option>
                                                <option value="month">ماه</option>
                                                <option value="year">سال</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gsw-input-group">
                                        <label class="gsw-label">ارائه‌دهنده گارانتی (اختیاری)</label>
                                        <select v-model="form.warranty_provider_id" class="gsw-input">
                                            <option :value="null">بدون ارائه‌دهنده</option>
                                            <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}
                                            </option>
                                        </select>
                                        <button type="button" class="gsw-btn gsw-btn-plain" style="margin-top:.4rem"
                                            @click="showNewProvider = !showNewProvider">
                                            {{ showNewProvider ? '− انصراف' : '+ افزودن ارائه‌دهنده جدید' }}
                                        </button>
                                    </div>

                                    <div v-if="showNewProvider" class="gsw-new-provider">
                                        <div class="gsw-input-group">
                                            <label class="gsw-label">نام ارائه‌دهنده *</label>
                                            <input v-model="newProvider.name" type="text" class="gsw-input" />
                                        </div>
                                        <div class="gsw-form-grid">
                                            <div class="gsw-input-group">
                                                <label class="gsw-label">تلفن</label>
                                                <input v-model="newProvider.phone" type="text" class="gsw-input" />
                                            </div>
                                            <div class="gsw-input-group">
                                                <label class="gsw-label">ایمیل</label>
                                                <input v-model="newProvider.email" type="text" class="gsw-input" />
                                            </div>
                                        </div>
                                        <div class="gsw-form-grid">
                                            <div class="gsw-input-group">
                                                <label class="gsw-label">سایت</label>
                                                <input v-model="newProvider.website" type="text" class="gsw-input" />
                                            </div>
                                            <div class="gsw-input-group">
                                                <label class="gsw-label">اینستاگرام</label>
                                                <input v-model="newProvider.instagram" type="text" class="gsw-input" />
                                            </div>
                                        </div>
                                        <div class="gsw-input-group">
                                            <label class="gsw-label">آدرس</label>
                                            <input v-model="newProvider.address" type="text" class="gsw-input" />
                                        </div>
                                        <div class="gsw-input-group">
                                            <label class="gsw-label">توضیحات</label>
                                            <input v-model="newProvider.description" type="text" class="gsw-input" />
                                        </div>
                                        <button type="button" class="gsw-btn gsw-btn-secondary"
                                            :disabled="!newProvider.name.trim() || savingProvider"
                                            @click="createProvider">
                                            {{ savingProvider ? '...' : 'افزودن و انتخاب' }}
                                        </button>
                                    </div>

                                    <div class="gsw-input-group">
                                        <label class="gsw-label">یادداشت</label>
                                        <input v-model="form.notes" type="text" class="gsw-input"
                                            placeholder="توضیح اختیاری..." />
                                    </div>

                                    <p v-if="formError" class="gsw-inline-error">{{ formError }}</p>

                                    <div class="gsw-form-actions">
                                        <button type="button" class="gsw-btn gsw-btn-plain"
                                            @click="cancelEdit">انصراف</button>
                                        <button type="button" class="gsw-btn gsw-btn-primary" :disabled="saving"
                                            @click="saveWarranty(c)">
                                            {{ saving ? '...' : 'ذخیره' }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    </div>
                </template>

                <div class="gsw-footer">
                    <button class="gsw-btn gsw-btn-plain" @click="close">بستن</button>
                </div>
            </div>
        </div>
    </Transition>
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
</script>

<style scoped>
.gsw-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .5);
    backdrop-filter: blur(3px);
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.gsw-overlay *,
.gsw-overlay *::before,
.gsw-overlay *::after {
    box-sizing: border-box;
    min-width: 0;
    overflow-wrap: break-word;
    word-break: break-word;
}

.gsw-modal {
    background: var(--gs-bg-card, #1c1c22);
    border: 1px solid var(--gs-border-strong, #3a3a42);
    border-radius: 16px;
    padding: 1.5rem;
    width: min(640px, 100%);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    color: var(--gs-text-primary, #f0f0f0);
}

.gsw-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.gsw-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--gs-text-primary, #f0f0f0);
    margin: 0;
}

.gsw-subtitle {
    font-size: .82rem;
    color: var(--gs-text-muted, #9a9aa5);
    margin: .2rem 0 0;
}

.gsw-close {
    background: rgba(128, 128, 128, .15);
    border: 1px solid var(--gs-border, #3a3a42);
    color: var(--gs-text-primary, #f0f0f0);
    border-radius: 8px;
    width: 28px;
    height: 28px;
    cursor: pointer;
    font-size: .8rem;
    line-height: 1;
}

.gsw-status {
    text-align: center;
    padding: 1.75rem 0;
    color: var(--gs-text-muted, #9a9aa5);
    font-size: .85rem;
}

.gsw-muted {
    color: var(--gs-text-muted, #9a9aa5);
}

.gsw-error-banner {
    background: var(--gs-error-soft);
    border: 1px solid var(--gs-error);
    color: var(--gs-error);
    border-radius: 10px;
    padding: .75rem 1rem;
    font-size: .85rem;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

.gsw-retry {
    align-self: flex-start;
    background: var(--gs-error);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: .3rem .75rem;
    font-size: .78rem;
    cursor: pointer;
}

.gsw-inline-error {
    color: var(--gs-error);
    font-size: .8rem;
    margin-top: .5rem;
}

.gsw-search-bar {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: .9rem;
    flex-shrink: 0;
}

.gsw-search-field {
    flex: 1 1 220px;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: .5rem;
    background: var(--gs-bg-elevated);
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: 0 .7rem;
}

.gsw-search-icon {
    flex-shrink: 0;
    font-size: .78rem;
    opacity: .6;
}

.gsw-search-input {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
    background: transparent;
    border: none;
    outline: none;
    color: var(--gs-text-primary, #f0f0f0);
    font-size: .85rem;
    padding: .5rem 0;
}

.gsw-search-input::placeholder {
    color: var(--gs-text-muted, #9a9aa5);
}

.gsw-status-select {
    flex: 1 1 140px;
    max-width: 100%;
    min-width: 0;
    width: auto;
    background: var(--gs-bg-elevated);
    color: var(--gs-text-primary);
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: .5rem .7rem;
    font-size: .8rem;
}

.gsw-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}

.gsw-row {
    border: 1px solid var(--gs-border, #3a3a42);
    border-radius: 10px;
    background: rgba(128, 128, 128, .06);
    overflow: visible;
}

.gsw-row-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    padding: .65rem .9rem;
    cursor: pointer;
}

.gsw-row-main:hover {
    background: rgba(128, 128, 128, .1);
}

.gsw-row-info {
    min-width: 0;
    flex: 1 1 auto;
}

.gsw-item-name {
    font-weight: 600;
    font-size: .88rem;
    margin: 0;
    color: var(--gs-text-primary, #f0f0f0);
    overflow-wrap: break-word;
    word-break: break-word;
}

.gsw-row-info p {
    margin: .15rem 0 0;
    font-size: .78rem;
    overflow-wrap: break-word;
    word-break: break-word;
}

.gsw-badge {
    flex-shrink: 0;
}

.gsw-row-detail {
    padding: 0 .9rem .9rem;
    border-top: 1px solid var(--gs-border, #3a3a42);
}

.gsw-badge {
    font-size: .74rem;
    padding: .2rem .6rem;
    border-radius: 999px;
    white-space: nowrap;
}

.gsw-badge-muted {
    background: rgba(148, 148, 148, .18);
    color: var(--gs-text-muted, #9a9aa5);
}

.gsw-badge-success {
    background: var(--gs-success-soft);
    color: var(--gs-success);
    border: 1px solid var(--gs-success);
}

.gsw-badge-danger {
    background: var(--gs-error-soft);
    color: var(--gs-error);
    border: 1px solid var(--gs-error);
}

.gsw-locked-note {
    background: rgba(128, 128, 128, .1);
    border-radius: 8px;
    padding: .55rem .75rem;
    font-size: .78rem;
    margin: .75rem 0 0;
    color: var(--gs-text-muted, #9a9aa5);
}

.gsw-view {
    padding-top: .75rem;
    display: flex;
    flex-direction: column;
    gap: .3rem;
    font-size: .85rem;
}

.gsw-form {
    padding-top: .75rem;
}

.gsw-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: .5rem .75rem;
}

.gsw-form-actions {
    display: flex;
    gap: .6rem;
    justify-content: flex-end;
    margin-top: .5rem;
}

.gsw-input-group {
    margin-bottom: .75rem;
}

.gsw-label {
    display: block;
    font-size: .78rem;
    color: var(--gs-text-muted, #9a9aa5);
    margin-bottom: .3rem;
}

.gsw-input {
    width: 100%;
    box-sizing: border-box;
    background: var(--gs-bg-elevated);
    color: var(--gs-text-primary);
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: .5rem .7rem;
    font-size: .85rem;
}

.gsw-new-provider {
    border: 1px dashed var(--gs-border, #3a3a42);
    border-radius: 10px;
    padding: .75rem;
    margin: .5rem 0;
}

.gsw-btn {
    border-radius: 8px;
    padding: .4rem .8rem;
    font-size: .8rem;
    cursor: pointer;
    border: 1px solid transparent;
    white-space: normal;
    max-width: 100%;
}

.gsw-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.gsw-btn-primary {
    background: var(--gs-gold, #c9a24b);
    color: #1a1a1a;
    border-color: var(--gs-gold, #c9a24b);
    font-weight: 600;
}

.gsw-btn-secondary {
    background: transparent;
    color: var(--gs-text-primary, #f0f0f0);
    border-color: var(--gs-border-strong, #3a3a42);
}

.gsw-btn-plain {
    background: transparent;
    color: var(--gs-text-muted, #9a9aa5);
    border-color: transparent;
}

.gsw-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 1.25rem;
}
</style>