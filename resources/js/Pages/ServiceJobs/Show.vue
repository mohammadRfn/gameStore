<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="blue" />

            <!-- ================= هیرو ================= -->
            <LuxHero
                chip="ماژول سرویس"
                :chip-two="jobLabel(job.status)"
                :title="'سرویس «#' + faInt(job.id) + '»'"
                :lead="(job.customer?.name ? 'مشتری: ' + job.customer.name + ' — ' : '') + (job.device_type ?? 'بدون نوع دستگاه')"
                cube="🔧"
                satellite="🛠️"
                :stats="[
                    { label: 'قیمت تخمینی', value: formatPrice(job.estimated_price) },
                    { label: 'قیمت نهایی', value: formatPrice(job.final_price) },
                ]"
            >
                <template #chip-icon><Wrench :size="13" /></template>
                <template #actions>
                    <Link :href="route('service-jobs.edit', job.id)" class="a3d-btn a3d-btn--gold">
                        <Pencil :size="15" /> ویرایش
                    </Link>
                    <Link :href="route('service-jobs.index')" class="a3d-btn a3d-btn--ghost">
                        <ArrowRight :size="15" /> بازگشت
                    </Link>
                </template>
            </LuxHero>

            <!-- ================= تایم‌لاین وضعیت ================= -->
            <div class="gx-panel" :style="{ '--gx-i': 0 }">
                <div class="gx-steps">
                    <div
                        v-for="(s, i) in statusSteps"
                        :key="s.key"
                        class="gx-step"
                        :class="{ 'is-active': job.status === s.key, 'is-done': isStepDone(s.key) }"
                    >
                        <span v-if="i < statusSteps.length - 1" class="gx-step__bar"><i /></span>
                        <span class="gx-step__dot">
                            <Check v-if="isStepDone(s.key)" :size="15" />
                            <template v-else>{{ faInt(i + 1) }}</template>
                        </span>
                        <span class="gx-step__label">{{ s.label }}</span>
                    </div>
                </div>
            </div>

            <div class="gx-detailgrid">
                <!-- ============== ستون اصلی ============== -->
                <div style="display: flex; flex-direction: column; gap: 1.1rem">
                    <!-- اطلاعات دستگاه -->
                    <section class="gx-panel" :style="{ '--gx-i': 1 }">
                        <div class="gx-panel__head">
                            <span class="gx-panel__icon"><MonitorSmartphone :size="16" /></span>
                            <div>
                                <p class="gx-panel__title">اطلاعات دستگاه</p>
                                <p class="gx-panel__desc">مشخصات دستگاه پذیرش‌شده</p>
                            </div>
                        </div>
                        <div class="gx-panel__body">
                            <div class="gx-row">
                                <span class="gx-row__k">نوع دستگاه</span>
                                <span class="gx-row__v">{{ job.device_type ?? '—' }}</span>
                            </div>
                            <div class="gx-row">
                                <span class="gx-row__k">سریال</span>
                                <span class="gx-row__v" style="font-family: monospace; direction: ltr">{{ job.device_serial ?? '—' }}</span>
                            </div>
                        </div>
                    </section>

                    <!-- نوع(های) سرویس -->
                    <section class="gx-panel" :style="{ '--gx-i': 2 }">
                        <div class="gx-panel__head">
                            <span class="gx-panel__icon"><ClipboardList :size="16" /></span>
                            <div>
                                <p class="gx-panel__title">نوع(های) سرویس</p>
                                <p class="gx-panel__desc">خدمات تعریف‌شده روی این سرویس</p>
                            </div>
                        </div>
                        <div class="gx-panel__body--flush">
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
                                        <td class="gx-price" style="font-size:.85rem">{{ formatPrice(st.price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="gx-empty" style="padding: 1.8rem">
                                <p class="gx-empty__desc">نوع سرویسی ثبت نشده</p>
                            </div>
                        </div>
                    </section>

                    <!-- شرح مشکل و تشخیص -->
                    <section class="gx-panel" :style="{ '--gx-i': 3 }">
                        <div class="gx-panel__head">
                            <span class="gx-panel__icon"><FileText :size="16" /></span>
                            <div>
                                <p class="gx-panel__title">شرح مشکل و تشخیص</p>
                                <p class="gx-panel__desc">گزارش مشتری و نتیجهٔ بررسی فنی</p>
                            </div>
                        </div>
                        <div class="gx-panel__body">
                            <p class="gx-row__k" style="margin-bottom: .4rem">شرح مشکل مشتری</p>
                            <p class="gx-desc">{{ job.customer_problem_description ?? '—' }}</p>
                            <div style="height: 1px; background: var(--gs-border); margin: 1rem 0" />
                            <p class="gx-row__k" style="margin-bottom: .4rem">تشخیص فنی</p>
                            <p class="gx-desc">{{ job.diagnosis_description ?? '—' }}</p>
                        </div>
                    </section>

                    <!-- قطعات مصرفی -->
                    <section class="gx-panel" :style="{ '--gx-i': 4 }">
                        <div class="gx-panel__head">
                            <span class="gx-panel__icon"><Cpu :size="16" /></span>
                            <div>
                                <p class="gx-panel__title">قطعات مصرفی</p>
                                <p class="gx-panel__desc">اقلام استفاده‌شده در تعمیر</p>
                            </div>
                            <div class="gx-panel__spacer">
                                <Link :href="route('items.index')" class="a3d-btn a3d-btn--sm a3d-btn--ghost">مدیریت اقلام</Link>
                            </div>
                        </div>
                        <div class="gx-panel__body--flush">
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
                                        <td>{{ faInt(it.quantity) }}</td>
                                        <td>{{ formatPrice(it.unit_price) }}</td>
                                        <td class="gx-price" style="font-size:.85rem">{{ formatPrice(it.total_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="gx-empty" style="padding: 1.8rem">
                                <p class="gx-empty__desc">قطعه‌ای ثبت نشده</p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ============== سایدبار ============== -->
                <div style="display: flex; flex-direction: column; gap: 1.1rem">
                    <!-- قیمت‌گذاری -->
                    <div class="gx-total" :style="{ '--gx-i': 1 }">
                        <p class="gx-total__label">قیمت نهایی سرویس</p>
                        <p class="gx-total__value">{{ formatPrice(job.final_price) }}</p>
                        <div class="gx-row" style="margin-top: .6rem">
                            <span class="gx-row__k">تخمین اولیه</span>
                            <span class="gx-row__v">{{ formatPrice(job.estimated_price) }}</span>
                        </div>
                    </div>

                    <!-- تاریخ‌ها -->
                    <section class="gx-panel" :style="{ '--gx-i': 2 }">
                        <div class="gx-panel__head">
                            <span class="gx-panel__icon"><CalendarDays :size="16" /></span>
                            <div>
                                <p class="gx-panel__title">تاریخ‌ها</p>
                                <p class="gx-panel__desc">خط زمانی این سرویس</p>
                            </div>
                        </div>
                        <div class="gx-panel__body">
                            <div class="gx-row">
                                <span class="gx-row__k">دریافت</span>
                                <span class="gx-row__v">{{ formatDate(job.received_at) }}</span>
                            </div>
                            <div class="gx-row" v-if="job.started_at">
                                <span class="gx-row__k">شروع</span>
                                <span class="gx-row__v">{{ formatDate(job.started_at) }}</span>
                            </div>
                            <div class="gx-row" v-if="job.completed_at">
                                <span class="gx-row__k">اتمام</span>
                                <span class="gx-row__v">{{ formatDate(job.completed_at) }}</span>
                            </div>
                            <div class="gx-row" v-if="job.delivered_at">
                                <span class="gx-row__k">تحویل</span>
                                <span class="gx-row__v">{{ formatDate(job.delivered_at) }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import {
    ArrowRight,
    CalendarDays,
    Check,
    ClipboardList,
    Cpu,
    FileText,
    MonitorSmartphone,
    Pencil,
    Wrench,
} from 'lucide-vue-next'

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

const JOB_LABELS = { received: 'دریافت شده', diagnosing: 'در حال بررسی', waiting_for_parts: 'انتظار قطعه', in_progress: 'در حال تعمیر', completed: 'تکمیل شده', delivered: 'تحویل داده شده', canceled: 'لغو شده' }
const jobLabel = s => JOB_LABELS[s] ?? s

const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
const formatDate = d => d ? new Date(d).toLocaleDateString('fa-IR') : '—'
const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')
</script>

<style scoped>
.gx-detailgrid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 1.1rem;
    align-items: start;
    margin-top: 1.1rem;
}

.gx-desc {
    font-size: 0.85rem;
    color: var(--gs-text-secondary);
    line-height: 2;
}

@media (max-width: 860px) {
    .gx-detailgrid { grid-template-columns: 1fr; }
}
</style>
