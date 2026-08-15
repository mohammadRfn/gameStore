<!-- ==========================================================================
     GameStore · ArchiveConfirmDialog
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveConfirmDialog.vue

     مودال تأیید با ورود چرخشی سه‌بعدی و فیلد اختیاری «دلیل»
     (فیلد reason دقیقاً همان چیزی است که کنترلر شما validate می‌کند).
     ========================================================================== -->
<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modelValue" class="modal-overlay" @click.self="cancel">
        <Transition name="modal-3d" appear>
          <div v-if="modelValue" class="modal a3d-holo" :class="`modal--${tone}`" role="alertdialog" aria-modal="true">
            <div class="modal-glow"></div>

            <div class="modal-body">
              <div class="modal-icon">{{ icon }}</div>

              <h3 class="modal-title">{{ title }}</h3>
              <p class="modal-message">{{ message }}</p>

              <div v-if="highlight" class="modal-highlight">{{ highlight }}</div>

              <label v-if="withReason" class="reason-field">
                <span>دلیل (اختیاری)</span>
                <textarea
                  v-model="reason"
                  rows="3"
                  maxlength="1000"
                  placeholder="مثلاً: تسویهٔ کامل و تحویل به مشتری انجام شد."
                ></textarea>
              </label>

              <div class="modal-actions">
                <button type="button" class="a3d-btn a3d-btn--ghost" :disabled="loading" @click="cancel">
                  انصراف
                </button>
                <button
                  type="button"
                  class="a3d-btn"
                  :class="tone === 'danger' ? 'a3d-btn--danger' : 'a3d-btn--gold'"
                  :disabled="loading"
                  @click="confirm"
                >
                  <span v-if="loading" class="spinner"></span>
                  {{ loading ? 'در حال انجام…' : confirmLabel }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'تأیید عملیات' },
  message: { type: String, default: 'آیا از انجام این عملیات مطمئن هستید؟' },
  highlight: { type: String, default: '' },
  confirmLabel: { type: String, default: 'تأیید' },
  tone: { type: String, default: 'gold' }, // 'gold' | 'danger'
  icon: { type: String, default: '⇥' },
  withReason: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const reason = ref('')

function cancel() {
  if (props.loading) return
  emit('update:modelValue', false)
  emit('cancel')
}

function confirm() {
  emit('confirm', reason.value.trim() || null)
}

watch(() => props.modelValue, (open) => {
  if (open) reason.value = ''
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 950;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.68);
  backdrop-filter: blur(7px);
  perspective: 1400px;
}

.modal {
  position: relative;
  width: min(460px, 100%);
  padding: 1.6rem 1.5rem 1.35rem;
  border-radius: var(--gs-radius-lg);
  background: var(--gs-bg-card-strong);
  border: 1px solid var(--gs-border-hover);
  box-shadow: var(--gs-shadow-md);
  text-align: center;
  transform-style: preserve-3d;
}

.modal-glow {
  position: absolute;
  inset: -30% 10% 60% 10%;
  border-radius: 50%;
  filter: blur(52px);
  opacity: 0.4;
  pointer-events: none;
  z-index: 0;
}

.modal--gold .modal-glow { background: var(--gs-gold-glow); }
.modal--danger .modal-glow { background: rgba(240, 106, 106, 0.35); }

.modal-body { position: relative; z-index: 2; }

.modal-icon {
  width: 58px;
  height: 58px;
  margin: 0 auto 0.85rem;
  display: grid;
  place-items: center;
  font-size: 1.5rem;
  border-radius: 18px;
  transform: translateZ(40px);
}

.modal--gold .modal-icon {
  background: var(--gs-gold-muted);
  border: 1px solid var(--gs-border-hover);
  color: var(--gs-gold);
  box-shadow: 0 12px 30px var(--gs-gold-glow);
}

.modal--danger .modal-icon {
  background: var(--gs-error-soft);
  border: 1px solid color-mix(in srgb, var(--gs-error) 40%, transparent);
  color: var(--gs-error);
  box-shadow: 0 12px 30px rgba(240, 106, 106, 0.25);
}

.modal-title {
  font-size: 1.02rem;
  font-weight: 800;
  color: var(--gs-text-primary);
  margin-bottom: 0.4rem;
  transform: translateZ(24px);
}

.modal-message {
  font-size: 0.82rem;
  line-height: 1.9;
  color: var(--gs-text-secondary);
}

.modal-highlight {
  margin-top: 0.75rem;
  padding: 0.6rem 0.75rem;
  border-radius: 12px;
  background: var(--gs-bg-elevated);
  border: 1px dashed var(--gs-border);
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--gs-text-primary);
  word-break: break-word;
}

.reason-field {
  display: block;
  margin-top: 1rem;
  text-align: right;
}

.reason-field span {
  display: block;
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--gs-text-muted);
  margin-bottom: 0.35rem;
}

.reason-field textarea {
  width: 100%;
  padding: 0.6rem 0.7rem;
  border-radius: 12px;
  border: 1px solid var(--gs-border-soft);
  background: var(--gs-bg);
  color: var(--gs-text-primary);
  font-family: inherit;
  font-size: 0.78rem;
  line-height: 1.8;
  resize: vertical;
  outline: none;
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
}

.reason-field textarea:focus {
  border-color: var(--gs-border-hover);
  box-shadow: 0 0 0 3px var(--gs-gold-muted);
}

.modal-actions {
  display: flex;
  gap: 0.6rem;
  justify-content: center;
  margin-top: 1.25rem;
}

.modal-actions .a3d-btn { flex: 1; }

.spinner {
  width: 13px;
  height: 13px;
  border-radius: 50%;
  border: 2px solid currentColor;
  border-top-color: transparent;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ترنزیشن‌ها */
.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity 0.28s ease; }
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }

.modal-3d-enter-active { transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease; }
.modal-3d-leave-active { transition: transform 0.25s ease-in, opacity 0.25s ease; }
.modal-3d-enter-from,
.modal-3d-leave-to {
  opacity: 0;
  transform: rotateX(-24deg) translateY(38px) scale(0.92);
}
</style>
