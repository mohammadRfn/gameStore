<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="violet" />

            <LuxHero
                chip="ماژول فروش"
                chip-two="Invoices / Edit"
                :title="'ویرایش «' + invoice.invoice_number + '»'"
                lead="به‌روزرسانی شماره فاکتور، مشتری و درخواست مرتبط."
                cube="🧾"
                satellite="✏️"
            >
                <template #chip-icon><ReceiptText :size="13" /></template>
                <template #actions>
                    <Link :href="route('invoices.show', invoice.id)" class="a3d-btn a3d-btn--ghost">
                        <ArrowRight :size="15" /> بازگشت
                    </Link>
                </template>
            </LuxHero>

            <section class="gx-panel" style="max-width: 680px" :style="{ '--gx-i': 0 }">
                <div class="gx-panel__head">
                    <span class="gx-panel__icon"><Pencil :size="16" /></span>
                    <div>
                        <p class="gx-panel__title">مشخصات فاکتور</p>
                        <p class="gx-panel__desc">{{ invoice.invoice_number }}</p>
                    </div>
                </div>
                <div class="gx-panel__body">
                    <form @submit.prevent="submit">
                        <div class="gs-input-group">
                            <label class="gs-input-label">شماره فاکتور</label>
                            <input v-model="form.invoice_number" class="gs-input"
                                :class="{ 'gs-input-error': form.errors.invoice_number }" />
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

                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.4rem">
                            <Link :href="route('invoices.show', invoice.id)" class="a3d-btn a3d-btn--ghost">انصراف</Link>
                            <button type="submit" class="a3d-btn a3d-btn--gold" :disabled="form.processing">
                                <Save :size="15" />
                                {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import { ArrowRight, Pencil, ReceiptText, Save } from 'lucide-vue-next'

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
