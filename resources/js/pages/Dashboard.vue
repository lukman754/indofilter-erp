<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { dashboard, documents as docsApi } from '../api/index.js'

const router = useRouter()
const stats = ref(null)
const recentDocs = ref([])
const loading = ref(true)

const statCards = [
    { key: 'quotation', label: 'Quotations', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { key: 'invoice', label: 'Invoice', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { key: 'delivery_slip', label: 'Surat Jalan', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    { key: 'purchase_order', label: 'Purchase Order', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z' },
]

const statusColors = { draft: 'bg-gray-100 text-gray-600', confirmed: 'bg-green-100 text-green-700', canceled: 'bg-red-100 text-red-600' }

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

async function fetchDashboard() {
    loading.value = true
    try {
        const [statsRes, docsRes] = await Promise.all([
            dashboard.stats(),
            docsApi.list({ limit: 10, sort: 'created_at', order: 'desc' }),
        ])
        stats.value = statsRes.data
        recentDocs.value = docsRes.data.data || docsRes.data || []
    } catch (e) {
        stats.value = {}
        recentDocs.value = []
    } finally {
        loading.value = false
    }
}

onMounted(fetchDashboard)
</script>

<template>
    <div>
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg class="animate-spin h-8 w-8 text-indofilter" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <template v-if="!loading">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div v-for="card in statCards" :key="card.key" class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">{{ card.label }}</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ stats?.[card.key] || 0 }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-6 h-6 text-indofilter" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="flex flex-wrap gap-3 mb-6">
                <button @click="router.push('/documents/create')" class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Buat Dokumen Baru
                </button>
            </div>

            <!-- Recent Documents -->
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-800">Dokumen Terbaru</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dokumen</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Partner</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="recentDocs.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada dokumen</td>
                            </tr>
                            <tr v-for="(doc, i) in recentDocs" :key="doc.id" class="hover:bg-blue-50 transition-colors" :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ doc.number || doc.id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ doc.document_type?.replace(/_/g, ' ') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ doc.partner?.name || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(doc.date) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full" :class="statusColors[doc.status] || 'bg-gray-100 text-gray-600'">
                                        {{ doc.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right font-medium">{{ formatMoney(doc.grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
