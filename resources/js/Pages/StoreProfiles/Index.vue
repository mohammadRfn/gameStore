<script setup>
/**
 * صفحهٔ پروفایل‌های فروشگاه — GameStore
 * مسیر: resources/js/Pages/StoreProfiles/Index.vue
 * ---------------------------------------------------------------------------
 * این صفحه توسط StoreProfileController::index رندر می‌شود (Inertia):
 *   props = { profiles, primary, searchTerm }
 *
 * عملیات ساخت/ویرایش با useForm و حذف/اصلی‌کردن با router اینرسیا انجام می‌شود
 * که دقیقاً با شاخهٔ redirect()->back() بک‌اند هماهنگ است.
 */
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Crown, Plus, Search, Store, X } from 'lucide-vue-next'

import AppLayout from '@/Layouts/AppLayout.vue'
import { vReveal } from '@/Composables/useTilt'
import { useFlash } from '@/Composables/useFlash'

import ProfileScene from '@/Components/StoreProfile/ProfileScene.vue'
import ProfileCard from '@/Components/StoreProfile/ProfileCard.vue'
import ProfileFormModal from '@/Components/StoreProfile/ProfileFormModal.vue'
import ToastHost from '@/Components/Settings/ToastHost.vue'

const props = defineProps({
    profiles: { type: Array, default: () => [] },
    primary: { type: Object, default: null },
    searchTerm: { type: String, default: '' },
})

const { success, error } = useFlash()

/* ------------------------------------------------------------------ */
/* جستجو (کلاینت‌ساید؛ بک‌اند route جستجو هم دارد)                      */
/* ------------------------------------------------------------------ */
const term = ref(props.searchTerm || '')

const filtered = computed(() => {
    const q = term.value.trim().toLowerCase()
    if (!q) return props.profiles
    return props.profiles.filter((p) =>
        [p.legal_name, p.brand_name, p.slug, p.phone, p.email]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(q)),
    )
})

/* ------------------------------------------------------------------ */
/* مودال فرم + تأیید حذف                                                 */
/* ------------------------------------------------------------------ */
const formOpen = ref(false)
const editing = ref(null)

const confirmProfile = ref(null)

function openCreate() {
    editing.value = null
    formOpen.value = true
}

function openEdit(profile) {
    editing.value = profile
    formOpen.value = true
}

function setPrimary(profile) {
    router.post(route('store-profiles.primary', profile.id))
}

function askRemove(profile) {
    confirmProfile.value = profile
}

function confirmRemove() {
    const id = confirmProfile.value?.id
    confirmProfile.value = null
    if (id) router.delete(route('store-profiles.destroy', id))
}

/* ------------------------------------------------------------------ */
/* توست از flash                                                        */
/* ------------------------------------------------------------------ */
const toasts = ref([])
let toastId = 0

function pushToast(kind, msg) {
    const id = ++toastId
    toasts.value.push({ id, kind, msg })
    setTimeout(() => dismissToast(id), 5000)
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
}

watch(success, (v) => v && pushToast('success', v))
watch(error, (v) => v && pushToast('danger', v))
</script>

<template>
    <AppLayout>
        <Head title="پروفایل‌های فروشگاه" />

        <div class="st-page">
            <ProfileScene />

            <div class="st-shell" style="position:relative; z-index:1">
                <!-- هدر -->
                <header class="st-hero">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap">
                            <span class="st-chip">
                                <Store :size="13" />
                                {{ props.profiles.length }} پروفایل
                            </span>
                            <span v-if="props.primary" class="st-chip st-chip--plain">
                                <Crown :size="13" />
                                اصلی: {{ props.primary.brand_name || props.primary.legal_name }}
                            </span>
                        </div>
                        <h1 class="st-hero__title">پروفایل <span>فروشگاه</span></h1>
                        <svg class="st-underline" viewBox="0 0 230 12" fill="none" aria-hidden="true">
                            <path d="M2 9C60 2 150 2 228 8" stroke="var(--gs-gold)" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <p class="st-hero__lead">
                            مدیریت هویت، تماس، آدرس و برندینگ فروشگاه‌های شما — یک پروفایل اصلی برای فاکتورها و رسیدها.
                        </p>
                    </div>

                    <button type="button" class="sp-btn sp-btn--gold" @click="openCreate">
                        <Plus :size="16" />
                        پروفایل جدید
                    </button>
                </header>

                <!-- جستجو -->
                <div style="display:flex; align-items:center; gap:0.6rem; margin:1.4rem 0 1rem">
                    <div style="position:relative; flex:1; max-width:420px">
                        <Search :size="16" style="position:absolute; top:50%; transform:translateY(-50%); inset-inline-start:0.85rem; color:var(--gs-text-muted)" />
                        <input
                            v-model="term"
                            type="search"
                            class="sp-input"
                            placeholder="جستجو بر اساس نام، شناسه، تلفن یا ایمیل…"
                            style="padding-inline-start:2.4rem"
                        />
                    </div>
                    <button v-if="term" type="button" class="sp-btn sp-btn--ghost" @click="term = ''">
                        <X :size="15" />
                        پاک‌کردن
                    </button>
                </div>

                <!-- شبکهٔ کارت‌ها -->
                <div v-if="filtered.length" class="sp-grid">
                    <ProfileCard
                        v-for="profile in filtered"
                        :key="profile.id"
                        :profile="profile"
                        :is-primary="props.primary?.id === profile.id"
                        v-reveal="{ delay: 40 }"
                        @view="router.visit(route('store-profiles.show', profile.id))"
                        @edit="openEdit(profile)"
                        @primary="setPrimary(profile)"
                        @remove="askRemove(profile)"
                    />
                </div>

                <!-- حالت خالی -->
                <div v-else class="st-card a3d-holo" style="text-align:center; padding:3.5rem 1rem">
                    <p style="font-size:2rem; margin-bottom:0.6rem">🏪</p>
                    <p class="st-sechead__title" style="justify-content:center">هنوز پروفایلی ثبت نشده</p>
                    <p class="st-sechead__desc" style="margin:0.5rem 0 1.4rem">اولین پروفایل به‌صورت خودکار «اصلی» می‌شود.</p>
                    <button type="button" class="sp-btn sp-btn--gold" @click="openCreate">
                        <Plus :size="16" />
                        ساخت اولین پروفایل
                    </button>
                </div>
            </div>
        </div>

        <!-- مودال فرم -->
        <ProfileFormModal :open="formOpen" :profile="editing" @close="formOpen = false" />

        <!-- تأیید حذف -->
        <Teleport to="body">
            <Transition name="sp-modal">
                <div v-if="confirmProfile" class="sp-modal-mask" @click.self="confirmProfile = null">
                    <div class="sp-modal" style="width:min(420px, 92vw)">
                        <div class="sp-modal__head">
                            <p class="sp-modal__title">حذف پروفایل</p>
                        </div>
                        <div class="sp-modal__body">
                            <p style="font-size:0.85rem; line-height:2; color:var(--gs-text-secondary)">
                                آیا از حذف پروفایل
                                <b style="color:var(--gs-text-primary)">{{ confirmProfile.brand_name || confirmProfile.legal_name }}</b>
                                مطمئن هستید؟ این عمل برگشت‌ناپذیر است.
                            </p>
                        </div>
                        <div class="sp-modal__foot">
                            <button type="button" class="sp-btn sp-btn--ghost" @click="confirmProfile = null">انصراف</button>
                            <button type="button" class="sp-btn sp-btn--danger" @click="confirmRemove">حذف</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ToastHost :toasts="toasts" @close="dismissToast" />
    </AppLayout>
</template>
