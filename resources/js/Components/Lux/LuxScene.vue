<script setup>
/**
 * LuxScene — پس‌زمینهٔ محیطی سه‌بعدی ماژول‌های عملیاتی (انبار/سرویس/فروش/گردش انبار)
 * مسیر: resources/js/Components/Lux/LuxScene.vue
 *
 * از کلاس‌های a3d-* موجود در archive-3d.css استفاده می‌کند:
 * a3d-scene / a3d-grid-floor / a3d-orb / a3d-dust
 *
 * پراپ accent رنگ هالهٔ دوم را تعیین می‌کند: 'blue' | 'violet' | 'gold'
 */
const props = defineProps({
    accent: { type: String, default: 'blue' },
})

const accentClass = {
    blue: 'a3d-orb--blue',
    violet: 'a3d-orb--violet',
    gold: 'a3d-orb--gold',
}[props.accent] ?? 'a3d-orb--blue'

const dust = Array.from({ length: 20 }, () => ({
    left: `${Math.round(Math.random() * 100)}%`,
    bottom: `${Math.round(Math.random() * 40)}%`,
    size: `${(2 + Math.random() * 2).toFixed(1)}px`,
    duration: `${(11 + Math.random() * 12).toFixed(1)}s`,
    delay: `${(Math.random() * 14).toFixed(1)}s`,
}))
</script>

<template>
    <div class="a3d-scene lux-scene" aria-hidden="true">
        <!-- کف مشبک پرسپکتیو -->
        <span class="a3d-grid-floor" />

        <!-- هاله‌های نورانی شناور -->
        <span
            class="a3d-orb a3d-orb--gold a3d-float-a"
            style="width: 440px; height: 440px; top: -160px; inset-inline-end: 5%"
        />
        <span
            :class="['a3d-orb', accentClass, 'a3d-float-b']"
            style="width: 380px; height: 380px; bottom: -130px; inset-inline-start: 2%"
        />
        <span
            class="a3d-orb a3d-orb--violet a3d-float-a"
            style="width: 300px; height: 300px; top: 45%; inset-inline-start: 48%; opacity: 0.18"
        />

        <!-- ذرات معلق -->
        <span
            v-for="(d, i) in dust"
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
.lux-scene {
    position: fixed;
    inset: 0;
    z-index: 0;
}
</style>
