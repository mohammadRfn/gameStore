<template>
    <form @submit.prevent="submit">
        <p class="gs-label" style="margin-bottom:.75rem">درخواست فعال‌سازی جدید (بدون کد)</p>

        <div class="gs-input-group">
            <label class="gs-input-label">نام شما (اختیاری)</label>
            <input v-model="form.customer_name" type="text" class="gs-input" placeholder="مثلاً فروشگاه راسخ" />
        </div>

        <div class="gs-input-group">
            <label class="gs-input-label">شماره تماس (اختیاری)</label>
            <input v-model="form.customer_phone" type="text" class="gs-input" dir="ltr" placeholder="09xxxxxxxxx" />
        </div>

        <Transition name="gs-fade">
            <span v-if="form.errors.request" class="gs-error-msg">{{ form.errors.request }}</span>
        </Transition>

        <button type="submit" class="gs-btn gs-btn-secondary" style="width:100%;margin-top:.5rem;justify-content:center"
            :disabled="form.processing">
            <span v-if="form.processing" class="gs-spinner"></span>
            {{ form.processing ? 'در حال ارسال...' : 'ارسال درخواست فعال‌سازی' }}
        </button>
    </form>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const emit = defineEmits(['submitted'])

const form = useForm({ customer_name: '', customer_phone: '' })

function submit() {
    form.post(route('licensing.request'), {
        preserveScroll: true,
        onSuccess: () => emit('submitted'),
    })
}
</script>