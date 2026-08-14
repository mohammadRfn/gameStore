<template>
    <div class="podium">
        <TiltCard v-for="(p, i) in podium" :key="i" :intensity="8" class="podium-slot">
            <div class="podium-block" :class="'place-' + p.place">
                <span class="podium-medal">{{ p.medal }}</span>
                <span class="podium-name">{{ p.name }}</span>
                <span class="podium-value">{{ p.value }}</span>
                <span v-if="p.sub" class="podium-sub">{{ p.sub }}</span>
            </div>
        </TiltCard>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import TiltCard from './TiltCard.vue'
import { compactMoney } from '@/Utils/format'

const props = defineProps({
    items: { type: Array, default: () => [] }, // [{ name, value, sub }] مرتب‌شده نزولی
})

const podium = computed(() => {
    const arr = (props.items || []).slice(0, 3)
    // ترتیب نمایش: نفر دوم، نفر اول، نفر سوم
    const order = [1, 0, 2]
    const medals = ['🥇', '🥈', '🥉']
    return order.map((idx) => {
        const item = arr[idx]
        if (!item) return { name: '—', value: '', sub: '', place: idx + 1, medal: medals[idx] }
        return {
            name: item.name,
            value: compactMoney(item.value),
            sub: item.sub || '',
            place: idx + 1,
            medal: medals[idx],
        }
    })
})
</script>

<style scoped>
.podium {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 1rem;
    padding: 0.5rem 0 0;
}
.podium-slot { flex: 1; max-width: 220px; }
.podium-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 1.1rem 0.9rem;
    border-radius: 16px;
    border: 1px solid var(--gs-border);
    background: var(--gs-bg-card);
    box-shadow: var(--gs-shadow-md);
    text-align: center;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.podium-block.place-1 {
    padding-top: 2rem;
    padding-bottom: 2rem;
    border-color: var(--gs-border-strong);
    background:
        radial-gradient(120% 90% at 50% 0%, var(--gs-gold-muted), transparent 60%),
        var(--gs-bg-card);
    animation: gs-float 6s ease-in-out infinite;
}
.podium-block.place-2 { padding-top: 1.4rem; padding-bottom: 1.4rem; }
.podium-block.place-3 { padding-top: 1rem; padding-bottom: 1rem; }
.podium-medal { font-size: 2rem; line-height: 1; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3)); }
.podium-name {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--gs-text-primary);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.podium-value { font-weight: 800; color: var(--gs-gold); font-variant-numeric: tabular-nums; }
.podium-sub { font-size: 0.7rem; color: var(--gs-text-muted); }
</style>
