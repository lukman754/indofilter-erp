<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { documents as api } from '../../api/index.js'
import { useAppStore } from '../../stores/app.js'
import DataTable from '../../components/DataTable.vue'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()

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

// Sync activeType with URL query parameter changes (e.g., when navigating via sidebar tabs)
watch(() => route.query.type, (newVal) => {
    const val = newVal || ''
    if (activeType.value !== val) {
        activeType.value = val
    }
})

const poFileInput = ref(null);
const currentUploadDocId = ref(null);

function triggerPoUpload(id) {
    currentUploadDocId.value = id;
    if (poFileInput.value) {
        poFileInput.value.click();
    }
}

function showConfirm(title, message, onConfirm, onCancel = null) {
    appStore.showConfirm(title, message, onConfirm, onCancel)
}

function showNotification(title, message, type = 'success') {
    appStore.showNotification(title, message, type)
}

async function onPoFileSelected(e) {
    const file = e.target.files[0];
    if (!file || !currentUploadDocId.value) return;

    const formData = new FormData();
    formData.append('customer_po_file', file);

    try {
        await api.uploadPo(currentUploadDocId.value, formData);
        showNotification('Sukses', 'File PO Customer berhasil diunggah!', 'success')
        await fetchData();
    } catch (err) {
        showNotification('Gagal', 'Gagal mengunggah file PO: ' + (err.response?.data?.message || err.message), 'error')
    } finally {
        e.target.value = '';
        currentUploadDocId.value = null;
    }
}

async function viewPoFile(id) {
    try {
        const response = await api.downloadPo(id);
        const blob = new Blob([response.data], { type: response.headers['content-type'] });
        const url = window.URL.createObjectURL(blob);
        window.open(url, '_blank');
    } catch (e) {
        showNotification('Gagal Membuka PO', 'Gagal menampilkan file PO: ' + (e.response?.data?.message || e.message), 'error')
    }
}

async function executeConfirm(id, overwrite) {
    try {
        await api.confirm(id, { overwrite })
        await fetchData()
        showNotification('Sukses', 'Dokumen berhasil dikonfirmasi.', 'success')
    } catch (e) {
        showNotification('Gagal', 'Gagal mengkonfirmasi dokumen.', 'error')
    }
}

async function handleConfirm(id) {
    showConfirm(
        'Konfirmasi Dokumen',
        'Apakah Anda yakin ingin mengkonfirmasi dokumen ini?',
        async () => {
            const doc = items.value.find(d => d.id === id)
            let overwrite = false
            if (doc && doc.document_number) {
                try {
                    const checkRes = await api.checkLocalFile({ number: doc.document_number })
                    if (checkRes.data && checkRes.data.path_configured && checkRes.data.exists) {
                        showConfirm(
                            'Berkas Sudah Ada',
                            `Berkas "${checkRes.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                            async () => {
                                await executeConfirm(id, true)
                            }
                        )
                        return
                    }
                } catch (e) {
                    console.error("Gagal memeriksa berkas lokal", e)
                }
            }
            await executeConfirm(id, false)
        }
    )
}

async function handleCancel(id) {
    showConfirm(
        'Batalkan Dokumen',
        'Apakah Anda yakin ingin membatalkan dokumen ini?',
        async () => {
            try {
                await api.cancel(id)
                await fetchData()
                showNotification('Sukses', 'Dokumen berhasil dibatalkan.', 'success')
            } catch (e) {
                showNotification('Gagal', 'Gagal membatalkan dokumen.', 'error')
            }
        }
    )
}

async function handleDelete(id) {
    showConfirm(
        'Hapus Dokumen',
        'Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.',
        async () => {
            try {
                await api.delete(id)
                await fetchData()
                showNotification('Sukses', 'Dokumen berhasil dihapus.', 'success')
            } catch (e) {
                showNotification('Gagal', 'Gagal menghapus dokumen.', 'error')
            }
        }
    )
}

async function handleExport(id) {
    try {
        let res = await api.export(id, { overwrite: false });
        
        if (res.data && res.data.exists) {
            showConfirm(
                'Berkas Sudah Ada',
                `Berkas "${res.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                async () => {
                    try {
                        const overwriteRes = await api.export(id, { overwrite: true });
                        if (overwriteRes.data && overwriteRes.data.success) {
                            showNotification('Ekspor Berhasil', overwriteRes.data.message, 'success')
                        } else {
                            showNotification('Ekspor Gagal', overwriteRes.data.message || 'Gagal mengekspor dokumen', 'error')
                        }
                    } catch (err) {
                        showNotification('Ekspor Gagal', err.response?.data?.message || 'Gagal mengekspor dokumen', 'error')
                    }
                }
            )
            return;
        }
        
        if (res.data && res.data.success) {
            showNotification('Ekspor Berhasil', res.data.message, 'success')
        } else {
            showNotification('Ekspor Gagal', res.data.message || 'Gagal mengekspor dokumen', 'error')
        }
    } catch (e) {
        showNotification('Ekspor Gagal', e.response?.data?.message || 'Gagal mengekspor dokumen', 'error')
    }
}

const searchInput = ref(null)

const handleIndexKeydown = (e) => {
    if (e.ctrlKey && e.key.toLowerCase() === 'f') {
        e.preventDefault()
        if (searchInput.value) {
            searchInput.value.focus()
            searchInput.value.select()
        }
    }
    if (e.ctrlKey && e.key.toLowerCase() === 'n') {
        e.preventDefault()
        router.push({ path: '/documents/create', query: { type: activeType.value || 'Quotation' } })
    }
}

onMounted(async () => {
    window.addEventListener('keydown', handleIndexKeydown)
    if (route.query.search) {
        search.value = route.query.search
    }
    await fetchData()
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleIndexKeydown)
})

watch(() => route.query.search, (newVal) => {
    search.value = newVal || ''
})
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h1 class="text-lg font-bold text-gray-800">
                    {{ activeType ? activeType : 'Semua Dokumen' }}
                </h1>
            </div>
            <button @click="router.push({ path: '/documents/create', query: { type: activeType || 'Quotation' } })" class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Baru
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <input ref="searchInput" v-model="search" type="text" placeholder="Cari dokumen..." class="w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
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
                        <!-- View PO File -->
                        <button
                            v-if="(row.type === 'invoice' || row.type === 'proforma_invoice') && row.customer_po_file"
                            @click="viewPoFile(row.id)"
                            class="text-blue-500 hover:text-blue-700 transition-colors"
                            title="Lihat File PO Customer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <!-- Upload PO File -->
                        <button
                            v-if="row.type === 'invoice' || row.type === 'proforma_invoice'"
                            @click="triggerPoUpload(row.id)"
                            class="text-teal-600 hover:text-teal-800 transition-colors"
                            title="Unggah File PO Customer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
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
            
            <!-- Hidden file input for PO upload -->
            <input
                type="file"
                ref="poFileInput"
                style="display: none"
                accept=".pdf,image/*"
                @change="onPoFileSelected"
            />
        </div>
    </div>
</template>
