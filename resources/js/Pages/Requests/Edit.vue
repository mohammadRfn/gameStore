<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">ویرایش درخواست #{{ request.id }}</h1>
                    <p class="gs-subtitle">{{ request.customer_name }}</p>
                </div>
                <Link :href="route('requests.show', request.id)" class="gs-btn gs-btn-secondary">← بازگشت</Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:680px">
            <form @submit.prevent="submit">
                <div class="gs-form-grid">
                    <div class="gs-input-group" style="grid-column:span 2">
                        <label class="gs-input-label">نام مشتری <span style="color:var(--gs-error)">*</span></label>
                        <input v-model="form.customer_name" type="text" class="gs-input"
                            :class="{'gs-input-error': form.errors.customer_name}" />
                        <span v-if="form.errors.customer_name" class="gs-error-msg">{{ form.errors.customer_name }}</span>
                    </div>

                    
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">دسته‌بندی‌ها</label>
                    <div class="gs-cat-grid">
                        <label v-for="cat in categories" :key="cat.id" class="gs-cat-item"
                            :class="{ active: form.category_ids.includes(cat.id) }">
                            <input type="checkbox" :value="cat.id" v-model="form.category_ids" class="gs-checkbox" />
                            <span>{{ cat.name }}</span>
                        </label>
                    </div>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">توضیحات <span style="color:var(--gs-error)">*</span></label>
                    <textarea v-model="form.description" class="gs-input" rows="4"
                        :class="{'gs-input-error': form.errors.description}" style="resize:vertical"></textarea>
                    <span v-if="form.errors.description" class="gs-error-msg">{{ form.errors.description }}</span>
                </div>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:space-between;align-items:center">
                    <span v-if="form.isDirty" class="gs-badge gs-badge-warning">تغییرات ذخیره نشده</span>
                    <span v-else></span>
                    <div style="display:flex;gap:.75rem">
                        <Link :href="route('requests.show', request.id)" class="gs-btn gs-btn-ghost">انصراف</Link>
                        <button type="submit" class="gs-btn gs-btn-primary" :disabled="form.processing || !form.isDirty">
                            {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ request: Object, categories: Array })

const form = useForm({
    customer_name: props.request.customer_name,
    description: props.request.description,
    category_ids: props.request.categories?.map(c => c.id) ?? [],
})

function submit() { form.put(route('requests.update', props.request.id)) }
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:0 1.25rem }
.gs-cat-grid { display:flex;flex-wrap:wrap;gap:.5rem;padding:.5rem 0 }
.gs-cat-item { display:flex;align-items:center;gap:.4rem;font-size:.875rem;color:var(--gs-text-secondary);cursor:pointer;padding:.35rem .75rem;border:1px solid var(--gs-border);border-radius:20px;transition:all var(--gs-transition) }
.gs-cat-item:hover,.gs-cat-item.active { border-color:var(--gs-gold);color:var(--gs-gold);background:var(--gs-gold-muted) }
.gs-checkbox { accent-color:var(--gs-gold) }
</style>
