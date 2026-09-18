<script setup>
/**
 * LuxHero — سربرگ سینمایی صفحات عملیاتی (هم‌خانوادهٔ هیروی صفحهٔ تنظیمات)
 * مسیر: resources/js/Components/Lux/LuxHero.vue
 *
 * props:
 *   chip     برچسب ماژول (مثلاً «ماژول انبار»)
 *   chipTwo  برچسب دوم (اختیاری)
 *   title    عنوان — بخش داخل «» طلایی می‌شود، مثلا: انبار «محصولات»
 *   lead     توضیح زیر عنوان
 *   cube     ایموجی وجه‌های مکعب سه‌بعدی سمت چپ
 *   satellite ایموجی ماهوارهٔ چرخان دور مکعب
 *   stats    [{ label, value }]
 *
 * slot ها:
 *   #actions → دکمه‌های اقدام زیر آمارها
 */
import { computed } from 'vue'

const props = defineProps({
    chip: { type: String, default: '' },
    chipTwo: { type: String, default: '' },
    title: { type: String, required: true },
    lead: { type: String, default: '' },
    cube: { type: String, default: '📦' },
    satellite: { type: String, default: '✦' },
    stats: { type: Array, default: () => [] },
})

/** عنوان را می‌شکنیم: بخش داخل گیومهٔ فارسی «...» گرادیان طلایی می‌گیرد */
const titleParts = computed(() => {
    const m = props.title.match(/^(.*?)«(.+?)»(.*)$/)
    if (!m) return { pre: props.title, gold: '', post: '' }
    return { pre: m[1], gold: m[2], post: m[3] }
})
</script>

<template>
    <header class="gx-hero">
        <div style="min-width: 0">
            <div class="gx-hero__chips">
                <span v-if="chip" class="gx-chip">
                    <slot name="chip-icon">✧</slot>
                    {{ chip }}
                </span>
                <span v-if="chipTwo" class="gx-chip gx-chip--plain">{{ chipTwo }}</span>
            </div>

            <h1 class="gx-hero__title">
                {{ titleParts.pre }}<em v-if="titleParts.gold">{{ titleParts.gold }}</em>{{ titleParts.post }}
                <svg class="gx-underline" viewBox="0 0 220 14" aria-hidden="true">
                    <path
                        d="M4 10 C 60 2, 150 2, 216 8"
                        fill="none"
                        stroke="var(--gs-gold)"
                        stroke-width="3.5"
                        stroke-linecap="round"
                    />
                </svg>
            </h1>

            <p v-if="lead" class="gx-hero__lead">{{ lead }}</p>

            <div v-if="stats.length" class="gx-hero__stats">
                <span v-for="(s, i) in stats" :key="i" class="gx-stat">
                    {{ s.label }}
                    <b>{{ s.value }}</b>
                </span>
            </div>

            <div v-if="$slots.actions" class="gx-hero__actions">
                <slot name="actions" />
            </div>
        </div>

        <!-- آرایهٔ سه‌بعدی: مکعب چرخان + حلقهٔ مداری -->
        <div class="gx-hero__ornament" aria-hidden="true">
            <span class="gx-hero__ring" />
            <span class="gx-hero__ring" style="inset: 22px; animation-direction: reverse; animation-duration: 18s" />
            <div class="a3d-cube" style="--cube-size: 64px">
                <span class="a3d-cube__face a3d-cube__face--front">{{ cube }}</span>
                <span class="a3d-cube__face a3d-cube__face--back">{{ cube }}</span>
                <span class="a3d-cube__face a3d-cube__face--right">{{ satellite }}</span>
                <span class="a3d-cube__face a3d-cube__face--left">{{ satellite }}</span>
                <span class="a3d-cube__face a3d-cube__face--top" />
                <span class="a3d-cube__face a3d-cube__face--bottom" />
            </div>
            <span class="gx-hero__satellite">{{ satellite }}</span>
        </div>
    </header>
</template>
