<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">انبارگردانی</h1>
                    <p class="gs-subtitle">ورود و خروج اقلام انبار</p>
                </div>
                <button @click="showForm = true" class="gs-btn gs-btn-primary">+ ثبت حرکت انبار</button>
            </div>
        </template>

        <!-- Stock summary per item -->
        <div class="gs-stock-grid" style="margin-bottom:1.5rem">
            <div v-for="item in stockSummary" :key="item.id" class="gs-card gs-stock-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
                    <p class="gs-item-name">{{ item.name }}</p>
                    <span :class="['gs-badge', item.current_stock > 0 ? 'gs-badge-success' : 'gs-badge-error']">
                        {{ item.current_stock }}
                    </span>
                </div>
                <p class="gs-muted">قیمت: {{ formatPrice(item.price) }}</p>
            </div>
        </div>

        <!-- Movements Table -->
        <div class="gs-card" style="padding:0;overflow:hidden">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gs-border)">
                <span class="gs-subtitle">تاریخچه حرکات انبار</span>
            </div>
            <table class="gs-table" v-if="movements.data?.length">
                <thead>
                    <tr>
                        <th>قلم</th>
                        <th>نوع</th>
                        <th>تعداد</th>
                        <th>دلیل</th>
                        <th>یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in movements.data" :key="m.id">
                        <td style="font-weight:500">{{ m.item?.name ?? '—' }}</td>
                        <td>
                            <span :class="['gs-badge', moveBadge(m.movement_type)]">{{ moveLabel(m.movement_type) }}</span>
                        </td>
                        <td :class="isIn(m.movement_type) ? 'gs-in' : 'gs-out'">
                            {{ isIn(m.movement_type) ? '+' : '-' }}{{ m.quantity }}
                        </td>
                        <td class="gs-muted">{{ m.reason ?? '—' }}</td>
                        <td class="gs-muted">{{ m.note ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p style="font-size:2rem">📦</p>
                <p class="gs-subtitle">حرکتی ثبت نشده</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="gs-pagination" v-if="movements.last_page > 1">
            <Link v-if="movements.prev_page_url" :href="movements.prev_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">قبلی</Link>
            <span class="gs-label">{{ movements.current_page }} / {{ movements.last_page }}</span>
            <Link v-if="movements.next_page_url" :href="movements.next_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">بعدی</Link>
        </div>

        <!-- Manual Movement Modal -->
        <Transition name="gs-fade">
            <div v-if="showForm" class="gs-modal-overlay" @click.self="showForm=false">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:1.25rem">ثبت حرکت دستی انبار</h3>

                    <div class="gs-input-group">
                        <label class="gs-input-label">قلم <span style="color:var(--gs-error)">*</span></label>
                        <select v-model="moveForm.item_id" class="gs-input" :class="{'gs-input-error': moveForm.errors.item_id}">
                            <option value="">انتخاب قلم...</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                        <span v-if="moveForm.errors.item_id" class="gs-error-msg">{{ moveForm.errors.item_id }}</span>
                    </div>

                    <div class="gs-form-grid">
                        <div class="gs-input-group">
                            <label class="gs-input-label">نوع <span style="color:var(--gs-error)">*</span></label>
                            <select v-model="moveForm.movement_type" class="gs-input">
                                <option value="in">ورودی</option>
                                <option value="out">خروجی</option>
                                <option value="adjust_in">تنظیم مثبت</option>
                                <option value="adjust_out">تنظیم منفی</option>
                            </select>
                        </div>
                        <div class="gs-input-group">
                            <label class="gs-input-label">تعداد <span style="color:var(--gs-error)">*</span></label>
                            <input v-model="moveForm.quantity" type="number" min="1" class="gs-input"
                                :class="{'gs-input-error': moveForm.errors.quantity}" />
                            <span v-if="moveForm.errors.quantity" class="gs-error-msg">{{ moveForm.errors.quantity }}</span>
                        </div>
                    </div>

                    <div class="gs-input-group">
                        <label class="gs-input-label">یادداشت</label>
                        <input v-model="moveForm.note" type="text" class="gs-input" placeholder="توضیح اختیاری..." />
                    </div>

                    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem">
                        <button type="button" @click="showForm=false" class="gs-btn gs-btn-ghost">انصراف</button>
                        <button type="button" @click="submitMove" class="gs-btn gs-btn-primary" :disabled="moveForm.processing">
                            {{ moveForm.processing ? '...' : 'ثبت حرکت' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ movements: Object, items: Array, stockSummary: Array })

const showForm = ref(false)

const moveForm = useForm({
    item_id: '',
    movement_type: 'in',
    quantity: 1,
    note: '',
})

function submitMove() {
    moveForm.post(route('stock-movements.store'), {
        onSuccess: () => { showForm.value = false; moveForm.reset() }
    })
}

const isIn = t => ['in', 'adjust_in'].includes(t)
const moveLabel = t => ({ in: 'ورودی', out: 'خروجی', adjust_in: 'تنظیم +', adjust_out: 'تنظیم −' }[t] ?? t)
const moveBadge = t => isIn(t) ? 'gs-badge-success' : 'gs-badge-error'
const formatPrice = p => p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-stock-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem }
.gs-stock-card { padding:1rem }
.gs-item-name { font-weight:700;font-size:.9rem;color:var(--gs-text-primary) }
.gs-muted { color:var(--gs-text-muted);font-size:.8rem }
.gs-in { color:var(--gs-success);font-weight:700 }
.gs-out { color:var(--gs-error);font-weight:700 }
.gs-empty { padding:3rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem }
.gs-pagination { display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:1.25rem }
.gs-modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(3px);z-index:500;display:flex;align-items:center;justify-content:center;padding:1rem }
.gs-modal { background:var(--gs-bg-card);border:1px solid var(--gs-border-strong);border-radius:16px;padding:1.75rem;max-width:480px;width:100% }
.gs-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:0 1rem }
</style>
