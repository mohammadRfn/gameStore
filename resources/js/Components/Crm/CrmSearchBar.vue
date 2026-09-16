<script setup>
/**
 * CrmSearchBar — جستجو با آیکون، دکمهٔ پاک‌کردن و میان‌بر «/»
 * مسیر: resources/js/Components/Crm/CrmSearchBar.vue
 *
 * استفاده:
 *   <CrmSearchBar v-model="search" placeholder="جستجو…" :loading="busy" @clear="clearFilters" />
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RefreshCw, Search, X } from 'lucide-vue-next'

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'جستجو…' },
    loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'clear'])
const input = ref(null)

function onKey(e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return
    const t = e.target
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return
    e.preventDefault()
    input.value?.focus()
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

function clear() {
    emit('update:modelValue', '')
    emit('clear')
    input.value?.focus()
}
</script>

<template>
    <label class="crm-search">
        <RefreshCw v-if="loading" :size="16" class="crm-search__icon crm-spin" />
        <Search v-else :size="16" class="crm-search__icon" />

        <input
            ref="input"
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            autocomplete="off"
            @input="emit('update:modelValue', $event.target.value)"
            @keydown.esc="clear"
        />

        <button v-if="modelValue" type="button" class="crm-search__clear" aria-label="پاک‌کردن" @click="clear">
            <X :size="14" />
        </button>
        <kbd v-else class="crm-search__kbd">/</kbd>
    </label>
</template>
