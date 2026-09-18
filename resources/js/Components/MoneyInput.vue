<!--
  resources/js/Components/MoneyInput.vue
  ------------------------------------------------------------------
  ورودی مبلغ — بازطراحی‌شده هم‌خانواده با کنترل‌های بخش تنظیمات (st-*)

  ⚠️ قرارداد قبلی ۱۰۰٪ حفظ شده:
     props : modelValue | placeholder | error
     emit  : update:modelValue  (Number یا '' مثل قبل)
     نمایش : Number(v).toLocaleString('en-US')  ← دقیقاً مثل قبل

  props اختیاری اضافه‌شده (همه دارای مقدار پیش‌فرض، شکستنِ چیزی ندارند):
     suffix   → پسوند نمایشی (پیش‌فرض: تومان)
     hint     → نمایش چیپ مقیاس (هزار/میلیون/میلیارد)
     disabled | readonly | size ('md' | 'sm')
-->
<template>
    <label
        class="mny"
        :class="[
            attrsClass,
            `mny--${size}`,
            { 'is-error': error, 'is-disabled': disabled, 'is-focus': focused, 'is-filled': hasValue },
        ]"
        :style="attrsStyle"
    >
        <span class="mny__glow" aria-hidden="true"></span>

        <span class="mny__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="6" width="20" height="12" rx="3" />
                <circle cx="12" cy="12" r="2.6" />
                <path d="M6 12h.01M18 12h.01" />
            </svg>
        </span>

        <input
            ref="inputRef"
            v-bind="restAttrs"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            dir="ltr"
            class="mny__input"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :value="displayValue"
            @focus="focused = true"
            @blur="focused = false"
            @input="onInput"
        />

        <Transition name="mny-pop">
            <span v-if="hint && scaleLabel" class="mny__scale">{{ scaleLabel }}</span>
        </Transition>

        <span v-if="suffix" class="mny__suffix">{{ suffix }}</span>

        <span class="mny__bar" aria-hidden="true"></span>
    </label>
</template>

<script setup>
import { computed, ref, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    modelValue: [Number, String],
    placeholder: { type: String, default: '' },
    error: { type: Boolean, default: false },
    suffix: { type: String, default: 'تومان' },
    hint: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // md | sm
})
const emit = defineEmits(['update:modelValue'])

const attrs = useAttrs()
const attrsClass = computed(() => attrs.class)
const attrsStyle = computed(() => attrs.style)
const restAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs
    return rest
})

const inputRef = ref(null)
const focused = ref(false)

const displayValue = computed(() => formatMoney(props.modelValue))
const hasValue = computed(() => props.modelValue === 0 || !!props.modelValue)

/* ---- منطق دست‌نخورده ---- */
function formatMoney(v) {
    return (v || v === 0) ? Number(v).toLocaleString('en-US') : ''
}

function onInput(e) {
    const raw = e.target.value.replace(/[^0-9]/g, '')
    const num = raw ? Number(raw) : ''
    emit('update:modelValue', num)
    e.target.value = formatMoney(num)
}
/* ------------------------- */

/** چیپ مقیاس: ۱٬۲۵۰٬۰۰۰ → «۱٫۲۵ میلیون» */
const scaleLabel = computed(() => {
    const n = Number(props.modelValue)
    if (!n || Number.isNaN(n) || Math.abs(n) < 1000) return ''
    const units = [
        { v: 1_000_000_000, l: 'میلیارد' },
        { v: 1_000_000, l: 'میلیون' },
        { v: 1_000, l: 'هزار' },
    ]
    const u = units.find(x => Math.abs(n) >= x.v)
    const val = n / u.v
    const txt = (Math.round(val * 100) / 100).toLocaleString('fa-IR')
    return `${txt} ${u.l}`
})

defineExpose({ focus: () => inputRef.value?.focus() })
</script>

<style scoped>
.mny {
    --mny-h: 44px;
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    min-width: 150px;
    height: var(--mny-h);
    padding: 0 0.85rem;
    border-radius: 13px;
    border: 1px solid var(--gs-border);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 60%),
        var(--gs-glass);
    backdrop-filter: blur(10px) saturate(1.2);
    -webkit-backdrop-filter: blur(10px) saturate(1.2);
    cursor: text;
    overflow: hidden;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        box-shadow 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        transform 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.mny--sm {
    --mny-h: 36px;
    border-radius: 11px;
    padding: 0 0.65rem;
}

.mny:hover {
    border-color: var(--gs-border-hover);
    background: var(--gs-glass-hover);
}

.mny.is-focus {
    border-color: var(--gs-border-strong);
    background: var(--gs-glass-hover);
    box-shadow: 0 0 0 4px var(--gs-gold-muted), var(--gs-shadow-gold);
    transform: translateY(-1px);
}

.mny.is-error {
    border-color: var(--gs-error);
    box-shadow: 0 0 0 4px var(--gs-error-soft);
    animation: mny-shake 0.4s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.mny.is-disabled {
    opacity: 0.5;
    pointer-events: none;
    filter: grayscale(0.35);
}

/* هالهٔ طلایی که با فوکوس از راست می‌درخشد */
.mny__glow {
    position: absolute;
    inset: -40% -10%;
    background: radial-gradient(60% 120% at 88% 50%, var(--gs-gold-glow), transparent 70%);
    opacity: 0;
    transition: opacity 0.45s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    pointer-events: none;
}

.mny.is-focus .mny__glow { opacity: 0.55; }

.mny__icon {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    flex: none;
    color: var(--gs-text-muted);
    transition: color 0.3s ease, transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.mny__icon svg { width: 100%; height: 100%; }

.mny:hover .mny__icon,
.mny.is-filled .mny__icon { color: var(--gs-gold); }

.mny.is-focus .mny__icon {
    color: var(--gs-gold-light);
    transform: scale(1.12) rotate(-6deg);
}

.mny__input {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
    height: 100%;
    border: 0;
    outline: none;
    background: transparent;
    color: var(--gs-text-primary);
    font: inherit;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.04em;
    font-variant-numeric: tabular-nums;
    text-align: start;
}

.mny--sm .mny__input { font-size: 0.85rem; }

.mny__input::placeholder {
    font-weight: 500;
    letter-spacing: 0;
    color: var(--gs-text-muted);
}

.mny__scale {
    position: relative;
    z-index: 1;
    flex: none;
    padding: 0.14rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    font-size: 0.66rem;
    font-weight: 700;
    white-space: nowrap;
    user-select: none;
}

.mny--sm .mny__scale { display: none; }

.mny__suffix {
    position: relative;
    z-index: 1;
    flex: none;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--gs-gold);
    opacity: 0.75;
    user-select: none;
}

/* نوار طلایی پایین که با فوکوس باز می‌شود */
.mny__bar {
    position: absolute;
    bottom: 0;
    inset-inline: 12%;
    height: 2px;
    border-radius: 2px;
    background: var(--gs-gold-grad);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.42s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.mny.is-focus .mny__bar { transform: scaleX(1); }
.mny.is-error .mny__bar { background: var(--gs-error); transform: scaleX(1); }

.mny-pop-enter-active { transition: all 0.3s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)); }
.mny-pop-leave-active { transition: all 0.18s ease; }
.mny-pop-enter-from,
.mny-pop-leave-to { opacity: 0; transform: translateY(4px) scale(0.85); }

@keyframes mny-shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}

@media (prefers-reduced-motion: reduce) {
    .mny, .mny * { transition-duration: 0.01ms !important; animation: none !important; }
}
</style>
