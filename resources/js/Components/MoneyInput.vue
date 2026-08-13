<template>
    <input type="text" inputmode="numeric" class="gs-input" :class="{ 'gs-input-error': error }"
        :placeholder="placeholder" :value="displayValue" @input="onInput" />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: [Number, String],
    placeholder: { type: String, default: '' },
    error: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const displayValue = computed(() => formatMoney(props.modelValue))

function formatMoney(v) {
    return (v || v === 0) ? Number(v).toLocaleString('en-US') : ''
}

function onInput(e) {
    const raw = e.target.value.replace(/[^0-9]/g, '')
    const num = raw ? Number(raw) : ''
    emit('update:modelValue', num)
    e.target.value = formatMoney(num)
}
</script>