<script setup>
/**
 * GfxPreview — پیش‌نمایش زندهٔ موتور گرافیک
 * مسیر: resources/js/Components/Settings/GfxPreview.vue
 *
 * مکعب سه‌بعدی واقعاً به تنظیمات واکنش نشان می‌دهد:
 *   • سرعت چرخش  ← نرخ فریم مؤثر
 *   • جزئیات وجوه، ذرات و هاله ← کیفیت بافت
 *   • V-Sync ← قفل شدن فریم روی ۶۰
 * از کلاس‌های a3d-cube/a3d-cube__face موجود در archive-3d.css استفاده می‌کند.
 */
import { computed } from 'vue'
import { Gamepad2 } from 'lucide-vue-next'
import { faInt } from '@/Utils/format'

const props = defineProps({
    /** 'low' | 'mid' | 'high' | 'ultra' */
    quality: { type: String, default: 'high' },
    fps: { type: Number, default: 144 },
    vsync: { type: Boolean, default: true },
})

const LEVELS = ['low', 'mid', 'high', 'ultra']
const LABELS = { low: 'پایین', mid: 'متوسط', high: 'بالا', ultra: 'اولترا' }

const FACES = [
    'front',
    'back',
    'right',
    'left',
    'top',
    'bottom',
]

const PARTICLES = [
    { l: 12, t: 20, s: 4, c: 'var(--gs-gold)', d: 0 },
    { l: 82, t: 16, s: 3, c: 'var(--gs-accent-2)', d: 0.6 },
    { l: 18, t: 72, s: 3, c: 'var(--gs-accent)', d: 1.1 },
    { l: 76, t: 68, s: 4, c: 'var(--gs-gold-light)', d: 0.3 },
    { l: 46, t: 8, s: 3, c: 'var(--gs-gold)', d: 1.6 },
    { l: 92, t: 44, s: 3, c: 'var(--gs-accent-2)', d: 0.9 },
    { l: 6, t: 46, s: 4, c: 'var(--gs-accent-3)', d: 1.9 },
    { l: 62, t: 86, s: 3, c: 'var(--gs-accent)', d: 0.4 },
    { l: 30, t: 34, s: 2, c: 'var(--gs-gold-light)', d: 2.2 },
    { l: 68, t: 30, s: 2, c: 'var(--gs-gold)', d: 1.3 },
    { l: 88, t: 80, s: 3, c: 'var(--gs-accent-3)', d: 2.5 },
    { l: 8, t: 86, s: 3, c: 'var(--gs-accent-2)', d: 0.8 },
    { l: 52, t: 92, s: 2, c: 'var(--gs-accent)', d: 1.7 },
    { l: 38, t: 58, s: 2, c: 'var(--gs-gold-light)', d: 2.8 },
]

const level = computed(() => Math.max(0, LEVELS.indexOf(props.quality)))

/** نرخ فریم مؤثر — V-Sync سقف را روی ۶۰ قفل می‌کند */
const effectiveFps = computed(() => (props.vsync ? Math.min(props.fps, 60) : props.fps))

/** هرچه فریم بالاتر، چرخش سریع‌تر (حداقل ۲٫۴ ثانیه) */
const spinDuration = computed(() => Math.max(2.4, 9 - effectiveFps.value / 30))

const particles = computed(() => PARTICLES.slice(0, [0, 5, 9, 14][level.value]))

const cubeStyle = computed(() => ({
    animationDuration: `${spinDuration.value.toFixed(2)}s`,
    filter:
        level.value === 0
            ? 'saturate(0.5) blur(0.7px)'
            : level.value === 1
              ? 'saturate(0.85)'
              : 'none',
}))

const stageStyle = computed(() => ({
    boxShadow:
        level.value === 3
            ? '0 0 80px var(--gs-gold-glow)'
            : level.value === 2
              ? '0 0 50px rgba(91, 157, 240, 0.12)'
              : 'none',
}))

/** شفافیت آیکون روی وجوه — در کیفیت پایین دیده نمی‌شود */
const iconOpacity = computed(() => [0, 0, 0.42, 0.9][level.value])

const qualityLabel = computed(() => LABELS[props.quality] || '—')
</script>

<template>
    <div class="a3d-holo st-gfx">
        <div class="st-gfx__topbar">
            <span class="st-gfx__caption">پیش‌نمایش زندهٔ موتور</span>
            <span class="st-chip st-chip--live">
                <span class="st-dot" />
                REALTIME
            </span>
        </div>

        <!-- صحنهٔ سه‌بعدی -->
        <div class="st-gfx__stage" :style="stageStyle">
            <!-- ذرات محیطی؛ تعدادشان با کیفیت زیاد می‌شود -->
            <TransitionGroup name="st-particle">
                <span
                    v-for="(p, i) in particles"
                    :key="`${p.l}-${p.t}`"
                    class="st-gfx__particle"
                    :style="{
                        left: `${p.l}%`,
                        top: `${p.t}%`,
                        width: `${p.s}px`,
                        height: `${p.s}px`,
                        background: p.c,
                        boxShadow: `0 0 ${p.s * 2.5}px ${p.c}`,
                        animationDuration: `${4.5 + (i % 5)}s`,
                        animationDelay: `${p.d}s`,
                    }"
                />
            </TransitionGroup>

            <div class="a3d-cube st-gfx__cube" :style="cubeStyle">
                <div
                    v-for="face in FACES"
                    :key="face"
                    class="a3d-cube__face"
                    :class="`a3d-cube__face--${face}`"
                >
                    <Gamepad2
                        :size="34"
                        :style="{ opacity: iconOpacity, transition: 'opacity 0.5s ease' }"
                    />
                </div>
            </div>

            <span class="st-gfx__shadow" />
        </div>

        <!-- قرائت‌های زنده -->
        <div class="st-gfx__readouts">
            <div class="st-gfx__cell">
                <p class="st-gfx__val st-gfx__val--green">{{ faInt(effectiveFps) }}</p>
                <p class="st-gfx__key">FPS</p>
            </div>
            <div class="st-gfx__cell">
                <p class="st-gfx__val">{{ qualityLabel }}</p>
                <p class="st-gfx__key">کیفیت</p>
            </div>
            <div class="st-gfx__cell">
                <p
                    class="st-gfx__val"
                    :class="vsync ? 'st-gfx__val--green' : 'st-gfx__val--off'"
                >
                    {{ vsync ? 'فعال' : 'خاموش' }}
                </p>
                <p class="st-gfx__key">V-SYNC</p>
            </div>
        </div>

        <p v-if="vsync && fps > 60" class="st-gfx__note">
            V-Sync نرخ فریم را روی ۶۰ قفل می‌کند
        </p>
    </div>
</template>

<style scoped>
.st-gfx__topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.st-gfx__caption {
    font-size: 0.76rem;
    color: var(--gs-text-secondary);
}

.st-gfx__cube {
    --cube-size: 126px;
}

.st-gfx__particle {
    position: absolute;
    border-radius: 50%;
    animation-name: st-float;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
    pointer-events: none;
}

.st-gfx__note {
    margin-top: 0.55rem;
    text-align: center;
    font-size: 0.7rem;
    color: var(--gs-text-muted);
}

.st-particle-enter-active,
.st-particle-leave-active {
    transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.st-particle-enter-from,
.st-particle-leave-to {
    opacity: 0;
    transform: scale(0);
}
</style>
