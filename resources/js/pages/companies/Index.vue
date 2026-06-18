<script setup>
import { ref, onMounted } from 'vue'
import { companies as api } from '../../api/index.js'
import DataTable from '../../components/DataTable.vue'
import FormModal from '../../components/FormModal.vue'

const items = ref([])
const loading = ref(false)
const formLoading = ref(false)
const showModal = ref(false)
const editing = ref(null)
const error = ref('')
const search = ref('')

const emptyForm = () => ({
    name: '', alias: '', address: '', phone: '', email: '', npwp: '',
    phones: [], bank_accounts: [],
})

const form = ref(emptyForm())

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'alias', label: 'Alias' },
    { key: 'phone', label: 'Telepon' },
    { key: 'email', label: 'Email' },
    { key: 'npwp', label: 'NPWP' },
    { key: 'actions', label: 'Aksi' },
]

async function fetchData() {
    loading.value = true
    try {
        const res = await api.list()
        items.value = res.data.data || res.data || []
    } catch (e) {
        items.value = []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    editing.value = null
    form.value = emptyForm()
    error.value = ''
    showModal.value = true
}

function openEdit(item) {
    editing.value = item.id
    form.value = { ...item, phones: [...(item.phones || [])], bank_accounts: (item.bank_accounts || []).map(b => ({ ...b, is_default: !!b.is_default })) }
    error.value = ''
    showModal.value = true
}

function addPhone() {
    form.value.phones.push({ phone: '', label: '' })
}

function removePhone(index) {
    form.value.phones.splice(index, 1)
}

function addBankAccount() {
    form.value.bank_accounts.push({ bank_name: '', account_name: '', account_number: '', is_default: false })
}

function removeBankAccount(index) {
    form.value.bank_accounts.splice(index, 1)
}

function setBankAccountDefault(index, isChecked) {
    if (isChecked) {
        form.value.bank_accounts.forEach((acc, i) => {
            acc.is_default = (i === index)
        })
    } else {
        form.value.bank_accounts[index].is_default = false
    }
}

async function save() {
    formLoading.value = true
    error.value = ''
    try {
        const payload = {
            name: form.value.name,
            alias: form.value.alias,
            address: form.value.address,
            phone: form.value.phone,
            email: form.value.email,
            npwp: form.value.npwp,
            phones: form.value.phones.map(p => ({ id: p.id, phone: p.phone, label: p.label })),
            bank_accounts: form.value.bank_accounts.map(b => ({ id: b.id, bank_name: b.bank_name, account_name: b.account_name, account_number: b.account_number, is_default: !!b.is_default })),
        }
        if (editing.value) {
            await api.update(editing.value, payload)
        } else {
            await api.create(payload)
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

onMounted(fetchData)
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <input v-model="search" type="text" placeholder="Cari perusahaan..." class="w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
            </div>
            <button @click="openCreate" class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Baru
            </button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200">
            <DataTable :columns="columns" :data="items" :loading="loading" empty-message="Belum ada perusahaan">
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

        <FormModal :show="showModal" :title="editing ? 'Edit Perusahaan' : 'Perusahaan Baru'" @close="showModal = false">
            <div class="space-y-4">
                <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nama *</label>
                        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Alias</label>
                        <input v-model="form.alias" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alamat</label>
                    <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Telepon (Utama)</label>
                        <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telepon Lainnya</label>
                    <div v-for="(p, i) in form.phones" :key="i" class="flex gap-2 mb-2">
                        <input v-model="p.phone" placeholder="Nomor telepon" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        <input v-model="p.label" placeholder="Label (opsional)" class="w-32 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        <button @click="removePhone(i)" class="text-red-500 hover:text-red-700 p-2" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                    <button @click="addPhone" class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Telepon
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">NPWP</label>
                        <input v-model="form.npwp" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Rekening Bank</label>
                    <div v-for="(b, i) in form.bank_accounts" :key="i" class="border border-gray-200 rounded-lg p-3 mb-2 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-gray-500">Rekening {{ i + 1 }}</span>
                            <button @click="removeBankAccount(i)" class="text-red-500 hover:text-red-700 text-sm">Hapus</button>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Nama Bank</label>
                                <input v-model="b.bank_name" placeholder="Bank Mandiri" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Atas Nama</label>
                                <input v-model="b.account_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">No. Rekening</label>
                                <input v-model="b.account_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                            </div>
                        </div>
                        <div class="flex items-center mt-1">
                            <label class="inline-flex items-center text-xs text-gray-600 cursor-pointer">
                                <input type="checkbox" :checked="b.is_default" @change="setBankAccountDefault(i, $event.target.checked)" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-1.5" />
                                Set sebagai Rekening Utama (Default)
                            </label>
                        </div>
                    </div>
                    <button @click="addBankAccount" class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Rekening
                    </button>
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
