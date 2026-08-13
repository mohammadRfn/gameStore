<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">محصول جدید</h1>
                    <p class="gs-subtitle">افزودن قلم به انبار</p>
                </div>
                <Link :href="route('items.index')" class="gs-btn gs-btn-secondary">← بازگشت</Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:620px">
            <form @submit.prevent="submit" enctype="multipart/form-data">
                <div class="gs-form-grid">
                    <div class="gs-input-group" style="grid-column:span 2">
                        <label class="gs-input-label">نام محصول <span style="color:var(--gs-error)">*</span></label>
                        <input v-model="form.name" type="text" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.name }" autofocus />
                        <span v-if="form.errors.name" class="gs-error-msg">{{ form.errors.name }}</span>
                    </div>
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
                    <div class="gs-input-group" style="grid-column:span 2">
                        <label class="gs-input-label">تصویر</label>
                        <input type="file" accept="image/*" class="gs-input" @change="onFile" style="cursor:pointer" />
                    </div>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">توضیحات</label>
                    <textarea v-model="form.description" class="gs-input" rows="3" style="resize:vertical"
                        placeholder="توضیح مختصر..."></textarea>
                </div>

                <!-- Category -->
                <div class="gs-input-group">
                    <label class="gs-input-label">دسته‌بندی <span style="color:var(--gs-error)">*</span></label>
                    <div style="display:flex;gap:.5rem">
                        <select v-model="form.category_id" @change="onCategoryChange" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.category_id }">
                            <option value="">انتخاب دسته‌بندی...</option>
                            <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <button type="button" class="gs-btn gs-btn-secondary gs-btn-sm"
                            @click="showNewCategory = !showNewCategory">+ جدید</button>
                        <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm"
                            @click="showManageCategories = !showManageCategories">مدیریت</button>
                    </div>
                    <span v-if="form.errors.category_id" class="gs-error-msg">{{ form.errors.category_id }}</span>

                    <!-- Add new category -->
                    <div v-if="showNewCategory" class="gs-new-category-box">
                        <input v-model="newCategoryName" type="text" class="gs-input" placeholder="نام دسته‌بندی جدید" />
                        <label class="gs-checkbox-label">
                            <input v-model="newCategoryTracksStock" type="checkbox" class="gs-checkbox" />
                            <span>این دسته‌بندی پیش‌فرض موجودی‌محور باشد</span>
                        </label>
                        <button type="button" class="gs-btn gs-btn-primary gs-btn-sm" :disabled="addingCategory"
                            @click="addCategory">
                            {{ addingCategory ? '...' : 'افزودن دسته‌بندی' }}
                        </button>
                    </div>

                    <!-- Manage / delete categories -->
                    <div v-if="showManageCategories" class="gs-manage-categories-box">
                        <div v-for="cat in localCategories" :key="cat.id" class="gs-manage-cat-row">
                            <span>{{ cat.name }}</span>
                            <button type="button" class="gs-btn gs-btn-danger gs-btn-sm"
                                @click="deleteCategory(cat)">حذف</button>
                        </div>
                        <p v-if="!localCategories.length" class="gs-label" style="margin:0">دسته‌بندی‌ای وجود ندارد</p>
                    </div>
                </div>

                <div class="gs-input-group">
                    <label class="gs-checkbox-label">
                        <input v-model="form.tracks_stock" type="checkbox" class="gs-checkbox" />
                        <span>این محصول موجودی انبار دارد</span>
                    </label>
                    <p class="gs-label" style="margin-top:.3rem">
                        برای اقلامی مثل بازی که موجودی نامحدود دارند، غیرفعالش کن.
                    </p>
                </div>

                <!-- Preview -->
                <div v-if="preview" class="gs-img-preview">
                    <img :src="preview" alt="preview" />
                </div>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('items.index')" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره محصول' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import MoneyInput from '@/Components/MoneyInput.vue'
import { Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ categories: Array })

const preview = ref(null)
const localCategories = ref([...props.categories])

const form = useForm({
    name: '',
    purchase_price: '',
    sale_price: '',
    description: '',
    image: null,
    category_id: '',
    tracks_stock: true,
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
    form.post(route('items.store'), { forceFormData: true })
}
</script>

<style scoped>
.gs-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between
}

.gs-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1.25rem
}

.gs-img-preview {
    margin: .75rem 0;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--gs-border);
    max-width: 220px
}

.gs-img-preview img {
    width: 100%;
    display: block
}

.gs-new-category-box {
    margin-top: .5rem;
    padding: .75rem;
    border: 1px dashed var(--gs-border);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: .5rem
}

.gs-checkbox-label {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    color: var(--gs-text-secondary);
    cursor: pointer
}

.gs-checkbox {
    accent-color: var(--gs-gold);
    width: 15px;
    height: 15px;
    cursor: pointer
}

.gs-label {
    color: var(--gs-text-muted);
    font-size: .8rem
}

.gs-manage-categories-box {
    margin-top: .5rem;
    padding: .5rem .75rem;
    border: 1px solid var(--gs-border);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: .4rem;
    max-height: 180px;
    overflow-y: auto
}

.gs-manage-cat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .25rem 0;
    font-size: .85rem
}
</style>