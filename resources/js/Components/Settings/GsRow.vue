<script setup>
/**
 * GsRow — ردیف استاندارد یک تنظیم (عنوان + توضیح + کنترل)
 * مسیر: resources/js/Components/Settings/GsRow.vue
 */
defineProps({
    title: { type: String, required: true },
    desc: { type: String, default: '' },
    /** وقتی true شود کنترل کم‌رنگ و غیرفعال می‌شود */
    disabled: { type: Boolean, default: false },
})
</script>

<template>
    <div class="st-row" :class="{ 'st-row--disabled': disabled }">
        <div>
            <p class="st-row__title">{{ title }}</p>
            <p v-if="desc" class="st-row__desc">{{ desc }}</p>
        </div>

        <div class="st-row__control">
            <slot />
        </div>
    </div>
</template>
