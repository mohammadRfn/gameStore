<script setup>
import { computed } from 'vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import { formatBytes } from '@/Composables/useBackupCenter'

/**
 * انتخاب مسیر (ورودی/خروجی) — نسخه‌ی هماهنگ با تم (dark/light).
 * منطق و props مثل قبل؛ فقط رنگ‌ها با توکن --gs-* جایگزین شد.
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
  try {
    const { data } = await axios.post('/backup/pick-directory', {
      default_path: props.modelValue || props.defaultPath,
    })

    if (data?.data?.path) emit('update:modelValue', data.data.path)
  } catch (e) {
    const status = e?.response?.status
    const detail = e?.response?.data?.message

    console.error('pick-directory failed:', status, detail || e.message)
    emit('bridge-missing', detail ? `${detail} (کد ${status})` : null)
  }
}
</script>

<template>
  <BackupCard :title="title" :description="description" :icon="icon" :tone="tone">
    <div class="bk-path__row">
      <input
        :value="modelValue"
        type="text"
        dir="ltr"
        :placeholder="placeholder || defaultPath"
        class="bk-input"
        @input="$emit('update:modelValue', $event.target.value)"
      >

      <div class="bk-path__actions">
        <button type="button" class="bk-btn-soft" @click="pickFolder">
          انتخاب پوشه
        </button>

        <button
          type="button"
          class="bk-btn-gold"
          :disabled="loading"
          @click="$emit('validate')"
        >
          {{ loading ? 'در حال بررسی…' : actionLabel }}
        </button>
      </div>
    </div>

    <div class="bk-path__meta">
      <span v-if="validated" class="bk-tag bk-tag--ok">مسیر تأیید شد</span>

      <span v-if="freeSpaceLabel" class="bk-tag">فضای آزاد: {{ freeSpaceLabel }}</span>

      <button
        v-if="defaultPath"
        type="button"
        class="bk-tag bk-tag--btn"
        @click="$emit('update:modelValue', defaultPath)"
      >
        استفاده از مسیر پیش‌فرض
      </button>
    </div>

    <p v-if="hint" class="bk-path__hint">{{ hint }}</p>
  </BackupCard>
</template>

<style scoped>
.bk-path__row {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}
@media (min-width: 640px) {
  .bk-path__row { flex-direction: row; align-items: center; }
}

.bk-input {
  height: 2.75rem;
  width: 100%;
  min-width: 0;
  flex: 1;
  border-radius: .75rem;
  border: 1px solid var(--gs-border);
  background: var(--gs-bg-soft);
  padding: 0 .875rem;
  font-size: 13px;
  color: var(--gs-text-primary);
  outline: none;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.bk-input::placeholder { color: var(--gs-text-muted); }
.bk-input:focus {
  border-color: var(--gs-border-hover);
  box-shadow: 0 0 0 3px var(--gs-gold-muted);
}

.bk-path__actions {
  display: flex;
  flex-shrink: 0;
  gap: .5rem;
}

.bk-btn-soft {
  height: 2.75rem;
  border-radius: .75rem;
  border: 1px solid var(--gs-border-soft);
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  padding: 0 1rem;
  font-size: 13px;
  font-weight: 600;
  color: var(--gs-text-primary);
  transition: background-color .2s ease, border-color .2s ease;
}
.bk-btn-soft:hover {
  background: color-mix(in srgb, var(--gs-text-primary) 11%, transparent);
  border-color: var(--gs-border);
}

.bk-btn-gold {
  height: 2.75rem;
  border-radius: .75rem;
  border: none;
  background: var(--gs-gold-grad);
  padding: 0 1rem;
  font-size: 13px;
  font-weight: 700;
  color: var(--gs-bg);
  transition: filter .2s ease, opacity .2s ease;
}
.bk-btn-gold:hover { filter: brightness(1.06); }
.bk-btn-gold:disabled { opacity: .6; cursor: not-allowed; }

.bk-path__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem;
}

.bk-tag {
  border-radius: 999px;
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  border: 1px solid var(--gs-border-soft);
  padding: .25rem .625rem;
  font-size: 11px;
  line-height: 1rem;
  color: var(--gs-text-secondary);
}
.bk-tag--ok {
  background: var(--gs-success-soft);
  border-color: color-mix(in srgb, var(--gs-success) 30%, transparent);
  color: var(--gs-success);
  font-weight: 600;
}
.bk-tag--btn { transition: background-color .2s ease; }
.bk-tag--btn:hover { background: color-mix(in srgb, var(--gs-text-primary) 11%, transparent); }

.bk-path__hint {
  font-size: .75rem;
  line-height: 1.5rem;
  color: var(--gs-text-muted);
}
</style>
