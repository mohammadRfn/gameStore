<template>
    <form @submit.prevent="submit">
        <div class="gs-input-group">
            <label class="gs-input-label">کد یک‌بارمصرف</label>
            <input v-model="form.code" type="text" class="gs-input" dir="ltr"
                style="text-align:center;letter-spacing:.1em"
                :class="{ 'gs-input-error': form.errors.code }"
                placeholder="GS-XXXX-XXXX-XXXX-XXXX" autofocus />
            <Transition name="gs-fade">
                <span v-if="form.errors.code" class="gs-error-msg">{{ form.errors.code }}</span>
            </Transition>
        </div>

        <button type="submit" class="gs-btn gs-btn-primary gs-btn-lg" style="width:100%;margin-top:.5rem;justify-content:center"
            :disabled="form.processing">
            <span v-if="form.processing" class="gs-spinner"></span>
            {{ form.processing ? 'در حال فعال‌سازی...' : 'فعال‌سازی با کد' }}
        </button>
    </form>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({ code: '' })

function submit() {
    // در موفقیت، سرور مستقیماً به داشبورد ریدایرکت می‌کند
    form.post(route('licensing.redeem'), { preserveScroll: true })
}
</script>