<script setup>
import { ref, onMounted } from 'vue'
import { settings as api } from '../../api/index.js'

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref('')
const success = ref('')
const testResult = ref(null)

const form = ref({
    supabase_enabled: '0',
    supabase_url: '',
    supabase_service_key: '',
    supabase_bucket: 'documents',
})

async function loadSettings() {
    loading.value = true
    error.value = ''
    success.value = ''
    try {
        const res = await api.getSupabase()
        const data = res.data
        form.value.supabase_enabled     = data.supabase_enabled || '0'
        form.value.supabase_url         = data.supabase_url || ''
        form.value.supabase_service_key = data.supabase_service_key || ''
        form.value.supabase_bucket      = data.supabase_bucket || 'documents'
    } catch (e) {
        error.value = 'Gagal memuat pengaturan Supabase Storage'
    } finally {
        loading.value = false
    }
}

async function handleSave() {
    saving.value = true
    error.value = ''
    success.value = ''
    testResult.value = null
    try {
        await api.updateSupabase(form.value)
        success.value = 'Pengaturan Supabase Storage berhasil disimpan.'
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan pengaturan'
    } finally {
        saving.value = false
    }
}

async function handleTest() {
    testing.value = true
    testResult.value = null
    error.value = ''
    try {
        const res = await api.testSupabase({
            supabase_url: form.value.supabase_url,
            supabase_service_key: form.value.supabase_service_key,
            supabase_bucket: form.value.supabase_bucket,
        })
        testResult.value = res.data
    } catch (e) {
        testResult.value = {
            success: false,
            message: e.response?.data?.message || 'Koneksi gagal'
        }
    } finally {
        testing.value = false
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
                <h2 class="text-lg font-semibold text-gray-800">Pengaturan Supabase Storage</h2>
                <div class="flex gap-2">
                    <!-- Test Connection -->
                    <button
                        @click="handleTest"
                        :disabled="testing || !form.supabase_url || !form.supabase_service_key"
                        class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="testing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Uji Koneksi
                    </button>

                    <!-- Save -->
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

            <!-- Local Fallback Info Banner -->
            <div v-if="form.supabase_enabled === '0'" class="bg-indigo-50 border border-indigo-200 text-indigo-700 text-sm rounded-lg px-4 py-3 mb-4 flex items-start gap-2.5 shadow-sm">
                <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-xs leading-relaxed">
                    <span class="font-semibold text-indigo-800">Penyimpanan Lokal Aktif:</span> Karena integrasi Supabase dinonaktifkan (atau jika layanan Supabase sedang bermasalah), dokumen Word akan secara otomatis disimpan di server lokal aplikasi Anda. Anda tetap dapat melakukan sinkronisasi dan mengunduh dokumen dengan lancar!
                </div>
            </div>

            <!-- Test Result -->
            <div v-if="testResult" class="px-4 py-3 rounded-lg text-sm border mb-4"
                :class="testResult.success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
                <div class="flex items-start gap-2">
                    <span class="font-bold">{{ testResult.success ? '✓ Sukses:' : '✗ Gagal:' }}</span>
                    <p class="whitespace-pre-wrap flex-1 text-xs">{{ testResult.message }}</p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Credentials Card -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-2">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Konfigurasi Supabase</h3>
                        <div class="flex items-center gap-3">
                            <label class="text-xs font-medium text-gray-600">Aktifkan Integrasi</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.supabase_enabled" true-value="1" false-value="0" class="sr-only peer" />
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-indofilter peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Supabase URL -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Project URL *</label>
                            <input
                                v-model="form.supabase_url"
                                type="url"
                                placeholder="https://xxxxxxxxxxx.supabase.co"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono"
                            />
                            <p class="text-xs text-gray-400 mt-1">Tersedia di Supabase Dashboard → Settings → API → Project URL</p>
                        </div>

                        <!-- Service Key -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Service Role Key *</label>
                            <input
                                v-model="form.supabase_service_key"
                                type="password"
                                placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono"
                            />
                            <p class="text-xs text-red-500 mt-1 font-medium">⚠ Gunakan <strong>service_role</strong> key, bukan anon key. Jaga kerahasiaannya.</p>
                        </div>

                        <!-- Bucket -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Nama Bucket *</label>
                            <input
                                v-model="form.supabase_bucket"
                                type="text"
                                placeholder="documents"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                            <p class="text-xs text-gray-400 mt-1">Bucket akan dibuat otomatis jika belum ada</p>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50/50 border border-blue-100 rounded-lg p-3 text-xs text-blue-800 space-y-1 mt-2">
                        <p class="font-bold flex items-center gap-1">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Cara Setup:
                        </p>
                        <ol class="list-decimal list-inside space-y-0.5 pl-1 text-[11px]">
                            <li>Daftar atau login di <strong>supabase.com</strong> → buat project baru (gratis)</li>
                            <li>Buka <strong>Settings → API</strong> → copy <strong>Project URL</strong></li>
                            <li>Di halaman yang sama, copy <strong>service_role</strong> key (bukan anon key)</li>
                            <li>Isi nama bucket bebas (contoh: <code class="bg-blue-100 px-1 rounded font-mono">documents</code>)</li>
                            <li>Klik <strong>Uji Koneksi</strong> — bucket akan otomatis dibuat jika belum ada</li>
                            <li>Klik <strong>Simpan</strong></li>
                        </ol>
                    </div>
                </div>

                <!-- File Structure Info -->
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100 pb-3 mb-4">Struktur Penyimpanan File</h3>
                    <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-600 space-y-1">
                        <p class="font-semibold text-gray-700">📦 {{ form.supabase_bucket || 'documents' }} (bucket)</p>
                        <p class="pl-4">📁 company_1/</p>
                        <p class="pl-8">📁 quotation/ → <span class="text-indigo-600">QUO-2026-0001.docx</span></p>
                        <p class="pl-8">📁 invoice/ → <span class="text-indigo-600">INV-2026-0001.docx</span></p>
                        <p class="pl-8">📁 ... (tipe lainnya)</p>
                        <p class="pl-4">📁 company_2/</p>
                        <p class="pl-8">📁 quotation/ → <span class="text-indigo-600">QUO-2026-0001.docx</span></p>
                        <p class="pl-8">📁 ... dst</p>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">File diorganisir otomatis berdasarkan perusahaan dan tipe dokumen. Setiap dokumen yang dikonfirmasi akan otomatis terupload.</p>
                </div>
            </div>
        </template>
    </div>
</template>
