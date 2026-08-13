<template>
    <AppLayout>
        <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <h1 class="gs-title">ویرایش مشتری</h1>
                    <p class="gs-subtitle">{{ customer.name }}</p>
                </div>
                <div style="display:flex;gap:.75rem">
                    <Link :href="route('customers.show', customer.id)" class="gs-btn gs-btn-ghost">مشاهده</Link>
                    <Link :href="route('customers.index')" class="gs-btn gs-btn-secondary">← بازگشت</Link>
                </div>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:680px">
            <form @submit.prevent="submit">

                <div class="gs-form-grid">
                    <div class="gs-input-group">
                        <label class="gs-input-label">نام مشتری <span style="color:var(--gs-error)">*</span></label>
                        <input v-model="form.name" type="text" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.name }" />
                        <span v-if="form.errors.name" class="gs-error-msg">{{ form.errors.name }}</span>
                    </div>

                    <div class="gs-input-group">
                        <label class="gs-input-label">شماره تلفن</label>
                        <input v-model="form.phone" type="tel" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.phone }" />
                        <span v-if="form.errors.phone" class="gs-error-msg">{{ form.errors.phone }}</span>
                    </div>

                    <div class="gs-input-group">
                        <label class="gs-input-label">ایمیل</label>
                        <input v-model="form.email" type="email" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.email }" />
                        <span v-if="form.errors.email" class="gs-error-msg">{{ form.errors.email }}</span>
                    </div>

                    <div class="gs-input-group">
                        <label class="gs-input-label">آدرس</label>
                        <input v-model="form.address" type="text" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.address }" />
                        <span v-if="form.errors.address" class="gs-error-msg">{{ form.errors.address }}</span>
                    </div>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">یادداشت</label>
                    <textarea v-model="form.notes" class="gs-input" rows="3" style="resize:vertical"></textarea>
                </div>

                <div class="gs-divider"></div>

                <div style="display:flex;gap:.75rem;justify-content:space-between;align-items:center">
                    <!-- Dirty indicator -->
                    <span v-if="form.isDirty" class="gs-badge gs-badge-warning" style="font-size:.75rem">
                        تغییرات ذخیره نشده
                    </span>
                    <span v-else></span>

                    <div style="display:flex;gap:.75rem">
                        <Link :href="route('customers.index')" class="gs-btn gs-btn-ghost">انصراف</Link>
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

const props = defineProps({
    customer: Object,
})

const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone ?? '',
    email: props.customer.email ?? '',
    address: props.customer.address ?? '',
    notes: props.customer.notes ?? '',
})

function submit() {
    form.put(route('customers.update', props.customer.id))
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
</style>
