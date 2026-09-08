<script setup>
/**
 * صفحهٔ جزئیات پروفایل فروشگاه — GameStore
 * مسیر: resources/js/Pages/StoreProfiles/Show.vue
 * ---------------------------------------------------------------------------
 * توسط StoreProfileController::show رندر می‌شود (Inertia): props = { profile }
 */
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
    ArrowRight,
    Crown,
    Pencil,
    PhoneCall,
    MapPinned,
    UserRound,
    ReceiptText,
    Image as ImageIcon,
    Clock,
    Globe,
    Mail,
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { jalali } from '@/Utils/format'
import { STATUS_META } from '@/Composables/useStoreProfileApi'

import ProfileScene from '@/Components/StoreProfile/ProfileScene.vue'
import ProfileFormModal from '@/Components/StoreProfile/ProfileFormModal.vue'

const props = defineProps({
    profile: { type: Object, required: true },
})

const editOpen = ref(false)

const name = computed(() => props.profile.brand_name || props.profile.legal_name || 'بدون نام')
const statusMeta = computed(() => STATUS_META[props.profile.status] || STATUS_META.inactive)

const logoUrl = computed(() => (props.profile.logo_path ? `/storage/${props.profile.logo_path}` : ''))
const coverUrl = computed(() => (props.profile.cover_path ? `/storage/${props.profile.cover_path}` : ''))

const fullAddress = computed(() =>
    [props.profile.address_street, props.profile.address_city, props.profile.address_province, props.profile.address_postal, props.profile.address_country]
        .filter(Boolean)
        .join('، '),
)

const ownerName = computed(() =>
    [props.profile.owner_first_name, props.profile.owner_last_name].filter(Boolean).join(' ') || '—',
)

const workingHours = computed(() => props.profile.working_hours || [])

const DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه']

function hourOf(day) {
    const found = workingHours.value.find((w) => w?.day === day)
    if (!found?.open && !found?.close) return null
    return `${found.open || '—'} تا ${found.close || '—'}`
}

const infoRows = (items) => items.filter((it) => it.value)
</script>

<template>
    <AppLayout>
        <Head :title="name" />

        <div class="st-page">
            <ProfileScene />

            <div class="st-shell" style="position:relative; z-index:1; padding-bottom:3rem">
                <!-- کاور -->
                <div class="a3d-holo" style="position:relative; height:190px; margin-top:1.5rem; overflow:hidden; border-radius:var(--gs-radius-lg)">
                    <img v-if="coverUrl" :src="coverUrl" :alt="name" style="width:100%; height:100%; object-fit:cover" />
                    <div v-else style="width:100%; height:100%; background:radial-gradient(120% 160% at 50% 0%, var(--gs-gold-glow), transparent 70%), var(--gs-bg-elevated)" />
                </div>

                <!-- سربرگ -->
                <div style="display:flex; align-items:flex-end; gap:1.1rem; margin-top:-42px; padding-inline:0.5rem; flex-wrap:wrap">
                    <div class="sp-card__avatar" style="width:88px; height:88px; font-size:2rem; border-radius:22px; box-shadow:var(--gs-shadow-md)">
                        <img v-if="logoUrl" :src="logoUrl" :alt="name" style="width:100%; height:100%; object-fit:cover" />
                        <span v-else>{{ name.charAt(0) }}</span>
                    </div>
                    <div style="flex:1; min-width:200px; padding-bottom:0.4rem">
                        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap">
                            <h1 style="font-size:1.7rem; font-weight:900; color:var(--gs-text-primary); letter-spacing:-0.02em">{{ name }}</h1>
                            <span v-if="profile.is_primary" class="sp-card__badge" style="position:static">
                                <Crown :size="13" />
                                اصلی
                            </span>
                        </div>
                        <p class="sp-card__slug">{{ profile.slug }}</p>
                        <span class="sp-pill" :class="statusMeta.className" style="margin-top:0.4rem">
                            {{ statusMeta.icon }} {{ statusMeta.label }}
                        </span>
                    </div>
                    <div style="display:flex; gap:0.6rem; padding-bottom:0.4rem">
                        <button type="button" class="sp-btn sp-btn--ghost" onclick="history.back()">
                            <ArrowRight :size="15" />
                            بازگشت
                        </button>
                        <button type="button" class="sp-btn sp-btn--gold" @click="editOpen = true">
                            <Pencil :size="15" />
                            ویرایش
                        </button>
                    </div>
                </div>

                <!-- جزئیات -->
                <div class="st-grid" style="grid-template-columns:repeat(2, 1fr); gap:1.2rem; margin-top:1.6rem">
                    <!-- تماس -->
                    <div class="st-card a3d-holo" v-reveal="{ delay: 40 }">
                        <div class="st-sechead">
                            <span class="st-sechead__icon"><PhoneCall :size="21" /></span>
                            <div>
                                <h2 class="st-sechead__title" style="font-size:1.05rem">تماس</h2>
                            </div>
                        </div>
                        <div class="cm-env">
                            <div v-if="profile.phone" class="cm-env__row"><span class="cm-env__k">تلفن</span><span class="cm-env__v">{{ profile.phone }}</span></div>
                            <div v-if="profile.secondary_phone" class="cm-env__row"><span class="cm-env__k">تلفن دوم</span><span class="cm-env__v">{{ profile.secondary_phone }}</span></div>
                            <div v-if="profile.email" class="cm-env__row"><span class="cm-env__k">ایمیل</span><span class="cm-env__v">{{ profile.email }}</span></div>
                            <div v-if="profile.website" class="cm-env__row"><span class="cm-env__k">وب‌سایت</span><span class="cm-env__v">{{ profile.website }}</span></div>
                            <div v-if="profile.instagram" class="cm-env__row"><span class="cm-env__k">اینستاگرام</span><span class="cm-env__v">{{ profile.instagram }}</span></div>
                            <div v-if="profile.telegram" class="cm-env__row"><span class="cm-env__k">تلگرام</span><span class="cm-env__v">{{ profile.telegram }}</span></div>
                        </div>
                    </div>

                    <!-- آدرس -->
                    <div class="st-card a3d-holo" v-reveal="{ delay: 80 }">
                        <div class="st-sechead">
                            <span class="st-sechead__icon"><MapPinned :size="21" /></span>
                            <div>
                                <h2 class="st-sechead__title" style="font-size:1.05rem">آدرس</h2>
                            </div>
                        </div>
                        <p v-if="fullAddress" style="font-size:0.82rem; line-height:2; color:var(--gs-text-secondary)">{{ fullAddress }}</p>
                        <p v-else class="cm-hint">آدرسی ثبت نشده است.</p>
                    </div>

                    <!-- مالک -->
                    <div class="st-card a3d-holo" v-reveal="{ delay: 120 }">
                        <div class="st-sechead">
                            <span class="st-sechead__icon"><UserRound :size="21" /></span>
                            <div>
                                <h2 class="st-sechead__title" style="font-size:1.05rem">مالک</h2>
                            </div>
                        </div>
                        <div class="cm-env">
                            <div class="cm-env__row"><span class="cm-env__k">نام</span><span class="cm-env__v">{{ ownerName }}</span></div>
                            <div v-if="profile.owner_national_id" class="cm-env__row"><span class="cm-env__k">کد ملی</span><span class="cm-env__v">{{ profile.owner_national_id }}</span></div>
                            <div v-if="profile.owner_phone" class="cm-env__row"><span class="cm-env__k">تلفن</span><span class="cm-env__v">{{ profile.owner_phone }}</span></div>
                            <div v-if="profile.owner_email" class="cm-env__row"><span class="cm-env__k">ایمیل</span><span class="cm-env__v">{{ profile.owner_email }}</span></div>
                        </div>
                    </div>

                    <!-- مالی -->
                    <div class="st-card a3d-holo" v-reveal="{ delay: 160 }">
                        <div class="st-sechead">
                            <span class="st-sechead__icon"><ReceiptText :size="21" /></span>
                            <div>
                                <h2 class="st-sechead__title" style="font-size:1.05rem">مالی و اسناد</h2>
                            </div>
                        </div>
                        <div class="cm-env">
                            <div v-if="profile.tax_id" class="cm-env__row"><span class="cm-env__k">شناسهٔ مالیاتی</span><span class="cm-env__v">{{ profile.tax_id }}</span></div>
                            <div v-if="profile.registration_no" class="cm-env__row"><span class="cm-env__k">شمارهٔ ثبت</span><span class="cm-env__v">{{ profile.registration_no }}</span></div>
                            <div v-if="profile.founding_date" class="cm-env__row"><span class="cm-env__k">تاریخ تأسیس</span><span class="cm-env__v">{{ jalali(profile.founding_date) }}</span></div>
                            <div v-if="profile.currency_code" class="cm-env__row"><span class="cm-env__k">ارز</span><span class="cm-env__v">{{ profile.currency_symbol || '' }} {{ profile.currency_code }}</span></div>
                            <div v-if="profile.fiscal_year_start" class="cm-env__row"><span class="cm-env__k">شروع سال مالی</span><span class="cm-env__v">ماه {{ profile.fiscal_year_start }}</span></div>
                        </div>
                    </div>

                    <!-- ساعات کاری -->
                    <div class="st-card a3d-holo" v-reveal="{ delay: 200 }">
                        <div class="st-sechead">
                            <span class="st-sechead__icon"><Clock :size="21" /></span>
                            <div>
                                <h2 class="st-sechead__title" style="font-size:1.05rem">ساعات کاری</h2>
                            </div>
                        </div>
                        <div v-if="workingHours.length" class="cm-env">
                            <div v-for="day in DAYS" :key="day" class="cm-env__row">
                                <span class="cm-env__k">{{ day }}</span>
                                <span class="cm-env__v">{{ hourOf(day) || 'تعطیل' }}</span>
                            </div>
                        </div>
                        <p v-else class="cm-hint">ساعات کاری ثبت نشده است.</p>
                    </div>

                    <!-- پاورقی -->
                    <div v-if="profile.receipt_footer" class="st-card a3d-holo" v-reveal="{ delay: 240 }">
                        <div class="st-sechead">
                            <span class="st-sechead__icon"><Globe :size="21" /></span>
                            <div>
                                <h2 class="st-sechead__title" style="font-size:1.05rem">متن پاورقی رسید</h2>
                            </div>
                        </div>
                        <p style="font-size:0.8rem; line-height:2; color:var(--gs-text-secondary)">{{ profile.receipt_footer }}</p>
                    </div>
                </div>
            </div>
        </div>

        <ProfileFormModal :open="editOpen" :profile="profile" @close="editOpen = false" />
    </AppLayout>
</template>
