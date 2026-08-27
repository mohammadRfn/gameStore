<script setup>
/**
 * پوسته‌ی مشترک همه‌ی کادرها.
 * دلیل وجودش: قبلاً هر کادر padding و فاصله‌ی متفاوتی داشت و کارت‌ها به هم می‌چسبیدند.
 * از این به بعد همه‌ی کادرها دقیقاً یک ریتم فاصله دارند و ارتفاعشان اندازه‌ی محتواست.
 */
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'default' }, // default | gold | emerald | rose
  fill: { type: Boolean, default: false },    // فقط وقتی می‌خواهیم کارت هم‌ارتفاع ستون شود
})

const toneRing = {
  default: 'ring-white/10',
  gold: 'ring-amber-400/25',
  emerald: 'ring-emerald-400/25',
  rose: 'ring-rose-400/25',
}

const toneIcon = {
  default: 'bg-white/5 text-slate-200',
  gold: 'bg-amber-400/10 text-amber-300',
  emerald: 'bg-emerald-400/10 text-emerald-300',
  rose: 'bg-rose-400/10 text-rose-300',
}
</script>

<template>
  <section
    class="flex flex-col rounded-2xl bg-slate-900/60 ring-1 backdrop-blur-sm transition-colors duration-300"
    :class="[toneRing[tone] || toneRing.default, fill ? 'h-full' : 'self-start']"
  >
    <header
      v-if="title || $slots.actions"
      class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4"
    >
      <div class="flex min-w-0 items-center gap-3">
        <span
          v-if="icon"
          class="grid size-9 shrink-0 place-items-center rounded-xl text-base leading-none"
          :class="toneIcon[tone] || toneIcon.default"
          aria-hidden="true"
        >{{ icon }}</span>

        <div class="min-w-0">
          <h3 class="truncate text-sm font-bold text-slate-100">{{ title }}</h3>
          <p v-if="description" class="mt-0.5 line-clamp-2 text-xs leading-5 text-slate-400">
            {{ description }}
          </p>
        </div>
      </div>

      <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
        <slot name="actions" />
      </div>
    </header>

    <div class="flex flex-1 flex-col gap-4 p-5">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="border-t border-white/5 px-5 py-4">
      <slot name="footer" />
    </footer>
  </section>
</template>
