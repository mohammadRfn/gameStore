<script setup>
/**
 * CrmSegmented — کنترل بخش‌بندی‌شده با قرص لغزان، آیکون و شمارنده
 * مسیر: resources/js/Components/Crm/CrmSegmented.vue
 *
 * برخلاف GsSegmented، جای قرص با اندازه‌گیری واقعی دکمه‌ها تعیین می‌شود
 * تا با عرض‌های نابرابر، RTL و اسکرول افقی موبایل هم دقیق بماند.
 *
 * استفاده:
 *   <CrmSegmented v-model="status" :options="[
 *       { value: '', label: 'همه' },
 *       { value: 'pending', label: 'در انتظار', icon: Clock, tone: 'warning', count: 4 },
 *   ]" />
 */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { faInt } from '@/Utils/format'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    /** [{ value, label, icon?, tone?, count? }] */
    options: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const root = ref(null)
const btns = ref([])
const pill = ref({ left: 4, width: 0, tone: '' })

function measure() {
    const i = props.options.findIndex((o) => o.value === props.modelValue)
    const el = btns.value[i < 0 ? 0 : i]
    if (!el) return
    pill.value = {
        left: el.offsetLeft,
        width: el.offsetWidth,
        tone: props.options[i < 0 ? 0 : i]?.tone || '',
    }
    el.scrollIntoView?.({ block: 'nearest', inline: 'nearest', behavior: 'smooth' })
}

let ro = null
onMounted(() => {
    nextTick(measure)
    if (typeof ResizeObserver !== 'undefined' && root.value) {
        ro = new ResizeObserver(() => measure())
        ro.observe(root.value)
    }
    // بعد از لود فونت اندازه‌ها تغییر می‌کند
    document.fonts?.ready?.then(measure)
})

onBeforeUnmount(() => ro?.disconnect())

watch(() => [props.modelValue, props.options.length], () => nextTick(measure))
</script>

<template>
    <div ref="root" class="crm-seg" role="tablist">
        <span
            class="crm-seg__pill"
            :class="pill.tone ? `is-${pill.tone}` : ''"
            :style="{ left: pill.left + 'px', width: pill.width + 'px' }"
            aria-hidden="true"
        />

        <button
            v-for="(opt, i) in options"
            :key="opt.value"
            :ref="(el) => (btns[i] = el)"
            type="button"
            role="tab"
            class="crm-seg__btn"
            :class="{ 'is-active': opt.value === modelValue }"
            :aria-selected="opt.value === modelValue"
            @click="emit('update:modelValue', opt.value)"
        >
            <component :is="opt.icon" v-if="opt.icon" :size="14" />
            {{ opt.label }}
            <em v-if="opt.count !== undefined && opt.count !== null" class="crm-seg__count">
                {{ faInt(opt.count) }}
            </em>
        </button>
    </div>
</template>
