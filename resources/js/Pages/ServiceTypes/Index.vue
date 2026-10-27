<script setup>
/**
 * مدیریت نوع‌های سرویس — ساخت، ویرایش، فعال/غیرفعال و حذف
 * مسیر: resources/js/Pages/ServiceTypes/Index.vue
 * نوعی که روی سرویس‌ها استفاده شده حذف نمی‌شود (برای حفظ سوابق)؛ به‌جایش «غیرفعال» می‌شود
 * تا در فرم سرویس‌های جدید نیاید.
 */
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ArrowRight, Check, Pencil, Plus, Power, Trash2, Wrench, X } from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import MoneyInput from '@/Components/MoneyInput.vue'
import CrmConfirmDialog from '@/Components/Crm/CrmConfirmDialog.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'
import { useToasts } from '@/Composables/useToasts'
import { faInt, money } from '@/Utils/format'

const props = defineProps({
    serviceTypes: { type: Array, default: () => [] },
})

const { toasts, dismiss, danger } = useToasts({ flash: true })

/* ---------- ساخت ---------- */
const createForm = useForm({ name: '', base_price: '', description: '', is_active: true })

function create() {
    createForm
        .transform((d) => ({ ...d, base_price: d.base_price === '' ? null : d.base_price }))
        .post(route('service-types.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
            onError: () => danger('اطلاعات فرم را بررسی کنید.'),
        })
}

/* ---------- ویرایش درجا ---------- */
const editingId = ref(null)
const editForm = useForm({ name: '', base_price: '', description: '', is_active: true })

function startEdit(t) {
    editingId.value = t.id
    editForm.clearErrors()
    editForm.name = t.name
    editForm.base_price = t.base_price === null ? '' : Number(t.base_price)
    editForm.description = t.description ?? ''
    editForm.is_active = !!t.is_active
}

function saveEdit() {
    editForm
        .transform((d) => ({ ...d, base_price: d.base_price === '' ? null : d.base_price }))
        .put(route('service-types.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => (editingId.value = null),
            onError: () => danger('اطلاعات فرم را بررسی کنید.'),
        })
}

/* ---------- فعال / غیرفعال ---------- */
function toggleActive(t) {
    router.put(
        route('service-types.update', t.id),
        {
            name: t.name,
            base_price: t.base_price === null ? null : Number(t.base_price),
            description: t.description,
            is_active: !t.is_active,
        },
        { preserveScroll: true },
    )
}

/* ---------- حذف ---------- */
const confirmOpen = ref(false)
const target = ref(null)
const deleting = ref(false)

function askDelete(t) {
    target.value = t
    confirmOpen.value = true
}
function doDelete() {
    if (!target.value) return
    deleting.value = true
    router.delete(route('service-types.destroy', target.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            confirmOpen.value = false
            target.value = null
        },
    })
}
</script>

<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="blue" />

            <LuxHero
                chip="ماژول سرویس"
                chip-two="Service Types"
                title="نوع‌های سرویس"
                lead="تعریف و مدیریت خدماتی که هنگام ثبت سرویس انتخاب می‌شوند. نوع اشتباه را می‌توانید حذف یا غیرفعال کنید."
                cube="🛠️"
                satellite="⚙️"
                :stats="[
                    { label: 'کل نوع‌ها', value: faInt(serviceTypes.length) },
                    { label: 'فعال', value: faInt(serviceTypes.filter((t) => t.is_active).length) },
                ]"
            >
                <template #chip-icon><Wrench :size="13" /></template>
                <template #actions>
                    <Link :href="route('service-jobs.index')" class="a3d-btn a3d-btn--ghost">
                        <ArrowRight :size="15" /> بازگشت به سرویس‌ها
                    </Link>
                </template>
            </LuxHero>

            <!-- ساخت نوع جدید -->
            <section class="gx-panel" :style="{ '--gx-i': 1 }" style="padding: 1rem; margin-bottom: 1rem">
                <p class="gx-panel__title" style="margin-bottom: 0.7rem">نوع سرویس جدید</p>
                <div class="st-grid" @keydown.enter.prevent="create">
                    <label class="st-f">
                        <span>نام <b style="color: var(--gs-error)">*</b></span>
                        <input v-model="createForm.name" class="gs-input" maxlength="255" placeholder="مثلاً تعویض خمیر سیلیکون" />
                        <small v-if="createForm.errors.name" class="st-err">{{ createForm.errors.name }}</small>
                    </label>
                    <label class="st-f">
                        <span>قیمت پایه</span>
                        <MoneyInput v-model="createForm.base_price" size="sm" :hint="false" />
                    </label>
                    <label class="st-f st-f--wide">
                        <span>توضیح (اختیاری)</span>
                        <input v-model="createForm.description" class="gs-input" maxlength="500" />
                    </label>
                    <div class="st-f st-f--btn">
                        <button type="button" class="a3d-btn a3d-btn--gold" :disabled="createForm.processing" @click="create">
                            <Plus :size="15" /> افزودن
                        </button>
                    </div>
                </div>
            </section>

            <!-- لیست -->
            <div class="gx-panel" :style="{ '--gx-i': 2 }">
                <table v-if="serviceTypes.length" class="gs-table">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>قیمت پایه</th>
                            <th>استفاده‌شده در</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="t in serviceTypes" :key="t.id">
                            <tr v-if="editingId !== t.id">
                                <td style="font-weight: 700">
                                    {{ t.name }}
                                    <small v-if="t.description" style="display: block; font-weight: 400; color: var(--gs-text-muted)">{{ t.description }}</small>
                                </td>
                                <td class="gx-price" style="font-size: 0.85rem">{{ t.base_price ? money(t.base_price) : '—' }}</td>
                                <td style="font-size: 0.82rem; color: var(--gs-text-secondary)">
                                    {{ t.job_links_count ? faInt(t.job_links_count) + ' سرویس' : 'بدون استفاده' }}
                                </td>
                                <td>
                                    <span class="gx-status" :class="t.is_active ? 'gx-status--green' : 'gx-status--red'"><i />{{ t.is_active ? 'فعال' : 'غیرفعال' }}</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; justify-content: flex-end; flex-wrap: wrap">
                                        <button type="button" class="a3d-btn a3d-btn--sm a3d-btn--ghost" @click="toggleActive(t)">
                                            <Power :size="13" /> {{ t.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}
                                        </button>
                                        <button type="button" class="a3d-btn a3d-btn--sm" @click="startEdit(t)">
                                            <Pencil :size="13" /> ویرایش
                                        </button>
                                        <button
                                            type="button"
                                            class="a3d-btn a3d-btn--sm a3d-btn--danger"
                                            :disabled="t.job_links_count > 0"
                                            :title="t.job_links_count > 0 ? 'روی سرویس‌ها استفاده شده؛ به‌جای حذف غیرفعالش کنید' : 'حذف'"
                                            @click="askDelete(t)"
                                        >
                                            <Trash2 :size="13" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-else>
                                <td colspan="5">
                                    <div class="st-grid" @keydown.enter.prevent="saveEdit">
                                        <label class="st-f">
                                            <span>نام</span>
                                            <input v-model="editForm.name" class="gs-input" maxlength="255" />
                                            <small v-if="editForm.errors.name" class="st-err">{{ editForm.errors.name }}</small>
                                        </label>
                                        <label class="st-f">
                                            <span>قیمت پایه</span>
                                            <MoneyInput v-model="editForm.base_price" size="sm" :hint="false" />
                                        </label>
                                        <label class="st-f st-f--wide">
                                            <span>توضیح</span>
                                            <input v-model="editForm.description" class="gs-input" maxlength="500" />
                                        </label>
                                        <div class="st-f st-f--btn" style="flex-direction: row; gap: 0.4rem">
                                            <button type="button" class="a3d-btn a3d-btn--gold a3d-btn--sm" :disabled="editForm.processing" @click="saveEdit">
                                                <Check :size="13" /> ذخیره
                                            </button>
                                            <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="editingId = null">
                                                <X :size="13" /> انصراف
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div v-else class="gx-empty">
                    <span class="gx-empty__icon">🛠️</span>
                    <p class="gx-empty__title">هنوز نوع سرویسی تعریف نشده</p>
                    <p class="gx-empty__desc">از کادر بالا اولین نوع را اضافه کنید.</p>
                </div>
            </div>

            <CrmConfirmDialog
                v-model="confirmOpen"
                title="حذف نوع سرویس"
                message="این نوع سرویس حذف می‌شود و دیگر در فرم سرویس‌ها نمایش داده نخواهد شد. ادامه می‌دهید؟"
                :highlight="target?.name ?? ''"
                :loading="deleting"
                @confirm="doDelete"
            />

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>

<style scoped>
.st-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1.6fr) auto; gap: 0.7rem; align-items: end; }
.st-f { display: grid; gap: 0.25rem; min-width: 0; }
.st-f > span { font-size: 0.72rem; font-weight: 600; color: var(--gs-text-secondary); }
.st-f--btn { display: flex; align-items: flex-end; }
.st-err { color: var(--gs-error); font-size: 0.7rem; }
@media (max-width: 900px) {
    .st-grid { grid-template-columns: 1fr 1fr; }
    .st-f--wide { grid-column: 1 / -1; }
}
</style>
