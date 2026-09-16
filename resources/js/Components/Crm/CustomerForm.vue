<script setup>
/**
 * CustomerForm — فرم مشترک ثبت/ویرایش مشتری + پیش‌نمایش زنده + نوار ذخیرهٔ شناور
 * مسیر: resources/js/Components/Crm/CustomerForm.vue
 *
 * فیلدها دقیقاً همان کلیدهای validate شده در CustomerController@store/update:
 *   name (required) | phone | email | address | notes
 *
 * استفاده:
 *   const form = useForm({ name:'', phone:'', email:'', address:'', notes:'' })
 *   <CustomerForm :form="form" mode="create" :cancel-href="route('customers.index')" @submit="submit" />
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Check, FileText, Info, Mail, MapPin, Phone, RefreshCw, RotateCcw, Sparkles, User } from 'lucide-vue-next'
import { vReveal } from '@/Composables/useTilt'
import { avatarHue, initials } from '@/Utils/crm'
import CrmField from '@/Components/Crm/CrmField.vue'
import CrmPanel from '@/Components/Crm/CrmPanel.vue'

const props = defineProps({
    /** نمونهٔ useForm اینرسیا */
    form: { type: Object, required: true },
    mode: { type: String, default: 'create' }, // create | edit
    cancelHref: { type: String, required: true },
})

const emit = defineEmits(['submit'])

const isEdit = computed(() => props.mode === 'edit')
const submitLabel = computed(() => (isEdit.value ? 'ذخیرهٔ تغییرات' : 'ثبت مشتری'))
const dirty = computed(() => props.form.isDirty)
const previewName = computed(() => props.form.name?.trim() || 'نام مشتری')
</script>

<template>
    <form class="crm-grid-side" @submit.prevent="emit('submit')">
        <!-- ---------- فرم ---------- -->
        <CrmPanel
            :title="isEdit ? 'ویرایش اطلاعات مشتری' : 'اطلاعات مشتری جدید'"
            desc="نام الزامی است؛ بقیهٔ فیلدها اختیاری‌اند"
            :icon="User"
            still
            :delay="60"
        >
            <div class="crm-form">
                <div class="crm-form__grid">
                    <CrmField
                        v-model="form.name"
                        label="نام و نام خانوادگی"
                        :icon="User"
                        required
                        placeholder="مثال: آرشام صادقی"
                        :error="form.errors.name"
                        :maxlength="255"
                        autocomplete="name"
                    />

                    <CrmField
                        v-model="form.phone"
                        type="tel"
                        label="شمارهٔ تماس"
                        :icon="Phone"
                        dir="ltr"
                        placeholder="0912 000 0000"
                        :error="form.errors.phone"
                        :maxlength="20"
                        autocomplete="tel"
                    />

                    <CrmField
                        v-model="form.email"
                        type="email"
                        label="ایمیل"
                        :icon="Mail"
                        dir="ltr"
                        placeholder="customer@example.com"
                        :error="form.errors.email"
                        :maxlength="255"
                        autocomplete="email"
                        class="is-full"
                    />

                    <CrmField
                        v-model="form.address"
                        label="آدرس"
                        :icon="MapPin"
                        placeholder="شهر، خیابان، پلاک…"
                        :error="form.errors.address"
                        :maxlength="500"
                        autocomplete="street-address"
                        class="is-full"
                    />

                    <CrmField
                        v-model="form.notes"
                        type="textarea"
                        label="یادداشت داخلی"
                        :icon="FileText"
                        :rows="4"
                        :maxlength="1000"
                        placeholder="ترجیحات مشتری، کنسول‌های در اختیار، توضیحات تکمیلی…"
                        :error="form.errors.notes"
                        class="is-full"
                    />
                </div>

                <div class="crm-form__foot">
                    <Transition name="crm-fade" mode="out-in">
                        <span v-if="dirty" key="d" class="st-chip">
                            <span class="st-ping" style="margin-inline-end: 0.2rem" />
                            تغییرات ذخیره‌نشده
                        </span>
                        <span v-else key="c" class="st-chip st-chip--plain">
                            <Check :size="12" /> {{ isEdit ? 'بدون تغییر' : 'آمادهٔ ثبت' }}
                        </span>
                    </Transition>

                    <div style="display: flex; gap: 0.5rem">
                        <Link :href="cancelHref" class="a3d-btn a3d-btn--ghost">انصراف</Link>
                        <button type="submit" class="a3d-btn a3d-btn--gold" :disabled="form.processing || (isEdit && !dirty)">
                            <RefreshCw v-if="form.processing" :size="15" class="crm-spin" />
                            <Check v-else :size="15" />
                            {{ form.processing ? 'در حال ذخیره…' : submitLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </CrmPanel>

        <!-- ---------- پیش‌نمایش زنده ---------- -->
        <aside v-reveal="{ delay: 140 }" class="a3d-holo crm-preview">
            <p class="crm-preview__label"><Sparkles :size="13" /> پیش‌نمایش پرونده</p>

            <div class="crm-person__head">
                <span class="crm-avatar crm-avatar--lg crm-avatar--ring" :style="{ '--hue': avatarHue(previewName) }">
                    {{ initials(previewName) }}
                </span>
                <div style="min-width: 0">
                    <p class="crm-person__name" style="font-size: 1.05rem">{{ previewName }}</p>
                    <p class="crm-person__id">{{ isEdit ? 'ویرایش پرونده' : 'پروندهٔ جدید' }}</p>
                </div>
            </div>

            <div class="crm-person__lines">
                <p class="crm-line" :class="{ 'is-empty': !form.phone }" style="--crm-accent: var(--gs-info)">
                    <Phone :size="14" /><span dir="ltr">{{ form.phone || 'شمارهٔ تماس' }}</span>
                </p>
                <p class="crm-line" :class="{ 'is-empty': !form.email }" style="--crm-accent: var(--gs-gold)">
                    <Mail :size="14" /><span dir="ltr">{{ form.email || 'ایمیل' }}</span>
                </p>
                <p class="crm-line" :class="{ 'is-empty': !form.address }" style="--crm-accent: var(--gs-accent-2)">
                    <MapPin :size="14" /><span>{{ form.address || 'آدرس' }}</span>
                </p>
            </div>

            <div v-if="form.notes" class="crm-preview__desc">{{ form.notes }}</div>

            <div class="crm-preview__tip">
                <Info :size="15" style="flex: none; margin-top: 0.15rem" />
                <span>شمارهٔ تماس و ایمیل برای جستجوی سریع و صدور فاکتور استفاده می‌شوند.</span>
            </div>
        </aside>

        <!-- ---------- نوار ذخیرهٔ شناور (همان st-savebar تنظیمات) ---------- -->
        <div class="st-savebar" :class="{ 'is-shown': dirty }">
            <div class="st-savebar__inner">
                <span class="st-ping" />
                <span class="st-savebar__text">{{ isEdit ? 'تغییرات ذخیره‌نشده دارید' : 'فرم آمادهٔ ثبت است' }}</span>
                <span class="st-savebar__sep" />
                <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" :disabled="form.processing" @click="form.reset()">
                    <RotateCcw :size="13" /> بازنشانی
                </button>
                <button type="submit" class="a3d-btn a3d-btn--gold a3d-btn--sm" :disabled="form.processing">
                    <RefreshCw v-if="form.processing" :size="14" class="crm-spin" />
                    <Check v-else :size="14" />
                    {{ form.processing ? 'در حال ذخیره…' : submitLabel }}
                </button>
            </div>
        </div>
    </form>
</template>
