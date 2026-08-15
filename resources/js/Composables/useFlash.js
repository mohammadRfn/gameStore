import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * دریافت پیام‌های flash از Inertia shared data
 * در HandleInertiaRequests باید share بشه:
 *   'flash' => ['success' => session('success'), 'error' => session('error')]
 */
export function useFlash() {
    const page = usePage()

    const success = computed(() => page.props.flash?.success ?? null)
    const error = computed(() => page.props.flash?.error ?? null)

    return { success, error }
}
