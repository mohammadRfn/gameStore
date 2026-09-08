<script setup>
/**
 * ProfileCard — کارت یک پروفایل فروشگاه
 * مسیر: resources/js/Components/StoreProfile/ProfileCard.vue
 *
 * ورودی: profile (StoreProfile) — خروجی‌ها: @view @edit @primary @remove
 */
import { computed } from 'vue'
import { Crown, Pencil, Trash2, Star, Phone, Mail, MapPin } from 'lucide-vue-next'

import { STATUS_META } from '@/Composables/useStoreProfileApi'

const props = defineProps({
    profile: { type: Object, required: true },
    /** آیا پروفایلِ اصلیِ فعلی همین است؟ */
    isPrimary: { type: Boolean, default: false },
})

const emit = defineEmits(['view', 'edit', 'primary', 'remove'])

const name = computed(() => props.profile.brand_name || props.profile.legal_name || 'بدون نام')

const initial = computed(() => (name.value || '؟').charAt(0))

const logoUrl = computed(() =>
    props.profile.logo_path ? `/storage/${props.profile.logo_path}` : '',
)

const coverUrl = computed(() =>
    props.profile.cover_path ? `/storage/${props.profile.cover_path}` : '',
)

const statusMeta = computed(() => STATUS_META[props.profile.status] || STATUS_META.inactive)
</script>

<template>
    <div class="sp-card a3d-tilt" :class="{ 'is-primary': isPrimary }" v-tilt="{ max: 8, scale: 1.02, lift: 12 }">
        <div class="sp-card__cover">
            <img v-if="coverUrl" :src="coverUrl" :alt="name" />
            <span v-if="isPrimary" class="sp-card__badge">
                <Crown :size="13" />
                پروفایل اصلی
            </span>
        </div>

        <div class="sp-card__body">
            <div class="sp-card__head">
                <div class="sp-card__avatar">
                    <img v-if="logoUrl" :src="logoUrl" :alt="name" />
                    <span v-else>{{ initial }}</span>
                </div>
                <div style="min-width:0">
                    <p class="sp-card__name">{{ name }}</p>
                    <p class="sp-card__slug">{{ profile.slug }}</p>
                </div>
                <span class="sp-pill" :class="statusMeta.className" style="margin-inline-start:auto">
                    {{ statusMeta.icon }} {{ statusMeta.label }}
                </span>
            </div>

            <div class="sp-card__meta">
                <div v-if="profile.phone" class="sp-card__meta-row">
                    <Phone :size="13" />
                    <span>{{ profile.phone }}</span>
                </div>
                <div v-if="profile.email" class="sp-card__meta-row">
                    <Mail :size="13" />
                    <span>{{ profile.email }}</span>
                </div>
                <div v-if="profile.address_city" class="sp-card__meta-row">
                    <MapPin :size="13" />
                    <span>{{ [profile.address_city, profile.address_province].filter(Boolean).join('، ') }}</span>
                </div>
            </div>

            <div class="sp-card__actions">
                <button type="button" class="sp-btn sp-btn--gold" style="flex:1; min-height:36px" @click="emit('view')">
                    <Star :size="14" />
                    مشاهده
                </button>
                <button type="button" class="sp-btn" style="min-height:36px; padding:0.25rem 0.7rem" title="ویرایش" @click="emit('edit')">
                    <Pencil :size="14" />
                </button>
                <button
                    v-if="!isPrimary"
                    type="button"
                    class="sp-btn"
                    style="min-height:36px; padding:0.25rem 0.7rem"
                    title="اصلی‌کردن"
                    @click="emit('primary')"
                >
                    <Crown :size="14" />
                </button>
                <button
                    v-if="!isPrimary"
                    type="button"
                    class="sp-btn sp-btn--danger"
                    style="min-height:36px; padding:0.25rem 0.7rem"
                    title="حذف"
                    @click="emit('remove')"
                >
                    <Trash2 :size="14" />
                </button>
            </div>
        </div>
    </div>
</template>
