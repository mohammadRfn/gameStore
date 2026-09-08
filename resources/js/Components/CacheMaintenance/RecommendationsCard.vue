<script setup>
/**
 * RecommendationsCard — پیشنهادهای هوشمند نگهداری کش
 * مسیر: resources/js/Components/CacheMaintenance/RecommendationsCard.vue
 *
 * ورودی دقیقاً recommendations از GET /settings/cache/overview است.
 */
defineProps({
    items: { type: Array, default: () => [] },
})

function isOk(item) {
    return /طبیعی|سالم/.test(item)
}
</script>

<template>
    <div class="cm-reco">
        <div
            v-for="(item, i) in items"
            :key="i"
            class="cm-reco__item"
            :class="{ 'cm-reco__item--ok': isOk(item) }"
        >
            <span class="cm-reco__dot" />
            <span>{{ item }}</span>
        </div>
    </div>
</template>
