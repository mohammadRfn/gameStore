<template>
    <div class="bar3d" :style="{ height: height + 'px' }">
        <div
            v-for="(item, i) in items"
            :key="item.label + i"
            class="bar3d-col"
            :title="item.label + ' · ' + item.tip"
        >
            <span class="bar3d-value">{{ item.valueText }}</span>
            <div class="bar3d-stack">
                <div class="bar3d-bar" :style="barStyle(item, i)">
                    <span class="bar3d-cap"></span>
                </div>
            </div>
            <span class="bar3d-label" :title="item.label">{{ item.shortLabel }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { fa, compactMoney } from '@/Utils/format'

const props = defineProps({
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    color: { type: String, default: '#e3bd5c' },
    height: { type: Number, default: 240 },
    money: { type: Boolean, default: false },
})

const items = computed(() => {
    const values = (props.values || []).map(Number)
    const max = Math.max(...values, 1)
    return (props.labels || []).map((label, i) => {
        const raw = values[i] ?? 0
        return {
            label,
            shortLabel: String(label).length > 10 ? String(label).slice(0, 9) + '…' : label,
            value: raw,
            pct: (raw / max) * 100,
            valueText: props.money ? compactMoney(raw) : fa(raw),
            tip: props.money ? compactMoney(raw) + ' تومان' : fa(raw),
        }
    })
})

function hexToRgba(hex, a) {
    const n = String(hex).replace('#', '')
    if (n.length !== 6) return hex
    const r = parseInt(n.slice(0, 2), 16)
    const g = parseInt(n.slice(2, 4), 16)
    const b = parseInt(n.slice(4, 6), 16)
    return `rgba(${r}, ${g}, ${b}, ${a})`
}

function barStyle(item, i) {
    return {
        height: Math.max(3, item.pct) + '%',
        animationDelay: (i * 0.05) + 's',
        '--c': props.color,
        '--c-light': hexToRgba(props.color, 0.85),
        '--c-soft': hexToRgba(props.color, 0.4),
    }
}
</script>

<style scoped>
.bar3d {
    display: flex;
    align-items: flex-end;
    gap: 0.7rem;
    padding: 1.25rem 0.5rem 0.5rem;
    overflow: hidden;
}
.bar3d-col {
    flex: 1;
    min-width: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}
.bar3d-value {
    font-size: 0.64rem;
    font-weight: 700;
    color: var(--gs-text-secondary);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}
.bar3d-stack {
    position: relative;
    width: 100%;
    height: calc(100% - 48px);
    display: flex;
    align-items: flex-end;
    justify-content: center;
}
.bar3d-bar {
    position: relative;
    width: min(48%, 46px);
    min-height: 4px;
    border-radius: 8px 8px 4px 4px;
    /* ستون استوانه‌ای: لبهٔ تیره → مرکز روشن → لبهٔ تیره */
    background:
        linear-gradient(90deg, rgba(0,0,0,0.32) 0%, rgba(255,255,255,0.12) 30%, rgba(255,255,255,0.22) 50%, rgba(255,255,255,0.12) 70%, rgba(0,0,0,0.32) 100%),
        linear-gradient(180deg, var(--c-light) 0%, var(--c) 55%, var(--c-soft) 100%);
    box-shadow:
        inset 0 2px 1px rgba(255,255,255,0.35),
        inset 0 -10px 16px rgba(0,0,0,0.28),
        0 10px 22px -8px var(--c-soft);
    animation: bar3d-grow 0.7s var(--gs-ease-spring) both;
    cursor: pointer;
    transition: filter 0.2s ease, box-shadow 0.2s ease;
}
.bar3d-bar:hover {
    filter: brightness(1.12);
    box-shadow:
        inset 0 2px 1px rgba(255,255,255,0.4),
        inset 0 -10px 16px rgba(0,0,0,0.28),
        0 12px 30px -6px var(--c);
}
.bar3d-cap {
    position: absolute;
    top: 0;
    left: 12%;
    right: 12%;
    height: 5px;
    border-radius: 0 0 6px 6px;
    background: linear-gradient(180deg, rgba(255,255,255,0.7), rgba(255,255,255,0.05));
    filter: blur(0.4px);
}
.bar3d-label {
    font-size: 0.66rem;
    color: var(--gs-text-muted);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

@keyframes bar3d-grow {
    from { transform: translateY(24px) scaleY(0.15); opacity: 0; }
    to { transform: translateY(0) scaleY(1); opacity: 1; }
}
</style>
