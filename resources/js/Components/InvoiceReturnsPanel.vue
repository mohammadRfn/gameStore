<template>
    <div class="gs-card">
        <p class="gs-label" style="margin-bottom:1rem">مرجوعی اقلام</p>

        <div v-if="!orderItems.length" class="gs-muted" style="text-align:center;padding:1rem">
            قلمی برای مرجوعی وجود ندارد
        </div>

        <div v-for="item in orderItems" :key="item.id" class="gs-return-row">
            <div style="flex:1">
                <p style="font-size:.875rem;font-weight:600">{{ item.product_name }}</p>
                <p class="gs-muted" style="font-size:.75rem">
                    {{ item.quantity }} عدد — {{ formatPrice(item.total_price) }}
                    <span v-if="item.is_returned" class="gs-badge gs-badge-error gs-badge-sm" style="margin-right:.4rem">
                        مرجوع شده{{ item.restock_on_return ? ' (برگشت به انبار)' : '' }}
                    </span>
                </p>
            </div>

            <label v-if="item.deduct_from_stock && !item.is_returned" class="gs-checkbox-label">
                <input type="checkbox" v-model="restockMap[item.id]" class="gs-checkbox" />
                <span>برگرده به انبار</span>
            </label>

            <button v-if="!item.is_returned" type="button" class="gs-btn gs-btn-danger gs-btn-sm"
                :disabled="processingId === item.id" @click="markReturned(item)">
                {{ processingId === item.id ? '...' : 'مرجوع شد' }}
            </button>
            <button v-else type="button" class="gs-btn gs-btn-ghost gs-btn-sm"
                :disabled="processingId === item.id" @click="unmarkReturned(item)">
                {{ processingId === item.id ? '...' : 'لغو مرجوعی' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

defineProps({ orderItems: Array })

const restockMap = reactive({})
const processingId = ref(null)

function markReturned(item) {
    processingId.value = item.id
    router.post(route('order-items.return', item.id), {
        restock: !!restockMap[item.id],
    }, {
        onFinish: () => processingId.value = null,
    })
}

function unmarkReturned(item) {
    processingId.value = item.id
    router.post(route('order-items.unreturn', item.id), {}, {
        onFinish: () => processingId.value = null,
    })
}

function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}
</script>

<style scoped>
.gs-return-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .625rem 0;
    border-bottom: 1px solid var(--gs-border);
}
.gs-return-row:last-child { border-bottom: none; }
.gs-checkbox-label {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .8rem;
    color: var(--gs-text-secondary);
    white-space: nowrap;
}
.gs-checkbox { accent-color: var(--gs-gold); }
</style>