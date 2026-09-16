<script setup>
/**
 * فهرست مشتریان — بازطراحی هم‌سطح با صفحهٔ تنظیمات
 * مسیر: resources/js/Pages/Customers/Index.vue
 * ---------------------------------------------------------------------------
 * هماهنگ با Modules\Customer\Http\Controllers\CustomerController@index:
 *   props.customers  → paginate(10)->withQueryString()  { data[], links[], total, ... }
 *   props.filters    → { search, name, email, request_status, invoice_status }
 * فیلترها با router.get(route('customers.index'), {...}) ارسال می‌شوند (همان کلیدها).
 * حذف: router.delete(route('customers.destroy', id)) → flash success از بک‌اند.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    CalendarDays,
    CheckCircle2,
    Clock,
    Eye,
    Filter,
    LayoutGrid,
    List,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Receipt,
    Trash2,
    UserPlus,
    Users,
    Wrench,
    X,
    XCircle,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { useToasts } from '@/Composables/useToasts'
import { faInt } from '@/Utils/format'
import { avatarHue, clip, dateFa, initials } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CrmOrbit from '@/Components/Crm/CrmOrbit.vue'
import CrmSearchBar from '@/Components/Crm/CrmSearchBar.vue'
import CrmSegmented from '@/Components/Crm/CrmSegmented.vue'
import CrmPagination from '@/Components/Crm/CrmPagination.vue'
import CrmEmptyState from '@/Components/Crm/CrmEmptyState.vue'
import CrmConfirmDialog from '@/Components/Crm/CrmConfirmDialog.vue'
import GsSelect from '@/Components/Settings/GsSelect.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
})

const { toasts, dismiss } = useToasts({ flash: true })

/* ------------------------------------------------------------------ */
/* فیلترها (کلیدها دقیقاً مطابق CustomerController@index)                */
/* ------------------------------------------------------------------ */
const search = ref(props.filters?.search || '')
const requestStatus = ref(props.filters?.request_status || '')
const invoiceStatus = ref(props.filters?.invoice_status ?? '')
const busy = ref(false)

const STATUS_OPTIONS = [
    { value: '', label: 'همهٔ مشتریان', icon: Users },
    { value: 'pending', label: 'درخواست در انتظار', icon: Clock, tone: 'warning' },
    { value: 'in_progress', label: 'درخواست در جریان', icon: Wrench, tone: 'info' },
    { value: 'completed', label: 'تکمیل‌شده', icon: CheckCircle2, tone: 'success' },
    { value: 'canceled', label: 'لغوشده', icon: XCircle, tone: 'error' },
]

const INVOICE_OPTIONS = [
    { value: '', label: 'همهٔ فاکتورها' },
    { value: '1', label: 'دارای فاکتور تأییدشده' },
    { value: '0', label: 'فاکتور در انتظار تأیید' },
]

const activeFilters = computed(
    () => [search.value, requestStatus.value, invoiceStatus.value].filter((v) => v !== '' && v !== null).length,
)

function applyFilters() {
    router.get(
        route('customers.index'),
        {
            search: search.value || undefined,
            request_status: requestStatus.value || undefined,
            invoice_status: invoiceStatus.value === '' ? undefined : invoiceStatus.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    )
}

let debounce = null
watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(applyFilters, 350)
})
watch([requestStatus, invoiceStatus], applyFilters)
onBeforeUnmount(() => clearTimeout(debounce))

function clearFilters() {
    clearTimeout(debounce)
    search.value = ''
    requestStatus.value = ''
    invoiceStatus.value = ''
}

/* ------------------------------------------------------------------ */
/* نمای شبکه / جدول (ذخیره در localStorage)                              */
/* ------------------------------------------------------------------ */
const VIEW_KEY = 'gs.customers.view'
const view = ref(typeof localStorage !== 'undefined' ? localStorage.getItem(VIEW_KEY) || 'grid' : 'grid')
watch(view, (v) => localStorage?.setItem(VIEW_KEY, v))

/* ------------------------------------------------------------------ */
/* حذف                                                                   */
/* ------------------------------------------------------------------ */
const deleteTarget = ref(null)
const deleting = ref(false)
const confirmOpen = computed({
    get: () => !!deleteTarget.value,
    set: (v) => {
        if (!v) deleteTarget.value = null
    },
})

function askDelete(c) {
    deleteTarget.value = c
}

function doDelete() {
    if (!deleteTarget.value) return
    deleting.value = true
    router.delete(route('customers.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            deleteTarget.value = null
        },
    })
}

const list = computed(() => props.customers?.data || [])
const orbitSats = [
    { icon: Phone, color: 'var(--gs-info)' },
    { icon: Mail, color: 'var(--gs-gold)', reverse: true },
    { icon: MapPin, color: 'var(--gs-accent-2)' },
]
</script>

<template>
    <Head title="مشتریان" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="green" />

            <!-- ================= سربرگ ================= -->
            <header class="st-shell">
                <div class="st-hero">
                    <div style="min-width: 0">
                        <div class="crm-hero__chips">
                            <span class="st-chip"><Users :size="13" /> باشگاه مشتریان</span>
                            <span class="st-chip st-chip--plain">ماژول Customer</span>
                        </div>

                        <h1 class="st-hero__title">
                            <span>مشتریان</span>
                            <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                            </svg>
                        </h1>

                        <p class="st-hero__lead">
                            پروندهٔ مشتریان، اطلاعات تماس و سابقهٔ درخواست‌ها و فاکتورها — همه در یک‌جا.
                        </p>

                        <div class="crm-hero__stats">
                            <span class="st-stat">
                                <Users :size="15" />
                                کل مشتریان
                                <b>{{ faInt(customers.total || 0) }}</b>
                            </span>
                            <span class="st-stat">
                                <List :size="15" />
                                در این صفحه
                                <b>{{ faInt(list.length) }}</b>
                            </span>
                            <span class="st-stat">
                                <Filter :size="15" />
                                فیلتر فعال
                                <b>{{ faInt(activeFilters) }}</b>
                            </span>
                        </div>
                    </div>

                    <div class="crm-hero__side">
                        <CrmOrbit :icon="Users" :satellites="orbitSats" accent="var(--gs-accent-2)" />
                        <div class="crm-hero__actions">
                            <Link :href="route('customers.create')" class="a3d-btn a3d-btn--gold">
                                <UserPlus :size="15" /> ثبت مشتری جدید
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ================= بدنه ================= -->
            <div class="st-shell crm-body crm-stack">
                <!-- نوار ابزار -->
                <section v-reveal="{ delay: 40 }" class="a3d-holo crm-toolbar">
                    <div class="crm-toolbar__grow">
                        <CrmSearchBar
                            v-model="search"
                            placeholder="جستجو در نام، ایمیل یا شمارهٔ فاکتور…"
                            :loading="busy"
                        />
                    </div>

                    <GsSelect v-model="invoiceStatus" :options="INVOICE_OPTIONS" />

                    <div class="crm-view" role="group" aria-label="نوع نمایش">
                        <button type="button" :class="{ 'is-active': view === 'grid' }" title="نمای کارتی" @click="view = 'grid'">
                            <LayoutGrid :size="16" />
                        </button>
                        <button type="button" :class="{ 'is-active': view === 'table' }" title="نمای جدولی" @click="view = 'table'">
                            <List :size="16" />
                        </button>
                    </div>

                    <Transition name="crm-fade">
                        <button v-if="activeFilters" type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="clearFilters">
                            <X :size="14" /> پاک‌کردن فیلترها
                        </button>
                    </Transition>

                    <div style="flex-basis: 100%">
                        <CrmSegmented v-model="requestStatus" :options="STATUS_OPTIONS" />
                    </div>
                </section>

                <!-- حالت خالی -->
                <section v-if="!list.length" v-reveal="{ delay: 90 }" class="a3d-holo">
                    <CrmEmptyState
                        :icon="Users"
                        :title="activeFilters ? 'مشتری‌ای مطابق فیلترها پیدا نشد' : 'هنوز مشتری‌ای ثبت نشده'"
                        :desc="activeFilters ? 'عبارت جستجو یا فیلترها را تغییر دهید.' : 'اولین مشتری باشگاه را ثبت کنید تا پرونده‌اش این‌جا نمایش داده شود.'"
                    >
                        <button v-if="activeFilters" type="button" class="a3d-btn a3d-btn--ghost" @click="clearFilters">
                            <X :size="14" /> پاک‌کردن فیلترها
                        </button>
                        <Link :href="route('customers.create')" class="a3d-btn a3d-btn--gold">
                            <UserPlus :size="15" /> ثبت مشتری
                        </Link>
                    </CrmEmptyState>
                </section>

                <!-- نمای کارتی -->
                <section v-else-if="view === 'grid'" class="crm-cards">
                    <article
                        v-for="(c, i) in list"
                        :key="c.id"
                        v-reveal="{ delay: 80 + (i % 9) * 45 }"
                        v-tilt="{ max: 6, lift: 12, scale: 1.015 }"
                        class="a3d-holo a3d-aura crm-person"
                        :style="{ '--a3d-aura-color': `hsl(${avatarHue(c.name)} 60% 45% / 0.35)` }"
                    >
                        <div class="crm-person__head">
                            <span class="crm-avatar" :style="{ '--hue': avatarHue(c.name) }">{{ initials(c.name) }}</span>
                            <div style="min-width: 0">
                                <Link :href="route('customers.show', c.id)" class="crm-person__name">{{ c.name }}</Link>
                                <p class="crm-person__id">
                                    شناسه #{{ faInt(c.id) }}
                                    <template v-if="c.created_at"> · عضویت {{ dateFa(c.created_at) }}</template>
                                </p>
                            </div>
                        </div>

                        <div class="crm-person__lines">
                            <p class="crm-line" :class="{ 'is-empty': !c.phone }" style="--crm-accent: var(--gs-info)">
                                <Phone :size="14" />
                                <span dir="ltr">{{ c.phone || 'شماره‌ای ثبت نشده' }}</span>
                            </p>
                            <p class="crm-line" :class="{ 'is-empty': !c.email }" style="--crm-accent: var(--gs-gold)">
                                <Mail :size="14" />
                                <span dir="ltr">{{ c.email || 'ایمیلی ثبت نشده' }}</span>
                            </p>
                            <p class="crm-line" :class="{ 'is-empty': !c.address }" style="--crm-accent: var(--gs-accent-2)">
                                <MapPin :size="14" />
                                <span>{{ clip(c.address, 60) || 'آدرسی ثبت نشده' }}</span>
                            </p>
                        </div>

                        <div class="crm-person__foot">
                            <Link :href="route('customers.show', c.id)" class="a3d-btn a3d-btn--sm">
                                <Eye :size="14" /> مشاهدهٔ پرونده
                            </Link>
                            <div class="crm-actions">
                                <Link :href="route('customers.edit', c.id)" class="crm-iconbtn" title="ویرایش">
                                    <Pencil :size="15" />
                                </Link>
                                <button type="button" class="crm-iconbtn crm-iconbtn--danger" title="حذف" @click="askDelete(c)">
                                    <Trash2 :size="15" />
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- نمای جدولی -->
                <section v-else v-reveal="{ delay: 80 }" class="a3d-holo">
                    <div class="crm-table-wrap">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>مشتری</th>
                                    <th>تلفن</th>
                                    <th>ایمیل</th>
                                    <th>آدرس</th>
                                    <th>عضویت</th>
                                    <th class="is-end">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(c, i) in list" :key="c.id" :style="{ '--i': i }">
                                    <td class="is-muted">{{ faInt(c.id) }}</td>
                                    <td>
                                        <div class="crm-name">
                                            <span class="crm-avatar crm-avatar--sm" :style="{ '--hue': avatarHue(c.name) }">
                                                {{ initials(c.name) }}
                                            </span>
                                            <Link :href="route('customers.show', c.id)" class="crm-name__t">{{ c.name }}</Link>
                                        </div>
                                    </td>
                                    <td><span v-if="c.phone" class="crm-code" style="color: var(--gs-text-primary)">{{ c.phone }}</span><span v-else class="is-muted">—</span></td>
                                    <td><span v-if="c.email" dir="ltr">{{ c.email }}</span><span v-else class="is-muted">—</span></td>
                                    <td class="crm-cell-clip">{{ c.address || '—' }}</td>
                                    <td class="is-muted">
                                        <span style="display: inline-flex; align-items: center; gap: 0.35rem">
                                            <CalendarDays :size="13" /> {{ dateFa(c.created_at) || '—' }}
                                        </span>
                                    </td>
                                    <td class="is-end">
                                        <div class="crm-actions" style="justify-content: flex-end">
                                            <Link :href="route('customers.show', c.id)" class="crm-iconbtn" title="مشاهده"><Eye :size="15" /></Link>
                                            <Link :href="route('customers.edit', c.id)" class="crm-iconbtn" title="ویرایش"><Pencil :size="15" /></Link>
                                            <button type="button" class="crm-iconbtn crm-iconbtn--danger" title="حذف" @click="askDelete(c)">
                                                <Trash2 :size="15" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <CrmPagination :paginator="customers" label="مشتری" />

                <footer class="crm-footer">
                    <span><Users :size="14" /> گیم‌استور — باشگاه مشتریان</span>
                    <span><Receipt :size="13" /> فیلتر فاکتور و درخواست از بک‌اند اعمال می‌شود</span>
                </footer>
            </div>

            <CrmConfirmDialog
                v-model="confirmOpen"
                title="حذف پروندهٔ مشتری"
                message="با حذف این مشتری، پرونده به سطل بازیافت (soft delete) منتقل می‌شود. ادامه می‌دهید؟"
                :highlight="deleteTarget?.name || ''"
                :loading="deleting"
                @confirm="doDelete"
            />

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
