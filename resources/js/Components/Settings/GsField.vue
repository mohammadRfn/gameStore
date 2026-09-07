<script setup>
/**
 * GsField — ورودی متنی/عددی هم‌خانواده با کنترل‌های st-*
 * مسیر: resources/js/Components/Settings/GsField.vue
 *
 * برای تنظیماتی از نوع string / int / float در بک‌اند استفاده می‌شود
 * (مثل invoice.prefix، invoice.counter، desktop.database_path ...).
 *
 * استفاده:
 *   <GsField v-model="form['invoice.prefix']" placeholder="INV-" />
 *   <GsField v-model="form['invoice.tax_rate']" type="number" :step="0.1" suffix="%" />
 */
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    type: { type: String, default: 'text' }, // text | number
    placeholder: { type: String, default: '' },
    step: { type: [String, Number], default: 'any' },
    min: { type: [String, Number], default: null },
    max: { type: [String, Number], default: null },
    dir: { type: String, default: 'auto' }, // برای مسیرها ltr مناسب‌تر است
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const displayValue = computed(() =>
    props.modelValue === null || props.modelValue === undefined
        ? ''
        : props.modelValue,
)

function onInput(event) {
    const raw = event.target.value
    if (props.type === 'number') {
        emit('update:modelValue', raw === '' ? null : Number(raw))
    } else {
        emit('update:modelValue', raw)
    }
}
</script>

<template>
    <label class="gsf" :class="{ 'is-invalid': invalid, 'is-disabled': disabled }">
        <span v-if="prefix" class="gsf__affix gsf__affix--pre">{{ prefix }}</span>

        <input
            class="gsf__input"
            :type="type"
            :dir="dir"
            :value="displayValue"
            :placeholder="placeholder"
            :step="type === 'number' ? step : undefined"
            :min="min ?? undefined"
            :max="max ?? undefined"
            :disabled="disabled"
            @input="onInput"
        />

        <span v-if="suffix" class="gsf__affix gsf__affix--suf">{{ suffix }}</span>
    </label>
</template>

<style scoped>
.gsf {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 190px;
    padding: 0 0.85rem;
    height: 42px;
    border-radius: 12px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    transition: border-color var(--gs-transition), box-shadow var(--gs-transition),
        background var(--gs-transition);
}

.gsf:focus-within {
    border-color: var(--gs-border-hover);
    background: var(--gs-glass-hover);
    box-shadow: 0 0 0 4px var(--gs-gold-muted);
}

.gsf.is-invalid {
    border-color: var(--gs-error);
    box-shadow: 0 0 0 4px var(--gs-error-soft);
}

.gsf.is-disabled {
    opacity: 0.55;
    pointer-events: none;
}

.gsf__input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.92rem;
    text-align: start;
}

.gsf__input::placeholder {
    color: var(--gs-text-muted);
}

.gsf__affix {
    flex: none;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--gs-gold);
    user-select: none;
}
</style>
