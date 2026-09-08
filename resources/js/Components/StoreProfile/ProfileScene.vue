<script setup>
/**
 * ProfileScene — پس‌زمینهٔ سینمایی صفحهٔ پروفایل فروشگاه
 * مسیر: resources/js/Components/StoreProfile/ProfileScene.vue
 *
 * کف مشبک + دو اورب شناور (بدون وابستگی خارجی).
 */
</script>

<template>
    <div class="sp-scene" aria-hidden="true">
        <div class="a3d-grid-floor" />

        <span
            class="sp-scene__orb sp-scene__orb--gold sp-scene__float-a"
            style="width: 360px; height: 360px; top: -110px; inset-inline-start: -70px"
        />
        <span
            class="sp-scene__orb sp-scene__orb--blue sp-scene__float-b"
            style="width: 300px; height: 300px; bottom: -90px; inset-inline-end: -50px"
        />
    </div>
</template>
