/**
 * useCountUp — شمارندهٔ انیمیت‌شدهٔ اعداد (برای KPI ها)
 * مسیر: resources/js/Composables/useCountUp.js
 *
 * استفاده:
 *   const shown = useCountUp(() => props.value, { duration: 1200 })
 *   {{ faInt(shown) }}
 *
 * prefers-reduced-motion را رعایت می‌کند و با تغییر مقدار، از عدد فعلی
 * به مقدار جدید حرکت می‌کند (نه از صفر).
 */
import { onBeforeUnmount, ref, watch } from 'vue'

function reduced() {
    return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
}

const easeOutExpo = (t) => (t >= 1 ? 1 : 1 - Math.pow(2, -10 * t))

export function useCountUp(source, { duration = 1100, delay = 0 } = {}) {
    const value = ref(0)
    let frame = null
    let timer = null

    const getTarget = () => Number(typeof source === 'function' ? source() : source?.value ?? source) || 0

    function animate(to) {
        if (frame) cancelAnimationFrame(frame)
        if (reduced()) {
            value.value = to
            return
        }
        const from = value.value
        const start = performance.now()
        const tick = (now) => {
            const p = Math.min(1, (now - start) / duration)
            value.value = Math.round(from + (to - from) * easeOutExpo(p))
            if (p < 1) frame = requestAnimationFrame(tick)
        }
        frame = requestAnimationFrame(tick)
    }

    watch(
        getTarget,
        (to) => {
            clearTimeout(timer)
            timer = setTimeout(() => animate(to), delay)
        },
        { immediate: true },
    )

    onBeforeUnmount(() => {
        clearTimeout(timer)
        if (frame) cancelAnimationFrame(frame)
    })

    return value
}

export default useCountUp
