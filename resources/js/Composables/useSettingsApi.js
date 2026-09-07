/**
 * useSettingsApi
 * ============================================================================
 * پل ارتباطی فرانت‌اند (Vue/Inertia) با ماژول تنظیمات لاراول.
 *
 * مسیر فایل: resources/js/Composables/useSettingsApi.js
 *
 * این composable دقیقاً مطابق روت‌های ماژول Setting نوشته شده است
 * (فایل: Modules/Setting/routes/web.php — prefix «settings»، middleware «auth»):
 *
 *   GET    /settings                       → index          (values + meta + groups)
 *   GET    /settings/meta                  → meta
 *   GET    /settings/group/{group}         → byGroup
 *   GET    /settings/{key}                 → show
 *   PUT    /settings                       → update          { settings: {key:value} }
 *   POST   /settings/bulk                  → update (alias)
 *   DELETE /settings/{key}                 → reset
 *   POST   /settings/reset-group/{group}   → resetGroup
 *   POST   /settings/reset-all             → resetAll
 *   GET    /settings/export                → export
 *   POST   /settings/import                → import          { settings: {...} }
 *   POST   /settings/test-printer          → testPrinter
 *   POST   /settings/trigger-backup        → triggerBackup
 *   POST   /settings/check-updates         → checkForUpdates
 *   POST   /settings/install-update        → installUpdate
 *   GET    /settings/restart-status        → restartStatus
 *   GET    /settings/system-printers       → systemPrinters
 *   POST   /settings/acknowledge-restart   → acknowledgeRestart
 *
 * همهٔ پاسخ‌ها ساختار { ok: boolean, ... } دارند و از axios سراسری لاراول
 * (که هدر X-CSRF و session را مدیریت می‌کند) استفاده می‌شود.
 */
import axios from 'axios'

const BASE = '/settings'

/** ابزار کوچک برای یکدست‌سازی خطاها */
function normalizeError(error) {
    const res = error?.response
    return {
        ok: false,
        status: res?.status ?? 0,
        message:
            res?.data?.message ||
            error?.message ||
            'ارتباط با سرور برقرار نشد.',
        errors: res?.data?.errors ?? null,
        raw: error,
    }
}

export function useSettingsApi() {
    /* ------------------------------------------------------------------ */
    /* خواندن                                                              */
    /* ------------------------------------------------------------------ */

    /** همهٔ تنظیمات + متادیتا + گروه‌ها */
    async function fetchAll() {
        try {
            const { data } = await axios.get(BASE)
            return data // { ok, values, meta, groups }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /** فقط متادیتای گروه‌بندی‌شده */
    async function fetchMeta() {
        try {
            const { data } = await axios.get(`${BASE}/meta`)
            return data // { ok, groups: { [group]: { label, icon, items } } }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /** تنظیمات یک گروه خاص */
    async function fetchGroup(group) {
        try {
            const { data } = await axios.get(`${BASE}/group/${group}`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /* ------------------------------------------------------------------ */
    /* نوشتن                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * به‌روزرسانی مجموعه‌ای از تنظیمات.
     * @param {Record<string, unknown>} changes  نگاشت key=>value
     */
    async function update(changes) {
        try {
            const { data } = await axios.put(BASE, { settings: changes })
            return data // { ok, message, changed }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /** ریست یک کلید به مقدار پیش‌فرض */
    async function resetKey(key) {
        try {
            const { data } = await axios.delete(`${BASE}/${key}`)
            return data // { ok, message, default }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /** ریست کامل یک گروه */
    async function resetGroup(group) {
        try {
            const { data } = await axios.post(`${BASE}/reset-group/${group}`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /** ریست همهٔ تنظیمات */
    async function resetAll() {
        try {
            const { data } = await axios.post(`${BASE}/reset-all`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /* ------------------------------------------------------------------ */
    /* Import / Export                                                     */
    /* ------------------------------------------------------------------ */

    async function exportSettings() {
        try {
            const { data } = await axios.get(`${BASE}/export`)
            return data // { ok, exported_at, settings }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    async function importSettings(settings) {
        try {
            const { data } = await axios.post(`${BASE}/import`, { settings })
            return data // { ok, message, count }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    /* ------------------------------------------------------------------ */
    /* عملیات دسکتاپ                                                       */
    /* ------------------------------------------------------------------ */

    async function testPrinter() {
        try {
            const { data } = await axios.post(`${BASE}/test-printer`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    async function triggerBackup() {
        try {
            const { data } = await axios.post(`${BASE}/trigger-backup`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    async function checkForUpdates() {
        try {
            const { data } = await axios.post(`${BASE}/check-updates`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    async function installUpdate() {
        try {
            const { data } = await axios.post(`${BASE}/install-update`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    async function restartStatus() {
        try {
            const { data } = await axios.get(`${BASE}/restart-status`)
            return data
        } catch (e) {
            throw normalizeError(e)
        }
    }

    async function systemPrinters() {
        try {
            const { data } = await axios.get(`${BASE}/system-printers`)
            return data // { ok, printers }
        } catch (e) {
            throw normalizeError(e)
        }
    }

    return {
        fetchAll,
        fetchMeta,
        fetchGroup,
        update,
        resetKey,
        resetGroup,
        resetAll,
        exportSettings,
        importSettings,
        testPrinter,
        triggerBackup,
        checkForUpdates,
        installUpdate,
        restartStatus,
        systemPrinters,
    }
}

export default useSettingsApi
