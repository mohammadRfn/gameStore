<script setup>
/**
 * CrmPanel — کارت هولوگرافیک با سربرگ (آیکون + عنوان + توضیح + اکشن‌ها)
 * مسیر: resources/js/Components/Crm/CrmPanel.vue
 *
 * استفاده:
 *   <CrmPanel title="آخرین درخواست‌ها" desc="…" :icon="ClipboardList" accent="var(--gs-gold)" flush>
 *     <template #actions><Link class="crm-more" …>مشاهدهٔ همه</Link></template>
 *     …محتوا…
 *   </CrmPanel>
 */
import { vReveal, vTilt } from '@/Composables/useTilt'

defineProps({
    title: { type: String, default: '' },
    desc: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    accent: { type: String, default: 'var(--gs-gold)' },
    /** بدنه بدون padding (برای جدول/ردیف‌ها) */
    flush: { type: Boolean, default: false },
    /** غیرفعال‌کردن تیلت (برای کارت‌های بلند/فرم) */
    still: { type: Boolean, default: false },
    delay: { type: Number, default: 0 },
})
</script>

<template>
    <section
        v-reveal="{ delay }"
        v-tilt="{ max: still ? 0 : 2.5, lift: still ? 0 : 6, scale: still ? 1 : 1.003, glare: !still }"
        class="a3d-holo crm-panel"
        :style="{ '--crm-accent': accent }"
    >
        <header v-if="title || $slots.actions" class="crm-panel__head">
            <div class="crm-panel__title">
                <span v-if="icon" class="crm-panel__icon"><component :is="icon" :size="18" /></span>
                <div style="min-width: 0">
                    <p class="crm-panel__t">{{ title }}</p>
                    <p v-if="desc" class="crm-panel__d">{{ desc }}</p>
                </div>
            </div>
            <div v-if="$slots.actions" class="crm-panel__actions">
                <slot name="actions" />
            </div>
        </header>

        <div class="crm-panel__body" :class="{ 'crm-panel__body--flush': flush }">
            <slot />
        </div>
    </section>
</template>
