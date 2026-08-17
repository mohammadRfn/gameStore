<template>
    <div ref="wrapperRef" class="gs-jdate">
        <input type="text" class="gs-input" readonly :value="displayValue" :placeholder="placeholder"
            @click="toggleOpen" />
        <Teleport to="body">
            <div v-if="open" class="gs-card gs-jdate-popover" :style="popoverStyle">
                <div class="gs-jdate-header">
                    <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" @click="prevMonth">›</button>
                    <span class="gs-jdate-title">{{ monthNames[viewMonth - 1] }} {{ toFa(viewYear) }}</span>
                    <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" @click="nextMonth">‹</button>
                </div>
                <div class="gs-jdate-grid">
                    <span v-for="d in weekDays" :key="d" class="gs-jdate-weekday">{{ d }}</span>
                    <span v-for="n in leadingBlanks" :key="'b' + n" class="gs-jdate-blank"></span>
                    <button v-for="day in daysInMonth" :key="day" type="button" class="gs-jdate-day"
                        :class="{ active: isSelected(day) }" @click="pickDay(day)">
                        {{ toFa(day) }}
                    </button>
                </div>
                <div class="gs-jdate-footer">
                    <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" @click="pickToday">امروز</button>
                    <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" @click="clear">پاک کردن</button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue'
import { toJalaali, toGregorian, jalaaliMonthLength } from 'jalaali-js'

const props = defineProps({
    modelValue: { type: String, default: null }, // ISO 'YYYY-MM-DD' (gregorian)
    placeholder: { type: String, default: 'انتخاب تاریخ' },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const wrapperRef = ref(null)
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

function toggleOpen() {
    open.value = !open.value
    if (open.value) nextTick(updatePopoverPosition)
}

function updatePopoverPosition() {
    const el = wrapperRef.value
    if (!el) return
    const rect = el.getBoundingClientRect()
    const width = Math.max(rect.width, 260)
    let right = window.innerWidth - rect.right
    if (right + width > window.innerWidth) {
        right = Math.max(8, window.innerWidth - width - 8)
    }
    popoverStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        right: `${right}px`,
        width: `${width}px`,
        zIndex: 1000,
    }
}

function handleReposition() {
    if (open.value) updatePopoverPosition()
}

function handleOutsideClick(e) {
    if (!open.value) return
    const el = wrapperRef.value
    if (el && el.contains(e.target)) return
    open.value = false
}

window.addEventListener('scroll', handleReposition, true)
window.addEventListener('resize', handleReposition)
document.addEventListener('mousedown', handleOutsideClick)

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleReposition, true)
    window.removeEventListener('resize', handleReposition)
    document.removeEventListener('mousedown', handleOutsideClick)
})

function prevMonth() {
    viewMonth.value--
    if (viewMonth.value < 1) { viewMonth.value = 12; viewYear.value-- }
}
function nextMonth() {
    viewMonth.value++
    if (viewMonth.value > 12) { viewMonth.value = 1; viewYear.value++ }
}

function isSelected(day) {
    return selectedJalali.value &&
        selectedJalali.value.jy === viewYear.value &&
        selectedJalali.value.jm === viewMonth.value &&
        selectedJalali.value.jd === day
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
    pickDay(todayJalali.jd)
}

function clear() {
    selectedJalali.value = null
    emit('update:modelValue', null)
    open.value = false
}
</script>

<style scoped>
.gs-jdate {
    position: relative;
}

.gs-jdate-popover {
    padding: .75rem;
}

.gs-jdate-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .5rem;
    font-size: .8rem;
    font-weight: 600;
}

.gs-jdate-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    text-align: center;
}

.gs-jdate-weekday {
    font-size: .7rem;
    color: var(--gs-text-muted);
    padding: .25rem 0;
}

.gs-jdate-day {
    border: none;
    background: transparent;
    color: inherit;
    font-family: inherit;
    font-size: .75rem;
    padding: .4rem 0;
    border-radius: 6px;
    cursor: pointer;
}

.gs-jdate-day:hover {
    background: rgba(128, 128, 128, .15);
}

.gs-jdate-day.active {
    background: var(--gs-gold, #c9a44c);
    color: #1a1a1a;
    font-weight: 700;
}

.gs-jdate-footer {
    display: flex;
    justify-content: space-between;
    margin-top: .5rem;
    padding-top: .5rem;
    border-top: 1px solid var(--gs-border);
}
</style>