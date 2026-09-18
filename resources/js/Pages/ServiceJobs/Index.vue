<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="blue" />

            <!-- ================= هیرو ================= -->
            <LuxHero
                chip="ماژول سرویس"
                chip-two="Service Jobs"
                title="مرکز «سرویس و تعمیر»"
                lead="پیگیری چرخهٔ کامل تعمیرات — از پذیرش دستگاه تا تحویل به مشتری."
                cube="🔧"
                satellite="⚙️"
                :stats="[
                    { label: 'کل سرویس‌ها', value: faInt(serviceJobs.total) },
                    { label: 'در این صفحه', value: faInt(serviceJobs.data.length) },
                ]"
            >
                <template #chip-icon><Wrench :size="13" /></template>
                <template #actions>
                    <Link :href="route('service-jobs.create')" class="a3d-btn a3d-btn--gold">
                        <Plus :size="15" />
                        سرویس جدید
                    </Link>
                </template>
            </LuxHero>

            <!-- ================= فیلتر وضعیت ================= -->
            <div class="gx-toolbar">
                <span style="display:flex;align-items:center;gap:.4rem;font-size:.76rem;color:var(--gs-text-muted)">
                    <Filter :size="14" />
                    وضعیت:
                </span>
                <div class="gx-seg">
                    <button
                        v-for="opt in STATUS_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="gx-seg__btn"
                        :class="{ 'is-active': filters.status === opt.value }"
                        @click="filters.status = opt.value; apply()"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- ================= جدول ================= -->
            <div class="gx-panel" :style="{ '--gx-i': 1 }">
                <table class="gs-table" v-if="serviceJobs.data.length">
                    <thead>
                        <tr>
                            <th>مشتری</th>
                            <th>دستگاه</th>
                            <th>نوع سرویس</th>
                            <th>وضعیت</th>
                            <th>قیمت تخمینی</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="job in serviceJobs.data" :key="job.id">
                            <td style="font-weight:700">{{ job.customer?.name ?? '—' }}</td>
                            <td style="color:var(--gs-text-secondary);font-size:.85rem">{{ job.device_type ?? '—' }}</td>
                            <td>
                                <div v-if="job.service_types?.length" style="display:flex;flex-wrap:wrap;gap:.3rem">
                                    <span v-for="st in job.service_types" :key="st.id" class="gx-tag">
                                        {{ st.service_type?.name ?? '—' }}
                                    </span>
                                </div>
                                <span v-else style="color:var(--gs-text-muted)">—</span>
                            </td>
                            <td>
                                <span class="gx-status" :class="jobStatusClass(job.status)">
                                    <i />
                                    {{ jobLabel(job.status) }}
                                </span>
                            </td>
                            <td class="gx-price" style="font-size:.85rem">{{ formatPrice(job.estimated_price) }}</td>
                            <td>
                                <div style="display:flex;gap:.4rem;justify-content:flex-end">
                                    <Link :href="route('service-jobs.show', job.id)" class="a3d-btn a3d-btn--sm a3d-btn--ghost">
                                        <Eye :size="13" /> مشاهده
                                    </Link>
                                    <Link :href="route('service-jobs.edit', job.id)" class="a3d-btn a3d-btn--sm">
                                        <Pencil :size="13" /> ویرایش
                                    </Link>
                                    <button type="button" @click="destroyJob(job)" class="a3d-btn a3d-btn--sm a3d-btn--danger">
                                        <Trash2 :size="13" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="gx-empty">
                    <span class="gx-empty__icon">🔧</span>
                    <p class="gx-empty__title">سرویسی یافت نشد</p>
                    <p class="gx-empty__desc">اولین دستگاه را بپذیر و چرخهٔ سرویس را آغاز کن.</p>
                    <Link :href="route('service-jobs.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm" style="margin-top:.5rem">
                        <Plus :size="14" />
                        اولین سرویس را ثبت کنید
                    </Link>
                </div>
            </div>

            <!-- ================= پجینیشن ================= -->
            <div class="gx-pagination" v-if="serviceJobs.last_page > 1">
                <Link v-if="serviceJobs.prev_page_url" :href="serviceJobs.prev_page_url" class="a3d-btn a3d-btn--sm">
                    <ChevronRight :size="14" /> قبلی
                </Link>
                <span class="gx-pagination__num">
                    صفحهٔ <b>{{ faInt(serviceJobs.current_page) }}</b> از {{ faInt(serviceJobs.last_page) }}
                </span>
                <Link v-if="serviceJobs.next_page_url" :href="serviceJobs.next_page_url" class="a3d-btn a3d-btn--sm">
                    بعدی <ChevronLeft :size="14" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Filter,
    Pencil,
    Plus,
    Trash2,
    Wrench,
} from 'lucide-vue-next'

const props = defineProps({ serviceJobs: Object, filters: Object })

const filters = ref({ status: props.filters?.status ?? '' })
function apply() { router.get(route('service-jobs.index'), filters.value, { preserveState: true, replace: true }) }

function destroyJob(job) {
    if (!confirm(`آیا از حذف سرویس #${job.id} مطمئن هستید؟`)) return
    router.delete(route('service-jobs.destroy', job.id), {
        preserveScroll: true,
        onError: () => alert('حذف این سرویس امکان‌پذیر نیست (احتمالاً تکمیل یا تحویل داده شده است).'),
    })
}

const STATUS_OPTIONS = [
    { value: '', label: 'همه' },
    { value: 'received', label: 'دریافت شده' },
    { value: 'diagnosing', label: 'در حال بررسی' },
    { value: 'waiting_for_parts', label: 'انتظار قطعه' },
    { value: 'in_progress', label: 'در حال تعمیر' },
    { value: 'completed', label: 'تکمیل شده' },
    { value: 'delivered', label: 'تحویل داده شده' },
    { value: 'canceled', label: 'لغو شده' },
]

const JOB_LABELS = { received: 'دریافت شده', diagnosing: 'در حال بررسی', waiting_for_parts: 'انتظار قطعه', in_progress: 'در حال تعمیر', completed: 'تکمیل شده', delivered: 'تحویل داده شده', canceled: 'لغو شده' }
const JOB_STATUS_CLASSES = {
    received: 'gx-status--blue',
    diagnosing: 'gx-status--amber',
    waiting_for_parts: 'gx-status--red',
    in_progress: 'gx-status--amber',
    completed: 'gx-status--green',
    delivered: 'gx-status--gold',
    canceled: 'gx-status--red',
}
const jobLabel = s => JOB_LABELS[s] ?? s
const jobStatusClass = s => JOB_STATUS_CLASSES[s] ?? 'gx-status--gold'
const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')
</script>
