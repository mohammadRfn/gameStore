<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">سرویس #{{ job.id }}</h1>
                    <p class="gs-subtitle">{{ job.customer?.name ?? '—' }}</p>
                </div>
                <div style="display:flex;gap:.75rem">
                    <Link :href="route('service-jobs.edit', job.id)" class="gs-btn gs-btn-secondary">ویرایش</Link>
                    <Link :href="route('service-jobs.index')" class="gs-btn gs-btn-ghost">← بازگشت</Link>
                </div>
            </div>
        </template>

        <!-- Status timeline -->
        <div class="gs-timeline-wrap gs-card" style="margin-bottom:1.25rem">
            <div v-for="(s, i) in statusSteps" :key="s.key"
                :class="['gs-step', { active: job.status === s.key, done: isStepDone(s.key) }]">
                <div class="gs-step-dot">{{ isStepDone(s.key) ? '✓' : i + 1 }}</div>
                <span class="gs-step-label">{{ s.label }}</span>
                <div v-if="i < statusSteps.length - 1" class="gs-step-line"></div>
            </div>
        </div>

        <div class="gs-detail-grid">
            <!-- Main card -->
            <div style="display:flex;flex-direction:column;gap:1rem">
                <!-- Device info -->
                <div class="gs-card">
                    <p class="gs-label" style="margin-bottom:.75rem">اطلاعات دستگاه</p>
                    <div class="gs-detail-row">
                        <span class="gs-label">نوع دستگاه</span>
                        <span>{{ job.device_type ?? '—' }}</span>
                    </div>
                    <div class="gs-detail-row">
                        <span class="gs-label">سریال</span>
                        <span style="font-family:monospace">{{ job.device_serial ?? '—' }}</span>
                    </div>
                    </div>

                <!-- Service types breakdown -->
                <div class="gs-card" style="padding:0;overflow:hidden">
                    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border)">
                        <span class="gs-subtitle">نوع(های) سرویس</span>
                    </div>
                    <table class="gs-table" v-if="job.service_types?.length">
                        <thead>
                            <tr>
                                <th>نوع سرویس</th>
                                <th>قیمت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="st in job.service_types" :key="st.id">
                                <td>{{ st.service_type?.name ?? '—' }}</td>
                                <td class="gs-gold-text" style="font-weight:700">{{ formatPrice(st.price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="gs-muted" style="padding:1.25rem;text-align:center">نوع سرویسی ثبت نشده</p>
                </div>

                <!-- Problem & Diagnosis -->
                <div class="gs-card">
                    <p class="gs-label" style="margin-bottom:.5rem">شرح مشکل مشتری</p>
                    <p class="gs-desc">{{ job.customer_problem_description ?? '—' }}</p>
                    <div class="gs-divider"></div>
                    <p class="gs-label" style="margin-bottom:.5rem">تشخیص فنی</p>
                    <p class="gs-desc">{{ job.diagnosis_description ?? '—' }}</p>
                </div>

                <!-- Items used -->
                <div class="gs-card" style="padding:0;overflow:hidden">
                    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border);display:flex;justify-content:space-between;align-items:center">
                        <span class="gs-subtitle">قطعات مصرفی</span>
                        <Link :href="route('items.index')" class="gs-btn gs-btn-ghost gs-btn-sm">مدیریت اقلام</Link>
                    </div>
                    <table class="gs-table" v-if="job.items?.length">
                        <thead>
                            <tr>
                                <th>قطعه</th>
                                <th>تعداد</th>
                                <th>قیمت واحد</th>
                                <th>جمع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="it in job.items" :key="it.id">
                                <td>{{ it.item?.name ?? '—' }}</td>
                                <td>{{ it.quantity }}</td>
                                <td>{{ formatPrice(it.unit_price) }}</td>
                                <td class="gs-gold-text" style="font-weight:700">{{ formatPrice(it.total_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="gs-muted" style="padding:1.25rem;text-align:center">قطعه‌ای ثبت نشده</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div style="display:flex;flex-direction:column;gap:1rem">
                <!-- Pricing -->
                <div class="gs-card">
                    <p class="gs-label" style="margin-bottom:.75rem">قیمت‌گذاری</p>
                    <div class="gs-detail-row">
                        <span class="gs-label">تخمینی</span>
                        <span class="gs-gold-text" style="font-weight:700">{{ formatPrice(job.estimated_price) }}</span>
                    </div>
                    <div class="gs-detail-row">
                        <span class="gs-label">نهایی</span>
                        <span style="font-weight:800;font-size:1rem;color:var(--gs-text-primary)">{{ formatPrice(job.final_price) }}</span>
                    </div>
                </div>

                <!-- Dates -->
                <div class="gs-card">
                    <p class="gs-label" style="margin-bottom:.75rem">تاریخ‌ها</p>
                    <div class="gs-detail-row">
                        <span class="gs-label">دریافت</span>
                        <span class="gs-secondary">{{ formatDate(job.received_at) }}</span>
                    </div>
                    <div class="gs-detail-row" v-if="job.started_at">
                        <span class="gs-label">شروع</span>
                        <span class="gs-secondary">{{ formatDate(job.started_at) }}</span>
                    </div>
                    <div class="gs-detail-row" v-if="job.completed_at">
                        <span class="gs-label">اتمام</span>
                        <span class="gs-secondary">{{ formatDate(job.completed_at) }}</span>
                    </div>
                    <div class="gs-detail-row" v-if="job.delivered_at">
                        <span class="gs-label">تحویل</span>
                        <span class="gs-secondary">{{ formatDate(job.delivered_at) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ job: Object })

const statusSteps = [
    { key: 'received', label: 'دریافت' },
    { key: 'diagnosing', label: 'بررسی' },
    { key: 'waiting_for_parts', label: 'انتظار قطعه' },
    { key: 'in_progress', label: 'تعمیر' },
    { key: 'completed', label: 'تکمیل' },
    { key: 'delivered', label: 'تحویل' },
]

const stepOrder = statusSteps.map(s => s.key)
function isStepDone(key) {
    const current = stepOrder.indexOf(props.job.status)
    return stepOrder.indexOf(key) < current
}

const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
const formatDate = d => d ? new Date(d).toLocaleDateString('fa-IR') : '—'
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-timeline-wrap { display:flex;align-items:center;gap:0;overflow-x:auto }
.gs-step { display:flex;align-items:center;gap:.4rem;flex-shrink:0 }
.gs-step-dot { width:28px;height:28px;border-radius:50%;border:2px solid var(--gs-border);display:flex;align-items:center;justify-content:center;font-size:.75rem;color:var(--gs-text-muted);background:var(--gs-bg-elevated);flex-shrink:0;transition:all var(--gs-transition) }
.gs-step.done .gs-step-dot { background:var(--gs-gold-muted);border-color:var(--gs-gold);color:var(--gs-gold) }
.gs-step.active .gs-step-dot { background:var(--gs-gold);border-color:var(--gs-gold);color:#0a0a0f;font-weight:700 }
.gs-step-label { font-size:.75rem;color:var(--gs-text-muted);white-space:nowrap }
.gs-step.active .gs-step-label { color:var(--gs-gold);font-weight:600 }
.gs-step-line { width:32px;height:2px;background:var(--gs-border);margin:0 .25rem;flex-shrink:0 }
.gs-detail-grid { display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start }
.gs-detail-row { display:flex;align-items:center;justify-content:space-between;padding:.55rem 0;border-bottom:1px solid var(--gs-border) }
.gs-detail-row:last-child { border-bottom:none }
.gs-muted { color:var(--gs-text-muted);font-size:.875rem }
.gs-secondary { color:var(--gs-text-secondary);font-size:.875rem }
.gs-desc { font-size:.875rem;color:var(--gs-text-secondary);line-height:1.8 }
@media(max-width:768px) { .gs-detail-grid { grid-template-columns:1fr } }
</style>
