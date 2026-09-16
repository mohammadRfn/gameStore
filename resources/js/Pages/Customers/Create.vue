<script setup>
/**
 * ثبت مشتری جدید
 * مسیر: resources/js/Pages/Customers/Create.vue
 * ---------------------------------------------------------------------------
 * CustomerController@create → Inertia::render('Customers/Create')  (بدون props)
 * ارسال: form.post(route('customers.store'))  با کلیدهای name/phone/email/address/notes
 * پس از موفقیت بک‌اند به customers.index با flash success ریدایرکت می‌کند.
 */
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowRight, Sparkles, UserPlus, Users } from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { useToasts } from '@/Composables/useToasts'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CustomerForm from '@/Components/Crm/CustomerForm.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const { toasts, dismiss, danger } = useToasts({ flash: true })

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
})

function submit() {
    form.post(route('customers.store'), {
        onError: () => danger('لطفاً خطاهای فرم را برطرف کنید.'),
    })
}
</script>

<template>
    <Head title="ثبت مشتری جدید" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="green" />

            <header class="st-shell">
                <div class="st-hero" style="padding-block: 2rem 1.6rem">
                    <div style="min-width: 0">
                        <div class="crm-hero__chips">
                            <span class="st-chip"><Users :size="13" /> باشگاه مشتریان</span>
                            <span class="st-chip st-chip--plain"><Sparkles :size="12" /> پروندهٔ جدید</span>
                        </div>

                        <h1 class="st-hero__title" style="font-size: clamp(2rem, 5vw, 3rem)">
                            ثبت <span>مشتری جدید</span>
                            <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                            </svg>
                        </h1>

                        <p class="st-hero__lead">
                            اطلاعات تماس و نشانی مشتری را ثبت کنید تا در صدور فاکتور و ثبت درخواست‌های تعمیر
                            به‌سرعت در دسترس باشد.
                        </p>
                    </div>

                    <div class="crm-hero__actions">
                        <Link :href="route('customers.index')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
                            <ArrowRight :size="14" /> بازگشت به فهرست
                        </Link>
                    </div>
                </div>
            </header>

            <div class="st-shell crm-body crm-body--form">
                <CustomerForm :form="form" mode="create" :cancel-href="route('customers.index')" @submit="submit" />
            </div>

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
