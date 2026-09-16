<script setup>
/**
 * صفحه لیست درخواست‌ها — نسخه بازطراحی‌شده ۳D
 * مسیر: resources/js/Pages/Requests/Index.vue
 * هماهنگ با Modules/Request/Http/Controllers/RequestController
 */
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ClipboardList,
    Plus,
    Search,
    Filter,
    Clock,
    Wrench,
    CheckCircle2,
    XCircle,
    Eye,
    Edit3,
    Trash2,
    Tag,
    ChevronLeft,
    ChevronRight,
    AlertCircle,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'

const props = defineProps({
    requests: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const searchFilter = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')

const hasFilters = computed(() => searchFilter.value !== '' || statusFilter.value !== '')

let debounceTimer = null
function debounceSearch() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(applyFilters, 320)
}

function applyFilters() {
    router.get(
        route('requests.index'),
        {
            search: searchFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    )
}

function setStatus(st) {
    statusFilter.value = st
    applyFilters()
}

function clearFilters() {
    searchFilter.value = ''
    statusFilter.value = ''
    applyFilters()
}

function statusBadgeClass(status) {
    const map = {
        pending: 'st-chip--warning',
        in_progress: 'st-chip--info',
        completed: 'st-chip--success',
        canceled: 'st-chip--error',
    }
    return map[status] ?? 'st-chip--plain'
}

function statusLabel(status) {
    const map = {
        pending: 'در انتظار',
        in_progress: 'در جریان',
        completed: 'تکمیل شده',
        canceled: 'لغو شده',
    }
    return map[status] ?? status
}

/* حذف درخواست */
const deleteTarget = ref(null)
const isDeleting = ref(false)

function confirmDelete(req) {
    deleteTarget.value = req
}

function doDelete() {
    if (!deleteTarget.value) return
    isDeleting.value = true
    router.delete(route('requests.destroy', deleteTarget.value.id), {
        onFinish: () => {
            isDeleting.value = false
            deleteTarget.value = null
        },
    })
}
</script>

<template>
    <AppLayout>
        <Head title="درخواست‌های سرویس و تعمیر" />

        <div class="a3d-scene st-scene" aria-hidden="true">
            <span class="a3d-grid-floor" />
            <span class="a3d-orb a3d-orb--gold a3d-float-a" style="width: 460px; height: 460px; top: -160px; inset-inline-end: 5%" />
            <span class="a3d-orb a3d-orb--blue a3d-float-b" style="width: 380px; height: 380px; bottom: -120px; inset-inline-start: 4%" />
        </div>

        <div class="st-page relative z-10 space-y-6 pb-12">
            <!-- هیرو -->
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="st-chip st-chip--live">
                            <Wrench :size="13" />
                            پایپ‌لاین خدمات فنی
                        </span>
                        <span class="text-xs text-neutral-400">Hardware & Software Services</span>
                    </div>

                    <h1 class="st-hero__title">
                        مدیریت <span>درخواست‌های خدمات</span>
                    </h1>
                    <p class="st-hero__lead">
                        مجموعاً {{ faInt(requests.total || 0) }} درخواست در سیستم ثبت شده است
                    </p>
                </div>

                <div>
                    <Link :href="route('requests.create')" class="gs-btn-gold">
                        <Plus :size="18" />
                        <span>ثبت درخواست جدید</span>
                    </Link>
                </div>
            </header>

            <!-- تب‌های وضعیت بالا -->
            <div class="flex flex-wrap items-center gap-2" v-reveal="{ delay: 90 }">
                <button
                    @click="setStatus('')"
                    :class="statusFilter === '' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-neutral-900/60 text-neutral-400 border-neutral-800 hover:text-neutral-200'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all"
                >
                    همه درخواست‌ها
                </button>
                <button
                    @click="setStatus('pending')"
                    :class="statusFilter === 'pending' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-neutral-900/60 text-neutral-400 border-neutral-800 hover:text-neutral-200'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5"
                >
                    <Clock :size="13" /> در انتظار
                </button>
                <button
                    @click="setStatus('in_progress')"
                    :class="statusFilter === 'in_progress' ? 'bg-blue-500/20 text-blue-300 border-blue-500/40' : 'bg-neutral-900/60 text-neutral-400 border-neutral-800 hover:text-neutral-200'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5"
                >
                    <Wrench :size="13" /> در جریان
                </button>
                <button
                    @click="setStatus('completed')"
                    :class="statusFilter === 'completed' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-neutral-900/60 text-neutral-400 border-neutral-800 hover:text-neutral-200'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5"
                >
                    <CheckCircle2 :size="13" /> تکمیل شده
                </button>
                <button
                    @click="setStatus('canceled')"
                    :class="statusFilter === 'canceled' ? 'bg-rose-500/20 text-rose-300 border-rose-500/40' : 'bg-neutral-900/60 text-neutral-400 border-neutral-800 hover:text-neutral-200'"
                    class="px-4 py-2 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5"
                >
                    <XCircle :size="13" /> لغو شده
                </button>
            </div>

            <!-- جستجو -->
            <div class="st-card p-4 rounded-2xl flex items-center gap-3" v-reveal="{ delay: 120 }">
                <Search :size="16" class="text-neutral-400" />
                <input
                    v-model="searchFilter"
                    type="search"
                    class="w-full bg-transparent text-xs text-neutral-200 outline-none placeholder:text-neutral-500"
                    placeholder="جستجو در نام مشتری، شرح مشکل دستگاه..."
                    @input="debounceSearch"
                />
                <button v-if="hasFilters" @click="clearFilters" class="text-xs text-neutral-400 hover:text-amber-300">
                    پاکسازی
                </button>
            </div>

            <!-- جدول درخواست‌ها -->
            <div class="st-card rounded-2xl overflow-hidden" v-reveal="{ delay: 150 }">
                <table v-if="requests.data.length" class="w-full text-right text-xs">
                    <thead>
                        <tr class="text-neutral-400 border-b border-neutral-800 bg-white/[0.02]">
                            <th class="py-3 px-4">کد</th>
                            <th class="py-3 px-4">مشتری</th>
                            <th class="py-3 px-4">شرح درخواست</th>
                            <th class="py-3 px-4">دسته‌بندی</th>
                            <th class="py-3 px-4">وضعیت</th>
                            <th class="py-3 px-4 text-left">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800/50">
                        <tr v-for="req in requests.data" :key="req.id" class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3.5 px-4 font-mono font-bold text-amber-400">#{{ faInt(req.id) }}</td>
                            <td class="py-3.5 px-4 font-bold text-neutral-200">
                                <Link v-if="req.customer" :href="route('customers.show', req.customer.id)" class="hover:text-amber-300">
                                    {{ req.customer_name }}
                                </Link>
                                <span v-else>{{ req.customer_name }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-neutral-300 max-w-[280px] truncate leading-relaxed">
                                {{ req.description }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="cat in req.categories"
                                        :key="cat.id"
                                        class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-300 border border-amber-500/20 text-[10px]"
                                    >
                                        {{ cat.name }}
                                    </span>
                                    <span v-if="!req.categories?.length" class="text-neutral-500 text-[11px]">—</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="st-chip text-[11px]" :class="statusBadgeClass(req.status)">
                                    {{ statusLabel(req.status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Link :href="route('requests.show', req.id)" class="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300" title="مشاهده">
                                        <Eye :size="14" />
                                    </Link>
                                    <Link :href="route('requests.edit', req.id)" class="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300" title="ویرایش">
                                        <Edit3 :size="14" />
                                    </Link>
                                    <button @click="confirmDelete(req)" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400" title="حذف">
                                        <Trash2 :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="p-12 text-center">
                    <p class="text-xs text-neutral-400">درخواستی یافت نشد</p>
                </div>
            </div>

            <!-- صفحه‌بندی -->
            <div v-if="requests.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
                <Link
                    v-if="requests.prev_page_url"
                    :href="requests.prev_page_url"
                    class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-xs text-neutral-300 flex items-center gap-1"
                >
                    <ChevronRight :size="14" /> قبلی
                </Link>
                <span class="text-xs text-neutral-400 px-3">
                    صفحه {{ faInt(requests.current_page) }} از {{ faInt(requests.last_page) }}
                </span>
                <Link
                    v-if="requests.next_page_url"
                    :href="requests.next_page_url"
                    class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-xs text-neutral-300 flex items-center gap-1"
                >
                    بعدی <ChevronLeft :size="14" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
