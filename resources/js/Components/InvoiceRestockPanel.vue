<!--
  resources/js/Components/InvoiceRestockPanel.vue
  ------------------------------------------------------------------
  پنل «برگشت به انبار» — بازطراحی کامل با زبان بصری بخش تنظیمات.

  ⚠️ منطق و ارتباط با بک‌اند دست‌نخورده:
     props : invoice
     route : invoices.restock-items  (POST { order_item_ids })
     شرط نمایش: invoice.is_returned
-->
<template>
    <section v-if="invoice.is_returned" class="rsk">
        <span class="rsk__aura" aria-hidden="true"></span>

        <!-- سربرگ -->
        <header class="rsk__head">
            <span class="rsk__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8.5 12 3 3 8.5v7L12 21l9-5.5v-7Z" />
                    <path d="m3 8.5 9 5.5 9-5.5M12 14v7" />
                </svg>
            </span>

            <div class="rsk__head-txt">
                <h3 class="rsk__title">برگشت به انبار</h3>
                <p class="rsk__desc">اقلامی که با فروش از موجودی کسر شده‌اند را دوباره به انبار برگردانید.</p>
            </div>

            <div class="rsk__progress" v-if="restockableItems.length">
                <svg class="rsk__ring" viewBox="0 0 44 44" aria-hidden="true">
                    <circle class="rsk__ring-bg" cx="22" cy="22" r="18" />
                    <circle
                        class="rsk__ring-fg"
                        cx="22" cy="22" r="18"
                        :style="{ strokeDasharray: RING, strokeDashoffset: ringOffset }"
                    />
                </svg>
                <span class="rsk__progress-txt">{{ fa(doneCount) }}<i>/</i>{{ fa(restockableItems.length) }}</span>
            </div>
        </header>

        <!-- خالی -->
        <div v-if="!restockableItems.length" class="rsk__empty">
            <span class="rsk__empty-ico">📦</span>
            <p>قلمی برای برگشت به انبار وجود ندارد</p>
        </div>

        <template v-else>
            <!-- نوار ابزار -->
            <div v-if="hasPendingRestock" class="rsk__toolbar">
                <button type="button" class="rsk__link" @click="toggleAll">
                    {{ allSelected ? 'لغو انتخاب همه' : 'انتخاب همه' }}
                </button>
                <Transition name="rsk-pop">
                    <span v-if="selected.length" class="rsk__count">{{ fa(selected.length) }} قلم انتخاب شد</span>
                </Transition>
            </div>

            <!-- لیست -->
            <TransitionGroup tag="ul" name="rsk-list" class="rsk__list">
                <li
                    v-for="(item, i) in restockableItems"
                    :key="item.id"
                    class="rsk__row"
                    :class="{ 'is-done': !!item.restocked_at, 'is-picked': selected.includes(item.id) }"
                    :style="{ '--i': i }"
                >
                    <label class="rsk__pick">
                        <input
                            type="checkbox"
                            class="rsk__native"
                            :value="item.id"
                            v-model="selected"
                            :disabled="!!item.restocked_at"
                        />

                        <span class="rsk__box" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m4.5 12.5 5 5 10-11" />
                            </svg>
                        </span>

                        <span class="rsk__body">
                            <span class="rsk__name">{{ item.product_name }}</span>
                            <span class="rsk__meta">
                                <span class="rsk__qty">{{ fa(item.quantity) }} عدد</span>
                                <span v-if="item.restocked_at" class="rsk__tag">
                                    <span class="rsk__tag-dot"></span> برگشته به انبار
                                </span>
                            </span>
                        </span>
                    </label>
                </li>
            </TransitionGroup>

            <!-- اکشن -->
            <footer v-if="hasPendingRestock" class="rsk__foot">
                <button
                    type="button"
                    class="rsk__btn"
                    :disabled="!selected.length || processing"
                    @click="confirmRestock"
                >
                    <span class="rsk__btn-sheen" aria-hidden="true"></span>
                    <span v-if="processing" class="rsk__spinner" aria-hidden="true"></span>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="rsk__btn-ico">
                        <path d="M3 12a9 9 0 1 0 2.6-6.4" /><path d="M3 4v5h5" />
                    </svg>
                    <span>{{ processing ? 'در حال ثبت…' : 'تأیید و برگشت به انبار' }}</span>
                </button>
            </footer>
        </template>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ invoice: Object })

/* ── منطق اصلی: بدون تغییر ─────────────────────────────── */
const restockableItems = computed(() =>
    (props.invoice.order_items ?? []).filter(i => i.deduct_from_stock)
)
const hasPendingRestock = computed(() =>
    restockableItems.value.some(i => !i.restocked_at)
)

const selected = ref([])
const processing = ref(false)

function confirmRestock() {
    processing.value = true
    router.post(route('invoices.restock-items', props.invoice.id), {
        order_item_ids: selected.value,
    }, {
        onFinish: () => { processing.value = false; selected.value = [] },
    })
}
/* ──────────────────────────────────────────────────────── */

/* کمکی‌های نمایشی */
const RING = 2 * Math.PI * 18

const doneCount = computed(() => restockableItems.value.filter(i => i.restocked_at).length)

const ringOffset = computed(() => {
    const total = restockableItems.value.length || 1
    return RING * (1 - doneCount.value / total)
})

const pendingIds = computed(() =>
    restockableItems.value.filter(i => !i.restocked_at).map(i => i.id)
)

const allSelected = computed(() =>
    pendingIds.value.length > 0 && selected.value.length === pendingIds.value.length
)

function toggleAll() {
    selected.value = allSelected.value ? [] : [...pendingIds.value]
}

function fa(n) {
    return Number(n ?? 0).toLocaleString('fa-IR')
}
</script>

<style scoped>
.rsk {
    position: relative;
    overflow: hidden;
    padding: 1.4rem 1.5rem 1.25rem;
    border-radius: var(--gs-radius-lg);
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-card);
    backdrop-filter: blur(16px) saturate(1.3);
    -webkit-backdrop-filter: blur(16px) saturate(1.3);
    box-shadow: var(--gs-shadow-sm);
    animation: rsk-in 0.55s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
}

.rsk__aura {
    position: absolute;
    inset: -60% 40% 55% -20%;
    background: radial-gradient(closest-side, var(--gs-gold-glow), transparent 75%);
    opacity: 0.35;
    pointer-events: none;
}

/* ── سربرگ ─────────────────────────────────────────────── */
.rsk__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gs-border-soft);
}

.rsk__icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: none;
    border-radius: 13px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    box-shadow: var(--gs-shadow-gold);
    transition: transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.rsk__icon svg { width: 22px; height: 22px; }
.rsk:hover .rsk__icon { transform: rotate(-8deg) scale(1.07); }

.rsk__head-txt { flex: 1; min-width: 0; }

.rsk__title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--gs-text-primary);
}

.rsk__desc {
    margin: 0.15rem 0 0;
    font-size: 0.74rem;
    line-height: 1.85;
    color: var(--gs-text-secondary);
}

/* حلقهٔ پیشرفت */
.rsk__progress {
    position: relative;
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: none;
}

.rsk__ring { width: 44px; height: 44px; transform: rotate(-90deg); }

.rsk__ring-bg {
    fill: none;
    stroke: var(--gs-border-soft);
    stroke-width: 3.5;
}

.rsk__ring-fg {
    fill: none;
    stroke: var(--gs-success);
    stroke-width: 3.5;
    stroke-linecap: round;
    filter: drop-shadow(0 0 5px var(--gs-success-soft));
    transition: stroke-dashoffset 0.8s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.rsk__progress-txt {
    position: absolute;
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--gs-text-secondary);
}

.rsk__progress-txt i { opacity: 0.45; font-style: normal; margin: 0 1px; }

/* ── نوار ابزار ────────────────────────────────────────── */
.rsk__toolbar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0 0.15rem;
}

.rsk__link {
    border: 0;
    background: none;
    padding: 0.2rem 0;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--gs-gold);
    cursor: pointer;
    position: relative;
}

.rsk__link::after {
    content: '';
    position: absolute;
    inset-inline: 0;
    bottom: -1px;
    height: 1px;
    background: var(--gs-gold);
    transform: scaleX(0);
    transform-origin: inline-end;
    transition: transform 0.32s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.rsk__link:hover::after { transform: scaleX(1); transform-origin: inline-start; }

.rsk__count {
    padding: 0.2rem 0.62rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    font-size: 0.68rem;
    font-weight: 700;
}

/* ── لیست ──────────────────────────────────────────────── */
.rsk__list {
    position: relative;
    list-style: none;
    margin: 0.55rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.rsk__row {
    border-radius: 13px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    overflow: hidden;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        transform 0.32s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    animation: rsk-row-in 0.5s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
    animation-delay: calc(var(--i) * 55ms);
}

.rsk__row:hover {
    border-color: var(--gs-border-hover);
    background: var(--gs-glass-hover);
    transform: translateX(-3px);
}

.rsk__row.is-picked {
    border-color: var(--gs-border-hover);
    background: var(--gs-gold-muted);
    box-shadow: inset 3px 0 0 0 var(--gs-gold);
}

.rsk__row.is-done {
    opacity: 0.62;
    box-shadow: inset 3px 0 0 0 var(--gs-success);
}

.rsk__row.is-done:hover { transform: none; }

.rsk__pick {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 0.85rem;
    cursor: pointer;
    user-select: none;
}

.rsk__row.is-done .rsk__pick { cursor: default; }

.rsk__native {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

/* چک‌باکس سفارشی با انیمیشن فنری */
.rsk__box {
    display: grid;
    place-items: center;
    width: 21px;
    height: 21px;
    flex: none;
    border-radius: 7px;
    border: 1.5px solid var(--gs-border-hover);
    background: var(--gs-bg-elevated);
    color: #14100a;
    transition:
        background 0.3s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        border-color 0.3s ease,
        transform 0.35s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)),
        box-shadow 0.3s ease;
}

.rsk__box svg {
    width: 13px;
    height: 13px;
    stroke-dasharray: 26;
    stroke-dashoffset: 26;
    transition: stroke-dashoffset 0.35s 0.06s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.rsk__pick:hover .rsk__box { border-color: var(--gs-gold); transform: scale(1.06); }

.rsk__native:checked + .rsk__box {
    background: var(--gs-gold-grad);
    border-color: transparent;
    box-shadow: 0 0 14px var(--gs-gold-glow);
}

.rsk__native:checked + .rsk__box svg { stroke-dashoffset: 0; }

.rsk__native:focus-visible + .rsk__box { box-shadow: 0 0 0 4px var(--gs-gold-muted); }

.rsk__native:disabled + .rsk__box {
    background: var(--gs-success-soft);
    border-color: color-mix(in srgb, var(--gs-success) 40%, transparent);
    color: var(--gs-success);
}

.rsk__native:disabled + .rsk__box svg { stroke-dashoffset: 0; }

.rsk__body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.rsk__name {
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--gs-text-primary);
    overflow-wrap: anywhere;
}

.rsk__meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    font-size: 0.72rem;
    color: var(--gs-text-secondary);
}

.rsk__qty { font-variant-numeric: tabular-nums; }

.rsk__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--gs-success) 32%, transparent);
    background: var(--gs-success-soft);
    color: var(--gs-success);
    font-size: 0.66rem;
    font-weight: 700;
}

.rsk__tag-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
    animation: rsk-blink 2.2s ease-in-out infinite;
}

/* ── خالی ─────────────────────────────────────────────── */
.rsk__empty {
    position: relative;
    text-align: center;
    padding: 1.9rem 1rem 1.2rem;
    color: var(--gs-text-muted);
    font-size: 0.82rem;
}

.rsk__empty-ico {
    display: block;
    font-size: 1.9rem;
    margin-bottom: 0.4rem;
    filter: grayscale(0.35);
    animation: rsk-float 3.6s ease-in-out infinite;
}

/* ── فوتر و دکمه ──────────────────────────────────────── */
.rsk__foot {
    position: relative;
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
}

.rsk__btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.62rem 1.25rem;
    border: 0;
    border-radius: 12px;
    background: var(--gs-gold-grad);
    color: #14100a;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 800;
    cursor: pointer;
    overflow: hidden;
    box-shadow: 0 6px 22px var(--gs-gold-glow);
    transition: transform 0.3s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)), box-shadow 0.3s ease, opacity 0.25s ease;
}

.rsk__btn-ico { width: 16px; height: 16px; }

.rsk__btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 30px var(--gs-gold-glow); }
.rsk__btn:active:not(:disabled) { transform: translateY(0) scale(0.98); }

.rsk__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    box-shadow: none;
    filter: grayscale(0.4);
}

.rsk__btn-sheen {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40%;
    background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.55), transparent);
    transform: translateX(-160%);
}

.rsk__btn:hover:not(:disabled) .rsk__btn-sheen { animation: rsk-sheen 0.9s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }

.rsk__spinner {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2px solid rgba(20, 16, 10, 0.25);
    border-top-color: #14100a;
    animation: rsk-spin 0.7s linear infinite;
}

/* ── ترنزیشن‌ها ───────────────────────────────────────── */
.rsk-list-move { transition: transform 0.45s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }
.rsk-list-enter-from, .rsk-list-leave-to { opacity: 0; transform: translateY(-6px); }
.rsk-list-leave-active { position: absolute; }

.rsk-pop-enter-active { transition: all 0.3s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)); }
.rsk-pop-leave-active { transition: all 0.16s ease; }
.rsk-pop-enter-from, .rsk-pop-leave-to { opacity: 0; transform: scale(0.8); }

@keyframes rsk-in { from { opacity: 0; transform: translateY(14px); } }
@keyframes rsk-row-in { from { opacity: 0; transform: translateY(8px); } }
@keyframes rsk-spin { to { transform: rotate(360deg); } }
@keyframes rsk-sheen { to { transform: translateX(320%); } }
@keyframes rsk-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
@keyframes rsk-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }

@media (max-width: 640px) {
    .rsk { padding: 1.1rem 1rem; }
    .rsk__progress { display: none; }
    .rsk__btn { width: 100%; justify-content: center; }
}

@media (prefers-reduced-motion: reduce) {
    .rsk *, .rsk { animation: none !important; transition-duration: 0.01ms !important; }
}
</style>
