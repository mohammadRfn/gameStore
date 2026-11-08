<template>
    <AppLayout>
        <div class="gx-page">
            <LuxScene accent="violet" />

            <!-- ================= هیرو ================= -->
            <LuxHero
                chip="ماژول فروش"
                chip-two="Invoices"
                title="فروش و «فاکتورها»"
                lead="مدیریت چرخهٔ فروش — صدور فاکتور، افزودن اقلام، پرداخت و مرجوعی."
                cube="🧾"
                satellite="💰"
                :stats="[
                    { label: 'کل فاکتورها', value: faInt(invoices.total ?? invoices.data.length) },
                    { label: 'در این صفحه', value: faInt(invoices.data.length) },
                    { label: 'پرداخت‌شدهٔ صفحه', value: faInt(paidOnPage) },
                ]"
            >
                <template #chip-icon><ReceiptText :size="13" /></template>
                <template #actions>
                    <button type="button" class="a3d-btn" @click="openProfit">
                        <FileSpreadsheet :size="15" />
                        خروجی سود و زیان
                    </button>
                    <Link :href="route('invoices.create')" class="a3d-btn a3d-btn--gold">
                        <Plus :size="15" />
                        فاکتور جدید
                    </Link>
                </template>
            </LuxHero>

            <!-- ================= جستجو و فیلتر ================= -->
            <div class="gx-toolbar">
                <div class="gx-search">
                    <Search :size="15" class="gx-search__icon" />
                    <input v-model="search" type="search" placeholder="جستجو شماره فاکتور..." />
                </div>
                <div class="gx-seg">
                    <button
                        v-for="opt in STATUS_OPTIONS"
                        :key="opt.value"
                        type="button"
                        class="gx-seg__btn"
                        :class="{ 'is-active': status === opt.value }"
                        @click="status = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- ================= جدول ================= -->
            <div class="gx-panel" :style="{ '--gx-i': 1 }">
                <table class="gs-table" v-if="invoices.data.length">
                    <thead>
                        <tr>
                            <th>شماره فاکتور</th>
                            <th>مشتری</th>
                            <th>تعداد اقلام</th>
                            <th>مبلغ کل</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices.data" :key="invoice.id">
                            <td style="font-family:monospace;direction:ltr;text-align:end;color:var(--gs-gold);font-weight:700">
                                {{ invoice.invoice_number }}
                            </td>
                            <td>{{ invoice.customer?.name ?? '—' }}</td>
                            <td>{{ faInt(invoice.order_items?.length ?? invoice.orderItems?.length ?? 0) }}</td>
                            <td class="gx-price" style="font-size:.85rem">{{ formatPrice(invoice.total_amount) }}</td>
                            <td>
                                <span class="gx-status" :class="invStatusClass(invoice.payment_status)">
                                    <i />
                                    {{ invLabel(invoice.payment_status) }}
                                </span>
                            </td>
                            <td style="text-align:end">
                                <Link :href="route('invoices.show', invoice.id)" class="a3d-btn a3d-btn--sm">
                                    <Eye :size="13" />
                                    مشاهده
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="gx-empty">
                    <span class="gx-empty__icon">🧾</span>
                    <p class="gx-empty__title">فاکتوری ثبت نشده</p>
                    <p class="gx-empty__desc">اولین فاکتور فروش را بساز و اقلام را به آن اضافه کن.</p>
                    <Link :href="route('invoices.create')" class="a3d-btn a3d-btn--gold a3d-btn--sm" style="margin-top:.5rem">
                        <Plus :size="14" />
                        اولین فاکتور را بسازید
                    </Link>
                </div>
            </div>

            <!-- ================= پجینیشن ================= -->
            <div class="gx-pagination" v-if="invoices.last_page > 1">
                <Link v-if="invoices.prev_page_url" :href="invoices.prev_page_url" class="a3d-btn a3d-btn--sm">
                    <ChevronRight :size="14" /> قبلی
                </Link>
                <span class="gx-pagination__num">
                    صفحهٔ <b>{{ faInt(invoices.current_page) }}</b> از {{ faInt(invoices.last_page) }}
                </span>
                <Link v-if="invoices.next_page_url" :href="invoices.next_page_url" class="a3d-btn a3d-btn--sm">
                    بعدی <ChevronLeft :size="14" />
                </Link>
            </div>
        </div>

        <!-- ================= مودال خروجی سود و زیان ================= -->
        <Teleport to="body">
            <Transition name="ipx-fade">
                <div v-if="profitOpen" class="ipx-overlay" @click.self="profitOpen = false" @keydown.esc="profitOpen = false">
                    <div class="ipx" role="dialog" aria-modal="true">
                        <span class="ipx__aura" aria-hidden="true"></span>

                        <header class="ipx__head">
                            <span class="ipx__icon"><FileSpreadsheet :size="20" /></span>
                            <div class="ipx__head-txt">
                                <h3 class="ipx__title">خروجی سود و زیان فاکتورها</h3>
                                <p class="ipx__sub">همهٔ فاکتورهای بازهٔ انتخابی در یک فایل اکسل، همراه سود هر فاکتور.</p>
                            </div>
                            <button type="button" class="ipx__close" title="بستن" @click="profitOpen = false">✕</button>
                        </header>

                        <div class="ipx__presets">
                            <button
                                v-for="p in PROFIT_PRESETS"
                                :key="p.key"
                                type="button"
                                class="ipx__chip"
                                @click="applyProfitPreset(p.key)"
                            >
                                {{ p.label }}
                            </button>
                        </div>

                        <div class="ipx__dates">
                            <div class="ipx__field">
                                <span class="ipx__label">از تاریخ</span>
                                <JalaliDateInput v-model="profitFrom" placeholder="تاریخ شروع" :clearable="false" />
                            </div>
                            <div class="ipx__field">
                                <span class="ipx__label">تا تاریخ</span>
                                <JalaliDateInput v-model="profitTo" placeholder="تاریخ پایان" :clearable="false" />
                            </div>
                        </div>

                        <ul class="ipx__rules">
                            <li>فاکتور پرداخت‌شده «سود محقق‌شده» و پرداخت‌نشده «سود احتمالی» جدا جمع می‌شوند.</li>
                            <li>فاکتور یا قلم مرجوع‌شده از درآمد، هزینه و سود حذف می‌شود.</li>
                            <li>تعدیل‌های غیردرآمدی (ارسال، مالیات، کارمزد و…) در سود لحاظ نمی‌شوند.</li>
                        </ul>

                        <p v-if="profitError" class="ipx__error">{{ profitError }}</p>

                        <footer class="ipx__foot">
                            <button type="button" class="a3d-btn" @click="profitOpen = false">انصراف</button>
                            <button type="button" class="a3d-btn a3d-btn--gold" @click="downloadProfit">
                                <Download :size="15" />
                                دریافت اکسل
                            </button>
                        </footer>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LuxScene from '@/Components/Lux/LuxScene.vue'
import LuxHero from '@/Components/Lux/LuxHero.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import { toJalaali, toGregorian, jalaaliMonthLength } from 'jalaali-js'
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    FileSpreadsheet,
    Plus,
    ReceiptText,
    Search,
} from 'lucide-vue-next'

const props = defineProps({
    invoices: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

const STATUS_OPTIONS = [
    { value: '', label: 'همه' },
    { value: 'unpaid', label: 'پرداخت نشده' },
    { value: 'paid', label: 'پرداخت شده' },
    { value: 'returned', label: 'مرجوع شده' },
]

const paidOnPage = computed(() =>
    props.invoices.data.filter(i => i.payment_status === 'paid').length
)

let debounceTimer = null
watch([search, status], () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('invoices.index'), { search: search.value, status: status.value }, {
            preserveState: true,
            replace: true,
        })
    }, 350)
})

function invLabel(v) {
    if (v === 'paid') return 'پرداخت شده'
    if (v === 'returned') return 'مرجوع شده'
    return 'پرداخت نشده'
}
function invStatusClass(v) {
    if (v === 'paid') return 'gx-status--green'
    if (v === 'returned') return 'gx-status--red'
    return 'gx-status--amber'
}
function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}
const faInt = n => Number(n ?? 0).toLocaleString('fa-IR')

/* ================= خروجی سود و زیان ================= */
const profitOpen = ref(false)
const profitFrom = ref(null)   // ISO میلادی 'YYYY-MM-DD' (قرارداد JalaliDateInput)
const profitTo = ref(null)
const profitError = ref('')

const PROFIT_PRESETS = [
    { key: 'today', label: 'امروز' },
    { key: '7d', label: '۷ روز اخیر' },
    { key: '30d', label: '۳۰ روز اخیر' },
    { key: 'this-month', label: 'این ماه' },
    { key: 'last-month', label: 'ماه قبل' },
    { key: 'this-year', label: 'امسال' },
]

const pad2 = n => String(n).padStart(2, '0')
// عمداً از toISOString استفاده نمی‌شود (اختلاف منطقهٔ زمانی تاریخ را یک روز جابه‌جا می‌کند)
const isoLocal = d => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`
const isoG = ({ gy, gm, gd }) => `${gy}-${pad2(gm)}-${pad2(gd)}`

function applyProfitPreset(key) {
    const now = new Date()
    const today = isoLocal(now)
    const j = toJalaali(now)
    profitError.value = ''

    if (key === 'today') {
        profitFrom.value = today
        profitTo.value = today
    } else if (key === '7d' || key === '30d') {
        const start = new Date(now)
        start.setDate(start.getDate() - (key === '7d' ? 6 : 29))
        profitFrom.value = isoLocal(start)
        profitTo.value = today
    } else if (key === 'this-month') {
        profitFrom.value = isoG(toGregorian(j.jy, j.jm, 1))
        profitTo.value = today
    } else if (key === 'last-month') {
        const jy = j.jm === 1 ? j.jy - 1 : j.jy
        const jm = j.jm === 1 ? 12 : j.jm - 1
        profitFrom.value = isoG(toGregorian(jy, jm, 1))
        profitTo.value = isoG(toGregorian(jy, jm, jalaaliMonthLength(jy, jm)))
    } else if (key === 'this-year') {
        profitFrom.value = isoG(toGregorian(j.jy, 1, 1))
        profitTo.value = today
    }
}

function openProfit() {
    if (!profitFrom.value || !profitTo.value) {
        applyProfitPreset('this-month')
    }
    profitError.value = ''
    profitOpen.value = true
}

function downloadProfit() {
    profitError.value = ''

    if (!profitFrom.value || !profitTo.value) {
        profitError.value = 'تاریخ شروع و پایان را انتخاب کن.'
        return
    }
    if (profitFrom.value > profitTo.value) {
        profitError.value = 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.'
        return
    }

    window.location.href = route('invoices.profit-export', {
        from: profitFrom.value,
        to: profitTo.value,
    })
    profitOpen.value = false
}
</script>

<style scoped>
.ipx-overlay {
    position: fixed;
    inset: 0;
    z-index: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(4, 4, 8, 0.62);
    backdrop-filter: blur(7px) saturate(0.85);
    -webkit-backdrop-filter: blur(7px) saturate(0.85);
}

.ipx {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: min(560px, 100%);
    max-height: 90vh;
    /* بدون اسکرول افقی: هاله‌ی پشت کادر از لبه بیرون می‌زند و با overflow-y محور افقی هم اسکرول‌پذیر می‌شد */
    overflow-x: hidden;
    overflow-y: auto;
    padding: 1.3rem 1.4rem 1.1rem;
    border-radius: 20px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-bg-card-strong);
    box-shadow: var(--gs-shadow-md), 0 0 70px -22px var(--gs-gold-glow);
    color: var(--gs-text-primary);
    direction: rtl;
}

.ipx *, .ipx *::before, .ipx *::after { box-sizing: border-box; min-width: 0; }

.ipx__aura {
    position: absolute;
    inset: -55% 20% 72% -25%;
    background: radial-gradient(closest-side, var(--gs-gold-glow), transparent 72%);
    opacity: 0.35;
    pointer-events: none;
}

.ipx__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding-bottom: 0.9rem;
    border-bottom: 1px solid var(--gs-border-soft);
}

.ipx__icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    flex: none;
    border-radius: 13px;
    color: var(--gs-gold);
    background: var(--gs-gold-muted);
    border: 1px solid var(--gs-border-hover);
}

.ipx__head-txt { flex: 1; }
.ipx__title { margin: 0; font-size: 1.02rem; font-weight: 800; color: var(--gs-text-primary); }
.ipx__sub { margin: 0.25rem 0 0; font-size: 0.78rem; line-height: 1.7; color: var(--gs-text-secondary); }

.ipx__close {
    width: 32px;
    height: 32px;
    flex: none;
    border-radius: 10px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    cursor: pointer;
    transition: var(--gs-transition);
}
.ipx__close:hover { color: var(--gs-text-primary); border-color: var(--gs-border-hover); background: var(--gs-glass-hover); }

.ipx__presets { display: flex; flex-wrap: wrap; gap: 0.45rem; }

.ipx__chip {
    padding: 0.38rem 0.8rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font-size: 0.76rem;
    font-family: inherit;
    cursor: pointer;
    transition: var(--gs-transition);
}
.ipx__chip:hover { color: var(--gs-gold-light); border-color: var(--gs-border-hover); background: var(--gs-gold-muted); }

.ipx__dates { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
.ipx__field { display: flex; flex-direction: column; gap: 0.35rem; }
.ipx__label { font-size: 0.74rem; font-weight: 700; color: var(--gs-text-secondary); }

.ipx__rules {
    margin: 0;
    padding: 0.7rem 1.1rem 0.7rem 0.8rem;
    list-style: disc;
    list-style-position: inside;
    border-radius: 12px;
    border: 1px dashed var(--gs-border);
    background: var(--gs-glass);
    font-size: 0.74rem;
    line-height: 1.95;
    color: var(--gs-text-secondary);
}

.ipx__error {
    margin: 0;
    padding: 0.55rem 0.8rem;
    border-radius: 10px;
    font-size: 0.78rem;
    color: var(--gs-error);
    background: var(--gs-error-soft);
    border: 1px solid var(--gs-error);
}

.ipx__foot {
    display: flex;
    justify-content: flex-start;
    gap: 0.6rem;
    padding-top: 0.2rem;
}

.ipx-fade-enter-active, .ipx-fade-leave-active { transition: opacity 0.2s ease; }
.ipx-fade-enter-from, .ipx-fade-leave-to { opacity: 0; }

@media (max-width: 520px) {
    .ipx__dates { grid-template-columns: 1fr; }
}
</style>
