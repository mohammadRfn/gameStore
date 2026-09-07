<script setup>
/**
 * GsTextArea — ورودی چندخطی هم‌خانواده با کنترل‌های st-*
 * مسیر: resources/js/Components/Settings/GsTextArea.vue
 *
 * برای فیلدهای طولانی مثل invoice.footer_text و invoice.warranty_terms.
 *
 * استفاده:
 *   <GsTextArea v-model="form['invoice.footer_text']" :rows="3" :maxlength="1000" />
 */
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: [String, null], default: '' },
    rows: { type: Number, default: 3 },
    maxlength: { type: Number, default: null },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const value = computed(() => props.modelValue ?? '')
const count = computed(() => (value.value || '').length)

function onInput(event) {
    const raw = event.target.value
    emit('update:modelValue', raw === '' ? null : raw)
}
</script>

<template>
    <div class="gsta" :class="{ 'is-disabled': disabled }">
        <textarea
            class="gsta__input"
            :rows="rows"
            :maxlength="maxlength ?? undefined"
            :placeholder="placeholder"
            :disabled="disabled"
            :value="value"
            @input="onInput"
        />

        <span v-if="maxlength" class="gsta__count">
            {{ count }} / {{ maxlength }}
        </span>
    </div>
</template>

<style scoped>
.gsta {
    position: relative;
    width: 100%;
}

.gsta__input {
    width: 100%;
    resize: vertical;
    padding: 0.75rem 0.9rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.9rem;
    line-height: 1.9;
    transition: border-color var(--gs-transition), box-shadow var(--gs-transition),
        background var(--gs-transition);
}

.gsta__input:focus {
    outline: none;
    border-color: var(--gs-border-hover);
    background: var(--gs-glass-hover);
    box-shadow: 0 0 0 4px var(--gs-gold-muted);
}

.gsta__input::placeholder {
    color: var(--gs-text-muted);
}

.gsta__count {
    position: absolute;
    inset-block-end: 0.55rem;
    inset-inline-start: 0.8rem;
    font-size: 0.68rem;
    color: var(--gs-text-muted);
    pointer-events: none;
}

.gsta.is-disabled {
    opacity: 0.55;
    pointer-events: none;
}
</style>
