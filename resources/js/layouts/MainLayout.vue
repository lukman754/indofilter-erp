<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Sidebar from '../components/Sidebar.vue'
import Navbar from '../components/Navbar.vue'
import GlobalSearchModal from '../components/GlobalSearchModal.vue'
import { useAppStore } from '../stores/app.js'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
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

        <!-- Global Alert / Confirm Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="appStore.confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/40 backdrop-blur-sm transition-opacity" @click="appStore.closeConfirmModal(false)"></div>
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transition-all overflow-hidden border border-gray-100">
                        <!-- Icon & Content -->
                        <div class="p-6 flex flex-col items-center text-center">
                            <!-- Success Icon -->
                            <div v-if="appStore.confirmModal.type === 'success'" class="w-12 h-12 bg-green-50 text-green-600 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <!-- Error Icon -->
                            <div v-else-if="appStore.confirmModal.type === 'error'" class="w-12 h-12 bg-red-50 text-red-600 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                            <!-- Confirm / Warning Icon -->
                            <div v-else class="w-12 h-12 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>

                            <h3 class="text-lg font-bold text-gray-800 mb-2">{{ appStore.confirmModal.title }}</h3>
                            <p class="text-sm text-gray-500 whitespace-pre-wrap leading-relaxed">{{ appStore.confirmModal.message }}</p>
                        </div>

                        <!-- Footer Actions -->
                        <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-end gap-3">
                            <button
                                v-if="appStore.confirmModal.cancelText"
                                @click="appStore.closeConfirmModal(false)"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 bg-white hover:bg-gray-100 border border-gray-200 rounded-lg transition-colors cursor-pointer"
                            >
                                {{ appStore.confirmModal.cancelText }}
                            </button>
                            <button
                                @click="appStore.closeConfirmModal(true)"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg shadow-sm transition-colors cursor-pointer"
                                :class="appStore.confirmModal.type === 'error' ? 'bg-red-600 hover:bg-red-700' : (appStore.confirmModal.type === 'success' ? 'bg-green-600 hover:bg-green-700' : 'bg-indofilter hover:bg-indofilter-dark')"
                            >
                                {{ appStore.confirmModal.confirmText }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
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
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-active > div:last-child, .modal-leave-active > div:last-child {
    transition: transform 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-from > div:last-child, .modal-leave-to > div:last-child {
    transform: scale(0.95);
}
</style>
