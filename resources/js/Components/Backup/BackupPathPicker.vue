<script setup>
import { computed } from 'vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import { formatBytes } from '@/Composables/useBackupCenter'

/**
 * انتخاب مسیر (ورودی یا خروجی).
 * تغییرات: برچسب‌های انگلیسی فارسی شدند، ورودی و دکمه‌ها هم‌قد (۴۴px) شدند
 * و ارتفاع کادر دقیقاً به اندازه‌ی محتوای داخلش است.
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  title: { type: String, default: 'مسیر' },
  icon: { type: String, default: '📁' },
  description: { type: String, default: '' },
  defaultPath: { type: String, default: '' },
  placeholder: { type: String, default: 'مسیر پوشه را وارد کنید' },
  actionLabel: { type: String, default: 'بررسی مسیر' },
  validated: { type: Boolean, default: false },
  freeSpace: { type: [Number, String], default: null },
  loading: { type: Boolean, default: false },
  hint: { type: String, default: '' },
  tone: { type: String, default: 'default' },
})

const emit = defineEmits(['update:modelValue', 'validate', 'bridge-missing'])

const freeSpaceLabel = computed(() =>
  props.freeSpace === null || props.freeSpace === '' ? '' : formatBytes(Number(props.freeSpace) * 1048576),
)

async function pickFolder() {
  const bridge = typeof window !== 'undefined' ? (window.gameStoreBridge || window.electronBridge) : null

  if (!bridge?.selectDirectory) {
    emit('bridge-missing')
    return
  }

  const selected = await bridge.selectDirectory()
  if (selected) emit('update:modelValue', selected)
}
</script>

<template>
  <BackupCard :title="title" :description="description" :icon="icon" :tone="tone">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <input
        :value="modelValue"
        type="text"
        dir="ltr"
        :placeholder="placeholder || defaultPath"
        class="h-11 w-full min-w-0 flex-1 rounded-xl bg-slate-950/50 px-3.5 text-[13px] text-slate-100 ring-1 ring-white/10 outline-none transition placeholder:text-slate-500 focus:ring-2 focus:ring-amber-400/60"
        @input="$emit('update:modelValue', $event.target.value)"
      >

      <div class="flex shrink-0 gap-2">
        <button
          type="button"
          class="h-11 rounded-xl bg-white/5 px-4 text-[13px] font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10"
          @click="pickFolder"
        >
          انتخاب پوشه
        </button>

        <button
          type="button"
          class="h-11 rounded-xl bg-amber-400/90 px-4 text-[13px] font-bold text-slate-900 transition hover:bg-amber-300 disabled:opacity-60"
          :disabled="loading"
          @click="$emit('validate')"
        >
          {{ loading ? 'در حال بررسی…' : actionLabel }}
        </button>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <span
        v-if="validated"
        class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-[11px] font-semibold leading-4 text-emerald-300 ring-1 ring-emerald-400/20"
      >مسیر تأیید شد</span>

      <span
        v-if="freeSpaceLabel"
        class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] leading-4 text-slate-300 ring-1 ring-white/10"
      >فضای آزاد: {{ freeSpaceLabel }}</span>

      <button
        v-if="defaultPath"
        type="button"
        class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] leading-4 text-slate-300 ring-1 ring-white/10 transition hover:bg-white/10"
        @click="$emit('update:modelValue', defaultPath)"
      >
        استفاده از مسیر پیش‌فرض
      </button>
    </div>

    <p v-if="hint" class="text-xs leading-6 text-slate-400">{{ hint }}</p>
  </BackupCard>
</template>
