<template>
  <section class="backup-glass backup-section">
    <div class="backup-section__head">
      <div>
        <h3 class="backup-section__title">
          <span>{{ icon }}</span>
          {{ title }}
        </h3>
        <p class="backup-section__desc">{{ description }}</p>
      </div>
      <span v-if="validated" class="backup-pill backup-pill--ok">✓ مسیر معتبر</span>
    </div>

    <div class="relative z-[1] grid gap-3">
      <div class="backup-field">
        <label>{{ label }}</label>
        <div class="grid gap-2 md:grid-cols-[1fr_auto_auto]">
          <input
            :value="modelValue"
            class="backup-input"
            dir="ltr"
            :placeholder="placeholder"
            @input="onInput"
            @keyup.enter="$emit('validate')"
          />
          <button type="button" class="backup-btn backup-btn--ghost" @click="chooseDirectory">
            انتخاب پوشه
          </button>
          <button type="button" class="backup-btn backup-btn--info" :disabled="loading" @click="$emit('validate')">
            <span v-if="loading" class="backup-spinner"></span>
            {{ loading ? 'در حال بررسی' : 'بررسی مسیر' }}
          </button>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 text-[0.74rem] text-[var(--gs-text-muted)]">
        <span class="backup-pill">پیش‌فرض: <b dir="ltr">{{ defaultPath || 'Documents' }}</b></span>
        <span v-if="freeSpace" class="backup-pill backup-pill--info">فضای آزاد: {{ freeSpace }}</span>
        <span class="backup-pill">سازگار با Windows / macOS / Linux</span>
      </div>

      <p v-if="hint" class="text-[0.74rem] leading-7 text-[var(--gs-text-secondary)]">{{ hint }}</p>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
  title: { type: String, default: 'مسیر' },
  label: { type: String, default: 'آدرس پوشه روی سیستم' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: 'مثلاً D:\\GameStore\\Backups' },
  defaultPath: { type: String, default: '' },
  hint: { type: String, default: '' },
  icon: { type: String, default: '📁' },
  loading: { type: Boolean, default: false },
  validated: { type: Boolean, default: false },
  freeSpace: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'validate', 'bridge-missing'])

function onInput(event) {
  emit('update:modelValue', event.target.value)
}

async function chooseDirectory() {
  const bridges = [
    window?.electronAPI?.selectDirectory,
    window?.nativeAPI?.selectDirectory,
    window?.Native?.selectDirectory,
    window?.gameStore?.selectDirectory,
  ].filter(Boolean)

  if (!bridges.length) {
    emit('bridge-missing')
    return
  }

  try {
    const result = await bridges[0]()
    const path = typeof result === 'string' ? result : (result?.path || result?.filePath || result?.directory)
    if (path) emit('update:modelValue', path)
  } catch (e) {
    emit('bridge-missing')
  }
}
</script>
