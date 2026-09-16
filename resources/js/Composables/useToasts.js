/**
 * useToasts — مدیریت توست‌ها (سازگار با ToastHost تنظیمات)
 * مسیر: resources/js/Composables/useToasts.js
 *
 * استفاده:
 *   import ToastHost from '@/Components/Settings/ToastHost.vue'
 *   const { toasts, push, dismiss } = useToasts({ flash: true })
 *   <ToastHost :toasts="toasts" @close="dismiss" />
 *
 * با flash:true پیام‌های session('success' | 'error') که کنترلرهای
 * Customer/Request با redirect()->with(...) می‌فرستند، به‌صورت خودکار
 * به توست تبدیل می‌شوند (همان چیزی که HandleInertiaRequests share می‌کند).
 */
import { onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useToasts({ flash = false, ttl = 3600 } = {}) {
    const toasts = ref([])
    const timers = new Map()

    function dismiss(id) {
        clearTimeout(timers.get(id))
        timers.delete(id)
        toasts.value = toasts.value.filter((t) => t.id !== id)
    }

    function push(kind, msg) {
        if (!msg) return
        const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`
        toasts.value = [...toasts.value.slice(-2), { id, kind, msg }]
        timers.set(id, setTimeout(() => dismiss(id), ttl))
        return id
    }

    const success = (m) => push('success', m)
    const info = (m) => push('info', m)
    const danger = (m) => push('danger', m)

    if (flash) {
        const page = usePage()
        let lastKey = ''
        watch(
            () => page.props.flash,
            (f) => {
                if (!f) return
                const key = `${f.success || ''}|${f.error || ''}`
                if (!key.replace('|', '') || key === lastKey) return
                lastKey = key
                if (f.success) success(f.success)
                if (f.error) danger(f.error)
            },
            { immediate: true, deep: true },
        )
    }

    onBeforeUnmount(() => timers.forEach((t) => clearTimeout(t)))

    return { toasts, push, dismiss, success, info, danger }
}

export default useToasts
