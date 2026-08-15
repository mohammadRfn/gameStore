<template>
    <div
        ref="el"
        class="tilt3d"
        :style="style"
        @mousemove="onMove"
        @mouseenter="onEnter"
        @mouseleave="onLeave"
    >
        <slot />
        <span v-if="glare" class="tilt3d-glare" :style="glareStyle" aria-hidden="true"></span>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    intensity: { type: Number, default: 9 },
    glare: { type: Boolean, default: true },
    scale: { type: Number, default: 1.015 },
})

const el = ref(null)
const rx = ref(0)
const ry = ref(0)
const px = ref(50)
const py = ref(50)
const hovering = ref(false)

const style = computed(() => {
    const s = hovering.value ? props.scale : 1
    return {
        transform: `perspective(1000px) rotateX(${rx.value}deg) rotateY(${ry.value}deg) scale3d(${s}, ${s}, ${s})`,
    }
})

const glareStyle = computed(() => ({
    background: `radial-gradient(340px circle at ${px.value}% ${py.value}%, var(--gs-gold-glow), transparent 55%)`,
    opacity: hovering.value ? 1 : 0,
}))

function onMove(e) {
    const rect = el.value.getBoundingClientRect()
    px.value = ((e.clientX - rect.left) / rect.width) * 100
    py.value = ((e.clientY - rect.top) / rect.height) * 100
    // در RTL، جهت X را برعکس می‌کنیم تا حس طبیعی بماند
    const dx = (px.value - 50) / 50
    const dy = (py.value - 50) / 50
    ry.value = dx * props.intensity
    rx.value = -dy * props.intensity
}

function onEnter() {
    hovering.value = true
}

function onLeave() {
    hovering.value = false
    rx.value = 0
    ry.value = 0
}
</script>

<style scoped>
.tilt3d {
    position: relative;
    height: 100%;
    transform-style: preserve-3d;
    will-change: transform;
    transition: transform 0.16s ease-out;
    border-radius: var(--gs-radius);
}
.tilt3d-glare {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 3;
}
</style>
