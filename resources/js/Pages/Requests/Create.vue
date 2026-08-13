<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">درخواست جدید</h1>
                    <p class="gs-subtitle">ثبت درخواست سرویس یا تعمیر</p>
                </div>
                <Link :href="route('requests.index')" class="gs-btn gs-btn-secondary">← بازگشت</Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:680px">
            <form @submit.prevent="submit">

                <div class="gs-form-grid">
                    <!-- Customer select or name -->
                    <div class="gs-input-group" style="grid-column:span 2">
                        <label class="gs-input-label">مشتری</label>
                        <select v-model="form.customer_id" class="gs-input" @change="onCustomerSelect">
                            <option value="">— بدون مشتری ثبت شده —</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="gs-input-group" style="grid-column:span 2">
                        <label class="gs-input-label">نام مشتری <span style="color:var(--gs-error)">*</span></label>
                        <input v-model="form.customer_name" type="text" class="gs-input"
                            :class="{'gs-input-error': form.errors.customer_name}"
                            placeholder="نام مشتری (الزامی)" />
                        <span v-if="form.errors.customer_name" class="gs-error-msg">{{ form.errors.customer_name }}</span>
                    </div>

                    
                </div>

                <!-- Categories -->
                <div class="gs-input-group">
                    <label class="gs-input-label">دسته‌بندی‌ها</label>
                    <div class="gs-cat-grid">
                        <label v-for="cat in categories" :key="cat.id" class="gs-cat-item">
                            <input type="checkbox" :value="cat.id" v-model="form.category_ids" class="gs-checkbox" />
                            <span>{{ cat.name }}</span>
                        </label>
                    </div>
                </div>

                <!-- Description -->
                <div class="gs-input-group">
                    <label class="gs-input-label">توضیحات <span style="color:var(--gs-error)">*</span></label>
                    <textarea v-model="form.description" class="gs-input" rows="4"
                        :class="{'gs-input-error': form.errors.description}"
                        placeholder="شرح مشکل یا درخواست مشتری..." style="resize:vertical"></textarea>
                    <span v-if="form.errors.description" class="gs-error-msg">{{ form.errors.description }}</span>
                </div>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('requests.index')" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'در حال ذخیره...' : 'ثبت درخواست' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ customers: Array, categories: Array })

const form = useForm({
    customer_id: '',
    customer_name: '',
    description: '',
    category_ids: [],
})

function onCustomerSelect() {
    const c = props.customers.find(c => c.id == form.customer_id)
    if (c) form.customer_name = c.name
}

function submit() { form.post(route('requests.store')) }
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:0 1.25rem }
.gs-cat-grid { display:flex;flex-wrap:wrap;gap:.5rem;padding:.5rem 0 }
.gs-cat-item { display:flex;align-items:center;gap:.4rem;font-size:.875rem;color:var(--gs-text-secondary);cursor:pointer;padding:.35rem .75rem;border:1px solid var(--gs-border);border-radius:20px;transition:all var(--gs-transition) }
.gs-cat-item:hover { border-color:var(--gs-border-hover);color:var(--gs-gold) }
.gs-checkbox { accent-color:var(--gs-gold) }
</style>
