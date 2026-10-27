<script setup>
/**
 * CrmCollapse — کادر کوچک اختیاریِ قابل‌جمع‌شدن (شرینک) هم‌تم با پنل‌های CRM
 * مسیر: resources/js/Components/Crm/CrmCollapse.vue
 *
 *   <CrmCollapse title="ایجاد مشتری جدید" :icon="UserPlus" badge="۲" v-model:open="open">
 *       …محتوا…
 *   </CrmCollapse>
 *
 * اگر v-model:open داده نشود، وضعیت را خودش نگه می‌دارد (پیش‌فرض: بسته).
 */
import { computed, ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'

const props = defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    badge: { type: [String, Number], default: '' },
    optional: { type: Boolean, default: true },
    open: { type: Boolean, default: undefined },
    defaultOpen: { type: Boolean, default: false },
})
const emit = defineEmits(['update:open'])

const inner = ref(props.defaultOpen)
const isOpen = computed(() => (props.open === undefined ? inner.value : props.open))

function toggle() {
    const next = !isOpen.value
    inner.value = next
    emit('update:open', next)
}
</script>

<template>
    <section class="rb-box" :class="{ 'is-open': isOpen }">
        <button type="button" class="rb-box__head" :aria-expanded="isOpen" @click="toggle">
            <span v-if="icon" class="rb-box__icon"><component :is="icon" :size="15" /></span>
            <span class="rb-box__title">
                {{ title }}
                <small v-if="hint">{{ hint }}</small>
            </span>
            <span v-if="badge !== '' && badge !== null" class="rb-box__badge">{{ badge }}</span>
            <span v-if="optional" class="rb-box__opt">اختیاری</span>
            <ChevronDown :size="16" class="rb-box__chev" />
        </button>

        <div class="rb-box__wrap">
            <div class="rb-box__inner">
                <div class="rb-box__body"><slot /></div>
            </div>
        </div>
    </section>
</template>
