<script setup>
/**
 * RequestQuickCustomer — ساخت سریع مشتری داخل صفحهٔ ثبت درخواست
 * مسیر: resources/js/Components/Crm/RequestQuickCustomer.vue
 *
 * طبق CustomerRequest تنها فیلد اجباری مشتری «نام و نام خانوادگی» است؛
 * بقیهٔ اطلاعات (تلفن، ایمیل، آدرس) را بعداً از بخش مشتریان می‌توان کامل کرد.
 * emit('created', { id, name })
 */
import { ref } from 'vue'
import axios from 'axios'
import { Check, RefreshCw, User, UserPlus } from 'lucide-vue-next'
import CrmCollapse from '@/Components/Crm/CrmCollapse.vue'
import CrmField from '@/Components/Crm/CrmField.vue'

const emit = defineEmits(['created'])

const open = ref(false)
const name = ref('')
const error = ref('')
const busy = ref(false)
const done = ref('')

async function create() {
    error.value = ''
    done.value = ''
    if (!name.value.trim()) {
        error.value = 'نام و نام خانوادگی مشتری الزامی است.'
        return
    }
    busy.value = true
    try {
        const { data } = await axios.post(route('requests.quick-customer'), { name: name.value.trim() })
        emit('created', data)
        done.value = `«${data.name}» ثبت و برای این درخواست انتخاب شد.`
        name.value = ''
    } catch (e) {
        error.value = e?.response?.data?.errors?.name?.[0] || e?.response?.data?.message || 'ثبت مشتری انجام نشد.'
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <CrmCollapse v-model:open="open" title="ایجاد مشتری جدید" hint="فقط نام و نام خانوادگی" :icon="UserPlus">
        <div class="rb-quick" @keydown.enter.prevent="create">
            <CrmField
                v-model="name"
                label="نام و نام خانوادگی"
                :icon="User"
                required
                placeholder="مثلاً علی رضایی"
                :error="error"
                :maxlength="255"
            />
            <button type="button" class="a3d-btn a3d-btn--gold a3d-btn--sm rb-quick__btn" :disabled="busy" @click="create">
                <RefreshCw v-if="busy" :size="14" class="crm-spin" />
                <Check v-else :size="14" />
                ثبت مشتری
            </button>
        </div>
        <p v-if="done" class="rb-ok"><Check :size="13" /> {{ done }}</p>
    </CrmCollapse>
</template>
