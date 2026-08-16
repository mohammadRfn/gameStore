<script setup>
/**
 * GsSelect — سلکت سفارشی با منوی کشویی و بستن با کلیک بیرون
 * مسیر: resources/js/Components/Settings/GsSelect.vue
 *
 * استفاده:
 *   <GsSelect v-model="form.lang" :options="[{ value:'fa', label:'فارسی' }]" />
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    /** [{ value, label }] */
    options: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const root = ref(null)

const current = computed(
    () => props.options.find((o) => o.value === props.modelValue) || props.options[0],
)

function onDocClick(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false
}

function onEsc(event) {
    if (event.key === 'Escape') open.value = false
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onDocClick)
        document.addEventListener('keydown', onEsc)
    } else {
        document.removeEventListener('mousedown', onDocClick)
        document.removeEventListener('keydown', onEsc)
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick)
    document.removeEventListener('keydown', onEsc)
})

function pick(value) {
    emit('update:modelValue', value)
    open.value = false
}
</script>

<template>
    <div ref="root" class="st-select">
        <button
            type="button"
            class="st-select__trigger"
            :class="{ 'is-open': open }"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
        >
            <span>{{ current?.label }}</span>
            <ChevronDown :size="16" class="st-select__chev" />
        </button>

        <Transition name="st-fade">
            <div v-if="open" class="st-select__menu" role="listbox">
                <button
                    v-for="opt in options"
                    :key="opt.value"
                    type="button"
                    role="option"
                    class="st-select__opt"
                    :class="{ 'is-selected': opt.value === modelValue }"
                    :aria-selected="opt.value === modelValue"
                    @click="pick(opt.value)"
                >
                    <span>{{ opt.label }}</span>
                    <Check v-if="opt.value === modelValue" :size="15" />
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.st-fade-enter-active,
.st-fade-leave-active {
    transition: opacity 0.18s ease, transform 0.18s cubic-bezier(0.22, 1, 0.36, 1);
}
.st-fade-enter-from,
.st-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}
</style>
