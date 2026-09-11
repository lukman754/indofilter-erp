<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { dashboard } from "../api/index.js";
import { useAppStore } from "../stores/app.js";

const router = useRouter();
const appStore = useAppStore();
const stats = ref(null);
const loading = ref(true);
const period = ref("month");

const documentTypesList = [
    {
        type: "Quotation",
        label: "Quotation",
        icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
        color: "bg-blue-50 text-blue-600",
    },
    {
        type: "Proforma Invoice",
        label: "Proforma Invoice",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2",
        color: "bg-violet-50 text-violet-600",
    },
    {
        type: "Invoice",
        label: "Invoice",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
        color: "bg-emerald-50 text-emerald-600",
    },
    {
        type: "Delivery Slip",
        label: "Surat Jalan",
        icon: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4",
        color: "bg-amber-50 text-amber-600",
    },
    {
        type: "Delivery Address",
        label: "Alamat Surat",
        icon: "M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z",
        color: "bg-rose-50 text-rose-600",
    },
    {
        type: "Purchase Order",
        label: "Purchase Order",
        icon: "M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z",
        color: "bg-teal-50 text-teal-600",
    },
];

const statusColors = {
    draft: "bg-slate-100 text-slate-700",
    confirmed: "bg-emerald-50 text-emerald-700",
    canceled: "bg-rose-50 text-rose-700",
};

const typeLabels = {
    quotation: "Quotation",
    proforma_invoice: "Proforma Invoice",
    invoice: "Invoice",
    delivery_slip: "Surat Jalan",
    delivery_address: "Alamat Surat",
    purchase_order: "Purchase Order",
};

function formatMoney(n) {
    if (n >= 1000000000) return "Rp " + (n / 1000000000).toFixed(1) + " M";
    if (n >= 1000000) return "Rp " + (n / 1000000).toFixed(1) + " Jt";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(n || 0);
}

function formatMoneyFull(n) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(n || 0);
}

function formatDate(d) {
    if (!d) return "-";
    return new Date(d).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

function formatRelativeDate(d) {
    if (!d) return "";
    const now = new Date();
    const date = new Date(d);
    const diff = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    if (diff === 0) return "Hari ini";
    if (diff === 1) return "Kemarin";
    if (diff < 7) return `${diff} hari lalu`;
    return formatDate(d);
}

const docTypeCount = computed(() => {
    if (!stats.value) return 0;
    return documentTypesList.reduce(
        (sum, dt) =>
            sum +
            (stats.value?.[dt.type.toLowerCase().replace(/ /g, "_")] || 0),
        0,
    );
});

async function fetchDashboard() {
    loading.value = true;
    try {
        const res = await dashboard.stats({ period: period.value });
        stats.value = res.data;
    } catch (e) {
        stats.value = {};
    } finally {
        loading.value = false;
    }
}

function selectCreateType(type) {
    router.push({ path: "/documents/create", query: { type } });
}

onMounted(fetchDashboard);
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="loading"
            class="flex min-h-[420px] items-center justify-center"
        >
            <div
                class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-600 shadow-sm"
            >
                <svg
                    class="h-4 w-4 animate-spin text-indofilter"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-20"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-80"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                    ></path>
                </svg>
                Memuat dashboard
            </div>
        </div>

        <template v-if="!loading && stats">
            <!-- Header -->
            <section
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Dashboard
                        </p>
                        <h1 class="mt-1 text-xl font-bold text-slate-950">
                            Ringkasan Operasional
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Data per
                            {{
                                new Date().toLocaleDateString("id-ID", {
                                    weekday: "long",
                                    year: "numeric",
                                    month: "long",
                                    day: "numeric",
                                })
                            }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="router.push('/documents')"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                ></path>
                            </svg>
                            Semua Dokumen
                        </button>
                        <router-link
                            to="/partners"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                ></path>
                            </svg>
                            Partner
                        </router-link>
                        <router-link
                            to="/products"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                ></path>
                            </svg>
                            Produk
                        </router-link>
                        <div class="h-5 w-px bg-slate-200"></div>
                        <div class="relative">
                            <button
                                @click="
                                    router.push({
                                        path: '/documents/create',
                                        query: { type: 'Quotation' },
                                    })
                                "
                                class="inline-flex h-9 items-center gap-1.5 rounded-md bg-slate-950 px-3.5 text-xs font-semibold text-white transition-colors hover:bg-slate-800"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
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
                                Buat Dokumen
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- KPI Cards -->
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Total Dokumen
                        </p>
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100"
                        >
                            <svg
                                class="h-4 w-4 text-slate-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                ></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-slate-950">
                        {{ stats.total_all || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">Bulan ini</p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Revenue Invoice
                        </p>
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-50"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-slate-950">
                        {{ formatMoney(stats.month_revenue) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">
                        Invoice terkonfirmasi bulan ini
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Perlu Diproses
                        </p>
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-amber-50"
                        >
                            <svg
                                class="h-4 w-4 text-amber-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-slate-950">
                        {{ stats.draft || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">
                        Dokumen draft aktif
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Total Revenue
                        </p>
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-indigo-50"
                        >
                            <svg
                                class="h-4 w-4 text-indigo-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                ></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-slate-950">
                        {{ formatMoney(stats.all_time_revenue) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">
                        {{ stats.all_time_invoice || 0 }} invoice sepanjang masa
                    </p>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <!-- Left: Document types + Trend -->
                <div class="space-y-5 xl:col-span-2">
                    <!-- Document type cards -->
                    <section
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-slate-950">
                                Dokumen Bulan Ini
                            </h2>
                            <router-link
                                to="/documents"
                                class="text-xs font-semibold text-indofilter hover:underline"
                                >Lihat Semua</router-link
                            >
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <button
                                v-for="dt in documentTypesList"
                                :key="dt.type"
                                @click="
                                    router.push({
                                        path: '/documents',
                                        query: { type: dt.type },
                                    })
                                "
                                class="group flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3 text-left transition-colors hover:border-slate-200 hover:bg-white"
                            >
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                    :class="dt.color"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            :d="dt.icon"
                                        ></path>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-medium text-slate-500 truncate"
                                    >
                                        {{ dt.label }}
                                    </p>
                                    <p class="text-lg font-bold text-slate-950">
                                        {{
                                            stats?.[
                                                dt.type
                                                    .toLowerCase()
                                                    .replace(/ /g, "_")
                                            ] || 0
                                        }}
                                    </p>
                                </div>
                            </button>
                        </div>
                    </section>

                    <!-- Monthly Trend -->
                    <section
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-slate-950 mb-4">
                            Tren 6 Bulan Terakhir
                        </h2>
                        <div
                            class="flex items-end gap-1.5"
                            style="height: 120px"
                        >
                            <div
                                v-for="(item, idx) in stats.monthly_trend || []"
                                :key="idx"
                                class="group relative flex-1"
                                style="height: 100%"
                            >
                                <div
                                    class="absolute bottom-0 w-full flex flex-col items-center gap-1"
                                >
                                    <div
                                        class="w-full rounded-t transition-colors"
                                        :class="
                                            idx ===
                                            stats.monthly_trend?.length - 1
                                                ? 'bg-slate-950'
                                                : 'bg-slate-200 group-hover:bg-slate-300'
                                        "
                                        :style="{
                                            height:
                                                Math.max(
                                                    4,
                                                    (item.count /
                                                        Math.max(
                                                            ...stats.monthly_trend.map(
                                                                (i) => i.count,
                                                            ),
                                                            1,
                                                        )) *
                                                        80,
                                                ) + 'px',
                                        }"
                                    ></div>
                                    <span
                                        class="text-[9px] font-medium text-slate-400"
                                        >{{ item.month?.split(" ")[0] }}</span
                                    >
                                </div>
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 shadow transition-opacity group-hover:opacity-100 pointer-events-none"
                                >
                                    {{ item.count }} dokumen ·
                                    {{ formatMoney(item.revenue) }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Right: Pending + Quick Actions -->
                <div class="space-y-5">
                    <!-- Pending Documents -->
                    <section
                        class="rounded-lg border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-100 px-5 py-3">
                            <div class="flex items-center justify-between">
                                <h2
                                    class="text-sm font-semibold text-slate-950"
                                >
                                    Perlu Diproses
                                </h2>
                                <span
                                    class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700"
                                    >{{ stats.draft || 0 }}</span
                                >
                            </div>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div
                                v-if="!stats.pending_documents?.length"
                                class="px-5 py-8 text-center"
                            >
                                <svg
                                    class="mx-auto h-8 w-8 text-slate-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M5 13l4 4L19 7"
                                    ></path>
                                </svg>
                                <p
                                    class="mt-2 text-xs font-medium text-slate-500"
                                >
                                    Semua dokumen sudah diproses
                                </p>
                            </div>
                            <router-link
                                v-for="doc in stats.pending_documents"
                                :key="doc.id"
                                :to="{
                                    path: '/documents/' + doc.id + '/edit',
                                    query: { type: doc.type },
                                }"
                                class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-xs font-semibold text-slate-900 truncate"
                                    >
                                        {{
                                            doc.document_number ||
                                            `Draft #${doc.id}`
                                        }}
                                    </p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ doc.partner?.name || "-" }} ·
                                        {{ formatRelativeDate(doc.date) }}
                                    </p>
                                </div>
                                <span
                                    class="text-xs font-semibold text-slate-900"
                                    >{{ formatMoney(doc.grand_total) }}</span
                                >
                            </router-link>
                        </div>
                        <div
                            v-if="stats.pending_documents?.length"
                            class="border-t border-slate-100 px-5 py-2.5"
                        >
                            <router-link
                                to="/documents"
                                class="text-[11px] font-semibold text-indofilter hover:underline"
                                >Lihat semua draft</router-link
                            >
                        </div>
                    </section>

                    <!-- Quick Actions -->
                    <section
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-slate-950 mb-3">
                            Aksi Cepat
                        </h2>
                        <div class="space-y-1.5">
                            <button
                                v-for="dt in documentTypesList.slice(0, 4)"
                                :key="dt.type"
                                @click="selectCreateType(dt.type)"
                                class="flex w-full items-center gap-3 rounded-lg border border-slate-100 px-3 py-2.5 text-left transition-colors hover:border-slate-200 hover:bg-slate-50"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md"
                                    :class="dt.color"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            :d="dt.icon"
                                        ></path>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-xs font-semibold text-slate-700"
                                    >
                                        {{ dt.label }}
                                    </p>
                                </div>
                                <svg
                                    class="h-3.5 w-3.5 text-slate-300"
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
                    </section>

                    <!-- Financial Summary -->
                    <section
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-slate-950 mb-3">
                            Ringkasan Keuangan
                        </h2>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500"
                                    >Nilai Quotation</span
                                >
                                <span
                                    class="text-xs font-semibold text-slate-700"
                                    >{{
                                        formatMoneyFull(
                                            stats.month_quotation_value,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500"
                                    >Nilai Invoice</span
                                >
                                <span
                                    class="text-xs font-semibold text-slate-700"
                                    >{{
                                        formatMoneyFull(
                                            stats.month_invoice_value,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500"
                                    >Total DP</span
                                >
                                <span
                                    class="text-xs font-semibold text-slate-700"
                                    >{{
                                        formatMoneyFull(stats.month_dp_total)
                                    }}</span
                                >
                            </div>
                            <div class="h-px bg-slate-100"></div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-semibold text-slate-700"
                                    >Revenue Bulan Ini</span
                                >
                                <span
                                    class="text-xs font-bold text-slate-950"
                                    >{{
                                        formatMoneyFull(stats.month_revenue)
                                    }}</span
                                >
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Recent Documents -->
            <section
                class="rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 px-5 py-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-950">
                            Dokumen Terbaru
                        </h2>
                        <router-link
                            to="/documents"
                            class="text-xs font-semibold text-indofilter hover:underline"
                            >Lihat Semua</router-link
                        >
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    class="px-5 py-2.5 text-left text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Nomor
                                </th>
                                <th
                                    class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Tipe
                                </th>
                                <th
                                    class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Partner
                                </th>
                                <th
                                    class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Tanggal
                                </th>
                                <th
                                    class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-5 py-2.5 text-right text-[11px] font-semibold uppercase text-slate-400"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-if="!stats.recent_documents?.length">
                                <td
                                    colspan="7"
                                    class="px-5 py-8 text-center text-xs text-slate-400"
                                >
                                    Belum ada dokumen
                                </td>
                            </tr>
                            <tr
                                v-for="doc in stats.recent_documents"
                                :key="doc.id"
                                class="transition-colors hover:bg-slate-50"
                            >
                                <td class="whitespace-nowrap px-5 py-2.5">
                                    <router-link
                                        :to="{
                                            path: '/documents/' + doc.id,
                                            query: { type: doc.type },
                                        }"
                                        class="text-xs font-semibold text-indofilter hover:underline"
                                    >
                                        {{
                                            doc.document_number ||
                                            `Draft #${doc.id}`
                                        }}
                                    </router-link>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600"
                                >
                                    {{ typeLabels[doc.type] || doc.type }}
                                </td>
                                <td
                                    class="max-w-[180px] truncate px-4 py-2.5 text-xs text-slate-600"
                                >
                                    {{ doc.partner?.name || "-" }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-500"
                                >
                                    {{ formatDate(doc.date) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
                                        :class="statusColors[doc.status]"
                                    >
                                        {{ doc.status }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2.5 text-right text-xs font-semibold text-slate-900"
                                >
                                    {{ formatMoneyFull(doc.grand_total) }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-5 py-2.5 text-right"
                                >
                                    <router-link
                                        :to="{
                                            path: '/documents/' + doc.id,
                                            query: { type: doc.type },
                                        }"
                                        class="text-[11px] font-semibold text-indofilter hover:underline"
                                        >Buka</router-link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
