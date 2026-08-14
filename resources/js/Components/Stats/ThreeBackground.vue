<template>
    <div ref="host" class="gs3d-bg" aria-hidden="true"></div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import * as THREE from 'three'

const props = defineProps({
    density: { type: Number, default: 160 },
    speed: { type: Number, default: 0.05 },
    showPoly: { type: Boolean, default: true },
})

const host = ref(null)

let renderer, scene, camera, points, poly, raf
let mouseX = 0, mouseY = 0
let running = false
let observer = null
let resizeObserver = null
let reduced = false

function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback
}

function makeColors() {
    const gold = new THREE.Color(cssVar('--gs-gold', '#e3bd5c'))
    const light = new THREE.Color(cssVar('--gs-gold-light', '#f3d98a'))
    const blue = new THREE.Color(cssVar('--gs-accent', '#5b9df0'))
    return { gold, light, blue }
}

function build() {
    const el = host.value
    if (!el) return

    const { gold, light, blue } = makeColors()

    renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' })
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2))
    renderer.setSize(el.clientWidth, el.clientHeight)
    el.appendChild(renderer.domElement)

    scene = new THREE.Scene()
    camera = new THREE.PerspectiveCamera(55, el.clientWidth / el.clientHeight, 0.1, 100)
    camera.position.z = 16

    // ذرات ستاره‌ای
    const count = props.density
    const positions = new Float32Array(count * 3)
    const colors = new Float32Array(count * 3)
    for (let i = 0; i < count; i++) {
        positions[i * 3 + 0] = (Math.random() - 0.5) * 26
        positions[i * 3 + 1] = (Math.random() - 0.5) * 14
        positions[i * 3 + 2] = (Math.random() - 0.5) * 10
        const c = Math.random() < 0.85 ? gold : Math.random() < 0.5 ? blue : light
        colors[i * 3 + 0] = c.r
        colors[i * 3 + 1] = c.g
        colors[i * 3 + 2] = c.b
    }
    const geo = new THREE.BufferGeometry()
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3))
    geo.setAttribute('color', new THREE.BufferAttribute(colors, 3))
    const mat = new THREE.PointsMaterial({
        size: 0.12,
        vertexColors: true,
        transparent: true,
        opacity: 0.9,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        sizeAttenuation: true,
    })
    points = new THREE.Points(geo, mat)
    scene.add(points)

    // چندضلعی سیمی — حس عمق سه‌بعدی
    if (props.showPoly) {
        const pGeo = new THREE.IcosahedronGeometry(4.6, 1)
        const pMat = new THREE.MeshBasicMaterial({
            color: gold,
            wireframe: true,
            transparent: true,
            opacity: 0.12,
        })
        poly = new THREE.Mesh(pGeo, pMat)
        poly.position.set(6.5, 2.5, -4)
        scene.add(poly)
    }

    running = true
    animate()
}

function animate() {
    if (!running) return
    raf = requestAnimationFrame(animate)

    if (!reduced) {
        if (points) {
            points.rotation.y += props.speed * 0.004
            points.rotation.x += props.speed * 0.0015
        }
        if (poly) {
            poly.rotation.y += 0.0025
            poly.rotation.x += 0.0012
        }
        // پارالاکس نرم با موس
        camera.position.x += (mouseX * 1.2 - camera.position.x) * 0.05
        camera.position.y += (-mouseY * 0.8 - camera.position.y) * 0.05
        camera.lookAt(0, 0, 0)
    }

    renderer.render(scene, camera)
}

function onMove(e) {
    const w = window.innerWidth
    const h = window.innerHeight
    mouseX = (e.clientX / w) * 2 - 1
    mouseY = (e.clientY / h) * 2 - 1
}

function resize() {
    const el = host.value
    if (!el || !renderer) return
    const w = el.clientWidth
    const h = el.clientHeight
    if (w === 0 || h === 0) return
    camera.aspect = w / h
    camera.updateProjectionMatrix()
    renderer.setSize(w, h)
}

function refreshTheme() {
    if (!points) return
    const { gold, light, blue } = makeColors()
    const colors = points.geometry.attributes.color
    for (let i = 0; i < colors.count; i++) {
        const c = i % 3 === 0 ? gold : (i % 5 === 0 ? blue : light)
        colors.setXYZ(i, c.r, c.g, c.b)
    }
    colors.needsUpdate = true
    if (poly) poly.material.color.set(gold)
}

onMounted(() => {
    reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    build()

    window.addEventListener('mousemove', onMove, { passive: true })
    window.addEventListener('resize', resize)
    window.addEventListener('gs-theme-changed', refreshTheme)

    resizeObserver = new ResizeObserver(() => resize())
    if (host.value) resizeObserver.observe(host.value)

    // توقف رندر وقتی از دید خارج است
    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver((entries) => {
            const visible = entries.some((e) => e.isIntersecting)
            if (visible && !running) {
                running = true
                animate()
            } else if (!visible && running) {
                running = false
                if (raf) cancelAnimationFrame(raf)
            }
        }, { threshold: 0.05 })
        observer.observe(host.value)
    }

    // توقف وقتی تب غیرفعال است
    document.addEventListener('visibilitychange', onVis)
})

function onVis() {
    if (!renderer) return
    if (document.hidden) {
        if (running) { running = false; if (raf) cancelAnimationFrame(raf) }
    } else {
        if (!running) { running = true; animate() }
    }
}

onBeforeUnmount(() => {
    running = false
    if (raf) cancelAnimationFrame(raf)
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('resize', resize)
    window.removeEventListener('gs-theme-changed', refreshTheme)
    document.removeEventListener('visibilitychange', onVis)
    if (observer) observer.disconnect()
    if (resizeObserver) resizeObserver.disconnect()
    if (renderer) {
        renderer.dispose()
        if (renderer.domElement && renderer.domElement.parentNode) {
            renderer.domElement.parentNode.removeChild(renderer.domElement)
        }
    }
    if (points) {
        points.geometry.dispose()
        points.material.dispose()
    }
    if (poly) {
        poly.geometry.dispose()
        poly.material.dispose()
    }
})
</script>

<style scoped>
.gs3d-bg {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 0;
}
.gs3d-bg :deep(canvas) {
    display: block;
    width: 100%;
    height: 100%;
}
</style>
