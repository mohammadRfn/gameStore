<!-- ==========================================================================
     GameStore · ArchiveScene
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveScene.vue

     لایهٔ پس‌زمینهٔ سه‌بعدی صفحهٔ بایگانی:
       • کف مشبک با پرسپکتیو واقعی و اسکرول بی‌نهایت
       • سه گویِ نورانی شناور با پارالاکس وابسته به ماوس
       • ذرات گرد‌وغبار طلایی که به بالا شناور می‌شوند
     کاملاً pointer-events:none است و هیچ تداخلی با کلیک‌ها ندارد.
     ========================================================================== -->
<template>
  <div class="a3d-scene" aria-hidden="true">
    <!-- کف مشبک پرسپکتیو -->
    <div class="a3d-grid-floor"></div>

    <!-- گوی‌های نورانی با پارالاکس -->
    <div ref="orbLayerA" class="orb-layer">
      <span class="a3d-orb a3d-orb--gold a3d-float-a orb orb--a"></span>
    </div>
    <div ref="orbLayerB" class="orb-layer">
      <span class="a3d-orb a3d-orb--blue a3d-float-b orb orb--b"></span>
    </div>
    <div ref="orbLayerC" class="orb-layer">
      <span class="a3d-orb a3d-orb--violet a3d-float-a orb orb--c"></span>
    </div>

    <!-- ذرات شناور -->
    <span
      v-for="dust in dustParticles"
      :key="dust.id"
      class="a3d-dust"
      :style="dust.style"
    ></span>

    <!-- محو کردن لبهٔ پایین برای ادغام نرم با محتوا -->
    <div class="scene-fade"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  dustCount: { type: Number, default: 18 },
  parallax: { type: Boolean, default: true },
})

const orbLayerA = ref(null)
const orbLayerB = ref(null)
const orbLayerC = ref(null)

/* ذرات با موقعیت و زمان‌بندی تصادفی — یک‌بار ساخته می‌شوند */
const dustParticles = Array.from({ length: props.dustCount }, (_, index) => ({
  id: index,
  style: {
    left: `${Math.random() * 100}%`,
    top: `${55 + Math.random() * 45}%`,
    animationDuration: `${9 + Math.random() * 11}s`,
    animationDelay: `${Math.random() * 9}s`,
    opacity: 0,
  },
}))

let frame = null

function reducedMotion() {
  return typeof window !== 'undefined'
    && window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function handlePointer(event) {
  if (!props.parallax || reducedMotion()) return
  if (frame) cancelAnimationFrame(frame)

  frame = requestAnimationFrame(() => {
    const x = event.clientX / window.innerWidth - 0.5
    const y = event.clientY / window.innerHeight - 0.5

    const apply = (element, depth) => {
      if (!element) return
      element.style.transform =
        `translate3d(${(x * depth).toFixed(2)}px, ${(y * depth).toFixed(2)}px, 0)`
    }

    apply(orbLayerA.value, 42)
    apply(orbLayerB.value, -30)
    apply(orbLayerC.value, 22)
  })
}

onMounted(() => {
  window.addEventListener('pointermove', handlePointer, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', handlePointer)
  if (frame) cancelAnimationFrame(frame)
})
</script>

<style scoped>
.orb-layer {
  position: absolute;
  inset: 0;
  transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: transform;
}

.orb {
  position: absolute;
  display: block;
}

.orb--a {
  width: 420px;
  height: 420px;
  top: -140px;
  right: -80px;
}

.orb--b {
  width: 360px;
  height: 360px;
  bottom: -120px;
  left: -70px;
}

.orb--c {
  width: 280px;
  height: 280px;
  top: 32%;
  left: 44%;
  opacity: 0.28;
}

.scene-fade {
  position: absolute;
  inset: auto 0 0 0;
  height: 38%;
  background: linear-gradient(to top, var(--gs-bg) 6%, transparent 100%);
}

@media (max-width: 768px) {
  .orb--a { width: 260px; height: 260px; }
  .orb--b { width: 220px; height: 220px; }
  .orb--c { display: none; }
}
</style>
