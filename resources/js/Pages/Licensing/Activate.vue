<template>
    <div class="gs-login-wrap">
        <div class="gs-login-card">
            <div class="gs-login-brand">
                <span class="gs-login-icon">♟</span>
                <h1 class="gs-title">Game<span class="gs-gold-text">Shop</span></h1>
                <p class="gs-subtitle" style="margin-top:.25rem">فعال‌سازی نرم‌افزار</p>
            </div>

            <div class="gs-divider-gold"></div>

            <!-- قفل شده (لایسنس فعال بوده ولی الان مسدود شده) -->
            <div v-if="local.status === 'locked'">
                <div class="gs-badge gs-badge-error" style="width:100%;justify-content:center">نرم‌افزار قفل شده است</div>
                <p class="gs-subtitle" style="margin-top:1rem;text-align:center">{{ local.lock_reason || 'لطفاً با پشتیبانی تماس بگیرید.' }}</p>
                <p class="gs-label" style="text-align:center;margin-top:.5rem">اثر انگشت دستگاه: {{ fingerprint.slice(0, 12) }}…</p>
            </div>

            <!-- در انتظار تأیید ادمین -->
            <template v-else-if="local.status === 'pending'">
                <div class="gs-badge gs-badge-warning" style="width:100%;justify-content:center">درخواست فعال‌سازی ارسال شد</div>
                <p class="gs-subtitle" style="margin-top:1rem;text-align:center">
                    منتظر تأیید مدیر و دریافت کد یک‌بارمصرف بمانید؛ به‌محض دریافت کد، آن را در فرم زیر وارد کنید.
                </p>
                <p class="gs-label" style="text-align:center;margin-top:.5rem">وضعیت هر {{ local.poll_after_seconds }} ثانیه خودکار بررسی می‌شود…</p>
                <div class="gs-divider-gold" style="margin:1.5rem 0"></div>
                <RedeemForm />
            </template>

            <!-- رد شده -->
            <template v-else-if="local.status === 'rejected'">
                <div class="gs-badge gs-badge-error" style="width:100%;justify-content:center">درخواست رد شد</div>
                <p class="gs-subtitle" style="margin-top:1rem;text-align:center">{{ local.reject_reason || '—' }}</p>
                <div class="gs-divider-gold" style="margin:1.5rem 0"></div>
                <RequestForm />
                <div class="gs-divider-gold" style="margin:1.5rem 0"></div>
                <RedeemForm />
            </template>

            <!-- فعال‌سازی‌نشده (حالت اولیه) -->
            <template v-else>
                <RequestForm />
                <div class="gs-divider-gold" style="margin:1.5rem 0"></div>
                <p class="gs-label" style="text-align:center;margin-bottom:.75rem">کد یک‌بارمصرف را از پیش دارید؟</p>
                <RedeemForm />
            </template>

            <div class="gs-divider-gold" style="margin-top:1.5rem"></div>
            <p class="gs-label" style="text-align:center">GameShop CRM v1.0</p>
        </div>
    </div>
</template>

<script setup>
import { reactive, onMounted, onUnmounted } from 'vue'
import RequestForm from '@/Components/Licensing/RequestForm.vue'
import RedeemForm from '@/Components/Licensing/RedeemForm.vue'

const props = defineProps({
    state: { type: Object, required: true },
    fingerprint: { type: String, required: true },
})

const local = reactive({ ...props.state })
let timer = null

function poll() {
    window.axios.get(route('licensing.poll')).then((res) => {
        Object.assign(local, res.data.state)
    }).catch(() => {
        // خطای موقت شبکه؛ در دور بعدی دوباره تلاش می‌شود
    })
}

onMounted(() => {
    if (local.status === 'pending') {
        timer = setInterval(poll, Math.max(5, local.poll_after_seconds || 30) * 1000)
    }
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
})
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
    max-width: 440px;
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
    to { transform: rotate(360deg); }
}
</style>