<template>
    <div class="gs-card" style="margin-top:1.25rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
            <span class="gs-subtitle">منوی دیجیتال مشتری</span>
            <span v-if="session" class="gs-badge" :class="statusBadgeClass">{{ statusLabel }}</span>
        </div>

        <div v-if="loading" style="text-align:center;padding:1rem 0">
            <p class="gs-label">در حال بارگذاری وضعیت...</p>
        </div>

        <div v-else-if="!session?.status || session.status === 'submitted' || session.status === 'expired'">
            <p v-if="session?.status === 'submitted'" class="gs-gold-text" style="font-weight:700;margin-bottom:.75rem">
                ✓ سفارش قبلی مشتری ثبت شد
            </p>
            <p v-else-if="session?.status === 'expired'" class="gs-label" style="margin-bottom:.75rem">
                کد قبلی منقضی شده است
            </p>
            <p class="gs-label" style="margin-bottom:.5rem">
                دسته‌بندی‌هایی که مشتری اجازه دیدن و انتخاب از آن‌ها را دارد مشخص کنید.
            </p>
            <div class="gs-category-grid">
                <label v-for="cat in categories" :key="cat.id" class="gs-category-pick"
                    :class="{ active: selectedCategoryIds.includes(cat.id) }">
                    <input type="checkbox" :value="cat.id" v-model="selectedCategoryIds" />
                    <span>{{ cat.name }}</span>
                </label>
            </div>
            <div style="margin-top:.75rem;display:flex;justify-content:flex-end">
                <button class="gs-btn gs-btn-primary gs-btn-sm" :disabled="!selectedCategoryIds.length || activating"
                    @click="activate">
                    {{ activating ? 'در حال ساخت کد...' : '+ فعال‌سازی منوی دیجیتال' }}
                </button>
            </div>
            <p v-if="error" class="gs-error-msg" style="margin-top:.5rem">{{ error }}</p>
        </div>

        <div v-else-if="session.status === 'pending'" style="text-align:center;padding:1rem 0">
            <p class="gs-label">این کد را به مشتری بدهید — فقط یک‌بار قابل استفاده است</p>
            <p class="gs-gold-text" style="font-size:2.5rem;font-weight:800;letter-spacing:.4rem;margin:.5rem 0">
                {{ session.code }}
            </p>
            <p class="gs-label" style="direction:ltr">{{ menuLink }}</p>
            <div style="display:flex;gap:.5rem;justify-content:center;margin-top:.5rem">
                <button class="gs-btn gs-btn-secondary gs-btn-sm" @click="copyLink">کپی لینک</button>
                <button class="gs-btn gs-btn-ghost gs-btn-sm" @click="refreshStatus">بروزرسانی وضعیت</button>
            </div>
        </div>

        <div v-else-if="session.status === 'active'" style="text-align:center;padding:1rem 0">
            <p class="gs-label">مشتری وارد منو شده و در حال انتخاب است...</p>
            <button class="gs-btn gs-btn-ghost gs-btn-sm" @click="refreshStatus" style="margin-top:.5rem">
                بروزرسانی وضعیت
            </button>
        </div>

        <!-- fallback: اگر status چیز شناخته‌شده‌ای نبود، هیچ‌وقت کارت کاملاً خالی نمونه -->
        <div v-else style="text-align:center;padding:1rem 0">
            <p class="gs-error-msg">وضعیت نامشخص — لطفاً بروزرسانی کنید</p>
            <button class="gs-btn gs-btn-ghost gs-btn-sm" @click="refreshStatus">بروزرسانی وضعیت</button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    invoiceId: [Number, String],
    categories: { type: Array, default: () => [] },
})

const selectedCategoryIds = ref([])
const session = ref(null)
const activating = ref(false)
const error = ref('')
const loading = ref(true)
let fetching = false

const statusLabel = computed(() => ({
    pending: 'در انتظار ورود مشتری',
    active: 'مشتری در حال انتخاب',
    submitted: 'ثبت شد',
    expired: 'منقضی شده',
}[session.value?.status] ?? ''))

const statusBadgeClass = computed(() => ({
    pending: 'gs-badge-gold',
    active: 'gs-badge-info',
    submitted: 'gs-badge-gold',
    expired: 'gs-badge-error',
}[session.value?.status] ?? ''))

const menuLink = computed(() => session.value?.link ?? '')

async function activate() {
    error.value = ''
    activating.value = true
    try {
        const { data } = await axios.post(route('invoices.digital-menu.activate', props.invoiceId), {
            category_ids: selectedCategoryIds.value,
        })
        session.value = data
        selectedCategoryIds.value = []
    } catch (e) {
        error.value = e.response?.data?.message ?? 'خطا در فعال‌سازی منوی دیجیتال'
    } finally {
        activating.value = false
    }
}

async function refreshStatus() {
    if (fetching) return
    fetching = true
    try {
        const { data } = await axios.get(route('invoices.digital-menu.status', props.invoiceId))
        session.value = data
    } catch (e) {
        console.error('digital-menu status fetch failed', e)
        // مقدار قبلی رو دست‌نخورده نگه می‌داریم تا کارت خالی نشه
    } finally {
        loading.value = false
        fetching = false
    }
}

function copyLink() {
    navigator.clipboard?.writeText(menuLink.value)
}

onMounted(refreshStatus)
</script>

<style scoped>
.gs-category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: .5rem;
}

.gs-category-pick {
    display: flex;
    align-items: center;
    gap: .4rem;
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    padding: .4rem .6rem;
    cursor: pointer;
    font-size: .85rem;
}

.gs-category-pick.active {
    border-color: var(--gs-gold, var(--gs-border));
    background: rgba(128, 128, 128, .1);
}
</style>