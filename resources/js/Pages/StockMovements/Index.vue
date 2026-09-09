<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">انبارگردانی</h1>
                    <p class="gs-subtitle">ورود و خروج اقلام انبار</p>
                </div>
                <button @click="showForm = true" class="gs-btn gs-btn-primary">+ ثبت حرکت انبار</button>
            </div>
        </template>

        <!-- Stock summary per item -->
        <div class="gs-stock-grid" style="margin-bottom:1.5rem">
            <div v-for="item in stockSummary" :key="item.id" class="gs-card gs-stock-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
                    <p class="gs-item-name">{{ item.name }}</p>
                    <span :class="['gs-badge', item.current_stock > 0 ? 'gs-badge-success' : 'gs-badge-error']">
                        {{ item.current_stock }}
                    </span>
                </div>
                <p class="gs-muted">قیمت: {{ formatPrice(item.price) }}</p>
            </div>
        </div>

        <!-- Movements Table -->
        <div class="gs-card" style="padding:0;overflow:hidden">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border)">
                <span class="gs-subtitle">تاریخچه حرکات انبار</span>
            </div>
            <table class="gs-table" v-if="movements.data?.length">
                <thead>
                    <tr>
                        <th>قلم</th>
                        <th>نوع</th>
                        <th>تعداد</th>
                        <th>دلیل</th>
                        <th>یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in movements.data" :key="m.id">
                        <td style="font-weight:500">{{ m.item?.name ?? '—' }}</td>
                        <td>
                            <span :class="['gs-badge', moveBadge(m.movement_type)]">{{ moveLabel(m.movement_type) }}</span>
                        </td>
                        <td :class="isIn(m.movement_type) ? 'gs-in' : 'gs-out'">
                            {{ isIn(m.movement_type) ? '+' : '-' }}{{ m.quantity }}
                        </td>
                        <td class="gs-muted">{{ m.reason ?? '—' }}</td>
                        <td class="gs-muted">{{ m.note ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p style="font-size:2rem">📦</p>
                <p class="gs-subtitle">حرکتی ثبت نشده</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="gs-pagination" v-if="movements.last_page > 1">
            <Link v-if="movements.prev_page_url" :href="movements.prev_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">قبلی</Link>
            <span class="gs-label">{{ movements.current_page }} / {{ movements.last_page }}</span>
            <Link v-if="movements.next_page_url" :href="movements.next_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">بعدی</Link>
        </div>

        <!-- Manual Movement Modal -->
        <Transition name="gs-fade">
            <div v-if="showForm" class="gs-modal-overlay" @click.self="showForm=false">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:1.25rem">ثبت حرکت دستی انبار</h3>

                    <div class="gs-input-group">
                        <label class="gs-input-label">قلم <span style="color:var(--gs-error)">*</span></label>
                        <select v-model="moveForm.item_id" class="gs-input" :class="{'gs-input-error': moveForm.errors.item_id}">
                            <option value="">انتخاب قلم...</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                        <span v-if="moveForm.errors.item_id" class="gs-error-msg">{{ moveForm.errors.item_id }}</span>
                    </div>

                    <div class="gs-form-grid">
                        <div class="gs-input-group">
                            <label class="gs-input-label">نوع <span style="color:var(--gs-error)">*</span></label>
                            <select v-model="moveForm.movement_type" class="gs-input">
                                <option value="in">ورودی</option>
                                <option value="out">خروجی</option>
                                <option value="adjust_in">تنظیم مثبت</option>
                                <option value="adjust_out">تنظیم منفی</option>
                            </select>
                        </div>
                        <div class="gs-input-group">
                            <label class="gs-input-label">تعداد <span style="color:var(--gs-error)">*</span></label>
                            <input v-model="moveForm.quantity" type="number" min="1" class="gs-input"
                                :class="{'gs-input-error': moveForm.errors.quantity}" />
                            <span v-if="moveForm.errors.quantity" class="gs-error-msg">{{ moveForm.errors.quantity }}</span>
                        </div>
                    </div>

                    <!-- Serial numbers for inbound movement: optional, add one at a time -->
                    <div class="gs-input-group" v-if="selectedItemHasSerial && isInboundType">
                        <label class="gs-input-label">
                            شماره سریال (اختیاری)
                            <span class="gs-label">— {{ enteredSerials.length }} از {{ moveForm.quantity || 0 }} وارد شده</span>
                        </label>
                        <p class="gs-label" style="margin:.2rem 0 .5rem">
                            وارد کردن شماره سریال اختیاری است؛ برای هر واحدی که سریال وارد نکنی، یک جایگاه «بدون شماره
                            سریال» در انبار ثبت می‌شود که بعداً از لیست محصولات قابل تکمیل است.
                        </p>

                        <div style="display:flex;gap:.5rem">
                            <input v-model="newSerialInput" type="text" class="gs-input"
                                placeholder="شماره سریال را وارد و اضافه کن..."
                                @keydown.enter.prevent="addSerial" />
                            <button type="button" class="gs-btn gs-btn-secondary gs-btn-sm"
                                :disabled="!newSerialInput.trim() || enteredSerials.length >= moveForm.quantity"
                                @click="addSerial">
                                افزودن
                            </button>
                        </div>

                        <div v-if="enteredSerials.length" class="gs-serial-chips">
                            <span v-for="(s, idx) in enteredSerials" :key="idx" class="gs-serial-chip">
                                {{ s }}
                                <button type="button" @click="removeSerial(idx)">✕</button>
                            </span>
                        </div>

                        <span v-if="moveForm.errors.serial_numbers" class="gs-error-msg">{{ moveForm.errors.serial_numbers }}</span>
                    </div>

                    <!-- Serial numbers for outbound/adjust-out movement: optional pick -->
                    <div class="gs-input-group" v-if="selectedItemHasSerial && !isInboundType">
                        <label class="gs-input-label">
                            انتخاب شماره سریال برای خروج (اختیاری)
                            <span class="gs-label">— حداکثر {{ moveForm.quantity || 0 }} مورد؛ باقی خودکار کسر می‌شود</span>
                        </label>
                        <p v-if="loadingSerials" class="gs-label">در حال دریافت...</p>
                        <p v-else-if="!availableSerials.length" class="gs-label">شماره سریال نام‌داری در انبار نیست؛ کسر به‌صورت خودکار انجام می‌شود.</p>
                        <div v-else class="gs-serial-grid">
                            <label v-for="s in availableSerials" :key="s.id" class="gs-serial-pick"
                                :class="{ active: moveForm.serial_number_ids.includes(s.id) }">
                                <input type="checkbox" :value="s.id" v-model="moveForm.serial_number_ids"
                                    :disabled="!moveForm.serial_number_ids.includes(s.id) && moveForm.serial_number_ids.length >= moveForm.quantity" />
                                <span>{{ s.serial_number }}</span>
                            </label>
                        </div>
                        <span v-if="moveForm.errors.serial_number_ids" class="gs-error-msg">{{ moveForm.errors.serial_number_ids }}</span>
                    </div>

                    <div class="gs-input-group">
                        <label class="gs-input-label">یادداشت</label>
                        <input v-model="moveForm.note" type="text" class="gs-input" placeholder="توضیح اختیاری..." />
                    </div>

                    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem">
                        <button type="button" @click="showForm=false" class="gs-btn gs-btn-ghost">انصراف</button>
                        <button type="button" @click="submitMove" class="gs-btn gs-btn-primary" :disabled="moveForm.processing">
                            {{ moveForm.processing ? '...' : 'ثبت حرکت' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ movements: Object, items: Array, stockSummary: Array })

const showForm = ref(false)

const moveForm = useForm({
    item_id: '',
    movement_type: 'in',
    quantity: 1,
    note: '',
    serial_numbers: [],
    serial_number_ids: [],
})

const isIn = t => ['in', 'adjust_in'].includes(t)
const isInboundType = computed(() => isIn(moveForm.movement_type))

const selectedItemHasSerial = computed(() => {
    const item = props.items.find(i => i.id === moveForm.item_id)
    return !!(item && item.has_serial_number)
})

// --- ورودی: افزودن شماره سریال یکی‌یکی (اختیاری) ---
const newSerialInput = ref('')
const enteredSerials = ref([])

function addSerial() {
    const value = newSerialInput.value.trim()
    if (!value) return
    if (enteredSerials.value.length >= moveForm.quantity) return
    if (enteredSerials.value.includes(value)) {
        newSerialInput.value = ''
        return
    }
    enteredSerials.value.push(value)
    newSerialInput.value = ''
}

function removeSerial(idx) {
    enteredSerials.value.splice(idx, 1)
}

// اگر تعداد کم شد و بیشتر از quantity سریال وارد شده بود، مازاد رو حذف کن
watch(() => moveForm.quantity, (q) => {
    const n = Math.max(0, parseInt(q) || 0)
    if (enteredSerials.value.length > n) {
        enteredSerials.value = enteredSerials.value.slice(0, n)
    }
})

// --- خروجی: انتخاب از سریال‌های نام‌دار موجود (اختیاری) ---
const availableSerials = ref([])
const loadingSerials = ref(false)

async function fetchSerials() {
    availableSerials.value = []
    moveForm.serial_number_ids = []
    enteredSerials.value = []
    newSerialInput.value = ''

    const item = props.items.find(i => i.id === moveForm.item_id)
    if (!item || !item.has_serial_number) return

    loadingSerials.value = true
    try {
        const { data } = await axios.get(route('items.available-serials', item.id))
        availableSerials.value = data.serial_numbers ?? []
    } catch (e) {
        console.error(e)
    } finally {
        loadingSerials.value = false
    }
}

watch(() => moveForm.item_id, fetchSerials)
watch(() => moveForm.movement_type, fetchSerials)

function submitMove() {
    if (isInboundType.value) {
        moveForm.serial_numbers = [...enteredSerials.value]
        moveForm.serial_number_ids = []
    } else {
        moveForm.serial_numbers = []
    }

    moveForm.post(route('stock-movements.store'), {
        onSuccess: () => {
            showForm.value = false
            moveForm.reset()
            enteredSerials.value = []
            newSerialInput.value = ''
            availableSerials.value = []
        }
    })
}

const moveLabel = t => ({ in: 'ورودی', out: 'خروجی', adjust_in: 'تنظیم +', adjust_out: 'تنظیم −' }[t] ?? t)
const moveBadge = t => isIn(t) ? 'gs-badge-success' : 'gs-badge-error'
const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-stock-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem }
.gs-stock-card { padding:1rem }
.gs-item-name { font-weight:700;font-size:.9rem;color:var(--gs-text-primary) }
.gs-muted { color:var(--gs-text-muted);font-size:.8rem }
.gs-in { color:var(--gs-success);font-weight:700 }
.gs-out { color:var(--gs-error);font-weight:700 }
.gs-empty { padding:3rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem }
.gs-pagination { display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:1.25rem }
.gs-modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(3px);z-index:500;display:flex;align-items:center;justify-content:center;padding:1rem }
.gs-modal { background:var(--gs-bg-card);border:1px solid var(--gs-border-strong);border-radius:16px;padding:1.75rem;max-width:480px;width:100% }
.gs-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:0 1rem }
.gs-serial-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: .5rem;
    margin-top: .5rem;
}
.gs-serial-pick {
    display: flex;
    align-items: center;
    gap: .4rem;
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: .4rem .6rem;
    cursor: pointer;
    font-size: .8rem;
}
.gs-serial-pick.active { border-color: var(--gs-gold, var(--gs-border)); background: rgba(128,128,128,.1) }

.gs-serial-chips {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    margin-top: .6rem;
}

.gs-serial-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: rgba(128, 128, 128, .12);
    border: 1px solid var(--gs-border);
    border-radius: 999px;
    padding: .25rem .5rem .25rem .75rem;
    font-size: .8rem;
}

.gs-serial-chip button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--gs-error);
    font-size: .85rem;
    line-height: 1;
    padding: 0;
}
</style>
