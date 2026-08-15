<!-- ==========================================================================
     GameStore · ArchiveToaster
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveToaster.vue

     نوتیفیکیشن‌های شناور با ورود سه‌بعدی و نوار عمر (progress).
     استفاده: <ArchiveToaster :toasts="toasts" @dismiss="removeToast" />
     ========================================================================== -->
<template>
  <Teleport to="body">
    <div class="toaster" aria-live="polite">
      <TransitionGroup name="toast3d">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="toast"
          :class="`toast--${toast.type}`"
          role="status"
        >
          <span class="toast-icon">{{ iconFor(toast.type) }}</span>

          <div class="toast-content">
            <p class="toast-title">{{ titleFor(toast.type) }}</p>
            <p class="toast-text">{{ toast.message }}</p>
          </div>

          <button type="button" class="toast-close" aria-label="بستن" @click="$emit('dismiss', toast.id)">
            ✕
          </button>

          <span class="toast-progress" :style="{ animationDuration: `${toast.duration}ms` }"></span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  toasts: { type: Array, default: () => [] },
})

defineEmits(['dismiss'])

const icons = { success: '✓', error: '!', info: 'i', warning: '⚠' }
const titles = { success: 'انجام شد', error: 'خطا', info: 'اطلاع', warning: 'هشدار' }

const iconFor = (type) => icons[type] ?? 'i'
const titleFor = (type) => titles[type] ?? 'اطلاع'
</script>

<style scoped>
.toaster {
  position: fixed;
  top: 74px;
  left: 1.1rem;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  width: min(360px, calc(100vw - 2.2rem));
  perspective: 900px;
  pointer-events: none;
}

.toast {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.8rem 0.85rem;
  border-radius: 14px;
  background: var(--gs-bg-card-strong);
  border: 1px solid var(--gs-border);
  box-shadow: var(--gs-shadow-md);
  overflow: hidden;
  pointer-events: auto;
  backdrop-filter: blur(14px);
}

.toast--success { border-color: color-mix(in srgb, var(--gs-success) 40%, transparent); }
.toast--error   { border-color: color-mix(in srgb, var(--gs-error) 40%, transparent); }
.toast--warning { border-color: color-mix(in srgb, var(--gs-warning) 40%, transparent); }
.toast--info    { border-color: color-mix(in srgb, var(--gs-info) 40%, transparent); }

.toast-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  font-size: 0.82rem;
  font-weight: 900;
}

.toast--success .toast-icon { background: var(--gs-success-soft); color: var(--gs-success); }
.toast--error   .toast-icon { background: var(--gs-error-soft);   color: var(--gs-error); }
.toast--warning .toast-icon { background: var(--gs-warning-soft); color: var(--gs-warning); }
.toast--info    .toast-icon { background: var(--gs-info-soft);    color: var(--gs-info); }

.toast-content { flex: 1; min-width: 0; }

.toast-title {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--gs-text-primary);
  margin-bottom: 0.1rem;
}

.toast-text {
  font-size: 0.74rem;
  line-height: 1.75;
  color: var(--gs-text-secondary);
  word-break: break-word;
}

.toast-close {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  border: none;
  background: none;
  color: var(--gs-text-muted);
  cursor: pointer;
  border-radius: 6px;
  font-size: 0.7rem;
  transition: color 0.2s ease, background 0.2s ease;
}

.toast-close:hover { color: var(--gs-text-primary); background: var(--gs-glass-hover); }

.toast-progress {
  position: absolute;
  bottom: 0;
  right: 0;
  height: 2px;
  width: 100%;
  transform-origin: right center;
  animation: toast-drain linear forwards;
}

.toast--success .toast-progress { background: var(--gs-success); }
.toast--error   .toast-progress { background: var(--gs-error); }
.toast--warning .toast-progress { background: var(--gs-warning); }
.toast--info    .toast-progress { background: var(--gs-info); }

@keyframes toast-drain {
  from { transform: scaleX(1); }
  to   { transform: scaleX(0); }
}

/* ترنزیشن سه‌بعدی */
.toast3d-enter-active { transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1); }
.toast3d-leave-active { transition: all 0.3s ease-in; position: absolute; width: 100%; }
.toast3d-move { transition: transform 0.35s ease; }

.toast3d-enter-from {
  opacity: 0;
  transform: translateX(-40px) rotateY(28deg) scale(0.9);
}

.toast3d-leave-to {
  opacity: 0;
  transform: translateX(-30px) scale(0.94);
}
</style>
