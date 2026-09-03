<script setup>
/**
 * ردیف کلید روشن/خاموش — نسخه‌ی هماهنگ با تم (dark/light).
 * رنگ‌های ثابت (slate/amber/white) با توکن‌های --gs-* جایگزین شد.
 * props و emit دقیقاً مثل قبل.
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
  <label class="bk-toggle" :class="{ 'is-disabled': disabled }">
    <input
      type="checkbox"
      class="bk-toggle__input"
      :checked="modelValue"
      :disabled="disabled"
      @change="$emit('update:modelValue', $event.target.checked)"
    >

    <span class="bk-toggle__track" :class="{ 'is-on': modelValue }" aria-hidden="true">
      <span class="bk-toggle__knob" :class="{ 'is-on': modelValue }" />
    </span>

    <span class="bk-toggle__body">
      <span class="bk-toggle__label">{{ label }}</span>
      <span v-if="hint" class="bk-toggle__hint">{{ hint }}</span>
    </span>
  </label>
</template>

<style scoped>
.bk-toggle {
  display: flex;
  cursor: pointer;
  align-items: flex-start;
  gap: .75rem;
  border-radius: .75rem;
  border: 1px solid var(--gs-border-soft);
  background: color-mix(in srgb, var(--gs-text-primary) 3%, transparent);
  padding: .75rem 1rem;
  transition: background-color .2s ease, border-color .2s ease;
}

.bk-toggle:hover {
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  border-color: var(--gs-border);
}

.bk-toggle.is-disabled {
  pointer-events: none;
  opacity: .5;
}

.bk-toggle__input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.bk-toggle__track {
  margin-top: .125rem;
  display: flex;
  height: 1.25rem;
  width: 2.25rem;
  flex-shrink: 0;
  align-items: center;
  border-radius: 999px;
  padding: .125rem;
  background: color-mix(in srgb, var(--gs-text-muted) 45%, transparent);
  transition: background-color .2s ease;
}

.bk-toggle__track.is-on {
  background: var(--gs-gold);
}

.bk-toggle__knob {
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  background: var(--gs-text-muted);
  box-shadow: var(--gs-shadow-sm);
  transition: transform .2s ease, background-color .2s ease;
}
.bk-toggle__knob.is-on {
  background: var(--gs-bg);
}

.bk-toggle__knob.is-on {
  /* RTL: روشن یعنی به سمت راست */
  transform: translateX(-1rem);
}

.bk-toggle__body {
  min-width: 0;
  flex: 1;
}

.bk-toggle__label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.25rem;
  color: var(--gs-text-primary);
}

.bk-toggle__hint {
  margin-top: .125rem;
  display: block;
  font-size: .75rem;
  line-height: 1.25rem;
  color: var(--gs-text-muted);
}
</style>
