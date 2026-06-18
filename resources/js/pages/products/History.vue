<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { products as productsApi } from '../../api/index.js'

const route = useRoute()
const router = useRouter()

const product = ref(null)
const loading = ref(false)
const search = ref('')
const error = ref('')

async function fetchProduct() {
    loading.value = true
    try {
        const res = await productsApi.get(route.params.id)
        product.value = res.data.data || res.data
    } catch (e) {
        error.value = 'Gagal memuat riwayat produk'
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

const filteredItems = computed(() => {
    if (!product.value?.document_items) return []
    const q = search.value.toLowerCase().trim()
    if (!q) return product.value.document_items
    
    return product.value.document_items.filter(item => {
        const docNo = (item.document?.document_number || '').toLowerCase()
        const docType = (item.document?.type || '').replace(/_/g, ' ').toLowerCase()
        const partnerName = (item.document?.partner?.name || '').toLowerCase()
        const dateStr = formatDate(item.document?.date).toLowerCase()
        const qtyStr = String(item.qty).toLowerCase()
        const priceStr = String(item.unit_price).toLowerCase()
        
        return docNo.includes(q) || 
               docType.includes(q) || 
               partnerName.includes(q) || 
               dateStr.includes(q) ||
               qtyStr.includes(q) ||
               priceStr.includes(q)
    })
})

onMounted(fetchProduct)
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push('/products')" class="p-1.5 hover:bg-gray-100 rounded-lg text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <span>Riwayat Penggunaan</span>
                        <span v-if="product" class="text-xs bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full font-semibold">{{ product.code }}</span>
                    </h2>
                    <p class="text-sm text-gray-500" v-if="product">{{ product.name }}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2" v-if="product">
                <input v-model="search" type="text" placeholder="Cari riwayat..." class="w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
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
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Partner</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Harga</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-gray-50/70 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-indofilter">
                                <router-link v-if="item.document" :to="{ path: '/documents/' + item.document.id, query: { type: item.document.document_type } }" class="hover:underline">
                                    {{ item.document.document_number || `Draft #${item.document.id}` }}
                                </router-link>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap uppercase tracking-wider text-xs font-semibold text-gray-600">
                                {{ item.document?.type?.replace(/_/g, ' ').toUpperCase() || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ item.document?.partner?.name || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                {{ formatDate(item.document?.date) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700 font-medium">
                                {{ item.qty }} {{ item.uom }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700 font-medium">
                                {{ formatMoney(item.unit_price) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900 font-bold">
                                {{ formatMoney(item.total) }}
                            </td>
                        </tr>
                        <tr v-if="filteredItems.length === 0">
                            <td colspan="7" class="px-6 py-10 text-center text-gray-400 italic">
                                Tidak ada data riwayat yang cocok atau ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
