<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { documents as api } from '../../api/index.js'
import DataTable from '../../components/DataTable.vue'

const router = useRouter()
const route = useRoute()

const items = ref([])
const loading = ref(false)
const search = ref('')
const activeType = ref(route.query.type || '')
const activeStatus = ref('')

const documentTypes = [
    { value: '', label: 'Semua' },
    { value: 'Quotation', label: 'Quotation' },
    { value: 'Proforma Invoice', label: 'Proforma Invoice' },
    { value: 'Invoice', label: 'Invoice' },
    { value: 'Delivery Slip', label: 'Surat Jalan' },
    { value: 'Delivery Address', label: 'Alamat Kirim' },
    { value: 'Purchase Order', label: 'Purchase Order' },
]

const statuses = [
    { value: '', label: 'Semua Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'canceled', label: 'Canceled' },
]

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    confirmed: 'bg-green-100 text-green-700',
    canceled: 'bg-red-100 text-red-600',
}

const columns = [
    { key: 'number', label: 'No. Dokumen' },
    { key: 'document_type', label: 'Tipe' },
    { key: 'reference', label: 'Referensi' },
    { key: 'partner_name', label: 'Partner' },
    { key: 'date', label: 'Tanggal' },
    { key: 'status', label: 'Status' },
    { key: 'grand_total', label: 'Total' },
    { key: 'actions', label: 'Aksi' },
]

const filteredItems = computed(() => {
    let result = items.value
    if (search.value) {
        const s = search.value.toLowerCase()
        result = result.filter(r => (r.number?.toLowerCase().includes(s) || r.partner?.name?.toLowerCase().includes(s)))
    }
    if (activeType.value) result = result.filter(r => r.document_type === activeType.value)
    if (activeStatus.value) result = result.filter(r => r.status === activeStatus.value)
    return result
})

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

async function fetchData() {
    loading.value = true
    try {
        const params = {}
        if (activeType.value) params.type = activeType.value
        if (activeStatus.value) params.status = activeStatus.value
        const res = await api.list(params)
        items.value = res.data.data || res.data || []
    } catch (e) {
        items.value = []
    } finally {
        loading.value = false
    }
}

watch([activeType, activeStatus], fetchData)

watch(activeType, (newVal) => {
    if (route.query.type !== (newVal || undefined)) {
        router.replace({ query: { ...route.query, type: newVal || undefined } })
    }
})

watch(() => route.query.type, (newVal) => {
    const val = newVal || ''
    if (activeType.value !== val) {
        activeType.value = val
    }
})

async function handleConfirm(id) {
    if (!confirm('Konfirmasi dokumen ini?')) return
    try {
        await api.confirm(id)
        await fetchData()
    } catch (e) {
        alert('Gagal mengkonfirmasi dokumen')
    }
}

async function handleCancel(id) {
    if (!confirm('Batalkan dokumen ini?')) return
    try {
        await api.cancel(id)
        await fetchData()
    } catch (e) {
        alert('Gagal membatalkan dokumen')
    }
}

async function handleDelete(id) {
    if (!confirm('Yakin ingin menghapus dokumen ini?')) return
    try {
        await api.delete(id)
        await fetchData()
    } catch (e) {
        alert('Gagal menghapus dokumen')
    }
}

async function handleExport(id) {
    try {
        const doc = items.value.find(item => item.id === id)
        const filename = doc && doc.number ? `${doc.number.replace(/[\/\\]/g, '-')}.docx` : `dokumen-${id}.docx`
        const res = await api.export(id)
        const url = window.URL.createObjectURL(new Blob([res.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', filename)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
    } catch (e) {
        alert('Gagal mengexport dokumen')
    }
}

onMounted(fetchData)
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <button v-for="t in documentTypes" :key="t.value" @click="activeType = t.value" class="px-3 py-1.5 text-sm rounded-lg transition-colors" :class="activeType === t.value ? 'bg-indofilter text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'">
                    {{ t.label }}
                </button>
            </div>
            <button @click="router.push({ path: '/documents/create', query: { type: activeType } })" class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Baru
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <input v-model="search" type="text" placeholder="Cari dokumen..." class="w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
            <select v-model="activeStatus" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div class="bg-white rounded-lg border border-gray-200">
            <DataTable :columns="columns" :data="filteredItems" :loading="loading" empty-message="Belum ada dokumen">
                <template #cell-document_type="{ value }">
                    {{ value?.replace(/_/g, ' ') }}
                </template>
                <template #cell-reference="{ row }">
                    <router-link v-if="row.reference" :to="{ path: '/documents/' + row.reference.id, query: { type: row.reference.document_type } }" class="text-xs font-semibold text-indofilter hover:underline">
                        {{ row.reference.document_number || `Draft #${row.reference.id}` }}
                    </router-link>
                    <span v-else class="text-gray-400">-</span>
                </template>
                <template #cell-partner_name="{ row }">
                    {{ row.partner?.name || '-' }}
                </template>
                <template #cell-date="{ value }">
                    {{ formatDate(value) }}
                </template>
                <template #cell-status="{ value }">
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full" :class="statusColors[value] || 'bg-gray-100 text-gray-600'">
                        {{ value }}
                    </span>
                </template>
                <template #cell-grand_total="{ value }">
                    <span class="font-medium">{{ formatMoney(value) }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <button @click="router.push({ path: '/documents/' + row.id, query: { type: row.document_type } })" class="text-gray-500 hover:text-gray-700 transition-colors" title="Lihat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                        <button v-if="row.status === 'draft' || row.status === 'confirmed'" @click="router.push({ path: '/documents/' + row.id + '/edit', query: { type: row.document_type } })" class="text-blue-600 hover:text-blue-800 transition-colors" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button v-if="row.status === 'draft'" @click="handleConfirm(row.id)" class="text-green-600 hover:text-green-800 transition-colors" title="Konfirmasi">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                        <button v-if="row.status === 'draft'" @click="handleCancel(row.id)" class="text-orange-500 hover:text-orange-700 transition-colors" title="Batalkan">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <button @click="handleExport(row.id)" class="text-purple-500 hover:text-purple-700 transition-colors" title="Export DOCX">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>
                        <button @click="handleDelete(row.id)" class="text-red-500 hover:text-red-700 transition-colors" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>
