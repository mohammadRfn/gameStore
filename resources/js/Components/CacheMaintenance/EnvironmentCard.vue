<script setup>
/**
 * EnvironmentCard — نمایش مشخصات محیط اجرا (PHP، لاراول، درایورها)
 * مسیر: resources/js/Components/CacheMaintenance/EnvironmentCard.vue
 *
 * ورودی دقیقاً metrics.environment از GET /settings/cache/overview است.
 */
import { computed } from 'vue'

const props = defineProps({
    env: { type: Object, default: () => ({}) },
})

/** نگاشت کلیدهای environment بک‌اند به برچسب فارسی + آیکون */
const ENV_UI = {
    app_env: { label: 'محیط', icon: '🌱' },
    cache_driver: { label: 'درایور کش', icon: '⚡' },
    session_driver: { label: 'درایور نشست', icon: '🔑' },
    queue_connection: { label: 'صف', icon: '🛰' },
    database_driver: { label: 'درایور دیتابیس', icon: '🗄' },
    php_version: { label: 'نسخهٔ PHP', icon: '🐘' },
    laravel_version: { label: 'نسخهٔ Laravel', icon: '🚀' },
    os: { label: 'سیستم‌عامل', icon: '🖥' },
}

const rows = computed(() =>
    Object.entries(props.env || {}).map(([key, value]) => ({
        key,
        ui: ENV_UI[key] || { label: key, icon: '•' },
        value: value ?? '—',
    })),
)
</script>

<template>
    <div class="cm-env">
        <div v-for="row in rows" :key="row.key" class="cm-env__row">
            <span class="cm-env__k">
                <span>{{ row.ui.icon }}</span>
                {{ row.ui.label }}
            </span>
            <span class="cm-env__v">{{ row.value }}</span>
        </div>
    </div>
</template>
