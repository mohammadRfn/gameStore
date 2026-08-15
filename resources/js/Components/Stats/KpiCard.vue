<template>
    <TiltCard :intensity="7">
        <article class="gs-card gs-kpi">
            <div class="gs-kpi-top">
                <span class="gs-kpi-icon" :class="'tone-' + tone">
                    <component :is="icon" v-if="icon" />
                </span>
                <span v-if="hasDelta" class="gs-kpi-delta" :class="deltaClass">
                    <ArrowUpRight v-if="deltaDir === 'up'" />
                    <ArrowDownRight v-else-if="deltaDir === 'down'" />
                    <Minus v-else />
                    {{ deltaText }}
                </span>
            </div>

            <div>
                <p class="gs-label">{{ label }}</p>
                <p class="gs-kpi-val">{{ value }}</p>
            </div>

            <svg v-if="spark && spark.length" class="kpi-spark" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient :id="sparkId" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" :stop-color="sparkColor" stop-opacity="0.32" />
                        <stop offset="100%" :stop-color="sparkColor" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <polygon :points="areaPoints" :fill="`url(#${sparkId})`" />
                <polyline :points="linePoints" fill="none" :stroke="sparkColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <p v-if="footnote" class="kpi-footnote">{{ footnote }}</p>
        </article>
    </TiltCard>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowUpRight, ArrowDownRight, Minus } from '@lucide/vue'
import TiltCard from './TiltCard.vue'
import { percent } from '@/Utils/format'

const props = defineProps({
    label: { type: String, default: '' },
    value: { type: [String, Number], default: '' },
    delta: { type: Number, default: null },
    invert: { type: Boolean, default: false },
    icon: { type: [Object, Function], default: null },
    tone: { type: String, default: 'gold' },
    spark: { type: Array, default: () => [] },
    footnote: { type: String, default: '' },
})

const hasDelta = computed(() => props.delta !== null && props.delta !== undefined && !Number.isNaN(Number(props.delta)))

// جهت خام (بالا/پایین) بدون در نظر گرفتن invert
const rawDir = computed(() => {
    const d = Number(props.delta) || 0
    return d > 0 ? 'up' : d < 0 ? 'down' : 'flat'
})

// جهت معنایی: برای معوق، افزایش بد است
const deltaDir = computed(() => {
    if (rawDir.value === 'flat') return 'flat'
    const good = rawDir.value === 'up'
    const inverted = props.invert ? !good : good
    return inverted ? 'up' : 'down'
})

const deltaClass = computed(() => ({ up: 'up', down: 'down', flat: 'flat' }[deltaDir.value]))

const deltaText = computed(() => {
    if (rawDir.value === 'flat') return 'ثابت'
    return percent(Math.abs(Number(props.delta) || 0))
})

const sparkColor = computed(() => ({
    gold: 'var(--gs-gold)',
    blue: 'var(--gs-accent)',
    green: 'var(--gs-accent-2)',
    violet: 'var(--gs-accent-3)',
    red: 'var(--gs-error)',
}[props.tone] || 'var(--gs-gold)'))

const sparkId = computed(() => 'spark-' + (Math.random().toString(36).slice(2, 8)))

// محاسبهٔ مختصات اسپارک‌لاین
const linePoints = computed(() => {
    const data = (props.spark || []).map(Number)
    if (!data.length) return ''
    const min = Math.min(...data)
    const max = Math.max(...data)
    const range = max - min || 1
    const step = 100 / (data.length - 1 || 1)
    return data.map((v, i) => {
        const x = (i * step).toFixed(2)
        const y = (26 - ((v - min) / range) * 22 - 2).toFixed(2)
        return `${x},${y}`
    }).join(' ')
})

const areaPoints = computed(() => {
    const data = (props.spark || []).map(Number)
    if (!data.length) return ''
    const min = Math.min(...data)
    const max = Math.max(...data)
    const range = max - min || 1
    const step = 100 / (data.length - 1 || 1)
    const top = data.map((v, i) => `${(i * step).toFixed(2)},${(26 - ((v - min) / range) * 22 - 2).toFixed(2)}`).join(' ')
    return `0,28 ${top} 100,28`
})
</script>

<style scoped>
.gs-kpi {
    min-height: 148px;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.gs-kpi-icon.tone-gold { color: var(--gs-gold); background: var(--gs-gold-muted); border-color: var(--gs-border); }
.gs-kpi-icon.tone-blue { color: var(--gs-accent); background: var(--gs-info-soft); border-color: rgba(91,157,240,0.2); }
.gs-kpi-icon.tone-green { color: var(--gs-accent-2); background: var(--gs-success-soft); border-color: rgba(69,214,139,0.2); }
.gs-kpi-icon.tone-violet { color: var(--gs-accent-3); background: rgba(159,123,246,0.12); border-color: rgba(159,123,246,0.2); }
.gs-kpi-icon.tone-red { color: var(--gs-error); background: var(--gs-error-soft); border-color: rgba(240,106,106,0.2); }

.kpi-spark {
    width: 100%;
    height: 30px;
    margin-top: 0.2rem;
    overflow: visible;
}

.kpi-footnote {
    font-size: 0.72rem;
    color: var(--gs-text-muted);
    margin-top: 0.2rem;
}
</style>
