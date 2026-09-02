<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { faNumber, formatBytes } from '@/Composables/useBackupCenter'

/**
 * کارت آمار — نسخه‌ی هماهنگ با تم (dark/light).
 * تغییر مهم: تمام رنگ‌های ثابت Tailwind (slate/amber/white...) حذف و با
 * توکن‌های --gs-* پروژه جایگزین شد؛ حالا در تم روشن هم کاملاً درست دیده می‌شود.
 * منطق و props دقیقاً مثل قبل است.
 */
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  hint: { type: String, default: '' },
  icon: { type: String, default: '◇' },
  tone: { type: String, default: 'gold' }, // gold | sky | rose | emerald
  bytes: { type: Boolean, default: false },
  delay: { type: Number, default: 0 },
})

const shown = ref(0)
const visible = ref(false)

/* هر تون به یک متغیر رنگی --gs-* نگاشت می‌شود */
const toneColor = {
  gold: 'var(--gs-gold)',
  sky: 'var(--gs-info)',
  rose: 'var(--gs-error)',
  emerald: 'var(--gs-success)',
}

const cardStyle = computed(() => {
  const c = toneColor[props.tone] || toneColor.gold
  return {
    '--tone': c,
    borderColor: `color-mix(in srgb, ${c} 30%, transparent)`,
    background: `linear-gradient(155deg, color-mix(in srgb, ${c} 10%, var(--gs-bg-card)) 0%, var(--gs-bg-card) 70%)`,
    boxShadow: 'var(--gs-shadow-sm)',
  }
})

const display = computed(() => (props.bytes ? formatBytes(shown.value) : faNumber(shown.value)))

function animate() {
  const target = Number(props.value) || 0
  const start = performance.now()
  const from = shown.value
  const duration = 700

  const step = (now) => {
    const progress = Math.min((now - start) / duration, 1)
    shown.value = Math.round(from + (target - from) * (1 - Math.pow(1 - progress, 3)))
    if (progress < 1) requestAnimationFrame(step)
  }

  requestAnimationFrame(step)
}

onMounted(() => {
  window.setTimeout(() => {
    visible.value = true
    animate()
  }, props.delay)
})

watch(() => props.value, () => visible.value && animate())
</script>

<template>
  <article
    class="bk-stat"
    :class="visible ? 'is-in' : ''"
    :style="cardStyle"
  >
    <div class="bk-stat__top">
      <p class="bk-stat__label">{{ label }}</p>
      <span class="bk-stat__icon" aria-hidden="true">{{ icon }}</span>
    </div>

    <div>
      <p class="bk-stat__value">{{ display }}</p>
      <p v-if="hint" class="bk-stat__hint">{{ hint }}</p>
    </div>
  </article>
</template>

<style scoped>
.bk-stat {
  display: flex;
  height: 100%;
  flex-direction: column;
  justify-content: space-between;
  gap: 1rem;
  border-radius: 1rem;
  border: 1px solid var(--gs-border);
  padding: 1.25rem;
  opacity: 0;
  transform: translateY(8px);
  transition: opacity .5s ease, transform .5s ease, border-color .3s ease;
}

.bk-stat.is-in {
  opacity: 1;
  transform: translateY(0);
}

.bk-stat__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .75rem;
}

.bk-stat__label {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.25rem;
  color: var(--gs-text-secondary);
}

.bk-stat__icon {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: .75rem;
  font-size: 1rem;
  line-height: 1;
  color: var(--tone);
  background: color-mix(in srgb, var(--tone) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--tone) 22%, transparent);
}

.bk-stat__value {
  font-size: 1.5rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
  color: var(--gs-text-primary);
}

.bk-stat__hint {
  margin-top: .25rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: .75rem;
  line-height: 1.25rem;
  color: var(--gs-text-muted);
}
</style>
