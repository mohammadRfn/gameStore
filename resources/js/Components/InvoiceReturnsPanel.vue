<!--
  resources/js/Components/InvoiceReturnsPanel.vue
  ------------------------------------------------------------------
  پنل «مرجوعی اقلام» — بازطراحی کامل هم‌سطح بخش تنظیمات.

  ⚠️ منطق و ارتباط با بک‌اند دست‌نخورده:
     props : orderItems (Array)
     routes: order-items.return   (POST { restock })
             order-items.unreturn (POST {})
-->
<template>
    <section class="rtn">
        <span class="rtn__aura" aria-hidden="true"></span>

        <header class="rtn__head">
            <span class="rtn__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 14 4 9l5-5" />
                    <path d="M4 9h10a6 6 0 0 1 6 6v5" />
                </svg>
            </span>

            <div class="rtn__head-txt">
                <h3 class="rtn__title">مرجوعی اقلام</h3>
                <p class="rtn__desc">قلم‌های مرجوع‌شده را علامت بزنید؛ در صورت نیاز به انبار برگردانید.</p>
            </div>

            <div class="rtn__stats">
                <span class="rtn__stat">
                    <b>{{ fa(returnedCount) }}</b> مرجوع
                </span>
                <span class="rtn__stat rtn__stat--total">
                    <b>{{ fa(orderItems?.length ?? 0) }}</b> قلم
                </span>
            </div>
        </header>

        <div v-if="!orderItems?.length" class="rtn__empty">
            <span class="rtn__empty-ico">🧾</span>
            <p>قلمی برای مرجوعی وجود ندارد</p>
        </div>

        <TransitionGroup v-else tag="ul" name="rtn-list" class="rtn__list">
            <li
                v-for="(item, i) in orderItems"
                :key="item.id"
                class="rtn__row"
                :class="{ 'is-returned': item.is_returned, 'is-busy': processingId === item.id }"
                :style="{ '--i': i }"
            >
                <span class="rtn__rail" aria-hidden="true"></span>

                <div class="rtn__info">
                    <p class="rtn__name">
                        {{ item.product_name }}
                        <Transition name="rtn-pop">
                            <span v-if="item.is_returned" class="rtn__tag">
                                مرجوع شده{{ item.restock_on_return ? ' · برگشت به انبار' : '' }}
                            </span>
                        </Transition>
                    </p>
                    <p class="rtn__meta">
                        <span class="rtn__chip">{{ fa(item.quantity) }} عدد</span>
                        <span class="rtn__price">{{ formatPrice(item.total_price) }}</span>
                    </p>
                </div>

                <label
                    v-if="item.deduct_from_stock && !item.is_returned"
                    class="rtn__switch"
                    :title="'با مرجوعی، این قلم به موجودی انبار اضافه شود'"
                >
                    <input type="checkbox" class="rtn__native" v-model="restockMap[item.id]" />
                    <span class="rtn__track" aria-hidden="true"><span class="rtn__knob"></span></span>
                    <span class="rtn__switch-txt">برگرده به انبار</span>
                </label>

                <button
                    v-if="!item.is_returned"
                    type="button"
                    class="rtn__btn rtn__btn--danger"
                    :disabled="processingId === item.id"
                    @click="markReturned(item)"
                >
                    <span v-if="processingId === item.id" class="rtn__spinner" aria-hidden="true"></span>
                    <span>{{ processingId === item.id ? 'در حال ثبت…' : 'مرجوع شد' }}</span>
                </button>

                <button
                    v-else
                    type="button"
                    class="rtn__btn rtn__btn--ghost"
                    :disabled="processingId === item.id"
                    @click="unmarkReturned(item)"
                >
                    <span v-if="processingId === item.id" class="rtn__spinner rtn__spinner--soft" aria-hidden="true"></span>
                    <span>{{ processingId === item.id ? 'در حال لغو…' : 'لغو مرجوعی' }}</span>
                </button>
            </li>
        </TransitionGroup>
    </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ orderItems: Array })

/* ── منطق اصلی: بدون تغییر ─────────────────────────────── */
const restockMap = reactive({})
const processingId = ref(null)

function markReturned(item) {
    processingId.value = item.id
    router.post(route('order-items.return', item.id), {
        restock: !!restockMap[item.id],
    }, {
        onFinish: () => processingId.value = null,
    })
}

function unmarkReturned(item) {
    processingId.value = item.id
    router.post(route('order-items.unreturn', item.id), {}, {
        onFinish: () => processingId.value = null,
    })
}

function formatPrice(p) {
    return p ? Number(p).toLocaleString('fa-IR') + ' تومان' : '—'
}
/* ──────────────────────────────────────────────────────── */

const returnedCount = computed(() => (props.orderItems ?? []).filter(i => i.is_returned).length)

function fa(n) {
    return Number(n ?? 0).toLocaleString('fa-IR')
}
</script>

<style scoped>
.rtn {
    position: relative;
    overflow: hidden;
    padding: 1.4rem 1.5rem 1.35rem;
    border-radius: var(--gs-radius-lg);
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-card);
    backdrop-filter: blur(16px) saturate(1.3);
    -webkit-backdrop-filter: blur(16px) saturate(1.3);
    box-shadow: var(--gs-shadow-sm);
    animation: rtn-in 0.55s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
}

.rtn__aura {
    position: absolute;
    inset: -70% -25% 60% 45%;
    background: radial-gradient(closest-side, var(--gs-error-soft), transparent 75%);
    opacity: 0.8;
    pointer-events: none;
}

/* ── سربرگ ─────────────────────────────────────────────── */
.rtn__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gs-border-soft);
}

.rtn__icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: none;
    border-radius: 13px;
    border: 1px solid color-mix(in srgb, var(--gs-error) 30%, transparent);
    background: var(--gs-error-soft);
    color: var(--gs-error);
    transition: transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.rtn__icon svg { width: 22px; height: 22px; }
.rtn:hover .rtn__icon { transform: rotate(8deg) scale(1.07); }

.rtn__head-txt { flex: 1; min-width: 0; }

.rtn__title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--gs-text-primary);
}

.rtn__desc {
    margin: 0.15rem 0 0;
    font-size: 0.74rem;
    line-height: 1.85;
    color: var(--gs-text-secondary);
}

.rtn__stats { display: flex; gap: 0.4rem; flex: none; }

.rtn__stat {
    padding: 0.3rem 0.62rem;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--gs-error) 26%, transparent);
    background: var(--gs-error-soft);
    color: var(--gs-error);
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
}

.rtn__stat b { font-weight: 800; font-variant-numeric: tabular-nums; }

.rtn__stat--total {
    border-color: var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
}

.rtn__stat--total b { color: var(--gs-text-primary); }

/* ── لیست ──────────────────────────────────────────────── */
.rtn__list {
    position: relative;
    list-style: none;
    margin: 0.7rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.rtn__row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.72rem 0.9rem 0.72rem 0.9rem;
    border-radius: 13px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    overflow: hidden;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        transform 0.32s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    animation: rtn-row-in 0.5s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
    animation-delay: calc(var(--i) * 55ms);
}

.rtn__row:hover {
    border-color: var(--gs-border-hover);
    background: var(--gs-glass-hover);
    transform: translateX(-3px);
}

.rtn__rail {
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--gs-gold-grad);
    transform: scaleY(0);
    transform-origin: center;
    transition: transform 0.4s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.rtn__row:hover .rtn__rail { transform: scaleY(1); }

.rtn__row.is-returned {
    background: color-mix(in srgb, var(--gs-error) 5%, transparent);
    border-color: color-mix(in srgb, var(--gs-error) 20%, transparent);
}

.rtn__row.is-returned .rtn__rail { background: var(--gs-error); transform: scaleY(1); }
.rtn__row.is-returned .rtn__name { color: var(--gs-text-secondary); }

.rtn__row.is-busy { opacity: 0.75; }

.rtn__info { flex: 1; min-width: 0; }

.rtn__name {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin: 0;
    font-size: 0.87rem;
    font-weight: 600;
    color: var(--gs-text-primary);
    overflow-wrap: anywhere;
}

.rtn__tag {
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--gs-error) 34%, transparent);
    background: var(--gs-error-soft);
    color: var(--gs-error);
    font-size: 0.64rem;
    font-weight: 700;
    white-space: nowrap;
}

.rtn__meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0.22rem 0 0;
    font-size: 0.73rem;
    color: var(--gs-text-secondary);
}

.rtn__chip {
    padding: 0.05rem 0.45rem;
    border-radius: 6px;
    background: var(--gs-glass-hover);
    border: 1px solid var(--gs-border-soft);
    font-variant-numeric: tabular-nums;
}

.rtn__price {
    color: var(--gs-gold);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

/* ── سوئیچ «برگرده به انبار» ───────────────────────────── */
.rtn__switch {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex: none;
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}

.rtn__native { position: absolute; opacity: 0; width: 0; height: 0; }

.rtn__track {
    position: relative;
    width: 40px;
    height: 22px;
    flex: none;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-elevated);
    transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

.rtn__knob {
    position: absolute;
    top: 50%;
    inset-inline-start: 3px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--gs-text-muted);
    transform: translateY(-50%);
    transition: inset-inline-start 0.34s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)), background 0.3s ease;
}

.rtn__switch:hover .rtn__track { border-color: var(--gs-border-hover); }

.rtn__native:checked + .rtn__track {
    background: linear-gradient(135deg, var(--gs-success), #1f9d63);
    border-color: transparent;
    box-shadow: 0 0 14px var(--gs-success-soft);
}

.rtn__native:checked + .rtn__track .rtn__knob {
    inset-inline-start: 21px;
    background: #0d0d14;
}

.rtn__native:focus-visible + .rtn__track { box-shadow: 0 0 0 4px var(--gs-gold-muted); }

.rtn__switch-txt {
    font-size: 0.73rem;
    color: var(--gs-text-secondary);
    transition: color 0.25s ease;
}

.rtn__native:checked ~ .rtn__switch-txt { color: var(--gs-success); font-weight: 600; }

/* ── دکمه‌ها ───────────────────────────────────────────── */
.rtn__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: none;
    padding: 0.45rem 0.9rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition:
        transform 0.28s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)),
        background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, color 0.25s ease;
}

.rtn__btn:disabled { opacity: 0.55; cursor: progress; }
.rtn__btn:active:not(:disabled) { transform: scale(0.96); }

.rtn__btn--danger {
    background: var(--gs-error-soft);
    border-color: color-mix(in srgb, var(--gs-error) 35%, transparent);
    color: var(--gs-error);
}

.rtn__btn--danger:hover:not(:disabled) {
    background: var(--gs-error);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px color-mix(in srgb, var(--gs-error) 30%, transparent);
}

.rtn__btn--ghost {
    background: var(--gs-glass);
    border-color: var(--gs-border);
    color: var(--gs-text-secondary);
}

.rtn__btn--ghost:hover:not(:disabled) {
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
    background: var(--gs-gold-muted);
    transform: translateY(-2px);
}

.rtn__spinner {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid color-mix(in srgb, var(--gs-error) 25%, transparent);
    border-top-color: var(--gs-error);
    animation: rtn-spin 0.7s linear infinite;
}

.rtn__spinner--soft {
    border-color: var(--gs-border);
    border-top-color: var(--gs-gold);
}

/* ── خالی ─────────────────────────────────────────────── */
.rtn__empty {
    position: relative;
    text-align: center;
    padding: 2rem 1rem 1.3rem;
    color: var(--gs-text-muted);
    font-size: 0.82rem;
}

.rtn__empty-ico {
    display: block;
    font-size: 1.9rem;
    margin-bottom: 0.4rem;
    filter: grayscale(0.3);
    animation: rtn-float 3.6s ease-in-out infinite;
}

/* ── ترنزیشن‌ها ───────────────────────────────────────── */
.rtn-list-move { transition: transform 0.45s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)); }
.rtn-list-enter-from, .rtn-list-leave-to { opacity: 0; transform: translateY(-6px); }
.rtn-list-leave-active { position: absolute; }

.rtn-pop-enter-active { transition: all 0.32s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)); }
.rtn-pop-leave-active { transition: all 0.18s ease; }
.rtn-pop-enter-from, .rtn-pop-leave-to { opacity: 0; transform: scale(0.7); }

@keyframes rtn-in { from { opacity: 0; transform: translateY(14px); } }
@keyframes rtn-row-in { from { opacity: 0; transform: translateY(8px); } }
@keyframes rtn-spin { to { transform: rotate(360deg); } }
@keyframes rtn-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }

@media (max-width: 720px) {
    .rtn { padding: 1.1rem 1rem; }
    .rtn__row { flex-wrap: wrap; }
    .rtn__info { flex: 1 1 100%; }
    .rtn__btn { flex: 1; justify-content: center; }
}

@media (prefers-reduced-motion: reduce) {
    .rtn *, .rtn { animation: none !important; transition-duration: 0.01ms !important; }
}
</style>
