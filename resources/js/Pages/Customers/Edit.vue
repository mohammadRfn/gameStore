<script setup>
/**
 * ویرایش مشتری
 * مسیر: resources/js/Pages/Customers/Edit.vue
 * ---------------------------------------------------------------------------
 * CustomerController@edit → props.customer (Customer با requests/invoices)
 * ارسال: form.put(route('customers.update', id)) با کلیدهای name/phone/email/address/notes
 * پس از موفقیت بک‌اند به customers.show ریدایرکت می‌کند (flash success).
 */
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowRight, Eye, Pencil, Users } from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { useToasts } from '@/Composables/useToasts'
import { faInt } from '@/Utils/format'
import { avatarHue, dateFa, initials } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CustomerForm from '@/Components/Crm/CustomerForm.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    customer: { type: Object, required: true },
})

const { toasts, dismiss, danger } = useToasts({ flash: true })

const form = useForm({
    name: props.customer.name ?? '',
    phone: props.customer.phone ?? '',
    email: props.customer.email ?? '',
    address: props.customer.address ?? '',
    notes: props.customer.notes ?? '',
})

function submit() {
    form.put(route('customers.update', props.customer.id), {
        onError: () => danger('لطفاً خطاهای فرم را برطرف کنید.'),
    })
}
</script>

<template>
    <Head :title="`ویرایش ${customer.name}`" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="green" />

            <header class="st-shell">
                <div class="st-hero" style="padding-block: 2rem 1.6rem">
                    <div style="display: flex; align-items: center; gap: 1.1rem; min-width: 0">
                        <span class="crm-avatar crm-avatar--lg crm-avatar--ring" :style="{ '--hue': avatarHue(customer.name) }">
                            {{ initials(customer.name) }}
                        </span>
                        <div style="min-width: 0">
                            <div class="crm-hero__chips">
                                <span class="st-chip"><Pencil :size="12" /> ویرایش پرونده</span>
                                <span class="st-chip st-chip--plain">شناسه #{{ faInt(customer.id) }}</span>
                                <span v-if="customer.created_at" class="st-chip st-chip--plain">عضویت {{ dateFa(customer.created_at) }}</span>
                            </div>
                            <h1 class="st-hero__title" style="font-size: clamp(1.8rem, 4.5vw, 2.7rem); margin-top: 0.5rem">
                                <span>{{ customer.name }}</span>
                            </h1>
                        </div>
                    </div>

                    <div class="crm-hero__actions">
                        <Link :href="route('customers.show', customer.id)" class="a3d-btn a3d-btn--sm">
                            <Eye :size="14" /> مشاهدهٔ پرونده
                        </Link>
                        <Link :href="route('customers.index')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
                            <ArrowRight :size="14" /> فهرست مشتریان
                        </Link>
                    </div>
                </div>
            </header>

            <div class="st-shell crm-body crm-body--form">
                <CustomerForm :form="form" mode="edit" :cancel-href="route('customers.show', customer.id)" @submit="submit" />
            </div>

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
