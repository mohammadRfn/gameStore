<script setup>
/**
 * انیمیشن هدر.
 * تغییر مهم: نام موتور دیتابیس (SQLite Core) و برچسب‌های انگلیسی حذف شدند؛
 * حالا فقط سه برچسب فارسی و قابل‌فهم برای کاربر نمایش داده می‌شود.
 * اندازه‌ی صحنه ثابت و متناسب با کارت است تا با کارت کناری تداخل نکند.
 */
const chips = ['بسته کامل', 'تصاویر امن', 'بازیابی سریع']

const sparks = Array.from({ length: 12 }, (_, index) => ({
  id: index,
  x: 10 + Math.random() * 80,
  y: 10 + Math.random() * 78,
  delay: Math.random() * 2.6,
  scale: 0.7 + Math.random() * 0.7,
}))

function sparkStyle(spark) {
  return {
    insetInlineEnd: spark.x + '%',
    top: spark.y + '%',
    transform: 'scale(' + spark.scale + ')',
    animationDelay: spark.delay + 's',
  }
}
</script>

<template>
  <div
    class="relative mx-auto grid aspect-square w-full max-w-[260px] place-items-center overflow-hidden rounded-2xl bg-slate-950/40 ring-1 ring-white/10"
    aria-hidden="true"
  >
    <span
      v-for="spark in sparks"
      :key="spark.id"
      class="orb-spark absolute size-1 rounded-full bg-amber-200/70"
      :style="sparkStyle(spark)"
    />

    <span class="orb-ring absolute size-[86%] rounded-full border border-amber-300/20" />
    <span class="orb-ring orb-ring--slow absolute size-[64%] rounded-full border border-amber-300/25" />
    <span class="orb-ring orb-ring--rev absolute size-[42%] rounded-full border border-emerald-300/25" />

    <div class="orb-core grid size-20 place-items-center rounded-full bg-gradient-to-b from-amber-300/90 to-amber-500/70 text-2xl text-slate-900 shadow-[0_0_40px_-6px_rgba(251,191,36,0.7)]">
      ♛
    </div>

    <div class="absolute inset-x-0 bottom-3 flex flex-wrap items-center justify-center gap-1.5 px-3">
      <span
        v-for="chip in chips"
        :key="chip"
        class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] font-medium leading-4 text-slate-300 ring-1 ring-white/10"
      >{{ chip }}</span>
    </div>
  </div>
</template>

<style scoped>
.orb-ring { animation: orb-spin 14s linear infinite; }
.orb-ring--slow { animation-duration: 22s; }
.orb-ring--rev { animation-direction: reverse; animation-duration: 18s; }
.orb-core { animation: orb-pulse 3.4s ease-in-out infinite; }
.orb-spark { animation: orb-twinkle 2.8s ease-in-out infinite; }

@keyframes orb-spin {
  from { transform: rotate(0deg) scale(1); }
  50% { transform: rotate(180deg) scale(1.03); }
  to { transform: rotate(360deg) scale(1); }
}

@keyframes orb-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.06); }
}

@keyframes orb-twinkle {
  0%, 100% { opacity: 0.15; }
  50% { opacity: 0.9; }
}

@media (prefers-reduced-motion: reduce) {
  .orb-ring, .orb-core, .orb-spark { animation: none; }
}
</style>
