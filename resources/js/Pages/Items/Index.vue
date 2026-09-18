<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="gold" />

            <!-- ================= هیرو ================= -->
            <LuxHero
                chip="ماژول انبار"
                chip-two="Items"
                title="انبار «محصولات»"
                lead="مدیریت اقلام فروشگاه — قیمت‌گذاری، موجودی، شماره سریال و گارانتی، همه در یک نما."
                cube="📦"
                satellite="🎮"
                :stats="[
                    { label: 'کل اقلام', value: faInt(items.length) },
                    { label: 'موجود در انبار', value: faInt(inStockCount) },
                    { label: 'دسته‌بندی‌ها', value: faInt(categoryChips.length) },
                ]"
            >
                <template #chip-icon>
                    <Package :size="13" />
                </template>
                <template #actions>
                    <Link :href="route('items.create')" class="a3d-btn a3d-btn--gold">
                        <Plus :size="15" />
                        محصول جدید
                    </Link>
                </template>
            </LuxHero>

            <!-- ================= نوار جستجو و فیلتر ================= -->
            <div class="gx-toolbar">
                <div class="gx-search">
                    <Search :size="15" class="gx-search__icon" />
                    <input v-model="search" type="search" placeholder="جستجو نام محصول..." />
                </div>
                <div v-if="categoryChips.length" class="gx-seg">
                    <button
                        type="button"
                        class="gx-seg__btn"
                        :class="{ 'is-active': !categoryFilter }"
                        @click="categoryFilter = ''"
                    >
                        همه
                    </button>
                    <button
                        v-for="c in categoryChips"
                        :key="c"
                        type="button"
                        class="gx-seg__btn"
                        :class="{ 'is-active': categoryFilter === c }"
                        @click="categoryFilter = categoryFilter === c ? '' : c"
                    >
                        {{ c }}
                    </button>
                </div>
            </div>

            <!-- ================= گرید محصولات ================= -->
            <div class="gx-prodgrid" v-if="filteredItems.length">
                <article
                    v-for="(item, idx) in filteredItems"
                    :key="item.id"
                    v-tilt="{ max: 7, scale: 1.015, lift: 12 }"
                    class="gx-prod a3d-aura"
                    :style="{ '--gx-i': Math.min(idx, 9) }"
                >
                    <!-- تصویر -->
                    <div class="gx-prod__img">
                        <img v-if="item.image_path" :src="'/storage/' + item.image_path" :alt="item.name" />
                        <span v-else class="gx-prod__ph">📦</span>
                    </div>

                    <!-- اطلاعات -->
                    <div class="gx-prod__body">
                        <p class="gx-prod__name">{{ item.name }}</p>
                        <p class="gx-prod__desc" v-if="item.description">{{ item.description }}</p>

                        <div class="gx-prod__tags">
                            <span v-if="item.category" class="gx-tag gx-tag--plain">{{ item.category.name }}</span>
                            <span v-if="item.has_serial_number" class="gx-tag">
                                <Barcode :size="11" /> سریال
                            </span>
                            <span v-if="item.has_warranty" class="gx-tag">
                                <ShieldCheck :size="11" /> گارانتی
                            </span>
                            <span
                                v-if="item.missing_serial_count > 0"
                                class="gx-tag gx-tag--warn"
                                @click="openSerialFix(item)"
                            >
                                <AlertTriangle :size="11" />
                                {{ faInt(item.missing_serial_count) }} بدون سریال
                            </span>
                        </div>

                        <div class="gx-prod__meta">
                            <span class="gx-price">{{ formatPrice(item.sale_price) }}</span>
                            <span
                                v-if="item.tracks_stock"
                                class="gx-status"
                                :class="item.current_stock > 0 ? 'gx-status--green' : 'gx-status--red'"
                            >
                                <i />
                                موجودی: {{ faInt(item.current_stock ?? 0) }}
                            </span>
                        </div>
                    </div>

                    <!-- اقدامات -->
                    <div class="gx-prod__actions">
                        <Link :href="route('items.edit', item.id)" class="a3d-btn a3d-btn--sm" style="flex: 1">
                            <Pencil :size="13" />
                            ویرایش
                        </Link>
                        <button @click="confirmDelete(item)" class="a3d-btn a3d-btn--sm a3d-btn--danger">
                            <Trash2 :size="13" />
                            حذف
                        </button>
                    </div>
                </article>
            </div>

            <!-- حالت خالی -->
            <div v-else class="gx-panel">
                <div class="gx-empty">
                    <span class="gx-empty__icon">📦</span>
                    <p class="gx-empty__title">محصولی یافت نشد</p>
                    <p class="gx-empty__desc">اولین قلم انبار را ثبت کن تا فروشگاهت جان بگیرد.</p>
                    <Link :href="route('items.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm" style="margin-top: 0.5rem">
                        <Plus :size="14" />
                        اولین محصول را اضافه کنید
                    </Link>
                </div>
            </div>

            <!-- ================= مودال حذف ================= -->
            <Transition name="gs-fade">
                <div v-if="deleteTarget" class="gs-modal-overlay" @click.self="deleteTarget = null">
                    <div class="gs-modal">
                        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.7rem">
                            <span class="gx-empty__icon" style="width: 40px; height: 40px; font-size: 1rem; border-radius: 12px; animation: none">🗑️</span>
                            <h3 style="font-weight: 800; color: var(--gs-text-primary)">حذف محصول</h3>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--gs-text-secondary); margin-bottom: 1.25rem; line-height: 1.9">
                            «{{ deleteTarget.name }}» برای همیشه حذف شود؟ این عملیات قابل بازگشت نیست.
                        </p>
                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end">
                            <button @click="deleteTarget = null" class="a3d-btn a3d-btn--ghost">انصراف</button>
                            <button @click="doDelete" class="a3d-btn a3d-btn--danger" :disabled="deleting">
                                {{ deleting ? 'در حال حذف...' : 'حذف' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ================= مودال تکمیل شماره سریال ================= -->
            <Transition name="gs-fade">
                <div v-if="serialFixTarget" class="gs-modal-overlay" @click.self="serialFixTarget = null">
                    <div class="gs-modal">
                        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.7rem">
                            <span class="gx-empty__icon" style="width: 40px; height: 40px; font-size: 1rem; border-radius: 12px; animation: none">🔢</span>
                            <h3 style="font-weight: 800; color: var(--gs-text-primary)">
                                تکمیل شماره سریال — {{ serialFixTarget.name }}
                            </h3>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--gs-text-muted); margin-bottom: 1rem; line-height: 1.9">
                            این واحدها در انبار موجودند ولی شماره سریال ندارند. برای هرکدام که می‌خوای، شماره سریال رو
                            وارد و ثبت کن.
                        </p>

                        <p v-if="loadingSlots" style="font-size: 0.8rem; color: var(--gs-text-muted)">در حال بارگذاری...</p>
                        <p v-else-if="!serialSlots.length" style="font-size: 0.8rem; color: var(--gs-success)">
                            همه‌ی واحدها شماره سریال دارند 🎉
                        </p>

                        <div v-else style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 300px; overflow-y: auto">
                            <div v-for="slot in serialSlots" :key="slot.id" style="display: flex; gap: 0.5rem">
                                <input
                                    v-model="slotInputs[slot.id]"
                                    type="text"
                                    class="gs-input"
                                    placeholder="شماره سریال..."
                                    @keydown.enter.prevent="submitSlotSerial(slot)"
                                />
                                <button type="button" class="a3d-btn a3d-btn--gold a3d-btn--sm" @click="submitSlotSerial(slot)">
                                    ثبت
                                </button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem">
                            <button @click="serialFixTarget = null" class="a3d-btn a3d-btn--ghost">بستن</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import { vTilt } from '@/Composables/useTilt'
import {
    AlertTriangle,
    Barcode,
    Package,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next'

const props = defineProps({ items: Array })

const search = ref('')
const categoryFilter = ref('')

const categoryChips = computed(() =>
    [...new Set(props.items.map(i => i.category?.name).filter(Boolean))]
)

const inStockCount = computed(() =>
    props.items.filter(i => !i.tracks_stock || (i.current_stock ?? 0) > 0).length
)

const filteredItems = computed(() =>
    props.items.filter(i =>
        i.name.toLowerCase().includes(search.value.toLowerCase()) &&
        (!categoryFilter.value || i.category?.name === categoryFilter.value)
    )
)

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(item) { deleteTarget.value = item }
function doDelete() {
    deleting.value = true
    router.delete(route('items.destroy', deleteTarget.value.id), {
        onFinish: () => { deleting.value = false; deleteTarget.value = null }
    })
}

// --- تکمیل شماره سریال‌های خالی ---
const serialFixTarget = ref(null)
const serialSlots = ref([])
const loadingSlots = ref(false)
const slotInputs = ref({})

async function openSerialFix(item) {
    serialFixTarget.value = item
    slotInputs.value = {}
    loadingSlots.value = true
    try {
        const { data } = await axios.get(route('items.missing-serials', item.id))
        serialSlots.value = data.slots ?? []
    } catch (e) {
        console.error(e)
        serialSlots.value = []
    } finally {
        loadingSlots.value = false
    }
}

async function submitSlotSerial(slot) {
    const value = (slotInputs.value[slot.id] || '').trim()
    if (!value) return
    try {
        await axios.patch(route('item-serial-numbers.assign', slot.id), { serial_number: value })
        serialSlots.value = serialSlots.value.filter(s => s.id !== slot.id)
        if (serialFixTarget.value) {
            serialFixTarget.value.missing_serial_count = Math.max(0, (serialFixTarget.value.missing_serial_count || 1) - 1)
        }
    } catch (e) {
        alert(e.response?.data?.message ?? 'خطا در ثبت شماره سریال')
    }
}

const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')

function formatPrice(p) {
    return Number(p).toLocaleString('fa-IR') + ' تومان'
}
</script>

<style scoped>
.gs-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.gs-modal {
    padding: 1.75rem;
    max-width: 420px;
    width: 100%;
}
</style>
