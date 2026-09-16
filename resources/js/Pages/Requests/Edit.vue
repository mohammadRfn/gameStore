<script setup>
/**
 * ویرایش درخواست
 * مسیر: resources/js/Pages/Requests/Edit.vue
 * ---------------------------------------------------------------------------
 * RequestController@edit → props: { request, categories[], customers[] }
 * ارسال: form.put(route('requests.update', id)) با کلیدهای
 *        customer_id | customer_name | description | category_ids[]
 * (وضعیت از این‌جا تغییر نمی‌کند؛ RequestService فقط این فیلدها را به‌روز می‌کند.)
 */
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowRight, Eye, Pencil } from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { useToasts } from '@/Composables/useToasts'
import { dateFa } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CrmStatusChip from '@/Components/Crm/CrmStatusChip.vue'
import RequestForm from '@/Components/Crm/RequestForm.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    request: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
})

const { toasts, dismiss, danger } = useToasts({ flash: true })

const form = useForm({
    customer_id: props.request.customer_id ?? props.request.customer?.id ?? '',
    customer_name: props.request.customer_name ?? '',
    description: props.request.description ?? '',
    category_ids: (props.request.categories || []).map((c) => c.id),
})

function submit() {
    form.put(route('requests.update', props.request.id), {
        onError: () => danger('لطفاً خطاهای فرم را برطرف کنید.'),
    })
}
</script>

<template>
    <Head :title="`ویرایش درخواست #${request.id}`" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="gold" />

            <header class="st-shell">
                <div class="st-hero" style="padding-block: 2rem 1.6rem">
                    <div style="min-width: 0">
                        <div class="crm-hero__chips">
                            <span class="st-chip"><Pencil :size="12" /> ویرایش درخواست</span>
                            <span class="st-chip st-chip--plain crm-code" style="font-size: 0.7rem">#{{ request.id }}</span>
                            <CrmStatusChip :status="request.status" sm />
                            <span v-if="request.created_at" class="st-chip st-chip--plain">ثبت {{ dateFa(request.created_at) }}</span>
                        </div>

                        <h1 class="st-hero__title" style="font-size: clamp(1.8rem, 4.5vw, 2.7rem)">
                            درخواست <span>{{ request.customer_name }}</span>
                            <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                            </svg>
                        </h1>
                    </div>

                    <div class="crm-hero__actions">
                        <Link :href="route('requests.show', request.id)" class="a3d-btn a3d-btn--sm">
                            <Eye :size="14" /> مشاهدهٔ درخواست
                        </Link>
                        <Link :href="route('requests.index')" class="a3d-btn a3d-btn--ghost a3d-btn--sm">
                            <ArrowRight :size="14" /> فهرست
                        </Link>
                    </div>
                </div>
            </header>

            <div class="st-shell crm-body crm-body--form">
                <RequestForm
                    :form="form"
                    :customers="customers"
                    :categories="categories"
                    mode="edit"
                    :status="request.status"
                    :cancel-href="route('requests.show', request.id)"
                    @submit="submit"
                />
            </div>

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
