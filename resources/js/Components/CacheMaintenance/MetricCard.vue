<script setup>
/**
 * MetricCard — کارت یک متریک کش
 * مسیر: resources/js/Components/CacheMaintenance/MetricCard.vue
 *
 * استفاده:
 *   <MetricCard icon="🗂" label="Bootstrap Cache" value="۱۲" sub="1.2 MB" accent="var(--gs-gold)" />
 */
defineProps({
    /** آیکون (ایموجی یا نویسه) */
    icon: { type: String, default: '📊' },
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    /** متن کمکی تک‌خطی (مثلاً مسیر یا اندازه) */
    sub: { type: String, default: '' },
    /** رنگ هالهٔ کارت */
    accent: { type: String, default: 'var(--gs-gold-glow)' },
})
</script>

<template>
    <div
        class="cm-metric a3d-tilt"
        v-tilt="{ max: 6, scale: 1.02, lift: 10 }"
        :style="{ '--cm-accent': accent }"
    >
        <div class="cm-metric__icon">{{ icon }}</div>
        <p class="cm-metric__label">{{ label }}</p>
        <p class="cm-metric__value">{{ value }}</p>
        <p v-if="sub" class="cm-metric__sub" :title="sub">{{ sub }}</p>
    </div>
</template>
