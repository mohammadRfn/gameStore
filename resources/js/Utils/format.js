import { toJalaali } from 'jalaali-js'

const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']

/** عدد را با ارقام فارسی و جداکنندهٔ هزارگان برمی‌گرداند */
export function fa(n) {
    const num = Number(n || 0)
    return num.toLocaleString('fa-IR')
}

/** عدد صحیح با ارقام فارسی */
export function faInt(n) {
    return Math.round(Number(n || 0)).toLocaleString('fa-IR')
}

/** مبلغ کامل به تومان */
export function money(n) {
    return faInt(n) + ' تومان'
}

/** مبلغ خلاصه: ۱٫۲ میلیارد / ۴٫۵ میلیون / ۸۰۰ هزار */
export function compactMoney(n) {
    const v = Number(n || 0)
    const abs = Math.abs(v)
    const sign = v < 0 ? '-' : ''
    if (abs >= 1e9) return sign + trimNum(abs / 1e9) + ' میلیارد'
    if (abs >= 1e6) return sign + trimNum(abs / 1e6) + ' میلیون'
    if (abs >= 1e3) return sign + trimNum(abs / 1e3) + ' هزار'
    return sign + faInt(abs)
}

function trimNum(v) {
    const s = v.toFixed(v >= 100 ? 0 : v >= 10 ? 1 : 1)
    const cleaned = parseFloat(s)
    return cleaned.toLocaleString('fa-IR', { maximumFractionDigits: 1 })
}

/** درصد با ارقام فارسی */
export function percent(n) {
    const v = Number(n || 0)
    return fa(v.toLocaleString('en-US', { maximumFractionDigits: 1 })) + '٪'
}

const JALALI_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']

/** تبدیل تاریخ میلادی ISO (YYYY-MM-DD) به جلالی '۱۴۰۳/۰۵/۲۱' */
export function jalali(iso) {
    if (!iso) return ''
    const [y, m, d] = String(iso).split('-').map(Number)
    if (!y || !m || !d) return ''
    const j = toJalaali(y, m, d)
    return fa(j.jy) + '/' + pad(j.jm) + '/' + pad(j.jd)
}

/** تبدیل تاریخ میلادی به جلالی طولانی '۲۱ مرداد ۱۴۰۳' */
export function jalaliLong(iso) {
    if (!iso) return ''
    const [y, m, d] = String(iso).split('-').map(Number)
    if (!y || !m || !d) return ''
    const j = toJalaali(y, m, d)
    return fa(j.jd) + ' ' + JALALI_MONTHS[j.jm - 1] + ' ' + fa(j.jy)
}

function pad(n) {
    return FA_DIGITS[n] ? n.toString().split('').map((d) => FA_DIGITS[d] || d).join('').padStart(2, '۰') : String(n)
}

/** برچسب بازهٔ انتخابی */
export const RANGE_LABELS = {
    today: 'امروز',
    yesterday: 'دیروز',
    week: 'هفتهٔ جاری',
    last_30: '۳۰ روز گذشته',
    month: 'ماه جاری',
    last_month: 'ماه گذشته',
    year: 'سال جاری',
    last_year: 'سال گذشته',
}

export function rangeLabel(range) {
    return RANGE_LABELS[range] || 'بازهٔ دلخواه'
}
