<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { products as api, companies as companiesApi } from '../../api/index.js'
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
const search = ref('')
const filterCompany = ref('')

const appStore = useAppStore()
const router = useRouter()

function showHistory(product) {
    router.push(`/products/${product.id}/history`)
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

const form = ref({ company_id: '', code: '', name: '', description: '', uom: 'PCS', price: 0 })

const columns = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'uom', label: 'Satuan' },
    { key: 'price', label: 'Harga' },
    { key: 'company', label: 'Perusahaan' },
    { key: 'actions', label: 'Aksi' },
]

const filteredItems = computed(() => {
    let result = items.value
    if (search.value) {
        const s = search.value.toLowerCase()
        result = result.filter(r => r.name?.toLowerCase().includes(s) || r.code?.toLowerCase().includes(s))
    }
    if (filterCompany.value) result = result.filter(r => r.company_id == filterCompany.value)
    return result
})

function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

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
    form.value = { company_id: appStore.activeCompanyId || '', code: '', name: '', description: '', uom: 'PCS', price: 0 }
    error.value = ''
    showModal.value = true
}

function openEdit(item) {
    editing.value = item.id
    form.value = { ...item, company_id: item.company_id || '' }
    error.value = ''
    showModal.value = true
}

async function save() {
    formLoading.value = true
    error.value = ''
    try {
        if (editing.value) {
            await api.update(editing.value, form.value)
        } else {
            await api.create(form.value)
        }
        showModal.value = false
        await fetchData()
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan data'
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
                <input ref="searchInput" v-model="search" type="text" placeholder="Cari produk..." class="w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
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
            <DataTable :columns="columns" :data="filteredItems" :loading="loading" empty-message="Belum ada produk">
                <template #cell-price="{ value }">
                    {{ formatMoney(value) }}
                </template>
                <template #cell-company="{ row }">
                    {{ row.company?.name || '-' }}
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <button @click="showHistory(row)" class="text-purple-600 hover:text-purple-800 transition-colors" title="Riwayat Surat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </button>
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

        <FormModal :show="showModal" :title="editing ? 'Edit Produk' : 'Produk Baru'" @close="showModal = false">
            <div class="space-y-4">
                <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Perusahaan</label>
                        <select v-model="form.company_id" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 cursor-not-allowed outline-none">
                            <option value="">Pilih Perusahaan</option>
                            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Kode *</label>
                        <input v-model="form.code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama *</label>
                    <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Deskripsi</label>
                    <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Satuan *</label>
                        <select v-model="form.uom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="PCS">PCS</option>
                            <option value="KG">KG</option>
                            <option value="MTR">MTR</option>
                            <option value="LTR">LTR</option>
                            <option value="BOX">BOX</option>
                            <option value="ROL">ROL</option>
                            <option value="SET">SET</option>
                            <option value="UNIT">UNIT</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Harga *</label>
                        <input v-model.number="form.price" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
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
