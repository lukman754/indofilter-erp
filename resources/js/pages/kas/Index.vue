<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { kas as api } from '../../api/index.js'
import { useAppStore } from '../../stores/app.js'

const router = useRouter()
const appStore = useAppStore()

const accounts          = ref([])
const transactions      = ref([])
const loading           = ref(false)
const txLoading         = ref(false)
const selectedAccountId = ref(null)
const filterDateFrom    = ref('')
const filterDateTo      = ref('')
const filterType        = ref('')
const searchQuery       = ref('')

const filterMonth = ref(new Date().getMonth() + 1) // 1-indexed, e.g. 7 for July
const filterYear  = ref(new Date().getFullYear())
const startingBalance = ref(0)
const endingBalance   = ref(0)

const months = [
    { value: 0, label: 'Semua Bulan' },
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },   { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },     { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },{ value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },{ value: 12, label: 'Desember' },
]

const years = computed(() => {
    const y = new Date().getFullYear()
    return Array.from({ length: 5 }, (_, i) => y - i)
})

const showAccountModal = ref(false)
const showTxModal      = ref(false)
const editingAccount   = ref(null)
const editingTx        = ref(null)
const savingAccount    = ref(false)
const savingTx         = ref(false)

const accountForm  = ref({ name: '', description: '' })
const txForm       = ref({ cash_account_id: '', type: 'in', amount: '', description: '', pic: '', date: today() })
const amountDisplay = ref('')

function formatAmountDisplay(val) {
    const num = parseFloat(String(val).replace(/[^0-9]/g, '')) || 0
    if (!num) return ''
    return new Intl.NumberFormat('id-ID').format(num)
}

function onAmountInput(e) {
    const raw = e.target.value.replace(/[^0-9]/g, '')
    const num = parseFloat(raw) || 0
    txForm.value.amount = num
    amountDisplay.value = num ? new Intl.NumberFormat('id-ID').format(num) : ''
}

function today() {
    return new Date().toISOString().split('T')[0]
}
function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}
function showNotification(title, message, type = 'success') {
    appStore.showNotification(title, message, type)
}
function showConfirm(title, message, onConfirm) {
    appStore.showConfirm(title, message, onConfirm)
}

const selectedAccount      = computed(() => accounts.value.find(a => a.id === selectedAccountId.value))
const filteredTransactions = computed(() => {
    let list = transactions.value
    if (filterType.value) {
        list = list.filter(t => t.type === filterType.value)
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase()
        list = list.filter(t =>
            (t.description && t.description.toLowerCase().includes(q)) ||
            (t.pic && t.pic.toLowerCase().includes(q)) ||
            String(t.amount).includes(q)
        )
    }
    return list
})
const periodTotalIn  = computed(() => filteredTransactions.value.filter(t => t.type === 'in').reduce((s, t) => s + t.amount, 0))
const periodTotalOut = computed(() => filteredTransactions.value.filter(t => t.type === 'out').reduce((s, t) => s + t.amount, 0))
const overallBalance = computed(() => accounts.value.reduce((s, a) => s + (a.balance || 0), 0))

async function loadAccounts() {
    loading.value = true
    try {
        const res = await api.listAccounts()
        accounts.value = res.data
        if (!selectedAccountId.value && accounts.value.length) {
            selectedAccountId.value = accounts.value[0].id
        }
    } catch {
        showNotification('Error', 'Gagal memuat akun kas', 'error')
    } finally {
        loading.value = false
    }
}

async function loadTransactions() {
    if (!selectedAccountId.value) { transactions.value = []; return }
    txLoading.value = true
    try {
        const params = { account_id: selectedAccountId.value }
        if (filterDateFrom.value) params.date_from = filterDateFrom.value
        if (filterDateTo.value)   params.date_to   = filterDateTo.value
        const res = await api.listTransactions(params)
        transactions.value = res.data.transactions || []
        startingBalance.value = res.data.starting_balance || 0
        endingBalance.value = res.data.ending_balance || 0
    } catch {
        showNotification('Error', 'Gagal memuat transaksi', 'error')
    } finally {
        txLoading.value = false
    }
}

// Watch month and year to set Date From & Date To
watch([filterYear, filterMonth], () => {
    if (filterMonth.value === 0) {
        filterDateFrom.value = `${filterYear.value}-01-01`
        filterDateTo.value = `${filterYear.value}-12-31`
    } else {
        const m = String(filterMonth.value).padStart(2, '0')
        filterDateFrom.value = `${filterYear.value}-${m}-01`
        const lastDay = new Date(filterYear.value, filterMonth.value, 0).getDate()
        filterDateTo.value = `${filterYear.value}-${m}-${String(lastDay).padStart(2, '0')}`
    }
}, { immediate: true })

watch(selectedAccountId, loadTransactions)
watch([filterDateFrom, filterDateTo], loadTransactions)


// --- Account CRUD ---
function openCreateAccount() {
    editingAccount.value = null
    accountForm.value = { name: '', description: '' }
    showAccountModal.value = true
}
function openEditAccount(acc) {
    editingAccount.value = acc
    accountForm.value = { name: acc.name, description: acc.description || '' }
    showAccountModal.value = true
}
async function saveAccount() {
    savingAccount.value = true
    try {
        if (editingAccount.value) {
            await api.updateAccount(editingAccount.value.id, accountForm.value)
            showNotification('Sukses', 'Akun kas berhasil diperbarui')
        } else {
            await api.createAccount(accountForm.value)
            showNotification('Sukses', 'Akun kas berhasil dibuat')
        }
        showAccountModal.value = false
        await loadAccounts()
    } catch (e) {
        showNotification('Error', e.response?.data?.message || 'Gagal menyimpan akun kas', 'error')
    } finally {
        savingAccount.value = false
    }
}
function confirmDeleteAccount(acc) {
    showConfirm('Hapus Akun Kas', `Hapus akun "${acc.name}"? Semua transaksi yang terkait juga akan dihapus.`, async () => {
        try {
            await api.deleteAccount(acc.id)
            if (selectedAccountId.value === acc.id) selectedAccountId.value = null
            await loadAccounts()
            showNotification('Sukses', 'Akun kas berhasil dihapus')
        } catch {
            showNotification('Error', 'Gagal menghapus akun kas', 'error')
        }
    })
}

// --- Transaction CRUD ---
function openCreateTx() {
    editingTx.value = null
    txForm.value = { cash_account_id: selectedAccountId.value || '', type: 'in', amount: '', description: '', pic: '', date: today() }
    amountDisplay.value = ''
    showTxModal.value = true
}
function openEditTx(tx) {
    editingTx.value = tx
    txForm.value = { cash_account_id: tx.cash_account_id, type: tx.type, amount: tx.amount, description: tx.description || '', pic: tx.pic || '', date: tx.date }
    amountDisplay.value = formatAmountDisplay(tx.amount)
    showTxModal.value = true
}

async function toggleMark(tx) {
    try {
        const nextState = !tx.is_marked;
        await api.updateTransaction(tx.id, { is_marked: nextState });
        tx.is_marked = nextState;
        showNotification('Sukses', nextState ? 'Transaksi ditandai' : 'Tanda transaksi dihapus');
    } catch {
        showNotification('Error', 'Gagal mengubah status penanda', 'error');
    }
}
async function saveTx() {
    savingTx.value = true
    try {
        const data = { ...txForm.value, amount: parseFloat(txForm.value.amount) }
        if (editingTx.value) {
            await api.updateTransaction(editingTx.value.id, data)
            showNotification('Sukses', 'Transaksi berhasil diperbarui')
        } else {
            await api.createTransaction(data)
            showNotification('Sukses', 'Transaksi berhasil dicatat')
        }
        showTxModal.value = false
        await loadAccounts()
        await loadTransactions()
    } catch (e) {
        showNotification('Error', e.response?.data?.message || 'Gagal menyimpan transaksi', 'error')
    } finally {
        savingTx.value = false
    }
}
function confirmDeleteTx(tx) {
    showConfirm('Hapus Transaksi', 'Yakin ingin menghapus transaksi ini?', async () => {
        try {
            await api.deleteTransaction(tx.id)
            await loadAccounts()
            await loadTransactions()
            showNotification('Sukses', 'Transaksi berhasil dihapus')
        } catch {
            showNotification('Error', 'Gagal menghapus transaksi', 'error')
        }
    })
}

onMounted(async () => {
    await loadAccounts()
    await loadTransactions()
})
</script>

<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h1 class="text-lg font-bold text-gray-800">Buku Kas</h1>
                <p class="text-sm text-gray-500 mt-0.5">Total saldo semua akun: <span class="font-semibold text-gray-700">{{ formatMoney(overallBalance) }}</span></p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="router.push('/kas/summary')"
                    class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Laporan
                </button>
                <button @click="openCreateTx" :disabled="!selectedAccountId"
                    class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 shadow-sm disabled:opacity-40">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Catat Transaksi
                </button>
            </div>
        </div>

        <div class="flex gap-4">
            <!-- ===== SIDEBAR AKUN KAS ===== -->
            <div class="w-56 flex-shrink-0 flex flex-col gap-2">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-gray-400 uppercase">Akun Kas</p>
                    <button @click="openCreateAccount"
                        class="text-xs text-indofilter hover:text-indofilter-dark font-medium flex items-center gap-1 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah
                    </button>
                </div>

                <div v-if="loading" class="text-center text-sm text-gray-400 py-6">Memuat...</div>
                <div v-else-if="accounts.length === 0"
                    class="bg-white border border-gray-200 rounded-lg px-4 py-6 text-center text-sm text-gray-400">
                    Belum ada akun kas
                </div>

                <div
                    v-for="acc in accounts" :key="acc.id"
                    @click="selectedAccountId = acc.id"
                    :class="[
                        'bg-white border rounded-lg px-3 py-3 cursor-pointer transition-colors group',
                        selectedAccountId === acc.id
                            ? 'border-indofilter bg-blue-50/40'
                            : 'border-gray-200 hover:border-gray-300'
                    ]"
                >
                    <div class="flex items-center justify-between gap-1 mb-1.5">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ acc.name }}</p>
                        <div class="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                            <button @click.stop="openEditAccount(acc)"
                                class="p-1 text-gray-400 hover:text-gray-600 rounded">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button @click.stop="confirmDeleteAccount(acc)"
                                class="p-1 text-gray-400 hover:text-red-500 rounded">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="acc.description" class="text-xs text-gray-400 truncate mb-1.5">{{ acc.description }}</p>
                    <p class="text-sm font-semibold" :class="acc.balance >= 0 ? 'text-gray-800' : 'text-red-600'">
                        {{ formatMoney(acc.balance) }}
                    </p>
                </div>
            </div>

            <!-- ===== PANEL KANAN ===== -->
            <div class="flex-1 min-w-0">
                <!-- Belum pilih akun -->
                <div v-if="!selectedAccountId"
                    class="bg-white rounded-lg border border-gray-200 flex items-center justify-center py-20">
                    <p class="text-sm text-gray-400">Pilih akun kas di sebelah kiri</p>
                </div>

                <template v-else>
                    <!-- Summary cards -->
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <p class="text-sm text-gray-500 font-medium">Pemasukan</p>
                            <p class="text-xl font-bold text-gray-800 mt-1">{{ formatMoney(periodTotalIn) }}</p>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <p class="text-sm text-gray-500 font-medium">Pengeluaran</p>
                            <p class="text-xl font-bold text-gray-800 mt-1">{{ formatMoney(periodTotalOut) }}</p>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <p class="text-sm text-gray-500 font-medium">Saldo — {{ selectedAccount?.name }}</p>
                            <p class="text-xl font-bold mt-1"
                                :class="endingBalance >= 0 ? 'text-gray-800' : 'text-red-600'">
                                {{ formatMoney(endingBalance) }}
                            </p>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <!-- Search -->
                        <input v-model="searchQuery" type="text" placeholder="Cari keterangan, PIC..."
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none w-52" />
                        <select v-model="filterMonth"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <select v-model="filterYear"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <select v-model="filterType"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">Semua Jenis</option>

                            <option value="in">Pemasukan</option>
                            <option value="out">Pengeluaran</option>
                        </select>

                        <button v-if="filterMonth !== (new Date().getMonth() + 1) || filterYear !== new Date().getFullYear() || filterType"
                            @click="filterMonth = new Date().getMonth() + 1; filterYear = new Date().getFullYear(); filterType=''"
                            class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Reset
                        </button>
                    </div>


                    <!-- Transaction table -->
                    <div class="bg-white rounded-lg border border-gray-200">
                        <div v-if="txLoading" class="text-center py-10 text-sm text-gray-400">Memuat transaksi...</div>
                        <div v-else-if="filteredTransactions.length === 0"
                            class="text-center py-16 text-sm text-gray-400">
                            Belum ada transaksi
                            <br />
                            <button @click="openCreateTx" class="mt-2 text-indofilter hover:underline text-sm">Catat sekarang</button>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="w-10 px-4 py-3 text-center"></th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PIC</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Masuk</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Keluar</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="(tx, i) in filteredTransactions" :key="tx.id"
                                        :class="[
                                            tx.is_marked ? 'bg-yellow-50/70 hover:bg-yellow-100/70' : (i % 2 === 0 ? 'bg-white' : 'bg-gray-50')
                                        ]"
                                        class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-4 py-3 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="!!tx.is_marked"
                                                @change="toggleMark(tx)"
                                                class="rounded border-gray-300 text-indofilter focus:ring-indofilter cursor-pointer h-4 w-4"
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ formatDate(tx.date) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <span v-if="tx.description">{{ tx.description }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                            <span v-if="tx.pic" class="bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-full font-medium">{{ tx.pic }}</span>
                                            <span v-else class="text-gray-300">-</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">
                                            <span v-if="tx.type === 'in'" class="text-green-700 font-medium">{{ formatMoney(tx.amount) }}</span>
                                            <span v-else class="text-gray-300">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">
                                            <span v-if="tx.type === 'out'" class="text-red-600 font-medium">{{ formatMoney(tx.amount) }}</span>
                                            <span v-else class="text-gray-300">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-medium whitespace-nowrap"
                                            :class="tx.running_balance >= 0 ? 'text-gray-700' : 'text-red-600'">
                                            {{ formatMoney(tx.running_balance) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">

                                            <div class="flex justify-center gap-1">
                                                <button @click="openEditTx(tx)"
                                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </button>
                                                <button @click="confirmDeleteTx(tx)"
                                                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Modal: Akun Kas -->
    <Teleport to="body">
        <div v-if="showAccountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">
                    {{ editingAccount ? 'Edit Akun Kas' : 'Tambah Akun Kas' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Nama Akun <span class="text-red-500">*</span></label>
                        <input v-model="accountForm.name" type="text" placeholder="mis. Kas Umum, Kas Parkir, Kasbon..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Keterangan</label>
                        <textarea v-model="accountForm.description" rows="2" placeholder="Opsional"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"></textarea>
                    </div>
                </div>
                <div class="flex gap-2 mt-5">
                    <button @click="showAccountModal = false"
                        class="flex-1 px-4 py-2 border border-gray-300 text-sm text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button @click="saveAccount" :disabled="savingAccount"
                        class="flex-1 px-4 py-2 bg-indofilter hover:bg-indofilter-dark text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50">
                        {{ savingAccount ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Transaksi -->
    <Teleport to="body">
        <div v-if="showTxModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">
                    {{ editingTx ? 'Edit Transaksi' : 'Catat Transaksi Kas' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Jenis <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <button @click="txForm.type = 'in'"
                                :class="txForm.type === 'in' ? 'bg-green-100 border-green-400 text-green-700 font-semibold' : 'bg-white border-gray-300 text-gray-600'"
                                class="flex-1 py-2 border rounded-lg text-sm transition-colors">
                                ↑ Pemasukan
                            </button>
                            <button @click="txForm.type = 'out'"
                                :class="txForm.type === 'out' ? 'bg-red-100 border-red-400 text-red-700 font-semibold' : 'bg-white border-gray-300 text-gray-600'"
                                class="flex-1 py-2 border rounded-lg text-sm transition-colors">
                                ↓ Pengeluaran
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Nominal <span class="text-red-500">*</span></label>
                        <div>
                            <input
                                :value="amountDisplay"
                                @input="onAmountInput"
                                type="text"
                                inputmode="numeric"
                                placeholder="Rp 0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <p v-if="txForm.amount" class="text-xs text-gray-400 mt-1">
                            {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(txForm.amount) }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input v-model="txForm.date" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">PIC / Pengguna <span class="text-gray-400">(Opsional)</span></label>
                        <input v-model="txForm.pic" type="text" placeholder="mis. John Doe (Reimburse)"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Keterangan</label>
                        <textarea v-model="txForm.description" rows="2" placeholder="Opsional"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"></textarea>
                    </div>

                </div>
                <div class="flex gap-2 mt-5">
                    <button @click="showTxModal = false"
                        class="flex-1 px-4 py-2 border border-gray-300 text-sm text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button @click="saveTx" :disabled="savingTx"
                        class="flex-1 px-4 py-2 bg-indofilter hover:bg-indofilter-dark text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50">
                        {{ savingTx ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
