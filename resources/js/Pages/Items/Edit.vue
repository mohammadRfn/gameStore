<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="gold" />

            <LuxHero
                chip="ماژول انبار"
                chip-two="Items / Edit"
                :title="'ویرایش «' + item.name + '»'"
                lead="به‌روزرسانی مشخصات، قیمت و ویژگی‌های این قلم انبار."
                cube="📦"
                satellite="✏️"
            >
                <template #chip-icon><Pencil :size="13" /></template>
                <template #actions>
                    <Link :href="route('items.index')" class="a3d-btn a3d-btn--ghost">
                        <ArrowRight :size="15" />
                        بازگشت به انبار
                    </Link>
                </template>
            </LuxHero>

            <form @submit.prevent="submit" enctype="multipart/form-data" class="gx-form" style="max-width: 720px">

                <!-- ===== اطلاعات پایه ===== -->
                <section class="gx-panel" :style="{ '--gx-i': 0 }">
                    <div class="gx-panel__head">
                        <span class="gx-panel__icon"><Package :size="16" /></span>
                        <div>
                            <p class="gx-panel__title">اطلاعات پایه</p>
                            <p class="gx-panel__desc">نام، قیمت‌گذاری و تصویر محصول</p>
                        </div>
                    </div>
                    <div class="gx-panel__body">
                        <div class="gs-input-group">
                            <label class="gs-input-label">نام محصول <span style="color:var(--gs-error)">*</span></label>
                            <input v-model="form.name" type="text" class="gs-input"
                                :class="{ 'gs-input-error': form.errors.name }" />
                            <span v-if="form.errors.name" class="gs-error-msg">{{ form.errors.name }}</span>
                        </div>

                        <div class="gx-grid2">
                            <div class="gs-input-group">
                                <label class="gs-input-label">قیمت خرید (تومان) <span style="color:var(--gs-error)">*</span></label>
                                <MoneyInput v-model="form.purchase_price" placeholder="0"
                                    :error="!!form.errors.purchase_price" />
                                <span v-if="form.errors.purchase_price" class="gs-error-msg">{{ form.errors.purchase_price }}</span>
                            </div>
                            <div class="gs-input-group">
                                <label class="gs-input-label">قیمت فروش (تومان) <span style="color:var(--gs-error)">*</span></label>
                                <MoneyInput v-model="form.sale_price" placeholder="0"
                                    :error="!!form.errors.sale_price" />
                                <span v-if="form.errors.sale_price" class="gs-error-msg">{{ form.errors.sale_price }}</span>
                            </div>
                        </div>

                        <div class="gs-input-group">
                            <label class="gs-input-label">توضیحات</label>
                            <textarea v-model="form.description" class="gs-input" rows="3" style="resize:vertical"></textarea>
                        </div>

                        <div class="gs-input-group">
                            <label class="gs-input-label">تصویر جدید</label>
                            <label class="gx-dropzone">
                                <input type="file" accept="image/*" @change="onFile" hidden />
                                <ImagePlus :size="18" />
                                <span>{{ form.image ? form.image.name : 'انتخاب تصویر جدید...' }}</span>
                            </label>
                        </div>

                        <!-- تصویر فعلی / جدید -->
                        <div class="gx-imgrow">
                            <div v-if="item.image_path && !preview" class="gx-imgbox">
                                <p>تصویر فعلی</p>
                                <img :src="'/storage/' + item.image_path" alt="current" />
                            </div>
                            <div v-if="preview" class="gx-imgbox">
                                <p>تصویر جدید</p>
                                <img :src="preview" alt="new preview" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== دسته‌بندی ===== -->
                <section class="gx-panel" :style="{ '--gx-i': 1 }">
                    <div class="gx-panel__head">
                        <span class="gx-panel__icon"><FolderTree :size="16" /></span>
                        <div>
                            <p class="gx-panel__title">دسته‌بندی</p>
                            <p class="gx-panel__desc">گروه‌بندی محصول برای انبار و منو</p>
                        </div>
                        <div class="gx-panel__spacer">
                            <button type="button" class="a3d-btn a3d-btn--sm"
                                @click="showNewCategory = !showNewCategory">
                                <Plus :size="13" /> جدید
                            </button>
                            <button type="button" class="a3d-btn a3d-btn--sm a3d-btn--ghost"
                                @click="showManageCategories = !showManageCategories">مدیریت</button>
                        </div>
                    </div>
                    <div class="gx-panel__body">
                        <div class="gs-input-group" style="margin-bottom: 0">
                            <select v-model="form.category_id" @change="onCategoryChange" class="gs-input"
                                :class="{ 'gs-input-error': form.errors.category_id }">
                                <option value="">انتخاب دسته‌بندی...</option>
                                <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <span v-if="form.errors.category_id" class="gs-error-msg">{{ form.errors.category_id }}</span>
                        </div>

                        <Transition name="gs-fade">
                            <div v-if="showNewCategory" class="gx-inline-box">
                                <input v-model="newCategoryName" type="text" class="gs-input" placeholder="نام دسته‌بندی جدید" />
                                <label class="gs-checkbox-label">
                                    <input v-model="newCategoryTracksStock" type="checkbox" class="gs-checkbox" />
                                    <span>این دسته‌بندی پیش‌فرض موجودی‌محور باشد</span>
                                </label>
                                <button type="button" class="a3d-btn a3d-btn--gold a3d-btn--sm" :disabled="addingCategory"
                                    @click="addCategory">
                                    {{ addingCategory ? '...' : 'افزودن دسته‌بندی' }}
                                </button>
                            </div>
                        </Transition>

                        <Transition name="gs-fade">
                            <div v-if="showManageCategories" class="gx-inline-box" style="max-height: 190px; overflow-y: auto">
                                <div v-for="cat in localCategories" :key="cat.id" class="gx-row">
                                    <span class="gx-row__v">{{ cat.name }}</span>
                                    <button type="button" class="a3d-btn a3d-btn--sm a3d-btn--danger"
                                        @click="deleteCategory(cat)">حذف</button>
                                </div>
                                <p v-if="!localCategories.length" style="font-size:.8rem;color:var(--gs-text-muted);margin:0">
                                    دسته‌بندی‌ای وجود ندارد
                                </p>
                            </div>
                        </Transition>
                    </div>
                </section>

                <!-- ===== ویژگی‌ها ===== -->
                <section class="gx-panel" :style="{ '--gx-i': 2 }">
                    <div class="gx-panel__head">
                        <span class="gx-panel__icon"><Sparkles :size="16" /></span>
                        <div>
                            <p class="gx-panel__title">ویژگی‌های محصول</p>
                            <p class="gx-panel__desc">موجودی، سریال، گارانتی و امانی بودن</p>
                        </div>
                    </div>
                    <div class="gx-panel__body">
                        <label class="gx-switchrow">
                            <div>
                                <p class="gx-row__v">این محصول موجودی انبار دارد</p>
                                <p class="gx-switchrow__hint">برای اقلامی مثل بازی که موجودی نامحدود دارند، غیرفعالش کن.</p>
                            </div>
                            <input v-model="form.tracks_stock" type="checkbox" class="gs-checkbox" />
                        </label>
                        <label class="gx-switchrow">
                            <p class="gx-row__v">این محصول شماره سریال دارد</p>
                            <input v-model="form.has_serial_number" type="checkbox" class="gs-checkbox" />
                        </label>
                        <label class="gx-switchrow">
                            <p class="gx-row__v">این محصول گارانتی دارد</p>
                            <input v-model="form.has_warranty" type="checkbox" class="gs-checkbox" />
                        </label>
                        <label class="gx-switchrow">
                            <p class="gx-row__v">این محصول امانی است</p>
                            <input v-model="form.is_consignment" type="checkbox" class="gs-checkbox" />
                        </label>
                    </div>
                </section>

                <!-- خطاهای اعتبارسنجی -->
                <div v-if="Object.keys(form.errors).length" class="gx-errors">
                    <AlertTriangle :size="15" />
                    <div>
                        <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
                    </div>
                </div>

                <!-- ===== ثبت ===== -->
                <div class="gx-formbar">
                    <Transition name="gs-fade">
                        <span v-if="form.isDirty" class="gx-status gx-status--amber"><i />تغییرات ذخیره نشده</span>
                    </Transition>
                    <span style="flex: 1" />
                    <Link :href="route('items.index')" class="a3d-btn a3d-btn--ghost">انصراف</Link>
                    <button type="submit" class="a3d-btn a3d-btn--gold" :disabled="form.processing">
                        <Save :size="15" />
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import MoneyInput from '@/Components/MoneyInput.vue'
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import {
    AlertTriangle,
    ArrowRight,
    FolderTree,
    ImagePlus,
    Package,
    Pencil,
    Plus,
    Save,
    Sparkles,
} from 'lucide-vue-next'

const props = defineProps({ item: Object, categories: Array })

const preview = ref(null)
const localCategories = ref([...props.categories])

const form = useForm({
    name: props.item.name,
    purchase_price: props.item.purchase_price,
    sale_price: props.item.sale_price,
    description: props.item.description ?? '',
    image: null,
    category_id: props.item.category_id ?? '',
    tracks_stock: props.item.tracks_stock ?? true,
    has_serial_number: props.item.has_serial_number ?? false,
    has_warranty: props.item.has_warranty ?? false,
    is_consignment: props.item.is_consignment ?? false,
    _method: 'PUT',
})

const showNewCategory = ref(false)
const newCategoryName = ref('')
const newCategoryTracksStock = ref(true)
const addingCategory = ref(false)
const showManageCategories = ref(false)

function onCategoryChange() {
    const cat = localCategories.value.find(c => c.id === form.category_id)
    if (cat) form.tracks_stock = !!cat.default_tracks_stock
}

async function addCategory() {
    if (!newCategoryName.value.trim()) return
    addingCategory.value = true
    try {
        const { data } = await axios.post(route('categories.store'), {
            name: newCategoryName.value,
            default_tracks_stock: newCategoryTracksStock.value,
        }, { headers: { Accept: 'application/json' } })
        localCategories.value.push(data)
        form.category_id = data.id
        form.tracks_stock = !!data.default_tracks_stock
        showNewCategory.value = false
        newCategoryName.value = ''
    } catch (e) {
        alert(e.response?.data?.errors?.name?.[0] ?? e.response?.data?.message ?? 'خطا در ثبت دسته‌بندی')
    } finally {
        addingCategory.value = false
    }
}

async function deleteCategory(cat) {
    if (!confirm(`دسته‌بندی «${cat.name}» حذف شود؟`)) return
    try {
        await axios.delete(route('categories.destroy', cat.id), { headers: { Accept: 'application/json' } })
        localCategories.value = localCategories.value.filter(c => c.id !== cat.id)
        if (form.category_id === cat.id) form.category_id = ''
    } catch (e) {
        alert(e.response?.data?.message ?? 'خطا در حذف دسته‌بندی')
    }
}

function onFile(e) {
    const file = e.target.files[0]
    if (!file) return
    form.image = file
    const reader = new FileReader()
    reader.onload = ev => preview.value = ev.target.result
    reader.readAsDataURL(file)
}

function submit() {
    form.post(route('items.update', props.item.id), { forceFormData: true })
}
</script>

<style scoped>
.gx-form {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.gx-grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1.25rem;
}

@media (max-width: 640px) {
    .gx-grid2 { grid-template-columns: 1fr; }
}

.gx-dropzone {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.85rem 1rem;
    border: 1px dashed var(--gs-border-hover);
    border-radius: 12px;
    color: var(--gs-text-secondary);
    font-size: 0.82rem;
    cursor: pointer;
    transition: border-color 0.25s ease, background 0.25s ease, color 0.25s ease;
}

.gx-dropzone:hover {
    border-color: var(--gs-gold);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
}

.gx-imgrow {
    display: flex;
    gap: 1rem;
    margin-top: 0.85rem;
}

.gx-imgbox {
    flex: 1;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
}

.gx-imgbox p {
    color: var(--gs-text-muted);
    font-size: 0.72rem;
    padding: 0.5rem 0.75rem 0.3rem;
}

.gx-imgbox img {
    width: 100%;
    display: block;
    max-height: 160px;
    object-fit: cover;
}

.gx-inline-box {
    margin-top: 0.75rem;
    padding: 0.85rem;
    border: 1px dashed var(--gs-border-hover);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    background: var(--gs-glass);
}

.gx-switchrow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0;
    border-bottom: 1px dashed var(--gs-border);
    cursor: pointer;
}

.gx-switchrow:last-child { border-bottom: none; }

.gx-switchrow__hint {
    font-size: 0.72rem;
    color: var(--gs-text-muted);
    margin-top: 0.2rem;
}

.gx-errors {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--gs-error) 35%, transparent);
    background: var(--gs-error-soft);
    color: var(--gs-error);
    font-size: 0.78rem;
    line-height: 1.9;
}

.gx-formbar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 0 2rem;
}

.gs-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--gs-text-secondary);
    cursor: pointer;
}
</style>
