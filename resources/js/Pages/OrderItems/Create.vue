<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="violet" />

            <LuxHero
                chip="ماژول فروش"
                chip-two="Order Items"
                title="افزودن به «فاکتور»"
                lead="افزودن قلم کالا یا سرویس تحویل‌شده به این فاکتور."
                cube="🛒"
                satellite="➕"
            >
                <template #chip-icon><ShoppingCart :size="13" /></template>
                <template #actions>
                    <Link :href="route('invoices.show', invoiceId)" class="a3d-btn a3d-btn--ghost">
                        <ArrowRight :size="15" />
                        بازگشت به فاکتور
                    </Link>
                </template>
            </LuxHero>

            <section class="gx-panel" style="max-width: 720px" :style="{ '--gx-i': 0 }">
                <!-- ===== سوییچ نوع سفارش ===== -->
                <div class="gx-panel__head">
                    <span class="gx-panel__icon">
                        <component :is="orderType === 'item' ? Package : Wrench" :size="16" />
                    </span>
                    <div>
                        <p class="gx-panel__title">نوع سفارش</p>
                        <p class="gx-panel__desc">کالا از انبار یا سرویس تحویل‌شده</p>
                    </div>
                    <div class="gx-panel__spacer">
                        <div class="gx-seg">
                            <button type="button" class="gx-seg__btn" :class="{ 'is-active': orderType === 'item' }"
                                @click="orderType = 'item'">
                                📦 کالا
                            </button>
                            <button type="button" class="gx-seg__btn" :class="{ 'is-active': orderType === 'service' }"
                                @click="orderType = 'service'">
                                🔧 سرویس
                            </button>
                        </div>
                    </div>
                </div>

                <div class="gx-panel__body">
                    <!-- ================= فرم کالا ================= -->
                    <form v-if="orderType === 'item'" @submit.prevent="submitItem" enctype="multipart/form-data">
                        <div class="gs-input-group">
                            <label class="gs-input-label">محصول <span style="color:var(--gs-error)">*</span></label>
                            <select v-model="itemForm.item_id" class="gs-input"
                                :class="{ 'gs-input-error': itemForm.errors.item_id }">
                                <option value="">انتخاب محصول...</option>
                                <option v-for="item in items" :key="item.id" :value="item.id">
                                    {{ item.name }} — {{ formatPrice(item.price) }}
                                </option>
                            </select>
                            <span v-if="itemForm.errors.item_id" class="gs-error-msg">{{ itemForm.errors.item_id }}</span>
                        </div>

                        <div class="gx-grid2">
                            <div class="gs-input-group">
                                <label class="gs-input-label">تعداد <span style="color:var(--gs-error)">*</span></label>
                                <input v-model.number="itemForm.quantity" type="number" min="1" class="gs-input"
                                    :class="{ 'gs-input-error': itemForm.errors.quantity }" />
                                <span v-if="itemForm.errors.quantity" class="gs-error-msg">{{ itemForm.errors.quantity }}</span>
                            </div>
                            <div class="gs-input-group">
                                <label class="gs-input-label">تصویر (اختیاری)</label>
                                <label class="gx-dropzone">
                                    <input type="file" accept="image/*" @change="onFile" hidden />
                                    <ImagePlus :size="16" />
                                    <span>{{ itemForm.image ? itemForm.image.name : 'انتخاب تصویر...' }}</span>
                                </label>
                            </div>
                        </div>

                        <label class="gx-switchrow" v-if="selectedItem && selectedItem.tracks_stock">
                            <div>
                                <p style="font-size:.85rem;font-weight:600;color:var(--gs-text-primary)">این قلم از موجودی انبار کسر شود</p>
                                <p class="gx-switchrow__hint">
                                    کسر واقعی از انبار زمانی اتفاق می‌افتد که فاکتور هم تأیید و هم پرداخت‌شده باشد؛ برای
                                    اقلامی مثل خدمات که کالای فیزیکی ندارند، این گزینه را غیرفعال کنید.
                                </p>
                            </div>
                            <input v-model="itemForm.deduct_from_stock" type="checkbox" class="gs-checkbox" />
                        </label>

                        <!-- انتخاب شماره سریال (کالای موجودی‌محور) -->
                        <div class="gs-input-group"
                            v-if="selectedItem && selectedItem.has_serial_number && itemForm.deduct_from_stock && !selectedItem.is_consignment">
                            <label class="gs-input-label">
                                انتخاب شماره سریال (اختیاری)
                                <span class="gx-labelhint">— حداکثر {{ itemForm.quantity || 0 }} مورد؛ باقی به‌صورت خودکار از انبار کسر می‌شود</span>
                            </label>

                            <p v-if="loadingSerials" class="gx-labelhint">در حال دریافت شماره سریال‌ها...</p>
                            <p v-else-if="!availableSerials.length" class="gs-error-msg">
                                هیچ شماره سریال موجودی برای این کالا در انبار ثبت نشده است.
                            </p>
                            <div v-else class="gx-serialgrid">
                                <label v-for="s in availableSerials" :key="s.id" class="gs-serial-pick"
                                    :class="{ active: itemForm.serial_number_ids.includes(s.id) }">
                                    <input type="checkbox" :value="s.id" v-model="itemForm.serial_number_ids"
                                        :disabled="!itemForm.serial_number_ids.includes(s.id) && itemForm.serial_number_ids.length >= itemForm.quantity"
                                        style="accent-color:var(--gs-gold)" />
                                    <span>{{ s.serial_number }}</span>
                                </label>
                            </div>
                            <span v-if="itemForm.errors.serial_number_ids" class="gs-error-msg">
                                {{ itemForm.errors.serial_number_ids }}
                            </span>
                        </div>

                        <!-- ورود دستی سریال (کالای امانی) -->
                        <div class="gs-input-group"
                            v-if="selectedItem && selectedItem.has_serial_number && selectedItem.is_consignment">
                            <label class="gs-input-label">
                                شماره سریال (اختیاری)
                                <span class="gx-labelhint">— این کالا امانی است؛ می‌توانید شماره سریال هر واحد را دستی وارد کنید</span>
                            </label>
                            <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.5rem">
                                <input v-for="(_, idx) in itemForm.manual_serial_numbers" :key="idx"
                                    v-model="itemForm.manual_serial_numbers[idx]" type="text" class="gs-input"
                                    :placeholder="`شماره سریال واحد ${idx + 1}`" />
                            </div>
                            <span v-if="itemForm.errors.manual_serial_numbers" class="gs-error-msg">
                                {{ itemForm.errors.manual_serial_numbers }}
                            </span>
                        </div>

                        <!-- پیش‌نمایش جمع -->
                        <Transition name="gs-fade">
                            <div class="gx-total" v-if="selectedItem" style="margin-top: .9rem">
                                <div class="gx-row">
                                    <span class="gx-row__k">قیمت واحد</span>
                                    <span class="gx-row__v">{{ formatPrice(selectedItem.price) }}</span>
                                </div>
                                <div class="gx-row">
                                    <span class="gx-row__k">جمع این قلم</span>
                                    <span class="gx-total__value" style="font-size:1.05rem">{{ itemLineTotal }}</span>
                                </div>
                            </div>
                        </Transition>

                        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.4rem">
                            <Link :href="route('invoices.show', invoiceId)" class="a3d-btn a3d-btn--ghost">انصراف</Link>
                            <button type="submit" class="a3d-btn a3d-btn--gold" :disabled="itemForm.processing || !invoiceId">
                                <Plus :size="15" />
                                {{ itemForm.processing ? 'در حال ذخیره...' : 'افزودن به فاکتور' }}
                            </button>
                        </div>
                    </form>

                    <!-- ================= فرم سرویس ================= -->
                    <form v-else @submit.prevent="submitServices">
                        <div v-if="!serviceJobs.length" class="gx-empty" style="padding: 1.8rem">
                            <span class="gx-empty__icon">🔧</span>
                            <p class="gx-empty__desc">
                                سرویس تحویل‌شده‌ای برای این مشتری وجود ندارد که به فاکتور اضافه نشده باشد.
                            </p>
                        </div>

                        <div v-else style="display:flex;flex-direction:column;gap:.5rem">
                            <label v-for="sj in serviceJobs" :key="sj.id" class="gs-service-pick"
                                :class="{ active: serviceForm.service_job_ids.includes(sj.id) }">
                                <input type="checkbox" :value="sj.id" v-model="serviceForm.service_job_ids"
                                    style="accent-color:var(--gs-gold)" />
                                <div style="flex:1">
                                    <div style="display:flex;justify-content:space-between;align-items:center">
                                        <span style="font-weight:700;font-size:.87rem">
                                            #{{ sj.id }} — {{ sj.device_type ?? 'بدون نوع دستگاه' }}
                                        </span>
                                        <span class="gx-price" style="font-size:.85rem">{{ formatPrice(sj.final_price) }}</span>
                                    </div>
                                    <div v-if="sj.service_types?.length"
                                        style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem">
                                        <span v-for="st in sj.service_types" :key="st.id" class="gx-tag">
                                            {{ st.service_type?.name ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <Transition name="gs-fade">
                            <div class="gx-total" v-if="serviceForm.service_job_ids.length" style="margin-top: .9rem">
                                <div class="gx-row">
                                    <span class="gx-row__k">تعداد سرویس انتخاب‌شده</span>
                                    <span class="gx-row__v">{{ faInt(serviceForm.service_job_ids.length) }}</span>
                                </div>
                                <div class="gx-row">
                                    <span class="gx-row__k">جمع</span>
                                    <span class="gx-total__value" style="font-size:1.05rem">{{ servicesLineTotal }}</span>
                                </div>
                            </div>
                        </Transition>

                        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.4rem">
                            <Link :href="route('invoices.show', invoiceId)" class="a3d-btn a3d-btn--ghost">انصراف</Link>
                            <button type="submit" class="a3d-btn a3d-btn--gold"
                                :disabled="serviceForm.processing || !invoiceId || !serviceForm.service_job_ids.length">
                                <Plus :size="15" />
                                {{ serviceForm.processing ? 'در حال ذخیره...' : 'افزودن به فاکتور' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import {
    ArrowRight,
    ImagePlus,
    Package,
    Plus,
    ShoppingCart,
    Wrench,
} from 'lucide-vue-next'

const props = defineProps({
    invoiceId: [Number, String],
    items: Array,
    serviceJobs: Array,
})

const orderType = ref('item')

// --- item form ---
const itemForm = useForm({
    invoice_id: props.invoiceId,
    item_id: '',
    quantity: 1,
    image: null,
    deduct_from_stock: false,
    serial_number_ids: [],
    manual_serial_numbers: [],
})

const selectedItem = computed(() => props.items.find(i => i.id === itemForm.item_id) ?? null)
watch(selectedItem, (item) => {
    itemForm.deduct_from_stock = !!(item && item.tracks_stock)
    itemForm.serial_number_ids = []
    resizeManualSerials(itemForm.quantity)
    fetchSerials()
}, { immediate: true })

// وقتی تعداد تغییر کند، انتخاب‌ها/ورودی‌های اضافه باید پاک شوند تا با quantity هماهنگ بمانند
watch(() => itemForm.quantity, (newQty) => {
    if (itemForm.serial_number_ids.length > itemForm.quantity) {
        itemForm.serial_number_ids = itemForm.serial_number_ids.slice(0, itemForm.quantity)
    }
    resizeManualSerials(newQty)
})

function resizeManualSerials(qty) {
    const size = Math.max(0, qty || 0)
    const current = itemForm.manual_serial_numbers
    if (current.length > size) {
        itemForm.manual_serial_numbers = current.slice(0, size)
    } else if (current.length < size) {
        itemForm.manual_serial_numbers = [...current, ...Array(size - current.length).fill('')]
    }
}

const availableSerials = ref([])
const loadingSerials = ref(false)

async function fetchSerials() {
    availableSerials.value = []
    if (!selectedItem.value || !selectedItem.value.has_serial_number) return

    loadingSerials.value = true
    try {
        const { data } = await axios.get(route('items.available-serials', selectedItem.value.id))
        availableSerials.value = data.serial_numbers ?? []
    } catch (e) {
        console.error(e)
    } finally {
        loadingSerials.value = false
    }
}

const itemLineTotal = computed(() =>
    formatPrice(selectedItem.value ? selectedItem.value.price * (itemForm.quantity || 0) : 0)
)

function onFile(e) {
    const file = e.target.files[0]
    if (!file) return
    itemForm.image = file
}

function submitItem() {
    itemForm.post(route('order-items.store'), { forceFormData: true })
}

// --- service form ---
const serviceForm = useForm({
    service_job_ids: [],
})

const servicesLineTotal = computed(() => {
    const total = props.serviceJobs
        .filter(sj => serviceForm.service_job_ids.includes(sj.id))
        .reduce((sum, sj) => sum + (Number(sj.final_price) || 0), 0)
    return formatPrice(total)
})

function submitServices() {
    serviceForm.post(route('invoices.service-jobs.attach', props.invoiceId))
}

function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}

const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')
</script>

<style scoped>
.gx-grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1.25rem;
}

@media (max-width: 640px) {
    .gx-grid2 { grid-template-columns: 1fr; }
}

.gx-dropzone {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.62rem 0.9rem;
    border: 1px dashed var(--gs-border-hover);
    border-radius: 12px;
    color: var(--gs-text-secondary);
    font-size: 0.78rem;
    cursor: pointer;
    transition: border-color 0.25s ease, background 0.25s ease, color 0.25s ease;
}

.gx-dropzone:hover {
    border-color: var(--gs-gold);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
}

.gx-switchrow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 0.9rem;
    margin: 0.4rem 0 0.8rem;
    border: 1px dashed var(--gs-border-hover);
    border-radius: 12px;
    background: var(--gs-glass);
    cursor: pointer;
}

.gx-switchrow__hint {
    font-size: 0.72rem;
    color: var(--gs-text-muted);
    margin-top: 0.25rem;
    line-height: 1.9;
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

.gs-service-pick {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    border: 1px solid var(--gs-border);
    border-radius: 10px;
    padding: 0.7rem 0.85rem;
    cursor: pointer;
}
</style>
