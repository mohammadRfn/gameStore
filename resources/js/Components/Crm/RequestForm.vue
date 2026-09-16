<script setup>
/**
 * RequestForm — فرم مشترک ثبت/ویرایش درخواست + پیش‌نمایش زندهٔ «تیکت» + نوار ذخیره
 * مسیر: resources/js/Components/Crm/RequestForm.vue
 *
 * کلیدهای فرم دقیقاً مطابق Modules\Request\Http\Requests\RequestRequest:
 *   customer_name (required) | customer_id (nullable, exists) | category_ids[] (required) | description (required)
 *
 * استفاده:
 *   <RequestForm :form="form" :customers="customers" :categories="categories" mode="create"
 *                :cancel-href="route('requests.index')" @submit="submit" />
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { AlertCircle, Check, FileText, Info, Layers, RefreshCw, RotateCcw, Sparkles, Tag, User, UserRound } from 'lucide-vue-next'
import { vReveal } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'
import { avatarHue, initials } from '@/Utils/crm'
import CrmField from '@/Components/Crm/CrmField.vue'
import CrmPanel from '@/Components/Crm/CrmPanel.vue'
import CrmStatusChip from '@/Components/Crm/CrmStatusChip.vue'

const props = defineProps({
    form: { type: Object, required: true },
    customers: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    mode: { type: String, default: 'create' }, // create | edit
    /** وضعیت فعلی (فقط برای پیش‌نمایش در حالت ویرایش) */
    status: { type: String, default: 'pending' },
    cancelHref: { type: String, required: true },
})

const emit = defineEmits(['submit'])

const isEdit = computed(() => props.mode === 'edit')
const dirty = computed(() => props.form.isDirty)
const submitLabel = computed(() => (isEdit.value ? 'ذخیرهٔ تغییرات' : 'ثبت درخواست'))

/* انتخاب مشتری → پرکردن خودکار نام */
function onCustomerChange() {
    const id = props.form.customer_id
    if (id === '' || id === null || id === undefined) return
    const found = props.customers.find((c) => String(c.id) === String(id))
    if (found) props.form.customer_name = found.name
}

/* دسته‌بندی‌های چندانتخابی */
function isOn(id) {
    return props.form.category_ids.some((v) => String(v) === String(id))
}
function toggle(id) {
    const i = props.form.category_ids.findIndex((v) => String(v) === String(id))
    if (i > -1) props.form.category_ids.splice(i, 1)
    else props.form.category_ids.push(id)
}

const chosenCats = computed(() => props.categories.filter((c) => isOn(c.id)))
const previewName = computed(() => props.form.customer_name?.trim() || 'نام مشتری')
const catError = computed(() => props.form.errors.category_ids || props.form.errors['category_ids.0'] || '')
</script>

<template>
    <form class="crm-grid-side" @submit.prevent="emit('submit')">
        <!-- ---------- فرم ---------- -->
        <CrmPanel
            :title="isEdit ? 'ویرایش درخواست' : 'درخواست جدید'"
            desc="نام مشتری، حداقل یک دسته‌بندی و شرح مشکل الزامی است"
            :icon="FileText"
            still
            :delay="60"
        >
            <div class="crm-form">
                <div class="crm-form__grid">
                    <CrmField
                        v-model="form.customer_id"
                        type="select"
                        label="انتخاب از مشتریان ثبت‌شده"
                        :icon="UserRound"
                        hint="اختیاری — با انتخاب، نام مشتری خودکار پر می‌شود"
                        @change="onCustomerChange"
                    >
                        <option value="">— مشتری موردی / بدون پرونده —</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </CrmField>

                    <CrmField
                        v-model="form.customer_name"
                        label="نام مشتری"
                        :icon="User"
                        required
                        placeholder="نام کامل مشتری"
                        :error="form.errors.customer_name"
                        :maxlength="255"
                    />

                    <!-- دسته‌بندی‌ها -->
                    <div class="crm-field is-full" :class="{ 'is-invalid': !!catError }">
                        <span class="crm-field__label">
                            <Tag :size="14" /> دسته‌بندی خدمت / قطعه <b class="crm-field__req">*</b>
                            <em v-if="form.category_ids.length" class="crm-seg__count" style="color: var(--gs-gold); font-style: normal">
                                {{ faInt(form.category_ids.length) }}
                            </em>
                        </span>

                        <div v-if="categories.length" class="crm-choices">
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                type="button"
                                class="crm-choice"
                                :class="{ 'is-on': isOn(cat.id) }"
                                :aria-pressed="isOn(cat.id)"
                                @click="toggle(cat.id)"
                            >
                                <span class="crm-choice__check"><Check v-if="isOn(cat.id)" :size="11" /></span>
                                {{ cat.name }}
                            </button>
                        </div>
                        <p v-else class="crm-field__hint">دسته‌بندی‌ای تعریف نشده است؛ ابتدا از بخش «دسته‌بندی‌ها» اضافه کنید.</p>

                        <Transition name="crm-err">
                            <span v-if="catError" class="crm-field__error"><AlertCircle :size="13" /> {{ catError }}</span>
                        </Transition>
                    </div>

                    <CrmField
                        v-model="form.description"
                        type="textarea"
                        label="شرح مشکل / درخواست"
                        :icon="FileText"
                        required
                        :rows="5"
                        :maxlength="2000"
                        placeholder="مدل دستگاه، شرح ایراد، لوازم همراه، درخواست مشتری…"
                        :error="form.errors.description"
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

        <!-- ---------- پیش‌نمایش تیکت ---------- -->
        <aside v-reveal="{ delay: 140 }" class="a3d-holo crm-preview">
            <p class="crm-preview__label"><Sparkles :size="13" /> پیش‌نمایش تیکت</p>

            <div class="crm-person__head">
                <span class="crm-avatar crm-avatar--ring" :style="{ '--hue': avatarHue(previewName) }">
                    {{ initials(previewName) }}
                </span>
                <div style="min-width: 0">
                    <p class="crm-person__name">{{ previewName }}</p>
                    <p class="crm-person__id">{{ form.customer_id ? 'مشتری دارای پرونده' : 'مشتری موردی' }}</p>
                </div>
                <CrmStatusChip :status="isEdit ? status : 'pending'" sm style="margin-inline-start: auto" />
            </div>

            <div class="crm-tags" style="margin-top: 1rem">
                <span v-for="c in chosenCats" :key="c.id" class="crm-tag"><Layers :size="11" /> {{ c.name }}</span>
                <span v-if="!chosenCats.length" class="crm-person__id">هنوز دسته‌بندی‌ای انتخاب نشده</span>
            </div>

            <div class="crm-preview__desc">{{ form.description || 'شرح مشکل این‌جا نمایش داده می‌شود…' }}</div>

            <div class="crm-preview__tip">
                <Info :size="15" style="flex: none; margin-top: 0.15rem" />
                <span>
                    وضعیت درخواست هنگام ثبت «در انتظار» است و با صدور/پرداخت فاکتور به‌صورت خودکار به‌روز می‌شود.
                </span>
            </div>
        </aside>

        <!-- ---------- نوار ذخیرهٔ شناور ---------- -->
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
