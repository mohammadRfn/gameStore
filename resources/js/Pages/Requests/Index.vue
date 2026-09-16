<script setup>
/**
 * فهرست درخواست‌ها — بازطراحی هم‌سطح با صفحهٔ تنظیمات
 * مسیر: resources/js/Pages/Requests/Index.vue
 * ---------------------------------------------------------------------------
 * هماهنگ با Modules\Request\Http\Controllers\RequestController@index:
 *   props.requests → paginate(10) با with('categories','customer')
 *   props.filters  → { search, status }
 * فیلترها با همان کلیدها به route('requests.index') ارسال می‌شوند.
 * حذف: router.delete(route('requests.destroy', id))
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock,
    Eye,
    Filter,
    LayoutGrid,
    Layers,
    List,
    Pencil,
    Plus,
    Trash2,
    Wrench,
    X,
    XCircle,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal, vTilt } from '@/Composables/useTilt'
import { useToasts } from '@/Composables/useToasts'
import { faInt } from '@/Utils/format'
import { avatarHue, clip, dateFa, initials, statusMeta } from '@/Utils/crm'

import CrmScene from '@/Components/Crm/CrmScene.vue'
import CrmOrbit from '@/Components/Crm/CrmOrbit.vue'
import CrmSearchBar from '@/Components/Crm/CrmSearchBar.vue'
import CrmSegmented from '@/Components/Crm/CrmSegmented.vue'
import CrmStatusChip from '@/Components/Crm/CrmStatusChip.vue'
import CrmPagination from '@/Components/Crm/CrmPagination.vue'
import CrmEmptyState from '@/Components/Crm/CrmEmptyState.vue'
import CrmConfirmDialog from '@/Components/Crm/CrmConfirmDialog.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    requests: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
})

const { toasts, dismiss } = useToasts({ flash: true })

/* ------------------------------------------------------------------ */
/* فیلترها                                                               */
/* ------------------------------------------------------------------ */
const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')
const busy = ref(false)

const STATUS_OPTIONS = [
    { value: '', label: 'همه', icon: Layers },
    { value: 'pending', label: 'در انتظار', icon: Clock, tone: 'warning' },
    { value: 'in_progress', label: 'در جریان', icon: Wrench, tone: 'info' },
    { value: 'completed', label: 'تکمیل‌شده', icon: CheckCircle2, tone: 'success' },
    { value: 'canceled', label: 'لغوشده', icon: XCircle, tone: 'error' },
]

const activeFilters = computed(() => [search.value, status.value].filter(Boolean).length)

function applyFilters() {
    router.get(
        route('requests.index'),
        { search: search.value || undefined, status: status.value || undefined },
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
watch(status, applyFilters)
onBeforeUnmount(() => clearTimeout(debounce))

function clearFilters() {
    clearTimeout(debounce)
    search.value = ''
    status.value = ''
}

/* ------------------------------------------------------------------ */
/* نما                                                                   */
/* ------------------------------------------------------------------ */
const VIEW_KEY = 'gs.requests.view'
const view = ref(typeof localStorage !== 'undefined' ? localStorage.getItem(VIEW_KEY) || 'table' : 'table')
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

function doDelete() {
    if (!deleteTarget.value) return
    deleting.value = true
    router.delete(route('requests.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            deleteTarget.value = null
        },
    })
}

const list = computed(() => props.requests?.data || [])
const openOnPage = computed(() => list.value.filter((r) => ['pending', 'in_progress'].includes(r.status)).length)

const TONE_VAR = {
    warning: 'var(--gs-warning)',
    info: 'var(--gs-info)',
    success: 'var(--gs-success)',
    error: 'var(--gs-error)',
}
const toneVar = (s) => TONE_VAR[statusMeta(s).tone] || 'var(--gs-gold)'

const orbitSats = [
    { icon: Clock, color: 'var(--gs-warning)' },
    { icon: Wrench, color: 'var(--gs-info)', reverse: true },
    { icon: CheckCircle2, color: 'var(--gs-success)' },
]
</script>

<template>
    <Head title="درخواست‌ها" />

    <AppLayout>
        <div class="crm-page">
            <CrmScene tone="gold" />

            <!-- ================= سربرگ ================= -->
            <header class="st-shell">
                <div class="st-hero">
                    <div style="min-width: 0">
                        <div class="crm-hero__chips">
                            <span class="st-chip"><Wrench :size="13" /> پایپ‌لاین خدمات فنی</span>
                            <span class="st-chip st-chip--plain">ماژول Request</span>
                        </div>

                        <h1 class="st-hero__title">
                            <span>درخواست‌ها</span>
                            <svg class="st-underline" viewBox="0 0 220 14" aria-hidden="true">
                                <path d="M4 10 C 60 2, 150 2, 216 8" fill="none" stroke="var(--gs-gold)" stroke-width="3.5" stroke-linecap="round" />
                            </svg>
                        </h1>

                        <p class="st-hero__lead">
                            درخواست‌های تعمیر و سرویس از لحظهٔ ثبت تا تحویل — با وضعیت زنده، دسته‌بندی و اتصال به پروندهٔ مشتری.
                        </p>

                        <div class="crm-hero__stats">
                            <span class="st-stat"><ClipboardList :size="15" /> کل درخواست‌ها <b>{{ faInt(requests.total || 0) }}</b></span>
                            <span class="st-stat"><Wrench :size="15" /> باز در این صفحه <b>{{ faInt(openOnPage) }}</b></span>
                            <span class="st-stat"><Filter :size="15" /> فیلتر فعال <b>{{ faInt(activeFilters) }}</b></span>
                        </div>
                    </div>

                    <div class="crm-hero__side">
                        <CrmOrbit :icon="ClipboardList" :satellites="orbitSats" />
                        <div class="crm-hero__actions">
                            <Link :href="route('requests.create')" class="a3d-btn a3d-btn--gold">
                                <Plus :size="15" /> ثبت درخواست جدید
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
                        <CrmSearchBar v-model="search" placeholder="جستجو در نام مشتری یا شرح درخواست…" :loading="busy" />
                    </div>

                    <CrmSegmented v-model="status" :options="STATUS_OPTIONS" />

                    <div class="crm-view" role="group" aria-label="نوع نمایش">
                        <button type="button" :class="{ 'is-active': view === 'table' }" title="نمای جدولی" @click="view = 'table'">
                            <List :size="16" />
                        </button>
                        <button type="button" :class="{ 'is-active': view === 'grid' }" title="نمای تیکتی" @click="view = 'grid'">
                            <LayoutGrid :size="16" />
                        </button>
                    </div>

                    <Transition name="crm-fade">
                        <button v-if="activeFilters" type="button" class="a3d-btn a3d-btn--ghost a3d-btn--sm" @click="clearFilters">
                            <X :size="14" /> پاک‌کردن
                        </button>
                    </Transition>
                </section>

                <!-- حالت خالی -->
                <section v-if="!list.length" v-reveal="{ delay: 90 }" class="a3d-holo">
                    <CrmEmptyState
                        :icon="ClipboardList"
                        :title="activeFilters ? 'درخواستی مطابق فیلترها پیدا نشد' : 'هنوز درخواستی ثبت نشده'"
                        :desc="activeFilters ? 'عبارت جستجو یا وضعیت را تغییر دهید.' : 'اولین درخواست تعمیر یا سرویس را ثبت کنید.'"
                    >
                        <button v-if="activeFilters" type="button" class="a3d-btn a3d-btn--ghost" @click="clearFilters">
                            <X :size="14" /> پاک‌کردن فیلترها
                        </button>
                        <Link :href="route('requests.create')" class="a3d-btn a3d-btn--gold">
                            <Plus :size="15" /> ثبت درخواست
                        </Link>
                    </CrmEmptyState>
                </section>

                <!-- نمای جدولی -->
                <section v-else-if="view === 'table'" v-reveal="{ delay: 80 }" class="a3d-holo">
                    <div class="crm-table-wrap">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>کد</th>
                                    <th>مشتری</th>
                                    <th>شرح درخواست</th>
                                    <th>دسته‌بندی</th>
                                    <th>تاریخ ثبت</th>
                                    <th>وضعیت</th>
                                    <th class="is-end">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(req, i) in list" :key="req.id" :style="{ '--i': i }">
                                    <td><span class="crm-code">#{{ req.id }}</span></td>
                                    <td>
                                        <div class="crm-name">
                                            <span class="crm-avatar crm-avatar--sm" :style="{ '--hue': avatarHue(req.customer_name) }">
                                                {{ initials(req.customer_name) }}
                                            </span>
                                            <div style="min-width: 0">
                                                <Link v-if="req.customer" :href="route('customers.show', req.customer.id)" class="crm-name__t">
                                                    {{ req.customer_name }}
                                                </Link>
                                                <span v-else class="crm-name__t">{{ req.customer_name }}</span>
                                                <p class="crm-name__s">{{ req.customer ? 'دارای پرونده' : 'مشتری موردی' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="crm-cell-clip" :title="req.description">{{ clip(req.description, 80) || '—' }}</td>
                                    <td>
                                        <div class="crm-tags">
                                            <span v-for="cat in req.categories || []" :key="cat.id" class="crm-tag">{{ cat.name }}</span>
                                            <span v-if="!req.categories?.length" class="is-muted">—</span>
                                        </div>
                                    </td>
                                    <td class="is-muted">
                                        <span style="display: inline-flex; align-items: center; gap: 0.35rem">
                                            <CalendarDays :size="13" /> {{ dateFa(req.created_at) || '—' }}
                                        </span>
                                    </td>
                                    <td><CrmStatusChip :status="req.status" /></td>
                                    <td class="is-end">
                                        <div class="crm-actions" style="justify-content: flex-end">
                                            <Link :href="route('requests.show', req.id)" class="crm-iconbtn" title="مشاهده"><Eye :size="15" /></Link>
                                            <Link :href="route('requests.edit', req.id)" class="crm-iconbtn" title="ویرایش"><Pencil :size="15" /></Link>
                                            <button type="button" class="crm-iconbtn crm-iconbtn--danger" title="حذف" @click="deleteTarget = req">
                                                <Trash2 :size="15" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- نمای تیکتی -->
                <section v-else class="crm-cards">
                    <article
                        v-for="(req, i) in list"
                        :key="req.id"
                        v-reveal="{ delay: 80 + (i % 9) * 45 }"
                        v-tilt="{ max: 6, lift: 12, scale: 1.015 }"
                        class="a3d-holo a3d-aura crm-person"
                        :style="{ '--a3d-aura-color': toneVar(req.status), '--crm-accent': toneVar(req.status) }"
                    >
                        <div class="crm-person__head">
                            <span class="crm-avatar" :style="{ '--hue': avatarHue(req.customer_name) }">{{ initials(req.customer_name) }}</span>
                            <div style="min-width: 0; flex: 1">
                                <Link v-if="req.customer" :href="route('customers.show', req.customer.id)" class="crm-person__name">
                                    {{ req.customer_name }}
                                </Link>
                                <p v-else class="crm-person__name">{{ req.customer_name }}</p>
                                <p class="crm-person__id">
                                    <span class="crm-code">#{{ req.id }}</span>
                                    <template v-if="req.created_at"> · {{ dateFa(req.created_at) }}</template>
                                </p>
                            </div>
                            <CrmStatusChip :status="req.status" sm />
                        </div>

                        <div class="crm-person__lines">
                            <p class="crm-preview__desc" style="margin-top: 0; min-height: 0">{{ clip(req.description, 140) || 'بدون شرح' }}</p>
                            <div class="crm-tags">
                                <span v-for="cat in req.categories || []" :key="cat.id" class="crm-tag"><Layers :size="11" /> {{ cat.name }}</span>
                                <span v-if="!req.categories?.length" class="crm-person__id">بدون دسته‌بندی</span>
                            </div>
                        </div>

                        <div class="crm-person__foot">
                            <Link :href="route('requests.show', req.id)" class="a3d-btn a3d-btn--sm"><Eye :size="14" /> جزئیات</Link>
                            <div class="crm-actions">
                                <Link :href="route('requests.edit', req.id)" class="crm-iconbtn" title="ویرایش"><Pencil :size="15" /></Link>
                                <button type="button" class="crm-iconbtn crm-iconbtn--danger" title="حذف" @click="deleteTarget = req">
                                    <Trash2 :size="15" />
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <CrmPagination :paginator="requests" label="درخواست" />

                <footer class="crm-footer">
                    <span><ClipboardList :size="14" /> گیم‌استور — پایپ‌لاین درخواست‌ها</span>
                    <span><Layers :size="13" /> وضعیت‌ها توسط فاکتور/سرویس در بک‌اند به‌روز می‌شوند</span>
                </footer>
            </div>

            <CrmConfirmDialog
                v-model="confirmOpen"
                title="حذف درخواست"
                message="درخواست به سطل بازیافت منتقل می‌شود و ارتباط دسته‌بندی‌ها حذف می‌شود. ادامه می‌دهید؟"
                :highlight="deleteTarget ? `#${deleteTarget.id} — ${deleteTarget.customer_name}` : ''"
                :loading="deleting"
                @confirm="doDelete"
            />

            <ToastHost :toasts="toasts" @close="dismiss" />
        </div>
    </AppLayout>
</template>
