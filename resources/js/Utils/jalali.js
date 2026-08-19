/**
 * resources/js/Utils/jalali.js
 * -----------------------------------------------------------
 * تبدیل هر نوع برچسب تاریخ (میلادی، ISO، کوتاه، انگلیسی، تایم‌استمپ)
 * به تاریخ شمسی با ارقام فارسی.
 *
 * جاوااسکریپت خالص است؛ در هر کامپوننت Vue، composable یا فایل .js
 * قابل import است:
 *     import { faLabel, jalaliFull } from '@/Utils/jalali'
 */
import { toJalaali } from 'jalaali-js'

const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']

export const JALALI_MONTHS = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
]

const EN_MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']

const WEEKDAYS = {
    sat: 'شنبه', sun: 'یکشنبه', mon: 'دوشنبه', tue: 'سه‌شنبه',
    wed: 'چهارشنبه', thu: 'پنجشنبه', fri: 'جمعه',
}

/** ارقام فارسی/عربی → لاتین (تا رجکس‌ها کار کنند) */
function toLatinDigits(s) {
    return String(s)
        .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 1776))
        .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 1632))
}

/** هر رشته/عدد را با ارقام فارسی برمی‌گرداند */
export function faDigits(v) {
    return String(v ?? '')
        .replace(/[0-9]/g, (d) => FA_DIGITS[Number(d)])
        .replace(/,/g, '٬')
}

function jal(j) {
    return { type: 'date', jy: j.jy, jm: j.jm, jd: j.jd }
}

function fromDate(dt) {
    return jal(toJalaali(dt.getFullYear(), dt.getMonth() + 1, dt.getDate()))
}

function fromTimestamp(ms) {
    const dt = new Date(ms)
    return isNaN(dt.getTime()) ? null : fromDate(dt)
}

/**
 * تشخیص و تجزیهٔ هر برچسب تاریخ‌مانند.
 * خروجی: { type:'date', jy, jm, jd } | { type:'month', jy, jm } | { type:'weekday', text } | null
 */
export function parseAnyDate(input) {
    if (input === null || input === undefined || input === '') return null
    if (input instanceof Date) return isNaN(input.getTime()) ? null : fromDate(input)
    if (typeof input === 'number') {
        if (input > 1e11) return fromTimestamp(input)       // میلی‌ثانیه
        if (input > 1e9) return fromTimestamp(input * 1000) // ثانیه
        return null
    }

    let s = toLatinDigits(input).trim()
    if (!s) return null

    // نام روز هفته: Sat / Saturday / Mon …
    if (!/\d/.test(s)) {
        const key = s.toLowerCase().slice(0, 3)
        return WEEKDAYS[key] ? { type: 'weekday', text: WEEKDAYS[key] } : null
    }

    // حذف بخش ساعت از ISO یا datetime
    s = s.replace(/[T ]\d{1,2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/i, '').trim()

    let m

    // 2025-08-12 | 2025/08/12 | 1404-05-21
    if ((m = s.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/))) {
        const y = +m[1], mo = +m[2], d = +m[3]
        if (mo < 1 || mo > 12 || d < 1 || d > 31) return null
        if (y >= 1500) { try { return jal(toJalaali(y, mo, d)) } catch { return null } }
        return { type: 'date', jy: y, jm: mo, jd: d } // از قبل شمسی بوده
    }

    // 12-08-2025 | 12/08/2025  (روز/ماه/سال)
    if ((m = s.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/))) {
        let d = +m[1], mo = +m[2]
        const y = +m[3]
        if (d > 12 && mo <= 12) { /* d/m/Y */ } else if (mo > 12) { const t = d; d = mo; mo = t }
        if (mo < 1 || mo > 12) return null
        if (y >= 1500) { try { return jal(toJalaali(y, mo, d)) } catch { return null } }
        return { type: 'date', jy: y, jm: mo, jd: d }
    }

    // 2025-08 (ماهانه)
    if ((m = s.match(/^(\d{4})[-/.](\d{1,2})$/))) {
        const y = +m[1], mo = +m[2]
        if (mo < 1 || mo > 12) return null
        if (y >= 1500) {
            try { const j = toJalaali(y, mo, 15); return { type: 'month', jy: j.jy, jm: j.jm } } catch { return null }
        }
        return { type: 'month', jy: y, jm: mo }
    }

    // 08-12 | 08/12  (بدون سال ← سال جاری میلادی)
    if ((m = s.match(/^(\d{1,2})[-/.](\d{1,2})$/))) {
        let mo = +m[1], d = +m[2]
        if (mo > 12 && d <= 12) { const t = mo; mo = d; d = t }
        if (mo < 1 || mo > 12 || d < 1 || d > 31) return null
        try { return jal(toJalaali(new Date().getFullYear(), mo, d)) } catch { return null }
    }

    // Aug 12 | Aug 12, 2025 | August 12 2025
    if ((m = s.match(/^([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:[,\s]+(\d{4}))?$/))) {
        const mo = EN_MONTHS.indexOf(m[1].slice(0, 3).toLowerCase()) + 1
        if (mo) {
            try { return jal(toJalaali(m[3] ? +m[3] : new Date().getFullYear(), mo, +m[2])) } catch { return null }
        }
    }

    // 12 Aug | 12 Aug 2025
    if ((m = s.match(/^(\d{1,2})\s+([A-Za-z]{3,9})\.?(?:\s+(\d{4}))?$/))) {
        const mo = EN_MONTHS.indexOf(m[2].slice(0, 3).toLowerCase()) + 1
        if (mo) {
            try { return jal(toJalaali(m[3] ? +m[3] : new Date().getFullYear(), mo, +m[1])) } catch { return null }
        }
    }

    // Mon 12 / Sat 03  ← نام روز + عدد
    if ((m = s.match(/^([A-Za-z]{3,9})\.?\s+(\d{1,2})$/))) {
        const key = m[1].toLowerCase().slice(0, 3)
        if (WEEKDAYS[key]) return { type: 'weekday', text: WEEKDAYS[key] + ' ' + faDigits(m[2]) }
    }

    // تایم‌استمپ رشته‌ای
    if (/^\d{13}$/.test(s)) return fromTimestamp(+s)
    if (/^\d{10}$/.test(s)) return fromTimestamp(+s * 1000)

    return null
}

/** «۲۱ مرداد» — مناسب تیک محور نمودار */
export function jalaliTick(v) {
    const p = parseAnyDate(v)
    if (!p) return null
    if (p.type === 'weekday') return p.text
    if (p.type === 'month') return JALALI_MONTHS[p.jm - 1] + ' ' + faDigits(p.jy)
    return faDigits(p.jd) + ' ' + JALALI_MONTHS[p.jm - 1]
}

/** «۲۱ مرداد ۱۴۰۴» — مناسب تولتیپ و جدول */
export function jalaliFull(v) {
    const p = parseAnyDate(v)
    if (!p) return null
    if (p.type === 'weekday') return p.text
    if (p.type === 'month') return JALALI_MONTHS[p.jm - 1] + ' ' + faDigits(p.jy)
    return faDigits(p.jd) + ' ' + JALALI_MONTHS[p.jm - 1] + ' ' + faDigits(p.jy)
}

/** «۱۴۰۴/۰۵/۲۱» */
export function jalaliNumeric(v) {
    const p = parseAnyDate(v)
    if (!p || p.type === 'weekday') return null
    const pad = (n) => faDigits(String(n).padStart(2, '0'))
    return p.type === 'month'
        ? faDigits(p.jy) + '/' + pad(p.jm)
        : faDigits(p.jy) + '/' + pad(p.jm) + '/' + pad(p.jd)
}

/**
 * ورودی اصلی و امن: همیشه خروجی می‌دهد.
 * اگر تاریخ بود → شمسی، وگرنه همان متن با ارقام فارسی.
 */
export function faLabel(v, { long = false } = {}) {
    const t = long ? jalaliFull(v) : jalaliTick(v)
    return t !== null && t !== undefined ? t : faDigits(v)
}

/** آیا این برچسب تاریخ است؟ */
export function isDateLabel(v) {
    return parseAnyDate(v) !== null
}
