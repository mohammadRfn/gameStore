<template>
  <Teleport to="body">
    <div class="backup-toast-wrap">
      <TransitionGroup name="backup-pop">
        <article v-for="toast in toasts" :key="toast.id" class="backup-toast">
          <span class="grid h-10 w-10 place-items-center rounded-2xl text-xl" :class="toneClass(toast.type)">
            {{ icon(toast.type) }}
          </span>
          <div>
            <h4 class="backup-toast__title">{{ toast.title }}</h4>
            <p v-if="toast.message" class="backup-toast__text">{{ toast.message }}</p>
          </div>
          <button type="button" class="backup-btn backup-btn--ghost backup-btn--sm" @click="$emit('dismiss', toast.id)">✕</button>
        </article>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  toasts: { type: Array, default: () => [] },
})

defineEmits(['dismiss'])

function icon(type) {
  return {
    success: '✓',
    error: '⚠',
    warning: '!',
    info: 'i',
  }[type] || '♛'
}

function toneClass(type) {
  return {
    success: 'bg-[var(--gs-success-soft)] text-[var(--gs-success)]',
    error: 'bg-[var(--gs-error-soft)] text-[var(--gs-error)]',
    warning: 'bg-[var(--gs-warning-soft)] text-[var(--gs-warning)]',
    info: 'bg-[var(--gs-info-soft)] text-[var(--gs-info)]',
  }[type] || 'bg-[var(--gs-gold-muted)] text-[var(--gs-gold)]'
}
</script>
