<script setup>
/**
 * CrmPagination — صفحه‌بندی برای خروجی paginate() لاراول (از طریق Inertia)
 * مسیر: resources/js/Components/Crm/CrmPagination.vue
 *
 * ورودی همان آبجکت paginator است: { current_page, last_page, links[], prev_page_url,
 * next_page_url, from, to, total }. با withQueryString بک‌اند، فیلترها حفظ می‌شوند.
 *
 * استفاده:  <CrmPagination :paginator="customers" />
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { faInt } from '@/Utils/format'

const props = defineProps({
    paginator: { type: Object, required: true },
    label: { type: String, default: 'مورد' },
})

/** لینک‌های میانی (بدون prev/next لاراول) */
const pages = computed(() => {
    const links = props.paginator?.links || []
    return links.slice(1, -1).map((l) => ({
        url: l.url,
        active: !!l.active,
        dots: /\.\.\./.test(l.label),
        label: String(l.label).replace(/&hellip;/g, '…'),
    }))
})

const show = computed(() => (props.paginator?.last_page || 1) > 1)
</script>

<template>
    <nav v-if="show" class="crm-pager" aria-label="صفحه‌بندی">
        <Link
            :href="paginator.prev_page_url || '#'"
            class="crm-pager__btn"
            :class="{ 'is-disabled': !paginator.prev_page_url }"
            preserve-scroll
            preserve-state
            aria-label="صفحهٔ قبل"
        >
            <ChevronRight :size="15" />
        </Link>

        <template v-for="(p, i) in pages" :key="i">
            <span v-if="p.dots || !p.url" class="crm-pager__dots">…</span>
            <Link
                v-else
                :href="p.url"
                class="crm-pager__btn"
                :class="{ 'is-active': p.active }"
                preserve-scroll
                preserve-state
            >
                {{ faInt(p.label) }}
            </Link>
        </template>

        <Link
            :href="paginator.next_page_url || '#'"
            class="crm-pager__btn"
            :class="{ 'is-disabled': !paginator.next_page_url }"
            preserve-scroll
            preserve-state
            aria-label="صفحهٔ بعد"
        >
            <ChevronLeft :size="15" />
        </Link>

        <p class="crm-pager__info">
            نمایش {{ faInt(paginator.from || 0) }} تا {{ faInt(paginator.to || 0) }} از
            {{ faInt(paginator.total || 0) }} {{ label }}
        </p>
    </nav>
</template>
