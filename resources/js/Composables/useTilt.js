/* ==========================================================================
 * GameStore · 3D Interaction Directives
 * --------------------------------------------------------------------------
 * مسیر فایل: resources/js/Composables/useTilt.js
 *
 * دو دایرکتیو سبک، بدون هیچ وابستگی خارجی و کاملاً سازگار با SSR/Vite:
 *
 *   v-tilt    → چرخش سه‌بعدی کارت با حرکت ماوس + درخشش نقطه‌ای (Glare)
 *   v-reveal  → ظاهر شدن سینمایی المنت هنگام ورود به کادر دید (IntersectionObserver)
 *
 * نحوهٔ استفاده در کامپوننت:
 *   import { vTilt, vReveal } from '@/Composables/useTilt'
 *   <div v-tilt="{ max: 12, scale: 1.02, lift: 18 }" v-reveal="{ delay: 120 }">
 *
 * هر دو دایرکتیو تنظیم prefers-reduced-motion سیستم‌عامل را رعایت می‌کنند.
 * ========================================================================== */

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'

function prefersReducedMotion() {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia(REDUCED_MOTION_QUERY).matches
}

/* --------------------------------------------------------------------------
 * v-tilt — چرخش سه‌بعدی وابسته به موقعیت نشانگر
 * -------------------------------------------------------------------------- */
const DEFAULT_TILT = {
  max: 10,        // حداکثر زاویهٔ چرخش بر حسب درجه
  scale: 1.015,   // بزرگ‌نمایی هنگام هاور
  lift: 14,       // میزان بیرون آمدن کارت روی محور Z (پیکسل)
  glare: true,    // فعال بودن درخشش نقطه‌ای
  reverse: false, // معکوس کردن جهت چرخش
}

export const vTilt = {
  mounted(el, binding) {
    const options = { ...DEFAULT_TILT, ...(binding.value || {}) }

    el.classList.add('a3d-tilt')

    // ساخت لایهٔ درخشش در صورت نیاز
    if (options.glare && !el.querySelector(':scope > .a3d-glare')) {
      const glare = document.createElement('span')
      glare.className = 'a3d-glare'
      glare.setAttribute('aria-hidden', 'true')
      el.appendChild(glare)
    }

    let frame = null

    const reset = () => {
      el.dataset.tilting = 'false'
      el.style.setProperty('--a3d-tilt-x', '0deg')
      el.style.setProperty('--a3d-tilt-y', '0deg')
      el.style.setProperty('--a3d-lift', '0px')
      el.style.setProperty('--a3d-scale', '1')
    }

    const onPointerMove = (event) => {
      if (prefersReducedMotion()) return
      if (frame) cancelAnimationFrame(frame)

      frame = requestAnimationFrame(() => {
        const rect = el.getBoundingClientRect()
        if (!rect.width || !rect.height) return

        const px = (event.clientX - rect.left) / rect.width   // 0..1
        const py = (event.clientY - rect.top) / rect.height   // 0..1

        const direction = options.reverse ? -1 : 1
        const rotateY = (px - 0.5) * 2 * options.max * direction
        const rotateX = (0.5 - py) * 2 * options.max * direction

        el.dataset.tilting = 'true'
        el.style.setProperty('--a3d-tilt-x', `${rotateX.toFixed(2)}deg`)
        el.style.setProperty('--a3d-tilt-y', `${rotateY.toFixed(2)}deg`)
        el.style.setProperty('--a3d-lift', `${options.lift}px`)
        el.style.setProperty('--a3d-scale', String(options.scale))
        el.style.setProperty('--a3d-pointer-x', `${(px * 100).toFixed(2)}%`)
        el.style.setProperty('--a3d-pointer-y', `${(py * 100).toFixed(2)}%`)
      })
    }

    const onPointerLeave = () => {
      if (frame) cancelAnimationFrame(frame)
      reset()
    }

    el.addEventListener('pointermove', onPointerMove, { passive: true })
    el.addEventListener('pointerleave', onPointerLeave, { passive: true })
    el.addEventListener('pointercancel', onPointerLeave, { passive: true })
    el.addEventListener('blur', onPointerLeave, true)

    reset()

    el._a3dTilt = { onPointerMove, onPointerLeave, cancel: () => frame && cancelAnimationFrame(frame) }
  },

  unmounted(el) {
    const handlers = el._a3dTilt
    if (!handlers) return
    handlers.cancel()
    el.removeEventListener('pointermove', handlers.onPointerMove)
    el.removeEventListener('pointerleave', handlers.onPointerLeave)
    el.removeEventListener('pointercancel', handlers.onPointerLeave)
    el.removeEventListener('blur', handlers.onPointerLeave, true)
    delete el._a3dTilt
  },
}

/* --------------------------------------------------------------------------
 * v-reveal — ورود سینمایی هنگام دیده شدن
 * -------------------------------------------------------------------------- */
export const vReveal = {
  mounted(el, binding) {
    const { delay = 0, threshold = 0.12, once = true } = binding.value || {}

    el.classList.add('a3d-reveal')
    el.style.setProperty('--a3d-delay', `${delay}ms`)

    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
      el.classList.add('is-visible')
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            el.classList.add('is-visible')
            if (once) observer.unobserve(el)
          } else if (!once) {
            el.classList.remove('is-visible')
          }
        })
      },
      { threshold, rootMargin: '0px 0px -40px 0px' }
    )

    observer.observe(el)
    el._a3dReveal = observer
  },

  unmounted(el) {
    if (el._a3dReveal) {
      el._a3dReveal.disconnect()
      delete el._a3dReveal
    }
  },
}

/* --------------------------------------------------------------------------
 * useParallax — پارالاکس ملایم برای لایه‌های پس‌زمینه
 * -------------------------------------------------------------------------- */
export function useParallax(targetRef, strength = 18) {
  let frame = null

  const onMove = (event) => {
    const el = targetRef?.value
    if (!el || prefersReducedMotion()) return
    if (frame) cancelAnimationFrame(frame)

    frame = requestAnimationFrame(() => {
      const x = (event.clientX / window.innerWidth - 0.5) * strength
      const y = (event.clientY / window.innerHeight - 0.5) * strength
      el.style.transform = `translate3d(${x.toFixed(2)}px, ${y.toFixed(2)}px, 0)`
    })
  }

  const start = () => window.addEventListener('pointermove', onMove, { passive: true })
  const stop = () => {
    window.removeEventListener('pointermove', onMove)
    if (frame) cancelAnimationFrame(frame)
  }

  return { start, stop }
}

export default { vTilt, vReveal, useParallax }
