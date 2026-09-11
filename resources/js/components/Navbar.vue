<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '../stores/app.js'

const route = useRoute()
const appStore = useAppStore()

const pageTitle = computed(() => {
    const name = route.name
    const titles = {
        dashboard: 'Dashboard',
        companies: 'Perusahaan',
        partners: 'Partner',
        products: 'Produk',
        documents: 'Dokumen',
        'documents.create': 'Buat Dokumen',
        'documents.edit': 'Edit Dokumen',
        'documents.show': 'Detail Dokumen',
        settings: 'Pengaturan',
        users: 'Pengguna',
        reports: 'Laporan',
    }
    return titles[name] || 'Indofilter ERP'
})

const emit = defineEmits(['toggle-sidebar', 'open-search'])
</script>

<template>
    <header class="h-12 bg-white border-b border-gray-200 flex items-center justify-between px-4 z-10 select-none">
        <div class="flex items-center gap-2 text-sm overflow-hidden whitespace-nowrap">
            <button class="lg:hidden p-1 text-gray-500 hover:text-gray-700" @click="emit('toggle-sidebar')">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <span class="text-odoo-brand font-semibold cursor-pointer">Indofilter ERP</span>
            <span class="text-gray-400">/</span>
            <span class="text-gray-600 font-medium truncate">{{ pageTitle }}</span>
        </div>

        <!-- Company Switcher -->
        <div v-if="appStore.companies && appStore.companies.length > 0" class="flex items-center bg-gray-100 rounded-lg p-0.5 border border-gray-200">
            <button
                v-for="company in appStore.companies"
                :key="company.id"
                @click="appStore.setActiveCompany(company.id)"
                :class="[
                    'px-4 py-1 text-xs font-bold rounded-md transition-all duration-200 uppercase tracking-wider',
                    appStore.activeCompanyId === company.id
                        ? 'bg-indofilter text-white shadow-sm'
                        : 'text-gray-500 hover:text-gray-800 hover:bg-gray-200/50'
                ]"
            >
                {{ company.alias || company.name.substring(0, 3) }}
            </button>
        </div>

        <div class="flex items-center gap-3">
            <button 
                @click="emit('open-search')" 
                class="flex items-center gap-1.5 px-2.5 py-1 text-xs text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-colors cursor-pointer"
                title="Cari Global (Ctrl + K)"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <span class="hidden md:inline text-[10px] font-semibold bg-white border border-gray-200 px-1 rounded shadow-2xs">Ctrl + K</span>
            </button>
        </div>
    </header>
</template>
