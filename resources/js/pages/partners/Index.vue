<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { partners as api, companies as companiesApi } from '../../api/index.js'
import { useAppStore } from '../../stores/app.js'
import DataTable from '../../components/DataTable.vue'
import FormModal from '../../components/FormModal.vue'

const items = ref([])
const companies = ref([])
const loading = ref(false)
const formLoading = ref(false)
const showModal = ref(false)
const editing = ref(null)
const error = ref('')
const originalName = ref('')
const search = ref('')
const filterType = ref('')
const filterCompany = ref('')

const appStore = useAppStore()

const form = ref({ company_id: '', company_ids: [], type: 'customer', name: '', alias: '', address: '', phone: '', email: '', npwp: '', contact_person: '', bank_name: '', bank_account_name: '', bank_account_number: '' })

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'type', label: 'Tipe' },
    { key: 'company', label: 'Perusahaan' },
    { key: 'phone', label: 'Telepon' },
    { key: 'email', label: 'Email' },
    { key: 'contact_person', label: 'Kontak' },
    { key: 'actions', label: 'Aksi' },
]

const filteredItems = computed(() => {
    let result = items.value
    if (search.value) {
        const s = search.value.toLowerCase()
        result = result.filter(r => r.name?.toLowerCase().includes(s) || r.alias?.toLowerCase().includes(s))
    }
    if (filterType.value) result = result.filter(r => r.type === filterType.value)
    if (filterCompany.value) result = result.filter(r => r.company_id == filterCompany.value)
    return result
})

async function fetchData() {
    loading.value = true
    try {
        const [res, compRes] = await Promise.all([api.list(), companiesApi.list()])
        items.value = res.data.data || res.data || []
        companies.value = compRes.data.data || compRes.data || []
    } catch (e) {
        items.value = []
        companies.value = []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    editing.value = null
    form.value = { company_id: appStore.activeCompanyId || '', company_ids: appStore.activeCompanyId ? [appStore.activeCompanyId] : [], type: 'customer', name: '', alias: '', address: '', phone: '', email: '', npwp: '', contact_person: '', bank_name: '', bank_account_name: '', bank_account_number: '' }
    error.value = ''
    showModal.value = true
}

function openEdit(item) {
    editing.value = item.id
    originalName.value = item.name || ''

    // Find all partners with the same name (case-insensitive) to pre-select company checkboxes
    const sameNamePartners = items.value.filter(p => 
        p.name && item.name && p.name.toLowerCase().trim() === item.name.toLowerCase().trim()
    )
    const companyIds = Array.from(new Set(sameNamePartners.map(p => Number(p.company_id)).filter(Boolean)))

    const companyId = item.company_id ? Number(item.company_id) : null
    if (companyId && !companyIds.includes(companyId)) {
        companyIds.push(companyId)
    }

    form.value = { 
        ...item, 
        company_id: companyId, 
        company_ids: companyIds, 
        alias: item.alias || '',
        bank_name: item.bank_name || '',
        bank_account_name: item.bank_account_name || '',
        bank_account_number: item.bank_account_number || ''
    }
    error.value = ''
    showModal.value = true
}

async function save() {
    formLoading.value = true
    error.value = ''
    try {
        if (!form.value.company_ids || form.value.company_ids.length === 0) {
            throw new Error('Harap pilih minimal satu perusahaan.')
        }

        if (editing.value) {
            const originalPartner = items.value.find(item => item.id === editing.value)
            const originalCompanyId = originalPartner ? Number(originalPartner.company_id) : null
            
            let primaryCompanyId = form.value.company_ids[0]
            if (originalCompanyId && form.value.company_ids.includes(originalCompanyId)) {
                primaryCompanyId = originalCompanyId
            }

            const otherCompanyIds = form.value.company_ids.filter(id => id !== primaryCompanyId)

            const updatePayload = { ...form.value, company_id: primaryCompanyId }
            delete updatePayload.company_ids
            delete updatePayload.company

            await api.update(editing.value, updatePayload)

            if (otherCompanyIds.length > 0) {
                await Promise.all(otherCompanyIds.map(id => {
                    // Check if partner with same name already exists in this company
                    const existingPartner = items.value.find(item => 
                        item.company_id == id && (
                            (item.name && originalName.value && item.name.toLowerCase().trim() === originalName.value.toLowerCase().trim()) ||
                            (item.name && form.value.name && item.name.toLowerCase().trim() === form.value.name.toLowerCase().trim())
                        )
                    )

                    if (existingPartner) {
                        const otherUpdatePayload = { ...form.value, company_id: id }
                        delete otherUpdatePayload.company_ids
                        delete otherUpdatePayload.company
                        return api.update(existingPartner.id, otherUpdatePayload)
                    } else {
                        const createPayload = { ...form.value, company_id: id }
                        delete createPayload.company_ids
                        delete createPayload.company
                        delete createPayload.id
                        delete createPayload.created_at
                        delete createPayload.updated_at
                        return api.create(createPayload)
                    }
                }))
            }
        } else {
            await Promise.all(form.value.company_ids.map(id => {
                const existingPartner = items.value.find(item => 
                    item.company_id == id && 
                    item.name && form.value.name && 
                    item.name.toLowerCase().trim() === form.value.name.toLowerCase().trim()
                )

                if (existingPartner) {
                    const updatePayload = { ...form.value, company_id: id }
                    delete updatePayload.company_ids
                    delete updatePayload.company
                    return api.update(existingPartner.id, updatePayload)
                } else {
                    const createPayload = { ...form.value, company_id: id }
                    delete createPayload.company_ids
                    delete createPayload.company
                    return api.create(createPayload)
                }
            }))
        }
        showModal.value = false
        await fetchData()
    } catch (e) {
        error.value = e.message || e.response?.data?.message || 'Gagal menyimpan data'
    } finally {
        formLoading.value = false
    }
}

async function confirmDelete(id) {
    if (!confirm('Yakin ingin menghapus data ini?')) return
    try {
        await api.delete(id)
        await fetchData()
    } catch (e) {
        alert('Gagal menghapus data')
    }
}

const route = useRoute()
const searchInput = ref(null)

const handleLocalKeydown = (e) => {
    if (e.ctrlKey && e.key.toLowerCase() === 'f') {
        e.preventDefault()
        if (searchInput.value) {
            searchInput.value.focus()
            searchInput.value.select()
        }
    }
    if (e.ctrlKey && e.key.toLowerCase() === 'n') {
        e.preventDefault()
        openCreate()
    }
    if (e.ctrlKey && e.key.toLowerCase() === 's') {
        if (showModal.value) {
            e.preventDefault()
            save()
        }
    }
}

onMounted(async () => {
    window.addEventListener('keydown', handleLocalKeydown)
    if (route.query.search) {
        search.value = route.query.search
    }
    await fetchData()
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleLocalKeydown)
})

watch(() => route.query.search, (newVal) => {
    search.value = newVal || ''
})
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-3">
                <input ref="searchInput" v-model="search" type="text" placeholder="Cari partner..." class="w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                <select v-model="filterType" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Semua Tipe</option>
                    <option value="customer">Customer</option>
                    <option value="vendor">Vendor</option>
                </select>
                <select v-model="filterCompany" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Semua Perusahaan</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <button @click="openCreate" class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Baru
            </button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200">
            <DataTable :columns="columns" :data="filteredItems" :loading="loading" empty-message="Belum ada partner">
                <template #cell-type="{ row }">
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full" :class="row.type === 'customer' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                        {{ row.type === 'customer' ? 'Customer' : 'Vendor' }}
                    </span>
                </template>
                <template #cell-company="{ row }">
                    {{ row.company?.name || '-' }}
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <button @click="openEdit(row)" class="text-blue-600 hover:text-blue-800 transition-colors" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button @click="confirmDelete(row.id)" class="text-red-500 hover:text-red-700 transition-colors" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <FormModal :show="showModal" :title="editing ? 'Edit Partner' : 'Partner Baru'" @close="showModal = false">
            <div class="space-y-4">
                <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Simpan ke Perusahaan *</label>
                        <div class="flex flex-col md:flex-row gap-4 p-3 border border-gray-300 rounded-lg bg-gray-50/50">
                            <label v-for="c in companies" :key="c.id" class="inline-flex items-center text-sm font-medium text-gray-700 cursor-pointer select-none">
                                <input type="checkbox" :value="c.id" v-model="form.company_ids" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2" />
                                {{ c.name }}
                            </label>
                        </div>
                        <p v-if="!editing" class="text-[10px] text-gray-500 mt-1">Partner akan disimpan secara terpisah ke semua perusahaan yang dicentang.</p>
                        <p v-else class="text-[10px] text-gray-500 mt-1">Jika Anda mencentang perusahaan tambahan, data partner baru akan dibuat untuk perusahaan tersebut.</p>
                    </div>
                    
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Tipe *</label>
                        <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="customer">Customer</option>
                            <option value="vendor">Vendor</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nama *</label>
                        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Alias Partner (Untuk No. Dokumen)</label>
                        <input v-model="form.alias" placeholder="Contoh: MJY" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alamat</label>
                    <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Telepon</label>
                        <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">NPWP</label>
                        <input v-model="form.npwp" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kontak Person</label>
                        <input v-model="form.contact_person" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>
                <div v-if="form.type === 'vendor'" class="border-t border-gray-100 pt-4 space-y-4">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Detail Bank Vendor (Opsional)</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nama Bank</label>
                            <input v-model="form.bank_name" placeholder="Contoh: BCA" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nama Rekening</label>
                            <input v-model="form.bank_account_name" placeholder="Contoh: PT ABC" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nomor Rekening</label>
                            <input v-model="form.bank_account_number" placeholder="Contoh: 12345678" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">Batal</button>
                <button @click="save" :disabled="formLoading" class="px-4 py-2 bg-indofilter hover:bg-indofilter-dark text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2">
                    <svg v-if="formLoading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ formLoading ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </template>
        </FormModal>
    </div>
</template>
