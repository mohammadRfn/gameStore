<template>
    <AppLayout>
        <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <h1 class="gs-title">مشتریان</h1>
                    <p class="gs-subtitle">{{ customers.total }} مشتری ثبت شده</p>
                </div>
                <Link :href="route('customers.create')" class="gs-btn gs-btn-primary">
                    + مشتری جدید
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="gs-card" style="margin-bottom:1.25rem">
            <div class="gs-filter-row">
                <div class="gs-input-group" style="margin:0;flex:1">
                    <input v-model="filters.search" type="search" class="gs-input" placeholder="جستجو نام، ایمیل..."
                        @input="debounceSearch" />
                </div>
                <select v-model="filters.request_status" class="gs-input" style="max-width:160px"
                    @change="applyFilters">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار</option>
                    <option value="in_progress">در جریان</option>
                    <option value="completed">تکمیل</option>
                    <option value="canceled">لغو</option>
                </select>
                <button v-if="hasFilters" @click="clearFilters" class="gs-btn gs-btn-ghost gs-btn-sm">
                    پاک کردن
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="gs-card" style="padding:0;overflow:hidden">
            <table class="gs-table" v-if="customers.data.length">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>تلفن</th>
                        <th>درخواست‌ها</th>
                        <th>فاکتورها</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="customer in customers.data" :key="customer.id">
                        <td class="gs-text-muted">{{ customer.id }}</td>
                        <td style="font-weight:500;color:var(--gs-text-primary)">{{ customer.name }}</td>
                        <td class="gs-text-secondary">{{ customer.email ?? '—' }}</td>
                        <td class="gs-text-secondary">{{ customer.phone ?? '—' }}</td>
                        <td>
                            <span class="gs-badge gs-badge-info">{{ customer.requests_count ?? 0 }}</span>
                        </td>
                        <td>
                            <span class="gs-badge gs-badge-gold">{{ customer.invoices_count ?? 0 }}</span>
                        </td>
                        <td>
                            <div class="gs-action-row">
                                <Link :href="route('customers.show', customer.id)"
                                    class="gs-btn gs-btn-ghost gs-btn-sm">مشاهده</Link>
                                <Link :href="route('customers.edit', customer.id)"
                                    class="gs-btn gs-btn-secondary gs-btn-sm">ویرایش</Link>
                                <button @click="confirmDelete(customer)"
                                    class="gs-btn gs-btn-danger gs-btn-sm">حذف</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty -->
            <div v-else class="gs-empty">
                <span style="font-size:2.5rem">👤</span>
                <p class="gs-subtitle">مشتری‌ای یافت نشد</p>
                <Link :href="route('customers.create')" class="gs-btn gs-btn-primary gs-btn-sm"
                    style="margin-top:.75rem">
                    اولین مشتری را اضافه کنید
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div class="gs-pagination" v-if="customers.last_page > 1">
            <Link v-if="customers.prev_page_url" :href="customers.prev_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">
                قبلی
            </Link>
            <span class="gs-label">صفحه {{ customers.current_page }} از {{ customers.last_page }}</span>
            <Link v-if="customers.next_page_url" :href="customers.next_page_url" class="gs-btn gs-btn-secondary gs-btn-sm">
                بعدی
            </Link>
        </div>

        <!-- Delete Modal -->
        <Transition name="gs-fade">
            <div v-if="deleteTarget" class="gs-modal-overlay" @click.self="deleteTarget = null">
                <div class="gs-modal">
                    <h3 class="gs-subtitle" style="margin-bottom:.5rem">حذف مشتری</h3>
                    <p class="gs-label" style="margin-bottom:1.25rem">
                        آیا از حذف «{{ deleteTarget.name }}» مطمئن هستید؟ این عملیات قابل بازگشت نیست.
                    </p>
                    <div style="display:flex;gap:.75rem;justify-content:flex-end">
                        <button @click="deleteTarget = null" class="gs-btn gs-btn-ghost">انصراف</button>
                        <button @click="doDelete" class="gs-btn gs-btn-danger" :disabled="deleting">
                            {{ deleting ? 'در حال حذف...' : 'حذف' }}
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

const props = defineProps({
    customers: Object,
    filters: Object,
})

const filters = ref({
    search: props.filters?.search ?? '',
    request_status: props.filters?.request_status ?? '',
})

const hasFilters = computed(() => filters.value.search || filters.value.request_status)

let debounceTimer = null
function debounceSearch() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(applyFilters, 350)
}

function applyFilters() {
    router.get(route('customers.index'), filters.value, {
        preserveState: true,
        replace: true,
    })
}

function clearFilters() {
    filters.value = { search: '', request_status: '' }
    applyFilters()
}

// Delete
const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(customer) {
    deleteTarget.value = customer
}

function doDelete() {
    deleting.value = true
    router.delete(route('customers.destroy', deleteTarget.value.id), {
        onFinish: () => {
            deleting.value = false
            deleteTarget.value = null
        },
    })
}
</script>

<style scoped>
.gs-filter-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}

.gs-text-secondary {
    color: var(--gs-text-secondary);
    font-size: .875rem;
}

.gs-text-muted {
    color: var(--gs-text-muted);
    font-size: .8rem;
}

.gs-action-row {
    display: flex;
    gap: .4rem;
}

.gs-empty {
    padding: 3rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
}

.gs-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.25rem;
}

.gs-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(3px);
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.gs-modal {
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border-strong);
    border-radius: 16px;
    padding: 1.75rem;
    max-width: 420px;
    width: 100%;
    box-shadow: var(--gs-shadow-md);
}
</style>
