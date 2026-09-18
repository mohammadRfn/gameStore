<!--
  resources/js/Components/JDate.vue
  ------------------------------------------------------------------
  نمایش هر تاریخی به‌صورت شمسی و فارسی — بازطراحی‌شده با زبان بصری تنظیمات.

  ⚠️ قرارداد قبلی حفظ شده:
     props : value | format ('long' | 'short' | 'numeric') | empty
     منبع  : @/Utils/jalali  → faLabel / jalaliFull / jalaliNumeric

  props اختیاری اضافه‌شده:
     variant : 'text' (پیش‌فرض، دقیقاً مثل قبل inline) | 'chip' | 'soft'
     icon    : نمایش آیکون تقویم (پیش‌فرض false در text، true در chip)
     tone    : 'default' | 'gold' | 'success' | 'error' | 'muted'

  استفاده:
     <JDate :value="inv.created_at" />
     <JDate :value="inv.paid_at" format="numeric" variant="chip" tone="gold" />
-->
<template>
    <span
        class="jdate"
        :class="[`jdate--${variant}`, `jdate--${tone}`, { 'is-empty': isEmpty }]"
        :title="titleText"
    >
        <svg
            v-if="showIcon"
            class="jdate__ico"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <rect x="3" y="4.5" width="18" height="16" rx="3.5" />
            <path d="M3 9.5h18M8 2.5v4M16 2.5v4" />
            <circle cx="12" cy="14.5" r="1.4" fill="currentColor" stroke="none" />
        </svg>

        <span class="jdate__txt">{{ text }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { faLabel, jalaliFull, jalaliNumeric } from '@/Utils/jalali'

const props = defineProps({
    value: { type: [String, Number, Date], default: '' },
    /** long | short | numeric */
    format: { type: String, default: 'long' },
    empty: { type: String, default: '—' },
    /** text | chip | soft */
    variant: { type: String, default: 'text' },
    /** default | gold | success | error | muted */
    tone: { type: String, default: 'default' },
    icon: { type: Boolean, default: null },
})

const isEmpty = computed(
    () => props.value === null || props.value === undefined || props.value === '',
)

const text = computed(() => {
    if (isEmpty.value) return props.empty
    if (props.format === 'numeric') return jalaliNumeric(props.value) || faLabel(props.value)
    return faLabel(props.value, { long: props.format !== 'short' })
})

const titleText = computed(() => jalaliFull(props.value) || '')

const showIcon = computed(() =>
    props.icon === null ? props.variant !== 'text' : props.icon,
)
</script>

<style scoped>
.jdate {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    vertical-align: middle;
    transition:
        color 0.25s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.25s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        border-color 0.25s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        transform 0.35s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.jdate__ico {
    width: 0.95em;
    height: 0.95em;
    flex: none;
    opacity: 0.75;
    transition: transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1)), opacity 0.25s ease;
}

.jdate:hover .jdate__ico {
    opacity: 1;
    transform: rotate(-8deg) scale(1.1);
}

.jdate__txt { line-height: 1.6; }

/* ── variant: text (پیش‌فرض، inline و سبک) ───────────────── */
.jdate--text {
    color: inherit;
    border-bottom: 1px dotted transparent;
}

.jdate--text:hover {
    color: var(--gs-gold);
    border-bottom-color: var(--gs-border-hover);
}

/* ── variant: soft ───────────────────────────────────────── */
.jdate--soft {
    padding: 0.14rem 0.5rem;
    border-radius: 8px;
    background: var(--gs-glass);
    border: 1px solid var(--gs-border-soft);
    font-size: 0.78rem;
    color: var(--gs-text-secondary);
}

.jdate--soft:hover {
    background: var(--gs-glass-hover);
    border-color: var(--gs-border-hover);
    color: var(--gs-text-primary);
    transform: translateY(-1px);
}

/* ── variant: chip ───────────────────────────────────────── */
.jdate--chip {
    padding: 0.28rem 0.7rem;
    border-radius: 999px;
    border: 1px solid var(--gs-border);
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    box-shadow: var(--gs-shadow-sm);
}

.jdate--chip:hover {
    border-color: var(--gs-border-hover);
    transform: translateY(-1.5px);
    box-shadow: var(--gs-shadow-gold);
}

/* ── tones ───────────────────────────────────────────────── */
.jdate--gold { color: var(--gs-gold); }

.jdate--success.jdate--chip,
.jdate--success.jdate--soft {
    background: var(--gs-success-soft);
    color: var(--gs-success);
    border-color: color-mix(in srgb, var(--gs-success) 32%, transparent);
}
.jdate--success.jdate--text { color: var(--gs-success); }

.jdate--error.jdate--chip,
.jdate--error.jdate--soft {
    background: var(--gs-error-soft);
    color: var(--gs-error);
    border-color: color-mix(in srgb, var(--gs-error) 32%, transparent);
}
.jdate--error.jdate--text { color: var(--gs-error); }

.jdate--muted { color: var(--gs-text-muted); }

.jdate.is-empty {
    color: var(--gs-text-muted);
    font-weight: 500;
    letter-spacing: 0.1em;
}

@media (prefers-reduced-motion: reduce) {
    .jdate, .jdate * { transition-duration: 0.01ms !important; }
}
</style>
