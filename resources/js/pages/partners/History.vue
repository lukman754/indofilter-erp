<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { partners as partnersApi } from '../../api/index.js'

const route = useRoute()
const router = useRouter()

const partner = ref(null)
const loading = ref(false)
const search = ref('')
const error = ref('')

async function fetchPartner() {
    loading.value = true
    try {
        const res = await partnersApi.get(route.params.id)
        partner.value = res.data.data || res.data
    } catch (e) {
        error.value = 'Gagal memuat riwayat dokumen partner'
    } finally {
        loading.value = false
    }
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

function getStatusClass(status) {
    switch (status?.toLowerCase()) {
        case 'draft':
            return 'bg-gray-100 text-gray-700'
        case 'confirmed':
            return 'bg-blue-100 text-blue-700'
        case 'sent':
            return 'bg-purple-100 text-purple-700'
        case 'paid':
            return 'bg-green-100 text-green-700'
        case 'cancelled':
            return 'bg-red-100 text-red-700'
        default:
            return 'bg-gray-50 text-gray-600'
    }
}

const filteredDocuments = computed(() => {
    if (!partner.value?.documents) return []
    const q = search.value.toLowerCase().trim()
    if (!q) return partner.value.documents
    
    return partner.value.documents.filter(doc => {
        const docNo = (doc.document_number || '').toLowerCase()
        const docType = (doc.type || '').replace(/_/g, ' ').toLowerCase()
        const dateStr = formatDate(doc.date).toLowerCase()
        const totalStr = formatMoney(doc.grand_total).toLowerCase()
        const statusStr = (doc.status || '').toLowerCase()
        
        return docNo.includes(q) || 
               docType.includes(q) || 
               dateStr.includes(q) ||
               totalStr.includes(q) ||
               statusStr.includes(q)
    })
})

onMounted(fetchPartner)
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push('/partners')" class="p-1.5 hover:bg-gray-100 rounded-lg text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <span>Riwayat Dokumen Partner</span>
                        <span v-if="partner" class="text-xs px-2.5 py-0.5 rounded-full font-semibold" :class="partner.type === 'customer' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'">
                            {{ partner.type === 'customer' ? 'Customer' : 'Vendor' }}
                        </span>
                    </h2>
                    <p class="text-sm text-gray-500" v-if="partner">{{ partner.name }} <span v-if="partner.alias" class="text-gray-400">({{ partner.alias }})</span></p>
                </div>
            </div>
            
            <div class="flex items-center gap-2" v-if="partner">
                <input v-model="search" type="text" placeholder="Cari dokumen..." class="w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-20 bg-white rounded-lg border border-gray-200 shadow-sm">
            <svg class="animate-spin h-8 w-8 text-indofilter" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
            {{ error }}
        </div>

        <div v-else class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">No. Dokumen</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Tipe Dokumen</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Grand Total</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="doc in filteredDocuments" :key="doc.id" class="hover:bg-gray-50/70 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-indofilter">
                                <router-link :to="{ path: '/documents/' + doc.id, query: { type: doc.type } }" class="hover:underline">
                                    {{ doc.document_number || `Draft #${doc.id}` }}
                                </router-link>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap uppercase tracking-wider text-xs font-semibold text-gray-600">
                                {{ doc.type?.replace(/_/g, ' ').toUpperCase() || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ formatDate(doc.date) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900 font-bold">
                                {{ doc.type === 'delivery_slip' ? '-' : formatMoney(doc.grand_total) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full uppercase" :class="getStatusClass(doc.status)">
                                    {{ doc.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="filteredDocuments.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-gray-400 italic">
                                Belum ada dokumen yang dibuat untuk partner ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
