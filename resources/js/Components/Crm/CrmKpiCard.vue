<script setup>
/**
 * CrmKpiCard — کارت شاخص با شمارندهٔ انیمیت‌شده، آیکون فنری و نوار رشد
 * مسیر: resources/js/Components/Crm/CrmKpiCard.vue
 *
 * استفاده:
 *   <CrmKpiCard
 *     label="کل مشتریان" :value="stats.customers_count" :icon="Users"
 *     accent="var(--gs-info)" hint="اعضای باشگاه مشتریان" :hint-icon="TrendingUp"
 *     :to="route('customers.index')" :delay="80" :fill="72" />
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowUpLeft } from 'lucide-vue-next'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { useCountUp } from '@/Composables/useCountUp'
import { faInt } from '@/Utils/format'

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: 0 },
    icon: { type: [Object, Function], required: true },
    accent: { type: String, default: 'var(--gs-gold)' },
    hint: { type: String, default: '' },
    hintIcon: { type: [Object, Function], default: null },
    to: { type: String, default: '' },
    delay: { type: Number, default: 0 },
    /** درصد پرشدن نوار پایین (صرفاً بصری) */
    fill: { type: Number, default: 62 },
    suffix: { type: String, default: '' },
})

const shown = useCountUp(() => props.value, { duration: 1300, delay: props.delay + 150 })
const tag = computed(() => (props.to ? Link : 'article'))
</script>

<template>
    <component
        :is="tag"
        v-reveal="{ delay }"
        v-tilt="{ max: 7, lift: 12, scale: 1.02 }"
        :href="to || undefined"
        class="a3d-holo a3d-aura crm-kpi"
        :style="{ '--crm-accent': accent, '--a3d-aura-color': accent, '--crm-fill': fill + '%' }"
    >
        <div class="crm-kpi__top">
            <span class="crm-kpi__icon"><component :is="icon" :size="22" /></span>
            <span v-if="to" class="crm-kpi__go"><ArrowUpLeft :size="14" /></span>
        </div>

        <p class="crm-kpi__label">{{ label }}</p>
        <p class="crm-kpi__value">
            {{ faInt(shown) }}<small v-if="suffix" style="font-size: 0.85rem; font-weight: 600; margin-inline-start: 0.3rem; color: var(--gs-text-muted)">{{ suffix }}</small>
        </p>
        <p v-if="hint" class="crm-kpi__hint">
            <component :is="hintIcon" v-if="hintIcon" :size="13" />
            {{ hint }}
        </p>

        <span class="crm-kpi__bar"><i /></span>
    </component>
</template>
