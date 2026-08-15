import { ref, computed } from 'vue'

const STORAGE_KEY = 'gs-theme'

function readStored() {
    try {
        const v = localStorage.getItem(STORAGE_KEY)
        if (v === 'light') return 'light'
        if (v === 'dark') return 'dark'
    } catch (e) {
        /* ignore */
    }
    // پیش‌فرض: تیره
    return 'dark'
}

// وضعیت اشتراکی میان همهٔ کامپوننت‌ها
const theme = ref(readStored())

function apply(root = document.documentElement) {
    const dark = theme.value !== 'light'
    root.classList.toggle('light', !dark)
    root.setAttribute('data-theme', dark ? 'dark' : 'light')
    try {
        localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light')
    } catch (e) {
        /* ignore */
    }
    // اطلاع به نمودارها برای رندر مجدد با رنگ تم
    window.dispatchEvent(new CustomEvent('gs-theme-changed', { detail: { dark } }))
}

// اعمال اولیه در اولین استفاده (بدون فلش)
if (typeof document !== 'undefined') {
    apply()
}

export function useTheme() {
    const isDark = computed(() => theme.value !== 'light')

    function toggle() {
        theme.value = theme.value === 'light' ? 'dark' : 'light'
        apply()
    }

    function set(dark) {
        theme.value = dark ? 'dark' : 'light'
        apply()
    }

    return { isDark, toggle, set, theme }
}
