<script setup>
/**
 * ثبت مشتری جدید — فرم مدرن ۳D
 * مسیر: resources/js/Pages/Customers/Create.vue
 */
import { Head, Link, useForm } from '@inertiajs/vue3'
import { UserPlus, ArrowRight, Save, User, Phone, Mail, MapPin, FileText } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal } from '@/Composables/useTilt'

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
})

function submit() {
    form.post(route('customers.store'))
}
</script>

<template>
    <AppLayout>
        <Head title="ثبت مشتری جدید" />

        <div class="st-page relative z-10 max-w-3xl mx-auto space-y-6 pb-12">
            <header class="st-hero" v-reveal="{ delay: 50 }">
                <div>
                    <span class="st-chip st-chip--live text-xs">باشگاه مشتریان</span>
                    <h1 class="st-hero__title">افزودن <span>مشتری جدید</span></h1>
                    <p class="st-hero__lead">ثبت اطلاعات تماس و نشانی جهت صدور فاکتور و درخواست‌های تعمیرات</p>
                </div>
                <Link :href="route('customers.index')" class="px-3 py-2 rounded-xl bg-neutral-800 text-neutral-300 text-xs hover:bg-neutral-700 flex items-center gap-1">
                    بازگشت <ArrowRight :size="14" />
                </Link>
            </header>

            <form @submit.prevent="submit" class="st-card p-6 rounded-2xl space-y-5" v-reveal="{ delay: 100 }">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- نام -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-neutral-200 flex items-center gap-1.5">
                            <User :size="14" class="text-amber-400" />
                            نام و نام خانوادگی <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full bg-neutral-900/80 border rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500"
                            :class="form.errors.name ? 'border-rose-500/80 bg-rose-500/5' : 'border-neutral-700 focus:border-amber-400'"
                            placeholder="مثال: آرشام صادقی"
                        />
                        <p v-if="form.errors.name" class="text-[11px] text-rose-400">{{ form.errors.name }}</p>
                    </div>

                    <!-- شماره تماس -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-neutral-200 flex items-center gap-1.5">
                            <Phone :size="14" class="text-amber-400" />
                            شماره تلفن همراه
                        </label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            dir="ltr"
                            class="w-full bg-neutral-900/80 border rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500 font-mono"
                            :class="form.errors.phone ? 'border-rose-500/80 bg-rose-500/5' : 'border-neutral-700 focus:border-amber-400'"
                            placeholder="09123456789"
                        />
                        <p v-if="form.errors.phone" class="text-[11px] text-rose-400">{{ form.errors.phone }}</p>
                    </div>

                    <!-- ایمیل -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-bold text-neutral-200 flex items-center gap-1.5">
                            <Mail :size="14" class="text-amber-400" />
                            آدرس ایمیل
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            dir="ltr"
                            class="w-full bg-neutral-900/80 border rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500"
                            :class="form.errors.email ? 'border-rose-500/80 bg-rose-500/5' : 'border-neutral-700 focus:border-amber-400'"
                            placeholder="customer@example.com"
                        />
                        <p v-if="form.errors.email" class="text-[11px] text-rose-400">{{ form.errors.email }}</p>
                    </div>

                    <!-- آدرس -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-bold text-neutral-200 flex items-center gap-1.5">
                            <MapPin :size="14" class="text-amber-400" />
                            آدرس پستی
                        </label>
                        <input
                            v-model="form.address"
                            type="text"
                            class="w-full bg-neutral-900/80 border rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500"
                            :class="form.errors.address ? 'border-rose-500/80 bg-rose-500/5' : 'border-neutral-700 focus:border-amber-400'"
                            placeholder="تهران، خیابان..."
                        />
                        <p v-if="form.errors.address" class="text-[11px] text-rose-400">{{ form.errors.address }}</p>
                    </div>

                    <!-- یادداشت -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-bold text-neutral-200 flex items-center gap-1.5">
                            <FileText :size="14" class="text-amber-400" />
                            یادداشت و توضیحات تکمیلی
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full bg-neutral-900/80 border border-neutral-700 focus:border-amber-400 rounded-xl py-2.5 px-3.5 text-xs text-neutral-200 outline-none transition-all placeholder:text-neutral-500"
                            placeholder="توضیحات مربوط به ترجیحات مشتری یا کنسول..."
                        ></textarea>
                    </div>
                </div>

                <div class="pt-4 border-t border-neutral-800 flex items-center justify-end gap-3">
                    <Link :href="route('customers.index')" class="gs-btn-ghost text-xs">انصراف</Link>
                    <button type="submit" :disabled="form.processing" class="gs-btn-gold text-xs">
                        <Save :size="15" />
                        <span>{{ form.processing ? 'در حال ثبت...' : 'ذخیره مشتری' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
