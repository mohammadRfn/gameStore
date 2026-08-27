<script setup>
/**
 * ردیف کلید روشن/خاموش با اندازه‌ی ثابت و یکسان.
 * قبلاً هر سوییچ ارتفاع و فاصله‌ی متفاوتی داشت؛ حالا همه هم‌قد هستند
 * و ارتفاع کادر دقیقاً به اندازه‌ی متن داخلش است.
 */
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <label
    class="flex cursor-pointer items-start gap-3 rounded-xl bg-white/[0.03] px-4 py-3 ring-1 ring-white/5 transition-colors duration-200 hover:bg-white/[0.06]"
    :class="disabled ? 'pointer-events-none opacity-50' : ''"
  >
    <input
      type="checkbox"
      class="peer sr-only"
      :checked="modelValue"
      :disabled="disabled"
      @change="$emit('update:modelValue', $event.target.checked)"
    >

    <span
      class="mt-0.5 flex h-5 w-9 shrink-0 items-center rounded-full bg-slate-700 p-0.5 transition-colors duration-200 peer-checked:bg-amber-400"
      aria-hidden="true"
    >
      <span
        class="size-4 rounded-full bg-white transition-transform duration-200"
        :class="modelValue ? '-translate-x-4' : 'translate-x-0'"
      />
    </span>

    <span class="min-w-0 flex-1">
      <span class="block text-[13px] font-semibold leading-5 text-slate-100">{{ label }}</span>
      <span v-if="hint" class="mt-0.5 block text-xs leading-5 text-slate-400">{{ hint }}</span>
    </span>
  </label>
</template>
