<template>
  <article class="backup-glass backup-section backup-reveal" :style="cardStyle">
    <div class="relative z-[1] flex items-start justify-between gap-3">
      <div>
        <p class="text-[0.72rem] font-extrabold text-[var(--gs-text-muted)]">{{ label }}</p>
        <div class="mt-2 flex items-end gap-2">
          <strong class="text-2xl font-black text-[var(--gs-text-primary)] tabular-nums">
            {{ displayValue }}
          </strong>
          <span v-if="suffix" class="pb-1 text-[0.72rem] font-bold text-[var(--gs-text-muted)]">{{ suffix }}</span>
        </div>
        <p v-if="hint" class="mt-1 text-[0.73rem] leading-7 text-[var(--gs-text-secondary)]">{{ hint }}</p>
      </div>

      <div class="grid h-12 w-12 place-items-center rounded-2xl border border-[var(--gs-border)] bg-[var(--gs-gold-muted)] text-2xl shadow-[var(--gs-shadow-sm)]">
        {{ icon }}
      </div>
    </div>

    <div class="relative z-[1] mt-3 flex items-end justify-between gap-3">
      <div class="backup-mini-chart flex-1">
        <span
          v-for="(bar, index) in bars"
          :key="index"
          :style="{ height: `${bar}%`, animationDelay: `${index * 45}ms` }"
        ></span>
      </div>
      <span v-if="badge" class="backup-pill" :class="badgeClass">{{ badge }}</span>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { faNumber, formatBytes } from '@/Composables/useBackupApi'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: '◇' },
  suffix: { type: String, default: '' },
  hint: { type: String, default: '' },
  badge: { type: String, default: '' },
  tone: { type: String, default: 'gold' },
  delay: { type: Number, default: 0 },
  bytes: { type: Boolean, default: false },
})

const displayValue = computed(() => props.bytes ? formatBytes(props.value) : faNumber(props.value))

const palette = computed(() => ({
  gold: 'var(--gs-gold)',
  blue: 'var(--gs-accent)',
  green: 'var(--gs-accent-2)',
  purple: 'var(--gs-accent-3)',
  red: 'var(--gs-error)',
}[props.tone] || 'var(--gs-gold)'))

const cardStyle = computed(() => ({
  '--delay': `${props.delay}ms`,
  '--stat-color': palette.value,
}))

const bars = computed(() => {
  const seed = Math.max(1, Number(props.value) || 1)
  return Array.from({ length: 9 }, (_, i) => 18 + ((seed * (i + 3) * 17) % 72))
})

const badgeClass = computed(() => ({
  green: 'backup-pill--ok',
  blue: 'backup-pill--info',
  red: 'backup-pill--danger',
  gold: '',
  purple: 'backup-pill--info',
}[props.tone] || ''))
</script>

<style scoped>
article::before {
  content: '';
  position: absolute;
  inset: auto 12px 12px auto;
  width: 86px;
  height: 86px;
  border-radius: 999px;
  background: radial-gradient(circle, color-mix(in srgb, var(--stat-color) 22%, transparent), transparent 70%);
  filter: blur(4px);
  pointer-events: none;
}
</style>
