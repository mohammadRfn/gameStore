<script setup>
/**
 * GsSegmented — کنترل بخش‌بندی‌شده با قرص لغزان
 * مسیر: resources/js/Components/Settings/GsSegmented.vue
 *
 * استفاده:
 *   <GsSegmented
 *     v-model="form.theme"
 *     :options="[{ value:'dark', label:'تیره', swatch:'#14141f' }, ...]"
 *   />
 */
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    /** [{ value, label, swatch? }] */
    options: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const count = computed(() => props.options.length || 1)

const activeIndex = computed(() => {
    const i = props.options.findIndex((o) => o.value === props.modelValue)
    return i < 0 ? 0 : i
})

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${count.value}, minmax(0, 1fr))`,
}))

/** عرض و جای قرص بر مبنای تعداد گزینه‌ها (سازگار با RTL از طریق inset-inline) */
const pillStyle = computed(() => {
    const unit = `((100% - 8px) / ${count.value})`
    return {
        width: `calc(${unit})`,
        insetInlineStart: `calc(${activeIndex.value} * ${unit} + 4px)`,
    }
})
</script>

<template>
    <div class="st-seg" :style="gridStyle">
        <span class="st-seg__pill" :style="pillStyle" aria-hidden="true" />

        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            class="st-seg__btn"
            :class="{ 'is-active': opt.value === modelValue }"
            @click="emit('update:modelValue', opt.value)"
        >
            <span
                v-if="opt.swatch"
                class="st-seg__swatch"
                :style="{ background: opt.swatch }"
            />
            {{ opt.label }}
        </button>
    </div>
</template>
