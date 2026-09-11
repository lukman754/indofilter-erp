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
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <article v-for="company in filteredItems" :key="company.id" class="overflow-hidden rounded-[10px] bg-white border border-slate-200 shadow-sm transition-all hover:shadow-md flex flex-col">
                <div class="h-[3px] w-full bg-[#2A7C13]"></div>
                
                <div class="px-4 py-4 flex-1 flex flex-col">
                    <!-- Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-[14px] font-bold leading-[1.4] tracking-[-0.005em] text-[#15171f]">{{ company.name }}</h3>
                            <div class="mt-1 flex items-center gap-2" v-if="company.alias">
                                <span class="inline-flex shrink-0 items-center px-1.5 py-0.5 rounded-sm bg-[#2A7C13]/10 text-[9px] font-bold text-[#2A7C13] uppercase tracking-wider">{{ company.alias }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button @click="openEdit(company)" class="p-1.5 text-slate-400 hover:text-[#2A7C13] hover:bg-[#2A7C13]/10 rounded transition-all" title="Edit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <button @click="confirmDelete(company.id)" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition-all" title="Hapus">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Contact Info block mimicking document card -->
                    <div class="mt-4 flex items-center gap-3 border-t border-[#f1f2f7] pt-3">
                        <div class="grid h-8 w-8 shrink-0 place-items-center rounded-[9px] bg-[#2A7C13]/20 text-[10px] font-extrabold text-[#2A7C13]">
                            {{ company.alias ? company.alias.substring(0, 2).toUpperCase() : company.name.substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[10px] font-bold text-[#15171f]">{{ company.email || 'Tanpa Email' }}</div>
                            <div class="truncate text-[8px] font-medium text-[#9296a3]">
                                <span class="font-semibold text-[#2A7C13]">{{ company.phone || '-' }}</span>
                            </div>
                        </div>
                        <a v-if="company.phone" :href="`tel:${company.phone}`" class="grid h-7 w-7 shrink-0 place-items-center rounded-[8px] bg-[#f4f5f9] text-[#63687a]" title="Telepon">
                            <svg class="h-3.5 w-3.5 fill-none stroke-current stroke-[1.8]" viewBox="0 0 24 24"><path d="M6 4h3l1.5 4L8.5 9.5a11 11 0 0 0 6 6l1.5-2L20 15v3a2 2 0 0 1-2 2C10.8 20 4 13.2 4 6a2 2 0 0 1 2-2z"/></svg>
                        </a>
                    </div>
                    
                    <div class="mt-3 flex-1 flex flex-col gap-2.5 text-[10px] text-slate-600">
                        <!-- NPWP -->
                        <div class="flex items-start gap-2">
                            <span class="font-bold shrink-0 w-14 text-slate-800">NPWP</span>
                            <span class="text-slate-600 break-all">{{ company.npwp || '-' }}</span>
                        </div>
                        <!-- PO Folder -->
                        <div class="flex items-start gap-2">
                            <span class="font-bold shrink-0 w-14 text-slate-800">Folder PO</span>
                            <span class="text-slate-600 break-all">{{ company.po_masuk_path || '-' }}</span>
                        </div>
                        <!-- Alamat -->
                        <div class="flex items-start gap-2">
                            <span class="font-bold shrink-0 w-14 text-slate-800">Alamat</span>
                            <span class="text-slate-600 line-clamp-2 leading-relaxed" :title="company.address">{{ company.address || '-' }}</span>
                        </div>
                    </div>

                    <!-- Additional Phones -->
                    <div v-if="company.phones && company.phones.length > 0" class="mt-4 pt-3 border-t border-[#f1f2f7]">
                        <h4 class="text-[9px] font-bold text-[#9296a3] uppercase tracking-wider mb-2">Telepon Tambahan</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="p in company.phones" :key="p.id" class="inline-flex items-center text-[9px] bg-slate-50 text-slate-700 px-2 py-1 rounded-[6px] border border-slate-200 font-medium">
                                {{ p.label ? p.label + ': ' : '' }}{{ p.phone }}
                            </span>
                        </div>
                    </div>

                    <!-- Bank Accounts -->
                    <div v-if="company.bank_accounts && company.bank_accounts.length > 0" class="mt-4 pt-3 border-t border-[#f1f2f7]">
                        <h4 class="text-[9px] font-bold text-[#9296a3] uppercase tracking-wider mb-2">Rekening Bank</h4>
                        <div class="grid grid-cols-1 gap-1.5">
                            <div v-for="acc in company.bank_accounts" :key="acc.id" class="px-2 py-2 bg-[#2A7C13]/10 rounded-[6px] flex items-center justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold text-black">{{ acc.bank_name }}</span>
                                        <span v-if="acc.is_default" class="text-[8px] font-bold bg-[#2A7C13] text-white px-1 py-0.5 rounded uppercase tracking-wider">Utama</span>
                                    </div>
                                    <div class="text-[9px] text-[#2A7C13] font-semibold mt-0.5 truncate">An. {{ acc.account_name }}</div>
                                </div>
                                <div class="text-[10px] font-bold text-[#2A7C13] bg-white px-1.5 py-0.5 rounded-[4px] border border-[#2A7C13]/20">{{ acc.account_number }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
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
