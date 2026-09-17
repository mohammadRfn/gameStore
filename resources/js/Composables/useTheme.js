import { ref, computed } from 'vue'

const STORAGE_KEY = 'gs-theme'
const VALID = ['light', 'dark', 'system']

function systemPrefersDark() {
    try {
        return window.matchMedia('(prefers-color-scheme: dark)').matches
    } catch (e) {
        return true
    }
}

function readInitial() {
    try {
        const v = localStorage.getItem(STORAGE_KEY)
        if (VALID.includes(v)) return v
    } catch (e) { /* ignore */ }
    return 'dark'
}

// وضعیت اشتراکی میان همهٔ کامپوننت‌ها ('light' | 'dark' | 'system')
const mode = ref(readInitial())

function resolvedDark() {
    return mode.value === 'system' ? systemPrefersDark() : mode.value !== 'light'
}

function apply(root = document.documentElement) {
    const dark = resolvedDark()
    root.classList.toggle('light', !dark)
    root.setAttribute('data-theme', dark ? 'dark' : 'light')
    try {
        localStorage.setItem(STORAGE_KEY, mode.value)
    } catch (e) { /* ignore */ }
    window.dispatchEvent(new CustomEvent('gs-theme-changed', { detail: { dark } }))
}

if (typeof document !== 'undefined') {
    apply()
    try {
        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => { if (mode.value === 'system') apply() })
    } catch (e) { /* ignore */ }
}

export function useTheme() {
    const isDark = computed(() => resolvedDark())

    function toggle() {
        mode.value = resolvedDark() ? 'light' : 'dark'
        apply()
    }

    function set(value) {
        if (!VALID.includes(value)) return
        mode.value = value
        apply()
    }

    return { isDark, toggle, set, mode }
}