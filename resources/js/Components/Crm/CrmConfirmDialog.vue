<script setup>
/**
 * CrmConfirmDialog — مودال تأیید با ورود سه‌بعدی (حذف مشتری / درخواست)
 * مسیر: resources/js/Components/Crm/CrmConfirmDialog.vue
 *
 * استفاده:
 *   <CrmConfirmDialog
 *     v-model="open" title="حذف پروندهٔ مشتری" :highlight="target?.name"
 *     message="این عملیات قابل بازگشت نیست." :loading="busy" @confirm="doDelete" />
 */
import { onBeforeUnmount, watch } from 'vue'
import { AlertTriangle, RefreshCw, Trash2 } from 'lucide-vue-next'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'تأیید عملیات' },
    message: { type: String, default: 'آیا از انجام این عملیات مطمئن هستید؟' },
    highlight: { type: String, default: '' },
    confirmLabel: { type: String, default: 'تأیید و حذف' },
    tone: { type: String, default: 'danger' }, // danger | gold
    loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

function close() {
    if (props.loading) return
    emit('update:modelValue', false)
    emit('cancel')
}

function onKey(e) {
    if (e.key === 'Escape') close()
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) document.addEventListener('keydown', onKey)
        else document.removeEventListener('keydown', onKey)
    },
)

onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
    <Teleport to="body">
        <Transition name="crm-modal-fade">
            <div v-if="modelValue" class="crm-modal-overlay" @click.self="close">
                <Transition name="crm-modal-3d" appear>
                    <div
                        v-if="modelValue"
                        class="a3d-holo crm-modal"
                        :class="`crm-modal--${tone}`"
                        role="alertdialog"
                        aria-modal="true"
                    >
                        <span class="crm-modal__icon">
                            <Trash2 v-if="tone === 'danger'" :size="28" />
                            <AlertTriangle v-else :size="28" />
                        </span>

                        <h3 class="crm-modal__title">{{ title }}</h3>
                        <p class="crm-modal__msg">{{ message }}</p>
                        <span v-if="highlight" class="crm-modal__hl">{{ highlight }}</span>

                        <div class="crm-modal__actions">
                            <button type="button" class="a3d-btn a3d-btn--ghost" :disabled="loading" @click="close">
                                انصراف
                            </button>
                            <button
                                type="button"
                                class="a3d-btn"
                                :class="tone === 'danger' ? 'a3d-btn--danger' : 'a3d-btn--gold'"
                                :disabled="loading"
                                @click="emit('confirm')"
                            >
                                <RefreshCw v-if="loading" :size="14" class="crm-spin" />
                                {{ loading ? 'در حال انجام…' : confirmLabel }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
