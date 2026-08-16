<script setup>
/**
 * GearsCluster — خوشهٔ چرخ‌دنده‌های SVG رویه‌ای با پارالاکس سه‌لایه
 * مسیر: resources/js/Components/Settings/GearsCluster.vue
 *
 * مسیر چرخ‌دنده به‌صورت ریاضی ساخته می‌شود (بدون فایل تصویری).
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'

/** ساخت path چرخ‌دنده: دندانه‌ها + سوراخ میانی (fill-rule="evenodd") */
function gearPath(teeth, outer, inner, hole) {
    const c = 50
    const step = (Math.PI * 2) / teeth
    const at = (r, a) =>
        `${(c + Math.cos(a) * r).toFixed(2)} ${(c + Math.sin(a) * r).toFixed(2)}`

    const pts = []
    for (let i = 0; i < teeth; i += 1) {
        const a = i * step
        pts.push(
            at(inner, a),
            at(outer, a + step * 0.16),
            at(outer, a + step * 0.34),
            at(inner, a + step * 0.5),
        )
    }

    const ring =
        `M ${(c + hole).toFixed(2)} ${c} ` +
        `A ${hole} ${hole} 0 1 0 ${(c - hole).toFixed(2)} ${c} ` +
        `A ${hole} ${hole} 0 1 0 ${(c + hole).toFixed(2)} ${c} Z`

    return `M${pts.join(' L')} Z ${ring}`
}

const gears = [
    {
        d: gearPath(12, 48, 38, 16),
        size: 166,
        top: '14px',
        start: '96px',
        tone: 'st-gear--gold',
        spin: 'st-spin 26s linear infinite',
        depth: 17,
    },
    {
        d: gearPath(10, 48, 37, 13),
        size: 110,
        top: '116px',
        start: '4px',
        tone: 'st-gear--muted',
        spin: 'st-spin-rev 18s linear infinite',
        depth: 9,
    },
    {
        d: gearPath(8, 48, 36, 10),
        size: 70,
        top: '176px',
        start: '212px',
        tone: 'st-gear--blue',
        spin: 'st-spin 11s linear infinite',
        depth: -7,
    },
]

const root = ref(null)
let frame = null

function onPointerMove(event) {
    if (!root.value) return
    if (frame) cancelAnimationFrame(frame)

    frame = requestAnimationFrame(() => {
        const x = event.clientX / window.innerWidth - 0.5
        const y = event.clientY / window.innerHeight - 0.5
        root.value.style.setProperty('--st-gx', x.toFixed(3))
        root.value.style.setProperty('--st-gy', y.toFixed(3))
    })
}

onMounted(() => window.addEventListener('pointermove', onPointerMove, { passive: true }))

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', onPointerMove)
    if (frame) cancelAnimationFrame(frame)
})
</script>

<template>
    <div ref="root" class="st-gears" aria-hidden="true">
        <!-- مدارهای نقطه‌چین چرخان -->
        <span
            class="st-gears__orbit"
            style="width: 252px; height: 252px; animation: st-spin 46s linear infinite"
        />
        <span
            class="st-gears__orbit"
            style="width: 176px; height: 176px; animation: st-spin-rev 30s linear infinite"
        />

        <!-- لایه‌های چرخ‌دنده با عمق متفاوت -->
        <div
            v-for="(g, i) in gears"
            :key="i"
            class="st-gears__layer"
            :style="{
                transform: `translate3d(calc(var(--st-gx, 0) * ${g.depth}px), calc(var(--st-gy, 0) * ${g.depth * 0.78}px), 0)`,
            }"
        >
            <svg
                viewBox="0 0 100 100"
                :width="g.size"
                :height="g.size"
                class="st-gear"
                :class="g.tone"
                :style="{ top: g.top, insetInlineStart: g.start, animation: g.spin }"
            >
                <path :d="g.d" fill="currentColor" fill-rule="evenodd" />
                <circle cx="50" cy="50" r="6.5" fill="currentColor" opacity="0.85" />
            </svg>
        </div>

        <!-- جرقه‌های شناور -->
        <span
            class="st-spark"
            style="
                top: 30px;
                inset-inline-start: 44px;
                width: 8px;
                height: 8px;
                background: var(--gs-gold-light);
                box-shadow: 0 0 12px var(--gs-gold);
            "
        />
        <span
            class="st-spark"
            style="
                top: 118px;
                inset-inline-start: 238px;
                width: 6px;
                height: 6px;
                background: var(--gs-accent-2);
                box-shadow: 0 0 10px var(--gs-accent-2);
                animation-delay: 1s;
            "
        />
        <span
            class="st-spark"
            style="
                top: 232px;
                inset-inline-start: 148px;
                width: 6px;
                height: 6px;
                background: var(--gs-accent);
                box-shadow: 0 0 10px var(--gs-accent);
                animation-delay: 0.5s;
            "
        />
    </div>
</template>
