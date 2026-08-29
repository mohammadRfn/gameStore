<script setup>

const props = defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'default' }, // default | gold | emerald | rose | info
  fill: { type: Boolean, default: false },
  delay: { type: Number, default: 0 },        // تاخیر انیمیشن ورود (backup-reveal)
})

const toneVar = {
  default: 'var(--gs-border)',
  gold: 'var(--gs-gold)',
  emerald: 'var(--gs-success)',
  rose: 'var(--gs-error)',
  info: 'var(--gs-info)',
}

const styleVars = () => ({
  '--delay': props.delay + 'ms',
  borderColor: 'color-mix(in srgb, ' + (toneVar[props.tone] || toneVar.default) + ' 34%, transparent)',
  height: props.fill ? '100%' : 'auto',
})
</script>

<template>
  <section class="backup-glass backup-section backup-reveal" :style="styleVars()">
    <header v-if="title || $slots.actions" class="backup-section__head">
      <div>
        <h3 class="backup-section__title">
          <span v-if="icon" aria-hidden="true">{{ icon }}</span>
          {{ title }}
        </h3>
        <p v-if="description" class="backup-section__desc">{{ description }}</p>
      </div>

      <div v-if="$slots.actions" class="backup-head-badges">
        <slot name="actions" />
      </div>
    </header>

    <div class="backup-grid">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="backup-section__head">
      <slot name="footer" />
    </footer>
  </section>
</template>
