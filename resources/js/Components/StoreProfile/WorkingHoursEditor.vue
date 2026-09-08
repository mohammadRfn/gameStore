<script setup>
/**
 * WorkingHoursEditor — ویرایشگر ساعات کاری هفته
 * مسیر: resources/js/Components/StoreProfile/WorkingHoursEditor.vue
 *
 * خروجی (modelValue) آرایه‌ای از { day, open, close } است که مستقیماً
 * در فیلد working_hours (نوع array در StoreProfile) ذخیره می‌شود.
 */
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه']

const rows = computed(() =>
    DAYS.map((day) => {
        const found = (props.modelValue || []).find((r) => r?.day === day)
        return { day, open: found?.open || '', close: found?.close || '' }
    }),
)

function update() {
    emit(
        'update:modelValue',
        rows.value
            .filter((r) => r.open || r.close)
            .map((r) => ({ day: r.day, open: r.open, close: r.close })),
    )
}
</script>

<template>
    <div style="display:flex; flex-direction:column; gap:0.45rem">
        <div
            v-for="row in rows"
            :key="row.day"
            style="display:grid; grid-template-columns:72px 1fr 1fr; align-items:center; gap:0.5rem"
        >
            <span class="cm-hint">{{ row.day }}</span>
            <input
                v-model="row.open"
                type="time"
                class="sp-input"
                style="min-height:36px; padding:0.2rem 0.5rem"
                @change="update"
            />
            <input
                v-model="row.close"
                type="time"
                class="sp-input"
                style="min-height:36px; padding:0.2rem 0.5rem"
                @change="update"
            />
        </div>
    </div>
</template>
