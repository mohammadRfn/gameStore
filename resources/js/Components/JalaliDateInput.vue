<!--
  resources/js/Components/JalaliDateInput.vue
  ------------------------------------------------------------------
  انتخابگر تاریخ شمسی — بازطراحی کامل (تریگر لوکس + پاپ‌اور شیشه‌ای،
  ناوبری ماه/سال، هایلایت «امروز»، رنگ جمعه، انیمیشن ورود خانه‌ها).

  ⚠️ قرارداد قبلی ۱۰۰٪ حفظ شده:
     v-model → رشتهٔ ISO میلادی 'YYYY-MM-DD' (یا null)
     props   : modelValue | placeholder
     وابستگی : jalaali-js (toJalaali / toGregorian / jalaaliMonthLength)
     پاپ‌اور همچنان Teleport به body با موقعیت‌دهی fixed است.

  props اختیاری اضافه‌شده: disabled | clearable | size ('md' | 'sm')
-->
<template>
    <div ref="wrapperRef" class="jdi" :class="[`jdi--${size}`, { 'is-open': open, 'is-disabled': disabled }]">
        <button
            type="button"
            class="jdi__trigger"
            :disabled="disabled"
            @click="toggleOpen"
            @keydown.down.prevent="openPicker"
        >
            <span class="jdi__glow" aria-hidden="true"></span>

            <span class="jdi__cal" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4.5" width="18" height="16" rx="3.5" />
                    <path d="M3 9.5h18M8 2.5v4M16 2.5v4" />
                </svg>
            </span>

            <span class="jdi__value" :class="{ 'is-placeholder': !displayValue }">
                {{ displayValue || placeholder }}
            </span>

            <span
                v-if="clearable && displayValue && !disabled"
                class="jdi__clear"
                role="button"
                tabindex="-1"
                title="پاک کردن"
                @click.stop="clear"
            >✕</span>

            <span v-if="!(clearable && displayValue && !disabled)" class="jdi__chev" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </span>

            <span class="jdi__bar" aria-hidden="true"></span>
        </button>

        <Teleport to="body">
            <Transition name="jdi-drop">
                <div v-if="open" ref="popoverRef" class="jdi-pop" :style="popoverStyle">
                    <!-- سربرگ -->
                    <div class="jdi-pop__head">
                        <button type="button" class="jdi-pop__nav" title="ماه بعد" @click="nextMonth">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                                 stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6" /></svg>
                        </button>

                        <button type="button" class="jdi-pop__title" @click="yearPicker = !yearPicker">
                            <span>{{ monthNames[viewMonth - 1] }}</span>
                            <b>{{ toFa(viewYear) }}</b>
                            <i class="jdi-pop__title-chev" :class="{ 'is-up': yearPicker }">▾</i>
                        </button>

                        <button type="button" class="jdi-pop__nav" title="ماه قبل" @click="prevMonth">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                                 stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6" /></svg>
                        </button>
                    </div>

                    <!-- انتخاب سال/ماه -->
                    <Transition name="jdi-fade">
                        <div v-if="yearPicker" class="jdi-pop__picker">
                            <div class="jdi-pop__years">
                                <button
                                    v-for="y in yearRange"
                                    :key="y"
                                    type="button"
                                    class="jdi-pop__year"
                                    :class="{ 'is-active': y === viewYear }"
                                    @click="viewYear = y"
                                >{{ toFa(y) }}</button>
                            </div>
                            <div class="jdi-pop__months">
                                <button
                                    v-for="(m, idx) in monthNames"
                                    :key="m"
                                    type="button"
                                    class="jdi-pop__month"
                                    :class="{ 'is-active': idx + 1 === viewMonth }"
                                    @click="selectMonth(idx + 1)"
                                >{{ m }}</button>
                            </div>
                        </div>
                    </Transition>

                    <!-- شبکهٔ روزها -->
                    <div v-if="!yearPicker" :key="`${viewYear}-${viewMonth}`" class="jdi-pop__grid">
                        <span
                            v-for="(d, i) in weekDays"
                            :key="d"
                            class="jdi-pop__wd"
                            :class="{ 'is-fri': i === 6 }"
                        >{{ d }}</span>

                        <span v-for="n in leadingBlanks" :key="'b' + n" class="jdi-pop__blank"></span>

                        <button
                            v-for="day in daysInMonth"
                            :key="day"
                            type="button"
                            class="jdi-pop__day"
                            :class="{
                                'is-active': isSelected(day),
                                'is-today': isToday(day),
                                'is-fri': (leadingBlanks + day - 1) % 7 === 6,
                            }"
                            :style="{ '--i': day }"
                            @click="pickDay(day)"
                        >
                            <span>{{ toFa(day) }}</span>
                        </button>
                    </div>

                    <!-- فوتر -->
                    <div class="jdi-pop__foot">
                        <button type="button" class="jdi-pop__act jdi-pop__act--gold" @click="pickToday">امروز</button>
                        <span class="jdi-pop__sel">{{ displayValue || 'تاریخی انتخاب نشده' }}</span>
                        <button type="button" class="jdi-pop__act" @click="clear">پاک کردن</button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { toJalaali, toGregorian, jalaaliMonthLength } from 'jalaali-js'

const props = defineProps({
    modelValue: { type: String, default: null }, // ISO 'YYYY-MM-DD' (gregorian)
    placeholder: { type: String, default: 'انتخاب تاریخ' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    size: { type: String, default: 'md' }, // md | sm
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const yearPicker = ref(false)
const wrapperRef = ref(null)
const popoverRef = ref(null)
const popoverStyle = ref({})
const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
const weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']

function toFa(n) {
    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']
    return String(n).replace(/\d/g, d => fa[d])
}

function gregorianToJalali(dateStr) {
    if (!dateStr) return null
    const [gy, gm, gd] = dateStr.split('-').map(Number)
    return toJalaali(gy, gm, gd)
}

const today = new Date()
const todayJalali = toJalaali(today.getFullYear(), today.getMonth() + 1, today.getDate())

const selectedJalali = ref(gregorianToJalali(props.modelValue))
const viewYear = ref(selectedJalali.value?.jy ?? todayJalali.jy)
const viewMonth = ref(selectedJalali.value?.jm ?? todayJalali.jm)

watch(() => props.modelValue, (v) => {
    selectedJalali.value = gregorianToJalali(v)
    if (selectedJalali.value) {
        viewYear.value = selectedJalali.value.jy
        viewMonth.value = selectedJalali.value.jm
    }
})

const displayValue = computed(() => {
    if (!selectedJalali.value) return ''
    const { jy, jm, jd } = selectedJalali.value
    return `${toFa(jy)}/${toFa(String(jm).padStart(2, '0'))}/${toFa(String(jd).padStart(2, '0'))}`
})

const daysInMonth = computed(() => jalaaliMonthLength(viewYear.value, viewMonth.value))

const leadingBlanks = computed(() => {
    const { gy, gm, gd } = toGregorian(viewYear.value, viewMonth.value, 1)
    const jsDay = new Date(gy, gm - 1, gd).getDay() // 0 = Sunday
    return (jsDay + 1) % 7 // shift so Saturday = 0
})

const yearRange = computed(() => {
    const base = todayJalali.jy
    const sel = selectedJalali.value?.jy ?? base
    const arr = []
    for (let y = Math.min(base - 40, sel); y <= Math.max(base + 10, sel); y++) arr.push(y)
    return arr
})

// وقتی انتخاب سال باز شد، سال فعال وسط لیست بیاد
watch(yearPicker, async (v) => {
    await nextTick()
    updatePopoverPosition()
    if (!v) return
    const box = popoverRef.value?.querySelector('.jdi-pop__years')
    const el = box?.querySelector('.is-active')
    if (box && el) box.scrollTop = el.offsetTop - box.clientHeight / 2 + el.clientHeight / 2
})

function toggleOpen() {
    if (props.disabled) return
    open.value = !open.value
    yearPicker.value = false
    if (open.value) nextTick(updatePopoverPosition)
}

function openPicker() {
    if (props.disabled || open.value) return
    open.value = true
    nextTick(updatePopoverPosition)
}

/* ── موقعیت‌دهی پاپ‌اور: منطق اصلی حفظ شده ─────────────── */
function updatePopoverPosition() {
    const el = wrapperRef.value
    if (!el) return
    const rect = el.getBoundingClientRect()
    // عرض تقویم نباید از عرض فیلد تبعیت کند؛ وگرنه در فیلدهای تمام‌عرض، تقویم تمام‌صفحه می‌شود
    const width = Math.min(Math.max(rect.width, 288), 320, window.innerWidth - 16)
    let right = window.innerWidth - rect.right
    if (right + width > window.innerWidth) {
        right = Math.max(8, window.innerWidth - width - 8)
    }
    right = Math.max(8, right)

    // ارتفاع واقعی پاپ‌اور (اگر هنوز رندر نشده، تخمین)
    const popH = popoverRef.value?.offsetHeight || 370
    const gap = 6
    const margin = 8

    // اگر فضای پایین کم بود و بالا بیشتر بود، بالای فیلد باز شود
    const spaceBelow = window.innerHeight - rect.bottom - gap - margin
    const spaceAbove = rect.top - gap - margin
    const openUp = spaceBelow < popH && spaceAbove > spaceBelow

    let top = openUp ? rect.top - popH - gap : rect.bottom + gap
    // همیشه داخل صفحه بماند
    top = Math.max(margin, Math.min(top, window.innerHeight - popH - margin))

    popoverStyle.value = {
        position: 'fixed',
        top: `${top}px`,
        right: `${right}px`,
        width: `${width}px`,
        zIndex: 1000,
    }
}

function handleReposition(e) {
    if (!open.value) return
    // اسکرول داخل خود پاپ‌اور (مثلاً لیست سال‌ها) نباید موقعیت را تغییر دهد
    if (e?.target instanceof Node && popoverRef.value?.contains(e.target)) return
    // اگر فیلد کاملاً از دید خارج شد، تقویم بسته شود
    const rect = wrapperRef.value?.getBoundingClientRect()
    if (rect && (rect.bottom < 0 || rect.top > window.innerHeight)) {
        open.value = false
        return
    }
    updatePopoverPosition()
}

function handleOutsideClick(e) {
    if (!open.value) return
    if (wrapperRef.value && wrapperRef.value.contains(e.target)) return
    if (popoverRef.value && popoverRef.value.contains(e.target)) return
    open.value = false
}

function handleEsc(e) {
    if (e.key === 'Escape' && open.value) open.value = false
}

onMounted(() => {
    window.addEventListener('scroll', handleReposition, true)
    window.addEventListener('resize', handleReposition)
    document.addEventListener('mousedown', handleOutsideClick)
    document.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleReposition, true)
    window.removeEventListener('resize', handleReposition)
    document.removeEventListener('mousedown', handleOutsideClick)
    document.removeEventListener('keydown', handleEsc)
})

function prevMonth() {
    viewMonth.value--
    if (viewMonth.value < 1) { viewMonth.value = 12; viewYear.value-- }
}
function nextMonth() {
    viewMonth.value++
    if (viewMonth.value > 12) { viewMonth.value = 1; viewYear.value++ }
}

function selectMonth(m) {
    viewMonth.value = m
    yearPicker.value = false
}

function isSelected(day) {
    return selectedJalali.value &&
        selectedJalali.value.jy === viewYear.value &&
        selectedJalali.value.jm === viewMonth.value &&
        selectedJalali.value.jd === day
}

function isToday(day) {
    return todayJalali.jy === viewYear.value &&
        todayJalali.jm === viewMonth.value &&
        todayJalali.jd === day
}

function pickDay(day) {
    const { gy, gm, gd } = toGregorian(viewYear.value, viewMonth.value, day)
    const iso = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
    selectedJalali.value = { jy: viewYear.value, jm: viewMonth.value, jd: day }
    emit('update:modelValue', iso)
    open.value = false
}

function pickToday() {
    viewYear.value = todayJalali.jy
    viewMonth.value = todayJalali.jm
    yearPicker.value = false
    pickDay(todayJalali.jd)
}

function clear() {
    selectedJalali.value = null
    emit('update:modelValue', null)
    open.value = false
}
</script>

<style scoped>
.jdi { position: relative; width: 100%; min-width: 172px; }

/* ── تریگر ─────────────────────────────────────────────── */
.jdi__trigger {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    width: 100%;
    height: 44px;
    padding: 0 0.7rem;
    border-radius: 13px;
    border: 1px solid var(--gs-border);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 60%),
        var(--gs-glass);
    backdrop-filter: blur(10px) saturate(1.2);
    -webkit-backdrop-filter: blur(10px) saturate(1.2);
    color: var(--gs-text-primary);
    font: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    overflow: hidden;
    text-align: start;
    transition:
        border-color 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        background 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        box-shadow 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)),
        transform 0.28s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.jdi--sm .jdi__trigger { height: 36px; border-radius: 11px; font-size: 0.8rem; padding: 0 0.65rem; }

.jdi__trigger:hover { border-color: var(--gs-border-hover); background: var(--gs-glass-hover); }

.jdi.is-open .jdi__trigger {
    border-color: var(--gs-border-strong);
    box-shadow: 0 0 0 4px var(--gs-gold-muted), var(--gs-shadow-gold);
    transform: translateY(-1px);
}

.jdi.is-disabled { opacity: 0.5; pointer-events: none; filter: grayscale(0.3); }

.jdi__glow {
    position: absolute;
    inset: -40% -10%;
    background: radial-gradient(55% 120% at 90% 50%, var(--gs-gold-glow), transparent 70%);
    opacity: 0;
    transition: opacity 0.45s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
    pointer-events: none;
}

.jdi.is-open .jdi__glow { opacity: 0.5; }

.jdi__cal {
    display: grid;
    place-items: center;
    width: 19px;
    height: 19px;
    flex: none;
    color: var(--gs-text-muted);
    transition: color 0.28s ease, transform 0.45s var(--st-spring, cubic-bezier(0.34, 1.56, 0.64, 1));
}

.jdi__cal svg { width: 100%; height: 100%; }
.jdi__trigger:hover .jdi__cal { color: var(--gs-gold); transform: rotate(-8deg) scale(1.08); }
.jdi.is-open .jdi__cal { color: var(--gs-gold-light); }

.jdi__value {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
    font-weight: 700;
    font-size: 0.95em;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.jdi__value.is-placeholder { font-weight: 500; color: var(--gs-text-muted); letter-spacing: 0; }

.jdi__clear {
    position: relative;
    z-index: 2;
    display: grid;
    place-items: center;
    width: 19px;
    height: 19px;
    flex: none;
    border-radius: 50%;
    background: var(--gs-glass-hover);
    color: var(--gs-text-muted);
    font-size: 0.6rem;
    transition: all 0.25s ease;
}

.jdi__clear:hover { background: var(--gs-error-soft); color: var(--gs-error); transform: rotate(90deg); }

.jdi__chev {
    position: relative;
    z-index: 1;
    display: grid;
    place-items: center;
    width: 15px;
    height: 15px;
    flex: none;
    color: var(--gs-text-muted);
    transition: transform 0.35s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1)), color 0.25s ease;
}

.jdi__chev svg { width: 100%; height: 100%; }
.jdi.is-open .jdi__chev { transform: rotate(180deg); color: var(--gs-gold); }

.jdi__bar {
    position: absolute;
    bottom: 0;
    inset-inline: 12%;
    height: 2px;
    border-radius: 2px;
    background: var(--gs-gold-grad);
    transform: scaleX(0);
    transition: transform 0.42s var(--st-ease, cubic-bezier(0.22, 1, 0.36, 1));
}

.jdi.is-open .jdi__bar { transform: scaleX(1); }
</style>

<!-- پاپ‌اور Teleport می‌شود، پس استایلش باید سراسری باشد -->
<style>
.jdi-pop {
    padding: 0.7rem;
    border-radius: 16px;
    border: 1px solid var(--gs-border-hover);
    background: var(--gs-bg-card-strong);
    backdrop-filter: blur(20px) saturate(1.4);
    -webkit-backdrop-filter: blur(20px) saturate(1.4);
    box-shadow: var(--gs-shadow-md), 0 0 0 1px rgba(255, 255, 255, 0.02) inset;
    direction: rtl;
    font-family: inherit;
    color: var(--gs-text-primary);
}

.jdi-pop__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    margin-bottom: 0.55rem;
}

.jdi-pop__nav {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    flex: none;
    border-radius: 9px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    cursor: pointer;
    transition: all 0.26s cubic-bezier(0.22, 1, 0.36, 1);
}

.jdi-pop__nav svg { width: 14px; height: 14px; }

.jdi-pop__nav:hover {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
    transform: scale(1.08);
}

.jdi-pop__title {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.7rem;
    border-radius: 10px;
    border: 1px solid transparent;
    background: none;
    font: inherit;
    font-size: 0.83rem;
    font-weight: 700;
    color: var(--gs-text-primary);
    cursor: pointer;
    transition: all 0.26s cubic-bezier(0.22, 1, 0.36, 1);
}

.jdi-pop__title b { color: var(--gs-gold); font-variant-numeric: tabular-nums; }

.jdi-pop__title:hover { background: var(--gs-gold-muted); border-color: var(--gs-border); }

.jdi-pop__title-chev {
    font-style: normal;
    font-size: 0.7rem;
    opacity: 0.6;
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

.jdi-pop__title-chev.is-up { transform: rotate(180deg); }

/* انتخاب سال و ماه */
.jdi-pop__picker { display: flex; flex-direction: column; gap: 0.5rem; }

.jdi-pop__years {
    position: relative;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.3rem;
    max-height: 120px;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 2px;
    scrollbar-width: thin;
    scrollbar-color: var(--gs-border-hover) transparent;
}

.jdi-pop__years::-webkit-scrollbar { width: 5px; }
.jdi-pop__years::-webkit-scrollbar-thumb { background: var(--gs-border-hover); border-radius: 4px; }

.jdi-pop__year {
    padding: 0.35rem 0;
    text-align: center;
    border-radius: 9px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font: inherit;
    font-size: 0.74rem;
    font-variant-numeric: tabular-nums;
    cursor: pointer;
    transition: all 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}

.jdi-pop__year:hover { border-color: var(--gs-border-hover); color: var(--gs-text-primary); }

.jdi-pop__year.is-active {
    background: var(--gs-gold-grad);
    border-color: transparent;
    color: #14100a;
    font-weight: 800;
}

.jdi-pop__months {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.3rem;
}

.jdi-pop__month {
    padding: 0.42rem 0.3rem;
    border-radius: 9px;
    border: 1px solid var(--gs-border-soft);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font: inherit;
    font-size: 0.74rem;
    cursor: pointer;
    transition: all 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}

.jdi-pop__month:hover {
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
    background: var(--gs-gold-muted);
}

.jdi-pop__month.is-active {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-strong);
    color: var(--gs-gold);
    font-weight: 700;
}

/* شبکهٔ روزها */
.jdi-pop__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
    text-align: center;
}

.jdi-pop__wd {
    padding: 0.3rem 0;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--gs-text-muted);
}

.jdi-pop__wd.is-fri { color: var(--gs-error); opacity: 0.75; }

.jdi-pop__day {
    position: relative;
    aspect-ratio: 1;
    display: grid;
    place-items: center;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    color: var(--gs-text-secondary);
    font: inherit;
    font-size: 0.76rem;
    font-variant-numeric: tabular-nums;
    cursor: pointer;
    transition:
        background 0.22s cubic-bezier(0.22, 1, 0.36, 1),
        color 0.22s ease,
        border-color 0.22s ease,
        transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    animation: jdi-day-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--i) * 8ms);
}

.jdi-pop__day.is-fri { color: color-mix(in srgb, var(--gs-error) 75%, var(--gs-text-secondary)); }

.jdi-pop__day:hover {
    background: var(--gs-glass-hover);
    border-color: var(--gs-border-hover);
    color: var(--gs-text-primary);
    transform: scale(1.08);
}

.jdi-pop__day.is-today::after {
    content: '';
    position: absolute;
    bottom: 4px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--gs-gold);
}

.jdi-pop__day.is-today {
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
    font-weight: 700;
}

.jdi-pop__day.is-active {
    background: var(--gs-gold-grad);
    border-color: transparent;
    color: #14100a;
    font-weight: 800;
    box-shadow: 0 4px 14px var(--gs-gold-glow);
    transform: scale(1.04);
}

.jdi-pop__day.is-active::after { background: #14100a; }

/* فوتر */
.jdi-pop__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.6rem;
    padding-top: 0.55rem;
    border-top: 1px solid var(--gs-border-soft);
}

.jdi-pop__sel {
    font-size: 0.68rem;
    color: var(--gs-text-muted);
    font-variant-numeric: tabular-nums;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.jdi-pop__act {
    flex: none;
    padding: 0.3rem 0.68rem;
    border-radius: 9px;
    border: 1px solid var(--gs-border);
    background: var(--gs-glass);
    color: var(--gs-text-secondary);
    font: inherit;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

.jdi-pop__act:hover { border-color: var(--gs-border-hover); color: var(--gs-text-primary); transform: translateY(-1px); }

.jdi-pop__act--gold { background: var(--gs-gold-muted); border-color: var(--gs-border-hover); color: var(--gs-gold); }
.jdi-pop__act--gold:hover { box-shadow: 0 4px 14px var(--gs-gold-glow); color: var(--gs-gold-light); }

/* ترنزیشن‌ها */
.jdi-drop-enter-active { transition: opacity 0.22s ease, transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1); }
.jdi-drop-leave-active { transition: opacity 0.16s ease, transform 0.16s ease; }
.jdi-drop-enter-from, .jdi-drop-leave-to { opacity: 0; transform: translateY(-8px) scale(0.96); }

.jdi-fade-enter-active, .jdi-fade-leave-active { transition: all 0.22s cubic-bezier(0.22, 1, 0.36, 1); }
.jdi-fade-enter-from, .jdi-fade-leave-to { opacity: 0; transform: translateY(-5px); }

@keyframes jdi-day-in { from { opacity: 0; transform: scale(0.85); } }

@media (prefers-reduced-motion: reduce) {
    .jdi-pop *, .jdi-pop { animation: none !important; transition-duration: 0.01ms !important; }
}
</style>
