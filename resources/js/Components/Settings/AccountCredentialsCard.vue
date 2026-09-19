<script setup>
/**
 * AccountCredentialsCard — تغییر نام کاربری و رمز عبور
 * مسیر: resources/js/Components/Settings/AccountCredentialsCard.vue
 * emit: toast(kind, message)
 */
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { Eye, EyeOff, KeyRound, RefreshCw, Save } from 'lucide-vue-next'
import GsRow from '@/Components/Settings/GsRow.vue'
import GsField from '@/Components/Settings/GsField.vue'

const emit = defineEmits(['toast'])

const loading = ref(true)
const saving = ref(false)
const showPasswords = ref(false)
const forceMode = ref(false)
const currentUsername = ref('')
const errors = ref({})

const form = ref({
    username: '',
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
})

const usernameChanged = computed(() => form.value.username.trim() !== currentUsername.value)
const passwordFilled = computed(() => form.value.new_password !== '')
const canSave = computed(() => {
    if (forceMode.value) return form.value.username.trim() !== '' && passwordFilled.value
    return !!form.value.current_password && (usernameChanged.value || passwordFilled.value)
})
const fieldType = computed(() => (showPasswords.value ? 'text' : 'password'))

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get(route('account.credentials.show'))
        currentUsername.value = data.username
        form.value.username = data.username
    } catch (e) {
        emit('toast', 'danger', 'دریافت اطلاعات حساب ناموفق بود.')
    } finally {
        loading.value = false
    }
}

function clearSecrets() {
    form.value.current_password = ''
    form.value.new_password = ''
    form.value.new_password_confirmation = ''
}

function reset() {
    form.value.username = currentUsername.value
    clearSecrets()
    errors.value = {}
    forceMode.value = false
}

function toggleForce() {
    forceMode.value = !forceMode.value
    errors.value = {}
    form.value.current_password = ''
}

async function save() {
    if (saving.value || !canSave.value) return

    if (forceMode.value) {
        const ok = window.confirm(
            'نام کاربری و رمز عبور فعلی کاملاً با مقادیر جدید جایگزین می‌شوند. ادامه می‌دهی؟',
        )
        if (!ok) return
    }

    saving.value = true
    errors.value = {}

    const payload = { username: form.value.username.trim() }
    if (forceMode.value) {
        payload.force = true
    } else {
        payload.current_password = form.value.current_password
    }
    if (form.value.new_password) {
        payload.new_password = form.value.new_password
        payload.new_password_confirmation = form.value.new_password_confirmation
    }

    try {
        const { data } = await axios.put(route('account.credentials.update'), payload)
        currentUsername.value = data.username
        form.value.username = data.username
        clearSecrets()
        forceMode.value = false
        emit('toast', 'success', data.message)
    } catch (e) {
        const res = e.response
        if (res?.status === 422) {
            errors.value = res.data.errors ?? {}
            const first = Object.values(errors.value)[0]
            emit('toast', 'danger', Array.isArray(first) ? first[0] : res.data.message)
        } else if (res?.status === 429) {
            emit('toast', 'danger', 'تعداد تلاش‌ها زیاد بود؛ یک دقیقه بعد دوباره امتحان کن.')
        } else {
            emit('toast', 'danger', 'ذخیره ناموفق بود.')
        }
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="a3d-holo st-card acc" @keydown.enter="save">
        <div class="acc__title">
            <KeyRound :size="16" />
            <div>
                <p class="acc__title-t">اطلاعات ورود به نرم‌افزار</p>
                <p class="acc__title-d">
                    {{ forceMode
                        ? 'حالت بازنویسی: بدون رمز فعلی، نام کاربری و رمز از نو نوشته می‌شود'
                        : 'برای هر تغییر، رمز عبور فعلی لازم است' }}
                </p>
            </div>
        </div>

        <div v-if="loading" class="acc__loading">
            <RefreshCw :size="15" class="acc__spin" />
            در حال دریافت…
        </div>

        <template v-else>
            <p v-if="forceMode" class="acc__warn">
                نام کاربری و رمز عبور فعلی کاملاً با مقادیر جدید جایگزین می‌شوند.
            </p>

            <GsRow title="نام کاربری" desc="نامی که هنگام ورود وارد می‌کنی">
                <div class="acc__ctl">
                    <GsField v-model="form.username" dir="ltr" :invalid="!!errors.username" />
                    <p v-if="errors.username" class="acc__err">{{ errors.username[0] }}</p>
                </div>
            </GsRow>

            <GsRow
                title="رمز عبور جدید"
                :desc="forceMode ? 'الزامی (حداقل ۶ کاراکتر)' : 'اگر فقط نام کاربری را عوض می‌کنی خالی بگذار (حداقل ۶ کاراکتر)'"
            >
                <div class="acc__ctl">
                    <GsField
                        v-model="form.new_password"
                        :type="fieldType"
                        dir="ltr"
                        placeholder="••••••"
                        :invalid="!!errors.new_password"
                    />
                    <p v-if="errors.new_password" class="acc__err">{{ errors.new_password[0] }}</p>
                </div>
            </GsRow>

            <GsRow v-if="passwordFilled" title="تکرار رمز عبور جدید">
                <div class="acc__ctl">
                    <GsField
                        v-model="form.new_password_confirmation"
                        :type="fieldType"
                        dir="ltr"
                        placeholder="••••••"
                    />
                </div>
            </GsRow>

            <GsRow v-if="!forceMode" title="رمز عبور فعلی" desc="برای تأیید هویت لازم است">
                <div class="acc__ctl">
                    <GsField
                        v-model="form.current_password"
                        :type="fieldType"
                        dir="ltr"
                        placeholder="••••••"
                        :invalid="!!errors.current_password"
                    />
                    <p v-if="errors.current_password" class="acc__err">
                        {{ errors.current_password[0] }}
                    </p>
                </div>
            </GsRow>

            <div class="acc__actions">
                <button
                    type="button"
                    class="a3d-btn a3d-btn--ghost a3d-btn--sm"
                    @click="showPasswords = !showPasswords"
                >
                    <component :is="showPasswords ? EyeOff : Eye" :size="14" />
                    {{ showPasswords ? 'پنهان کردن رمزها' : 'نمایش رمزها' }}
                </button>

                <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="toggleForce">
                    {{ forceMode ? 'بازگشت به حالت عادی' : 'رمز فعلی را ندارم (بازنویسی)' }}
                </button>

                <span class="acc__spacer" />

                <button type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="reset">
                    بازنشانی
                </button>
                <button
                    type="button"
                    class="a3d-btn a3d-btn--gold a3d-btn--sm"
                    :disabled="saving || !canSave"
                    @click="save"
                >
                    <RefreshCw v-if="saving" :size="14" class="acc__spin" />
                    <Save v-else :size="14" />
                    {{ saving ? 'در حال ذخیره…' : forceMode ? 'بازنویسی اطلاعات ورود' : 'ذخیرهٔ اطلاعات ورود' }}
                </button>
            </div>
        </template>
    </div>
</template>

<style scoped>
.acc {
    margin-top: 1.15rem;
}

.acc__title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding-bottom: 0.9rem;
    margin-bottom: 0.4rem;
    border-bottom: 1px dashed var(--gs-border);
    color: var(--gs-gold);
}

.acc__title-t {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--gs-text-primary);
}

.acc__title-d {
    font-size: 0.74rem;
    color: var(--gs-text-muted);
    margin-top: 0.1rem;
}

.acc__loading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1.2rem 0;
    color: var(--gs-text-muted);
    font-size: 0.84rem;
}

.acc__warn {
    margin: 0.6rem 0;
    padding: 0.6rem 0.8rem;
    border-radius: 10px;
    background: var(--gs-warning-soft);
    border: 1px solid color-mix(in srgb, var(--gs-warning) 30%, transparent);
    color: var(--gs-warning);
    font-size: 0.76rem;
    line-height: 1.9;
}

.acc__ctl {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
}

.acc__err {
    font-size: 0.72rem;
    color: var(--gs-error);
}

.acc__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.9rem;
    padding-top: 0.9rem;
    border-top: 1px dashed var(--gs-border);
}

.acc__spacer {
    flex: 1;
}

.acc__spin {
    animation: acc-spin 0.9s linear infinite;
}

@keyframes acc-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>