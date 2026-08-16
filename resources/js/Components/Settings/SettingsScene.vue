<script setup>
/**
 * SettingsScene — پس‌زمینهٔ محیطی سه‌بعدی صفحهٔ تنظیمات
 * مسیر: resources/js/Components/Settings/SettingsScene.vue
 *
 * از کلاس‌های a3d-* موجود در archive-3d.css استفاده می‌کند:
 * a3d-scene / a3d-grid-floor / a3d-orb / a3d-dust
 */
const dust = Array.from({ length: 22 }, () => ({
    left: `${Math.round(Math.random() * 100)}%`,
    bottom: `${Math.round(Math.random() * 40)}%`,
    size: `${(2 + Math.random() * 2).toFixed(1)}px`,
    duration: `${(11 + Math.random() * 12).toFixed(1)}s`,
    delay: `${(Math.random() * 14).toFixed(1)}s`,
}))
</script>

<template>
    <div class="a3d-scene st-scene" aria-hidden="true">
        <!-- کف مشبک پرسپکتیو -->
        <span class="a3d-grid-floor" />

        <!-- هاله‌های نورانی شناور -->
        <span
            class="a3d-orb a3d-orb--gold a3d-float-a"
            style="width: 460px; height: 460px; top: -170px; inset-inline-end: 6%"
        />
        <span
            class="a3d-orb a3d-orb--blue a3d-float-b"
            style="width: 400px; height: 400px; bottom: -140px; inset-inline-start: 2%"
        />
        <span
            class="a3d-orb a3d-orb--violet a3d-float-a"
            style="
                width: 320px;
                height: 320px;
                top: 42%;
                inset-inline-start: 46%;
                opacity: 0.2;
            "
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
.st-scene {
    position: fixed;
    inset: 0;
    z-index: 0;
}
</style>
