/**
 * ابزارهای مشترک ماژول‌های داشبورد / مشتریان / درخواست‌ها
 * مسیر: resources/js/Utils/crm.js
 * ---------------------------------------------------------------------------
 * فقط توابع خالص؛ هیچ وابستگی به DOM ندارد.
 * وضعیت‌ها دقیقاً همان ثابت‌های مدل Modules\Request\Models\Request هستند:
 *   pending | in_progress | completed | canceled
 */
import { CheckCircle2, Clock, Wrench, XCircle, BadgeCheck, Hourglass } from 'lucide-vue-next'
import { faInt } from '@/Utils/format'
import { jalaliFull, jalaliNumeric } from '@/Utils/jalali'

/* ------------------------------------------------------------------ */
/* وضعیت درخواست                                                        */
/* ------------------------------------------------------------------ */
export const REQUEST_STATUS = {
    pending: { label: 'در انتظار', long: 'در انتظار رسیدگی', tone: 'warning', icon: Clock, step: 0 },
    in_progress: { label: 'در جریان', long: 'در جریان بررسی و تعمیر', tone: 'info', icon: Wrench, step: 1, live: true },
    completed: { label: 'تکمیل شده', long: 'تکمیل و آمادهٔ تحویل', tone: 'success', icon: CheckCircle2, step: 2 },
    canceled: { label: 'لغو شده', long: 'لغو شده', tone: 'error', icon: XCircle, step: -1 },
}

export const REQUEST_STATUS_LIST = Object.keys(REQUEST_STATUS)

export function statusMeta(status) {
    return (
        REQUEST_STATUS[status] || {
            label: status || 'نامشخص',
            long: status || 'نامشخص',
            tone: 'plain',
            icon: Hourglass,
            step: -1,
        }
    )
}

/* ------------------------------------------------------------------ */
/* وضعیت تأیید فاکتور (is_confirmed ممکن است 0/1، "0"/"1" یا bool باشد) */
/* ------------------------------------------------------------------ */
export function isConfirmed(v) {
    return v === true || Number(v) === 1
}

export function invoiceMeta(inv) {
    return isConfirmed(inv?.is_confirmed)
        ? { label: 'تأیید شده', tone: 'success', icon: BadgeCheck }
        : { label: 'در انتظار تأیید', tone: 'warning', icon: Hourglass }
}

/* ------------------------------------------------------------------ */
/* پول / اعداد                                                          */
/* ------------------------------------------------------------------ */
export function money(n, { empty = '—' } = {}) {
    if (n === null || n === undefined || n === '') return empty
    return faInt(n) + ' تومان'
}

export function sum(list, key) {
    return (list || []).reduce((acc, it) => acc + Number(it?.[key] || 0), 0)
}

/* ------------------------------------------------------------------ */
/* تاریخ                                                                */
/* ------------------------------------------------------------------ */
/** «۲۱ مرداد ۱۴۰۴» — ورودی ISO/datetime لاراول را می‌پذیرد */
export function dateFa(v) {
    return jalaliFull(v) || ''
}

/** «۱۴۰۴/۰۵/۲۱» */
export function dateFaShort(v) {
    return jalaliNumeric(v) || ''
}

export function todayFa() {
    return jalaliFull(new Date()) || ''
}

/** سلام مناسب ساعت روز */
export function greeting(date = new Date()) {
    const h = date.getHours()
    if (h < 5) return 'شب‌بخیر'
    if (h < 12) return 'صبح‌بخیر'
    if (h < 17) return 'ظهر‌بخیر'
    if (h < 21) return 'عصر‌بخیر'
    return 'شب‌بخیر'
}

/* ------------------------------------------------------------------ */
/* آواتار                                                               */
/* ------------------------------------------------------------------ */
export function initials(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
    if (!parts.length) return '؟'
    return parts.length === 1 ? parts[0].slice(0, 1) : parts[0].slice(0, 1) + parts[1].slice(0, 1)
}

/** رنگ ثابت و یکتا برای هر نام (0..360) — طلایی‌ها را کمی ترجیح می‌دهد */
export function avatarHue(name) {
    const s = String(name || '')
    let h = 0
    for (let i = 0; i < s.length; i += 1) h = (h * 31 + s.charCodeAt(i)) >>> 0
    const palette = [42, 28, 200, 152, 262, 330, 190, 18, 96, 232]
    return palette[h % palette.length]
}

/* ------------------------------------------------------------------ */
/* متن                                                                  */
/* ------------------------------------------------------------------ */
export function clip(text, max = 90) {
    const t = String(text || '').trim()
    if (t.length <= max) return t
    return t.slice(0, max - 1).trimEnd() + '…'
}

export function plural(n, word) {
    return `${faInt(n)} ${word}`
}
