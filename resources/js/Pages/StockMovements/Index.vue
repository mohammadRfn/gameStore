<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="gold" />

            <!-- ================= هیرو ================= -->
            <LuxHero chip="ماژول گردش انبار" chip-two="Stock Movements" title="گردش «انبار»"
                lead="ورود و خروج اقلام انبار — رهگیری کامل هر واحد کالا با شماره سریال و گارانتی." cube="🔄"
                satellite="📦" :stats="[
                    { label: 'حرکات ثبت‌شده', value: faInt(movements.total ?? movements.data?.length ?? 0) },
                    { label: 'اقلام موجودی‌محور', value: faInt(stockSummary.length) },
                    { label: 'جمع موجودی', value: faInt(totalStock) },
                ]">
                <template #chip-icon>
                    <Repeat :size="13" />
                </template>
                <template #actions>
                    <button @click="showForm = true" class="a3d-btn a3d-btn--gold">
                        <Plus :size="15" />
                        ثبت حرکت انبار
                    </button>
                </template>
            </LuxHero>

            <!-- ================= جستجو ================= -->
            <div class="gx-toolbar">
                <div class="gx-search">
                    <Search :size="15" class="gx-search__icon" />
                    <input v-model="search" type="search" placeholder="جستجو: نام قلم، دلیل یا یادداشت حرکت..." />
                </div>
            </div>

            <!-- ================= خلاصه موجودی هر قلم ================= -->
            <div class="gx-stockgrid" v-if="filteredSummary.length">
                <article v-for="(item, idx) in filteredSummary" :key="item.id"
                    v-tilt="{ max: 6, scale: 1.015, lift: 10 }" class="gx-stockcard a3d-aura"
                    :style="{ '--gx-i': Math.min(idx, 9) }">
                    <div class="gx-stockcard__top">
                        <p class="gx-stockcard__name">{{ item.name }}</p>
                        <span class="gx-status" :class="item.current_stock > 0 ? 'gx-status--green' : 'gx-status--red'">
                            <i />
                            {{ faInt(item.current_stock) }}
                        </span>
                    </div>

                    <div class="gx-stockcard__actions">
                        <button v-if="item.has_serial_number" type="button" class="a3d-btn a3d-btn--sm" style="flex:1"
                            @click="openSerialManager(item)">
                            <Barcode :size="13" />
                            سریال‌ها
                        </button>
                        <button v-if="item.has_warranty" type="button" class="a3d-btn a3d-btn--sm" style="flex:1"
                            @click="openWarrantyManager(item)">
                            <ShieldCheck :size="13" />
                            گارانتی
                        </button>
                    </div>
                </article>
            </div>

            <!-- ================= تاریخچه حرکات ================= -->
            <section class="gx-panel" :style="{ '--gx-i': 2 }" style="margin-top: 1.3rem">
                <div class="gx-panel__head">
                    <span class="gx-panel__icon">
                        <History :size="16" />
                    </span>
                    <div>
                        <p class="gx-panel__title">تاریخچه حرکات انبار</p>
                        <p class="gx-panel__desc">هر ورود و خروج با دلیل و یادداشت ثبت می‌شود</p>
                    </div>
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
                            <td style="font-weight:700">{{ m.item?.name ?? '—' }}</td>
                            <td>
                                <span class="gx-status"
                                    :class="isIn(m.movement_type) ? 'gx-status--green' : 'gx-status--red'">
                                    <component :is="isIn(m.movement_type) ? ArrowDownToLine : ArrowUpFromLine"
                                        :size="12" />
                                    {{ moveLabel(m.movement_type) }}
                                </span>
                            </td>
                            <td
                                :style="{ color: isIn(m.movement_type) ? 'var(--gs-success)' : 'var(--gs-error)', fontWeight: 800 }">
                                {{ isIn(m.movement_type) ? '+' : '-' }}{{ faInt(m.quantity) }}
                            </td>
                            <td style="color:var(--gs-text-muted);font-size:.8rem">{{ m.reason ?? '—' }}</td>
                            <td style="color:var(--gs-text-muted);font-size:.8rem">{{ m.note ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="gx-empty">
                    <span class="gx-empty__icon">📦</span>
                    <p class="gx-empty__title">حرکتی ثبت نشده</p>
                    <p class="gx-empty__desc">اولین ورود یا خروج انبار را ثبت کن تا تاریخچه شکل بگیرد.</p>
                </div>
            </section>

            <!-- ================= پجینیشن ================= -->
            <div class="gx-pagination" v-if="movements.last_page > 1">
                <Link v-if="movements.prev_page_url" :href="movements.prev_page_url" class="a3d-btn a3d-btn--sm">
                    <ChevronRight :size="14" /> قبلی
                </Link>
                <span class="gx-pagination__num">
                    صفحهٔ <b>{{ faInt(movements.current_page) }}</b> از {{ faInt(movements.last_page) }}
                </span>
                <Link v-if="movements.next_page_url" :href="movements.next_page_url" class="a3d-btn a3d-btn--sm">
                    بعدی
                    <ChevronLeft :size="14" />
                </Link>
            </div>

            <!-- ================= مودال ثبت حرکت دستی ================= -->
            <Teleport to="body">
                <Transition name="gs-fade">
                    <div v-if="showForm" class="gs-modal-overlay" @click.self="showForm = false">
                        <div class="gs-modal">
                            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.1rem">
                                <span class="gx-empty__icon"
                                    style="width:40px;height:40px;font-size:1rem;border-radius:12px;animation:none">🔄</span>
                                <h3 style="font-weight:800;color:var(--gs-text-primary)">ثبت حرکت دستی انبار</h3>
                            </div>

                            <div class="gs-input-group">
                                <label class="gs-input-label">قلم <span style="color:var(--gs-error)">*</span></label>
                                <select v-model="moveForm.item_id" class="gs-input"
                                    :class="{ 'gs-input-error': moveForm.errors.item_id }">
                                    <option value="">انتخاب قلم...</option>
                                    <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}
                                    </option>
                                </select>
                                <span v-if="moveForm.errors.item_id" class="gs-error-msg">{{ moveForm.errors.item_id
                                }}</span>
                            </div>

                            <div class="gx-grid2">
                                <div class="gs-input-group">
                                    <label class="gs-input-label">نوع <span
                                            style="color:var(--gs-error)">*</span></label>
                                    <select v-model="moveForm.movement_type" class="gs-input">
                                        <option value="in">ورودی</option>
                                        <option value="out">خروجی</option>
                                        <option value="adjust_in">تنظیم مثبت</option>
                                        <option value="adjust_out">تنظیم منفی</option>
                                    </select>
                                </div>
                                <div class="gs-input-group">
                                    <label class="gs-input-label">تعداد <span
                                            style="color:var(--gs-error)">*</span></label>
                                    <input v-model="moveForm.quantity" type="number" min="1" class="gs-input"
                                        :class="{ 'gs-input-error': moveForm.errors.quantity }" />
                                    <span v-if="moveForm.errors.quantity" class="gs-error-msg">{{
                                        moveForm.errors.quantity }}</span>
                                </div>
                            </div>

                            <!-- سریال‌های حرکت ورودی: اختیاری، یکی‌یکی -->
                            <div class="gs-input-group" v-if="selectedItemHasSerial && isInboundType">
                                <label class="gs-input-label">
                                    شماره سریال (اختیاری)
                                    <span class="gx-labelhint">— {{ faInt(enteredSerials.length) }} از {{
                                        faInt(moveForm.quantity ||
                                            0) }} وارد شده</span>
                                </label>
                                <p class="gx-labelhint" style="margin:.2rem 0 .5rem;display:block">
                                    وارد کردن شماره سریال اختیاری است؛ برای هر واحدی که سریال وارد نکنی، یک جایگاه «بدون
                                    شماره
                                    سریال» در انبار ثبت می‌شود که بعداً از لیست محصولات قابل تکمیل است.
                                </p>

                                <div style="display:flex;gap:.5rem">
                                    <input v-model="newSerialInput" type="text" class="gs-input"
                                        placeholder="شماره سریال را وارد و اضافه کن..."
                                        @keydown.enter.prevent="addSerial" />
                                    <button type="button" class="a3d-btn a3d-btn--sm"
                                        :disabled="!newSerialInput.trim() || enteredSerials.length >= moveForm.quantity"
                                        @click="addSerial">
                                        افزودن
                                    </button>
                                </div>

                                <div v-if="enteredSerials.length" class="gx-serialchips">
                                    <span v-for="(s, idx) in enteredSerials" :key="idx" class="gs-serial-chip">
                                        {{ s }}
                                        <button type="button" @click="removeSerial(idx)">✕</button>
                                    </span>
                                </div>

                                <span v-if="moveForm.errors.serial_numbers" class="gs-error-msg">{{
                                    moveForm.errors.serial_numbers
                                }}</span>
                            </div>

                            <!-- انتخاب سریال برای خروج -->
                            <div class="gs-input-group" v-if="selectedItemHasSerial && !isInboundType">
                                <label class="gs-input-label">
                                    انتخاب شماره سریال برای خروج (اختیاری)
                                    <span class="gx-labelhint">— حداکثر {{ faInt(moveForm.quantity || 0) }} مورد؛ باقی
                                        خودکار کسر
                                        می‌شود</span>
                                </label>
                                <p v-if="loadingSerials" class="gx-labelhint" style="display:block">در حال دریافت...</p>
                                <p v-else-if="!availableSerials.length" class="gx-labelhint" style="display:block">
                                    شماره سریال نام‌داری در انبار نیست؛ کسر به‌صورت خودکار انجام می‌شود.
                                </p>
                                <div v-else class="gx-serialgrid">
                                    <label v-for="s in availableSerials" :key="s.id" class="gs-serial-pick"
                                        :class="{ active: moveForm.serial_number_ids.includes(s.id) }">
                                        <input type="checkbox" :value="s.id" v-model="moveForm.serial_number_ids"
                                            :disabled="!moveForm.serial_number_ids.includes(s.id) && moveForm.serial_number_ids.length >= moveForm.quantity"
                                            style="accent-color:var(--gs-gold)" />
                                        <span>{{ s.serial_number }}</span>
                                    </label>
                                </div>
                                <span v-if="moveForm.errors.serial_number_ids" class="gs-error-msg">{{
                                    moveForm.errors.serial_number_ids }}</span>
                            </div>

                            <div class="gs-input-group">
                                <label class="gs-input-label">یادداشت</label>
                                <input v-model="moveForm.note" type="text" class="gs-input"
                                    placeholder="توضیح اختیاری..." />
                            </div>

                            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.7rem">
                                <button type="button" @click="showForm = false"
                                    class="a3d-btn a3d-btn--ghost">انصراف</button>
                                <button type="button" @click="submitMove" class="a3d-btn a3d-btn--gold"
                                    :disabled="moveForm.processing">
                                    <Plus :size="14" />
                                    {{ moveForm.processing ? '...' : 'ثبت حرکت' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <SerialNumberManager :show="showSerialManager" :item-id="serialManagerItemId"
                :item-name="serialManagerItemName" @close="showSerialManager = false" @changed="onSerialsChanged" />

            <WarrantyManagerModal :show="showWarrantyManager" :item-id="warrantyManagerItemId"
                :item-name="warrantyManagerItemName" @close="showWarrantyManager = false" />
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import SerialNumberManager from '@/Components/SerialNumberManager.vue'
import WarrantyManagerModal from '@/Components/WarrantyManagerModal.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import { vTilt } from '@/Composables/useTilt'
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Barcode,
    ChevronLeft,
    ChevronRight,
    History,
    Plus,
    Repeat,
    Search,
    ShieldCheck,
} from 'lucide-vue-next'

const props = defineProps({ movements: Object, items: Array, stockSummary: Array, filters: Object })

const showForm = ref(false)

// --- جستجو: کارت‌ها فوری (کلاینت)، تاریخچه با تأخیر (سرور) ---
const search = ref(props.filters?.search ?? '')

const filteredSummary = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.stockSummary
    return props.stockSummary.filter(i => (i.name ?? '').toLowerCase().includes(q))
})

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        const q = search.value.trim()
        router.get(route('stock-movements.index'), q ? { search: q } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['movements', 'filters'],
        })
    }, 350)
})

const totalStock = computed(() =>
    props.stockSummary.reduce((sum, i) => sum + (Number(i.current_stock) || 0), 0)
)

const showSerialManager = ref(false)
const serialManagerItemId = ref(null)
const serialManagerItemName = ref('')

function openSerialManager(item) {
    serialManagerItemId.value = item.id
    serialManagerItemName.value = item.name
    showSerialManager.value = true
}

function onSerialsChanged() {
    router.reload({ only: ['stockSummary', 'movements'] })
}

const showWarrantyManager = ref(false)
const warrantyManagerItemId = ref(null)
const warrantyManagerItemName = ref('')

function openWarrantyManager(item) {
    warrantyManagerItemId.value = item.id
    warrantyManagerItemName.value = item.name
    showWarrantyManager.value = true
}

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

const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')
</script>

<style scoped>
.gx-stockgrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(215px, 1fr));
    gap: 1rem;
}

.gx-stockcard {
    border-radius: var(--gs-radius-lg);
    border: 1px solid var(--gs-border);
    background: var(--a3d-glass, var(--gs-bg-card));
    backdrop-filter: blur(14px);
    padding: 1rem 1.05rem;
    animation: gx-rise 0.6s var(--gx-ease, ease) both;
    animation-delay: calc(var(--gx-i, 0) * 60ms);
    transition: border-color 0.3s ease, box-shadow 0.35s ease;
}

.gx-stockcard:hover {
    border-color: var(--gs-border-hover);
    box-shadow: var(--gs-shadow-gold);
}

.gx-stockcard__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.6rem;
    margin-bottom: 0.45rem;
}

.gx-stockcard__name {
    font-weight: 800;
    font-size: 0.88rem;
    color: var(--gs-text-primary);
}



.gx-stockcard__actions {
    display: flex;
    gap: 0.45rem;
    margin-top: 0.7rem;
}

.gx-grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1rem;
}

.gx-labelhint {
    color: var(--gs-text-muted);
    font-size: 0.72rem;
    font-weight: 400;
}

.gx-serialgrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.gs-serial-pick {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: 0.4rem 0.6rem;
    cursor: pointer;
    font-size: 0.8rem;
}

.gx-serialchips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.6rem;
}

.gs-serial-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.5rem 0.25rem 0.75rem;
    font-size: 0.8rem;
}

.gs-serial-chip button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--gs-error);
    font-size: 0.85rem;
    line-height: 1;
    padding: 0;
}

.gs-modal-overlay {
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

.gs-modal {
    padding: 1.75rem;
    max-width: 500px;
    width: 100%;
    max-height: 88vh;
    overflow-y: auto;
    direction: rtl;
    border-radius: 20px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-bg-card-strong);
    box-shadow: var(--gs-shadow-md), 0 0 70px -22px var(--gs-gold-glow);
    color: var(--gs-text-primary);
}
</style>