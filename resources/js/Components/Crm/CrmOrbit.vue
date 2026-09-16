<script setup>
/**
 * CrmOrbit — دکور مداری هیرو (هم‌خانوادهٔ GearsCluster تنظیمات)
 * مسیر: resources/js/Components/Crm/CrmOrbit.vue
 *
 * یک هستهٔ هولوگرافیک شناور + ماهواره‌های آیکونی روی مدارهای چرخان
 * + پارالاکس سه‌لایه با حرکت نشانگر. کاملاً SVG/CSS — بدون تصویر.
 *
 * استفاده:
 *   <CrmOrbit :icon="Users" :satellites="[{ icon: Phone, color: 'var(--gs-info)' }, ...]" />
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
    icon: { type: [Object, Function], required: true },
    /** [{ icon, color?, radius?, duration?, reverse? }] */
    satellites: { type: Array, default: () => [] },
    accent: { type: String, default: 'var(--gs-gold)' },
})

const RADII = [78, 112, 96]
const DURS = [14, 22, 18]

function trackStyle(s, i) {
    const dur = s.duration ?? DURS[i % DURS.length]
    return {
        animation: `${s.reverse ? 'st-spin-rev' : 'st-spin'} ${dur}s linear infinite`,
        animationDelay: `-${(i * dur) / (props.satellites.length || 1)}s`,
    }
}

function satStyle(s, i) {
    const r = s.radius ?? RADII[i % RADII.length]
    const dur = s.duration ?? DURS[i % DURS.length]
    return {
        transform: `translate(${r}px, 0)`,
        '--crm-sat': s.color || 'var(--gs-gold)',
        animation: `${s.reverse ? 'st-spin' : 'st-spin-rev'} ${dur}s linear infinite`,
        animationDelay: `-${(i * dur) / (props.satellites.length || 1)}s`,
    }
}

const root = ref(null)
let frame = null

function onMove(e) {
    if (!root.value) return
    if (frame) cancelAnimationFrame(frame)
    frame = requestAnimationFrame(() => {
        const x = e.clientX / window.innerWidth - 0.5
        const y = e.clientY / window.innerHeight - 0.5
        root.value.style.setProperty('--crm-ox', x.toFixed(3))
        root.value.style.setProperty('--crm-oy', y.toFixed(3))
    })
}

onMounted(() => window.addEventListener('pointermove', onMove, { passive: true }))
onBeforeUnmount(() => {
    window.removeEventListener('pointermove', onMove)
    if (frame) cancelAnimationFrame(frame)
})
</script>

<template>
    <div ref="root" class="crm-orbit" :style="{ '--crm-accent': accent }" aria-hidden="true">
        <!-- مدارهای نقطه‌چین -->
        <span class="crm-orbit__ring" style="width: 156px; height: 156px; animation: st-spin 40s linear infinite" />
        <span class="crm-orbit__ring" style="width: 224px; height: 224px; animation: st-spin-rev 64s linear infinite; opacity: 0.5" />

        <!-- لایهٔ عمیق: ماهواره‌ها -->
        <div
            class="crm-orbit__layer"
            style="transform: translate3d(calc(var(--crm-ox) * 10px), calc(var(--crm-oy) * 8px), 0)"
        >
            <span
                v-for="(s, i) in satellites"
                :key="i"
                class="crm-orbit__track"
                :style="trackStyle(s, i)"
            >
                <span class="crm-orbit__sat" :style="satStyle(s, i)">
                    <component :is="s.icon" :size="18" />
                </span>
            </span>
        </div>

        <!-- لایهٔ جلو: هسته -->
        <div
            class="crm-orbit__layer"
            style="transform: translate3d(calc(var(--crm-ox) * 22px), calc(var(--crm-oy) * 18px), 0)"
        >
            <span class="crm-orbit__core">
                <component :is="icon" :size="40" />
            </span>
        </div>

        <!-- جرقه‌ها -->
        <span class="st-spark" style="top: 12%; inset-inline-start: 18%; width: 5px; height: 5px; background: var(--gs-gold-light)" />
        <span class="st-spark" style="top: 70%; inset-inline-start: 82%; width: 4px; height: 4px; background: var(--gs-accent); animation-delay: -2s" />
        <span class="st-spark" style="top: 86%; inset-inline-start: 26%; width: 3px; height: 3px; background: var(--gs-accent-3); animation-delay: -4s" />
    </div>
</template>
