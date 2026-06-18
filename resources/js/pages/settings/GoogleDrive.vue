<script setup>
import { ref, onMounted } from 'vue'
import { settings as api } from '../../api/index.js'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref('')
const success = ref('')
const testResult = ref('')

const form = ref({
    google_drive_enabled: '0',
    google_drive_service_account_json: '',
    global_folders: {},
    company_folders: {},
})

const clientEmail = ref('')
const companies = ref([])

const documentTypes = [
    { key: 'quotation', label: 'Quotation' },
    { key: 'proforma_invoice', label: 'Proforma Invoice' },
    { key: 'invoice', label: 'Invoice' },
    { key: 'delivery_slip', label: 'Surat Jalan (Delivery Slip)' },
    { key: 'delivery_address', label: 'Alamat Kirim (Delivery Address)' },
    { key: 'purchase_order', label: 'Purchase Order' },
]

const activeCompanyTab = ref('global') // 'global' or company ID

async function loadSettings() {
    loading.value = true
    error.value = ''
    success.value = ''
    try {
        const res = await api.getGoogleDrive()
        const data = res.data
        form.value.google_drive_enabled = data.google_drive_enabled || '0'
        form.value.google_drive_service_account_json = data.google_drive_service_account_json || ''
        clientEmail.value = data.google_drive_service_account_email || ''

        // Initialize global folders safely to prevent vue model bind error
        form.value.global_folders = {}
        const responseGlobalFolders = data.global_folders || {}
        for (const dt of documentTypes) {
            form.value.global_folders[dt.key] = responseGlobalFolders[dt.key] || ''
        }

        // Initialize company folders safely to prevent vue model bind error
        form.value.company_folders = {}
        const responseCompanyFolders = data.company_folders || {}
        companies.value = data.companies || []
        
        for (const company of companies.value) {
            form.value.company_folders[company.id] = {}
            const compFolderData = responseCompanyFolders[company.id] || {}
            for (const dt of documentTypes) {
                form.value.company_folders[company.id][dt.key] = compFolderData[dt.key] || ''
            }
        }
    } catch (e) {
        error.value = 'Gagal memuat pengaturan Google Drive'
    } finally {
        loading.value = false
    }
}

async function handleSave() {
    saving.value = true
    error.value = ''
    success.value = ''
    try {
        await api.updateGoogleDrive(form.value)
        success.value = 'Pengaturan Google Drive berhasil disimpan.'
        // Refresh settings to reload details
        await loadSettings()
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan pengaturan'
    } finally {
        saving.value = false
    }
}

async function handleTest() {
    testing.value = true
    testResult.value = ''
    error.value = ''
    
    let testFolderId = '';
    if (activeCompanyTab.value === 'global') {
        const firstKey = documentTypes[0].key;
        testFolderId = form.value.global_folders[firstKey] || '';
    } else {
        const firstKey = documentTypes[0].key;
        const compFolders = form.value.company_folders[activeCompanyTab.value] || {};
        testFolderId = compFolders[firstKey] || '';
    }

    try {
        const res = await api.testGoogleDrive({
            google_drive_service_account_json: form.value.google_drive_service_account_json,
            test_folder_id: testFolderId || null
        })
        if (res.data && res.data.success) {
            testResult.value = 'success'
        } else {
            testResult.value = 'error: ' + (res.data?.message || 'Koneksi gagal')
        }
    } catch (e) {
        testResult.value = 'error: ' + (e.response?.data?.message || 'Koneksi gagal')
    } finally {
        testing.value = false
    }
}

function ensureCompanyFolderInitialized(companyId) {
    if (!form.value.company_folders[companyId]) {
        form.value.company_folders[companyId] = {}
    }
    for (const dt of documentTypes) {
        if (form.value.company_folders[companyId][dt.key] === undefined) {
            form.value.company_folders[companyId][dt.key] = ''
        }
    }
}

onMounted(() => {
    loadSettings()
})
</script>

<template>
    <div>
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg class="animate-spin h-8 w-8 text-indofilter" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <template v-else>
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    Pengaturan Google Drive
                </h2>
                <div class="flex gap-2">
                    <!-- Test Connection -->
                    <button 
                        @click="handleTest" 
                        :disabled="testing || !form.google_drive_service_account_json" 
                        class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="testing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        Uji Koneksi
                    </button>
                    
                    <!-- Save Settings -->
                    <button 
                        @click="handleSave" 
                        :disabled="saving" 
                        class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg v-if="saving" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Simpan
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">{{ error }}</div>
            <div v-if="success" class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3 mb-4">{{ success }}</div>
            
            <div v-if="testResult" class="px-4 py-3 rounded-lg text-sm border mb-4" :class="testResult === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
                <div class="flex items-start gap-2">
                    <span v-if="testResult === 'success'" class="font-bold">✓ Sukses:</span>
                    <span v-else class="font-bold">✗ Gagal:</span>
                    <p class="whitespace-pre-wrap flex-1 text-xs">{{ testResult === 'success' ? 'Berhasil terhubung ke Google Drive API!' : testResult.replace('error: ', '') }}</p>
                </div>
            </div>

            <!-- Main Layout Grid -->
            <div class="space-y-4">
                <!-- Credentials Section -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-150 pb-2 mb-2">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kredensial Google Cloud</h3>
                        <div class="flex items-center gap-3">
                            <label class="text-xs font-medium text-gray-650">Aktivasi Integrasi</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.google_drive_enabled" true-value="1" false-value="0" class="sr-only peer" />
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-indofilter peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 font-semibold">Service Account JSON *</label>
                            <textarea 
                                v-model="form.google_drive_service_account_json" 
                                rows="5" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" 
                                placeholder="Tempel isi file JSON service account yang diunduh dari Google Cloud Console"
                            ></textarea>
                            <p v-if="clientEmail" class="text-xs text-green-600 mt-1 font-medium">Email Service Account terdeteksi: {{ clientEmail }}</p>
                            <p v-else class="text-xs text-gray-400 mt-1">Belum terhubung ke Service Account</p>
                        </div>

                        <div class="bg-blue-50/50 border border-blue-100 rounded-lg p-3 text-xs text-blue-800 space-y-1">
                            <p class="font-bold flex items-center gap-1">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Informasi Penyiapan:
                            </p>
                            <ul class="list-disc list-inside space-y-0.5 pl-1 text-[11px]">
                                <li>Pastikan Google Drive API sudah diaktifkan di Google Cloud Console.</li>
                                <li v-if="clientEmail">
                                    Berikan akses folder Google Drive Anda dengan cara membagikan (share) folder tersebut ke email Service Account: 
                                    <code class="bg-blue-100 px-1 py-0.5 rounded font-bold font-mono text-[10px] select-all">{{ clientEmail }}</code> 
                                    sebagai <strong>Editor</strong>.
                                </li>
                                <li v-else>Bagikan folder Google Drive tujuan ke email Service Account Anda sebagai Editor.</li>
                                <li>ID Folder dapat diambil dari URL halaman Google Drive di browser Anda.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Folders Settings Section -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-150 pb-2 mb-2">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pemetaan Folder Google Drive</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium">Konfigurasi Untuk:</span>
                            <select 
                                v-model="activeCompanyTab" 
                                @change="activeCompanyTab !== 'global' && ensureCompanyFolderInitialized(activeCompanyTab)"
                                class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                                <option value="global">Bawaan (Semua Perusahaan / Global)</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }} ({{ c.alias }})</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="activeCompanyTab !== 'global'" class="bg-amber-50/50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                        <p class="font-bold flex items-center gap-1 mb-1">
                            <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Konfigurasi Khusus Perusahaan
                        </p>
                        Jika Folder ID untuk dokumen di bawah ini dikosongkan, sistem akan otomatis memakai Folder ID dari <strong>Bawaan (Global)</strong>.
                    </div>

                    <!-- Global Folders form -->
                    <div v-if="activeCompanyTab === 'global' && form.global_folders" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="dt in documentTypes" :key="dt.key">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ dt.label }} *</label>
                            <input 
                                v-model="form.global_folders[dt.key]" 
                                type="text" 
                                placeholder="Masukkan Folder ID Google Drive" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>

                    <!-- Company Folders form -->
                    <div v-else-if="activeCompanyTab !== 'global' && form.company_folders && form.company_folders[activeCompanyTab]" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="dt in documentTypes" :key="dt.key">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ dt.label }}</label>
                            <input 
                                v-model="form.company_folders[activeCompanyTab][dt.key]" 
                                type="text" 
                                placeholder="Masukkan Folder ID khusus (atau biarkan kosong)" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
