<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">سرویس‌ها</h1>
                    <p class="gs-subtitle">{{ serviceJobs.total }} سرویس ثبت شده</p>
                </div>
                <Link :href="route('service-jobs.create')" class="gs-btn gs-btn-primary">+ سرویس جدید</Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="gs-card" style="margin-bottom:1.25rem">
            <div class="gs-filter-row">
                <select v-model="filters.status" class="gs-input" style="max-width:200px" @change="apply">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="received">دریافت شده</option>
                    <option value="diagnosing">در حال بررسی</option>
                    <option value="waiting_for_parts">انتظار قطعه</option>
                    <option value="in_progress">در حال تعمیر</option>
                    <option value="completed">تکمیل شده</option>
                    <option value="delivered">تحویل داده شده</option>
                    <option value="canceled">لغو شده</option>
                </select>
                <button v-if="filters.status" @click="filters.status='';apply()" class="gs-btn gs-btn-ghost gs-btn-sm">پاک</button>
            </div>
        </div>

        <!-- Table -->
        <div class="gs-card" style="padding:0;overflow:hidden">
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
                        <td style="font-weight:500">{{ job.customer?.name ?? '—' }}</td>
                        <td class="gs-secondary">{{ job.device_type ?? '—' }}</td>
                        <td>
                            <div v-if="job.service_types?.length" style="display:flex;flex-wrap:wrap;gap:.3rem">
                                <span v-for="st in job.service_types" :key="st.id" class="gs-badge gs-badge-gold gs-badge-sm">
                                    {{ st.service_type?.name ?? '—' }}
                                </span>
                            </div>
                            <span v-else class="gs-muted">—</span>
                        </td>
                        <td>
                            <span :class="['gs-badge', jobBadge(job.status)]">{{ jobLabel(job.status) }}</span>
                        </td>
                        <td>{{ formatPrice(job.estimated_price) }}</td>
                        <td>
                            <div class="gs-actions">
                                <Link :href="route('service-jobs.show', job.id)" class="gs-btn gs-btn-ghost gs-btn-sm">مشاهده</Link>
                                <Link :href="route('service-jobs.edit', job.id)" class="gs-btn gs-btn-secondary gs-btn-sm">ویرایش</Link>
                                <button type="button" @click="destroyJob(job)" class="gs-btn gs-btn-danger gs-btn-sm">حذف</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p style="font-size:2rem">🔧</p>
                <p class="gs-subtitle">سرویسی یافت نشد</p>
                <Link :href="route('service-jobs.create')" class="gs-btn gs-btn-primary gs-btn-sm" style="margin-top:.75rem">
                    اولین سرویس را ثبت کنید
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div class="gs-pagination" v-if="serviceJobs.last_page > 1">
            <Link v-if="serviceJobs.prev_page_url" :href="serviceJobs.prev_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">قبلی</Link>
            <span class="gs-label">{{ serviceJobs.current_page }} / {{ serviceJobs.last_page }}</span>
            <Link v-if="serviceJobs.next_page_url" :href="serviceJobs.next_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">بعدی</Link>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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

const JOB_LABELS = { received:'دریافت شده', diagnosing:'در حال بررسی', waiting_for_parts:'انتظار قطعه', in_progress:'در حال تعمیر', completed:'تکمیل شده', delivered:'تحویل داده شده', canceled:'لغو شده' }
const JOB_BADGES = { received:'gs-badge-info', diagnosing:'gs-badge-warning', waiting_for_parts:'gs-badge-error', in_progress:'gs-badge-warning', completed:'gs-badge-success', delivered:'gs-badge-gold', canceled:'gs-badge-error' }
const jobLabel = s => JOB_LABELS[s] ?? s
const jobBadge = s => JOB_BADGES[s] ?? 'gs-badge-gold'
const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-filter-row { display:flex;align-items:center;gap:.75rem }
.gs-muted { color:var(--gs-text-muted);font-size:.8rem }
.gs-secondary { color:var(--gs-text-secondary);font-size:.875rem }
.gs-actions { display:flex;gap:.4rem }
.gs-empty { padding:3rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem }
.gs-pagination { display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:1.25rem }
</style>
