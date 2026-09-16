<script setup>
/**
 * صفحه لیست مشتریان — نسخه بازطراحی‌شده لوکس ۳D
 * مسیر: resources/js/Pages/Customers/Index.vue
 * هماهنگ با Modules/Customer/Http/Controllers/CustomerController
 */
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Users,
    UserPlus,
    Search,
    Filter,
    Phone,
    Mail,
    MapPin,
    Eye,
    Edit3,
    Trash2,
    LayoutGrid,
    List,
    X,
    AlertTriangle,
    CheckCircle2,
    Sparkles,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { faInt } from '@/Utils/format'

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

/* وضعیت فیلترها */
const searchFilter = ref(props.filters?.search || '')
const requestStatusFilter = ref(props.filters?.request_status || '')
const viewMode = ref('grid') // 'grid' | 'table'

const hasFilters = computed(() => searchFilter.value !== '' || requestStatusFilter.value !== '')

let debounceTimer = null
function debounceSearch() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(applyFilters, 320)
}

function applyFilters() {
    router.get(
        route('customers.index'),
        {
            search: searchFilter.value || undefined,
            request_status: requestStatusFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    )
}

function clearFilters() {
    searchFilter.value = ''
    requestStatusFilter.value = ''
    applyFilters()
}

/* حذف مشتری */
const deleteTarget = ref(null)
const isDeleting = ref(false)

function confirmDelete(customer) {
    deleteTarget.value = customer
}

function doDelete() {
    if (!deleteTarget.value) return
    isDeleting.value = true
    router.delete(route('customers.destroy', deleteTarget.value.id), {
        onFinish: () => {
            isDeleting.value = false
            deleteTarget.value = null
        },
    })
}
</script>

<template>
    <AppLayout>
        <Head title="مدیریت مشتریان" />

        <!-- پس‌زمینه محیطی -->
        <div class="a3d-scene st-scene" aria-hidden="true">
            <span class="a3d-grid-floor" />
            <span class="a3d-orb a3d-orb--gold a3d-float-a" style="width: 440px; height: 440px; top: -140px; inset-inline-end: 4%" />
            <span class="a3d-orb a3d-orb--violet a3d-float-b" style="width: 360px; height: 360px; bottom: -100px; inset-inline-start: 2%" />
        </div>

        <div class="st-page relative z-10 space-y-6 pb-12">
            <!-- هیرو و سربرگ -->
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="st-chip st-chip--live">
                            <Users :size="13" />
                            مشتریان گیم‌استور
                        </span>
                        <span class="text-xs text-neutral-400">CRM & Profiles</span>
                    </div>

                    <h1 class="st-hero__title">
                        مدیریت و پرونده <span>مشتریان</span>
                    </h1>
                    <p class="st-hero__lead">
                        مجموعاً {{ faInt(customers.total || 0) }} مشتری در باشگاه مشتریان ثبت شده است
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link :href="route('customers.create')" class="gs-btn-gold">
                        <UserPlus :size="18" />
                        <span>ثبت مشتری جدید</span>
                    </Link>
                </div>
            </header>

            <!-- نوار فیلتر و جستجوی مدرن -->
            <section class="st-card p-4 rounded-2xl flex flex-wrap items-center justify-between gap-4" v-reveal="{ delay: 100 }">
                <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[280px]">
                    <div class="relative flex-1 min-w-[220px]">
                        <Search :size="16" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-neutral-400" />
                        <input
                            v-model="searchFilter"
                            type="search"
                            class="w-full bg-neutral-900/80 border border-neutral-700/60 focus:border-amber-400/80 rounded-xl py-2.5 pr-10 pl-4 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500"
                            placeholder="جستجو بر اساس نام، شماره تلفن یا ایمیل..."
                            @input="debounceSearch"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="requestStatusFilter"
                            class="bg-neutral-900/80 border border-neutral-700/60 text-xs text-neutral-300 rounded-xl py-2.5 px-3 outline-none focus:border-amber-400"
                            @change="applyFilters"
                        >
                            <option value="">همه وضعیت‌های درخواست</option>
                            <option value="pending">در انتظار</option>
                            <option value="in_progress">در جریان</option>
                            <option value="completed">تکمیل شده</option>
                            <option value="canceled">لغو شده</option>
                        </select>

                        <button
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="px-3 py-2 rounded-xl bg-neutral-800 text-xs text-neutral-400 hover:text-amber-300 hover:bg-neutral-700 flex items-center gap-1 transition-colors"
                        >
                            <X :size="14" /> پاکسازی
                        </button>
                    </div>
                </div>

                <!-- سوییچ نحوه نمایش -->
                <div class="flex items-center gap-1 bg-neutral-900/80 p-1 rounded-xl border border-neutral-800">
                    <button
                        @click="viewMode = 'grid'"
                        :class="viewMode === 'grid' ? 'bg-amber-500/20 text-amber-300' : 'text-neutral-400 hover:text-neutral-200'"
                        class="p-1.5 rounded-lg transition-colors"
                        title="نمای کارتی ۳D"
                    >
                        <LayoutGrid :size="16" />
                    </button>
                    <button
                        @click="viewMode = 'table'"
                        :class="viewMode === 'table' ? 'bg-amber-500/20 text-amber-300' : 'text-neutral-400 hover:text-neutral-200'"
                        class="p-1.5 rounded-lg transition-colors"
                        title="نمای جدول"
                    >
                        <List :size="16" />
                    </button>
                </div>
            </section>

            <!-- محتوای مشتریان: نمای کارتی سه‌بعدی -->
            <div v-if="viewMode === 'grid' && customers.data.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" v-reveal="{ delay: 150 }">
                <article
                    v-for="c in customers.data"
                    :key="c.id"
                    v-tilt="{ max: 8, scale: 1.02, lift: 12 }"
                    class="st-card p-5 rounded-2xl flex flex-col justify-between group transition-all duration-300 hover:border-amber-500/40 relative overflow-hidden"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-neutral-800 border border-amber-500/30 flex items-center justify-center font-bold text-amber-300 text-base shadow-md">
                                    {{ c.name.slice(0, 1) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-neutral-100 text-sm group-hover:text-amber-300 transition-colors">
                                        {{ c.name }}
                                    </h3>
                                    <p class="text-[11px] text-neutral-400">شناسه: #{{ faInt(c.id) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- اطلاعات تماس -->
                        <div class="space-y-1.5 pt-2 text-xs text-neutral-300">
                            <div v-if="c.phone" class="flex items-center gap-2 text-neutral-300">
                                <Phone :size="13" class="text-amber-400/80" />
                                <span dir="ltr">{{ c.phone }}</span>
                            </div>
                            <div v-if="c.email" class="flex items-center gap-2 text-neutral-400">
                                <Mail :size="13" class="text-blue-400/80" />
                                <span class="truncate">{{ c.email }}</span>
                            </div>
                            <div v-if="c.address" class="flex items-center gap-2 text-neutral-400">
                                <MapPin :size="13" class="text-emerald-400/80 flex-shrink-0" />
                                <span class="truncate text-[11px]">{{ c.address }}</span>
                            </div>
                        </div>

                        <!-- بج‌های آماری -->
                        <div class="flex items-center gap-2 pt-2 border-t border-neutral-800/60">
                            <span class="st-chip st-chip--info text-[10px]">
                                {{ faInt(c.requests_count || 0) }} درخواست
                            </span>
                            <span class="st-chip st-chip--gold text-[10px]">
                                {{ faInt(c.invoices_count || 0) }} فاکتور
                            </span>
                        </div>
                    </div>

                    <!-- دکمه‌های عملیات -->
                    <div class="flex items-center justify-between gap-2 pt-4 mt-3 border-t border-neutral-800/80">
                        <Link :href="route('customers.show', c.id)" class="flex-1 py-1.5 px-3 rounded-xl bg-white/[0.04] hover:bg-amber-500/15 hover:text-amber-300 text-neutral-300 text-xs text-center font-medium transition-colors">
                            مشاهده پرونده
                        </Link>
                        <Link :href="route('customers.edit', c.id)" class="p-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors" title="ویرایش">
                            <Edit3 :size="15" />
                        </Link>
                        <button @click="confirmDelete(c)" class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors" title="حذف">
                            <Trash2 :size="15" />
                        </button>
                    </div>
                </article>
            </div>

            <!-- محتوای مشتریان: نمای جدول لوکس -->
            <div v-else-if="viewMode === 'table' && customers.data.length" class="st-card rounded-2xl overflow-hidden" v-reveal="{ delay: 150 }">
                <table class="w-full text-right text-xs">
                    <thead>
                        <tr class="text-neutral-400 border-b border-neutral-800 bg-white/[0.02]">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">نام مشتری</th>
                            <th class="py-3 px-4">تلفن</th>
                            <th class="py-3 px-4">ایمیل</th>
                            <th class="py-3 px-4">درخواست‌ها</th>
                            <th class="py-3 px-4">فاکتورها</th>
                            <th class="py-3 px-4 text-left">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-800/50">
                        <tr v-for="c in customers.data" :key="c.id" class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3.5 px-4 text-neutral-500 font-mono">{{ faInt(c.id) }}</td>
                            <td class="py-3.5 px-4 font-bold text-neutral-200">{{ c.name }}</td>
                            <td class="py-3.5 px-4 font-mono text-neutral-300" dir="ltr">{{ c.phone || '—' }}</td>
                            <td class="py-3.5 px-4 text-neutral-400">{{ c.email || '—' }}</td>
                            <td class="py-3.5 px-4">
                                <span class="st-chip st-chip--info text-[10px]">{{ faInt(c.requests_count || 0) }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="st-chip st-chip--gold text-[10px]">{{ faInt(c.invoices_count || 0) }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Link :href="route('customers.show', c.id)" class="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300">
                                        <Eye :size="14" />
                                    </Link>
                                    <Link :href="route('customers.edit', c.id)" class="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300">
                                        <Edit3 :size="14" />
                                    </Link>
                                    <button @click="confirmDelete(c)" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400">
                                        <Trash2 :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- حالت خالی -->
            <div v-else class="st-card p-12 text-center rounded-2xl" v-reveal="{ delay: 150 }">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 mx-auto flex items-center justify-center text-amber-400 mb-3">
                    <Users :size="32" />
                </div>
                <h3 class="text-sm font-bold text-neutral-200">مشتری‌ای مطابق با جستجو پیدا نشد</h3>
                <p class="text-xs text-neutral-400 mt-1">می‌توانید فیلترها را ریست کنید یا مشتری جدید اضافه نمایید</p>
                <div class="mt-4 flex items-center justify-center gap-2">
                    <button v-if="hasFilters" @click="clearFilters" class="gs-btn-ghost text-xs">
                        پاکسازی فیلترها
                    </button>
                    <Link :href="route('customers.create')" class="gs-btn-gold text-xs">
                        + ثبت اولین مشتری
                    </Link>
                </div>
            </div>

            <!-- صفحه‌بندی -->
            <div v-if="customers.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
                <Link
                    v-if="customers.prev_page_url"
                    :href="customers.prev_page_url"
                    class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-xs text-neutral-300 flex items-center gap-1"
                >
                    <ChevronRight :size="14" /> قبلی
                </Link>
                <span class="text-xs text-neutral-400 px-3">
                    صفحه {{ faInt(customers.current_page) }} از {{ faInt(customers.last_page) }}
                </span>
                <Link
                    v-if="customers.next_page_url"
                    :href="customers.next_page_url"
                    class="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-xs text-neutral-300 flex items-center gap-1"
                >
                    بعدی <ChevronLeft :size="14" />
                </Link>
            </div>

            <!-- مودال حذف ایمن -->
            <div v-if="deleteTarget" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="st-card max-w-md w-full p-6 rounded-2xl border border-rose-500/30 space-y-4 animate-in fade-in zoom-in-95">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                        <AlertTriangle :size="24" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-neutral-100">حذف پرونده مشتری</h3>
                        <p class="text-xs text-neutral-400 mt-1 leading-relaxed">
                            آیا از حذف اطلاعات «<span class="text-amber-300 font-semibold">{{ deleteTarget.name }}</span>» اطمینان دارید؟ تمامی ارجاعات مربوطه پاک خواهند شد.
                        </p>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button @click="deleteTarget = null" class="gs-btn-ghost text-xs">انصراف</button>
                        <button @click="doDelete" :disabled="isDeleting" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs">
                            {{ isDeleting ? 'در حال حذف...' : 'تأیید و حذف' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
