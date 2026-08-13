<template>
    <div class="gs-card" v-if="invoice.is_returned">
        <p class="gs-label" style="margin-bottom:1rem">برگشت به انبار</p>

        <div v-if="!restockableItems.length" class="gs-muted" style="text-align:center;padding:1rem">
            قلمی برای برگشت به انبار وجود ندارد
        </div>

        <div v-for="item in restockableItems" :key="item.id" class="gs-restock-row">
            <label class="gs-checkbox-label" style="flex:1">
                <input type="checkbox" :value="item.id" v-model="selected" :disabled="!!item.restocked_at"
                    class="gs-checkbox" />
                <span>
                    {{ item.product_name }} — {{ item.quantity }} عدد
                    <span v-if="item.restocked_at" class="gs-badge gs-badge-success gs-badge-sm"
                        style="margin-right:.4rem">
                        برگشته به انبار
                    </span>
                </span>
            </label>
        </div>

        <div v-if="hasPendingRestock" style="margin-top:1rem;display:flex;justify-content:flex-end">
            <button type="button" class="gs-btn gs-btn-primary gs-btn-sm" :disabled="!selected.length || processing"
                @click="confirmRestock">
                {{ processing ? '...' : 'تأیید و برگشت به انبار' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ invoice: Object })

const restockableItems = computed(() =>
    (props.invoice.order_items ?? []).filter(i => i.deduct_from_stock)
)
const hasPendingRestock = computed(() =>
    restockableItems.value.some(i => !i.restocked_at)
)

const selected = ref([])
const processing = ref(false)

function confirmRestock() {
    processing.value = true
    router.post(route('invoices.restock-items', props.invoice.id), {
        order_item_ids: selected.value,
    }, {
        onFinish: () => { processing.value = false; selected.value = [] },
    })
}
</script>

<style scoped>
.gs-restock-row {
    display: flex;
    align-items: center;
    padding: .5rem 0;
    border-bottom: 1px solid var(--gs-border);
}

.gs-restock-row:last-child {
    border-bottom: none;
}

.gs-checkbox-label {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    cursor: pointer;
}

.gs-checkbox {
    accent-color: var(--gs-gold);
}
</style>