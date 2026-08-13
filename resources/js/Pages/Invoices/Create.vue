<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">فاکتور جدید</h1>
                    <p class="gs-subtitle">پس از ساخت فاکتور، اقلام را اضافه کنید</p>
                </div>
                <Link :href="route('invoices.index')" class="gs-btn gs-btn-secondary">← بازگشت</Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:520px">
            <form @submit.prevent="submit">
                <div class="gs-input-group">
                    <label class="gs-input-label">مشتری</label>
                    <select v-model="form.customer_id" class="gs-input"
                        :class="{ 'gs-input-error': form.errors.customer_id }">
                        <option value="">بدون مشتری</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                    <span v-if="form.errors.customer_id" class="gs-error-msg">{{ form.errors.customer_id }}</span>
                </div>

                <p class="gs-muted" style="margin-top:.5rem">
                    شماره فاکتور به‌صورت خودکار ساخته می‌شود. اقلام فاکتور را در مرحله بعد اضافه می‌کنید.
                </p>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('invoices.index')" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'در حال ساخت...' : 'ساخت فاکتور' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ customers: Array, default_customer_id: [Number, String, null] })

const urlParams = new URLSearchParams(window.location.search)
const requestId = urlParams.get('request_id') ?? ''

const form = useForm({
    customer_id: props.default_customer_id ?? '',
    request_id: requestId,
})

function submit() {
    form.post(route('invoices.store'))
}
</script>

<style scoped>
.gs-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between
}

.gs-muted {
    color: var(--gs-text-muted);
    font-size: .8rem
}
</style>