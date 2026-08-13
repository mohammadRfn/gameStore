<template>
    <div class="gs-login-wrap">

        <!-- Card -->
        <div class="gs-login-card">

            <!-- Brand -->
            <div class="gs-login-brand">
                <span class="gs-login-icon">♟</span>
                <h1 class="gs-title">Game<span class="gs-gold-text">Shop</span></h1>
                <p class="gs-subtitle" style="margin-top:.25rem">ورود به پنل مدیریت</p>
            </div>

            <div class="gs-divider-gold"></div>

            <!-- Error -->
            <Transition name="gs-fade">
                <div v-if="form.errors.username || form.errors.password || form.errors.message"
                    class="gs-badge gs-badge-error" style="width:100%;justify-content:center;margin-bottom:1rem">
                    {{ form.errors.username || form.errors.password || form.errors.message }}
                </div>
            </Transition>

            <!-- Form -->
            <form @submit.prevent="submit">
                <!-- Username -->
                <div class="gs-input-group">
                    <label class="gs-input-label">نام کاربری</label>
                    <input v-model="form.username" type="text" class="gs-input"
                        :class="{ 'gs-input-error': form.errors.username }" placeholder="نام کاربری"
                        autocomplete="username" autofocus />
                    <span v-if="form.errors.username" class="gs-error-msg">{{ form.errors.username }}</span>
                </div>

                <!-- Password -->
                <div class="gs-input-group">
                    <label class="gs-input-label">رمز عبور</label>
                    <div class="gs-password-wrap">
                        <input v-model="form.password" :type="showPass ? 'text' : 'password'" class="gs-input"
                            :class="{ 'gs-input-error': form.errors.password }" placeholder="••••••••"
                            autocomplete="current-password" />
                        <button type="button" class="gs-eye-btn" @click="showPass = !showPass"
                            :aria-label="showPass ? 'مخفی کردن' : 'نمایش'">
                            {{ showPass ? '🙈' : '👁' }}
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="gs-error-msg">{{ form.errors.password }}</span>
                </div>

                <!-- Remember -->
                <div class="gs-remember-row">
                    <label class="gs-checkbox-label">
                        <input v-model="form.remember" type="checkbox" class="gs-checkbox" />
                        <span>مرا به خاطر بسپار</span>
                    </label>
                </div>

                <!-- Submit -->
                <button type="submit" class="gs-btn gs-btn-primary gs-btn-lg"
                    style="width:100%;margin-top:1.5rem;justify-content:center" :disabled="form.processing">
                    <span v-if="form.processing" class="gs-spinner"></span>
                    {{ form.processing ? 'در حال ورود...' : 'ورود به داشبورد' }}
                </button>
            </form>

            <div class="gs-divider-gold" style="margin-top:1.5rem"></div>
            <p class="gs-label" style="text-align:center">GameShop CRM v1.0</p>
        </div>

    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const showPass = ref(false)

const form = useForm({
    username: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<style scoped>
.gs-login-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gs-bg);
    padding: 1.5rem;
}

.gs-login-card {
    width: 100%;
    max-width: 420px;
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border-strong);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: var(--gs-shadow-gold);
}

.gs-login-brand {
    text-align: center;
    margin-bottom: 1.5rem;
}

.gs-login-icon {
    display: block;
    font-size: 2.5rem;
    margin-bottom: .5rem;
    filter: drop-shadow(0 0 12px rgba(201, 168, 76, 0.5));
}

.gs-password-wrap {
    position: relative;
}

.gs-password-wrap .gs-input {
    padding-left: 2.75rem;
}

.gs-eye-btn {
    position: absolute;
    left: .75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
    color: var(--gs-text-muted);
    transition: color var(--gs-transition);
}

.gs-eye-btn:hover {
    color: var(--gs-gold);
}

.gs-remember-row {
    margin-top: .5rem;
}

.gs-checkbox-label {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    color: var(--gs-text-secondary);
    cursor: pointer;
}

.gs-checkbox {
    accent-color: var(--gs-gold);
    width: 15px;
    height: 15px;
    cursor: pointer;
}

.gs-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(10, 10, 15, 0.3);
    border-top-color: #0a0a0f;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin-left: .5rem;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>