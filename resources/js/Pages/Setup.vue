<template>
    <div class="gs-login-wrap">
        <div class="gs-login-card">
            <div class="gs-login-brand">
                <span class="gs-login-icon">♟</span>
                <h1 class="gs-title">Game<span class="gs-gold-text">Shop</span></h1>
                <p class="gs-subtitle" style="margin-top:.25rem">
                    {{ step === 'recovery' ? 'حساب شما ساخته شد' : 'راه‌اندازی اولیه — ساخت حساب مدیر' }}
                </p>
            </div>

            <div class="gs-divider-gold"></div>

            <!-- مرحله ۱: ساخت حساب -->
            <form v-if="step === 'form'" @submit.prevent="submit">
                <p class="gs-label" style="margin-bottom:1rem;line-height:1.9">
                    این اولین اجرای برنامه است. نام کاربری و رمز عبور مدیر را انتخاب کنید؛ با همین اطلاعات وارد می‌شوید.
                </p>

                <div class="gs-input-group">
                    <label class="gs-input-label">نام کاربری</label>
                    <input v-model="form.username" type="text" class="gs-input" :class="{ 'gs-input-error': form.errors.username }"
                        placeholder="مثلاً admin" autocomplete="username" autofocus />
                    <span v-if="form.errors.username" class="gs-error-msg">{{ form.errors.username }}</span>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">رمز عبور</label>
                    <div class="gs-password-wrap">
                        <input v-model="form.password" :type="showPass ? 'text' : 'password'" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.password }" placeholder="حداقل ۶ کاراکتر" autocomplete="new-password" />
                        <button type="button" class="gs-eye-btn" @click="showPass = !showPass">{{ showPass ? '🙈' : '👁' }}</button>
                    </div>
                    <span v-if="form.errors.password" class="gs-error-msg">{{ form.errors.password }}</span>
                </div>

                <div class="gs-input-group">
                    <label class="gs-input-label">تکرار رمز عبور</label>
                    <input v-model="form.password_confirmation" :type="showPass ? 'text' : 'password'" class="gs-input"
                        placeholder="تکرار رمز عبور" autocomplete="new-password" />
                </div>

                <button type="submit" class="gs-btn gs-btn-primary gs-btn-lg"
                    style="width:100%;margin-top:1.5rem;justify-content:center" :disabled="form.processing">
                    <span v-if="form.processing" class="gs-spinner"></span>
                    {{ form.processing ? 'در حال ساخت…' : 'ساخت حساب و ادامه' }}
                </button>
            </form>

            <!-- مرحله ۲: کد بازیابی (فقط یک‌بار نمایش داده می‌شود) -->
            <div v-else>
                <p class="gs-label" style="margin-bottom:.75rem;line-height:1.9">
                    این <b>کد بازیابی</b> تنها راه برگشت به حساب در صورت فراموشی رمز است و فقط همین یک بار نمایش داده می‌شود.
                    آن را یادداشت کنید یا در جای امن نگه دارید.
                </p>
                <div class="gs-recovery" dir="ltr">{{ recoveryCode }}</div>
                <button type="button" class="gs-btn gs-btn-primary gs-btn-lg"
                    style="width:100%;margin-top:1.5rem;justify-content:center" @click="router.visit(route('dashboard'))">
                    کد را ذخیره کردم؛ ورود به داشبورد
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

defineProps({
    step: { type: String, default: 'form' },
    recoveryCode: { type: String, default: null },
})

const showPass = ref(false)
const form = useForm({ username: '', password: '', password_confirmation: '' })

function submit() {
    form.post(route('setup.store'), { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<style scoped>
.gs-login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--gs-bg); padding: 1.5rem; }
.gs-login-card { width: 100%; max-width: 440px; background: var(--gs-bg-card); border: 1px solid var(--gs-border-strong); border-radius: 20px; padding: 2.5rem; box-shadow: var(--gs-shadow-gold); }
.gs-login-brand { text-align: center; margin-bottom: 1.5rem; }
.gs-login-icon { display: block; font-size: 2.5rem; margin-bottom: .5rem; filter: drop-shadow(0 0 12px rgba(201, 168, 76, 0.5)); }
.gs-password-wrap { position: relative; }
.gs-password-wrap .gs-input { padding-left: 2.75rem; }
.gs-eye-btn { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; }
.gs-recovery { text-align: center; font-family: ui-monospace, monospace; font-size: 1.25rem; letter-spacing: .12em; padding: 1rem; border-radius: 12px; border: 1px dashed var(--gs-gold-muted); color: var(--gs-gold); user-select: all; }
</style>