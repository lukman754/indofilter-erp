<script setup>
import { ref, onMounted } from "vue";
import { reports } from "../../api/index.js";
import { useAppStore } from "../../stores/app.js";

const appStore = useAppStore();

const loading = ref(true);
const exporting = ref(false);
const documents = ref([]);
const statsData = ref(null);
const pagination = ref({});

const filters = ref({
    type: "",
    month: "",
    search: "",
    page: 1,
    per_page: 25,
});

const documentTypes = [
    { value: "", label: "Semua Jenis" },
    { value: "Quotation", label: "Quotation" },
    { value: "Proforma Invoice", label: "Proforma Invoice" },
    { value: "Invoice", label: "Invoice" },
    { value: "Delivery Slip", label: "Surat Jalan" },
    { value: "Delivery Address", label: "Alamat Surat" },
    { value: "Purchase Order", label: "Purchase Order" },
];

const typeLabels = {
    quotation: "Quotation",
    proforma_invoice: "Proforma Invoice",
    invoice: "Invoice",
    delivery_slip: "Surat Jalan",
    delivery_address: "Alamat Surat",
    purchase_order: "Purchase Order",
};

const typeColors = {
    quotation: "bg-blue-50 text-blue-700",
    proforma_invoice: "bg-violet-50 text-violet-700",
    invoice: "bg-emerald-50 text-emerald-700",
    delivery_slip: "bg-amber-50 text-amber-700",
    delivery_address: "bg-rose-50 text-rose-700",
    purchase_order: "bg-teal-50 text-teal-700",
};

function formatMoney(val) {
    if (val == null || isNaN(val)) return "-";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(val);
}

function formatNumber(val) {
    if (val == null || isNaN(val)) return "0";
    return new Intl.NumberFormat("id-ID").format(val);
}

function formatDate(val) {
    if (!val) return "-";
    const d = new Date(val);
    return d.toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function currentMonth() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
}

async function fetchStats() {
    try {
        const params = {};
        if (filters.value.type) params.type = filters.value.type;
        if (filters.value.month) params.month = filters.value.month;
        const { data } = await reports.stats(params);
        statsData.value = data;
    } catch (e) {
        console.error(e);
    }
}

async function fetchData(all = false) {
    loading.value = true;
    try {
        const params = { ...filters.value };
        if (all) {
            delete params.page;
            delete params.per_page;
            params.per_page = 9999;
        }
        Object.keys(params).forEach((k) => {
            if (!params[k]) delete params[k];
        });
        const { data } = await reports.list(params);
        if (all) {
            return data.data || data;
        }
        documents.value = data.data;
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page,
            total: data.total,
        };
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
    return null;
}

function applyFilters() {
    filters.value.page = 1;
    fetchStats();
    fetchData();
}

function goToPage(page) {
    if (page < 1 || page > pagination.value.last_page) return;
    filters.value.page = page;
    fetchData();
}

function setPerPage(e) {
    filters.value.per_page = Number(e.target.value);
    filters.value.page = 1;
    fetchData();
}

let searchTimeout = null;
function onSearch(e) {
    clearTimeout(searchTimeout);
    filters.value.search = e.target.value;
    searchTimeout = setTimeout(() => {
        filters.value.page = 1;
        fetchData();
    }, 400);
}

function escapeCsv(val) {
    if (val == null) return "";
    const s = String(val);
    if (s.includes(",") || s.includes('"') || s.includes("\n")) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

async function exportExcel() {
    exporting.value = true;
    try {
        const allDocs = await fetchData(true);
        if (!allDocs || allDocs.length === 0) {
            appStore.showNotification(
                "Export",
                "Tidak ada data untuk di-export",
                "warning",
            );
            return;
        }

        const headers = [
            "No. Dokumen",
            "Jenis",
            "Tanggal",
            "Nama Partner",
            "PIC",
            "No. PO",
            "Tgl PO",
            "Produk",
            "Deskripsi",
            "Qty",
            "Satuan",
            "Harga Satuan",
            "Subtotal Item",
            "Subtotal",
            "PPN",
            "Grand Total",
            "Catatan",
        ];

        const rows = [];
        allDocs.forEach((doc) => {
            const items = doc.items?.length ? doc.items : [{ _empty: true }];
            items.forEach((item) => {
                rows.push([
                    doc.document_number || "",
                    typeLabels[doc.type] || doc.type,
                    doc.date || "",
                    doc.partner?.name || "",
                    doc.partner?.contact_person || "",
                    doc.customer_po_number || "",
                    doc.customer_po_date || "",
                    item._empty ? "" : item.product_name || "",
                    item._empty ? "" : item.description || "",
                    item._empty ? "" : item.qty,
                    item._empty ? "" : item.uom || "",
                    item._empty ? "" : item.unit_price,
                    item._empty ? "" : item.total,
                    doc.subtotal || "",
                    doc.is_ppn ? doc.tax || "" : "",
                    doc.grand_total || "",
                    doc.notes || "",
                ]);
            });
        });

        const csvContent = [headers, ...rows]
            .map((row) => row.map(escapeCsv).join(","))
            .join("\n");

        const blob = new Blob(["\ufeff" + csvContent], {
            type: "text/csv;charset=utf-8;",
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        const monthLabel = filters.value.month || "semua";
        const typeLabel = filters.value.type || "semua-jenis";
        link.href = url;
        link.download = `laporan-${typeLabel}-${monthLabel}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error(e);
        appStore.showNotification("Error", "Gagal export data", "error");
    } finally {
        exporting.value = false;
    }
}

function exportPdf() {
    window.print();
}

onMounted(() => {
    filters.value.month = currentMonth();
    fetchStats();
    fetchData();
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 p-4 lg:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-lg font-bold text-gray-900">Laporan Dokumen</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Data detail untuk semua jenis dokumen
                </p>
            </div>
            <div class="flex items-center gap-2 no-print">
                <button
                    @click="exportExcel"
                    :disabled="exporting"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors disabled:opacity-50"
                >
                    <svg
                        class="w-3.5 h-3.5"
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
                    {{ exporting ? "Exporting..." : "Excel" }}
                </button>
                <button
                    @click="exportPdf"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                >
                    <svg
                        class="w-3.5 h-3.5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                        ></path>
                    </svg>
                    PDF / Print
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div
                class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm"
            >
                <div
                    class="text-[10px] font-medium text-gray-500 uppercase tracking-wide"
                >
                    Total Dokumen
                </div>
                <div class="text-xl font-bold text-gray-900 mt-1">
                    {{ formatNumber(statsData?.stats?.total_documents ?? 0) }}
                </div>
            </div>
            <div
                class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm"
            >
                <div
                    class="text-[10px] font-medium text-gray-500 uppercase tracking-wide"
                >
                    Total Nilai
                </div>
                <div class="text-xl font-bold text-gray-900 mt-1">
                    {{ formatMoney(statsData?.stats?.total_grand_total ?? 0) }}
                </div>
            </div>
            <div
                class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm"
            >
                <div
                    class="text-[10px] font-medium text-gray-500 uppercase tracking-wide"
                >
                    Rata-rata / Doc
                </div>
                <div class="text-xl font-bold text-gray-900 mt-1">
                    {{ formatMoney(statsData?.stats?.avg_grand_total ?? 0) }}
                </div>
            </div>
            <div
                class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm"
            >
                <div
                    class="text-[10px] font-medium text-gray-500 uppercase tracking-wide"
                >
                    Total PPN
                </div>
                <div class="text-xl font-bold text-gray-900 mt-1">
                    {{ formatMoney(statsData?.stats?.total_tax ?? 0) }}
                </div>
            </div>
        </div>

        <!-- Type Breakdown -->
        <div
            v-if="statsData?.type_breakdown?.length"
            class="flex flex-wrap gap-2 mb-4"
        >
            <div
                v-for="tb in statsData.type_breakdown"
                :key="tb.type"
                class="flex items-center gap-2 bg-white rounded-lg border border-gray-200 px-3 py-1.5 shadow-sm"
            >
                <span
                    :class="[
                        'text-[10px] font-semibold px-1.5 py-0.5 rounded',
                        typeColors[tb.type] || 'bg-gray-100 text-gray-600',
                    ]"
                >
                    {{ typeLabels[tb.type] || tb.type }}
                </span>
                <span class="text-xs text-gray-600">{{ tb.count }} doc</span>
                <span class="text-xs font-semibold text-gray-800">{{
                    formatMoney(tb.total)
                }}</span>
            </div>
        </div>

        <!-- Filters -->
        <div
            class="bg-white rounded-lg border border-gray-200 shadow-sm p-3 mb-4 no-print"
        >
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[140px] max-w-[200px]">
                    <label
                        class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide mb-1"
                        >Bulan</label
                    >
                    <input
                        type="month"
                        :value="filters.month"
                        @change="
                            filters.month = $event.target.value;
                            applyFilters();
                        "
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <div class="flex-1 min-w-[140px] max-w-[200px]">
                    <label
                        class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide mb-1"
                        >Jenis Dokumen</label
                    >
                    <select
                        :value="filters.type"
                        @change="
                            filters.type = $event.target.value;
                            applyFilters();
                        "
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                        <option
                            v-for="t in documentTypes"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>
                <div class="flex-1 min-w-[160px] max-w-[240px]">
                    <label
                        class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide mb-1"
                        >Cari</label
                    >
                    <input
                        type="text"
                        :value="filters.search"
                        @input="onSearch"
                        placeholder="No. doc, partner..."
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <div>
                    <label
                        class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide mb-1"
                        >Baris</label
                    >
                    <select
                        :value="filters.per_page"
                        @change="setPerPage"
                        class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div
            class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-[11px] leading-tight">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap sticky left-0 bg-gray-50 z-10"
                            >
                                #
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                No. Dokumen
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Jenis
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Tanggal
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Nama Partner
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                PIC
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                No. PO
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Tgl PO
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Produk
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Deskripsi
                            </th>
                            <th
                                class="px-2 py-2 text-right font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Qty
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Satuan
                            </th>
                            <th
                                class="px-2 py-2 text-right font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Harga Satuan
                            </th>
                            <th
                                class="px-2 py-2 text-right font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Subtotal Item
                            </th>
                            <th
                                class="px-2 py-2 text-right font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Subtotal
                            </th>
                            <th
                                class="px-2 py-2 text-right font-semibold text-gray-600 whitespace-nowrap"
                            >
                                PPN
                            </th>
                            <th
                                class="px-2 py-2 text-right font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Grand Total
                            </th>
                            <th
                                class="px-2 py-2 text-left font-semibold text-gray-600 whitespace-nowrap"
                            >
                                Catatan
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="n in 5" :key="'skeleton-' + n">
                                <td colspan="18" class="px-2 py-3">
                                    <div
                                        class="h-4 bg-gray-100 rounded animate-pulse"
                                    ></div>
                                </td>
                            </tr>
                        </template>
                        <template v-else-if="documents.length === 0">
                            <tr>
                                <td
                                    colspan="18"
                                    class="px-2 py-8 text-center text-xs text-gray-400"
                                >
                                    Tidak ada data
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <template
                                v-for="(doc, docIdx) in documents"
                                :key="doc.id"
                            >
                                <tr
                                    v-for="(item, itemIdx) in doc.items?.length
                                        ? doc.items
                                        : [{ _empty: true }]"
                                    :key="`${doc.id}-${itemIdx}`"
                                    class="border-b border-gray-100 hover:bg-blue-50/30"
                                    :class="{
                                        'bg-gray-50/50': docIdx % 2 === 1,
                                    }"
                                >
                                    <td
                                        class="px-2 py-1.5 text-gray-400 sticky left-0 bg-inherit whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            docIdx + 1
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 font-medium text-gray-800 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            doc.document_number || "-"
                                        }}</template>
                                    </td>
                                    <td class="px-2 py-1.5 whitespace-nowrap">
                                        <template v-if="itemIdx === 0">
                                            <span
                                                :class="[
                                                    'text-[9px] font-semibold px-1.5 py-0.5 rounded',
                                                    typeColors[doc.type] ||
                                                        'bg-gray-100 text-gray-600',
                                                ]"
                                            >
                                                {{
                                                    typeLabels[doc.type] ||
                                                    doc.type
                                                }}
                                            </span>
                                        </template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-600 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            formatDate(doc.date)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-800 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            doc.partner?.name || "-"
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-600 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            doc.partner?.contact_person || "-"
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-700 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            doc.customer_po_number || "-"
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-600 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            formatDate(doc.customer_po_date)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-800 whitespace-nowrap"
                                    >
                                        <template v-if="!item._empty">{{
                                            item.product_name || "-"
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-500 max-w-[120px] truncate"
                                    >
                                        <template v-if="!item._empty">{{
                                            item.description || "-"
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right text-gray-700 whitespace-nowrap"
                                    >
                                        <template v-if="!item._empty">{{
                                            formatNumber(item.qty)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-600 whitespace-nowrap"
                                    >
                                        <template v-if="!item._empty">{{
                                            item.uom || "-"
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right text-gray-700 whitespace-nowrap"
                                    >
                                        <template v-if="!item._empty">{{
                                            formatMoney(item.unit_price)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right text-gray-700 whitespace-nowrap font-medium"
                                    >
                                        <template v-if="!item._empty">{{
                                            formatMoney(item.total)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right text-gray-700 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            formatMoney(doc.subtotal)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right text-gray-700 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">
                                            <span
                                                v-if="doc.is_ppn"
                                                class="text-emerald-600"
                                                >{{
                                                    formatMoney(doc.tax)
                                                }}</span
                                            >
                                            <span v-else class="text-gray-400"
                                                >-</span
                                            >
                                        </template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right font-semibold text-gray-900 whitespace-nowrap"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            formatMoney(doc.grand_total)
                                        }}</template>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-gray-500 max-w-[120px] truncate"
                                    >
                                        <template v-if="itemIdx === 0">{{
                                            doc.notes || "-"
                                        }}</template>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                    <tfoot v-if="!loading && documents.length > 0">
                        <tr
                            class="bg-gray-50 border-t-2 border-gray-200 font-semibold text-gray-800"
                        >
                            <td
                                colspan="14"
                                class="px-2 py-2 text-right text-[10px] uppercase tracking-wide"
                            >
                                Total
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{
                                    formatMoney(
                                        documents.reduce(
                                            (s, d) =>
                                                s + Number(d.subtotal || 0),
                                            0,
                                        ),
                                    )
                                }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{
                                    formatMoney(
                                        documents.reduce(
                                            (s, d) => s + Number(d.tax || 0),
                                            0,
                                        ),
                                    )
                                }}
                            </td>
                            <td class="px-2 py-2 text-right text-gray-900">
                                {{
                                    formatMoney(
                                        documents.reduce(
                                            (s, d) =>
                                                s + Number(d.grand_total || 0),
                                            0,
                                        ),
                                    )
                                }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="!loading && pagination.last_page > 1"
                class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-gray-50 no-print"
            >
                <div class="text-[10px] text-gray-500">
                    Menampilkan
                    {{
                        (pagination.current_page - 1) * pagination.per_page + 1
                    }}-{{
                        Math.min(
                            pagination.current_page * pagination.per_page,
                            pagination.total,
                        )
                    }}
                    dari {{ formatNumber(pagination.total) }} data
                </div>
                <div class="flex items-center gap-1">
                    <button
                        @click="goToPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="px-2 py-1 text-[10px] font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        &laquo;
                    </button>
                    <template v-for="p in pagination.last_page" :key="p">
                        <button
                            v-if="
                                p === 1 ||
                                p === pagination.last_page ||
                                (p >= pagination.current_page - 2 &&
                                    p <= pagination.current_page + 2)
                            "
                            @click="goToPage(p)"
                            :class="[
                                'px-2 py-1 text-[10px] font-medium rounded border',
                                p === pagination.current_page
                                    ? 'bg-gray-900 text-white border-gray-900'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                            ]"
                        >
                            {{ p }}
                        </button>
                        <span
                            v-else-if="
                                p === pagination.current_page - 3 ||
                                p === pagination.current_page + 3
                            "
                            class="px-1 text-[10px] text-gray-400"
                        >
                            ...
                        </span>
                    </template>
                    <button
                        @click="goToPage(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page >= pagination.last_page
                        "
                        class="px-2 py-1 text-[10px] font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        &raquo;
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    table {
        font-size: 9px;
    }
    th,
    td {
        padding: 2px 4px !important;
    }
}
</style>
