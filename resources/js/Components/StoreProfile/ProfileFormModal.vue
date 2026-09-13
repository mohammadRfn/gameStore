<script setup>
/**
 * ProfileFormModal — فرم ساخت/ویرایش پروفایل فروشگاه
 * مسیر: resources/js/Components/StoreProfile/ProfileFormModal.vue
 *
 * ورودی‌ها:
 *   open    : boolean
 *   profile : StoreProfile|null  (null = ساخت جدید)
 *
 * تمام فیلدها دقیقاً مطابق StoreProfileRequest بک‌اند هستند.
 * از useForm اینرسیا برای multipart (آپلود لوگو/کاور) و مدیریت خطاها استفاده می‌شود.
 */
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
    X,
    Building2,
    PhoneCall,
    MapPinned,
    UserRound,
    Image as ImageIcon,
    Upload,
} from 'lucide-vue-next'

import GsToggle from '@/Components/Settings/GsToggle.vue'
import GsSegmented from '@/Components/Settings/GsSegmented.vue'
import { STATUS_OPTIONS } from '@/Composables/useStoreProfileApi'

const props = defineProps({
    open: { type: Boolean, default: false },
    profile: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const DEFAULTS = {
    legal_name: '',
    brand_name: '',
    slug: '',
    phone: '',
    secondary_phone: '',
    email: '',
    website: '',
    instagram: '',
    telegram: '',
    address_street: '',
    address_city: '',
    address_province: '',
    address_postal: '',
    address_country: '',
    owner_first_name: '',
    owner_last_name: '',
    owner_national_id: '',
    owner_phone: '',
    owner_email: '',
    is_primary: false,
    status: 'active',
    logo: null,
    cover: null,
    remove_logo: false,
    remove_cover: false,
}

const form = useForm({ ...DEFAULTS })

const isEdit = computed(() => Boolean(props.profile))

const title = computed(() => (isEdit.value ? 'ویرایش پروفایل فروشگاه' : 'پروفایل جدید فروشگاه'))

const existingLogo = computed(() => (props.profile?.logo_path ? `/storage/${props.profile.logo_path}` : ''))
const existingCover = computed(() => (props.profile?.cover_path ? `/storage/${props.profile.cover_path}` : ''))

function syncFromProfile() {
    const p = props.profile || {}
    for (const key of Object.keys(DEFAULTS)) {
        if (key === 'logo' || key === 'cover' || key === 'remove_logo' || key === 'remove_cover') {
            form[key] = DEFAULTS[key]
            continue
        }
        form[key] = p[key] ?? DEFAULTS[key]
    }
    logoPreview.value = ''
    coverPreview.value = ''
    form.clearErrors()
}

watch(
    () => props.open,
    (open) => {
        if (open) syncFromProfile()
    },
)

const logoPreview = ref('')
const coverPreview = ref('')

function readAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(reader.result)
        reader.onerror = reject
        reader.readAsDataURL(file)
    })
}

function onLogoChange(e) {
    const file = e.target.files?.[0] || null
    form.logo = file
    form.remove_logo = false
    logoPreview.value = ''
    if (file) readAsDataUrl(file).then((url) => { logoPreview.value = url })
}

function onCoverChange(e) {
    const file = e.target.files?.[0] || null
    form.cover = file
    form.remove_cover = false
    coverPreview.value = ''
    if (file) readAsDataUrl(file).then((url) => { coverPreview.value = url })
}

function submit() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    }

    // رشته‌های خالی رو قبل از ارسال به null تبدیل می‌کنیم
    // چون قانون nullable در Laravel فقط روی null اثر می‌کنه، نه ''
    const emptyToNull = (data) => {
        const out = { ...data }
        for (const key of Object.keys(out)) {
            if (out[key] === '') out[key] = null
        }
        return out
    }

    if (isEdit.value) {
        form.transform(emptyToNull).put(route('store-profiles.update', props.profile.id), options)
    } else {
        form.transform(emptyToNull).post(route('store-profiles.store'), options)
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="sp-modal">
            <div v-if="open" class="sp-modal-mask" @click.self="emit('close')">
                <div class="sp-modal">
                    <div class="sp-modal__head">
                        <p class="sp-modal__title">{{ title }}</p>
                        <button type="button" class="sp-btn sp-btn--ghost" aria-label="بستن" @click="emit('close')">
                            <X :size="18" />
                        </button>
                    </div>

                    <form class="sp-modal__body" @submit.prevent="submit">
                        <!-- هویت -->
                        <p class="sp-legend">
                            <span class="sp-legend__icon">
                                <Building2 :size="16" />
                            </span>
                            هویت فروشگاه
                        </p>
                        <div class="sp-form-grid">
                            <div class="sp-field">
                                <label class="sp-field__label">نام حقوقی <em>*</em></label>
                                <input v-model="form.legal_name" class="sp-input"
                                    :class="{ 'has-error': form.errors.legal_name }" />
                                <p v-if="form.errors.legal_name" class="sp-field__error">{{ form.errors.legal_name }}
                                </p>
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">نام تجاری</label>
                                <input v-model="form.brand_name" class="sp-input" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">شناسه (slug) <em>*</em></label>
                                <input v-model="form.slug" class="sp-input" dir="ltr"
                                    :class="{ 'has-error': form.errors.slug }" />
                                <p v-if="form.errors.slug" class="sp-field__error">{{ form.errors.slug }}</p>
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">وضعیت</label>
                                <GsSegmented v-model="form.status" :options="STATUS_OPTIONS" />
                            </div>
                        </div>

                        <div class="st-row">
                            <div>
                                <p class="st-row__title">پروفایل اصلی</p>
                                <p class="st-row__desc">این پروفایل به‌عنوان پروفایل پیش‌فرض فاکتورها استفاده شود.</p>
                            </div>
                            <GsToggle v-model="form.is_primary" />
                        </div>

                        <!-- تماس -->
                        <p class="sp-legend">
                            <span class="sp-legend__icon">
                                <PhoneCall :size="16" />
                            </span>
                            تماس و شبکه‌های اجتماعی
                        </p>
                        <div class="sp-form-grid">
                            <div class="sp-field">
                                <label class="sp-field__label">تلفن</label>
                                <input v-model="form.phone" class="sp-input" dir="ltr" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">تلفن دوم</label>
                                <input v-model="form.secondary_phone" class="sp-input" dir="ltr" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">ایمیل</label>
                                <input v-model="form.email" class="sp-input" dir="ltr"
                                    :class="{ 'has-error': form.errors.email }" />
                                <p v-if="form.errors.email" class="sp-field__error">{{ form.errors.email }}</p>
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">وب‌سایت</label>
                                <input v-model="form.website" class="sp-input" dir="ltr"
                                    :class="{ 'has-error': form.errors.website }" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">اینستاگرام</label>
                                <input v-model="form.instagram" class="sp-input" dir="ltr" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">تلگرام</label>
                                <input v-model="form.telegram" class="sp-input" dir="ltr" />
                            </div>
                        </div>

                        <!-- آدرس -->
                        <p class="sp-legend">
                            <span class="sp-legend__icon">
                                <MapPinned :size="16" />
                            </span>
                            آدرس
                        </p>
                        <div class="sp-form-grid">
                            <div class="sp-field sp-field--full">
                                <label class="sp-field__label">خیابان</label>
                                <input v-model="form.address_street" class="sp-input" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">شهر</label>
                                <input v-model="form.address_city" class="sp-input" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">استان</label>
                                <input v-model="form.address_province" class="sp-input" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">کد پستی</label>
                                <input v-model="form.address_postal" class="sp-input" dir="ltr" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">کشور</label>
                                <input v-model="form.address_country" class="sp-input" />
                            </div>
                        </div>

                        <!-- مالک -->
                        <p class="sp-legend">
                            <span class="sp-legend__icon">
                                <UserRound :size="16" />
                            </span>
                            مالک
                        </p>
                        <div class="sp-form-grid">
                            <div class="sp-field">
                                <label class="sp-field__label">نام</label>
                                <input v-model="form.owner_first_name" class="sp-input" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">نام خانوادگی</label>
                                <input v-model="form.owner_last_name" class="sp-input" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">کد ملی</label>
                                <input v-model="form.owner_national_id" class="sp-input" dir="ltr" />
                            </div>
                            <div class="sp-field">
                                <label class="sp-field__label">تلفن مالک</label>
                                <input v-model="form.owner_phone" class="sp-input" dir="ltr" />
                            </div>
                            <div class="sp-field sp-field--full">
                                <label class="sp-field__label">ایمیل مالک</label>
                                <input v-model="form.owner_email" class="sp-input" dir="ltr" />
                            </div>
                        </div>


                        <p class="sp-legend">
                            <span class="sp-legend__icon">
                                <ImageIcon :size="16" />
                            </span>
                            برندینگ
                        </p>
                        <div class="sp-form-grid">
                            <div class="sp-field">
                                <label class="sp-field__label">لوگو</label>
                                <label class="sp-upload">
                                    <img v-if="logoPreview || existingLogo" class="sp-upload__preview"
                                        :src="logoPreview || existingLogo" alt="" />
                                    <span v-else class="sp-upload__preview"
                                        style="display:grid; place-items:center; color:var(--gs-text-muted)">
                                        <Upload :size="18" />
                                    </span>
                                    <div>
                                        <p style="font-size:0.78rem; color:var(--gs-text-primary)">انتخاب فایل لوگو</p>
                                        <p class="cm-hint">jpg / png / webp — حداکثر ۲MB</p>
                                    </div>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" hidden
                                        @change="onLogoChange" />
                                </label>
                                <label v-if="existingLogo" class="cm-check" style="margin-top:0.4rem">
                                    <input v-model="form.remove_logo" type="checkbox" />
                                    حذف لوگوی فعلی
                                </label>
                                <p v-if="form.errors.logo" class="sp-field__error">{{ form.errors.logo }}</p>
                            </div>

                            <div class="sp-field">
                                <label class="sp-field__label">کاور</label>
                                <label class="sp-upload">
                                    <img v-if="coverPreview || existingCover" class="sp-upload__preview"
                                        :src="coverPreview || existingCover" alt="" />
                                    <span v-else class="sp-upload__preview"
                                        style="display:grid; place-items:center; color:var(--gs-text-muted)">
                                        <Upload :size="18" />
                                    </span>
                                    <div>
                                        <p style="font-size:0.78rem; color:var(--gs-text-primary)">انتخاب تصویر کاور</p>
                                        <p class="cm-hint">jpg / png / webp — حداکثر ۴MB</p>
                                    </div>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" hidden
                                        @change="onCoverChange" />
                                </label>
                                <label v-if="existingCover" class="cm-check" style="margin-top:0.4rem">
                                    <input v-model="form.remove_cover" type="checkbox" />
                                    حذف کاور فعلی
                                </label>
                                <p v-if="form.errors.cover" class="sp-field__error">{{ form.errors.cover }}</p>
                            </div>
                        </div>
                    </form>
                    <p v-if="form.errors.profile" class="sp-field__error" style="margin-bottom:0.8rem">
                        {{ form.errors.profile }}
                    </p>
                    <div class="sp-modal__foot">
                        <button type="button" class="sp-btn sp-btn--ghost" @click="emit('close')">
                            انصراف
                        </button>
                        <button type="button" class="sp-btn sp-btn--gold" :disabled="form.processing" @click="submit">
                            {{ form.processing ? 'در حال ذخیره…' : isEdit ? 'ذخیرهٔ تغییرات' : 'ایجاد پروفایل' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
