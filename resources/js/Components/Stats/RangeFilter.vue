<template>
    <form class="gs-filter" @submit.prevent="apply">
        <div class="gs-filter-ranges">
            <button v-for="r in ranges" :key="r.id" type="button" class="gs-btn"
                :class="range === r.id ? 'gs-btn-primary' : 'gs-btn-ghost'" @click="setRange(r.id)">
                {{ r.label }}
            </button>
        </div>

        <span class="filter-sep"></span>

        <label class="gs-check">
            <input type="checkbox" v-model="paidOnly" />
            فقط وصول‌شده
        </label>

        <JalaliDateInput v-model="from" class="gs-date" placeholder="از تاریخ" />
        <JalaliDateInput v-model="to" class="gs-date" placeholder="تا تاریخ" />

        <button type="submit" class="gs-btn gs-btn-primary">
            <Search :size="15" />
            اعمال
        </button>

        <button v-if="from || to" type="button" class="gs-btn gs-btn-ghost" title="پاک کردن بازهٔ دستی"
            @click="clearDates">
            <X :size="15" />
        </button>
    </form>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Search, X } from '@lucide/vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    paidOnly: { type: Boolean, default: false },
    range: { type: String, default: 'month' },
    routeName: { type: String, default: 'stats.index' },
})

const ranges = [
    { id: 'today', label: 'امروز' },
    { id: 'week', label: 'هفته' },
    { id: 'last_30', label: '۳۰ روز' },
    { id: 'month', label: 'ماه' },
    { id: 'year', label: 'سال' },
]

const range = ref(props.range)
const paidOnly = ref(props.paidOnly)
const from = ref(props.from)
const to = ref(props.to)

// همگام‌سازی وقتی صفحه با query متفاوت باز شود
watch(() => [props.range, props.paidOnly, props.from, props.to], ([r, p, f, t]) => {
    range.value = r
    paidOnly.value = p
    from.value = f
    to.value = t
})

function setRange(id) {
    range.value = id
    from.value = ''
    to.value = ''
    apply()
}

function clearDates() {
    from.value = ''
    to.value = ''
    apply()
}

function apply() {
    router.get(route(props.routeName), {
        from: from.value || undefined,
        to: to.value || undefined,
        paid_only: paidOnly.value ? 1 : 0,
        range: range.value,
    }, { preserveState: true, replace: true, preserveScroll: true })
}
</script>

<style scoped>
.filter-sep {
    width: 1px;
    align-self: stretch;
    min-height: 28px;
    background: var(--gs-border);
    margin: 0 0.1rem;
}
</style>
