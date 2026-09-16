<script setup>
/**
 * CrmStatusSteps — نمایش چرخهٔ عمر درخواست (در انتظار → در جریان → تکمیل)
 * مسیر: resources/js/Components/Crm/CrmStatusSteps.vue
 *
 * وضعیت canceled به‌صورت مسیر خاکستری + بنر لغو نمایش داده می‌شود.
 * وضعیت‌ها همان ثابت‌های مدل Request بک‌اند هستند؛ تغییر وضعیت از این‌جا
 * انجام نمی‌شود (توسط فاکتور/سرویس در بک‌اند مدیریت می‌شود).
 *
 * استفاده:  <CrmStatusSteps :status="request.status" />
 */
import { computed } from 'vue'
import { Check, CheckCircle2, Clock, Wrench, XCircle } from 'lucide-vue-next'
import { statusMeta } from '@/Utils/crm'

const props = defineProps({
    status: { type: String, default: 'pending' },
})

const STEPS = [
    { key: 'pending', label: 'ثبت درخواست', sub: 'در صف رسیدگی', icon: Clock },
    { key: 'in_progress', label: 'بررسی و تعمیر', sub: 'در کارگاه', icon: Wrench },
    { key: 'completed', label: 'تکمیل', sub: 'آمادهٔ تحویل', icon: CheckCircle2 },
]

const meta = computed(() => statusMeta(props.status))
const canceled = computed(() => props.status === 'canceled')
const current = computed(() => (canceled.value ? -1 : meta.value.step))
const progress = computed(() => (current.value <= 0 ? 0 : (current.value / (STEPS.length - 1)) * 100))

function stateOf(i) {
    if (canceled.value) return ''
    if (i < current.value) return 'is-done'
    if (i === current.value) return current.value === STEPS.length - 1 ? 'is-done' : 'is-current'
    return ''
}
</script>

<template>
    <div>
        <div class="crm-steps" :class="{ 'is-canceled': canceled }" :style="{ '--crm-progress': progress + '%' }">
            <span class="crm-steps__line"><span class="crm-steps__fill" /></span>

            <div v-for="(s, i) in STEPS" :key="s.key" class="crm-step" :class="stateOf(i)">
                <span class="crm-step__dot">
                    <Check v-if="stateOf(i) === 'is-done'" :size="17" />
                    <component :is="s.icon" v-else :size="17" />
                </span>
                <p class="crm-step__t">{{ s.label }}</p>
                <p class="crm-step__s">{{ s.sub }}</p>
            </div>
        </div>

        <div v-if="canceled" class="crm-steps__canceled">
            <XCircle :size="16" />
            این درخواست لغو شده است و در جریان کار قرار ندارد.
        </div>
    </div>
</template>
