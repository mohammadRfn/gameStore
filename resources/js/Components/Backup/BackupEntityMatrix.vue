<script setup>
import { computed } from 'vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import { faNumber } from '@/Composables/useBackupCenter'

/**
 * انتخاب بخش‌های اطلاعات — نسخه‌ی هماهنگ با تم (dark/light).
 * منطق کاملاً مثل قبل؛ فقط رنگ‌های ثابت با توکن --gs-* جایگزین شد.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  entities: { type: Array, default: () => [] },
  groups: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const usable = computed(() => props.entities.filter((entity) => entity.available !== false))

const grouped = computed(() => {
  const map = new Map()

  usable.value.forEach((entity) => {
    const key = entity.group || 'other'
    const title = entity.group_label || props.groups?.[key] || 'سایر بخش‌ها'
    if (!map.has(key)) map.set(key, { key, title, items: [] })
    map.get(key).items.push(entity)
  })

  return Array.from(map.values())
})

const selectedCount = computed(() => props.modelValue.length)
const allSelected = computed(() => selectedCount.value > 0 && selectedCount.value === usable.value.length)

function isSelected(key) {
  return props.modelValue.includes(key)
}

function toggle(key) {
  const next = isSelected(key)
    ? props.modelValue.filter((item) => item !== key)
    : [...props.modelValue, key]

  emit('update:modelValue', next)
}

function selectAll() {
  emit('update:modelValue', usable.value.map((entity) => entity.key))
}

function clearAll() {
  emit('update:modelValue', [])
}

function toggleGroup(group) {
  const keys = group.items.map((item) => item.key)
  const everySelected = keys.every((key) => isSelected(key))

  emit(
    'update:modelValue',
    everySelected
      ? props.modelValue.filter((key) => !keys.includes(key))
      : Array.from(new Set([...props.modelValue, ...keys])),
  )
}
</script>

<template>
  <BackupCard
    title="بخش‌های اطلاعات"
    description="مشخص کن کدام بخش‌های فروشگاه در بسته‌ی پشتیبان قرار بگیرند. اگر چیزی انتخاب نشود، همه‌ی بخش‌ها گرفته می‌شود."
    icon="🗂"
  >
    <template #actions>
      <span class="bk-chip">{{ faNumber(selectedCount) }} از {{ faNumber(usable.length) }}</span>
      <button type="button" class="bk-btn-soft" @click="allSelected ? clearAll() : selectAll()">
        {{ allSelected ? 'پاک کردن انتخاب‌ها' : 'انتخاب همه' }}
      </button>
    </template>

    <div v-for="group in grouped" :key="group.key" class="bk-group">
      <div class="bk-group__head">
        <h4 class="bk-group__title">{{ group.title }}</h4>
        <button type="button" class="bk-group__link" @click="toggleGroup(group)">
          انتخاب این گروه
        </button>
      </div>

      <div class="bk-ent-grid">
        <button
          v-for="entity in group.items"
          :key="entity.key"
          type="button"
          class="bk-ent"
          :class="{ 'is-selected': isSelected(entity.key) }"
          :aria-pressed="isSelected(entity.key)"
          @click="toggle(entity.key)"
        >
          <span class="bk-ent__check" :class="{ 'is-selected': isSelected(entity.key) }" aria-hidden="true">✓</span>

          <span class="bk-ent__body">
            <span class="bk-ent__label">{{ entity.label }}</span>
            <span class="bk-ent__meta">{{ faNumber(entity.rows) }} مورد</span>
          </span>

          <span v-if="entity.has_media" class="bk-ent__badge">تصویر دارد</span>
        </button>
      </div>
    </div>

    <p v-if="!grouped.length" class="bk-empty">
      فهرست بخش‌ها هنوز بارگذاری نشده است.
    </p>
  </BackupCard>
</template>

<style scoped>
.bk-chip {
  border-radius: 999px;
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  border: 1px solid var(--gs-border-soft);
  padding: .25rem .625rem;
  font-size: 11px;
  line-height: 1rem;
  color: var(--gs-text-secondary);
}

.bk-btn-soft {
  height: 2rem;
  border-radius: .5rem;
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
  border: 1px solid var(--gs-border-soft);
  padding: 0 .75rem;
  font-size: .75rem;
  font-weight: 600;
  color: var(--gs-text-primary);
  transition: background-color .2s ease, border-color .2s ease;
}
.bk-btn-soft:hover {
  background: color-mix(in srgb, var(--gs-text-primary) 11%, transparent);
  border-color: var(--gs-border);
}

.bk-group {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}
.bk-group__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
}
.bk-group__title {
  font-size: .75rem;
  font-weight: 700;
  color: var(--gs-text-secondary);
}
.bk-group__link {
  font-size: 11px;
  font-weight: 500;
  color: var(--gs-gold);
  transition: color .2s ease;
}
.bk-group__link:hover { color: var(--gs-gold-light); }

.bk-ent-grid {
  display: grid;
  gap: .75rem;
  grid-template-columns: 1fr;
}
@media (min-width: 640px) { .bk-ent-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1280px) { .bk-ent-grid { grid-template-columns: repeat(3, 1fr); } }

.bk-ent {
  display: flex;
  align-items: center;
  gap: .75rem;
  border-radius: .75rem;
  padding: .75rem 1rem;
  text-align: right;
  border: 1px solid var(--gs-border-soft);
  background: color-mix(in srgb, var(--gs-text-primary) 3%, transparent);
  transition: background-color .2s ease, border-color .2s ease;
}
.bk-ent:hover {
  background: color-mix(in srgb, var(--gs-text-primary) 6%, transparent);
}
.bk-ent.is-selected {
  background: var(--gs-gold-muted);
  border-color: var(--gs-border-hover);
}

.bk-ent__check {
  display: grid;
  place-items: center;
  width: 1.25rem;
  height: 1.25rem;
  flex-shrink: 0;
  border-radius: .375rem;
  font-size: 11px;
  line-height: 1;
  color: transparent;
  background: color-mix(in srgb, var(--gs-text-primary) 10%, transparent);
  transition: background-color .2s ease, color .2s ease;
}
.bk-ent__check.is-selected {
  background: var(--gs-gold);
  color: var(--gs-bg);
}

.bk-ent__body { min-width: 0; flex: 1; }
.bk-ent__label {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.25rem;
  color: var(--gs-text-primary);
}
.bk-ent__meta {
  margin-top: .125rem;
  display: block;
  font-size: 11px;
  line-height: 1rem;
  color: var(--gs-text-muted);
}
.bk-ent__badge {
  flex-shrink: 0;
  border-radius: 999px;
  background: color-mix(in srgb, var(--gs-info) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--gs-info) 22%, transparent);
  padding: .125rem .5rem;
  font-size: 10px;
  line-height: 1rem;
  color: var(--gs-info);
}

.bk-empty {
  border-radius: .75rem;
  background: color-mix(in srgb, var(--gs-text-primary) 3%, transparent);
  padding: 1.5rem 1rem;
  text-align: center;
  font-size: .75rem;
  color: var(--gs-text-muted);
}
</style>
