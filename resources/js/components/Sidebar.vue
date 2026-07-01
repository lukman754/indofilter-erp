<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../stores/app.js'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

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

const menuItems = [
    { path: '/dashboard', label: 'Dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { path: '/documents', label: 'Daftar Dokumen', icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
    {
        label: 'Penjualan', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        children: [
            { path: '/documents?type=Quotation', label: 'Quotations', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
            { path: '/documents?type=Proforma%20Invoice', label: 'Proforma Invoice', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { path: '/documents?type=Invoice', label: 'Invoice', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        ],
    },
    {
        label: 'Inventaris', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        children: [
            { path: '/documents?type=Delivery%20Slip', label: 'Surat Jalan', icon: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1' },
            { path: '/documents?type=Delivery%20Address', label: 'Alamat Kirim', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
        ],
    },
    {
        label: 'Pembelian', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z',
        children: [
            { path: '/documents?type=Purchase%20Order', label: 'Purchase Order', icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z' },
        ],
    },
    {
        label: 'Master Data', icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
        children: [
            { path: '/companies', label: 'Perusahaan', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5' },
            { path: '/partners', label: 'Partner', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
            { path: '/products', label: 'Produk', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
        ],
    },
    { path: '/settings', label: 'Pengaturan', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]

const collapsed = computed(() => appStore.sidebarCollapsed)

function handleNav(item) {
    if (item.children) return
    router.push(item.path)
}

function navigateTo(path) {
    router.push(path)
}
</script>

<template>
    <aside class="bg-odoo-sidebar text-gray-300 flex flex-col transition-all duration-300 select-none" :class="collapsed ? 'w-16' : 'w-56'">
        <div class="h-14 flex items-center px-4 border-b border-gray-700">
            <div v-if="!collapsed" class="flex items-center gap-2">
                <div class="w-7 h-7 rounded bg-indofilter flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <span class="font-bold text-white text-sm">Indofilter ERP</span>
            </div>
            <div v-else class="mx-auto">
                <div class="w-7 h-7 rounded bg-indofilter flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-2 text-xs-custom">
            <div v-for="item in menuItems" :key="item.label" class="mb-1">
                <div v-if="!item.children" class="px-2">
                    <button @click="handleNav(item)" 
                        class="w-full flex items-center rounded-custom transition-colors" 
                        :class="[
                            isActive(item.path) ? 'bg-odoo-brand text-white font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white',
                            collapsed ? 'justify-center py-2 px-0' : 'gap-3 px-3 py-2'
                        ]"
                        :title="item.label"
                    >
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                        </svg>
                        <span v-if="!collapsed">{{ item.label }}</span>
                    </button>
                </div>
                <div v-else class="space-y-1">
                    <div class="flex items-center gap-3 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider" :class="collapsed ? 'justify-center px-0' : 'px-4'">
                        <span v-if="!collapsed" class="whitespace-nowrap">{{ item.label }}</span>
                        <hr v-else class="w-8 border-gray-700 mx-auto" />
                    </div>
                    <div class="space-y-0.5 px-2">
                        <button v-for="child in item.children" :key="child.label" @click="navigateTo(child.path)" 
                            class="w-full flex items-center rounded-custom transition-colors text-left" 
                            :class="[
                                isActive(child.path) ? 'bg-odoo-brand text-white font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white',
                                collapsed ? 'justify-center py-2 px-0' : 'gap-3 px-4 py-2 text-xs-custom'
                            ]"
                            :title="child.label"
                        >
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="child.icon"></path>
                            </svg>
                            <span v-if="!collapsed" class="whitespace-nowrap">{{ child.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-700 p-2">
            <button @click="appStore.toggleSidebar()" class="w-full flex items-center gap-3 px-3 py-2 rounded-custom text-gray-500 hover:text-white hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="collapsed ? 'M13 5l7 7-7 7M5 5l7 7-7 7' : 'M11 19l-7-7 7-7m8 14l-7-7 7-7'"></path>
                </svg>
                <span v-if="!collapsed" class="text-xs">Ciutkan Menu</span>
            </button>
        </div>
    </aside>
</template>
