<script setup>
/**
 * ToastHost — نمایشگر پیام‌های شناور
 * مسیر: resources/js/Components/Settings/ToastHost.vue
 *
 * استفاده:
 *   <ToastHost :toasts="toasts" @close="dismiss" />
 */
import { Check, Info, Trash2, X } from 'lucide-vue-next'

defineProps({
    /** [{ id, kind: 'success'|'info'|'danger', msg }] */
    toasts: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const ICONS = {
    success: Check,
    info: Info,
    danger: Trash2,
}

const BARS = {
    success: 'var(--gs-success)',
    info: 'var(--gs-info)',
    danger: 'var(--gs-error)',
}
</script>

<template>
    <Teleport to="body">
        <div class="st-toasts">
            <TransitionGroup name="st-toast-list">
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="st-toast"
                    :class="`st-toast--${t.kind}`"
                    role="status"
                >
                    <span class="st-toast__icon">
                        <component :is="ICONS[t.kind] || Info" :size="16" />
                    </span>

                    <p class="st-toast__msg">{{ t.msg }}</p>

                    <button
                        type="button"
                        class="st-toast__close"
                        aria-label="بستن"
                        @click="emit('close', t.id)"
                    >
                        <X :size="15" />
                    </button>

                    <span class="st-toast__bar" :style="{ background: BARS[t.kind] }" />
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
