<script setup>
/**
 * CrmField — فیلد فرم هم‌خانواده با کنترل‌های st-* (برچسب + آیکون + خطا)
 * مسیر: resources/js/Components/Crm/CrmField.vue
 *
 * انواع: text | tel | email | number | textarea | select
 * برای select گزینه‌ها را با <option> در اسلات پیش‌فرض بدهید.
 *
 * استفاده:
 *   <CrmField v-model="form.name" label="نام مشتری" :icon="User" required :error="form.errors.name" />
 *   <CrmField v-model="form.notes" type="textarea" label="یادداشت" :rows="4" :maxlength="1000" />
 *   <CrmField v-model="form.customer_id" type="select" label="مشتری">
 *       <option value="">— بدون مشتری —</option>
 *   </CrmField>
 */
import { computed, useId } from 'vue'
import { AlertCircle, ChevronDown } from 'lucide-vue-next'
import { faInt } from '@/Utils/format'

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    label: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    dir: { type: String, default: 'auto' },
    rows: { type: Number, default: 4 },
    maxlength: { type: Number, default: null },
    autocomplete: { type: String, default: 'off' },
    /** آیکون/متن انتهایی داخل کادر */
    affix: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const id = useId()
const value = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : props.modelValue))
const isArea = computed(() => props.type === 'textarea')
const isSelect = computed(() => props.type === 'select')
const count = computed(() => String(value.value).length)

function onInput(e) {
    const raw = e.target.value
    emit('update:modelValue', props.type === 'number' ? (raw === '' ? null : Number(raw)) : raw)
}

function onChange(e) {
    emit('update:modelValue', e.target.value)
    emit('change', e)
}
</script>

<template>
    <div class="crm-field" :class="{ 'is-invalid': !!error, 'is-disabled': disabled }">
        <label v-if="label" :for="id" class="crm-field__label">
            <component :is="icon" v-if="icon" :size="14" />
            {{ label }}
            <b v-if="required" class="crm-field__req">*</b>
        </label>

        <div class="crm-field__box" :class="{ 'crm-field__box--area': isArea }">
            <textarea
                v-if="isArea"
                :id="id"
                :value="value"
                :rows="rows"
                :dir="dir"
                :placeholder="placeholder"
                :maxlength="maxlength ?? undefined"
                :disabled="disabled"
                @input="onInput"
            />

            <template v-else-if="isSelect">
                <select :id="id" :value="value" :disabled="disabled" @change="onChange">
                    <slot />
                </select>
                <ChevronDown :size="16" class="crm-field__chev" />
            </template>

            <input
                v-else
                :id="id"
                :type="type"
                :value="value"
                :dir="dir"
                :placeholder="placeholder"
                :maxlength="maxlength ?? undefined"
                :disabled="disabled"
                :autocomplete="autocomplete"
                @input="onInput"
            />

            <span v-if="affix && !isSelect" class="crm-field__affix">{{ affix }}</span>
        </div>

        <Transition name="crm-err" mode="out-in">
            <span v-if="error" key="e" class="crm-field__error">
                <AlertCircle :size="13" /> {{ error }}
            </span>
            <span v-else-if="hint" key="h" class="crm-field__hint">{{ hint }}</span>
            <span v-else-if="isArea && maxlength" key="c" class="crm-field__count">
                {{ faInt(count) }} / {{ faInt(maxlength) }}
            </span>
        </Transition>
    </div>
</template>
