<script setup>
/**
 * ServiceInvoiceAttach — کارت کوچک «افزودن سرویس تحویل‌شده به فاکتور مربوطه»
 * مسیر: resources/js/Components/ServiceInvoiceAttach.vue
 *
 * فقط وقتی target دارد که: سرویس «تحویل داده شده» باشد، هنوز به هیچ فاکتوری وصل نشده باشد،
 * درخواستش از دسته‌بندی «تعمیرات/خدمات» باشد و فاکتور پرداخت‌نشدهٔ همان درخواست وجود داشته باشد.
 * با یک بار افزودن، target تهی می‌شود و کارت ناپدید می‌شود؛ اگر سرویس از فاکتور حذف شود دوباره ظاهر می‌شود.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Check, ClipboardList, Receipt, RefreshCw } from 'lucide-vue-next'
import { faInt, money } from '@/Utils/format'
import { useToasts } from '@/Composables/useToasts'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    jobId: { type: Number, required: true },
    target: { type: Object, default: null },
})

const { toasts, dismiss } = useToasts({ flash: true })
const busy = ref(false)

function attach() {
    if (busy.value) return
    router.post(route('service-jobs.attach-invoice', props.jobId), {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    })
}
</script>

<template>
    <div>
        <section v-if="target" class="sia">
            <header class="sia__head">
                <Receipt :size="14" />
                <span>افزودن به فاکتور مربوطه</span>
                <em>یک‌بار مصرف</em>
            </header>

            <div class="sia__card">
                <div class="sia__row">
                    <b dir="ltr">{{ target.invoice_number }}</b>
                    <span>{{ money(target.total_amount) }}</span>
                </div>
                <p class="sia__req">
                    <ClipboardList :size="12" />
                    درخواست #{{ faInt(target.request.id) }} — {{ target.request.customer_name }}
                </p>
                <p v-if="target.request.description" class="sia__desc">{{ target.request.description }}</p>
                <div v-if="target.request.categories?.length" class="sia__tags">
                    <span v-for="c in target.request.categories" :key="c">{{ c }}</span>
                </div>
            </div>

            <button type="button" class="a3d-btn a3d-btn--gold a3d-btn--sm sia__btn" :disabled="busy" @click="attach">
                <RefreshCw v-if="busy" :size="14" class="crm-spin" />
                <Check v-else :size="14" />
                افزودن به این فاکتور
            </button>
        </section>

        <ToastHost :toasts="toasts" @close="dismiss" />
    </div>
</template>

<style scoped>
.sia {
    display: grid;
    gap: 0.55rem;
    padding: 0.7rem 0.8rem;
    margin-bottom: 1rem;
    border-radius: 14px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-gold-muted);
}
.sia__head { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; font-weight: 800; color: var(--gs-gold); }
.sia__head em {
    margin-inline-start: auto; font-style: normal; font-size: 0.62rem; font-weight: 700;
    padding: 0.05rem 0.5rem; border-radius: 999px; color: var(--gs-text-muted); border: 1px dashed var(--gs-border);
}
.sia__card {
    display: grid; gap: 0.25rem; padding: 0.5rem 0.65rem; border-radius: 10px;
    background: var(--gs-bg-card-strong); border: 1px solid var(--gs-border);
}
.sia__row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.78rem; color: var(--gs-text-primary); }
.sia__row span { font-weight: 700; color: var(--gs-gold); font-size: 0.74rem; }
.sia__req { display: flex; align-items: center; gap: 0.3rem; margin: 0; font-size: 0.7rem; font-weight: 600; color: var(--gs-text-secondary); }
.sia__desc {
    margin: 0; font-size: 0.68rem; color: var(--gs-text-muted);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sia__tags { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.sia__tags span { padding: 0.04rem 0.5rem; border-radius: 999px; font-size: 0.64rem; font-weight: 600; color: var(--gs-text-secondary); background: var(--gs-glass); border: 1px solid var(--gs-border-soft); }
.sia__btn { justify-self: end; }
</style>
