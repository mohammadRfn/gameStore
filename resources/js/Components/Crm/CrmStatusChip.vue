<script setup>
/**
 * CrmStatusChip — چیپ وضعیت درخواست / تأیید فاکتور
 * مسیر: resources/js/Components/Crm/CrmStatusChip.vue
 *
 * استفاده:
 *   <CrmStatusChip :status="req.status" />            ← وضعیت درخواست
 *   <CrmStatusChip :invoice="inv" />                   ← وضعیت تأیید فاکتور
 *   <CrmStatusChip :status="req.status" long sm />
 */
import { computed } from 'vue'
import { invoiceMeta, statusMeta } from '@/Utils/crm'

const props = defineProps({
    status: { type: String, default: '' },
    invoice: { type: Object, default: null },
    long: { type: Boolean, default: false },
    sm: { type: Boolean, default: false },
    icon: { type: Boolean, default: true },
})

const meta = computed(() => (props.invoice ? invoiceMeta(props.invoice) : statusMeta(props.status)))
const text = computed(() => (props.long ? meta.value.long || meta.value.label : meta.value.label))
</script>

<template>
    <span
        class="crm-status"
        :class="[`crm-status--${meta.tone}`, { 'crm-status--sm': sm, 'is-live': meta.live }]"
    >
        <span class="crm-status__dot" />
        <component :is="meta.icon" v-if="icon && meta.icon" :size="sm ? 11 : 13" />
        {{ text }}
    </span>
</template>
