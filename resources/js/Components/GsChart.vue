<template>
    <div class="gs-chart" :style="{ height: height + 'px' }">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Chart, registerables } from 'chart.js'
import { faInt } from '@/Utils/format'
// 👇 کانورتر شمسی (فایل resources/js/Utils/jalali.js)
import { faLabel, jalaliFull, isDateLabel } from '@/Utils/jalali'

Chart.register(...registerables)
Chart.defaults.font.family = "'Vazir', Tahoma, sans-serif"

const props = defineProps({
    type: { type: String, default: 'bar' },
    labels: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    height: { type: Number, default: 260 },
    stacked: { type: Boolean, default: false },
    centerText: { type: String, default: '' },
    debugLabels: { type: Boolean, default: false },
    valueUnit: { type: String, default: 'toman' }, // 'toman' | 'count'
})

const canvas = ref(null)
let chart = null
let themeObserver = null

const PALETTE = ['#e3bd5c', '#5b9df0', '#45d68b', '#9f7bf6', '#f0b04c', '#f06a6a']

function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback
}

function palette() {
    return [
        cssVar('--gs-gold', '#e3bd5c'),
        cssVar('--gs-accent', '#5b9df0'),
        cssVar('--gs-accent-2', '#45d68b'),
        cssVar('--gs-accent-3', '#9f7bf6'),
        cssVar('--gs-warning', '#f0b04c'),
        cssVar('--gs-error', '#f06a6a'),
    ]
}

function hexToRgba(hex, a) {
    const n = String(hex).replace('#', '')
    if (n.length !== 6) return hex
    const r = parseInt(n.slice(0, 2), 16)
    const g = parseInt(n.slice(2, 4), 16)
    const b = parseInt(n.slice(4, 6), 16)
    return `rgba(${r}, ${g}, ${b}, ${a})`
}

function normalize() {
    return (props.datasets || []).map((d, i) => ({
        label: d.label || '',
        data: (d.data || []).map((n) => Number(n) || 0),
        color: d.color || PALETTE[i % PALETTE.length],
        colors: d.colors || null,
    }))
}

function build() {
    if (!canvas.value) return
    if (chart) { chart.destroy(); chart = null }

    const labels = props.labels || []
    const datasets = normalize()
    const pal = palette()
    const grid = cssVar('--gs-grid-line', 'rgba(227,189,92,0.08)')
    const tickColor = cssVar('--gs-text-muted', '#6c6557')
    const legendColor = cssVar('--gs-text-secondary', '#a9a194')
    const rtl = true

    // 🔍 دیباگ: اگر تاریخ‌ها شمسی نشدند، این را روشن کن تا فرمت خام را ببینی
    if (props.debugLabels && labels.length) {
        // eslint-disable-next-line no-console
        console.log('[GsChart] برچسب خام:', JSON.stringify(labels.slice(0, 3)),
            '| تاریخ تشخیص داده شد؟', isDateLabel(labels[0]),
            '| خروجی:', faLabel(labels[0]))
    }

    // تیک محور دسته‌ای — همیشه از faLabel عبور می‌کند (تاریخ ⇐ شمسی، غیرتاریخ ⇐ ارقام فارسی)
    const catTicks = () => ({
        color: tickColor,
        font: { size: 10 },
        maxRotation: 0,
        autoSkip: true,
        autoSkipPadding: 6,
        callback: function (val, i) {
            const raw = this.getLabelForValue(val)
            const max = labels.length
            if (max > 16 && i % Math.ceil(max / 14) !== 0) return ''
            const out = faLabel(raw)
            return typeof out === 'string' && out.length > 14 ? out.slice(0, 13) + '…' : out
        },
    })

    const valTicks = () => ({
        color: tickColor,
        font: { size: 10 },
        callback: (v) => short(v),
    })

    // عنوان تولتیپ: تاریخ کامل شمسی
    const tooltipTitle = (items) => {
        const raw = items?.[0]?.label ?? ''
        return jalaliFull(raw) || faLabel(raw)
    }

    if (props.type === 'doughnut') {
        const ds = datasets[0] || { data: [], color: pal[0] }
        const colors = ds.colors || pal.slice(0, ds.data.length)
        chart = new Chart(canvas.value, {
            type: 'doughnut',
            data: {
                labels: labels.map((l) => faLabel(l)), // برچسب لجند هم فارسی
                datasets: [{
                    data: ds.data,
                    backgroundColor: colors.map((c, i) => c || pal[i % pal.length]),
                    borderColor: cssVar('--gs-bg-card', '#14141f'),
                    borderWidth: 3,
                    hoverOffset: 8,
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        rtl,
                        position: 'bottom',
                        labels: { color: legendColor, usePointStyle: true, pointStyle: 'circle', padding: 14, font: { size: 11 } },
                    },
                    tooltip: {
                        rtl,
                        callbacks: {
                            title: tooltipTitle,
                            label: (ctx) => {
                                const v = faInt(Number(ctx.parsed) || 0)
                                const suffix = props.valueUnit === 'count' ? ' مورد' : ' تومان'
                                return ` ${faLabel(ctx.label)}: ${v}${suffix}`
                            },
                        },
                    },
                },
            },
            plugins: [centerTextPlugin()],
        })
        return
    }

    const horizontal = props.type === 'hbar'
    const type = horizontal ? 'bar' : (props.type === 'line' ? 'line' : 'bar')

    const chartDatasets = datasets.map((d) => {
        const base = {
            label: d.label,
            data: d.data,
            borderColor: d.color,
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
            tension: 0.4,
            hoverBackgroundColor: d.color,
        }
        if (type === 'line') {
            base.fill = true
            base.backgroundColor = (ctx) => {
                const { chartArea } = ctx.chart
                if (!chartArea) return hexToRgba(d.color, 0.12)
                const g = ctx.chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
                g.addColorStop(0, hexToRgba(d.color, 0.3))
                g.addColorStop(1, hexToRgba(d.color, 0.02))
                return g
            }
            base.pointBackgroundColor = d.color
            base.pointBorderColor = cssVar('--gs-bg-card', '#14141f')
            base.pointRadius = 3
            base.pointHoverRadius = 5
        } else {
            base.backgroundColor = (ctx) => {
                const { chartArea } = ctx.chart
                if (!chartArea) return hexToRgba(d.color, 0.8)
                const g = ctx.chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
                g.addColorStop(0, hexToRgba(d.color, 0.95))
                g.addColorStop(1, hexToRgba(d.color, 0.55))
                return g
            }
        }
        return base
    })

    chart = new Chart(canvas.value, {
        type,
        data: { labels, datasets: chartDatasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            animation: { duration: 700, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: datasets.length > 1 || props.type === 'line',
                    rtl,
                    position: 'bottom',
                    labels: { color: legendColor, usePointStyle: true, pointStyle: 'circle', padding: 14, font: { size: 11 } },
                },
                tooltip: {
                    rtl,
                    backgroundColor: cssVar('--gs-bg-elevated', '#191926'),
                    borderColor: cssVar('--gs-border-hover', 'rgba(227,189,92,0.34)'),
                    borderWidth: 1,
                    titleColor: cssVar('--gs-text-primary', '#f4efe4'),
                    bodyColor: cssVar('--gs-text-secondary', '#a9a194'),
                    padding: 10,
                    callbacks: {
                        title: tooltipTitle,
                        label: (ctx) => {
                            const v = horizontal ? ctx.parsed.x : ctx.parsed.y
                            const name = ctx.dataset.label || faLabel(ctx.label) || ''
                            return ` ${name}: ${faInt(v)} تومان`
                        },
                    },
                },
            },
            scales: {
                x: horizontal ? {
                    rtl, stacked: props.stacked, grid: { color: grid, drawBorder: false }, border: { display: false }, ticks: valTicks(),
                } : {
                    rtl, grid: { color: grid, drawBorder: false }, border: { display: false }, ticks: catTicks(),
                },
                y: horizontal ? {
                    rtl, grid: { color: grid, drawBorder: false }, border: { display: false }, ticks: catTicks(),
                } : {
                    rtl, stacked: props.stacked, grid: { color: grid, drawBorder: false }, border: { display: false }, ticks: valTicks(),
                },
            },
        },
    })
}

function centerTextPlugin() {
    return {
        id: 'centerText',
        afterDraw(chart) {
            const { ctx, chartArea } = chart
            if (!chartArea) return
            const ds = chart.data.datasets[0]
            if (!ds?.data?.length) return
            const cx = (chartArea.left + chartArea.right) / 2
            const cy = (chartArea.top + chartArea.bottom) / 2
            const total = ds.data.reduce((s, v) => s + (Number(v) || 0), 0)
            ctx.save()
            ctx.textAlign = 'center'
            ctx.textBaseline = 'middle'
            ctx.fillStyle = cssVar('--gs-text-muted', '#6c6557')
            ctx.font = '600 11px Vazir, Tahoma'
            ctx.fillText('مجموع', cx, cy - 9)
            ctx.fillStyle = cssVar('--gs-text-primary', '#f4efe4')
            ctx.font = '800 15px Vazir, Tahoma'
            ctx.fillText(short(total), cx, cy + 11)
            ctx.restore()
        },
    }
}

function short(v) {
    const n = Number(v) || 0
    if (Math.abs(n) >= 1e9) return (n / 1e9).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' میلیارد'
    if (Math.abs(n) >= 1e6) return (n / 1e6).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' م'
    if (Math.abs(n) >= 1e3) return (n / 1e3).toLocaleString('fa-IR', { maximumFractionDigits: 0 }) + ' ه'
    return n.toLocaleString('fa-IR')
}

onMounted(() => {
    build()
    themeObserver = new MutationObserver(() => build())
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] })
    window.addEventListener('gs-theme-changed', build)
})

onBeforeUnmount(() => {
    if (themeObserver) themeObserver.disconnect()
    window.removeEventListener('gs-theme-changed', build)
    if (chart) chart.destroy()
})

watch(() => [props.labels, props.datasets, props.type, props.stacked], build, { deep: true })
</script>

<style scoped>
.gs-chart {
    position: relative;
    width: 100%;
}

.gs-chart canvas {
    display: block;
}
</style>
