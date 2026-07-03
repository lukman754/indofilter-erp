<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { kas as api } from '../../api/index.js'

const router = useRouter()

const year    = ref(new Date().getFullYear())
const month   = ref(0)
const data    = ref(null)
const loading = ref(false)

const months = [
    { value: 0,  label: 'Semua Bulan' },
    { value: 1,  label: 'Januari' },  { value: 2,  label: 'Februari' },
    { value: 3,  label: 'Maret' },    { value: 4,  label: 'April' },
    { value: 5,  label: 'Mei' },      { value: 6,  label: 'Juni' },
    { value: 7,  label: 'Juli' },     { value: 8,  label: 'Agustus' },
    { value: 9,  label: 'September' },{ value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

const years = computed(() => {
    const y = new Date().getFullYear()
    return Array.from({ length: 5 }, (_, i) => y - i)
})

function formatMoney(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

async function loadSummary() {
    loading.value = true
    try {
        const params = { year: year.value }
        if (month.value) params.month = month.value
        const res = await api.summary(params)
        data.value = res.data
    } catch {
        data.value = null
    } finally {
        loading.value = false
    }
}

watch([year, month], loadSummary)
onMounted(loadSummary)
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <button @click="router.push('/kas')"
                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Laporan Kas</h1>
                </div>
            </div>
            <!-- Period filter -->
            <div class="flex items-center gap-2">
                <select v-model="month"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <select v-model="year"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg class="animate-spin h-6 w-6 text-indofilter" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
        </div>

        <template v-else-if="data">
            <!-- Overall stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Pemasukan</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ formatMoney(data.overall_in) }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Pengeluaran</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ formatMoney(data.overall_out) }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Saldo Periode</p>
                            <p class="text-2xl font-bold mt-1"
                                :class="data.overall_balance >= 0 ? 'text-gray-800' : 'text-red-600'">
                                {{ formatMoney(data.overall_balance) }}
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indofilter" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per-account table -->
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-700">Rincian per Akun Kas</h2>
                </div>

                <div v-if="!data.accounts.length" class="text-center py-12 text-sm text-gray-400">
                    Tidak ada data untuk periode ini
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Akun Kas</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pemasukan</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pengeluaran</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Transaksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr v-for="acc in data.accounts" :key="acc.cash_account_id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ acc.account_name }}</td>
                                <td class="px-4 py-3 text-sm text-right text-green-700 font-medium">{{ formatMoney(acc.total_in) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-red-600 font-medium">{{ formatMoney(acc.total_out) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold"
                                    :class="acc.balance >= 0 ? 'text-gray-800' : 'text-red-600'">
                                    {{ formatMoney(acc.balance) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-500">{{ acc.transaction_count }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-700">Total</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-green-700">{{ formatMoney(data.overall_in) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-red-600">{{ formatMoney(data.overall_out) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold"
                                    :class="data.overall_balance >= 0 ? 'text-gray-800' : 'text-red-600'">
                                    {{ formatMoney(data.overall_balance) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-500">
                                    {{ data.accounts.reduce((s, a) => s + a.transaction_count, 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
