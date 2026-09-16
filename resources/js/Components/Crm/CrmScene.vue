<script setup>
/**
 * CrmScene — پس‌زمینهٔ محیطی سه‌بعدی (همان صحنهٔ تنظیمات با تُن قابل انتخاب)
 * مسیر: resources/js/Components/Crm/CrmScene.vue
 *
 * از کلاس‌های a3d-* موجود در archive-3d.css استفاده می‌کند:
 * a3d-scene / a3d-grid-floor / a3d-orb / a3d-dust
 *
 * استفاده:  <CrmScene tone="blue" />   ← tone: gold | blue | violet | green
 */
const props = defineProps({
    tone: { type: String, default: 'gold' },
    dust: { type: Number, default: 20 },
})

const SECOND = {
    gold: 'a3d-orb--blue',
    blue: 'a3d-orb--blue',
    violet: 'a3d-orb--violet',
    green: 'crm-orb--green',
}

const particles = Array.from({ length: props.dust }, () => ({
    left: `${Math.round(Math.random() * 100)}%`,
    bottom: `${Math.round(Math.random() * 40)}%`,
    size: `${(2 + Math.random() * 2).toFixed(1)}px`,
    duration: `${(11 + Math.random() * 12).toFixed(1)}s`,
    delay: `${(Math.random() * 14).toFixed(1)}s`,
}))
</script>

<template>
    <div class="a3d-scene crm-scene" aria-hidden="true">
        <span class="a3d-grid-floor" />

        <span
            class="a3d-orb a3d-orb--gold a3d-float-a"
            style="width: 460px; height: 460px; top: -170px; inset-inline-end: 6%"
        />
        <span
            class="a3d-orb a3d-float-b"
            :class="SECOND[tone] || SECOND.gold"
            style="width: 400px; height: 400px; bottom: -140px; inset-inline-start: 2%"
        />
        <span
            class="a3d-orb a3d-orb--violet a3d-float-a"
            style="width: 320px; height: 320px; top: 42%; inset-inline-start: 46%; opacity: 0.18"
        />

        <span
            v-for="(d, i) in particles"
            :key="i"
            class="a3d-dust"
            :style="{
                left: d.left,
                bottom: d.bottom,
                width: d.size,
                height: d.size,
                animationDuration: d.duration,
                animationDelay: d.delay,
            }"
        />
    </div>
</template>

<style scoped>
.crm-scene {
    position: fixed;
    inset: 0;
    z-index: 0;
}
</style>
