<template>
    <Transition name="gs-fade">
        <div v-if="show" class="gs-modal-overlay" @click.self="close">
            <div class="snm-modal">
                <div class="snm-header">
                    <div>
                        <h3 class="snm-title">مدیریت شماره سریال</h3>
                        <p class="snm-subtitle">{{ itemName }}</p>
                    </div>
                    <button type="button" class="snm-close" @click="close">✕</button>
                </div>

                <p v-if="loading" class="snm-status">در حال دریافت...</p>

                <div v-else-if="loadError" class="snm-error-banner">
                    <strong>خطا در دریافت اطلاعات:</strong> {{ loadError }}
                    <button type="button" class="snm-retry" @click="load">تلاش دوباره</button>
                </div>

                <div v-else class="snm-list">
                    <div v-for="row in rows" :key="row.id" class="snm-row">
                        <template v-if="editingId === row.id">
                            <input v-model="editValue" type="text" class="snm-input" placeholder="شماره سریال..."
                                @keydown.enter.prevent="saveEdit(row)" />
                            <button class="snm-btn snm-btn-primary" :disabled="savingId === row.id"
                                @click="saveEdit(row)">ذخیره</button>
                            <button class="snm-btn snm-btn-plain" @click="cancelEdit">انصراف</button>
                        </template>
                        <template v-else>
                            <span class="snm-badge" :class="row.serial_number ? 'snm-badge-filled' : 'snm-badge-empty'">
                                {{ row.serial_number ?? 'بدون شماره سریال' }}
                            </span>
                            <span v-if="row.hasWarranty" class="snm-warranty-tag">🛡️ گارانتی‌دار</span>
                            <button class="snm-btn snm-btn-secondary" @click="startEdit(row)">
                                {{ row.serial_number ? 'ویرایش' : 'ثبت' }}
                            </button>
                            <button class="snm-btn snm-btn-danger" :disabled="deletingId === row.id"
                                @click="removeRow(row)">حذف</button>
                        </template>
                    </div>

                    <p v-if="!rows.length" class="snm-status">هیچ واحدی از این کالا در انبار نیست.</p>
                </div>

                <p v-if="actionError" class="snm-inline-error">{{ actionError }}</p>

                <div class="snm-footer">
                    <button class="snm-btn snm-btn-plain" @click="close">بستن</button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch } from 'vue'
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
        loadError.value = ''
        actionError.value = ''
    }
})

function startEdit(row) {
    editingId.value = row.id
    editValue.value = row.serial_number ?? ''
    actionError.value = ''
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

async function removeRow(row) {
    if (!confirm(row.serial_number
        ? `شماره سریال «${row.serial_number}» حذف شود؟ (یک واحد از موجودی کم می‌شود)`
        : 'این جایگاه حذف شود؟ (یک واحد از موجودی کم می‌شود)')) return

    deletingId.value = row.id
    actionError.value = ''
    try {
        await axios.delete(route('serial-numbers.destroy', row.id))
        rows.value = rows.value.filter(r => r.id !== row.id)
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
</script>

<style scoped>
.snm-modal {
    background: var(--gs-bg-card, #1c1c22);
    border: 1px solid var(--gs-border-strong, #3a3a42);
    border-radius: 16px;
    padding: 1.5rem;
    max-width: 560px;
    width: 100%;
    color: var(--gs-text-primary, #f0f0f0);
}

.snm-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.snm-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--gs-text-primary, #f0f0f0);
    margin: 0;
}

.snm-subtitle {
    font-size: .82rem;
    color: var(--gs-text-muted, #9a9aa5);
    margin: .2rem 0 0;
}

.snm-close {
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

.snm-status {
    text-align: center;
    padding: 1.5rem 0;
    color: var(--gs-text-muted, #9a9aa5);
    font-size: .85rem;
}

.snm-error-banner {
    background: rgba(220, 38, 38, .12);
    border: 1px solid #dc2626;
    color: #dc2626;
    border-radius: 10px;
    padding: .75rem 1rem;
    font-size: .85rem;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

.snm-retry {
    align-self: flex-start;
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: .3rem .75rem;
    font-size: .78rem;
    cursor: pointer;
}

.snm-inline-error {
    color: #dc2626;
    font-size: .8rem;
    margin-top: .5rem;
}

.snm-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    max-height: 340px;
    overflow-y: auto;
}

.snm-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .55rem .75rem;
    background: rgba(128, 128, 128, .08);
    border: 1px solid var(--gs-border, #3a3a42);
    border-radius: 10px;
}

.snm-badge {
    flex: 1;
    font-size: .82rem;
    padding: .25rem .6rem;
    border-radius: 999px;
    text-align: center;
}

.snm-badge-filled {
    background: var(--gs-success-soft);
    color: var(--gs-success);
    border: 1px solid var(--gs-success);
}

.snm-badge-empty {
    background: rgba(148, 148, 148, .15);
    color: var(--gs-text-muted, #9a9aa5);
    border: 1px solid var(--gs-border, #3a3a42);
    font-style: italic;
}

.snm-warranty-tag {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .74rem;
    font-weight: 600;
    padding: .2rem .55rem;
    border-radius: 999px;
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    border: 1px solid var(--gs-border-strong);
    white-space: nowrap;
}

.snm-input {
    flex: 1;
    background: var(--gs-bg-elevated);
    color: var(--gs-text-primary);
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: .4rem .6rem;
    font-size: .85rem;
}

.snm-btn {
    border-radius: 8px;
    padding: .4rem .7rem;
    font-size: .78rem;
    cursor: pointer;
    border: 1px solid transparent;
    white-space: nowrap;
}

.snm-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.snm-btn-primary {
    background: var(--gs-gold, #c9a24b);
    color: #1a1a1a;
    border-color: var(--gs-gold, #c9a24b);
    font-weight: 600;
}

.snm-btn-secondary {
    background: transparent;
    color: var(--gs-text-primary, #f0f0f0);
    border-color: var(--gs-border-strong, #3a3a42);
}

.snm-btn-danger {
    background: rgba(220, 38, 38, .1);
    color: #dc2626;
    border-color: rgba(220, 38, 38, .4);
}

.snm-btn-plain {
    background: transparent;
    color: var(--gs-text-muted, #9a9aa5);
    border-color: transparent;
}

.snm-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 1.25rem;
}
</style>