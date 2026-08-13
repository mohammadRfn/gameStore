<template>
    <AppLayout>
        <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <h1 class="gs-title">مشتری جدید</h1>
                    <p class="gs-subtitle">افزودن مشتری به سیستم</p>
                </div>
                <Link :href="route('customers.index')" class="gs-btn gs-btn-secondary">
                    ← بازگشت
                </Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:680px">
            <form @submit.prevent="submit">

                <div class="gs-form-grid">
                    <!-- Name -->
                    <div class="gs-input-group">
                        <label class="gs-input-label">نام مشتری <span style="color:var(--gs-error)">*</span></label>
                        <input v-model="form.name" type="text" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.name }" placeholder="نام کامل مشتری" autofocus />
                        <span v-if="form.errors.name" class="gs-error-msg">{{ form.errors.name }}</span>
                    </div>

                    <!-- Phone -->
                    <div class="gs-input-group">
                        <label class="gs-input-label">شماره تلفن</label>
                        <input v-model="form.phone" type="tel" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.phone }" placeholder="09xxxxxxxxx" />
                        <span v-if="form.errors.phone" class="gs-error-msg">{{ form.errors.phone }}</span>
                    </div>

                    <!-- Email -->
                    <div class="gs-input-group">
                        <label class="gs-input-label">ایمیل</label>
                        <input v-model="form.email" type="email" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.email }" placeholder="example@email.com" />
                        <span v-if="form.errors.email" class="gs-error-msg">{{ form.errors.email }}</span>
                    </div>

                    <!-- Address -->
                    <div class="gs-input-group">
                        <label class="gs-input-label">آدرس</label>
                        <input v-model="form.address" type="text" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.address }" placeholder="آدرس مشتری (اختیاری)" />
                        <span v-if="form.errors.address" class="gs-error-msg">{{ form.errors.address }}</span>
                    </div>
                </div>

                <!-- Notes - full width -->
                <div class="gs-input-group">
                    <label class="gs-input-label">یادداشت</label>
                    <textarea v-model="form.notes" class="gs-input" rows="3"
                        placeholder="توضیحات اضافی..." style="resize:vertical"></textarea>
                </div>

                <div class="gs-divider"></div>

                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('customers.index')" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary" :disabled="form.processing">
                        <span v-if="form.processing" class="gs-spinner-sm"></span>
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره مشتری' }}
                    </button>
                </div>

            </form>
        </div>

    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
})

function submit() {
    form.post(route('customers.store'))
}
</script>

<style scoped>
.gs-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 1.25rem;
}

@media (max-width: 560px) {
    .gs-form-grid {
        grid-template-columns: 1fr;
    }
}

.gs-spinner-sm {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(10, 10, 15, 0.3);
    border-top-color: #0a0a0f;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin-left: .4rem;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
