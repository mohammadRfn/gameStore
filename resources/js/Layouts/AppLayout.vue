<template>
    <div :class="['gs-app', themeClass]">

        <!-- TopBar -->
        <header class="gs-topbar">
            <div class="gs-topbar-right">
                <!-- Hamburger -->
                <button class="gs-hamburger" @click="toggleSidebar"
                    :aria-label="sidebarOpen ? 'بستن منو' : 'باز کردن منو'">
                    <span :class="['gs-hamburger-line', { 'open': sidebarOpen }]"></span>
                    <span :class="['gs-hamburger-line', { 'open': sidebarOpen }]"></span>
                    <span :class="['gs-hamburger-line', { 'open': sidebarOpen }]"></span>
                </button>

                <!-- Brand -->
                <Link :href="route('dashboard')" class="gs-brand">
                    <span class="gs-brand-icon">♟</span>
                    <span class="gs-brand-name">Game<span class="gs-gold-text">Shop</span></span>
                </Link>
            </div>

            <!-- Quick Actions -->
            <nav class="gs-topbar-nav">
                <Link v-for="item in quickActions" :key="item.route" :href="route(item.route)"
                    :class="['gs-topbar-link', { 'active': isActive(item.route) }]">
                    <span class="gs-topbar-link-icon">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <!-- Left Side -->
            <div class="gs-topbar-left">
                <!-- Theme Toggle -->
                <button class="gs-icon-btn" @click="toggleTheme" :title="isDark ? 'حالت روشن' : 'حالت تاریک'">
                    <span>{{ isDark ? '☀️' : '🌙' }}</span>
                </button>

                <!-- User -->
                <div class="gs-user-menu" @click="userMenuOpen = !userMenuOpen">
                    <div class="gs-user-avatar">
                        {{ userInitial }}
                    </div>
                    <span class="gs-user-name">{{ $page.props.auth?.user?.name ?? 'کاربر' }}</span>

                    <!-- Dropdown -->
                    <Transition name="gs-fade">
                        <div v-if="userMenuOpen" class="gs-user-dropdown" v-click-outside="() => userMenuOpen = false">
                            <Link :href="route('logout')" method="post" as="button"
                                class="gs-dropdown-item gs-dropdown-item-danger">
                                خروج
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </header>

        <!-- Sidebar Overlay -->
        <Transition name="gs-fade">
            <div v-if="sidebarOpen" class="gs-sidebar-overlay" @click="closeSidebar"></div>
        </Transition>

        <!-- Sidebar -->
        <Transition name="gs-slide">
            <aside v-if="sidebarOpen" class="gs-sidebar">
                <div class="gs-sidebar-header">
                    <span class="gs-label">منوی اصلی</span>
                    <button class="gs-icon-btn" @click="closeSidebar">✕</button>
                </div>

                <nav class="gs-sidebar-nav">
                    <div v-for="group in sidebarGroups" :key="group.title" class="gs-sidebar-group">
                        <span class="gs-sidebar-group-title">{{ group.title }}</span>
                        <Link v-for="item in group.items" :key="item.route" :href="route(item.route)"
                            :class="['gs-sidebar-link', { 'active': isActive(item.route) }]" @click="closeSidebar">
                            <span class="gs-sidebar-link-icon">{{ item.icon }}</span>
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </nav>

                <div class="gs-sidebar-footer">
                    <div class="gs-divider-gold"></div>
                    <p class="gs-label" style="text-align:center">GameShop v1.0</p>
                </div>
            </aside>
        </Transition>

        <!-- Main -->
        <main class="gs-main">
            <!-- Page Header (اختیاری) -->
            <div v-if="$slots.header" class="gs-page-header">
                <slot name="header" />
            </div>

            <!-- Content -->
            <div class="gs-content">
                <slot />
            </div>
        </main>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

// Theme
const isDark = ref(true)
const themeClass = computed(() => isDark.value ? '' : 'light')

function toggleTheme() {
    isDark.value = !isDark.value
    localStorage.setItem('gs-theme', isDark.value ? 'dark' : 'light')
}

onMounted(() => {
    const saved = localStorage.getItem('gs-theme')
    if (saved) isDark.value = saved === 'dark'
})

// Sidebar
const sidebarOpen = ref(false)
function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value }
function closeSidebar() { sidebarOpen.value = false }

// User Menu
const userMenuOpen = ref(false)
const userInitial = computed(() => {
    const name = page.props.auth?.user?.name ?? 'K'
    return name.charAt(0).toUpperCase()
})

// Active Route
function isActive(routeName) {
    return page.url.startsWith('/' + routeName.split('.')[0])
}

// Quick Actions (TopBar)
const quickActions = [
    { label: 'فروش', icon: '🧾', route: 'invoices.index' },
    { label: 'انبار', icon: '📦', route: 'items.index' },
    { label: 'گزارشات', icon: '📊', route: 'stats.daily' },
    { label: 'سرویس', icon: '🔧', route: 'service-jobs.index' },
]

// Sidebar Groups
const sidebarGroups = [
    {
        title: 'مدیریت',
        items: [
            { label: 'داشبورد', icon: '◈', route: 'dashboard' },
            { label: 'مشتریان', icon: '👤', route: 'customers.index' },
            { label: 'درخواست‌ها', icon: '📋', route: 'requests.index' },
        ],
    },
    {
        title: 'فروش',
        items: [
            { label: 'فاکتورها', icon: '🧾', route: 'invoices.index' },
            { label: 'اقلام سفارش', icon: '🛒', route: 'order-items.index' },
        ],
    },
    {
        title: 'انبار',
        items: [
            { label: 'محصولات', icon: '📦', route: 'items.index' },
            { label: 'دسته‌بندی‌ها', icon: '🗂', route: 'categories.index' },
            { label: 'گردش انبار', icon: '🔄', route: 'stock-movements.index' },
        ],
    },
    {
        title: 'سرویس',
        items: [
            { label: 'سرویس‌کارها', icon: '🔧', route: 'service-jobs.index' },
            { label: 'نوع سرویس', icon: '⚙️', route: 'service-types.index' },
        ],
    },
    {
        title: 'گزارشات',
        items: [
            { label: 'روزانه', icon: '📅', route: 'stats.daily' },
            { label: 'ماهانه', icon: '📆', route: 'stats.monthly' },
        ],
    },
]

// Click Outside Directive
const vClickOutside = {
    mounted(el, binding) {
        el._clickOutside = (e) => {
            if (!el.contains(e.target)) binding.value(e)
        }
        document.addEventListener('click', el._clickOutside)
    },
    unmounted(el) {
        document.removeEventListener('click', el._clickOutside)
    },
}
</script>

<style scoped>
/* ============================================================
   App Shell
   ============================================================ */
.gs-app {
    min-height: 100vh;
    background: var(--gs-bg);
    color: var(--gs-text-primary);
    font-family: 'IRANYekan', Tahoma, Arial, sans-serif;
}

/* ============================================================
   TopBar
   ============================================================ */
.gs-topbar {
    position: fixed;
    top: 0;
    right: 0;
    left: 0;
    z-index: 100;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.25rem;
    background: var(--gs-bg-card);
    border-bottom: 1px solid var(--gs-border);
    box-shadow: var(--gs-shadow-sm);
    gap: 1rem;
}

.gs-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.gs-topbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

/* ============================================================
   Hamburger
   ============================================================ */
.gs-hamburger {
    display: flex;
    flex-direction: column;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px;
    border-radius: 6px;
    transition: background var(--gs-transition);
}

.gs-hamburger:hover {
    background: var(--gs-gold-muted);
}

.gs-hamburger-line {
    display: block;
    width: 22px;
    height: 2px;
    background: var(--gs-text-secondary);
    border-radius: 2px;
    transition: all var(--gs-transition);
}

.gs-hamburger-line.open:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
    background: var(--gs-gold);
}

.gs-hamburger-line.open:nth-child(2) {
    opacity: 0;
}

.gs-hamburger-line.open:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
    background: var(--gs-gold);
}

/* ============================================================
   Brand
   ============================================================ */
.gs-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: var(--gs-text-primary);
}

.gs-brand-icon {
    font-size: 1.4rem;
    color: var(--gs-gold);
}

.gs-brand-name {
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}

/* ============================================================
   TopBar Nav
   ============================================================ */
.gs-topbar-nav {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex: 1;
    justify-content: center;
}

.gs-topbar-link {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gs-text-secondary);
    text-decoration: none;
    transition: all var(--gs-transition);
    border: 1px solid transparent;
}

.gs-topbar-link:hover {
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    border-color: var(--gs-border);
}

.gs-topbar-link.active {
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    border-color: var(--gs-border-hover);
}

.gs-topbar-link-icon {
    font-size: 1rem;
}

/* ============================================================
   Icon Button
   ============================================================ */
.gs-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: none;
    border: 1px solid var(--gs-border);
    cursor: pointer;
    color: var(--gs-text-secondary);
    font-size: 1rem;
    transition: all var(--gs-transition);
}

.gs-icon-btn:hover {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-hover);
    color: var(--gs-gold);
}

/* ============================================================
   User Menu
   ============================================================ */
.gs-user-menu {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    padding: 0.3rem 0.6rem;
    border-radius: 8px;
    border: 1px solid var(--gs-border);
    transition: all var(--gs-transition);
}

.gs-user-menu:hover {
    background: var(--gs-gold-muted);
    border-color: var(--gs-border-hover);
}

.gs-user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gs-gold) 0%, var(--gs-gold-dark) 100%);
    color: #0a0a0f;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.85rem;
}

.gs-user-name {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gs-text-primary);
}

.gs-user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    min-width: 150px;
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border);
    border-radius: 10px;
    box-shadow: var(--gs-shadow-md);
    overflow: hidden;
    z-index: 200;
}

.gs-dropdown-item {
    display: block;
    width: 100%;
    padding: 0.65rem 1rem;
    font-family: 'IRANYekan', Tahoma, Arial, sans-serif;
    font-size: 0.875rem;
    color: var(--gs-text-primary);
    background: none;
    border: none;
    text-align: right;
    cursor: pointer;
    transition: background var(--gs-transition);
    text-decoration: none;
}

.gs-dropdown-item:hover {
    background: var(--gs-bg-elevated);
}

.gs-dropdown-item-danger {
    color: var(--gs-error);
}

.gs-dropdown-item-danger:hover {
    background: rgba(224, 92, 92, 0.1);
}

/* ============================================================
   Sidebar Overlay
   ============================================================ */
.gs-sidebar-overlay {
    position: fixed;
    inset: 0;
    z-index: 150;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(2px);
}

/* ============================================================
   Sidebar
   ============================================================ */
.gs-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 200;
    width: var(--gs-sidebar-w);
    background: var(--gs-bg-card);
    border-left: 1px solid var(--gs-border);
    box-shadow: -4px 0 30px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.gs-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gs-border);
    margin-top: 60px;
}

.gs-sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem 0;
}

.gs-sidebar-group {
    margin-bottom: 0.5rem;
}

.gs-sidebar-group-title {
    display: block;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--gs-gold);
    letter-spacing: 0.1em;
    padding: 0.6rem 1.25rem 0.3rem;
    text-transform: uppercase;
}

.gs-sidebar-link {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 400;
    color: var(--gs-text-secondary);
    text-decoration: none;
    transition: all var(--gs-transition);
    border-right: 3px solid transparent;
}

.gs-sidebar-link:hover {
    background: var(--gs-gold-muted);
    color: var(--gs-text-primary);
    border-right-color: var(--gs-gold);
}

.gs-sidebar-link.active {
    background: var(--gs-gold-muted);
    color: var(--gs-gold);
    border-right-color: var(--gs-gold);
    font-weight: 500;
}

.gs-sidebar-link-icon {
    font-size: 1rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.gs-sidebar-footer {
    padding: 1rem 1.25rem;
}

/* ============================================================
   Main Content
   ============================================================ */
.gs-main {
    padding-top: 60px;
    min-height: 100vh;
}

.gs-page-header {
    padding: 1.25rem 1.5rem 0;
    border-bottom: 1px solid var(--gs-border);
    background: var(--gs-bg-soft);
}

.gs-content {
    padding: 1.5rem;
}

/* ============================================================
   Transitions
   ============================================================ */
.gs-fade-enter-active,
.gs-fade-leave-active {
    transition: opacity 0.2s ease;
}

.gs-fade-enter-from,
.gs-fade-leave-to {
    opacity: 0;
}

.gs-slide-enter-active,
.gs-slide-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.gs-slide-enter-from,
.gs-slide-leave-to {
    transform: translateX(100%);
}
</style>