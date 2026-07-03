<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { documents as api } from '../../api/index.js'
import { useAppStore } from '../../stores/app.js'

const route = useRoute()
const router = useRouter()
const doc = ref(null)
const loading = ref(true)
const error = ref('')
const appStore = useAppStore()

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    confirmed: 'bg-green-100 text-green-700',
    canceled: 'bg-red-100 text-red-600',
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })
}

function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

async function loadDocument() {
    loading.value = true
    error.value = ''
    try {
        const res = await api.get(route.params.id)
        doc.value = res.data.data || res.data
        if (!route.query.type && doc.value) {
            router.replace({ query: { ...route.query, type: doc.value.document_type || doc.value.type } })
        }
    } catch (e) {
        error.value = 'Gagal memuat dokumen'
    } finally {
        loading.value = false
    }
}

function showConfirm(title, message, onConfirm, onCancel = null) {
    appStore.showConfirm(title, message, onConfirm, onCancel)
}

function showNotification(title, message, type = 'success') {
    appStore.showNotification(title, message, type)
}

async function executeConfirm(overwrite) {
    try {
        await api.confirm(route.params.id, { overwrite })
        await loadDocument()
        showNotification('Sukses', 'Dokumen berhasil dikonfirmasi.', 'success')
    } catch (e) {
        showNotification('Gagal', 'Gagal mengkonfirmasi dokumen.', 'error')
    }
}

async function handleConfirm() {
    showConfirm(
        'Konfirmasi Dokumen',
        'Apakah Anda yakin ingin mengkonfirmasi dokumen ini?',
        async () => {
            let overwrite = false
            if (doc.value && doc.value.document_number) {
                try {
                    const checkRes = await api.checkLocalFile({ number: doc.value.document_number })
                    if (checkRes.data && checkRes.data.path_configured && checkRes.data.exists) {
                        showConfirm(
                            'Berkas Sudah Ada',
                            `Berkas "${checkRes.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                            async () => {
                                await executeConfirm(true)
                            }
                        )
                        return
                    }
                } catch (e) {
                    console.error("Gagal memeriksa berkas lokal", e)
                }
            }
            await executeConfirm(false)
        }
    )
}

async function handleCancel() {
    showConfirm(
        'Batalkan Dokumen',
        'Apakah Anda yakin ingin membatalkan dokumen ini?',
        async () => {
            try {
                await api.cancel(route.params.id)
                await loadDocument()
                showNotification('Sukses', 'Dokumen berhasil dibatalkan.', 'success')
            } catch (e) {
                showNotification('Gagal', 'Gagal membatalkan dokumen.', 'error')
            }
        }
    )
}

async function handleDelete() {
    showConfirm(
        'Hapus Dokumen',
        'Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.',
        async () => {
            try {
                await api.delete(route.params.id)
                router.push({ path: '/documents', query: { type: doc.value?.document_type } })
            } catch (e) {
                showNotification('Gagal', 'Gagal menghapus dokumen.', 'error')
            }
        }
    )
}

async function handleExport() {
    try {
        let res = await api.export(route.params.id, { overwrite: false });
        
        if (res.data && res.data.exists) {
            showConfirm(
                'Berkas Sudah Ada',
                `Berkas "${res.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                async () => {
                    try {
                        const overwriteRes = await api.export(route.params.id, { overwrite: true });
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

async function handleViewPoFile() {
    if (!doc.value || !doc.value.id) return;
    try {
        const response = await api.downloadPo(doc.value.id);
        const blob = new Blob([response.data], { type: response.headers['content-type'] });
        const url = window.URL.createObjectURL(blob);
        window.open(url, '_blank');
    } catch (e) {
        showNotification('Gagal Membuka PO', 'Gagal menampilkan file PO: ' + (e.response?.data?.message || e.message), 'error')
    }
}

onMounted(loadDocument)
</script>

<template>
    <div>
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg class="animate-spin h-8 w-8 text-indofilter" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>

        <template v-if="doc">
            <div class="flex items-center justify-between mb-6">
                <button @click="router.push({ path: '/documents', query: { type: doc.document_type } })" class="text-sm text-gray-500 hover:text-gray-700 transition-colors flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali ke Daftar
                </button>
                <div class="flex gap-2">
                    <button v-if="doc.status === 'draft' || doc.status === 'confirmed'" @click="router.push({ path: '/documents/' + doc.id + '/edit', query: { type: doc.document_type } })" class="px-3 py-1.5 text-sm bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit
                    </button>
                    <button v-if="doc.status === 'draft'" @click="handleConfirm" class="px-3 py-1.5 text-sm bg-green-50 text-green-700 hover:bg-green-100 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Konfirmasi
                    </button>
                    <button v-if="doc.status === 'draft'" @click="handleCancel" class="px-3 py-1.5 text-sm bg-orange-50 text-orange-600 hover:bg-orange-100 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Batalkan
                    </button>
                    <button @click="handleDelete" class="px-3 py-1.5 text-sm bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Hapus
                    </button>
                    <button
                        v-if="(doc.type === 'invoice' || doc.type === 'proforma_invoice') && doc.customer_po_file"
                        @click="handleViewPoFile"
                        class="px-3 py-1.5 text-sm bg-teal-50 text-teal-700 hover:bg-teal-100 rounded-lg transition-colors flex items-center gap-1 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Lihat PO Customer
                    </button>
                    <button @click="handleExport" class="px-3 py-1.5 text-sm bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export DOCX
                    </button>
                </div>
            </div>


            <div class="bg-white rounded-lg border border-gray-200">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">{{ doc.document_type?.replace(/_/g, ' ') }}</h1>
                            <p class="text-sm text-gray-500 mt-1">No. {{ doc.number || '-' }}</p>
                        </div>
                        <span class="inline-block px-3 py-1 text-sm font-medium rounded-full" :class="statusColors[doc.status] || 'bg-gray-100 text-gray-600'">
                            {{ doc.status }}
                        </span>
                    </div>
                </div>

                <div class="px-6 py-5 border-b border-gray-200">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Perusahaan</h3>
                            <p class="text-sm text-gray-700">{{ doc.company?.name || '-' }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Partner</h3>
                            <p class="text-sm text-gray-700 font-medium">{{ doc.partner?.name || '-' }}</p>
                            <p v-if="doc.partner?.address" class="text-xs text-gray-500 mt-0.5">{{ doc.partner.address }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Tanggal</h3>
                            <p class="text-sm text-gray-700">{{ formatDate(doc.date) }}</p>
                            <p v-if="doc.due_date" class="text-xs text-gray-500 mt-0.5">Jatuh tempo: {{ formatDate(doc.due_date) }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Referensi</h3>
                            <p class="text-sm text-gray-700 font-medium">
                                <router-link v-if="doc.reference" :to="{ path: '/documents/' + doc.reference.id, query: { type: doc.reference.document_type } }" class="text-indofilter hover:underline">
                                    {{ doc.reference.document_number || `Draft #${doc.reference.id}` }}
                                </router-link>
                                <span v-else class="text-gray-400">-</span>
                            </p>
                        </div>
                        <div v-if="doc.type === 'invoice' || doc.type === 'proforma_invoice'">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Customer Reff</h3>
                            <p class="text-sm text-gray-700 font-medium">{{ doc.customer_po_number || '-' }}</p>
                        </div>
                        <div v-if="doc.type === 'delivery_slip'">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">No. PO Customer</h3>
                            <p class="text-sm text-gray-700 font-medium">{{ doc.customer_po_number || '-' }}</p>
                        </div>
                        <div v-if="doc.type === 'delivery_slip'">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Tanggal PO Customer</h3>
                            <p class="text-sm text-gray-700">{{ formatDate(doc.customer_po_date) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address Info -->
                <div v-if="doc.type === 'delivery_address'" class="px-6 py-5 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">Pengirim</h3>
                            <p class="text-sm font-semibold text-gray-800">{{ doc.sender_name || doc.company?.name || '-' }}</p>
                            <p v-if="doc.sender_phone || doc.company?.phone" class="text-sm text-gray-600 mt-1">{{ doc.sender_phone || doc.company?.phone }}</p>
                            <p v-if="doc.sender_address || doc.company?.address" class="text-sm text-gray-500 mt-1">{{ doc.sender_address || doc.company?.address }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">Penerima</h3>
                            <p class="text-sm font-semibold text-gray-800">{{ doc.recipient_name || doc.partner?.name || '-' }}</p>
                            <p v-if="doc.recipient_pic" class="text-xs text-gray-500 mt-1">Up. {{ doc.recipient_pic }}</p>
                            <p v-if="doc.recipient_address || doc.partner?.address" class="text-sm text-gray-500 mt-1">{{ doc.recipient_address || doc.partner?.address }}</p>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div v-if="doc.type !== 'delivery_address'" class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">Item Barang</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Satuan</th>
                                    <th v-if="doc.type !== 'delivery_slip'" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Harga</th>
                                    <th v-if="doc.type !== 'delivery_slip'" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, i) in doc.items" :key="i" :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                    <td class="px-3 py-2 text-sm text-gray-700">
                                        <div class="font-medium">{{ item.product_name }}</div>
                                        <div v-if="item.variations && item.variations.length > 0" class="mt-1.5 space-y-1">
                                            <div v-for="(v, vi) in item.variations" :key="vi" class="text-[11px] text-purple-700 font-medium pl-3 border-l-2 border-purple-200">
                                                {{ vi + 1 }}. {{ v.name }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500">
                                        <div>{{ item.description || '-' }}</div>
                                        <div v-if="item.variations && item.variations.length > 0" class="mt-1.5 space-y-1">
                                            <div v-for="(v, vi) in item.variations" :key="vi" class="text-[11px] text-gray-400">
                                                &nbsp;
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-700 text-right">
                                        <div :class="{'text-gray-400 font-normal': item.variations && item.variations.length > 0}">{{ item.qty }}</div>
                                        <div v-if="item.variations && item.variations.length > 0" class="mt-1.5 space-y-1">
                                            <div v-for="(v, vi) in item.variations" :key="vi" class="text-[11px] text-gray-500">
                                                {{ v.qty }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500">
                                        <div>{{ item.uom }}</div>
                                        <div v-if="item.variations && item.variations.length > 0" class="mt-1.5 space-y-1">
                                            <div v-for="(v, vi) in item.variations" :key="vi" class="text-[11px] text-gray-400">
                                                {{ item.uom }}
                                            </div>
                                        </div>
                                    </td>
                                    <td v-if="doc.type !== 'delivery_slip'" class="px-3 py-2 text-sm text-gray-700 text-right">
                                        <div :class="{'text-gray-400 font-normal text-xs': item.variations && item.variations.length > 0}">
                                            <span v-if="item.variations && item.variations.length > 0" class="text-[10px] text-gray-400 block">(Rata-rata)</span>
                                            {{ formatMoney(item.unit_price) }}
                                        </div>
                                        <div v-if="item.variations && item.variations.length > 0" class="mt-1.5 space-y-1">
                                            <div v-for="(v, vi) in item.variations" :key="vi" class="text-[11px] text-gray-600">
                                                {{ formatMoney(v.unit_price) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td v-if="doc.type !== 'delivery_slip'" class="px-3 py-2 text-sm text-gray-700 text-right font-medium">
                                        <div>{{ formatMoney(item.total) }}</div>
                                        <div v-if="item.variations && item.variations.length > 0" class="mt-1.5 space-y-1">
                                            <div v-for="(v, vi) in item.variations" :key="vi" class="text-[11px] text-gray-600 font-normal">
                                                {{ formatMoney(v.total) }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totals -->
                <div v-if="doc.type !== 'delivery_slip' && doc.type !== 'delivery_address'" class="px-6 py-5 border-b border-gray-200">
                    <div class="flex justify-end">
                        <div class="w-72 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-medium">{{ formatMoney(doc.subtotal || doc.items?.reduce((s, i) => s + (i.total || 0), 0)) }}</span>
                            </div>
                            <div v-if="doc.discount && Number(doc.discount) > 0" class="flex justify-between text-sm">
                                <span class="text-gray-500">Diskon</span>
                                <span class="text-red-600">-{{ formatMoney(doc.discount) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ doc.is_ppn ? 'PPN (11%)' : 'Non PPN' }}</span>
                                <span class="text-green-600">+{{ formatMoney(doc.tax) }}</span>
                            </div>
                            <div v-if="doc.payment_type && doc.payment_type !== 'full'" class="flex justify-between text-sm border-t border-gray-100 pt-1.5">
                                <span class="text-gray-500 font-medium">Total Tagihan Penuh</span>
                                <span class="font-medium text-gray-700">{{ formatMoney(Number(doc.subtotal) + Number(doc.tax)) }}</span>
                            </div>
                            <div v-if="doc.payment_type === 'dp'" class="space-y-1.5 border-t border-gray-100 pt-2 pb-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 font-medium text-blue-600">Uang Muka (DP {{ parseFloat(doc.dp_percent) }}%)</span>
                                    <span class="font-semibold text-blue-600">{{ formatMoney(doc.dp_amount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Nominal Pelunasan ({{ 100 - parseFloat(doc.dp_percent) }}%)</span>
                                    <span class="font-medium text-gray-400">{{ formatMoney((Number(doc.subtotal) + Number(doc.tax)) - Number(doc.dp_amount)) }}</span>
                                </div>
                            </div>
                            <div v-if="doc.payment_type === 'pelunasan'" class="space-y-1.5 border-t border-gray-100 pt-2 pb-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Uang Muka (DP {{ parseFloat(doc.dp_percent) }}%)</span>
                                    <span class="font-medium text-gray-400">{{ formatMoney(doc.dp_amount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 font-medium text-blue-600">Nominal Pelunasan ({{ 100 - parseFloat(doc.dp_percent) }}%)</span>
                                    <span class="font-semibold text-blue-600">{{ formatMoney((Number(doc.subtotal) + Number(doc.tax)) - Number(doc.dp_amount)) }}</span>
                                </div>
                            </div>
                            <div class="flex justify-between text-sm font-bold border-t border-gray-200 pt-2 pb-2">
                                <span>Grand Total {{ doc.payment_type === 'dp' ? '(DP)' : (doc.payment_type === 'pelunasan' ? '(Pelunasan)' : '') }}</span>
                                <span class="text-lg text-blue-600">{{ formatMoney(doc.grand_total) }}</span>
                            </div>
                            <!-- Terbilang Display -->
                            <div v-if="(doc.type === 'invoice' || doc.type === 'proforma_invoice') && doc.terbilang" class="text-xs text-right text-gray-950 italic mt-1 font-bold bg-gray-50 p-2 rounded border border-gray-100">
                                Terbilang: {{ doc.terbilang }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ketentuan Penawaran -->
                <div v-if="doc.type === 'quotation'" class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">Ketentuan Penawaran</h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div>
                            <h4 class="text-[10px] font-medium text-gray-400">Stock Conditions</h4>
                            <p class="text-sm text-gray-700 mt-0.5">{{ doc.stock_conditions || '-' }}</p>
                        </div>
                        <div>
                            <h4 class="text-[10px] font-medium text-gray-400">Term of Payment</h4>
                            <p class="text-sm text-gray-700 mt-0.5">{{ doc.term_of_payment || '-' }}</p>
                        </div>
                        <div>
                            <h4 class="text-[10px] font-medium text-gray-400">Price Conditions</h4>
                            <p class="text-sm text-gray-700 mt-0.5">{{ doc.price_conditions || '-' }}</p>
                        </div>
                        <div>
                            <h4 class="text-[10px] font-medium text-gray-400">Standard Packing</h4>
                            <p class="text-sm text-gray-700 mt-0.5">{{ doc.standard_packing || '-' }}</p>
                        </div>
                        <div>
                            <h4 class="text-[10px] font-medium text-gray-400">Offer Validity</h4>
                            <p class="text-sm text-gray-700 mt-0.5">{{ doc.offer_validity || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Terms & Notes & Payment Bank -->
                <div class="px-6 py-5 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-if="doc.terms">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Syarat Pembayaran</h3>
                            <p class="text-sm text-gray-600">{{ doc.terms }}</p>
                        </div>
                        <div v-if="doc.notes">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Catatan</h3>
                            <p class="text-sm text-gray-600">{{ doc.notes }}</p>
                        </div>
                    </div>
                    
                    <div v-if="(doc.type === 'invoice' || doc.type === 'proforma_invoice') && (doc.bank_account || doc.bankAccount)" class="border-t border-gray-100 pt-4 bg-gray-50/50 p-4 rounded-lg">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase mb-1">Informasi Pembayaran / Rekening Bank</h3>
                        <p class="text-xs text-gray-500 mb-2">Pembayaran dapat ditujukan ke rekening perusahaan berikut:</p>
                        <p class="text-sm font-bold text-gray-800">
                            {{ (doc.bank_account || doc.bankAccount).bank_name }} - {{ (doc.bank_account || doc.bankAccount).account_name }}
                        </p>
                        <p class="text-sm font-semibold text-blue-600 mt-0.5">
                            No. Rekening: {{ (doc.bank_account || doc.bankAccount).account_number }}
                        </p>
                    </div>

                    <div v-if="doc.type === 'purchase_order' && (doc.vendor_bank_name || doc.partner?.bank_name)" class="border-t border-gray-100 pt-4 bg-gray-50/50 p-4 rounded-lg">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase mb-1">Informasi Pembayaran / Rekening Bank Vendor</h3>
                        <p class="text-xs text-gray-500 mb-2">Pembayaran PO ini ditujukan ke rekening vendor berikut:</p>
                        <p class="text-sm font-bold text-gray-800">
                            {{ doc.vendor_bank_name || doc.partner?.bank_name }} - {{ doc.vendor_bank_account_name || doc.partner?.bank_account_name }}
                        </p>
                        <p class="text-sm font-semibold text-blue-600 mt-0.5">
                            No. Rekening: {{ doc.vendor_bank_account_number || doc.partner?.bank_account_number }}
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
