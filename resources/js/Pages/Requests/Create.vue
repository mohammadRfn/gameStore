<script setup>
/**
 * ثبت درخواست جدید — فرم مدرن ۳D
 * مسیر: resources/js/Pages/Requests/Create.vue
 */
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ClipboardList, ArrowRight, Save, User, Tag, FileText } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal } from '@/Composables/useTilt'

const props = defineProps({
    customers: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const form = useForm({
    customer_id: '',
    customer_name: '',
    description: '',
    category_ids: [],
})

function onCustomerSelect(e) {
    const cid = e.target.value
    if (cid) {
        const found = props.customers.find((c) => String(c.id) === String(cid))
        if (found) form.customer_name = found.name
    }
}

function toggleCategory(catId) {
    const idx = form.category_ids.indexOf(catId)
    if (idx > -1) {
        form.category_ids.splice(idx, 1)
    } else {
        form.category_ids.push(catId)
    }
}

function submit() {
    form.post(route('requests.store'))
}
</script>

<template>
    <AppLayout>
        <Head title="ثبت درخواست جدید" />

        <div class="st-page relative z-10 max-w-3xl mx-auto space-y-6 pb-12">
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div>
                    <span class="st-chip st-chip--live text-xs">خدمات و پشتیبانی</span>
                    <h1 class="st-hero__title">ثبت <span>درخواست جدید</span></h1>
                    <p class="st-hero__lead">ثبت شرح مشکل دستگاه، انتخاب دسته‌بندی و الصاق به پرونده مشتری</p>
                </div>
                <Link :href="route('requests.index')" class="px-3 py-2 rounded-xl bg-neutral-800 text-neutral-300 text-xs hover:bg-neutral-700 flex items-center gap-1">
                    بازگشت <ArrowRight :size="14" />
                </Link>
            </header>

            <form @submit.prevent="submit" class="st-card p-6 rounded-2xl space-y-5" v-reveal="{ delay: 100 }">
                <!-- انتخاب مشتری -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-neutral-200">انتخاب مشتری از قبل ثبت‌شده</label>
                        <select
                            v-model="form.customer_id"
                            @change="onCustomerSelect"
                            class="w-full bg-neutral-900/80 border border-neutral-700 focus:border-amber-400 rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none"
                        >
                            <option value="">— مشتری ثبت نشده / موردی —</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-neutral-200">
                            نام مشتری <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.customer_name"
                            type="text"
                            class="w-full bg-neutral-900/80 border rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500"
                            :class="form.errors.customer_name ? 'border-rose-500/80 bg-rose-500/5' : 'border-neutral-700 focus:border-amber-400'"
                            placeholder="نام کامل مشتری"
                        />
                        <p v-if="form.errors.customer_name" class="text-[11px] text-rose-400">{{ form.errors.customer_name }}</p>
                    </div>
                </div>

                <!-- دسته‌بندی‌ها با چیپ چندانتخابی -->
                <div class="space-y-2">
                    <label class="text-xs font-bold text-neutral-200">دسته‌بندی‌های خدمت / قطعه</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            @click="toggleCategory(cat.id)"
                            :class="form.category_ids.includes(cat.id) ? 'bg-amber-500/20 text-amber-300 border-amber-500/60 shadow-md shadow-amber-500/10' : 'bg-neutral-900/60 text-neutral-400 border-neutral-800 hover:border-neutral-700'"
                            class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- شرح مشکل -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-neutral-200">
                        شرح مشکل یا درخواست <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full bg-neutral-900/80 border rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500 leading-relaxed"
                        :class="form.errors.description ? 'border-rose-500/80 bg-rose-500/5' : 'border-neutral-700 focus:border-amber-400'"
                        placeholder="توضیحات ایراد دستگاه، مدل کنسول، دسته یا دیتای مورد نیاز..."
                    ></textarea>
                    <p v-if="form.errors.description" class="text-[11px] text-rose-400">{{ form.errors.description }}</p>
                </div>

                <div class="pt-4 border-t border-neutral-800 flex items-center justify-end gap-3">
                    <Link :href="route('requests.index')" class="gs-btn-ghost text-xs">انصراف</Link>
                    <button type="submit" :disabled="form.processing" class="gs-btn-gold text-xs">
                        <Save :size="15" />
                        <span>{{ form.processing ? 'در حال ثبت...' : 'ثبت نهایی درخواست' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
