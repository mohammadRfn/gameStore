<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { faNumber, formatBytes } from '@/Composables/useBackupCenter'

/**
 * کارت آمار.
 * تغییرات: برچسب‌های انگلیسی (Audit / Schema / Health) حذف شدند،
 * همه‌ی کارت‌ها هم‌ارتفاع و با فاصله‌ی یکسان هستند و متن‌ها سرریز نمی‌کنند.
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

const tones = {
  gold: 'from-amber-400/15 text-amber-300 ring-amber-400/20',
  sky: 'from-sky-400/15 text-sky-300 ring-sky-400/20',
  rose: 'from-rose-400/15 text-rose-300 ring-rose-400/20',
  emerald: 'from-emerald-400/15 text-emerald-300 ring-emerald-400/20',
}

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
    class="flex h-full flex-col justify-between gap-4 rounded-2xl bg-gradient-to-bl to-transparent p-5 ring-1 transition-all duration-500"
    :class="[tones[tone] || tones.gold, visible ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0']"
  >
    <div class="flex items-start justify-between gap-3">
      <p class="text-[13px] font-semibold leading-5 text-slate-300">{{ label }}</p>
      <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white/5 text-base leading-none" aria-hidden="true">
        {{ icon }}
      </span>
    </div>

    <div>
      <p class="text-2xl font-black tabular-nums tracking-tight text-slate-50">{{ display }}</p>
      <p v-if="hint" class="mt-1 line-clamp-2 text-xs leading-5 text-slate-400">{{ hint }}</p>
    </div>
  </article>
</template>
