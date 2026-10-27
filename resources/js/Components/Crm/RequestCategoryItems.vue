<script setup>
/**
 * RequestCategoryItems — مرورگر کالاهای دسته‌بندی‌های انتخاب‌شده (کادر کوچک اختیاریِ قابل‌جمع‌شدن)
 * مسیر: resources/js/Components/Crm/RequestCategoryItems.vue
 *
 * - کالاها صفحه‌بندی‌شده (۵ تا در هر صفحه) و فشرده نمایش داده می‌شوند، همراه موجودی انبار
 * - با کلیک روی هر کالا: قیمت فروش (برای نشان‌دادن به مشتری)، افزودن به سفارش و «تغییر قیمت»
 * - تغییر قیمت (خرید + فروش) همان‌جا ثبت می‌شود؛ بعد از ثبت فقط قیمت فروش نمایش داده می‌شود
 * - اقلام انتخاب‌شده در basket (= form.items) جمع می‌شوند و با «ثبت + ایجاد فاکتور» به فاکتور می‌روند
 */
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { Boxes, Check, ChevronLeft, ChevronRight, Minus, Pencil, Plus, RefreshCw, ShoppingBasket, X } from 'lucide-vue-next'
import CrmCollapse from '@/Components/Crm/CrmCollapse.vue'
import MoneyInput from '@/Components/MoneyInput.vue'
import { faInt, money } from '@/Utils/format'

const PER_PAGE = 5

const props = defineProps({
    categories: { type: Array, default: () => [] },
    selectedIds: { type: Array, default: () => [] },
    basket: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:basket'])

const open = ref(false)
const loading = ref(false)
const error = ref('')
const page = ref(1)
const pager = ref({ data: [], current_page: 1, last_page: 1, total: 0 })
const activeCat = ref('')
const expanded = ref(null)
const savedId = ref(null)
const edits = reactive({})

let ticket = 0

const selectedCats = computed(() =>
    props.categories.filter((c) => props.selectedIds.some((id) => String(id) === String(c.id))),
)
const queryIds = computed(() => (activeCat.value !== '' ? [activeCat.value] : props.selectedIds))

async function load() {
    if (!props.selectedIds.length) {
        pager.value = { data: [], current_page: 1, last_page: 1, total: 0 }
        return
    }
    const mine = ++ticket
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(route('requests.category-items'), {
            params: { category_ids: queryIds.value, page: page.value, per_page: PER_PAGE },
        })
        if (mine === ticket) pager.value = data
    } catch (e) {
        if (mine === ticket) error.value = 'بارگذاری کالاها انجام نشد.'
    } finally {
        if (mine === ticket) loading.value = false
    }
}

watch(open, (v) => {
    if (v) load()
})
watch(
    () => props.selectedIds.join(','),
    () => {
        if (activeCat.value !== '' && !selectedCats.value.some((c) => String(c.id) === String(activeCat.value))) {
            activeCat.value = ''
        }
        page.value = 1
        expanded.value = null
        if (open.value) load()
    },
)
watch(activeCat, () => {
    page.value = 1
    expanded.value = null
    load()
})

function goto(p) {
    if (p < 1 || p > pager.value.last_page || p === pager.value.current_page) return
    page.value = p
    expanded.value = null
    load()
}

const pageNumbers = computed(() => {
    const last = pager.value.last_page || 1
    const size = 5
    let end = Math.min(last, Math.max(1, pager.value.current_page - 2) + size - 1)
    const start = Math.max(1, end - size + 1)
    end = Math.min(last, start + size - 1)
    return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

/* ---------- موجودی ---------- */
function stockLabel(it) {
    if (!it.tracks_stock) return 'بدون انبار'
    return it.stock > 0 ? `موجود ${faInt(it.stock)}` : 'ناموجود'
}
function stockClass(it) {
    if (!it.tracks_stock) return 'is-na'
    return it.stock > 0 ? 'is-ok' : 'is-out'
}

/* ---------- سبد سفارش ---------- */
const basketMap = computed(() => new Map(props.basket.map((b) => [b.item_id, b])))
const inBasket = (id) => basketMap.value.has(id)
const basketTotal = computed(() => props.basket.reduce((s, b) => s + b.quantity * Number(b.sale_price || 0), 0))

function toggleBasket(it) {
    if (inBasket(it.id)) {
        emit('update:basket', props.basket.filter((b) => b.item_id !== it.id))
    } else {
        emit('update:basket', [...props.basket, { item_id: it.id, quantity: 1, name: it.name, sale_price: it.sale_price }])
    }
}
function removeFromBasket(id) {
    emit('update:basket', props.basket.filter((b) => b.item_id !== id))
}
function setQty(id, q) {
    const n = Math.max(1, Math.min(100000, Math.floor(Number(q) || 1)))
    emit('update:basket', props.basket.map((b) => (b.item_id === id ? { ...b, quantity: n } : b)))
}

/* ---------- تغییر قیمت ---------- */
function startEdit(it) {
    savedId.value = null
    edits[it.id] = { purchase: it.purchase_price, sale: it.sale_price, busy: false, error: '' }
}
function cancelEdit(id) {
    delete edits[id]
}
async function saveEdit(it) {
    const e = edits[it.id]
    if (!e || e.busy) return
    if (e.purchase === '' || e.purchase === null || e.sale === '' || e.sale === null) {
        e.error = 'هر دو قیمت را وارد کنید.'
        return
    }
    e.busy = true
    e.error = ''
    try {
        const { data } = await axios.patch(route('requests.items.price', it.id), {
            purchase_price: Number(e.purchase),
            sale_price: Number(e.sale),
        })
        it.purchase_price = data.purchase_price
        it.sale_price = data.sale_price
        emit('update:basket', props.basket.map((b) => (b.item_id === it.id ? { ...b, sale_price: data.sale_price } : b)))
        delete edits[it.id]
        savedId.value = it.id
    } catch (err) {
        e.error = err?.response?.data?.message || 'ذخیرهٔ قیمت انجام نشد.'
        e.busy = false
    }
}
function toggleRow(id) {
    expanded.value = expanded.value === id ? null : id
    savedId.value = null
}
</script>

<template>
    <CrmCollapse
        v-model:open="open"
        title="کالاهای این دسته‌بندی"
        hint="نمایش، تغییر قیمت و افزودن به سفارش"
        :icon="Boxes"
        :badge="basket.length ? faInt(basket.length) : ''"
    >
        <div v-if="selectedCats.length > 1" class="rb-cats">
            <button type="button" class="rb-cat" :class="{ 'is-on': activeCat === '' }" @click="activeCat = ''">همه</button>
            <button
                v-for="c in selectedCats"
                :key="c.id"
                type="button"
                class="rb-cat"
                :class="{ 'is-on': String(activeCat) === String(c.id) }"
                @click="activeCat = c.id"
            >
                {{ c.name }}
            </button>
        </div>

        <p v-if="loading && !pager.data.length" class="rb-state"><RefreshCw :size="13" class="crm-spin" /> در حال بارگذاری…</p>
        <p v-else-if="error" class="rb-state rb-state--err">{{ error }}</p>
        <p v-else-if="!pager.data.length" class="rb-state">کالایی در این دسته‌بندی ثبت نشده است.</p>

        <ul v-else class="rb-list" :class="{ 'is-loading': loading }">
            <li
                v-for="it in pager.data"
                :key="it.id"
                class="rb-item"
                :class="{ 'is-open': expanded === it.id, 'is-picked': inBasket(it.id) }"
            >
                <button type="button" class="rb-item__row" :aria-expanded="expanded === it.id" @click="toggleRow(it.id)">
                    <span class="rb-item__check"><Check v-if="inBasket(it.id)" :size="10" /></span>
                    <span class="rb-item__name">{{ it.name }}</span>
                    <span class="rb-stock" :class="stockClass(it)">{{ stockLabel(it) }}</span>
                    <span class="rb-item__price">{{ faInt(it.sale_price) }}</span>
                </button>

                <div v-if="expanded === it.id" class="rb-item__more">
                    <div class="rb-show">
                        <span>قیمت فروش</span>
                        <b>{{ money(it.sale_price) }}</b>
                        <em v-if="savedId === it.id" class="rb-show__ok"><Check :size="12" /> ثبت شد</em>
                    </div>

                    <div class="rb-actions">
                        <button
                            type="button"
                            class="a3d-btn a3d-btn--sm"
                            :class="inBasket(it.id) ? 'a3d-btn--gold' : 'a3d-btn--ghost'"
                            @click="toggleBasket(it)"
                        >
                            <Check v-if="inBasket(it.id)" :size="13" />
                            <Plus v-else :size="13" />
                            {{ inBasket(it.id) ? 'در سفارش' : 'افزودن به سفارش' }}
                        </button>

                        <div v-if="inBasket(it.id)" class="rb-qty">
                            <button type="button" aria-label="کمتر" @click="setQty(it.id, basketMap.get(it.id).quantity - 1)"><Minus :size="13" /></button>
                            <input
                                type="number"
                                min="1"
                                :value="basketMap.get(it.id).quantity"
                                dir="ltr"
                                @input="setQty(it.id, $event.target.value)"
                                @keydown.enter.prevent
                            />
                            <button type="button" aria-label="بیشتر" @click="setQty(it.id, basketMap.get(it.id).quantity + 1)"><Plus :size="13" /></button>
                        </div>

                        <button v-if="!edits[it.id]" type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="startEdit(it)">
                            <Pencil :size="13" /> تغییر قیمت
                        </button>
                    </div>

                    <div v-if="edits[it.id]" class="rb-edit" @keydown.enter.prevent="saveEdit(it)">
                        <div class="rb-edit__grid">
                            <label>
                                <span>قیمت خرید</span>
                                <MoneyInput v-model="edits[it.id].purchase" size="sm" :hint="false" />
                            </label>
                            <label>
                                <span>قیمت فروش</span>
                                <MoneyInput v-model="edits[it.id].sale" size="sm" :hint="false" />
                            </label>
                        </div>
                        <p v-if="edits[it.id].error" class="rb-edit__err">{{ edits[it.id].error }}</p>
                        <div class="rb-edit__foot">
                            <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="cancelEdit(it.id)">انصراف</button>
                            <button type="button" class="a3d-btn a3d-btn--gold a3d-btn--sm" :disabled="edits[it.id].busy" @click="saveEdit(it)">
                                <RefreshCw v-if="edits[it.id].busy" :size="13" class="crm-spin" />
                                <Check v-else :size="13" />
                                ثبت قیمت
                            </button>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <nav v-if="pager.last_page > 1" class="rb-pager" aria-label="صفحه‌بندی کالاها">
            <button type="button" :disabled="pager.current_page <= 1" aria-label="صفحهٔ قبل" @click="goto(pager.current_page - 1)">
                <ChevronRight :size="14" />
            </button>
            <button
                v-for="p in pageNumbers"
                :key="p"
                type="button"
                :class="{ 'is-active': p === pager.current_page }"
                @click="goto(p)"
            >
                {{ faInt(p) }}
            </button>
            <button type="button" :disabled="pager.current_page >= pager.last_page" aria-label="صفحهٔ بعد" @click="goto(pager.current_page + 1)">
                <ChevronLeft :size="14" />
            </button>
            <span class="rb-pager__info">{{ faInt(pager.total) }} کالا</span>
        </nav>

        <div v-if="basket.length" class="rb-basket">
            <p class="rb-basket__t"><ShoppingBasket :size="13" /> اقلام سفارش ({{ faInt(basket.length) }})</p>
            <div class="rb-basket__chips">
                <span v-for="b in basket" :key="b.item_id" class="rb-chip">
                    {{ b.name }} × {{ faInt(b.quantity) }}
                    <button type="button" aria-label="حذف" @click="removeFromBasket(b.item_id)"><X :size="11" /></button>
                </span>
            </div>
            <p class="rb-basket__sum">جمع تقریبی: <b>{{ money(basketTotal) }}</b></p>
            <p class="rb-basket__hint">این اقلام فقط با دکمهٔ «ثبت و ایجاد فاکتور» وارد فاکتور می‌شوند.</p>
        </div>
    </CrmCollapse>
</template>
