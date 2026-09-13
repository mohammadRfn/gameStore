<template>
    <div class="menu-page">
        <div class="menu-card">
            <h1>ورود به منوی دیجیتال</h1>
            <p v-if="error || form.errors.code" class="menu-error">{{ error || form.errors.code }}</p>
            <form @submit.prevent="submit">
                <input v-model="form.code" type="text" inputmode="numeric" maxlength="4" placeholder="کد ۴ رقمی"
                    class="menu-input" autofocus />
                <button type="submit" class="menu-btn" :disabled="form.processing">ورود</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

defineProps({ error: String })
const form = useForm({ code: '' })

function submit() {
    form.post(route('menu.verify'))
}
</script>

<style scoped>
.menu-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #14141a; direction: rtl; font-family: Vazirmatn, sans-serif; }
.menu-card { background: #1e1e26; border-radius: 16px; padding: 2rem; width: 100%; max-width: 340px; text-align: center; color: #eee; }
.menu-input { width: 100%; font-size: 1.5rem; text-align: center; letter-spacing: .5rem; padding: .75rem; border-radius: 10px; border: 1px solid #444; background: #111; color: #fff; margin-bottom: 1rem; }
.menu-btn { width: 100%; padding: .75rem; border-radius: 10px; border: none; background: #c9a24b; color: #111; font-weight: 700; cursor: pointer; }
.menu-error { color: #e05c5c; margin-bottom: .75rem; }
</style>