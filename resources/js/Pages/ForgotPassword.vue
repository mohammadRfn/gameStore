<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'

const form = ref({
    username: '',
    new_password: '',
    new_password_confirmation: '',
})
const errors = ref({})
const saving = ref(false)
const showPasswords = ref(false)
const done = ref(false)

async function submit() {
    if (saving.value) return
    saving.value = true
    errors.value = {}
    try {
        await axios.post(route('password.forgot.store'), {
            ...form.value,
            username: form.value.username.trim(),
        })
        done.value = true
    } catch (e) {
        const res = e.response
        if (res?.status === 422) {
            errors.value = res.data.errors ?? {}
        } else if (res?.status === 429) {
            errors.value = { username: ['تعداد تلاش‌ها زیاد بود؛ یک دقیقه بعد دوباره امتحان کن.'] }
        } else {
            errors.value = { username: ['خطای غیرمنتظره؛ دوباره تلاش کن.'] }
        }
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Head title="بازیابی رمز عبور" />

    <div class="fp" dir="rtl">
        <div class="fp__card">
            <!-- موفقیت -->
            <template v-if="done">
                <h1 class="fp__title">رمز عبور تغییر کرد ✓</h1>
                <p class="fp__hint">حالا می‌توانی با رمز جدید وارد شوی.</p>
                <div class="fp__row">
                    <Link :href="route('login')" class="fp__btn fp__btn--gold">ورود به نرم‌افزار</Link>
                </div>
            </template>

            <!-- فرم -->
            <template v-else>
                <h1 class="fp__title">بازیابی رمز عبور</h1>
                <p class="fp__hint">
                    نام کاربری و رمز عبور جدید را وارد کن. نیازی به رمز قبلی نیست.
                </p>

                <form @submit.prevent="submit">
                    <label class="fp__group">
                        <span class="fp__label">نام کاربری</span>
                        <input
                            v-model="form.username"
                            class="fp__input"
                            :class="{ 'is-invalid': errors.username }"
                            dir="ltr"
                            autocomplete="username"
                            autofocus
                        />
                        <span v-if="errors.username" class="fp__err">{{ errors.username[0] }}</span>
                    </label>

                    <label class="fp__group">
                        <span class="fp__label">رمز عبور جدید</span>
                        <input
                            v-model="form.new_password"
                            class="fp__input"
                            :class="{ 'is-invalid': errors.new_password }"
                            :type="showPasswords ? 'text' : 'password'"
                            dir="ltr"
                            autocomplete="new-password"
                        />
                        <span v-if="errors.new_password" class="fp__err">{{ errors.new_password[0] }}</span>
                    </label>

                    <label class="fp__group">
                        <span class="fp__label">تکرار رمز عبور جدید</span>
                        <input
                            v-model="form.new_password_confirmation"
                            class="fp__input"
                            :type="showPasswords ? 'text' : 'password'"
                            dir="ltr"
                            autocomplete="new-password"
                        />
                    </label>

                    <button type="button" class="fp__link" @click="showPasswords = !showPasswords">
                        {{ showPasswords ? 'پنهان کردن رمزها' : 'نمایش رمزها' }}
                    </button>

                    <div class="fp__row">
                        <Link :href="route('login')" class="fp__btn fp__btn--plain">بازگشت به ورود</Link>
                        <button type="submit" class="fp__btn fp__btn--gold" :disabled="saving">
                            {{ saving ? 'در حال ذخیره…' : 'تغییر رمز عبور' }}
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</template>

<style scoped>
.fp {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background: var(--gs-bg);
    color: var(--gs-text-primary);
    font-family: 'IRANYekan', Tahoma, Arial, sans-serif;
}

.fp__card {
    width: min(440px, 100%);
    padding: 1.75rem;
    border-radius: 20px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-bg-card-strong, var(--gs-bg-card));
    box-shadow: var(--gs-shadow-md);
}

.fp__title { margin: 0 0 0.5rem; font-size: 1.15rem; font-weight: 800; }
.fp__hint { margin: 0 0 1.2rem; font-size: 0.8rem; line-height: 1.9; color: var(--gs-text-secondary); }

.fp__group { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.85rem; }
.fp__label { font-size: 0.76rem; font-weight: 600; color: var(--gs-text-secondary); }

.fp__input {
    width: 100%;
    height: 42px;
    padding: 0 0.8rem;
    border-radius: 12px;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-elevated);
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.fp__input:focus { border-color: var(--gs-border-strong); box-shadow: 0 0 0 4px var(--gs-gold-muted); }
.fp__input.is-invalid { border-color: var(--gs-error); }

.fp__err { font-size: 0.72rem; color: var(--gs-error); }

.fp__link {
    border: 0;
    background: none;
    padding: 0;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--gs-gold);
    cursor: pointer;
}

.fp__row { display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: 1.1rem; }

.fp__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.55rem 1.1rem;
    border-radius: 11px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

.fp__btn:disabled { opacity: 0.55; cursor: not-allowed; }
.fp__btn--gold { background: var(--gs-gold-grad, var(--gs-gold)); color: #14100a; }
.fp__btn--plain { background: var(--gs-glass); border-color: var(--gs-border); color: var(--gs-text-secondary); }
</style>