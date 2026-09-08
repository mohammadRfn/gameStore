/* ==========================================================================
 * GameStore · Store Profile API Layer
 * --------------------------------------------------------------------------
 * مسیر فایل: resources/js/Composables/useStoreProfileApi.js
 *
 * این composable دقیقاً مطابق روت‌های ماژول Profile نوشته شده است:
 *   Modules/Profile/routes/web.php
 *
 *   GET    /store-profiles           → index   (Inertia یا JSON)
 *   GET    /store-profiles/search    → search  (?q=)
 *   GET    /store-profiles/{id}      → show
 *   POST   /store-profiles           → store
 *   PUT    /store-profiles/{id}      → update
 *   DELETE /store-profiles/{id}      → destroy
 *   POST   /store-profiles/{id}/primary → setPrimary
 *
 * نکته: برای فرم‌های Inertia (آپلود لوگو/کاور به‌صورت multipart) از useForm
 * اینرسیا استفاده کنید؛ این لایهٔ axios برای حالت «Electron renderer / local API»
 * (زمانی که بک‌اند wantsJson را تشخیص می‌دهد) کاربرد دارد.
 * ========================================================================== */

import axios from 'axios'

const BASE = '/store-profiles'

export const PROFILE_STATUS = {
  ACTIVE: 'active',
  INACTIVE: 'inactive',
  PENDING: 'pending',
}

export const STATUS_META = {
  [PROFILE_STATUS.ACTIVE]: { label: 'فعال', icon: '✓', className: 'sp-pill sp-pill--ok' },
  [PROFILE_STATUS.INACTIVE]: { label: 'غیرفعال', icon: '⊘', className: 'sp-pill sp-pill--muted' },
  [PROFILE_STATUS.PENDING]: { label: 'در انتظار', icon: '⏳', className: 'sp-pill sp-pill--warn' },
}

export const STATUS_OPTIONS = [
  { value: PROFILE_STATUS.ACTIVE, label: 'فعال' },
  { value: PROFILE_STATUS.INACTIVE, label: 'غیرفعال' },
  { value: PROFILE_STATUS.PENDING, label: 'در انتظار' },
]

/** ماه‌های جلالی برای انتخاب «ماه شروع سال مالی» (۱..۱۲) */
export const FISCAL_MONTHS = [
  { value: 1, label: 'فروردین' },
  { value: 2, label: 'اردیبهشت' },
  { value: 3, label: 'خرداد' },
  { value: 4, label: 'تیر' },
  { value: 5, label: 'مرداد' },
  { value: 6, label: 'شهریور' },
  { value: 7, label: 'مهر' },
  { value: 8, label: 'آبان' },
  { value: 9, label: 'آذر' },
  { value: 10, label: 'دی' },
  { value: 11, label: 'بهمن' },
  { value: 12, label: 'اسفند' },
]

/** توضیح کمکی برای فیلدهای مهم فرم */
export const FIELD_DESC = {
  legal_name: 'نام رسمی ثبت‌شدهٔ کسب‌وکار (الزامی)',
  brand_name: 'نام تجاری نمایش‌داده‌شده روی فاکتورها و رسیدها',
  slug: 'شناسهٔ یکتا؛ فقط حروف لاتین، عدد، خط تیره و زیرخط',
  tax_id: 'شناسه/شمارهٔ مالیاتی',
  registration_no: 'شمارهٔ ثبت شرکت',
  founding_date: 'تاریخ تأسیس (میلادی)',
  currency_code: 'کد سه‌حرفی ارز مطابق ISO 4217 (مثل IRR)',
  currency_symbol: 'نماد نمایشی ارز (مثل تومان/﷼)',
  fiscal_year_start: 'ماه شروع سال مالی (۱ تا ۱۲)',
  receipt_footer: 'متنی که در پاورقی رسید/فاکتور چاپ می‌شود',
}

function normalizeError(error) {
  const res = error?.response
  return {
    ok: false,
    status: res?.status ?? 0,
    message: res?.data?.message || error?.message || 'ارتباط با سرور برقرار نشد.',
    errors: res?.data?.errors ?? null,
    raw: error,
  }
}

export function useStoreProfileApi() {
  /** جستجوی پروفایل‌ها */
  async function search(term = '') {
    try {
      const { data } = await axios.get(`${BASE}/search`, { params: { q: term } })
      return data // { data: [...profiles] }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** ساخت پروفایل جدید — data مطابق StoreProfileRequest */
  async function create(data) {
    try {
      const { data: res } = await axios.post(BASE, data)
      return res // { success, data: profile }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** به‌روزرسانی پروفایل */
  async function update(id, data) {
    try {
      const { data: res } = await axios.put(`${BASE}/${id}`, data)
      return res // { success, data: profile }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** حذف پروفایل */
  async function remove(id) {
    try {
      const { data: res } = await axios.delete(`${BASE}/${id}`)
      return res // { success }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  /** اصلی‌کردن یک پروفایل */
  async function setPrimary(id) {
    try {
      const { data: res } = await axios.post(`${BASE}/${id}/primary`)
      return res // { success, data: profile }
    } catch (e) {
      throw normalizeError(e)
    }
  }

  return { search, create, update, remove, setPrimary }
}

export default useStoreProfileApi
