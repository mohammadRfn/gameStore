<template>
    <div class="menu-page">
        <div class="menu-card">
            <h1>جلسه منقضی شده</h1>
            <p>{{ message }}</p>
        </div>
    </div>
</template>

<script setup>
defineProps({ message: String })
</script>

<style scoped>
.menu-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #14141a; direction: rtl; font-family: Vazirmatn, sans-serif; }
.menu-card { background: #1e1e26; border-radius: 16px; padding: 2rem; max-width: 340px; text-align: center; color: #eee; }
</style>