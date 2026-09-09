<template>
    <AppLayout>
        <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <h1 class="gs-title">انبار محصولات</h1>
                    <p class="gs-subtitle">{{ items.length }} قلم ثبت شده</p>
                </div>
                <Link :href="route('items.create')" class="gs-btn gs-btn-primary">+ محصول جدید</Link>
            </div>
        </template>

        <!-- Search -->
        <div class="gs-card" style="margin-bottom:1.25rem">
            <input v-model="search" type="search" class="gs-input" placeholder="جستجو نام محصول..." />
        </div>

        <!-- Grid -->
        <div class="gs-items-grid" v-if="filteredItems.length">
            <div v-for="item in filteredItems" :key="item.id" class="gs-item-card gs-card">
                <!-- Image -->
                <div class="gs-item-img">
                    <img v-if="item.image_path" :src="'/storage/' + item.image_path" :alt="item.name" />
                    <span v-else class="gs-item-img-placeholder">📦</span>
                </div>
                <!-- Info -->
                <div class="gs-item-info">
                    <p class="gs-item-name">{{ item.name }}</p>
                    <p class="gs-item-desc" v-if="item.description">{{ item.description }}</p>
                    <span v-if="item.category" class="gs-badge" style="margin-top:.3rem">{{ item.category.name }}</span>
                              <div v-if="item.has_serial_number || item.has_warranty || item.missing_serial_count > 0"
                        style="display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.35rem">
                        <span v-if="item.has_serial_number" class="gs-badge gs-badge-gold gs-badge-sm">شماره سریال دارد</span>
                        <span v-if="item.has_warranty" class="gs-badge gs-badge-gold gs-badge-sm">گارانتی دارد</span>
                        <span v-if="item.missing_serial_count > 0" class="gs-badge gs-badge-warning gs-badge-sm"
                            style="cursor:pointer" @click="openSerialFix(item)">
                            {{ item.missing_serial_count }} عدد بدون شماره سریال
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.75rem">
                        <span class="gs-gold-text" style="font-weight:700;font-size:.95rem">
                            {{ formatPrice(item.sale_price) }}
                        </span>
                        <span v-if="item.tracks_stock" class="gs-badge"
                            :class="item.current_stock > 0 ? 'gs-badge-success' : 'gs-badge-error'">
                            موجودی: {{ item.current_stock ?? 0 }}
                        </span>
                    </div>
                </div>
                <!-- Actions -->
                <div class="gs-item-actions">
                    <Link :href="route('items.edit', item.id)" class="gs-btn gs-btn-secondary gs-btn-sm">ویرایش</Link>
                    <button @click="confirmDelete(item)" class="gs-btn gs-btn-danger gs-btn-sm">حذف</button>
                </div>
            </div>
        </div>

        <div v-else class="gs-card" style="text-align:center;padding:3rem">
            <p style="font-size:2rem;margin-bottom:.5rem">📦</p>
            <p class="gs-subtitle">محصولی یافت نشد</p>
            <Link :href="route('items.create')" class="gs-btn gs-btn-primary gs-btn-sm" style="margin-top:.75rem">
                اولین محصول را اضافه کنید
            </Link>
        </div>

        <!-- Delete modal -->
        <Transition name="gs-fade">
            <div v-if="deleteTarget" class="gs-modal-overlay" @click.self="deleteTarget = null">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:.5rem">حذف محصول</h3>
                    <p class="gs-label" style="margin-bottom:1.25rem">
                        «{{ deleteTarget.name }}» حذف شود؟
                    </p>
                    <div style="display:flex;gap:.75rem;justify-content:flex-end">
                        <button @click="deleteTarget = null" class="gs-btn gs-btn-ghost">انصراف</button>
                        <button @click="doDelete" class="gs-btn gs-btn-danger" :disabled="deleting">
                            {{ deleting ? '...' : 'حذف' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Serial fix modal -->
        <Transition name="gs-fade">
            <div v-if="serialFixTarget" class="gs-modal-overlay" @click.self="serialFixTarget = null">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:.5rem">تکمیل شماره سریال — {{ serialFixTarget.name }}</h3>
                    <p class="gs-label" style="margin-bottom:1rem">
                        این واحدها در انبار موجودند ولی شماره سریال ندارند. برای هرکدام که می‌خوای، شماره سریال رو
                        وارد و ثبت کن.
                    </p>

                    <p v-if="loadingSlots" class="gs-label">در حال بارگذاری...</p>
                    <p v-else-if="!serialSlots.length" class="gs-label">همه‌ی واحدها شماره سریال دارند 🎉</p>

                    <div v-else style="display:flex;flex-direction:column;gap:.5rem;max-height:300px;overflow-y:auto">
                        <div v-for="slot in serialSlots" :key="slot.id" style="display:flex;gap:.5rem">
                            <input v-model="slotInputs[slot.id]" type="text" class="gs-input"
                                placeholder="شماره سریال..." @keydown.enter.prevent="submitSlotSerial(slot)" />
                            <button type="button" class="gs-btn gs-btn-primary gs-btn-sm"
                                @click="submitSlotSerial(slot)">ثبت</button>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-top:1.25rem">
                        <button @click="serialFixTarget = null" class="gs-btn gs-btn-ghost">بستن</button>
                    </div>
                </div>
            </div>
        </Transition>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ items: Array })

const search = ref('')
const filteredItems = computed(() =>
    props.items.filter(i => i.name.toLowerCase().includes(search.value.toLowerCase()))
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

function formatPrice(p) {
    return Number(p).toLocaleString('fa-IR') + ' تومان'
}
</script>

<style scoped>
.gs-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.gs-item-card {
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
}

.gs-item-img {
    height: 130px;
    background: var(--gs-bg-elevated);
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--gs-border);
    overflow: hidden;
}

.gs-item-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gs-item-img-placeholder {
    font-size: 2.5rem;
    opacity: .5;
}

.gs-item-info {
    padding: 1rem;
    flex: 1;
}

.gs-item-name {
    font-weight: 700;
    color: var(--gs-text-primary);
    font-size: .95rem;
    margin-bottom: .2rem;
}

.gs-item-desc {
    font-size: .8rem;
    color: var(--gs-text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gs-item-actions {
    padding: .75rem 1rem;
    border-top: 1px solid var(--gs-border);
    display: flex;
    gap: .5rem;
}

.gs-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(3px);
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.gs-modal {
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border-strong);
    border-radius: 16px;
    padding: 1.75rem;
    max-width: 380px;
    width: 100%;
}
</style>
