<script setup>
/**
 * CacheScene — پس‌زمینهٔ سینمایی صفحهٔ نگهداری کش
 * مسیر: resources/js/Components/CacheMaintenance/CacheScene.vue
 *
 * کف مشبک متحرک + سه اورب رنگی شناور (بدون هیچ وابستگی خارجی).
 * کلاس‌های a3d-grid-floor از archive-3d.css و cm-scene__* از
 * cache-maintenance-3d.css می‌آیند.
 */
</script>

<template>
    <div class="cm-scene" aria-hidden="true">
        <div class="a3d-grid-floor" />

        <span
            class="cm-scene__orb cm-scene__orb--gold cm-scene__float-a"
            style="width: 380px; height: 380px; top: -120px; inset-inline-start: -80px"
        />
        <span
            class="cm-scene__orb cm-scene__orb--blue cm-scene__float-b"
            style="width: 300px; height: 300px; bottom: -100px; inset-inline-end: -60px"
        />
        <span
            class="cm-scene__orb cm-scene__orb--violet cm-scene__float-c"
            style="width: 220px; height: 220px; top: 32%; inset-inline-end: 12%"
        />
    </div>
</template>
