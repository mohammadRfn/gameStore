<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">ویرایش فاکتور</h1>
                    <p class="gs-subtitle">{{ invoice.invoice_number }}</p>
                </div>
                <Link :href="route('invoices.show', invoice.id)" class="gs-btn gs-btn-secondary">← بازگشت</Link>
            </div>
        </template>

        <div class="gs-card gs-card-elevated" style="max-width:680px">
            <form @submit.prevent="submit">

                <div class="gs-input-group">
                    <label class="gs-input-label">شماره فاکتور</label>
                    <input v-model="form.invoice_number" class="gs-input"
                        :class="{'gs-input-error': form.errors.invoice_number}" />
                    <span v-if="form.errors.invoice_number" class="gs-error-msg">{{ form.errors.invoice_number }}</span>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">درخواست مرتبط</label>
                    <select v-model="form.request_id" class="gs-input">
                        <option :value="null">— بدون درخواست —</option>
                        <option v-for="r in requests" :key="r.id" :value="r.id">
                            #{{ r.id }} — {{ r.customer?.name ?? '—' }}
                        </option>
                    </select>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">مشتری</label>
                    <select v-model="form.customer_id" class="gs-input">
                        <option :value="null">— انتخاب مشتری —</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div class="gs-divider"></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <Link :href="route('invoices.show', invoice.id)" class="gs-btn gs-btn-ghost">انصراف</Link>
                    <button type="submit" class="gs-btn gs-btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ invoice: Object, customers: Array, requests: Array })

const form = useForm({
    invoice_number: props.invoice.invoice_number,
    request_id:     props.invoice.request_id ?? null,
    customer_id:    props.invoice.customer_id ?? null,
})

function submit() {
    form.put(route('invoices.update', props.invoice.id))
}
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
</style>