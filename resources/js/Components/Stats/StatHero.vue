<template>
    <header class="hero">
        <ThreeBackground :density="150" />

        <div class="hero-inner">
            <div class="hero-meta">
                <span class="hero-period">
                    <CalendarRange class="ic" :size="15" />
                    {{ rangeLabel(props.range) }}
                    <span v-if="from && to" class="hero-dates">· {{ jalali(from) }} تا {{ jalali(to) }}</span>
                </span>
                <span class="hero-live">
                    <span class="gs-live-dot"></span>
                    به‌روز
                </span>
            </div>

            <h1 class="hero-title">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p class="hero-subtitle">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>

            <div v-if="$slots.actions" class="hero-actions">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>

<script setup>
import { CalendarRange } from '@lucide/vue'
import ThreeBackground from './ThreeBackground.vue'
import { jalali, rangeLabel } from '@/Utils/format'

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    range: { type: String, default: 'month' },
})
</script>

<style scoped>
.hero {
    position: relative;
    overflow: hidden;
    border-radius: var(--gs-radius-lg);
    border: 1px solid var(--gs-border);
    background:
        linear-gradient(160deg, var(--gs-gold-muted) 0%, transparent 40%),
        var(--gs-bg-card);
    box-shadow: var(--gs-shadow-md);
    padding: 2rem 2rem 1.75rem;
    margin-bottom: 1.25rem;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    isolation: isolate;
}

.hero-inner {
    position: relative;
    z-index: 1;
    max-width: 980px;
}

.hero-meta {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.hero-period {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.32rem 0.8rem;
    border-radius: 999px;
    background: var(--gs-glass);
    border: 1px solid var(--gs-border);
    color: var(--gs-gold);
    font-size: 0.78rem;
    font-weight: 600;
}
.hero-period .ic { opacity: 0.9; }

.hero-dates { color: var(--gs-text-secondary); font-weight: 500; }

.hero-live {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    color: var(--gs-text-secondary);
}

.hero-title {
    font-size: clamp(1.6rem, 3.6vw, 2.6rem);
    font-weight: 900;
    line-height: 1.25;
    letter-spacing: -0.02em;
    color: var(--gs-text-primary);
    margin-bottom: 0.55rem;
    text-shadow: 0 2px 24px var(--gs-gold-glow);
}
.hero-title :deep(.accent) {
    background: var(--gs-gold-grad);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.hero-subtitle {
    font-size: 0.95rem;
    color: var(--gs-text-secondary);
    max-width: 640px;
    margin-bottom: 1.1rem;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
</style>
