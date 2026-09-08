<script setup>
/**
 * OptimizePanel — پنل بهینه‌سازی/گرم‌سازی کش
 * مسیر: resources/js/Components/CacheMaintenance/OptimizePanel.vue
 *
 * ورودی‌ها: busy (boolean)، result (CacheMaintenanceRun|null)
 * خروجی:   @optimize(payload) — مطابق CacheMaintenanceRequest
 */
import { computed, reactive } from 'vue'
import { Zap } from 'lucide-vue-next'

import GsRow from '@/Components/Settings/GsRow.vue'
import GsToggle from '@/Components/Settings/GsToggle.vue'
import { STATUS_META } from '@/Composables/useCacheMaintenanceApi'

const props = defineProps({
    busy: { type: Boolean, default: false },
    result: { type: Object, default: null },
})

const emit = defineEmits(['optimize'])

const options = reactive({
    dry_run: false,
    warm_config: true,
    warm_views: true,
    warm_settings: true,
})

function runOptimize() {
    emit('optimize', { ...options })
}

const resultHead = computed(() => {
    if (!props.result) return null
    const meta = STATUS_META[props.result.status] || STATUS_META.failed
    const cls = props.result.status === 'completed' ? 'ok' : 'fail'
    return { meta, cls }
})

const warmRows = computed(() => {
    if (!props.result) return []
    const warm = props.result.summary_json?.warm || props.result.summary_json?.dry_run
    if (!warm) return []
    if (typeof warm === 'string') return [{ key: 'نتیجه', value: warm }]
    return Object.entries(warm).map(([key, val]) => ({
        key,
        value: typeof val === 'string' ? val : val?.ok ? 'انجام شد' : 'رد شد',
    }))
})
</script>

<template>
    <div class="st-card a3d-holo">
        <div class="st-sechead">
            <span class="st-sechead__icon"><Zap :size="21" /></span>
            <div>
                <h2 class="st-sechead__title">بهینه‌سازی و گرم‌سازی</h2>
                <p class="st-sechead__desc">بعد از نصب/آپدیت برنامه؛ کش‌ها را دوباره و امن می‌سازد</p>
            </div>
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">اجرای آزمایشی</p>
                <p class="st-row__desc">دستورها فقط بررسی می‌شوند و اجرا نمی‌شوند.</p>
            </div>
            <GsToggle v-model="options.dry_run" accent="green" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">کش تنظیمات</p>
                <p class="st-row__desc">فلاش و autoload سرویس تنظیمات</p>
            </div>
            <GsToggle v-model="options.warm_settings" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">config:cache</p>
                <p class="st-row__desc">کش پیکربندی لاراول</p>
            </div>
            <GsToggle v-model="options.warm_config" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">view:cache</p>
                <p class="st-row__desc">کامپایل viewهای Blade</p>
            </div>
            <GsToggle v-model="options.warm_views" />
        </div>

        <div style="display:flex; gap:0.7rem; margin-top:1.4rem; flex-wrap:wrap">
            <button
                type="button"
                class="cm-btn cm-btn--gold"
                :class="{ 'is-loading': busy }"
                :disabled="busy"
                @click="runOptimize"
            >
                <span class="cm-btn__spinner" />
                <Zap :size="16" />
                اجرای بهینه‌سازی
            </button>
        </div>

        <div v-if="result" class="cm-result" style="margin-top:1.3rem">
            <div class="cm-result__head" :class="`cm-result__head--${resultHead.cls}`">
                <span>{{ resultHead.meta.icon }}</span>
                {{ resultHead.meta.label }}
                <span v-if="result.is_dry_run" class="cm-pill cm-pill--info">Dry-run</span>
            </div>
            <div class="cm-result__body">
                <div v-for="(row, i) in warmRows" :key="i" class="cm-result__row">
                    <span>{{ row.key }}</span>
                    <b>{{ row.value }}</b>
                </div>
                <div v-if="result.duration_ms" class="cm-result__row">
                    <span>مدت اجرا</span>
                    <b>{{ Math.round(result.duration_ms / 100) / 10 }} ثانیه</b>
                </div>
            </div>
        </div>
    </div>
</template>
