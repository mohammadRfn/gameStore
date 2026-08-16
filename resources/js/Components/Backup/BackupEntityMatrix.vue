<template>
  <section class="backup-glass backup-section">
    <div class="backup-section__head">
      <div>
        <h3 class="backup-section__title">
          <span>🧭</span>
          انتخاب بخش‌های قابل پشتیبان‌گیری
        </h3>
        <p class="backup-section__desc">
          می‌توانی همه‌ی دیتابیس را بگیری یا فقط جدول‌های موردنیاز را برای خروجی/ورودی انتخاب کنی.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="backup-btn backup-btn--sm backup-btn--gold" @click="selectAll">انتخاب همه</button>
        <button type="button" class="backup-btn backup-btn--sm backup-btn--ghost" @click="clearAll">پاک کردن</button>
      </div>
    </div>

    <div class="relative z-[1] grid gap-3">
      <div v-for="group in groupedEntities" :key="group.key" class="grid gap-2">
        <div class="flex items-center justify-between gap-2">
          <button type="button" class="backup-pill" @click="toggleGroup(group)">
            <span>{{ groupIcon(group.key) }}</span>
            <b>{{ group.label }}</b>
            <span>{{ selectedCount(group.items) }} / {{ group.items.length }}</span>
          </button>
          <span class="text-[0.72rem] text-[var(--gs-text-muted)]">
            {{ totalRows(group.items) }} رکورد
          </span>
        </div>

        <div class="backup-entity-grid">
          <button
            v-for="entity in group.items"
            :key="entity.key"
            type="button"
            class="backup-entity-card text-right"
            :class="{ 'is-active': isSelected(entity.key), 'is-disabled': entity.available === false }"
            :disabled="entity.available === false"
            @click="toggle(entity.key)"
          >
            <span class="backup-entity-card__head">
              <span class="flex items-center gap-2">
                <span class="grid h-9 w-9 place-items-center rounded-2xl bg-[var(--gs-gold-muted)] text-lg">
                  {{ entityIcon(entity) }}
                </span>
                <span>
                  <span class="backup-entity-card__title">{{ entity.label }}</span>
                  <span class="block text-[0.68rem] text-[var(--gs-text-muted)]" dir="ltr">{{ entity.table }}</span>
                </span>
              </span>
              <span class="text-lg">{{ isSelected(entity.key) ? '✓' : '＋' }}</span>
            </span>

            <span class="backup-entity-card__meta">
              <span class="backup-pill">{{ formatRows(entity.rows) }} رکورد</span>
              <span v-if="entity.has_media" class="backup-pill backup-pill--info">تصاویر</span>
              <span v-if="entity.soft_deletes" class="backup-pill">SoftDelete</span>
            </span>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { faNumber } from '@/Composables/useBackupApi'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  entities: { type: Array, default: () => [] },
  groups: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const groupedEntities = computed(() => {
  const map = new Map()
  props.entities.forEach((entity) => {
    const key = entity.group || 'other'
    if (!map.has(key)) {
      map.set(key, { key, label: props.groups?.[key] || entity.group_label || key, items: [] })
    }
    map.get(key).items.push(entity)
  })
  return Array.from(map.values())
})

function isSelected(key) {
  return props.modelValue.includes(key)
}

function toggle(key) {
  const next = new Set(props.modelValue)
  next.has(key) ? next.delete(key) : next.add(key)
  emit('update:modelValue', Array.from(next))
}

function selectAll() {
  emit('update:modelValue', props.entities.filter((entity) => entity.available !== false).map((entity) => entity.key))
}

function clearAll() {
  emit('update:modelValue', [])
}

function toggleGroup(group) {
  const available = group.items.filter((item) => item.available !== false).map((item) => item.key)
  const allSelected = available.every(isSelected)
  const next = new Set(props.modelValue)
  available.forEach((key) => allSelected ? next.delete(key) : next.add(key))
  emit('update:modelValue', Array.from(next))
}

function selectedCount(items) {
  return faNumber(items.filter((item) => isSelected(item.key)).length)
}

function totalRows(items) {
  return faNumber(items.reduce((sum, item) => sum + Number(item.rows || 0), 0))
}

function formatRows(value) {
  return faNumber(value || 0)
}

function groupIcon(key) {
  if (key.includes('core')) return '◈'
  if (key.includes('people')) return '👥'
  if (key.includes('catalog')) return '🎮'
  if (key.includes('sales')) return '🧾'
  if (key.includes('services')) return '🔧'
  if (key.includes('inventory')) return '📦'
  if (key.includes('analytics')) return '📊'
  if (key.includes('archive')) return '🗄'
  return '◇'
}

function entityIcon(entity) {
  if (entity.has_media) return '🖼'
  if (entity.key?.includes('invoice')) return '🧾'
  if (entity.key?.includes('item')) return '🎮'
  if (entity.key?.includes('customer')) return '👤'
  if (entity.key?.includes('service')) return '🔧'
  if (entity.key?.includes('stock')) return '📦'
  return '◆'
}
</script>
