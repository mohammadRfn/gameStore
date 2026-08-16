<script setup>
/**
 * GsToggle — سوئیچ فنری
 * مسیر: resources/js/Components/Settings/GsToggle.vue
 *
 * استفاده:  <GsToggle v-model="form.autoClean" accent="green" />
 */
defineProps({
    modelValue: { type: Boolean, default: false },
    /** 'gold' (پیش‌فرض) یا 'green' */
    accent: { type: String, default: 'gold' },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <button
        type="button"
        role="switch"
        class="st-toggle"
        :class="[
            { 'is-on': modelValue },
            accent === 'green' ? 'st-toggle--green' : '',
        ]"
        :aria-checked="modelValue"
        :disabled="disabled"
        @click="emit('update:modelValue', !modelValue)"
    >
        <span class="st-toggle__knob" />
    </button>
</template>
