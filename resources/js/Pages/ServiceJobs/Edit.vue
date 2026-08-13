<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">ویرایش سرویس #{{ job.id }}</h1>
                    <p class="gs-subtitle">{{ job.customer?.name ?? '—' }}</p>
                </div>
                <div style="display:flex;gap:.75rem">
                    <Link :href="route('service-jobs.show', job.id)" class="gs-btn gs-btn-ghost">مشاهده</Link>
                    <Link :href="route('service-jobs.index')" class="gs-btn gs-btn-secondary">← بازگشت</Link>
                </div>
            </div>
        </template>

        <form @submit.prevent="submit">
            <div class="gs-sj-grid">

                <div style="display:flex;flex-direction:column;gap:1rem">
                    <div class="gs-card">
                        <p class="gs-label" style="margin-bottom:1rem">اطلاعات مشتری و درخواست</p>
                        <div class="gs-input-group">
                            <label class="gs-input-label">مشتری</label>
                            <select v-model="form.customer_id" class="gs-input">
                                <option value="">— انتخاب مشتری —</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div class="gs-input-group">
                            <div style="display:flex;align-items:center;justify-content:space-between">
                                <label class="gs-input-label">درخواست مرتبط</label>
                                <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm"
                                    @click="showRequestList = !showRequestList">
                                    {{ showRequestList ? 'بستن ▲' : 'نمایش لیست ▼' }}
                                </button>
                            </div>

                            <div class="gs-request-summary" @click="showRequestList = !showRequestList">
                                <span v-if="selectedRequest">#{{ selectedRequest.id }} — {{
                                    selectedRequest.customer_name }}</span>
                                <span v-else class="gs-muted">— بدون درخواست —</span>
                            </div>

                            <div v-if="showRequestList" class="gs-request-list">
                                <div class="gs-request-card" :class="{ active: !form.request_id }"
                                    @click="selectRequest('')">
                                    <span class="gs-request-card-title">— بدون درخواست —</span>
                                </div>

                                <div v-if="!filteredRequests.length" class="gs-muted" style="padding:.5rem">
                                    درخواستی برای این مشتری ثبت نشده
                                </div>

                                <div v-for="r in filteredRequests" :key="r.id" class="gs-request-card"
                                    :class="{ active: form.request_id === r.id }" @click="selectRequest(r.id)">
                                    <div class="gs-request-card-top">
                                        <span class="gs-request-card-title">#{{ r.id }} — {{ r.customer_name }}</span>
                                        <span v-if="form.request_id === r.id"
                                            class="gs-badge gs-badge-info gs-badge-sm">✓
                                            انتخاب شده</span>
                                    </div>
                                    <div class="gs-request-card-cats" v-if="r.categories?.length">
                                        <span v-for="c in r.categories" :key="c.id"
                                            class="gs-badge gs-badge-gold gs-badge-sm">
                                            {{ c.name }}
                                        </span>
                                    </div>
                                    <p class="gs-request-card-desc" :class="{ expanded: expandedRequestId === r.id }">
                                        {{ r.description }}
                                    </p>
                                    <button v-if="r.description && r.description.length > 90" type="button"
                                        class="gs-request-toggle" @click.stop="toggleExpand(r.id)">
                                        {{ expandedRequestId === r.id ? 'بستن جزئیات ▲' : 'مشاهده کامل ▼' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gs-card">
                        <p class="gs-label" style="margin-bottom:1rem">اطلاعات دستگاه</p>
                        <div class="gs-form-grid">
                            <div class="gs-input-group">
                                <label class="gs-input-label">نوع دستگاه</label>
                                <input v-model="form.device_type" type="text" class="gs-input" />
                            </div>
                            <div class="gs-input-group">
                                <label class="gs-input-label">شماره سریال</label>
                                <input v-model="form.device_serial" type="text" class="gs-input" />
                            </div>
                        </div>
                        <div class="gs-input-group">
                            <label class="gs-input-label">شرح مشکل مشتری</label>
                            <textarea v-model="form.customer_problem_description" class="gs-input" rows="3"
                                style="resize:vertical"></textarea>
                        </div>
                        <div class="gs-input-group">
                            <label class="gs-input-label">تشخیص فنی</label>
                            <textarea v-model="form.diagnosis_description" class="gs-input" rows="3"
                                style="resize:vertical"></textarea>
                        </div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:1rem">
                    <div class="gs-card">
                        <p class="gs-label" style="margin-bottom:1rem">وضعیت و زمان‌بندی</p>
                        <div class="gs-input-group">
                            <label class="gs-input-label">وضعیت</label>
                            <select v-model="form.status" class="gs-input">
                                <option value="received">دریافت شده</option>
                                <option value="diagnosing">در حال بررسی</option>
                                <option value="waiting_for_parts">در انتظار قطعه</option>
                                <option value="in_progress">در حال تعمیر</option>
                                <option value="completed">تکمیل شده</option>
                                <option value="delivered">تحویل داده شده</option>
                                <option value="canceled">لغو شده</option>
                            </select>
                        </div>
                        <div class="gs-form-grid">
                            <div class="gs-input-group">
                                <label class="gs-input-label">تاریخ شروع</label>
                                <JalaliDateInput v-model="form.started_at" placeholder="انتخاب تاریخ شروع" />
                            </div>
                            <div class="gs-input-group">
                                <label class="gs-input-label">تاریخ تحویل</label>
                                <JalaliDateInput v-model="form.delivered_at" placeholder="انتخاب تاریخ تحویل" />
                            </div>
                        </div>
                    </div>

                    <!-- Service types (multi) -->
                    <div class="gs-card">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
                            <p class="gs-label">نوع(های) سرویس</p>
                            <div style="display:flex;gap:.5rem">
                                <button type="button" @click="openServiceTypePicker"
                                    class="gs-btn gs-btn-secondary gs-btn-sm">+
                                    افزودن</button>
                                <button type="button" @click="toggleNewServiceType"
                                    class="gs-btn gs-btn-ghost gs-btn-sm">
                                    {{ showNewServiceType ? 'انصراف' : '+ نوع جدید' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="showNewServiceType" class="gs-inline-create" style="margin-bottom:.75rem">
                            <input v-model="newServiceType.name" type="text" class="gs-input"
                                placeholder="نام نوع سرویس" @keyup.enter="createServiceType" />
                            <input type="text" class="gs-input" placeholder="قیمت پایه (اختیاری)"
                                :value="formatMoney(newServiceType.base_price)"
                                @input="onMoneyInput($event, newServiceType, 'base_price')" />
                            <button type="button" @click="createServiceType" class="gs-btn gs-btn-primary gs-btn-sm"
                                :disabled="creatingServiceType || !newServiceType.name">
                                {{ creatingServiceType ? 'در حال ثبت...' : 'ثبت' }}
                            </button>
                            <p v-if="newServiceTypeError" class="gs-muted" style="color:#e05c5c">{{
                                newServiceTypeError }}</p>
                        </div>

                        <div v-for="(st, i) in form.service_types" :key="i" class="gs-item-row">
                            <span style="flex:1;font-size:.875rem">{{ serviceTypeName(st.service_type_id) }}</span>
                            <input type="text" class="gs-input" style="width:130px" placeholder="قیمت"
                                :value="formatMoney(st.price)" @input="onMoneyInput($event, st, 'price')" />
                            <button type="button" @click="removeServiceType(i)"
                                class="gs-btn gs-btn-danger gs-btn-sm">✕</button>
                        </div>

                        <p v-if="!form.service_types.length" class="gs-muted" style="text-align:center;padding:.5rem">
                            هنوز نوع سرویسی انتخاب نشده
                        </p>

                        <div v-if="form.service_types.length" class="gs-divider"></div>
                        <div v-if="form.service_types.length"
                            style="display:flex;justify-content:space-between;padding-top:.5rem">
                            <span class="gs-label">قیمت کلی خدمات</span>
                            <span class="gs-gold-text" style="font-weight:700">{{ formatPrice(servicesTotal) }}</span>
                        </div>
                    </div>

                    <div class="gs-card">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
                            <p class="gs-label">قطعات مصرفی</p>
                            <button type="button" @click="openPicker" class="gs-btn gs-btn-secondary gs-btn-sm">+
                                افزودن</button>
                        </div>
                        <div v-for="(it, i) in form.items" :key="i" class="gs-item-row">
                            <span style="flex:1;font-size:.875rem">{{ itemName(it.item_id) }}</span>
                            <input v-model="it.quantity" type="number" min="1" class="gs-input" style="width:60px" />
                            <input type="text" class="gs-input" style="width:120px" placeholder="قیمت واحد"
                                :value="formatMoney(it.unit_price)" @input="onMoneyInput($event, it, 'unit_price')" />
                            <button type="button" @click="removeItem(i)"
                                class="gs-btn gs-btn-danger gs-btn-sm">✕</button>
                        </div>
                        <p v-if="!form.items.length" class="gs-muted" style="text-align:center;padding:.5rem">قطعه‌ای
                            ثبت نشده
                        </p>
                        <div v-if="form.items.length" class="gs-divider"></div>
                        <div v-if="form.items.length"
                            style="display:flex;justify-content:space-between;padding-top:.5rem">
                            <span class="gs-label">جمع قطعات</span>
                            <span class="gs-gold-text" style="font-weight:700">{{ formatPrice(itemsTotal) }}</span>
                        </div>
                    </div>

                    <!-- Final price (auto) -->
                    <div class="gs-card">
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <span class="gs-label">قیمت نهایی (خدمات + قطعات)</span>
                            <span class="gs-gold-text" style="font-weight:700;font-size:1.05rem">
                                {{ formatPrice(finalPriceComputed) }}
                            </span>
                        </div>
                    </div>

                    <div style="display:flex;gap:.75rem;justify-content:flex-end">
                        <span v-if="form.isDirty" class="gs-badge gs-badge-warning" style="align-self:center">تغییرات
                            ذخیره
                            نشده</span>
                        <button type="submit" class="gs-btn gs-btn-primary"
                            :disabled="form.processing || !form.isDirty">
                            {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Item picker modal -->
            <div v-if="pickerOpen" class="gs-modal-overlay" @click.self="closePicker">
                <div class="gs-card gs-modal">
                    <div class="gs-modal-header">
                        <button v-if="pickerStep === 'items'" type="button" @click="backToCategories"
                            class="gs-btn gs-btn-ghost gs-btn-sm">→ بازگشت</button>
                        <p class="gs-label" style="flex:1;margin:0">
                            {{ pickerStep === 'categories' ? 'انتخاب دسته‌بندی' : selectedCategoryName }}
                        </p>
                        <button type="button" @click="closePicker" class="gs-btn gs-btn-ghost gs-btn-sm">✕</button>
                    </div>
                    <div class="gs-modal-body">
                        <template v-if="pickerStep === 'categories'">
                            <div class="gs-category-grid">
                                <button v-for="cat in categories" :key="cat.id" type="button"
                                    class="gs-btn gs-btn-secondary gs-category-btn" @click="selectCategory(cat)">
                                    {{ cat.name }}
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div v-if="!itemsInCategory.length" class="gs-muted" style="text-align:center;padding:1rem">
                                قطعه‌ای در این دسته‌بندی نیست</div>
                            <button v-for="item in itemsInCategory" :key="item.id" type="button" class="gs-picker-item"
                                @click="pickItem(item)">
                                <span>{{ item.name }}</span>
                                <span class="gs-muted">{{ formatPrice(item.sale_price) }}</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Service type picker modal -->
            <div v-if="serviceTypePickerOpen" class="gs-modal-overlay" @click.self="closeServiceTypePicker">
                <div class="gs-card gs-modal">
                    <div class="gs-modal-header">
                        <p class="gs-label" style="flex:1;margin:0">انتخاب نوع سرویس</p>
                        <button type="button" @click="closeServiceTypePicker"
                            class="gs-btn gs-btn-ghost gs-btn-sm">✕</button>
                    </div>
                    <div class="gs-modal-body">
                        <div v-if="!availableServiceTypes.length" class="gs-muted"
                            style="text-align:center;padding:1rem">همه‌ی
                            نوع‌های سرویس اضافه شده‌اند</div>
                        <button v-for="st in availableServiceTypes" :key="st.id" type="button" class="gs-picker-item"
                            @click="addServiceType(st)">
                            <span>{{ st.name }}</span>
                            <span class="gs-muted">{{ formatPrice(st.base_price) }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import { computed, ref } from 'vue'
import axios from 'axios'

// 1) props همیشه اول
const props = defineProps({
    job: Object,
    customers: Array,
    requests: Array,
    serviceTypes: Array,
    items: Array,
    categories: Array,
})

// 2) form بعد از props
const form = useForm({
    customer_id: props.job.customer_id ?? '',
    request_id: props.job.request_id ?? '',
    device_type: props.job.device_type ?? '',
    device_serial: props.job.device_serial ?? '',
    customer_problem_description: props.job.customer_problem_description ?? '',
    diagnosis_description: props.job.diagnosis_description ?? '',
    status: props.job.status ?? 'received',
    started_at: props.job.started_at ? props.job.started_at.substring(0, 10) : null,
    delivered_at: props.job.delivered_at ? props.job.delivered_at.substring(0, 10) : null,
    estimated_price: props.job.estimated_price ?? 0,
    final_price: props.job.final_price ?? 0,
    items: props.job.items?.map(it => ({
        id: it.id,
        item_id: it.item_id,
        quantity: it.quantity,
        unit_price: it.unit_price,
    })) ?? [],
    service_types: props.job.service_types?.map(st => ({
        id: st.id,
        service_type_id: st.service_type_id,
        price: st.price ?? '',
    })) ?? [],
})

const filteredRequests = computed(() => {
    if (!form.customer_id) return props.requests
    return props.requests.filter(r => r.customer_id === form.customer_id)
})

const expandedRequestId = ref(null)
function toggleExpand(id) {
    expandedRequestId.value = expandedRequestId.value === id ? null : id
}

const showRequestList = ref(false)
const selectedRequest = computed(() => props.requests.find(r => r.id === form.request_id) ?? null)
function selectRequest(id) {
    form.request_id = id
    showRequestList.value = false
}

// --- money formatting helpers ---
function formatMoney(v) {
    return (v || v === 0) ? Number(v).toLocaleString('en-US') : ''
}
function onMoneyInput(e, obj, key) {
    const raw = e.target.value.replace(/[^0-9]/g, '')
    obj[key] = raw ? Number(raw) : ''
    e.target.value = formatMoney(obj[key])
}
function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '۰ تومان'
}

// --- item picker (category -> item) ---
const pickerOpen = ref(false)
const pickerStep = ref('categories')
const pickerCategoryId = ref(null)

const itemsInCategory = computed(() => props.items.filter(i => i.category_id === pickerCategoryId.value))
const selectedCategoryName = computed(() => props.categories.find(c => c.id === pickerCategoryId.value)?.name ?? '')

function openPicker() {
    pickerStep.value = 'categories'
    pickerCategoryId.value = null
    pickerOpen.value = true
}
function selectCategory(cat) {
    pickerCategoryId.value = cat.id
    pickerStep.value = 'items'
}
function backToCategories() { pickerStep.value = 'categories' }
function closePicker() { pickerOpen.value = false }
function pickItem(item) {
    form.items.push({ item_id: item.id, quantity: 1, unit_price: item.sale_price })
    closePicker()
}
function removeItem(i) { form.items.splice(i, 1) }
function itemName(id) { return props.items.find(i => i.id === id)?.name ?? '—' }

const itemsTotal = computed(() =>
    form.items.reduce((sum, it) => sum + ((Number(it.quantity) || 0) * (Number(it.unit_price) || 0)), 0)
)

// --- quick create service type ---
const attachedServiceTypes = (props.job.service_types ?? [])
    .map(st => st.service_type)
    .filter(Boolean)
    .filter((st, idx, arr) =>
        !props.serviceTypes.some(s => s.id === st.id) &&
        arr.findIndex(a => a.id === st.id) === idx
    )

const serviceTypesList = ref([...props.serviceTypes, ...attachedServiceTypes])
const showNewServiceType = ref(false)
const creatingServiceType = ref(false)
const newServiceTypeError = ref('')
const newServiceType = ref({ name: '', base_price: '' })

function toggleNewServiceType() {
    showNewServiceType.value = !showNewServiceType.value
    newServiceTypeError.value = ''
    if (!showNewServiceType.value) newServiceType.value = { name: '', base_price: '' }
}

async function createServiceType() {
    if (!newServiceType.value.name) return
    creatingServiceType.value = true
    newServiceTypeError.value = ''
    try {
        const { data } = await axios.post(route('service-types.quick-store'), {
            name: newServiceType.value.name,
            base_price: newServiceType.value.base_price || null,
        })
        serviceTypesList.value.push(data)
        showNewServiceType.value = false
        newServiceType.value = { name: '', base_price: '' }
    } catch (e) {
        newServiceTypeError.value = e.response?.data?.message ?? 'خطا در ثبت نوع سرویس'
    } finally {
        creatingServiceType.value = false
    }
}

// --- service type picker (multi-select for this job) ---
const serviceTypePickerOpen = ref(false)
const availableServiceTypes = computed(() => serviceTypesList.value)
function openServiceTypePicker() { serviceTypePickerOpen.value = true }
function closeServiceTypePicker() { serviceTypePickerOpen.value = false }
function addServiceType(st) {
    form.service_types.push({ service_type_id: st.id, price: st.base_price ?? '' })
    closeServiceTypePicker()
}
function removeServiceType(i) { form.service_types.splice(i, 1) }
function serviceTypeName(id) { return serviceTypesList.value.find(s => s.id === id)?.name ?? '—' }

const servicesTotal = computed(() =>
    form.service_types.reduce((sum, st) => sum + (Number(st.price) || 0), 0)
)

const finalPriceComputed = computed(() => servicesTotal.value + itemsTotal.value)

function submit() {
    form.estimated_price = servicesTotal.value
    form.final_price = finalPriceComputed.value
    form.put(route('service-jobs.update', props.job.id))
}
</script>

<style scoped>
.gs-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between
}

.gs-sj-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    align-items: start
}

.gs-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1rem
}

.gs-item-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .5rem
}

.gs-muted {
    color: var(--gs-text-muted);
    font-size: .875rem
}

.gs-inline-create {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    margin-top: .25rem;
}

.gs-request-summary {
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: .5rem .75rem;
    font-size: .875rem;
    cursor: pointer;
}

.gs-request-summary:hover {
    background: rgba(128, 128, 128, .07);
}

.gs-request-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    max-height: 200px;
    overflow-y: auto;
    padding-inline-end: .25rem;
    margin-top: .5rem;
}

.gs-request-card {
    border: 1px solid var(--gs-border);
    border-radius: 10px;
    padding: .625rem .75rem;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}

.gs-request-card:hover {
    background: rgba(128, 128, 128, .07);
}

.gs-request-card.active {
    border-color: var(--gs-gold, var(--gs-border));
    background: rgba(128, 128, 128, .1);
}

.gs-request-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}

.gs-request-card-title {
    font-weight: 500;
    font-size: .875rem;
}

.gs-request-card-cats {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .4rem;
}

.gs-request-card-desc {
    margin-top: .4rem;
    font-size: .8rem;
    color: var(--gs-text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gs-request-card-desc.expanded {
    -webkit-line-clamp: unset;
    display: block;
}

.gs-request-toggle {
    margin-top: .35rem;
    background: none;
    border: none;
    padding: 0;
    font-size: .75rem;
    color: var(--gs-gold, inherit);
    cursor: pointer;
}

.gs-badge-sm {
    font-size: .7rem;
    padding: .1rem .45rem;
}

.gs-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
}

.gs-modal {
    width: 100%;
    max-width: 420px;
    max-height: 70vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 0;
}

.gs-modal-header {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--gs-border);
}

.gs-modal-body {
    padding: .75rem 1.25rem 1rem;
    overflow-y: auto;
}

.gs-category-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
}

.gs-category-btn {
    justify-content: center;
}

.gs-picker-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: .625rem .25rem;
    border: none;
    border-bottom: 1px solid var(--gs-border);
    background: transparent;
    cursor: pointer;
    font-size: .875rem;
    color: inherit;
    font-family: inherit;
    text-align: right;
}

.gs-picker-item:hover {
    background: rgba(128, 128, 128, .12);
}

@media(max-width:768px) {
    .gs-sj-grid {
        grid-template-columns: 1fr
    }
}
</style>