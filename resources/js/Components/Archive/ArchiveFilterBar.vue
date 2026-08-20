<!-- ==========================================================================
     GameStore · ArchiveFilterBar
     --------------------------------------------------------------------------
     مسیر فایل: resources/js/Components/Archive/ArchiveFilterBar.vue

     نوار فرماندهٔ بایگانی:
       • تب‌های سه‌بعدی نوع سند با نشانگر لغزان (sliding indicator)
       • جستجوی زندهٔ debounce شده
       • فیلتر وضعیت و بازهٔ تاریخ
       • منوی خروجی اکسل برای هر بخش به‌صورت جداگانه
       • دکمهٔ همگام‌سازی موارد پرداخت‌شده
     ========================================================================== -->
<template>
  <section v-reveal="{ delay: 80 }" class="a3d-holo filter-bar">
    <!-- ردیف ۱ : تب‌های نوع سند -->
    <div class="filter-row">
      <div class="segment" ref="segmentRef">
        <span class="segment-indicator" :style="indicatorStyle"></span>
        <button
          v-for="(tab, index) in tabs"
          :key="tab.value"
          ref="tabRefs"
          type="button"
          class="segment-btn"
          :class="{ 'is-active': modelValue.source_type === tab.value }"
          @click="selectType(tab.value, index)"
        >
          <span class="segment-icon">{{ tab.icon }}</span>
          <span>{{ tab.label }}</span>
          <span v-if="counts[tab.value] !== undefined" class="segment-count">
            {{ counts[tab.value] }}
          </span>
        </button>
      </div>

      <div class="filter-actions">
        <button
          type="button"
          class="a3d-btn a3d-btn--sm"
          :disabled="busy === 'sync'"
          title="کپی/به‌روزرسانی همهٔ موارد پرداخت‌شده در بایگانی"
          @click="$emit('sync')"
        >
          <span :class="{ 'is-spinning': busy === 'sync' }">⟳</span>
          همگام‌سازی پرداخت‌شده‌ها
        </button>

        <!-- منوی خروجی اکسل -->
        <div class="export-wrap" ref="exportWrapRef">
          <button
            ref="exportBtnRef"
            type="button"
            class="a3d-btn a3d-btn--gold a3d-btn--sm"
            @click="toggleExportMenu"
          >
            <span>⬇</span>
            خروجی اکسل
            <span class="caret" :class="{ 'is-open': exportOpen }">▾</span>
          </button>

          <Teleport to="body">
            <Transition name="pop3d">
              <div
                v-if="exportOpen"
                ref="exportMenuRef"
                class="export-menu"
                :style="exportMenuStyle"
              >
                <p class="export-title">خروجی با اعمال فیلترهای فعلی</p>
                <button type="button" class="export-item" @click="doExport('all')">
                  <span>🗂</span> فایل کامل (سه شیت جدا)
                </button>
                <button type="button" class="export-item" @click="doExport('invoice')">
                  <span>🧾</span> فقط فاکتورها
                </button>
                <button type="button" class="export-item" @click="doExport('request')">
                  <span>📋</span> فقط درخواست‌ها
                </button>
                <button type="button" class="export-item" @click="doExport('service_job')">
                  <span>🔧</span> فقط سرویس‌ها
                </button>
              </div>
            </Transition>
          </Teleport>
        </div>
      </div>
    </div>

    <!-- ردیف ۲ : جستجو و فیلترها -->
    <div class="filter-row filter-row--inputs">
      <label class="field field--grow">
        <span class="field-icon">🔎</span>
        <input
          :value="modelValue.search"
          type="search"
          class="field-input"
          placeholder="جستجوی شمارهٔ فاکتور، نام مشتری یا عنوان…"
          @input="onSearch($event.target.value)"
        />
      </label>

      <label class="field">
        <span class="field-label">وضعیت</span>
        <select
          :value="modelValue.archive_status"
          class="field-input field-input--select"
          @change="update('archive_status', $event.target.value)"
        >
          <option value="">همه</option>
          <option value="copied">کپی‌شده (در بخش اصلی فعال است)</option>
          <option value="transferred">منتقل‌شده (از بخش اصلی حذف شده)</option>
        </select>
      </label>

      <label class="field">
        <span class="field-label">از تاریخ</span>
        <JalaliDateInput
          :model-value="modelValue.from"
          placeholder="از تاریخ"
          @update:model-value="update('from', $event)"
        />
      </label>

      <label class="field">
        <span class="field-label">تا تاریخ</span>
        <JalaliDateInput
          :model-value="modelValue.to"
          placeholder="تا تاریخ"
          @update:model-value="update('to', $event)"
        />
      </label>

      <button
        v-if="hasFilters"
        type="button"
        class="a3d-btn a3d-btn--ghost a3d-btn--sm"
        @click="$emit('reset')"
      >
        پاک‌سازی
      </button>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { vReveal } from '@/Composables/useTilt'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
const props = defineProps({
  modelValue: { type: Object, required: true },
  counts: { type: Object, default: () => ({}) },
  busy: { type: [String, null], default: null },
})

const emit = defineEmits(['update:modelValue', 'apply', 'reset', 'export', 'sync'])

const tabs = [
  { value: '', label: 'همه', icon: '✦' },
  { value: 'invoice', label: 'فاکتورها', icon: '🧾' },
  { value: 'request', label: 'درخواست‌ها', icon: '📋' },
  { value: 'service_job', label: 'سرویس‌ها', icon: '🔧' },
]

const exportOpen = ref(false)
const exportWrapRef = ref(null)
const exportBtnRef = ref(null)
const exportMenuRef = ref(null)
const exportMenuStyle = ref({})
const segmentRef = ref(null)
const tabRefs = ref([])
const indicatorStyle = ref({ width: '0px', transform: 'translateX(0px)' })

const hasFilters = computed(() =>
  Boolean(
    props.modelValue.search ||
    props.modelValue.archive_status ||
    props.modelValue.from ||
    props.modelValue.to ||
    props.modelValue.source_type
  )
)

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
  emit('apply')
}

let searchTimer = null
function onSearch(value) {
  emit('update:modelValue', { ...props.modelValue, search: value })
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => emit('apply'), 400)
}

function selectType(value, index) {
  emit('update:modelValue', { ...props.modelValue, source_type: value })
  emit('apply')
  nextTick(() => moveIndicator(index))
}

function doExport(scope) {
  exportOpen.value = false
  emit('export', scope)
}

function toggleExportMenu() {
  if (exportOpen.value) {
    exportOpen.value = false
    return
  }
  exportOpen.value = true
  nextTick(positionExportMenu)
}

function positionExportMenu() {
  const btn = exportBtnRef.value
  if (!btn) return
  const rect = btn.getBoundingClientRect()
  const menuWidth = 246
  // اگر منو از سمت چپ صفحه بیرون بزنه، از راست دکمه بچینش
  let left = rect.left
  if (left + menuWidth > window.innerWidth - 8) {
    left = rect.right - menuWidth
  }

  exportMenuStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 10}px`,
    left: `${Math.max(left, 8)}px`,
    minWidth: `${menuWidth}px`,
  }
}

function handleExportDocClick(event) {
  if (!exportOpen.value) return
  const wrap = exportWrapRef.value
  const menu = exportMenuRef.value
  if (wrap?.contains(event.target) || menu?.contains(event.target)) return
  exportOpen.value = false
}

/* نشانگر لغزان زیر تب فعال */
function moveIndicator(index) {
  const el = tabRefs.value?.[index]
  const container = segmentRef.value
  if (!el || !container) return

  const elRect = el.getBoundingClientRect()
  const boxRect = container.getBoundingClientRect()
  // در حالت RTL از سمت راست محاسبه می‌کنیم
  const offset = boxRect.right - elRect.right

  indicatorStyle.value = {
    width: `${elRect.width}px`,
    transform: `translateX(${-offset}px)`,
  }
}

function syncIndicator() {
  const index = tabs.findIndex((t) => t.value === props.modelValue.source_type)
  moveIndicator(index < 0 ? 0 : index)
}

onMounted(() => {
  nextTick(syncIndicator)
  document.addEventListener('click', handleExportDocClick)
  window.addEventListener('resize', positionExportMenu)
  window.addEventListener('scroll', positionExportMenu, true)
})
watch(() => props.modelValue.source_type, () => nextTick(syncIndicator))

onUnmounted(() => {
  document.removeEventListener('click', handleExportDocClick)
  window.removeEventListener('resize', positionExportMenu)
  window.removeEventListener('scroll', positionExportMenu, true)
})
</script>

<style scoped>
.filter-bar {
  position: relative;
  z-index: 20;
  padding: 1rem 1.1rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.filter-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.85rem;
  flex-wrap: wrap;
}

.filter-row--inputs { gap: 0.65rem; }

/* ---------- تب‌های سگمنتی ---------- */
.segment {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.15rem;
  padding: 0.28rem;
  border-radius: 14px;
  background: color-mix(in srgb, var(--gs-bg) 55%, transparent);
  border: 1px solid var(--gs-border-soft);
  perspective: 700px;
}

.segment-indicator {
  position: absolute;
  top: 0.28rem;
  right: 0.28rem;
  bottom: 0.28rem;
  border-radius: 11px;
  background: var(--gs-gold-muted);
  border: 1px solid var(--gs-border-hover);
  box-shadow: 0 6px 18px color-mix(in srgb, var(--gs-gold) 18%, transparent);
  transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
  pointer-events: none;
}

.segment-btn {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.46rem 0.85rem;
  border: none;
  background: none;
  border-radius: 11px;
  font-family: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--gs-text-secondary);
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.segment-btn:hover { color: var(--gs-text-primary); transform: translateZ(10px); }
.segment-btn.is-active { color: var(--gs-gold); }

.segment-icon { font-size: 0.9rem; }

.segment-count {
  min-width: 20px;
  padding: 0 0.32rem;
  border-radius: 99px;
  background: color-mix(in srgb, var(--gs-gold) 16%, transparent);
  font-size: 0.66rem;
  font-weight: 800;
  color: var(--gs-gold);
  font-variant-numeric: tabular-nums;
}

/* ---------- اکشن‌ها ---------- */
.filter-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.is-spinning { display: inline-block; animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.caret { font-size: 0.65rem; transition: transform 0.3s ease; }
.caret.is-open { transform: rotate(180deg); }

/* ---------- منوی خروجی ---------- */
.export-wrap { position: relative; }

.export-menu {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  min-width: 246px;
  padding: 0.45rem;
  border-radius: 14px;
  background: var(--gs-bg-card-strong);
  border: 1px solid var(--gs-border-hover);
  box-shadow: var(--gs-shadow-md);
  z-index: 60;
  transform-origin: top left;
}

.export-title {
  padding: 0.4rem 0.6rem 0.5rem;
  font-size: 0.68rem;
  color: var(--gs-text-muted);
  border-bottom: 1px solid var(--gs-border-soft);
  margin-bottom: 0.3rem;
}

.export-item {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  width: 100%;
  padding: 0.55rem 0.6rem;
  border: none;
  background: none;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--gs-text-primary);
  text-align: right;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease, color 0.2s ease;
}

.export-item:hover {
  background: var(--gs-gold-muted);
  color: var(--gs-gold);
  transform: translateX(-4px);
}

/* ---------- فیلدها ---------- */
.field {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.65rem;
  border-radius: 12px;
  background: color-mix(in srgb, var(--gs-bg) 55%, transparent);
  border: 1px solid var(--gs-border-soft);
  transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
}

.field:focus-within {
  border-color: var(--gs-border-hover);
  box-shadow: 0 0 0 3px var(--gs-gold-muted);
  transform: translateY(-1px);
}

.field--grow { flex: 1 1 260px; min-width: 200px; }

.field-icon { font-size: 0.82rem; opacity: 0.75; }

.field-label {
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--gs-text-muted);
  white-space: nowrap;
}

.field-input {
  flex: 1;
  min-width: 0;
  padding: 0.28rem 0;
  border: none;
  outline: none;
  background: transparent;
  font-family: inherit;
  font-size: 0.78rem;
  color: var(--gs-text-primary);
  color-scheme: dark;
}

.field-input--select { cursor: pointer; }
.field-input option { background: var(--gs-bg-card-strong); color: var(--gs-text-primary); }

/* ---------- ترنزیشن منو ---------- */
.pop3d-enter-active { transition: opacity 0.24s ease, transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pop3d-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.pop3d-enter-from,
.pop3d-leave-to {
  opacity: 0;
  transform: perspective(700px) rotateX(-14deg) translateY(-8px) scale(0.96);
}

@media (max-width: 820px) {
  .segment { width: 100%; overflow-x: auto; }
  .filter-actions { width: 100%; }
}
</style>
