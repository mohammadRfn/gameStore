<script setup>
/**
 * GsSectionHead — سربرگ هر بخش تنظیمات
 * مسیر: resources/js/Components/Settings/GsSectionHead.vue
 */
defineProps({
    /** کامپوننت آیکون lucide */
    icon: { type: [Object, Function], required: true },
    title: { type: String, required: true },
    desc: { type: String, default: '' },
})
</script>

<template>
    <div class="st-sechead">
        <span class="st-sechead__icon">
            <component :is="icon" :size="21" />
        </span>

        <div>
            <h2 class="st-sechead__title">{{ title }}</h2>
            <p v-if="desc" class="st-sechead__desc">{{ desc }}</p>
        </div>
    </div>
</template>
