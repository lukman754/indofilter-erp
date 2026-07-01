<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { dashboard, documents as docsApi } from "../api/index.js";

const router = useRouter();
const stats = ref(null);
const docs = ref([]);
const loading = ref(true);
const showDropdown = ref(false);

// Filter states
const search = ref("");
const activeType = ref("");
const activeStatus = ref("");
const currentPage = ref(1);
const perPage = ref(10);

const statCards = [
    {
        key: "quotation",
        label: "Quotations",
        icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
    },
    {
        key: "invoice",
        label: "Invoice",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2",
    },
    {
        key: "delivery_slip",
        label: "Surat Jalan",
        icon: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4",
    },
    {
        key: "purchase_order",
        label: "Purchase Order",
        icon: "M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z",
    },
];

const documentTypesList = [
    { type: "Quotation", label: "Quotation" },
    { type: "Proforma Invoice", label: "Proforma Invoice" },
    { type: "Invoice", label: "Invoice" },
    { type: "Delivery Slip", label: "Delivery Slip" },
    { type: "Delivery Address", label: "Delivery Address" },
    { type: "Purchase Order", label: "Purchase Order" },
];

const filterTypes = [
    { value: "", label: "Semua Tipe" },
    { value: "Quotation", label: "Quotation" },
    { value: "Proforma Invoice", label: "Proforma Invoice" },
    { value: "Invoice", label: "Invoice" },
    { value: "Delivery Slip", label: "Surat Jalan" },
    { value: "Delivery Address", label: "Alamat Kirim" },
    { value: "Purchase Order", label: "Purchase Order" },
];

const statuses = [
    { value: "", label: "Semua Status" },
    { value: "draft", label: "Draft" },
    { value: "confirmed", label: "Confirmed" },
    { value: "canceled", label: "Canceled" },
];

const statusColors = {
    draft: "bg-gray-100 text-gray-600",
    confirmed: "bg-green-100 text-green-700",
    canceled: "bg-red-100 text-red-600",
};

function formatDate(d) {
    if (!d) return "-";
    return new Date(d).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

function formatMoney(n) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(n || 0);
}

async function fetchDashboard() {
    loading.value = true;
    try {
        const [statsRes, docsRes] = await Promise.all([
            dashboard.stats(),
            docsApi.list(),
        ]);
        stats.value = statsRes.data;
        docs.value = docsRes.data.data || docsRes.data || [];
    } catch (e) {
        stats.value = {};
        docs.value = [];
    } finally {
        loading.value = false;
    }
}

function selectCreateType(type) {
    router.push({ path: "/documents/create", query: { type } });
    showDropdown.value = false;
}

// Filtering logic
const filteredDocs = computed(() => {
    let result = docs.value;
    if (search.value) {
        const s = search.value.toLowerCase();
        result = result.filter(
            (r) =>
                r.number?.toLowerCase().includes(s) ||
                r.partner?.name?.toLowerCase().includes(s),
        );
    }
    if (activeType.value) {
        result = result.filter((r) => r.document_type === activeType.value);
    }
    if (activeStatus.value) {
        result = result.filter((r) => r.status === activeStatus.value);
    }
    return result;
});

// Pagination logic
const totalPages = computed(() => {
    return Math.ceil(filteredDocs.value.length / perPage.value) || 1;
});

const paginatedDocs = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return filteredDocs.value.slice(start, end);
});

watch([search, activeType, activeStatus], () => {
    currentPage.value = 1;
});

// Action handlers
async function handleConfirm(id) {
    if (!confirm("Konfirmasi dokumen ini?")) return;
    const doc = docs.value.find(d => d.id === id);
    let overwrite = false;
    if (doc && (doc.document_number || doc.number)) {
        try {
            const docNum = doc.document_number || doc.number;
            const checkRes = await docsApi.checkLocalFile({ number: docNum });
            if (checkRes.data && checkRes.data.path_configured && checkRes.data.exists) {
                if (!window.confirm(`Berkas "${checkRes.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`)) {
                    return;
                }
                overwrite = true;
            }
        } catch (e) {
            console.error("Gagal memeriksa berkas lokal", e);
        }
    }
    try {
        await docsApi.confirm(id, { overwrite });
        await fetchDashboard();
    } catch (e) {
        alert("Gagal mengkonfirmasi dokumen");
    }
}

async function handleCancel(id) {
    if (!confirm("Batalkan dokumen ini?")) return;
    try {
        await docsApi.cancel(id);
        await fetchDashboard();
    } catch (e) {
        alert("Gagal membatalkan dokumen");
    }
}

async function handleDelete(id) {
    if (!confirm("Yakin ingin menghapus dokumen ini?")) return;
    try {
        await docsApi.delete(id);
        await fetchDashboard();
    } catch (e) {
        alert("Gagal menghapus dokumen");
    }
}

async function handleExport(id) {
    try {
        let overwrite = false;
        let res = await docsApi.export(id, { overwrite: false });
        
        if (res.data && res.data.exists) {
            if (window.confirm(`Berkas "${res.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`)) {
                res = await docsApi.export(id, { overwrite: true });
            } else {
                return;
            }
        }
        
        if (res.data && res.data.success) {
            alert(res.data.message);
        } else {
            alert(res.data.message || 'Gagal mengekspor dokumen');
        }
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal mengekspor dokumen');
    }
}

onMounted(fetchDashboard);
</script>

<template>
    <div>
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg
                class="animate-spin h-8 w-8 text-indofilter"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </div>

        <template v-if="!loading">
            <!-- Stats Cards -->
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"
            >
                <div
                    v-for="card in statCards"
                    :key="card.key"
                    class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">
                                {{ card.label }}
                            </p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">
                                {{ stats?.[card.key] || 0 }}
                            </p>
                        </div>
                        <div
                            class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center"
                        >
                            <svg
                                class="w-6 h-6 text-indofilter"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    :d="card.icon"
                                ></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions (Dropdown Button) -->
            <div class="relative inline-block mb-6">
                <!-- Dropdown Backdrop to close click outside -->
                <div
                    v-if="showDropdown"
                    @click="showDropdown = false"
                    class="fixed inset-0 z-10"
                ></div>

                <button
                    @click="showDropdown = !showDropdown"
                    class="relative z-20 bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 shadow-sm"
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        ></path>
                    </svg>
                    <span>Buat Dokumen Baru</span>
                    <svg
                        class="w-4 h-4 transition-transform duration-200"
                        :class="showDropdown ? 'rotate-180' : ''"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        ></path>
                    </svg>
                </button>

                <div
                    v-if="showDropdown"
                    class="absolute left-0 mt-2 w-64 rounded-lg shadow-lg bg-white border border-gray-100 z-20 overflow-hidden py-1"
                >
                    <button
                        v-for="t in documentTypesList"
                        :key="t.type"
                        @click="selectCreateType(t.type)"
                        class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors flex items-center justify-between border-b last:border-0 border-gray-50"
                    >
                        <span>{{ t.label }}</span>
                        <svg
                            class="w-3.5 h-3.5 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            ></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Document List Section with filters & pagination -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div
                    class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/50"
                >
                    <h2 class="text-base font-semibold text-gray-800">
                        Daftar Semua Dokumen
                    </h2>

                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari nomor/partner..."
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none w-48 bg-white"
                        />
                        <select
                            v-model="activeType"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 outline-none bg-white"
                        >
                            <option
                                v-for="t in filterTypes"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                        <select
                            v-model="activeStatus"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 outline-none bg-white"
                        >
                            <option
                                v-for="s in statuses"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    No. Dokumen
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Tipe
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Referensi
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Partner
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Tanggal
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-if="paginatedDocs.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-8 text-center text-gray-400"
                                >
                                    Belum ada dokumen / hasil filter kosong
                                </td>
                            </tr>
                            <tr
                                v-for="(doc, i) in paginatedDocs"
                                :key="doc.id"
                                class="hover:bg-blue-50/40 transition-colors"
                                :class="
                                    i % 2 === 0 ? 'bg-white' : 'bg-gray-50/30'
                                "
                            >
                                <td
                                    class="px-4 py-3 text-sm font-medium text-gray-800"
                                >
                                    {{ doc.number || doc.id }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ doc.document_type?.replace(/_/g, " ") }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <router-link
                                        v-if="doc.reference"
                                        :to="{
                                            path:
                                                '/documents/' +
                                                doc.reference.id,
                                            query: {
                                                type: doc.reference
                                                    .document_type,
                                            },
                                        }"
                                        class="text-xs font-semibold text-indofilter hover:underline"
                                    >
                                        {{
                                            doc.reference.document_number ||
                                            `Draft #${doc.reference.id}`
                                        }}
                                    </router-link>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ doc.partner?.name || "-" }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    {{ formatDate(doc.date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full"
                                        :class="
                                            statusColors[doc.status] ||
                                            'bg-gray-100 text-gray-600'
                                        "
                                    >
                                        {{ doc.status }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-sm text-gray-800 text-right font-medium"
                                >
                                    {{ formatMoney(doc.grand_total) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <div
                                        class="flex items-center justify-center gap-2.5"
                                    >
                                        <button
                                            @click="
                                                router.push({
                                                    path:
                                                        '/documents/' + doc.id,
                                                    query: {
                                                        type: doc.document_type,
                                                    },
                                                })
                                            "
                                            class="text-gray-500 hover:text-gray-800 transition-colors"
                                            title="Lihat"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                ></path>
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                ></path>
                                            </svg>
                                        </button>
                                        <button
                                            v-if="
                                                doc.status === 'draft' ||
                                                doc.status === 'confirmed'
                                            "
                                            @click="
                                                router.push({
                                                    path:
                                                        '/documents/' +
                                                        doc.id +
                                                        '/edit',
                                                    query: {
                                                        type: doc.document_type,
                                                    },
                                                })
                                            "
                                            class="text-blue-600 hover:text-blue-800 transition-colors"
                                            title="Edit"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                                ></path>
                                            </svg>
                                        </button>
                                        <button
                                            v-if="doc.status === 'draft'"
                                            @click="handleConfirm(doc.id)"
                                            class="text-green-600 hover:text-green-800 transition-colors"
                                            title="Konfirmasi"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M5 13l4 4L19 7"
                                                ></path>
                                            </svg>
                                        </button>
                                        <button
                                            v-if="doc.status === 'draft'"
                                            @click="handleCancel(doc.id)"
                                            class="text-orange-500 hover:text-orange-700 transition-colors"
                                            title="Batalkan"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                ></path>
                                            </svg>
                                        </button>
                                        <button
                                            @click="handleExport(doc.id)"
                                            class="text-purple-500 hover:text-purple-700 transition-colors"
                                            title="Export DOCX"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                ></path>
                                            </svg>
                                        </button>
                                        <button
                                            @click="handleDelete(doc.id)"
                                            class="text-red-500 hover:text-red-700 transition-colors"
                                            title="Hapus"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                ></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination controls -->
                <div
                    class="px-5 py-3 border-t border-gray-200 flex items-center justify-between bg-gray-50/50"
                >
                    <div class="text-xs text-gray-500">
                        Menampilkan
                        <span class="font-medium">{{
                            paginatedDocs.length > 0
                                ? (currentPage - 1) * perPage + 1
                                : 0
                        }}</span>
                        sampai
                        <span class="font-medium">{{
                            Math.min(currentPage * perPage, filteredDocs.length)
                        }}</span>
                        dari
                        <span class="font-medium">{{
                            filteredDocs.length
                        }}</span>
                        dokumen
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            @click="currentPage--"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 text-xs border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Sebelumnya
                        </button>

                        <div class="flex gap-1 text-xs">
                            <button
                                v-for="page in totalPages"
                                :key="page"
                                @click="currentPage = page"
                                class="w-7 h-7 flex items-center justify-center rounded-md border"
                                :class="
                                    currentPage === page
                                        ? 'bg-indofilter text-white border-indofilter font-medium'
                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
                                "
                            >
                                {{ page }}
                            </button>
                        </div>

                        <button
                            @click="currentPage++"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 text-xs border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
