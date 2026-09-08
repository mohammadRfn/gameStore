<script setup>
/**
 * ClearPanel — پنل پاکسازی کش‌ها (چک‌لیست target + آپشن‌ها + اجرا)
 * مسیر: resources/js/Components/CacheMaintenance/ClearPanel.vue
 *
 * ورودی‌ها:
 *   targets : object  → همان data از GET /settings/cache/targets
 *                       { [target]: { label, description, safe } }
 *   busy    : boolean → در حال اجرا بودن عملیات
 *   result  : object|null → آخرین اجرا (CacheMaintenanceRun) برای نمایش خلاصه
 *
 * خروجی:
 *   @clear  : payload کامل مطابق CacheMaintenanceRequest
 *             (شامل targets و dry_run و همهٔ آپشن‌ها)
 */
import { computed, reactive, ref } from 'vue'
import { FlaskConical, Trash2, Sparkles, ShieldCheck, ShieldAlert } from 'lucide-vue-next'

import GsRow from '@/Components/Settings/GsRow.vue'
import GsToggle from '@/Components/Settings/GsToggle.vue'
import TargetCard from './TargetCard.vue'
import { TARGETS, TARGET_ORDER, TARGET_UI, STATUS_META } from '@/Composables/useCacheMaintenanceApi'

const props = defineProps({
    targets: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
    result: { type: Object, default: null },
})

const emit = defineEmits(['clear'])

/* ------------------------------------------------------------------ */
/* انتخاب targetها                                                      */
/* ------------------------------------------------------------------ */
const selected = ref([])

/** کلیدهای امن پیش‌فرض (منطبق با DEFAULT_TARGETS بک‌اند) */
const safeKeys = computed(() =>
    Object.keys(props.targets).filter((k) => k !== TARGETS.ALL && props.targets[k]?.safe),
)

const orderedTargets = computed(() => {
    const keys = Object.keys(props.targets)
    return TARGET_ORDER.filter((k) => keys.includes(k))
        .concat(keys.filter((k) => !TARGET_ORDER.includes(k)))
        .filter((k) => k !== TARGETS.ALL)
})

function isSelected(key) {
    return selected.value.includes(key)
}

function toggle(key) {
    const i = selected.value.indexOf(key)
    if (i >= 0) selected.value.splice(i, 1)
    else selected.value.push(key)
}

function selectAllSafe() {
    selected.value = [...safeKeys.value]
}

function clearSelection() {
    selected.value = []
}

/* ------------------------------------------------------------------ */
/* آپشن‌ها                                                              */
/* ------------------------------------------------------------------ */
const options = reactive({
    dry_run: false,
    warm_after_clear: false,
    warm_config: false,
    warm_views: true,
    warm_settings: true,
    run_sqlite_vacuum: false,
    include_logs: false,
    logs_older_than_days: 14,
    include_sessions: false,
    include_orphan_media: false,
    include_old_backups: false,
    keep_last_backups: 5,
    force: false,
})

const selectedCount = computed(() => selected.value.length)

function buildPayload(dryRun) {
    return {
        targets: [...selected.value],
        dry_run: dryRun,
        include_logs: options.include_logs,
        logs_older_than_days: Number(options.logs_older_than_days),
        include_sessions: options.include_sessions,
        include_orphan_media: options.include_orphan_media,
        include_old_backups: options.include_old_backups,
        keep_last_backups: Number(options.keep_last_backups),
        warm_after_clear: options.warm_after_clear,
        warm_config: options.warm_config,
        warm_views: options.warm_views,
        warm_settings: options.warm_settings,
        run_sqlite_vacuum: options.run_sqlite_vacuum,
        force: options.force,
    }
}

function runDryRun() {
    emit('clear', buildPayload(true))
}

function runClear() {
    emit('clear', buildPayload(false))
}

/* ------------------------------------------------------------------ */
/* خلاصهٔ نتیجه                                                         */
/* ------------------------------------------------------------------ */
const resultHead = computed(() => {
    if (!props.result) return null
    const meta = STATUS_META[props.result.status] || STATUS_META.failed
    const cls = props.result.status === 'completed'
        ? 'ok'
        : props.result.status === 'partial'
            ? 'partial'
            : 'fail'
    return { meta, cls }
})

const resultRows = computed(() => {
    if (!props.result) return []
    const s = props.result.summary_json || {}
    const rows = []
    for (const [key, val] of Object.entries(s)) {
        if (key === 'message') continue
        if (typeof val === 'object' && val !== null) {
            rows.push({ key: targetLabel(key), value: summarize(val) })
        } else {
            rows.push({ key: targetLabel(key), value: val })
        }
    }
    return rows.slice(0, 12)
})

function targetLabel(key) {
    return props.targets[key]?.label || key
}

function summarize(val) {
    const parts = []
    if (val.deleted != null) parts.push(`${val.deleted} حذف`)
    if (val.deleted_files != null) parts.push(`${val.deleted_files} فایل`)
    if (val.deleted_directories != null) parts.push(`${val.deleted_directories} پوشه`)
    if (val.deleted_rows != null) parts.push(`${val.deleted_rows} رکورد`)
    if (val.skipped) return `رد شد (${val.reason || '—'})`
    if (val.ok != null) parts.unshift(val.ok ? 'انجام شد' : 'خطا')
    return parts.join('، ') || 'انجام شد'
}
</script>

<template>
    <div class="st-card a3d-holo">
        <!-- هدر پنل -->
        <div class="st-sechead">
            <span class="st-sechead__icon"><Trash2 :size="21" /></span>
            <div>
                <h2 class="st-sechead__title">پاکسازی کش</h2>
                <p class="st-sechead__desc">انتخاب بخش‌ها، اجرای آزمایشی یا پاکسازی واقعی</p>
            </div>
        </div>

        <!-- نوار ابزار انتخاب -->
        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; margin-bottom:0.9rem; flex-wrap:wrap">
            <span class="st-chip st-chip--plain">
                <Sparkles :size="13" />
                {{ selectedCount }} بخش انتخاب شده
            </span>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap">
                <button type="button" class="cm-btn cm-btn--ghost" @click="selectAllSafe">
                    <ShieldCheck :size="15" /> همهٔ امن
                </button>
                <button type="button" class="cm-btn cm-btn--ghost" @click="clearSelection">
                    پاک‌کردن انتخاب
                </button>
            </div>
        </div>

        <!-- چک‌لیست targetها -->
        <div class="cm-targets" style="margin-bottom:1.4rem">
            <TargetCard
                v-for="key in orderedTargets"
                :key="key"
                :title="targets[key]?.label || key"
                :desc="targets[key]?.description || ''"
                :safe="targets[key]?.safe !== false"
                :icon="TARGET_UI[key]?.icon || '🗂'"
                :selected="isSelected(key)"
                @toggle="toggle(key)"
            />
        </div>

        <!-- آپشن‌ها -->
        <div class="st-row">
            <div>
                <p class="st-row__title">اجرای آزمایشی (Dry-run)</p>
                <p class="st-row__desc">بدون حذف واقعی؛ فقط گزارش می‌دهد چه چیزی حذف می‌شد.</p>
            </div>
            <GsToggle v-model="options.dry_run" accent="green" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">گرم‌سازی بعد از پاکسازی</p>
                <p class="st-row__desc">بعد از پاکسازی، کش تنظیمات/ویوها دوباره ساخته شود.</p>
            </div>
            <GsToggle v-model="options.warm_after_clear" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">فشرده‌سازی دیتابیس (VACUUM)</p>
                <p class="st-row__desc">فقط برای دیتابیس SQLite اعمال می‌شود.</p>
            </div>
            <GsToggle v-model="options.run_sqlite_vacuum" />
        </div>

        <div class="st-row" :class="{ 'st-row--disabled': !options.warm_after_clear }">
            <div>
                <p class="st-row__title">تنظیمات گرم‌سازی</p>
                <p class="st-row__desc">config ، view و تنظیمات اپ</p>
            </div>
            <div class="st-row__control" style="display:flex; gap:1.2rem">
                <label class="cm-check"><input type="checkbox" v-model="options.warm_config" /> config</label>
                <label class="cm-check"><input type="checkbox" v-model="options.warm_views" /> views</label>
                <label class="cm-check"><input type="checkbox" v-model="options.warm_settings" /> settings</label>
            </div>
        </div>

        <!-- پاکسازی پیشرفته -->
        <div class="st-sechead" style="margin-top:1.3rem">
            <span class="st-sechead__icon"><ShieldAlert :size="21" /></span>
            <div>
                <h2 class="st-sechead__title" style="font-size:1.05rem">پاکسازی‌های پیشرفته</h2>
                <p class="st-sechead__desc">این موارد برگشت‌ناپذیرند و فقط با انتخاب صریح اجرا می‌شوند.</p>
            </div>
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">حذف لاگ‌های قدیمی</p>
                <p class="st-row__desc">فایل‌های .log قدیمی‌تر از N روز</p>
            </div>
            <div class="st-row__control" style="display:flex; align-items:center; gap:0.75rem">
                <input
                    v-if="options.include_logs"
                    v-model.number="options.logs_older_than_days"
                    type="number"
                    min="0"
                    max="3650"
                    class="cm-input"
                    style="width:90px"
                />
                <span v-if="options.include_logs" class="cm-hint">روز</span>
                <GsToggle v-model="options.include_logs" />
            </div>
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">حذف نشست‌های فایلی</p>
                <p class="st-row__desc">فقط برای session_driver=file</p>
            </div>
            <GsToggle v-model="options.include_sessions" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">حذف فایل‌های بلااستفاده</p>
                <p class="st-row__desc">فایل‌های public که به هیچ رکوردی وصل نیستند</p>
            </div>
            <GsToggle v-model="options.include_orphan_media" />
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">حذف بک‌آپ‌های قدیمی</p>
                <p class="st-row__desc">فقط N بک‌آپ آخر نگه داشته می‌شود</p>
            </div>
            <div class="st-row__control" style="display:flex; align-items:center; gap:0.75rem">
                <input
                    v-if="options.include_old_backups"
                    v-model.number="options.keep_last_backups"
                    type="number"
                    min="0"
                    max="100"
                    class="cm-input"
                    style="width:90px"
                />
                <span v-if="options.include_old_backups" class="cm-hint">نگه‌داشتن</span>
                <GsToggle v-model="options.include_old_backups" />
            </div>
        </div>

        <div class="st-row">
            <div>
                <p class="st-row__title">اجبار اجرا</p>
                <p class="st-row__desc">حتی اگر عملیات دیگری در حال اجراست ادامه بده.</p>
            </div>
            <GsToggle v-model="options.force" />
        </div>

        <!-- اکشن‌ها -->
        <div style="display:flex; gap:0.7rem; margin-top:1.5rem; flex-wrap:wrap">
            <button
                type="button"
                class="cm-btn"
                :class="{ 'is-loading': busy }"
                :disabled="busy || selectedCount === 0"
                @click="runDryRun"
            >
                <span class="cm-btn__spinner" />
                <FlaskConical :size="16" />
                بررسی آزمایشی
            </button>
            <button
                type="button"
                class="cm-btn cm-btn--gold"
                :class="{ 'is-loading': busy }"
                :disabled="busy || selectedCount === 0"
                @click="runClear"
            >
                <span class="cm-btn__spinner" />
                <Trash2 :size="16" />
                پاکسازی واقعی
            </button>
        </div>

        <!-- نتیجه -->
        <div v-if="result" class="cm-result" style="margin-top:1.3rem">
            <div class="cm-result__head" :class="`cm-result__head--${resultHead.cls}`">
                <span>{{ resultHead.meta.icon }}</span>
                {{ resultHead.meta.label }}
                <span v-if="result.is_dry_run" class="cm-pill cm-pill--info">Dry-run</span>
            </div>
            <div class="cm-result__body">
                <div v-for="(row, i) in resultRows" :key="i" class="cm-result__row">
                    <span>{{ row.key }}</span>
                    <b>{{ row.value }}</b>
                </div>
                <div v-if="result.duration_ms" class="cm-result__row">
                    <span>مدت اجرا</span>
                    <b>{{ Math.round(result.duration_ms / 100) / 10 }} ثانیه</b>
                </div>
            </div>
        </div>
    </div>
</template>
