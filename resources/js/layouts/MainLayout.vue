<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Sidebar from '../components/Sidebar.vue'
import Navbar from '../components/Navbar.vue'
import GlobalSearchModal from '../components/GlobalSearchModal.vue'

const route = useRoute()
const router = useRouter()
const mobileSidebarOpen = ref(false)
const showSearch = ref(false)

const isActive = (path) => {
    if (path === '/documents') {
        return route.path === '/documents' && !route.query.type
    }
    if (path.includes('?')) {
        const [basePath, queryString] = path.split('?')
        if (!route.path.startsWith('/documents')) return false
        const params = new URLSearchParams(queryString)
        for (const [key, val] of params.entries()) {
            if (decodeURIComponent(route.query[key] || '') !== decodeURIComponent(val || '')) return false
        }
        return true
    }
    return route.path === path || route.path.startsWith(path + '/')
}

const sidebarPaths = [
    '/dashboard',
    '/documents',
    '/documents?type=Quotation',
    '/documents?type=Proforma%20Invoice',
    '/documents?type=Invoice',
    '/documents?type=Delivery%20Slip',
    '/documents?type=Delivery%20Address',
    '/documents?type=Purchase%20Order',
    '/companies',
    '/partners',
    '/products',
    '/settings'
]

const handleGlobalKeydown = (e) => {
    // 1. CTRL + K: toggle global search modal
    if (e.ctrlKey && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        showSearch.value = !showSearch.value
        return
    }

    // Don't intercept other shortcuts if the global search modal is open
    if (showSearch.value) return

    // 2. CTRL + ArrowUp / ArrowDown: navigasi sidebar
    if (e.ctrlKey && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        e.preventDefault()
        const activeIndex = sidebarPaths.findIndex(path => isActive(path))
        let nextIndex = 0
        if (activeIndex !== -1) {
            if (e.key === 'ArrowDown') {
                nextIndex = (activeIndex + 1) % sidebarPaths.length
            } else {
                nextIndex = (activeIndex - 1 + sidebarPaths.length) % sidebarPaths.length
            }
        }
        router.push(sidebarPaths[nextIndex])
        return
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown)
})
</script>

<template>
    <div class="flex h-screen bg-odoo-gray-bg">
        <!-- Mobile overlay -->
        <Transition name="fade">
            <div v-if="mobileSidebarOpen" class="fixed inset-0 bg-black/40 z-20 lg:hidden" @click="mobileSidebarOpen = false"></div>
        </Transition>

        <!-- Mobile sidebar -->
        <Transition name="slide">
            <div v-if="mobileSidebarOpen" class="fixed inset-y-0 left-0 z-30 lg:hidden">
                <Sidebar />
            </div>
        </Transition>

        <!-- Desktop sidebar -->
        <div class="hidden lg:flex">
            <Sidebar />
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <Navbar @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen" @open-search="showSearch = true" />
            <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-odoo-gray-bg">
                <router-view />
            </main>
        </div>

        <!-- Global Search Modal -->
        <GlobalSearchModal :show="showSearch" @close="showSearch = false" />
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
.slide-enter-active, .slide-leave-active {
    transition: transform 0.3s ease;
}
.slide-enter-from, .slide-leave-to {
    transform: translateX(-100%);
}
</style>
