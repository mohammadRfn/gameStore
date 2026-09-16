<script setup>
/**
 * ثبت درخواست جدید
 * مسیر: resources/js/Pages/Requests/Create.vue
 * ---------------------------------------------------------------------------
 * RequestController@create → props: { categories[], customers[{id,name}] }
 * ارسال: form.post(route('requests.store')) با کلیدهای
 *        customer_id | customer_name | description | category_ids[]
 * اگر صفحه با ?customer_id=… باز شود (از پروندهٔ مشتری)، مشتری از پیش انتخاب می‌شود.
 */
import { onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowRight, ClipboardList, Sparkles, Wrench } from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { useToasts } from '@/Composables/useToasts'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import RequestForm from '@/Components/Crm/RequestForm.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    customers: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const { toasts, dismiss, danger } = useToasts({ flash: true })

const form = useForm({
    customer_id: '',
    customer_name: '',
    description: '',
    category_ids: [],
})

/* پیش‌انتخاب مشتری از query string (?customer_id=12) */
onMounted(() => {
    const id = new URLSearchParams(window.location.search).get('customer_id')
    if (!id) return
    const found = props.customers.find((c) => String(c.id) === String(id))
    if (found) {
        form.defaults({ customer_id: found.id, customer_name: found.name })
        form.reset()
    }
})

function submit() {
    form.post(route('requests.store'), {
        onError: () => danger('لطفاً خطاهای فرم را برطرف کنید.'),
    })
}
</script>

<template>
    <Head title="ثبت درخواست جدید" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="gold" />

            <header class="st-shell">
                <div class="st-hero" style="padding-block: 2rem 1.6rem">
                    <div style="min-width: 0">
                        <div class="crm-hero__chips">
                            <span class="st-chip"><Wrench :size="13" /> خدمات و پشتیبانی</span>
                            <span class="st-chip st-chip--plain"><Sparkles :size="12" /> تیکت جدید</span>
                        </div>

                        <h1 class="st-hero__title" style="font-size: clamp(2rem, 5vw, 3rem)">
                            ثبت <span>درخواست جدید</span>
                            <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                            </svg>
                        </h1>

                        <p class="st-hero__lead">
                            شرح مشکل دستگاه را ثبت کنید، دسته‌بندی خدمت را انتخاب کنید و در صورت وجود، به پروندهٔ مشتری متصل کنید.
                        </p>
                    </div>

                    <div class="crm-hero__actions">
                        <Link :href="route('requests.index')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
                            <ArrowRight :size="14" /> بازگشت به فهرست
                        </Link>
                    </div>
                </div>
            </header>

            <div class="st-shell crm-body crm-body--form">
                <RequestForm
                    :form="form"
                    :customers="customers"
                    :categories="categories"
                    mode="create"
                    :cancel-href="route('requests.index')"
                    @submit="submit"
                />
            </div>

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
