<script setup>
import { computed } from 'vue'
import BackupCard from '@/Components/Backup/BackupCard.vue'
import { faNumber } from '@/Composables/useBackupCenter'

/**
 * انتخاب بخش‌های اطلاعات.
 * تغییرات مهم:
 *  - نام جدول‌های دیتابیس دیگر نمایش داده نمی‌شود.
 *  - وضعیت «حذف نرم» بودن جدول‌ها کاملاً از رابط کاربری حذف شد.
 *  - همه‌ی کارت‌ها هم‌اندازه‌اند و بین آن‌ها فاصله‌ی یکسان وجود دارد.
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
      <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] leading-4 text-slate-300 ring-1 ring-white/10">
        {{ faNumber(selectedCount) }} از {{ faNumber(usable.length) }}
      </span>
      <button
        type="button"
        class="h-8 rounded-lg bg-white/5 px-3 text-xs font-semibold text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10"
        @click="allSelected ? clearAll() : selectAll()"
      >
        {{ allSelected ? 'پاک کردن انتخاب‌ها' : 'انتخاب همه' }}
      </button>
    </template>

    <div v-for="group in grouped" :key="group.key" class="flex flex-col gap-3">
      <div class="flex items-center justify-between gap-3">
        <h4 class="text-xs font-bold text-slate-300">{{ group.title }}</h4>
        <button
          type="button"
          class="text-[11px] font-medium text-amber-300/90 transition hover:text-amber-200"
          @click="toggleGroup(group)"
        >
          انتخاب این گروه
        </button>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="entity in group.items"
          :key="entity.key"
          type="button"
          class="flex items-center gap-3 rounded-xl px-4 py-3 text-right ring-1 transition-colors duration-200"
          :class="isSelected(entity.key)
            ? 'bg-amber-400/10 ring-amber-400/40'
            : 'bg-white/[0.03] ring-white/5 hover:bg-white/[0.06]'"
          :aria-pressed="isSelected(entity.key)"
          @click="toggle(entity.key)"
        >
          <span
            class="grid size-5 shrink-0 place-items-center rounded-md text-[11px] leading-none transition"
            :class="isSelected(entity.key) ? 'bg-amber-400 text-slate-900' : 'bg-white/10 text-transparent'"
            aria-hidden="true"
          >✓</span>

          <span class="min-w-0 flex-1">
            <span class="block truncate text-[13px] font-semibold leading-5 text-slate-100">{{ entity.label }}</span>
            <span class="mt-0.5 block text-[11px] leading-4 text-slate-400">
              {{ faNumber(entity.rows) }} مورد
            </span>
          </span>

          <span
            v-if="entity.has_media"
            class="shrink-0 rounded-full bg-white/5 px-2 py-0.5 text-[10px] leading-4 text-slate-300 ring-1 ring-white/10"
          >تصویر دارد</span>
        </button>
      </div>
    </div>

    <p v-if="!grouped.length" class="rounded-xl bg-white/[0.03] px-4 py-6 text-center text-xs text-slate-400">
      فهرست بخش‌ها هنوز بارگذاری نشده است.
    </p>
  </BackupCard>
</template>
