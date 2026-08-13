<template>
    <canvas ref="canvas" :height="height"></canvas>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    type: { type: String, default: 'bar' },
    labels: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    height: { type: Number, default: 240 },
    stacked: { type: Boolean, default: false },
})

const canvas = ref(null)
let resizeObserver = null

const gold = '#c9a84c'
const muted = '#a09880'
const grid = 'rgba(201,168,76,0.12)'

function css(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback
}

function draw() {
    const el = canvas.value
    if (!el) return
    const ctx = el.getContext('2d')
    const dpr = window.devicePixelRatio || 1
    const width = el.clientWidth || el.parentElement?.clientWidth || 600
    const height = props.height
    el.width = width * dpr
    el.height = height * dpr
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, width, height)

    const labels = props.labels || []
    const datasets = (props.datasets || []).map((d, i) => ({
        label: d.label || '',
        data: (d.data || []).map((n) => Number(n) || 0),
        color: d.color || [gold, '#4c8fe0', '#4caf7d', '#e05c5c'][i % 4],
    }))

    if (!labels.length || !datasets.length) {
        ctx.fillStyle = muted
        ctx.font = '13px Vazirmatn, Tahoma, sans-serif'
        ctx.textAlign = 'center'
        ctx.fillText('داده‌ای برای این بازه نیست', width / 2, height / 2)
        return
    }

    if (props.type === 'doughnut') {
        drawDoughnut(ctx, width, height, labels, datasets[0])
        return
    }
    if (props.type === 'hbar') {
        drawHBar(ctx, width, height, labels, datasets[0])
        return
    }
    drawCartesian(ctx, width, height, labels, datasets)
}

function maxValue(datasets) {
    if (props.stacked) {
        const len = datasets[0]?.data.length || 0
        let m = 0
        for (let i = 0; i < len; i++) {
            const s = datasets.reduce((a, d) => a + (d.data[i] || 0), 0)
            if (s > m) m = s
        }
        return m || 1
    }
    return Math.max(1, ...datasets.flatMap((d) => d.data))
}

function drawCartesian(ctx, width, height, labels, datasets) {
    const pad = { t: 16, r: 12, b: 44, l: 48 }
    const w = width - pad.l - pad.r
    const h = height - pad.t - pad.b
    const max = maxValue(datasets)
    const n = labels.length
    const gap = 6
    const groupW = w / n
    const barW = Math.max(4, (groupW - gap) / (props.stacked ? 1 : datasets.length))

    ctx.strokeStyle = grid
    ctx.lineWidth = 1
    ctx.fillStyle = muted
    ctx.font = '11px Vazirmatn, Tahoma, sans-serif'
    ctx.textAlign = 'left'
    for (let i = 0; i <= 4; i++) {
        const y = pad.t + h - (h * i) / 4
        ctx.beginPath()
        ctx.moveTo(pad.l, y)
        ctx.lineTo(pad.l + w, y)
        ctx.stroke()
        const val = (max * i) / 4
        ctx.fillText(shortNum(val), 4, y + 4)
    }

    ctx.textAlign = 'center'
    labels.forEach((label, i) => {
        const x = pad.l + i * groupW + groupW / 2
        if (n > 20 && i % Math.ceil(n / 12) !== 0) return
        ctx.save()
        ctx.translate(x, pad.t + h + 14)
        ctx.rotate(-0.45)
        ctx.fillText(String(label), 0, 0)
        ctx.restore()
    })

    if (props.type === 'line') {
        datasets.forEach((ds) => {
            ctx.beginPath()
            ctx.strokeStyle = ds.color
            ctx.lineWidth = 2
            ds.data.forEach((v, i) => {
                const x = pad.l + i * groupW + groupW / 2
                const y = pad.t + h - (v / max) * h
                if (i === 0) ctx.moveTo(x, y)
                else ctx.lineTo(x, y)
            })
            ctx.stroke()
            ctx.lineTo(pad.l + (n - 1) * groupW + groupW / 2, pad.t + h)
            ctx.lineTo(pad.l + groupW / 2, pad.t + h)
            ctx.closePath()
            ctx.fillStyle = hexAlpha(ds.color, 0.15)
            ctx.fill()
        })
        return
    }

    datasets.forEach((ds, di) => {
        ds.data.forEach((v, i) => {
            let base = 0
            if (props.stacked) {
                base = datasets.slice(0, di).reduce((a, d) => a + (d.data[i] || 0), 0)
            }
            const bh = (v / max) * h
            const bb = (base / max) * h
            const x = props.stacked
                ? pad.l + i * groupW + gap / 2
                : pad.l + i * groupW + gap / 2 + di * barW
            const y = pad.t + h - bb - bh
            const bw = props.stacked ? groupW - gap : barW
            ctx.fillStyle = ds.color
            roundRect(ctx, x, y, Math.max(1, bw), Math.max(0, bh), 3)
            ctx.fill()
        })
    })
}

function drawHBar(ctx, width, height, labels, ds) {
    const pad = { t: 8, r: 16, b: 8, l: 120 }
    const w = width - pad.l - pad.r
    const h = height - pad.t - pad.b
    const n = labels.length || 1
    const rowH = h / n
    const max = Math.max(1, ...ds.data)
    ctx.font = '12px Vazirmatn, Tahoma, sans-serif'
    labels.forEach((label, i) => {
        const y = pad.t + i * rowH + 4
        const bh = Math.max(10, rowH - 10)
        ctx.fillStyle = muted
        ctx.textAlign = 'right'
        ctx.fillText(truncate(String(label), 16), pad.l - 8, y + bh * 0.7)
        ctx.fillStyle = ds.color
        roundRect(ctx, pad.l, y, (ds.data[i] / max) * w, bh, 4)
        ctx.fill()
    })
}

function drawDoughnut(ctx, width, height, labels, ds) {
    const cx = width / 2
    const cy = height / 2 - 8
    const r = Math.min(width, height) / 2 - 28
    const total = ds.data.reduce((a, b) => a + b, 0) || 1
    let angle = -Math.PI / 2
    ds.data.forEach((v, i) => {
        const slice = (v / total) * Math.PI * 2
        ctx.beginPath()
        ctx.moveTo(cx, cy)
        ctx.fillStyle = ['#c9a84c', '#4c8fe0', '#4caf7d', '#e05c5c', '#e0a84c', '#8b5cf6'][i % 6]
        ctx.arc(cx, cy, r, angle, angle + slice)
        ctx.closePath()
        ctx.fill()
        angle += slice
    })
    ctx.beginPath()
    ctx.fillStyle = css('--gs-bg-card', '#16161f')
    ctx.arc(cx, cy, r * 0.62, 0, Math.PI * 2)
    ctx.fill()

    ctx.font = '11px Vazirmatn, Tahoma, sans-serif'
    ctx.textAlign = 'center'
    const legendY = height - 14
    const step = width / Math.max(labels.length, 1)
    labels.forEach((label, i) => {
        ctx.fillStyle = ['#c9a84c', '#4c8fe0', '#4caf7d', '#e05c5c', '#e0a84c', '#8b5cf6'][i % 6]
        ctx.fillRect(step * i + step / 2 - 28, legendY - 8, 8, 8)
        ctx.fillStyle = muted
        ctx.fillText(truncate(String(label), 10), step * i + step / 2 + 8, legendY)
    })
}

function shortNum(v) {
    if (v >= 1e9) return (v / 1e9).toFixed(1) + 'میلیارد'
    if (v >= 1e6) return (v / 1e6).toFixed(1) + 'م'
    if (v >= 1e3) return (v / 1e3).toFixed(0) + 'ه'
    return String(Math.round(v))
}

function truncate(s, n) {
    return s.length > n ? s.slice(0, n - 1) + '…' : s
}

function hexAlpha(hex, a) {
    const n = hex.replace('#', '')
    const r = parseInt(n.slice(0, 2), 16)
    const g = parseInt(n.slice(2, 4), 16)
    const b = parseInt(n.slice(4, 6), 16)
    return `rgba(${r},${g},${b},${a})`
}

function roundRect(ctx, x, y, w, h, r) {
    const rr = Math.min(r, w / 2, h / 2)
    ctx.beginPath()
    ctx.moveTo(x + rr, y)
    ctx.arcTo(x + w, y, x + w, y + h, rr)
    ctx.arcTo(x + w, y + h, x, y + h, rr)
    ctx.arcTo(x, y + h, x, y, rr)
    ctx.arcTo(x, y, x + w, y, rr)
    ctx.closePath()
}

onMounted(() => {
    draw()
    resizeObserver = new ResizeObserver(() => draw())
    if (canvas.value?.parentElement) resizeObserver.observe(canvas.value.parentElement)
})
onBeforeUnmount(() => resizeObserver?.disconnect())
watch(() => [props.labels, props.datasets, props.type, props.stacked], draw, { deep: true })
</script>

<style scoped>
canvas {
    width: 100%;
    display: block;
}
</style>
