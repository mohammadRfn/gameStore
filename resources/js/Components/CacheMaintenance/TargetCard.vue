<script setup>
/**
 * TargetCard — کارت انتخاب یک target پاکسازی
 * مسیر: resources/js/Components/CacheMaintenance/TargetCard.vue
 *
 * استفاده:
 *   <TargetCard
 *     :key="t.key"
 *     :title="t.label"
 *     :desc="t.description"
 *     :safe="t.safe"
 *     icon="🗂"
 *     :selected="selectedTargets.includes(t.key)"
 *     @toggle="toggleTarget(t.key)"
 *   />
 */
defineProps({
    title: { type: String, required: true },
    desc: { type: String, default: '' },
    /** true یعنی حذف امن و بدون ریسک */
    safe: { type: Boolean, default: true },
    icon: { type: String, default: '🗂' },
    selected: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle'])
</script>

<template>
    <div
        class="cm-target"
        :class="{ 'is-on': selected }"
        role="checkbox"
        :aria-checked="selected"
        tabindex="0"
        @click="emit('toggle')"
        @keydown.enter.prevent="emit('toggle')"
        @keydown.space.prevent="emit('toggle')"
    >
        <span class="cm-target__check">✓</span>

        <span class="cm-target__icon">{{ icon }}</span>

        <div>
            <p class="cm-target__title">
                {{ title }}
                <span
                    class="cm-badge"
                    :class="safe ? 'cm-badge--safe' : 'cm-badge--unsafe'"
                >
                    {{ safe ? 'ایمن' : 'با احتیاط' }}
                </span>
            </p>
            <p v-if="desc" class="cm-target__desc">{{ desc }}</p>
        </div>
    </div>
</template>
