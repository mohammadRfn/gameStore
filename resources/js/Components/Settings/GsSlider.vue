<script setup>
/**
 * GsSlider — اسلایدر با حباب مقدار شناور
 * مسیر: resources/js/Components/Settings/GsSlider.vue
 *
 * استفاده:
 *   <GsSlider v-model="form.volume" :min="0" :max="100" :formatter="v => percent(v)" />
 */
import { computed } from 'vue'
import { faInt } from '@/Utils/format'

const props = defineProps({
    modelValue: { type: Number, default: 0 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 100 },
    step: { type: Number, default: 1 },
    /** تابع نمایش مقدار روی حباب */
    formatter: { type: Function, default: null },
    /** رنگ خط پرشده — پیش‌فرض طلایی تم */
    accent: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const ratio = computed(() => {
    const span = props.max - props.min || 1
    return ((props.modelValue - props.min) / span) * 100
})

const bubble = computed(() =>
    props.formatter ? props.formatter(props.modelValue) : faInt(props.modelValue),
)

/** چون جهت صفحه RTL است، حباب از سمت راست جای‌گذاری می‌شود */
const bubbleStyle = computed(() => ({ insetInlineStart: `${100 - ratio.value}%` }))

const trackStyle = computed(() => ({
    '--st-p': `${ratio.value}%`,
    ...(props.accent ? { '--st-slider-accent': props.accent } : {}),
}))

function onInput(event) {
    emit('update:modelValue', Number(event.target.value))
}
</script>

<template>
    <div class="st-slider">
        <span class="st-slider__bubble" :style="bubbleStyle">{{ bubble }}</span>

        <input
            type="range"
            :min="min"
            :max="max"
            :step="step"
            :value="modelValue"
            :style="trackStyle"
            @input="onInput"
        />
    </div>
</template>
