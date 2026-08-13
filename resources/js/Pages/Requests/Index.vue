<template>
    <AppLayout>
        <template #header>
            <div class="gs-page-header">
                <div>
                    <h1 class="gs-title">درخواست‌ها</h1>
                    <p class="gs-subtitle">{{ requests.total }} درخواست ثبت شده</p>
                </div>
                <Link :href="route('requests.create')" class="gs-btn gs-btn-primary">+ درخواست جدید</Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="gs-card" style="margin-bottom:1.25rem">
            <div class="gs-filter-row">
                <input v-model="filters.search" type="search" class="gs-input" style="flex:1"
                    placeholder="جستجو نام مشتری، توضیحات..." @input="debounce" />
                <select v-model="filters.status" class="gs-input" style="max-width:160px" @change="apply">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار</option>
                    <option value="in_progress">در جریان</option>
                    <option value="completed">تکمیل شده</option>
                    <option value="canceled">لغو شده</option>
                </select>
                <button v-if="hasFilters" @click="clear" class="gs-btn gs-btn-ghost gs-btn-sm">پاک</button>
            </div>
        </div>

        <!-- Table -->
        <div class="gs-card" style="padding:0;overflow:hidden">
            <table class="gs-table" v-if="requests.data.length">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>مشتری</th>
                        <th>توضیحات</th>
                        <th>دسته‌بندی</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="req in requests.data" :key="req.id">
                        <td class="gs-muted">{{ req.id }}</td>
                        <td style="font-weight:500">{{ req.customer_name }}</td>
                        <td class="gs-truncate" style="max-width:220px">{{ req.description }}</td>
                        <td>
                            <span v-for="cat in req.categories" :key="cat.id"
                                class="gs-badge gs-badge-gold" style="margin-left:.3rem;font-size:.7rem">
                                {{ cat.name }}
                            </span>
                        </td>
                        <td>
                            <span :class="['gs-badge', statusBadge(req.status)]">{{ statusLabel(req.status) }}</span>
                        </td>
                        <td>
                            <div class="gs-actions">
                                <Link :href="route('requests.show', req.id)" class="gs-btn gs-btn-ghost gs-btn-sm">مشاهده</Link>
                                <Link :href="route('requests.edit', req.id)" class="gs-btn gs-btn-secondary gs-btn-sm">ویرایش</Link>
                                <button @click="del(req)" class="gs-btn gs-btn-danger gs-btn-sm">حذف</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="gs-empty">
                <p style="font-size:2rem">📋</p>
                <p class="gs-subtitle">درخواستی یافت نشد</p>
                <Link :href="route('requests.create')" class="gs-btn gs-btn-primary gs-btn-sm" style="margin-top:.75rem">
                    اولین درخواست را ثبت کنید
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div class="gs-pagination" v-if="requests.last_page > 1">
            <Link v-if="requests.prev_page_url" :href="requests.prev_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">قبلی</Link>
            <span class="gs-label">{{ requests.current_page }} / {{ requests.last_page }}</span>
            <Link v-if="requests.next_page_url" :href="requests.next_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">بعدی</Link>
        </div>

        <!-- Delete Modal -->
        <Transition name="gs-fade">
            <div v-if="deleteTarget" class="gs-modal-overlay" @click.self="deleteTarget=null">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:.5rem">حذف درخواست</h3>
                    <p class="gs-label" style="margin-bottom:1.25rem">درخواست «{{ deleteTarget.customer_name }}» حذف شود؟</p>
                    <div style="display:flex;gap:.75rem;justify-content:flex-end">
                        <button @click="deleteTarget=null" class="gs-btn gs-btn-ghost">انصراف</button>
                        <button @click="doDelete" class="gs-btn gs-btn-danger" :disabled="deleting">
                            {{ deleting ? '...' : 'حذف' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ requests: Object, filters: Object })

const filters = ref({ search: props.filters?.search ?? '', status: props.filters?.status ?? '' })
const hasFilters = computed(() => filters.value.search || filters.value.status)

let t = null
function debounce() { clearTimeout(t); t = setTimeout(apply, 350) }
function apply() { router.get(route('requests.index'), filters.value, { preserveState: true, replace: true }) }
function clear() { filters.value = { search: '', status: '' }; apply() }

const deleteTarget = ref(null)
const deleting = ref(false)
function del(r) { deleteTarget.value = r }
function doDelete() {
    deleting.value = true
    router.delete(route('requests.destroy', deleteTarget.value.id), {
        onFinish: () => { deleting.value = false; deleteTarget.value = null }
    })
}

const statusLabel = s => ({ pending: 'در انتظار', in_progress: 'در جریان', completed: 'تکمیل', canceled: 'لغو' }[s] ?? s)
const statusBadge = s => ({ pending: 'gs-badge-warning', in_progress: 'gs-badge-info', completed: 'gs-badge-success', canceled: 'gs-badge-error' }[s] ?? 'gs-badge-gold')
</script>

<style scoped>
.gs-page-header { display:flex;align-items:center;justify-content:space-between }
.gs-filter-row { display:flex;align-items:center;gap:.75rem;flex-wrap:wrap }
.gs-muted { color:var(--gs-text-muted);font-size:.8rem }
.gs-truncate { white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--gs-text-secondary);font-size:.875rem }
.gs-actions { display:flex;gap:.4rem }
.gs-empty { padding:3rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem }
.gs-pagination { display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:1.25rem }
.gs-modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(3px);z-index:500;display:flex;align-items:center;justify-content:center;padding:1rem }
.gs-modal { background:var(--gs-bg-card);border:1px solid var(--gs-border-strong);border-radius:16px;padding:1.75rem;max-width:420px;width:100% }
</style>
