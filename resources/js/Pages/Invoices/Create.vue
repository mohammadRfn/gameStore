<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="violet" />

            <LuxHero
                chip="ماژول فروش"
                chip-two="Invoices / Create"
                title="فاکتور «جدید»"
                lead="پس از ساخت فاکتور، اقلام و سرویس‌ها را به آن اضافه می‌کنید. شماره فاکتور خودکار ساخته می‌شود."
                cube="🧾"
                satellite="✨"
            >
                <template #chip-icon><ReceiptText :size="13" /></template>
                <template #actions>
                    <Link :href="route('invoices.index')" class="a3d-btn a3d-btn--ghost">
                        <ArrowRight :size="15" /> بازگشت
                    </Link>
                </template>
            </LuxHero>

            <section class="gx-panel" style="max-width: 560px" :style="{ '--gx-i': 0 }">
                <div class="gx-panel__head">
                    <span class="gx-panel__icon"><UserRound :size="16" /></span>
                    <div>
                        <p class="gx-panel__title">مشخصات فاکتور</p>
                        <p class="gx-panel__desc">انتخاب مشتری برای فاکتور جدید</p>
                    </div>
                </div>
                <div class="gx-panel__body">
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

                        <p class="gx-hint">
                            <Info :size="14" />
                            شماره فاکتور به‌صورت خودکار ساخته می‌شود. اقلام فاکتور را در مرحله بعد اضافه می‌کنید.
                        </p>

                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.4rem">
                            <Link :href="route('invoices.index')" class="a3d-btn a3d-btn--ghost">انصراف</Link>
                            <button type="submit" class="a3d-btn a3d-btn--gold" :disabled="form.processing">
                                <Plus :size="15" />
                                {{ form.processing ? 'در حال ساخت...' : 'ساخت فاکتور' }}
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
import { ArrowRight, Info, Plus, ReceiptText, UserRound } from 'lucide-vue-next'

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
.gx-hint {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-top: 0.6rem;
    padding: 0.7rem 0.9rem;
    border-radius: 12px;
    border: 1px dashed var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-text-secondary);
    font-size: 0.76rem;
    line-height: 1.9;
}

.gx-hint svg { color: var(--gs-gold); flex-shrink: 0; margin-top: 0.2rem; }
</style>
