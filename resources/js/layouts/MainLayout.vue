<script setup>
import { ref } from 'vue'
import Sidebar from '../components/Sidebar.vue'
import Navbar from '../components/Navbar.vue'

const mobileSidebarOpen = ref(false)
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
            <Navbar @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen" />
            <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-odoo-gray-bg">
                <router-view />
            </main>
        </div>
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
