<!--
  resources/js/Components/JDate.vue
  ------------------------------------------------------------------
  کامپوننت Vue برای نمایش هر تاریخی به‌صورت شمسی و فارسی.

  استفاده:
      <JDate :value="inv.created_at" />                 →  ۲۱ مرداد ۱۴۰۴
      <JDate :value="inv.created_at" format="short" />  →  ۲۱ مرداد
      <JDate :value="inv.paid_at" format="numeric" />   →  ۱۴۰۴/۰۵/۲۱
-->
<template>
    <span class="j-date" :title="titleText">{{ text }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { faLabel, jalaliFull, jalaliNumeric } from '@/Utils/jalali'

const props = defineProps({
    value: { type: [String, Number, Date], default: '' },
    /** long | short | numeric */
    format: { type: String, default: 'long' },
    empty: { type: String, default: '—' },
})

const text = computed(() => {
    if (props.value === null || props.value === undefined || props.value === '') return props.empty
    if (props.format === 'numeric') return jalaliNumeric(props.value) || faLabel(props.value)
    return faLabel(props.value, { long: props.format !== 'short' })
})

const titleText = computed(() => jalaliFull(props.value) || '')
</script>

<style scoped>
.j-date {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
</style>
