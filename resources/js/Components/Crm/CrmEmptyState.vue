<script setup>
/**
 * CrmEmptyState — حالت خالی با آیکون شناور و اکشن‌ها
 * مسیر: resources/js/Components/Crm/CrmEmptyState.vue
 *
 * استفاده:
 *   <CrmEmptyState :icon="Users" title="مشتری‌ای یافت نشد" desc="…">
 *     <button class="a3d-btn a3d-btn--gold">ثبت مشتری</button>
 *   </CrmEmptyState>
 */
defineProps({
    icon: { type: [Object, Function], required: true },
    title: { type: String, required: true },
    desc: { type: String, default: '' },
})
</script>

<template>
    <div class="crm-empty">
        <span class="crm-empty__icon"><component :is="icon" :size="32" /></span>
        <p class="crm-empty__title">{{ title }}</p>
        <p v-if="desc" class="crm-empty__desc">{{ desc }}</p>
        <div v-if="$slots.default" class="crm-empty__actions">
            <slot />
        </div>
    </div>
</template>
