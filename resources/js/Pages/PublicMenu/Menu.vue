<template>
    <div class="menu-page menu-page-top">
        <div class="menu-shell">
            <h1>انتخاب از منو</h1>

            <input v-model="searchQuery" type="text" class="search-input" placeholder="جستجو در منو..." />

            <div v-if="!filteredItems.length" class="empty-state">
                <p>موردی پیدا نشد</p>
            </div>

            <div class="items-grid" v-else>
                <div v-for="item in filteredItems" :key="item.id" class="item-card">
                    <div class="item-image" @click="item.image_url && openPreview(item.image_url)"
                        :class="{ clickable: item.image_url }">
                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" />
                        <span v-else class="item-image-placeholder">🎮</span>
                    </div>
                    <p class="item-name">{{ item.name }}</p>
                    <p class="item-price">{{ formatPrice(item.sale_price) }}</p>
                    <div class="item-actions">
                        <button class="qty-btn" @click="decrease(item)" :disabled="qtyFor(item.id) === 0">−</button>
                        <span>{{ qtyFor(item.id) }}</span>
                        <button class="qty-btn" @click="increase(item)">+</button>
                    </div>
                </div>
            </div>

            <div class="cart-bar" v-if="cartCount > 0">
                <span>{{ cartCount }} مورد انتخاب شده</span>
                <button class="menu-btn" @click="submitOrder" :disabled="submitting">
                    {{ submitting ? 'در حال ثبت...' : 'ثبت نهایی سفارش' }}
                </button>
            </div>
        </div>

        <!-- پیش‌نمایش تصویر -->
        <Transition name="fade">
            <div v-if="previewUrl" class="lightbox" @click="closePreview">
                <img :src="previewUrl" alt="پیش‌نمایش" @click.stop />
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    token: String,
    items: Array,
    selections: { type: Array, default: () => [] },
})

const cart = ref(Object.fromEntries(props.selections.map(s => [s.item_id, s.quantity])))
const submitting = ref(false)
const searchQuery = ref('')
const previewUrl = ref(null)

// نرمال‌سازی حروف مشابه فارسی/عربی و بی‌حساس کردن به بزرگی/کوچکی، برای اینکه سرچ اذیت نکنه
function normalize(text) {
    return (text ?? '')
        .toString()
        .toLowerCase()
        .replace(/ي/g, 'ی')
        .replace(/ك/g, 'ک')
        .trim()
}

const filteredItems = computed(() => {
    const q = normalize(searchQuery.value)
    if (!q) return props.items
    return props.items.filter(item => normalize(item.name).includes(q))
})

const cartCount = computed(() => Object.values(cart.value).reduce((a, b) => a + b, 0))

function qtyFor(itemId) {
    return cart.value[itemId] ?? 0
}

function increase(item) {
    cart.value[item.id] = (cart.value[item.id] ?? 0) + 1
    router.post(route('menu.select', props.token), { item_id: item.id, quantity: 1 }, { preserveScroll: true, preserveState: true })
}

function decrease(item) {
    if (!cart.value[item.id]) return
    cart.value[item.id] -= 1
    if (cart.value[item.id] === 0) delete cart.value[item.id]
    router.delete(route('menu.select.destroy', [props.token, item.id]), { preserveScroll: true, preserveState: true })
}

function submitOrder() {
    submitting.value = true
    router.post(route('menu.submit', props.token), {}, {
        onFinish: () => submitting.value = false,
    })
}

function openPreview(url) {
    previewUrl.value = url
}

function closePreview() {
    previewUrl.value = null
}

function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}
</script>

<style scoped>
.menu-page-top { align-items: flex-start; padding: 1.5rem 1rem; }
.menu-shell { max-width: 640px; margin: 0 auto; width: 100%; color: #eee; }

.search-input {
    width: 100%;
    padding: .65rem .9rem;
    border-radius: 10px;
    border: 1px solid #444;
    background: #1e1e26;
    color: #fff;
    margin: .75rem 0 1rem;
    font-size: .95rem;
}

.empty-state { text-align: center; padding: 2rem 0; color: #999; }

.items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .75rem; }
.item-card { background: #1e1e26; border-radius: 12px; padding: .75rem; text-align: center; }

.item-image {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    background: #111;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .5rem;
}
.item-image.clickable { cursor: zoom-in; }
.item-image img { width: 100%; height: 100%; object-fit: cover; }
.item-image-placeholder { font-size: 1.8rem; opacity: .5; }

.item-price { color: #c9a24b; font-weight: 700; margin: .3rem 0; }
.item-actions { display: flex; align-items: center; justify-content: center; gap: .75rem; }
.qty-btn { width: 28px; height: 28px; border-radius: 50%; border: 1px solid #444; background: #111; color: #fff; cursor: pointer; }

.cart-bar { position: sticky; bottom: 0; background: #1e1e26; padding: 1rem; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; margin-top: 1.5rem; }

.lightbox {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
    padding: 1.5rem;
}
.lightbox img { max-width: 100%; max-height: 100%; border-radius: 10px; }

.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>