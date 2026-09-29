import axios from 'axios'

const ENDPOINT = '/auditlog/client-error'
const MAX_PER_SESSION = 30 // سقف گزارش در هر بار اجرای اپ
const DEDUPE_MS = 60_000 // خطای تکراری در این بازه فقط یک بار گزارش می‌شود
const IGNORED = [/ResizeObserver loop/i, /^Script error\.?$/i]

const seen = new Map()
let sent = 0
let reporting = false

export function reportError({ message, source, stack, url, component, info, line, column }) {
    const text = String(message ?? 'Unknown error').slice(0, 1000)

    if (reporting || sent >= MAX_PER_SESSION || IGNORED.some((re) => re.test(text))) return

    const key = text + '|' + String(stack ?? '').slice(0, 200)
    const now = Date.now()

    if (seen.has(key) && now - seen.get(key) < DEDUPE_MS) return

    seen.set(key, now)
    sent++
    reporting = true

    axios
        .post(
            ENDPOINT,
            {
                message: text,
                source,
                stack: stack ? String(stack).slice(0, 6000) : null,
                url: url ?? window.location.pathname,
                component: component ? String(component).slice(0, 191) : null,
                info: info ? String(info).slice(0, 191) : null,
                line,
                column,
            },
            { headers: { Accept: 'application/json' } },
        )
        .catch(() => {}) // گزارش خطا هرگز نباید خودش خطا بسازد
        .finally(() => {
            reporting = false
        })
}

export function installGlobalErrorReporting() {
    window.addEventListener('error', (e) => {
        if (!e.message) return // خطای بارگذاری تصویر/اسکریپت بدون پیام
        reportError({ source: 'window.onerror', message: e.message, stack: e.error?.stack, line: e.lineno, column: e.colno })
    })

    window.addEventListener('unhandledrejection', (e) => {
        const reason = e.reason
        if (reason?.isAxiosError) return // خطای HTTP/شبکه؛ سمت بک‌اند خودش ثبت شده است
        reportError({ source: 'unhandledrejection', message: reason?.message ?? String(reason), stack: reason?.stack })
    })
}

export function vueErrorHandler(err, instance, info) {
    reportError({
        source: 'vue',
        message: err?.message ?? String(err),
        stack: err?.stack,
        component: instance?.$options?.name ?? instance?.$options?.__name,
        info,
    })
    console.error(err)
}