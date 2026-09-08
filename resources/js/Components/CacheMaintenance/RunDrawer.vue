<script setup>
/**
 * RunDrawer — کشوی جزئیات یک اجرا
 * مسیر: resources/js/Components/CacheMaintenance/RunDrawer.vue
 *
 * ورودی: run (CacheMaintenanceRun|null) — خروجی: @close
 */
import { computed } from 'vue'
import { X, Clock, Target, ListChecks, AlertTriangle, Terminal } from 'lucide-vue-next'

import { OPERATION_META, STATUS_META } from '@/Composables/useCacheMaintenanceApi'
import { jalali } from '@/Utils/format'

const props = defineProps({
    run: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const statusPill = computed(() =>
    props.run ? STATUS_META[props.run.status] || STATUS_META.failed : STATUS_META.failed,
)

const op = computed(() =>
    props.run ? OPERATION_META[props.run.operation] || { label: props.run.operation, icon: '•' } : null,
)

const targets = computed(() => {
    if (!props.run) return []
    const t = props.run.targets_json || []
    if (t.length === 1 && t[0] === 'inspect') return ['بازبینی (inspect)']
    if (t.length === 1 && t[0] === 'warm') return ['گرم‌سازی (warm)']
    return t
})

const summaryRows = computed(() => {
    if (!props.run) return []
    const s = props.run.summary_json || {}
    return Object.entries(s)
        .filter(([k]) => k !== 'message')
        .map(([key, val]) => ({ key, value: formatValue(val) }))
})

const errors = computed(() => {
    if (!props.run) return []
    const e = props.run.errors_json || {}
    return Object.entries(e).map(([key, val]) => ({ key, value: val }))
})

function formatValue(val) {
    if (typeof val === 'string') return val
    if (val === null || val === undefined) return '—'
    if (Array.isArray(val)) return val.join('، ')
    if (typeof val === 'object') {
        const p = []
        for (const [k, v] of Object.entries(val)) {
            if (v && typeof v === 'object') p.push(`${k}: ${formatValue(v)}`)
            else p.push(`${k}: ${v}`)
        }
        return p.join(' · ')
    }
    return String(val)
}

function dateOf(run) {
    return jalali((run.started_at || run.created_at || '').slice(0, 10))
}

function timeOf(run) {
    const iso = run.started_at || run.created_at || ''
    return iso.slice(11, 19)
}
</script>

<template>
    <Teleport to="body">
        <Transition name="cm-drawer-mask">
            <div v-if="run" class="cm-drawer-mask" @click="emit('close')" />
        </Transition>

        <Transition name="cm-drawer">
            <aside v-if="run" class="cm-drawer" aria-modal="true" role="dialog">
                <div class="cm-drawer__head">
                    <div>
                        <p class="cm-drawer__title">
                            {{ op?.icon }} {{ op?.label }} — اجرای #{{ run.id }}
                        </p>
                        <span class="cm-pill" :class="statusPill.className" style="margin-top:0.4rem">
                            {{ statusPill.icon }} {{ statusPill.label }}
                            <span v-if="run.is_dry_run"> · Dry-run</span>
                        </span>
                    </div>
                    <button type="button" class="cm-btn cm-btn--ghost" aria-label="بستن" @click="emit('close')">
                        <X :size="18" />
                    </button>
                </div>

                <div class="cm-drawer__body">
                    <!-- اطلاعات زمان -->
                    <div class="cm-env">
                        <div class="cm-env__row">
                            <span class="cm-env__k"><Clock :size="14" /> تاریخ</span>
                            <span class="cm-env__v">{{ dateOf(run) }} {{ timeOf(run) }}</span>
                        </div>
                        <div class="cm-env__row">
                            <span class="cm-env__k"><Clock :size="14" /> مدت اجرا</span>
                            <span class="cm-env__v">{{ Math.round(run.duration_ms / 100) / 10 }} ثانیه</span>
                        </div>
                    </div>

                    <!-- targetها -->
                    <section>
                        <p class="cm-legend"><span class="cm-legend__icon"><Target :size="16" /></span> بخش‌های هدف</p>
                        <div style="display:flex; flex-wrap:wrap; gap:0.4rem">
                            <span v-for="t in targets" :key="t" class="cm-pill cm-pill--muted">{{ t }}</span>
                        </div>
                    </section>

                    <!-- خلاصه -->
                    <section v-if="summaryRows.length">
                        <p class="cm-legend"><span class="cm-legend__icon"><ListChecks :size="16" /></span> خلاصه</p>
                        <div class="cm-result">
                            <div class="cm-result__body">
                                <div v-for="(r, i) in summaryRows" :key="i" class="cm-result__row">
                                    <span>{{ r.key }}</span>
                                    <b>{{ r.value }}</b>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- خطاها -->
                    <section v-if="errors.length">
                        <p class="cm-legend">
                            <span class="cm-legend__icon" style="color:var(--gs-error); background:var(--gs-error-soft)">
                                <AlertTriangle :size="16" />
                            </span>
                            خطاها
                        </p>
                        <div v-for="(e, i) in errors" :key="i" class="cm-reco__item">
                            <span class="cm-reco__dot" style="background:var(--gs-error)" />
                            <span><b>{{ e.key }}:</b> {{ e.value }}</span>
                        </div>
                    </section>

                    <!-- خروجی کنسول -->
                    <section v-if="run.console_output">
                        <p class="cm-legend"><span class="cm-legend__icon"><Terminal :size="16" /></span> خروجی کنسول</p>
                        <pre class="cm-input" style="min-height:120px; direction:ltr; text-align:left; font-size:0.72rem; overflow:auto; white-space:pre-wrap">{{ run.console_output }}</pre>
                    </section>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
