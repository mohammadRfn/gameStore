<template>
  <Teleport to="body">
    <Transition name="backup-fade">
      <div v-if="modelValue" class="backup-modal-overlay" @click.self="cancel">
        <Transition name="backup-pop" appear>
          <section v-if="modelValue" class="backup-modal" role="alertdialog" aria-modal="true">
            <div class="grid gap-4">
              <div class="flex items-start gap-3">
                <div class="grid h-14 w-14 place-items-center rounded-3xl text-3xl" :class="toneClass">
                  {{ icon }}
                </div>
                <div class="min-w-0 flex-1">
                  <h3 class="text-lg font-black text-[var(--gs-text-primary)]">{{ title }}</h3>
                  <p class="mt-1 text-[0.84rem] leading-8 text-[var(--gs-text-secondary)]">{{ message }}</p>
                </div>
              </div>

              <label v-if="confirmText" class="backup-field">
                <span class="backup-field__label">برای تأیید عبارت زیر را وارد کن:</span>
                <code class="rounded-xl border border-[var(--gs-border)] bg-[var(--gs-glass)] px-3 py-2 text-left text-[var(--gs-gold)]" dir="ltr">{{ confirmText }}</code>
                <input v-model="typed" class="backup-input" dir="ltr" :placeholder="confirmText" />
              </label>

              <label v-if="withCheckbox" class="backup-switch">
                <input v-model="checked" type="checkbox" />
                <span class="backup-switch__text">
                  <span class="backup-switch__title">{{ checkboxLabel }}</span>
                  <span class="backup-switch__hint">{{ checkboxHint }}</span>
                </span>
                <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
              </label>

              <div class="flex flex-wrap justify-end gap-2">
                <button type="button" class="backup-btn backup-btn--ghost" :disabled="loading" @click="cancel">انصراف</button>
                <button type="button" class="backup-btn" :class="buttonClass" :disabled="loading || !canConfirm" @click="confirm">
                  <span v-if="loading" class="backup-spinner"></span>
                  {{ loading ? 'در حال انجام...' : confirmLabel }}
                </button>
              </div>
            </div>
          </section>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'تأیید عملیات' },
  message: { type: String, default: 'آیا از انجام این عملیات مطمئن هستید؟' },
  icon: { type: String, default: '♛' },
  tone: { type: String, default: 'gold' },
  confirmLabel: { type: String, default: 'تأیید' },
  confirmText: { type: String, default: '' },
  withCheckbox: { type: Boolean, default: false },
  checkboxLabel: { type: String, default: 'فایل‌های فیزیکی هم حذف شوند' },
  checkboxHint: { type: String, default: 'در صورت فعال بودن، پوشه‌ی خروجی مرتبط نیز پاک می‌شود.' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])
const typed = ref('')
const checked = ref(false)

const canConfirm = computed(() => !props.confirmText || typed.value.trim() === props.confirmText)
const toneClass = computed(() => props.tone === 'danger' ? 'bg-[var(--gs-error-soft)] text-[var(--gs-error)]' : 'bg-[var(--gs-gold-muted)] text-[var(--gs-gold)]')
const buttonClass = computed(() => props.tone === 'danger' ? 'backup-btn--danger' : 'backup-btn--gold')

function cancel() {
  if (props.loading) return
  emit('update:modelValue', false)
  emit('cancel')
}

function confirm() {
  if (!canConfirm.value) return
  emit('confirm', { checked: checked.value })
}

watch(() => props.modelValue, (open) => {
  if (open) {
    typed.value = ''
    checked.value = false
  }
})
</script>
