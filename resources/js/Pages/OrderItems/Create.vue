<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">افزودن به فاکتور</h1>
                    <p class="gs-subtitle">افزودن قلم یا سرویس به فاکتور</p>
                </div>
                <Link :href="route('invoices.show', invoiceId)" class="gs-btn gs-btn-secondary">← بازگشت به فاکتور
                </Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:680px">

            <!-- Order type switch -->
            <div class="gs-input-group">
                <label class="gs-input-label">نوع سفارش</label>
                <div style="display:flex;gap:.5rem">
                    <button type="button" class="gs-btn gs-btn-sm"
                        :class="orderType === 'item' ? 'gs-btn-primary' : 'gs-btn-secondary'"
                        @click="orderType = 'item'">کالا</button>
                    <button type="button" class="gs-btn gs-btn-sm"
                        :class="orderType === 'service' ? 'gs-btn-primary' : 'gs-btn-secondary'"
                        @click="orderType = 'service'">سرویس</button>
                </div>
            </div>

            <div class="gs-divider" style="margin:1rem 0"></div>

            <!-- ITEM FORM -->
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

                <div class="gs-form-grid">
                    <div class="gs-input-group">
                        <label class="gs-input-label">تعداد <span style="color:var(--gs-error)">*</span></label>
                        <input v-model.number="itemForm.quantity" type="number" min="1" class="gs-input"
                            :class="{ 'gs-input-error': itemForm.errors.quantity }" />
                        <span v-if="itemForm.errors.quantity" class="gs-error-msg">{{ itemForm.errors.quantity }}</span>
                    </div>
                    <div class="gs-input-group">
                        <label class="gs-input-label">تصویر (اختیاری)</label>
                        <input type="file" accept="image/*" class="gs-input" @change="onFile" style="cursor:pointer" />
                    </div>
                </div>

                <div class="gs-input-group" v-if="selectedItem && selectedItem.tracks_stock">
                    <label class="gs-checkbox-label">
                        <input v-model="itemForm.deduct_from_stock" type="checkbox" class="gs-checkbox" />
                        <span>این قلم از موجودی انبار کسر شود</span>
                    </label>
                    <p class="gs-label" style="margin-top:.3rem">
                        کسر واقعی از انبار زمانی اتفاق می‌افتد که فاکتور هم تأیید و هم پرداخت‌شده باشد؛ برای اقلامی مثل
                        خدمات که کالای فیزیکی ندارند، این گزینه را غیرفعال کنید.
                    </p>
                </div>

                <div class="gs-total-preview gs-card" v-if="selectedItem">
                    <div class="gs-detail-row">
                        <span class="gs-label">قیمت واحد</span>
                        <span>{{ formatPrice(selectedItem.price) }}</span>
                    </div>
                    <div class="gs-detail-row">
                        <span class="gs-label">جمع این قلم</span>
                        <span class="gs-gold-text" style="font-weight:700">{{ itemLineTotal }}</span>
                    </div>
                </div>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('invoices.show', invoiceId)" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary" :disabled="itemForm.processing || !invoiceId">
                        {{ itemForm.processing ? 'در حال ذخیره...' : 'افزودن به فاکتور' }}
                    </button>
                </div>
            </form>

            <!-- SERVICE FORM -->
            <form v-else @submit.prevent="submitServices">
                <p v-if="!serviceJobs.length" class="gs-muted" style="text-align:center;padding:1.5rem">
                    سرویس تحویل‌شده‌ای برای این مشتری وجود ندارد که به فاکتور اضافه نشده باشد.
                </p>

                <div v-else style="display:flex;flex-direction:column;gap:.5rem">
                    <label v-for="sj in serviceJobs" :key="sj.id" class="gs-service-pick"
                        :class="{ active: serviceForm.service_job_ids.includes(sj.id) }">
                        <input type="checkbox" :value="sj.id" v-model="serviceForm.service_job_ids"
                            style="accent-color:var(--gs-gold)" />
                        <div style="flex:1">
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <span style="font-weight:600">#{{ sj.id }} — {{ sj.device_type ?? 'بدون نوع دستگاه'
                                }}</span>
                                <span class="gs-gold-text" style="font-weight:700">{{ formatPrice(sj.final_price)
                                }}</span>
                            </div>
                            <div v-if="sj.service_types?.length"
                                style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem">
                                <span v-for="st in sj.service_types" :key="st.id"
                                    class="gs-badge gs-badge-gold gs-badge-sm">
                                    {{ st.service_type?.name ?? '—' }}
                                </span>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="gs-total-preview gs-card" v-if="serviceForm.service_job_ids.length">
                    <div class="gs-detail-row">
                        <span class="gs-label">تعداد سرویس انتخاب‌شده</span>
                        <span>{{ serviceForm.service_job_ids.length }}</span>
                    </div>
                    <div class="gs-detail-row">
                        <span class="gs-label">جمع</span>
                        <span class="gs-gold-text" style="font-weight:700">{{ servicesLineTotal }}</span>
                    </div>
                </div>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('invoices.show', invoiceId)" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary"
                        :disabled="serviceForm.processing || !invoiceId || !serviceForm.service_job_ids.length">
                        {{ serviceForm.processing ? 'در حال ذخیره...' : 'افزودن به فاکتور' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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
})

const selectedItem = computed(() => props.items.find(i => i.id === itemForm.item_id) ?? null)
watch(selectedItem, (item) => {
    itemForm.deduct_from_stock = !!(item && item.tracks_stock)
}, { immediate: true })

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
</script>

<style scoped>
.gs-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between
}

.gs-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1.25rem
}

.gs-total-preview {
    margin-top: .75rem;
    padding: .9rem 1rem
}

.gs-detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .35rem 0
}

.gs-label {
    color: var(--gs-text-muted);
    font-size: .85rem
}

.gs-checkbox-label {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    color: var(--gs-text-secondary);
    cursor: pointer
}

.gs-checkbox {
    accent-color: var(--gs-gold);
    width: 15px;
    height: 15px;
    cursor: pointer
}

.gs-service-pick {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    border: 1px solid var(--gs-border);
    border-radius: 10px;
    padding: .6rem .75rem;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}

.gs-service-pick:hover {
    background: rgba(128, 128, 128, .07);
}

.gs-service-pick.active {
    border-color: var(--gs-gold, var(--gs-border));
    background: rgba(128, 128, 128, .1);
}

.gs-badge-sm {
    font-size: .7rem;
    padding: .1rem .45rem;
}
</style>