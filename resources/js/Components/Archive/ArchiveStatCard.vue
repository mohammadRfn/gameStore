<!-- ==========================================================================
     GameStore · ArchiveStatCard
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveStatCard.vue

     کارت آمار سه‌بعدی با:
       • چرخش تیلت وابسته به ماوس (v-tilt)
       • لایه‌بندی عمق واقعی (آیکون و عدد روی محور Z جلو می‌آیند)
       • شمارندهٔ انیمیشنی با easing
       • قاب هولوگرافیک چرخان هنگام هاور
     ========================================================================== -->
<template>
  <article
    v-tilt="{ max: 11, scale: 1.02, lift: 16 }"
    v-reveal="{ delay }"
    class="a3d-holo a3d-aura stat-card"
    :style="{ '--a3d-aura-color': aura, '--accent': accent }"
  >
    <div class="a3d-layer stat-inner">
      <!-- آیکون شناور -->
      <div class="stat-icon a3d-z-60">
        <span>{{ icon }}</span>
      </div>

      <!-- برچسب -->
      <p class="stat-label a3d-z-20">{{ label }}</p>

      <!-- عدد -->
      <p class="stat-value a3d-z-45">
        <span>{{ displayValue }}</span>
        <small v-if="suffix" class="stat-suffix">{{ suffix }}</small>
      </p>

      <!-- نوار پیشرفت تزئینی -->
      <div class="stat-bar a3d-z-10">
        <span :style="{ width: barWidth }"></span>
      </div>
    </div>

    <!-- بازتاب کف کارت -->
    <div class="stat-reflection"></div>
  </article>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { vTilt, vReveal } from '@/Composables/useTilt'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: '◈' },
  suffix: { type: String, default: '' },
  accent: { type: String, default: 'var(--gs-gold)' },
  aura: { type: String, default: 'var(--gs-gold-glow)' },
  delay: { type: Number, default: 0 },
  ratio: { type: Number, default: 0.7 },
  animate: { type: Boolean, default: true },
})

const displayValue = ref('0')
const barWidth = ref('0%')

const formatter = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 })

function runCountUp(target) {
  const numeric = Number(target)

  // اگر مقدار عددی نیست، مستقیم نمایش بده
  if (Number.isNaN(numeric)) {
    displayValue.value = String(target ?? '—')
    return
  }

  const reduced = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

  if (!props.animate || reduced || numeric === 0) {
    displayValue.value = formatter.format(numeric)
    return
  }

  const duration = 900
  const startedAt = performance.now()

  const step = (now) => {
    const progress = Math.min((now - startedAt) / duration, 1)
    // easeOutExpo برای حس پرمیوم
    const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress)
    displayValue.value = formatter.format(Math.round(numeric * eased))
    if (progress < 1) requestAnimationFrame(step)
  }

  requestAnimationFrame(step)
}

onMounted(() => {
  runCountUp(props.value)
  // تاخیر کوتاه تا نوار بعد از ورود کارت پر شود
  setTimeout(() => {
    barWidth.value = `${Math.min(Math.max(props.ratio, 0), 1) * 100}%`
  }, props.delay + 220)
})

watch(() => props.value, (next) => runCountUp(next))
</script>

<style scoped>
.stat-card {
  padding: 1.15rem 1.25rem 1.35rem;
  min-height: 138px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.stat-inner {
  position: relative;
  z-index: 4;
  transform-style: preserve-3d;
}

.stat-icon {
  position: absolute;
  top: -0.35rem;
  left: 0;
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  font-size: 1.2rem;
  border-radius: 13px;
  background: color-mix(in srgb, var(--accent) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
  box-shadow: 0 8px 22px color-mix(in srgb, var(--accent) 22%, transparent);
}

.stat-label {
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--gs-text-muted);
  letter-spacing: 0.05em;
  margin-bottom: 0.3rem;
}

.stat-value {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
  font-size: 1.72rem;
  font-weight: 900;
  line-height: 1.15;
  color: var(--accent);
  font-variant-numeric: tabular-nums;
  text-shadow: 0 6px 24px color-mix(in srgb, var(--accent) 34%, transparent);
}

.stat-suffix {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--gs-text-muted);
}

.stat-bar {
  margin-top: 0.85rem;
  height: 4px;
  border-radius: 99px;
  background: color-mix(in srgb, var(--gs-text-muted) 22%, transparent);
  overflow: hidden;
}

.stat-bar span {
  display: block;
  height: 100%;
  width: 0;
  border-radius: inherit;
  background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 45%, transparent), var(--accent));
  transition: width 1.1s cubic-bezier(0.22, 1, 0.36, 1);
  box-shadow: 0 0 14px color-mix(in srgb, var(--accent) 55%, transparent);
}

.stat-reflection {
  position: absolute;
  inset: auto 14% -1px 14%;
  height: 1px;
  background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent) 60%, transparent), transparent);
  opacity: 0.65;
}
</style>
