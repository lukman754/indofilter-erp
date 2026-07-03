<script setup>
import { ref, onMounted, computed } from 'vue'
import { companies as api } from '../../api/index.js'
import { useAppStore } from '../../stores/app.js'
import DataTable from '../../components/DataTable.vue'
import FormModal from '../../components/FormModal.vue'

const items = ref([])
const loading = ref(false)
const formLoading = ref(false)
const showModal = ref(false)
const editing = ref(null)
const error = ref('')
const appStore = useAppStore()
const search = ref('')

const emptyForm = () => ({
    name: '', alias: '', address: '', phone: '', email: '', npwp: '', po_masuk_path: '',
    phones: [], bank_accounts: [],
})

const form = ref(emptyForm())

const filteredItems = computed(() => {
    if (!search.value) return items.value
    const q = search.value.toLowerCase()
    return items.value.filter(item => {
        return (item.name?.toLowerCase().includes(q)) || 
               (item.alias?.toLowerCase().includes(q)) ||
               (item.email?.toLowerCase().includes(q)) ||
               (item.phone?.toLowerCase().includes(q)) ||
               (item.npwp?.toLowerCase().includes(q)) ||
               (item.address?.toLowerCase().includes(q))
    })
})

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
            po_masuk_path: form.value.po_masuk_path,
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
    appStore.showConfirm(
        'Hapus Perusahaan',
        'Yakin ingin menghapus data ini?',
        async () => {
            try {
                await api.delete(id)
                await fetchData()
                appStore.showNotification('Sukses', 'Perusahaan berhasil dihapus.', 'success')
            } catch (e) {
                appStore.showNotification('Gagal', 'Gagal menghapus data.', 'error')
            }
        }
    )
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

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indofilter"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredItems.length === 0" class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-400">
            {{ search ? 'Tidak menemukan perusahaan yang cocok.' : 'Belum ada data perusahaan.' }}
        </div>

        <!-- Grid Cards 2x2 Layout -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="company in filteredItems" :key="company.id" class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200 p-6 flex flex-col justify-between">
                <div>
                    <!-- Header: Logo/Initials + Name & Alias + Actions -->
                    <div class="flex items-start justify-between mb-4 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg select-none">
                                {{ company.alias ? company.alias.substring(0, 2).toUpperCase() : company.name.substring(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 text-base leading-tight">{{ company.name }}</h3>
                                <span v-if="company.alias" class="text-xs text-blue-600 font-semibold uppercase tracking-wider">{{ company.alias }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <button @click="openEdit(company)" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button @click="confirmDelete(company.id)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Hapus">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Details Content -->
                    <div class="space-y-4">
                        <!-- Contact Info -->
                        <div class="space-y-2">
                            <!-- Email -->
                            <div class="flex items-start gap-2.5 text-sm text-gray-600">
                                <svg class="w-4.5 h-4.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                <span class="break-all">{{ company.email || '-' }}</span>
                            </div>
                            <!-- Phone -->
                            <div class="flex items-start gap-2.5 text-sm text-gray-600">
                                <svg class="w-4.5 h-4.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span>{{ company.phone || '-' }}</span>
                            </div>
                            <!-- NPWP -->
                            <div class="flex items-start gap-2.5 text-sm text-gray-600">
                                <svg class="w-4.5 h-4.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <span>NPWP: <span class="font-semibold text-gray-700">{{ company.npwp || '-' }}</span></span>
                            </div>
                            <!-- PO Masuk Path -->
                            <div class="flex items-start gap-2.5 text-sm text-gray-600">
                                <svg class="w-4.5 h-4.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <span>Folder PO: <span class="font-semibold text-gray-700 break-all">{{ company.po_masuk_path || '-' }}</span></span>
                            </div>
                            <!-- Address -->
                            <div class="flex items-start gap-2.5 text-sm text-gray-600">
                                <svg class="w-4.5 h-4.5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span class="leading-relaxed">{{ company.address || '-' }}</span>
                            </div>
                        </div>

                        <!-- Additional Phones -->
                        <div v-if="company.phones && company.phones.length > 0" class="pt-3 border-t border-gray-100">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Telepon Tambahan</h4>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="p in company.phones" :key="p.id" class="inline-flex items-center text-xs bg-gray-50 text-gray-600 px-2.5 py-1 rounded-lg border border-gray-150 font-medium">
                                    {{ p.label ? p.label + ': ' : '' }}{{ p.phone }}
                                </span>
                            </div>
                        </div>

                        <!-- Bank Accounts -->
                        <div v-if="company.bank_accounts && company.bank_accounts.length > 0" class="pt-3 border-t border-gray-100">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Rekening Bank</h4>
                            <div class="grid grid-cols-1 gap-2">
                                <div v-for="acc in company.bank_accounts" :key="acc.id" class="p-3 bg-blue-50/20 border border-blue-100/50 rounded-xl flex items-center justify-between text-xs">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-gray-800">{{ acc.bank_name }}</span>
                                            <span v-if="acc.is_default" class="text-[9px] font-bold bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded uppercase tracking-wider">Utama</span>
                                        </div>
                                        <div class="text-gray-500 mt-0.5">An. {{ acc.account_name }}</div>
                                    </div>
                                    <div class="font-mono font-bold text-blue-600 bg-blue-50/50 px-2.5 py-1 rounded-lg border border-blue-100/20">{{ acc.account_number }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Path Folder PO Masuk</label>
                        <input v-model="form.po_masuk_path" placeholder="contoh: D:\PO_MASUK" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
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
