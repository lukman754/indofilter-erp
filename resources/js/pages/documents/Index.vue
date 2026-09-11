<script setup>
import { ref, onMounted, computed, watch, onUnmounted, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import { documents as api } from "../../api/index.js";
import { useAppStore } from "../../stores/app.js";

const router = useRouter();
const route = useRoute();
const appStore = useAppStore();

const items = ref([]);
const loading = ref(false);
const search = ref("");
const activeType = ref(route.query.type || "");
const activeStatus = ref("");
const previewDoc = ref(null);
const previewLoading = ref(false);
const previewColumn = ref(null);
const kanbanScroll = ref(null);
const kanbanTrack = ref(null);
const showScrollTop = ref(false);
const isSyncingScroll = ref(false);
const periodMode = ref("all");
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const selectedMonth = ref(new Date().toISOString().slice(0, 7));

const documentTypes = [
    { value: "", label: "Semua" },
    { value: "Quotation", label: "Quotation" },
    { value: "Proforma Invoice", label: "Proforma Invoice" },
    { value: "Invoice", label: "Invoice" },
    { value: "Delivery Slip", label: "Surat Jalan" },
    { value: "Delivery Address", label: "Alamat Surat" },
    { value: "Purchase Order", label: "Purchase Order" },
];

const statusColors = {
    draft: "bg-slate-100 text-slate-700 ring-1 ring-slate-200",
    confirmed: "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200",
    canceled: "bg-rose-50 text-rose-700 ring-1 ring-rose-200",
};

const filteredItems = computed(() => {
    let result = items.value;
    if (search.value) {
        const s = search.value.toLowerCase();
        result = result.filter(
            (r) =>
                r.number?.toLowerCase().includes(s) ||
                r.partner?.name?.toLowerCase().includes(s) ||
                contactName(r)?.toLowerCase().includes(s) ||
                contactPhone(r)?.toLowerCase().includes(s),
        );
    }
    if (activeType.value)
        result = result.filter((r) => r.document_type === activeType.value);
    if (activeStatus.value)
        result = result.filter((r) => r.status === activeStatus.value);
    result = result.filter(isInsideSelectedPeriod);
    return result;
});

const visibleDocumentTypes = computed(() => {
    const types = documentTypes.filter((type) => type.value);
    if (!activeType.value) return types;
    return types.filter((type) => type.value === activeType.value);
});

const groupedDocuments = computed(() => {
    return visibleDocumentTypes.value.map((type) => {
        const docs = filteredItems.value.filter(
            (item) => item.document_type === type.value,
        );
        const total = docs.reduce(
            (sum, item) => sum + Number(item.grand_total || 0),
            0,
        );
        return { ...type, docs, total };
    });
});

const boardTotalValue = computed(() =>
    filteredItems.value.reduce(
        (sum, item) => sum + Number(item.grand_total || 0),
        0,
    ),
);

const periodOptions = [
    { value: "all", label: "Semua" },
    { value: "date", label: "Tanggal" },
    { value: "week", label: "Minggu" },
    { value: "month", label: "Bulan" },
];

function parseDate(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    date.setHours(0, 0, 0, 0);
    return date;
}

function isSameDate(a, b) {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function isInsideSelectedPeriod(item) {
    if (periodMode.value === "all") return true;

    const docDate = parseDate(item.date || item.created_at);
    if (!docDate) return false;

    if (periodMode.value === "date") {
        const target = parseDate(selectedDate.value);
        return target ? isSameDate(docDate, target) : true;
    }

    if (periodMode.value === "week") {
        const target = parseDate(selectedDate.value);
        if (!target) return true;
        const day = target.getDay() || 7;
        const start = new Date(target);
        start.setDate(target.getDate() - day + 1);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        return docDate >= start && docDate <= end;
    }

    if (periodMode.value === "month") {
        const [year, month] = selectedMonth.value.split("-").map(Number);
        return (
            docDate.getFullYear() === year && docDate.getMonth() === month - 1
        );
    }

    return true;
}

function contactName(item) {
    return item.recipient_pic || item.partner?.contact_person || "";
}

function contactPhone(item) {
    return item.recipient_phone || item.partner?.phone || "";
}
function isDeliveryAddressRow(item) {
    return (
        item?.document_type === "Delivery Address" ||
        item?.type === "delivery_address"
    );
}
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

async function loadPreview(id, docType) {
    previewLoading.value = true;
    previewDoc.value = null;
    previewColumn.value = docType || null;
    try {
        const res = await api.get(id);
        previewDoc.value = res.data.data || res.data;
        previewColumn.value =
            previewDoc.value?.document_type ||
            previewDoc.value?.type ||
            docType ||
            null;
    } catch (e) {
        previewDoc.value = null;
        previewColumn.value = null;
    } finally {
        previewLoading.value = false;
        nextTick(() => {
            syncTrackWidth();
            scrollPreviewIntoView();
        });
    }
}

function scrollPreviewIntoView() {
    if (!kanbanScroll.value) return;
    const element = kanbanScroll.value.querySelector("[data-preview-panel]");
    if (!element) return;
    const container = kanbanScroll.value;
    const elementLeft = element.offsetLeft;
    const elementRight = elementLeft + element.offsetWidth;
    const containerWidth = container.clientWidth;
    const scrollLeft = container.scrollLeft;
    if (elementLeft < scrollLeft) {
        container.scrollLeft = elementLeft;
    } else if (elementRight > scrollLeft + containerWidth) {
        container.scrollLeft = elementRight - containerWidth;
    }
}

function closePreview() {
    previewDoc.value = null;
    previewLoading.value = false;
    previewColumn.value = null;
    nextTick(syncTrackWidth);
}

function updateScrollButtonState() {
    const el = kanbanScroll.value;
    if (!el) {
        showScrollTop.value = false;
        return;
    }

    const canScrollHorizontally = el.scrollWidth > el.clientWidth + 1;
    showScrollTop.value = canScrollHorizontally && el.scrollLeft > 24;
}

function onKanbanScroll() {
    if (isSyncingScroll.value) return;
    const el = kanbanScroll.value;
    const track = kanbanTrack.value;
    if (!el || !track) return;
    isSyncingScroll.value = true;
    track.scrollLeft = el.scrollLeft;
    updateScrollButtonState();
    requestAnimationFrame(() => {
        isSyncingScroll.value = false;
    });
}

function onTrackScroll() {
    if (isSyncingScroll.value) return;
    const el = kanbanScroll.value;
    const track = kanbanTrack.value;
    if (!el || !track) return;
    isSyncingScroll.value = true;
    el.scrollLeft = track.scrollLeft;
    updateScrollButtonState();
    requestAnimationFrame(() => {
        isSyncingScroll.value = false;
    });
}

function scrollToStart() {
    const el = kanbanScroll.value;
    const track = kanbanTrack.value;
    if (el) el.scrollTo({ left: 0, behavior: "smooth" });
    if (track) track.scrollTo({ left: 0, behavior: "smooth" });
    showScrollTop.value = false;
}

async function fetchData() {
    loading.value = true;
    try {
        const params = {};
        if (activeType.value) params.type = activeType.value;
        if (activeStatus.value) params.status = activeStatus.value;
        const res = await api.list(params);
        items.value = res.data.data || res.data || [];
    } catch (e) {
        items.value = [];
    } finally {
        loading.value = false;
        nextTick(() => {
            syncTrackWidth();
            updateScrollButtonState();
        });
    }
}

function syncTrackWidth() {
    const el = kanbanScroll.value;
    const track = kanbanTrack.value;
    if (!el || !track) return;
    const inner = track.querySelector("div");
    if (inner) inner.style.width = el.scrollWidth + "px";
}

watch([activeType, activeStatus], fetchData);

// Sync activeType with URL query parameter changes (e.g., when navigating via sidebar tabs)
watch(
    () => route.query.type,
    (newVal) => {
        const val = newVal || "";
        if (activeType.value !== val) {
            activeType.value = val;
        }
    },
);

const poFileInput = ref(null);
const currentUploadDocId = ref(null);

function triggerPoUpload(id) {
    currentUploadDocId.value = id;
    if (poFileInput.value) {
        poFileInput.value.click();
    }
}

function showConfirm(title, message, onConfirm, onCancel = null) {
    appStore.showConfirm(title, message, onConfirm, onCancel);
}

function showNotification(title, message, type = "success") {
    appStore.showNotification(title, message, type);
}

async function onPoFileSelected(e) {
    const file = e.target.files[0];
    if (!file || !currentUploadDocId.value) return;

    const formData = new FormData();
    formData.append("customer_po_file", file);

    try {
        await api.uploadPo(currentUploadDocId.value, formData);
        showNotification(
            "Sukses",
            "File PO Customer berhasil diunggah!",
            "success",
        );
        await fetchData();
    } catch (err) {
        showNotification(
            "Gagal",
            "Gagal mengunggah file PO: " +
                (err.response?.data?.message || err.message),
            "error",
        );
    } finally {
        e.target.value = "";
        currentUploadDocId.value = null;
    }
}

async function viewPoFile(id) {
    try {
        const response = await api.downloadPo(id);
        const blob = new Blob([response.data], {
            type: response.headers["content-type"],
        });
        const url = window.URL.createObjectURL(blob);
        window.open(url, "_blank");
    } catch (e) {
        showNotification(
            "Gagal Membuka PO",
            "Gagal menampilkan file PO: " +
                (e.response?.data?.message || e.message),
            "error",
        );
    }
}

async function executeConfirm(id, overwrite) {
    try {
        await api.confirm(id, { overwrite });
        await fetchData();
        showNotification("Sukses", "Dokumen berhasil dikonfirmasi.", "success");
    } catch (e) {
        showNotification("Gagal", "Gagal mengkonfirmasi dokumen.", "error");
    }
}

async function handleConfirm(id) {
    showConfirm(
        "Konfirmasi Dokumen",
        "Apakah Anda yakin ingin mengkonfirmasi dokumen ini?",
        async () => {
            const doc = items.value.find((d) => d.id === id);
            let overwrite = false;
            if (doc && doc.document_number) {
                try {
                    const checkRes = await api.checkLocalFile({
                        number: doc.document_number,
                    });
                    if (
                        checkRes.data &&
                        checkRes.data.path_configured &&
                        checkRes.data.exists
                    ) {
                        showConfirm(
                            "Berkas Sudah Ada",
                            `Berkas "${checkRes.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                            async () => {
                                await executeConfirm(id, true);
                            },
                        );
                        return;
                    }
                } catch (e) {
                    console.error("Gagal memeriksa berkas lokal", e);
                }
            }
            await executeConfirm(id, false);
        },
    );
}

async function handleCancel(id) {
    showConfirm(
        "Batalkan Dokumen",
        "Apakah Anda yakin ingin membatalkan dokumen ini?",
        async () => {
            try {
                await api.cancel(id);
                await fetchData();
                showNotification(
                    "Sukses",
                    "Dokumen berhasil dibatalkan.",
                    "success",
                );
            } catch (e) {
                showNotification(
                    "Gagal",
                    "Gagal membatalkan dokumen.",
                    "error",
                );
            }
        },
    );
}

async function handleDelete(id) {
    showConfirm(
        "Hapus Dokumen",
        "Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.",
        async () => {
            try {
                await api.delete(id);
                await fetchData();
                showNotification(
                    "Sukses",
                    "Dokumen berhasil dihapus.",
                    "success",
                );
            } catch (e) {
                showNotification("Gagal", "Gagal menghapus dokumen.", "error");
            }
        },
    );
}

async function handleExport(id) {
    try {
        let res = await api.export(id, { overwrite: false });

        if (res.data && res.data.exists) {
            showConfirm(
                "Berkas Sudah Ada",
                `Berkas "${res.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                async () => {
                    try {
                        const overwriteRes = await api.export(id, {
                            overwrite: true,
                        });
                        if (overwriteRes.data && overwriteRes.data.success) {
                            showNotification(
                                "Ekspor Berhasil",
                                overwriteRes.data.message,
                                "success",
                            );
                        } else {
                            showNotification(
                                "Ekspor Gagal",
                                overwriteRes.data.message ||
                                    "Gagal mengekspor dokumen",
                                "error",
                            );
                        }
                    } catch (err) {
                        showNotification(
                            "Ekspor Gagal",
                            err.response?.data?.message ||
                                "Gagal mengekspor dokumen",
                            "error",
                        );
                    }
                },
            );
            return;
        }

        if (res.data && res.data.success) {
            showNotification("Ekspor Berhasil", res.data.message, "success");
        } else {
            showNotification(
                "Ekspor Gagal",
                res.data.message || "Gagal mengekspor dokumen",
                "error",
            );
        }
    } catch (e) {
        showNotification(
            "Ekspor Gagal",
            e.response?.data?.message || "Gagal mengekspor dokumen",
            "error",
        );
    }
}

const searchInput = ref(null);

const handleIndexKeydown = (e) => {
    if (e.key === "Escape" && previewDoc.value) {
        e.preventDefault();
        closePreview();
        return;
    }
    if (e.ctrlKey && e.key.toLowerCase() === "f") {
        e.preventDefault();
        if (searchInput.value) {
            searchInput.value.focus();
            searchInput.value.select();
        }
    }
    if (e.ctrlKey && e.key.toLowerCase() === "n") {
        e.preventDefault();
        router.push({
            path: "/documents/create",
            query: { type: activeType.value || "Quotation" },
        });
    }
};

const getConvertAction = (docType) => {
    switch (docType) {
        case "quotation":
            return { label: "Jadikan PI", targetType: "Proforma Invoice" };
        case "proforma_invoice":
            return { label: "Jadikan INV", targetType: "Invoice" };
        case "invoice":
            return {
                label: "Jadikan Surat Jalan",
                targetType: "Delivery Slip",
            };
        case "delivery_slip":
            return {
                label: "Jadikan Alamat Surat",
                targetType: "Delivery Address",
            };
        default:
            return null;
    }
};

function handleConvert(document) {
    const action = getConvertAction(document.type);
    if (!action) return;
    router.push({
        path: "/documents/create",
        query: {
            type: action.targetType,
            reference_id: document.id,
        },
    });
}

onMounted(async () => {
    window.addEventListener("keydown", handleIndexKeydown);
    window.addEventListener("resize", updateScrollButtonState);
    if (route.query.search) {
        search.value = route.query.search;
    }
    await fetchData();
    nextTick(updateScrollButtonState);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleIndexKeydown);
    window.removeEventListener("resize", updateScrollButtonState);
});

watch(
    () => route.query.search,
    (newVal) => {
        search.value = newVal || "";
    },
);
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div
                class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-15 w-15 shrink-0 items-center justify-center rounded-md bg-[#d5932b]/10 text-[#d5932b]"
                    >
                        <img
                            class="w-12"
                            src="https://img.icons8.com/?size=100&id=100417&format=png&color=d5932b"
                            alt=""
                        />
                    </div>

                    <div>
                        <p
                            class="text-[11px] font-extrabold uppercase text-[#d5932b]"
                        >
                            Dokumen
                        </p>
                        <h1 class="text-xl font-bold text-slate-900">
                            {{ activeType ? activeType : "Semua Dokumen" }}
                        </h1>
                        <p class="text-sm text-slate-500">
                            {{ filteredItems.length }} dokumen ·
                            <span class="font-semibold text-slate-700">{{
                                formatMoney(boardTotalValue)
                            }}</span>
                        </p>
                    </div>
                </div>

                <button
                    @click="
                        router.push({
                            path: '/documents/create',
                            query: { type: activeType || 'Quotation' },
                        })
                    "
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-black px-5 text-sm font-semibold text-white transition-colors hover:bg-[#d5932b]"
                >
                    <svg
                        class="h-3 w-3"
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
                    Baru
                </button>
            </div>

            <div
                class="mt-5 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center rounded-md border border-slate-200 bg-slate-100 p-1"
                >
                    <div class="relative">
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            placeholder="Cari nomor atau partner"
                            class="h-9 w-full min-w-[240px] rounded border-0 bg-white pl-9 pr-4 text-xs text-slate-700 shadow-sm placeholder:text-slate-400 focus:outline-none"
                        />
                    </div>

                    <select
                        v-model="activeType"
                        class="h-9 rounded-md border-0 bg-white px-3.5 text-xs font-medium text-slate-700 shadow-sm focus:outline-none"
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

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div
                        class="inline-flex h-9 items-center rounded-md border border-slate-200 bg-slate-50 p-1"
                    >
                        <button
                            v-for="option in periodOptions"
                            :key="option.value"
                            @click="periodMode = option.value"
                            class="h-7 rounded-md px-3.5 text-xs font-semibold transition-colors"
                            :class="
                                periodMode === option.value
                                    ? 'bg-[#2A7C13] text-white shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                            "
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <input
                        v-if="periodMode === 'date' || periodMode === 'week'"
                        v-model="selectedDate"
                        type="date"
                        class="h-9 rounded-full border border-slate-200 bg-white px-3.5 text-xs text-slate-700 shadow-sm focus:border-[#2A7C13] focus:outline-none focus:ring-2 focus:ring-[#2A7C13]"
                    />
                    <input
                        v-if="periodMode === 'month'"
                        v-model="selectedMonth"
                        type="month"
                        class="h-9 rounded-full border border-slate-200 bg-white px-3.5 text-xs text-slate-700 shadow-sm focus:border-[#2A7C13] focus:outline-none focus:ring-2 focus:ring-[#2A7C13]"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="loading"
            class="flex min-h-[320px] items-center justify-center rounded-lg border border-slate-200 bg-white"
        >
            <div
                class="flex items-center gap-3 text-sm font-medium text-slate-600"
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
                Memuat dokumen
            </div>
        </div>

        <div v-else class="pb-2">
            <div class="relative">
                <!-- Synced scrollbar track at top -->
                <div
                    ref="kanbanTrack"
                    @scroll="onTrackScroll"
                    class="kanban-scroll mb-2 overflow-x-auto overflow-y-hidden"
                    style="height: 12px"
                >
                    <div style="width: max-content; height: 1px"></div>
                </div>

                <!-- Actual kanban content -->
                <div
                    ref="kanbanScroll"
                    @scroll="onKanbanScroll"
                    class="kanban-content min-h-[520px] overflow-x-auto overflow-y-hidden"
                >
                    <div
                        class="flex items-start gap-2"
                        style="width: max-content"
                    >
                        <template
                            v-for="group in groupedDocuments"
                            :key="group.value"
                        >
                            <section
                                class="flex w-[350px] rounded-xl shrink-0 flex-col border border-slate-200 bg-gray"
                            >
                                <div
                                    class="border-b border-slate-300 px-4 py-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <h2
                                                class="truncate text-[13px] font-semibold tracking-[-0.01em] text-slate-950"
                                            >
                                                {{ group.label }}
                                            </h2>
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                {{ formatMoney(group.total) }} ·
                                                {{ group.docs.length }} dokumen
                                            </p>
                                        </div>
                                        <button
                                            @click="
                                                router.push({
                                                    path: '/documents/create',
                                                    query: {
                                                        type: group.value,
                                                    },
                                                })
                                            "
                                            class="rounded p-1.5 text-slate-500 transition-colors hover:bg-white hover:text-slate-950"
                                            title="Buat dokumen"
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
                                                    d="M12 4v16m8-8H4"
                                                ></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex-1 overflow-y-auto">
                                    <div
                                        v-if="group.docs.length === 0"
                                        class="border-b border-slate-200 bg-white px-4 py-8 text-center"
                                    >
                                        <p
                                            class="text-xs font-medium text-slate-500"
                                        >
                                            Kosong
                                        </p>
                                    </div>

                                    <article
                                        v-for="row in group.docs"
                                        :key="row.id"
                                        class="my-3 mx-3 overflow-hidden rounded-[10px] bg-white shadow-sm transition-all hover:shadow-md"
                                    >
                                        <div
                                            class="h-[3px] w-full bg-[#2A7C13]"
                                        ></div>

                                        <div class="px-4 py-3.5">
                                            <div
                                                class="text-[9px] font-medium text-black"
                                            >
                                                {{
                                                    formatDate(row.date) ||
                                                    "Tanpa tanggal"
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 flex items-start justify-between gap-2"
                                            >
                                                <button
                                                    @click="
                                                        router.push({
                                                            path:
                                                                '/documents/' +
                                                                row.id,
                                                            query: {
                                                                type: row.document_type,
                                                            },
                                                        })
                                                    "
                                                    class="min-w-0 flex-1 text-left text-[12px] font-bold leading-[1.4] tracking-[-0.005em] text-[#15171f] hover:underline"
                                                >
                                                    {{
                                                        row.number ||
                                                        "Draft #" + row.id
                                                    }}
                                                </button>

                                                <span
                                                    class="inline-flex shrink-0 items-center gap-1.5 text-[8px] font-bold text-[#0a9d6e]"
                                                >
                                                    <span
                                                        class="h-1 w-1 rounded-full bg-[#0a9d6e]"
                                                    ></span>
                                                    {{ row.status }}
                                                </span>
                                            </div>

                                            <div
                                                class="mt-3 flex items-center gap-2 border-t border-[#f1f2f7] pt-3"
                                            >
                                                <div
                                                    class="grid h-8 w-8 place-items-center rounded-[9px] bg-[#2A7C13]/20 text-[10px] font-extrabold text-[#2A7C13]"
                                                >
                                                    {{
                                                        (
                                                            row.partner?.name ||
                                                            row.recipient_name ||
                                                            "A"
                                                        )
                                                            .split(" ")
                                                            .map(
                                                                (word) =>
                                                                    word[0],
                                                            )
                                                            .join("")
                                                            .slice(0, 2)
                                                            .toUpperCase() ||
                                                        "A"
                                                    }}
                                                </div>

                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="truncate text-[10px] font-bold text-[#15171f]"
                                                    >
                                                        {{
                                                            row.partner?.name ||
                                                            row.recipient_name ||
                                                            "-"
                                                        }}
                                                    </div>
                                                    <div
                                                        class="truncate text-[8px] font-medium text-[#9296a3]"
                                                    >
                                                        <span
                                                            class="font-semibold text-[#2A7C13]"
                                                        >
                                                            {{
                                                                contactName(
                                                                    row,
                                                                ) ||
                                                                "No contact"
                                                            }}
                                                        </span>
                                                        <span
                                                            v-if="
                                                                contactPhone(
                                                                    row,
                                                                )
                                                            "
                                                        >
                                                            ·
                                                            {{
                                                                contactPhone(
                                                                    row,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>

                                                <a
                                                    v-if="contactPhone(row)"
                                                    :href="`tel:${contactPhone(row)}`"
                                                    class="grid h-7 w-7 place-items-center rounded-[8px] bg-[#f4f5f9] text-[#63687a]"
                                                    title="Telepon"
                                                >
                                                    <svg
                                                        class="h-3.5 w-3.5 fill-none stroke-current stroke-[1.8]"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            d="M6 4h3l1.5 4L8.5 9.5a11 11 0 0 0 6 6l1.5-2L20 15v3a2 2 0 0 1-2 2C10.8 20 4 13.2 4 6a2 2 0 0 1 2-2z"
                                                        />
                                                    </svg>
                                                </a>
                                            </div>

                                            <div
                                                v-if="isDeliveryAddressRow(row)"
                                                class=""
                                            ></div>

                                            <div
                                                v-else
                                                class="mt-3 flex items-center justify-between gap-3 rounded-md bg-[#2A7C13]/20 px-3 py-2"
                                            >
                                                <div
                                                    class="text-[10px] font-bold text-black"
                                                >
                                                    Total
                                                </div>
                                                <div>
                                                    <div
                                                        class="text-[12px] font-bold leading-[1.2] tracking-[-0.01em] text-[#2A7C13]"
                                                    >
                                                        {{
                                                            formatMoney(
                                                                row.grand_total,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    row.reference ||
                                                    row.customer_po_number ||
                                                    row.items?.length
                                                "
                                                class="mt-3"
                                            >
                                                <div
                                                    v-if="row.reference"
                                                    class="truncate text-[10.5px] font-medium text-[#b0b4c0]"
                                                >
                                                    <b
                                                        class="mr-1 inline-block min-w-[26px] font-semibold text-[#9296a3]"
                                                        >Ref</b
                                                    >
                                                    <router-link
                                                        :to="{
                                                            path:
                                                                '/documents/' +
                                                                row.reference
                                                                    .id,
                                                            query: {
                                                                type: row
                                                                    .reference
                                                                    .document_type,
                                                            },
                                                        }"
                                                        class="text-[#2A7C13] hover:underline"
                                                    >
                                                        {{
                                                            row.reference
                                                                .document_number ||
                                                            `Draft #${row.reference.id}`
                                                        }}
                                                    </router-link>
                                                </div>
                                                <div
                                                    v-if="
                                                        row.customer_po_number
                                                    "
                                                    class="truncate text-[10.5px] font-medium text-[#b0b4c0]"
                                                >
                                                    <b
                                                        class="mr-1 inline-block min-w-[26px] font-semibold text-[#9296a3]"
                                                        >PO</b
                                                    >
                                                    <button
                                                        v-if="
                                                            row.customer_po_file
                                                        "
                                                        @click="
                                                            viewPoFile(row.id)
                                                        "
                                                        class="text-[#2A7C13] hover:underline"
                                                    >
                                                        {{
                                                            row.customer_po_number
                                                        }}
                                                    </button>
                                                    <span
                                                        v-else
                                                        class="text-[#2A7C13]"
                                                    >
                                                        {{
                                                            row.customer_po_number
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    v-if="row.items?.length"
                                                    class="mt-3 space-y-1 text-[10.5px] font-medium text-[#b0b4c0]"
                                                >
                                                    <div
                                                        class="flex items-center gap-1.5"
                                                    >
                                                        <b
                                                            class="inline-block min-w-[26px] font-bold text-black"
                                                            >Items</b
                                                        >
                                                        <span
                                                            class="text-[#6b7280]"
                                                        >
                                                            {{
                                                                row.items.length
                                                            }}
                                                            item
                                                        </span>
                                                    </div>
                                                    <ul
                                                        class="list-none space-y-[-3px] pl-0 text-[#63687a]"
                                                    >
                                                        <li
                                                            v-for="(
                                                                itm, idx
                                                            ) in row.items"
                                                            :key="idx"
                                                            class="flex items-start gap-1.5"
                                                        >
                                                            <span
                                                                class="mt-2 h-1 w-1 bg-[#2A7C13]"
                                                            ></span>
                                                            <span
                                                                class="truncate"
                                                                >{{
                                                                    itm.product_name ||
                                                                    "—"
                                                                }}</span
                                                            >
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            <div
                                                v-if="isDeliveryAddressRow(row)"
                                                class="mt-3 space-y-2"
                                            >
                                                <div
                                                    v-if="contactName(row)"
                                                    class="text-[11px] text-[#63687a]"
                                                >
                                                    <span
                                                        class="mr-2 font-semibold text-[#15171f]"
                                                        >PIC:</span
                                                    >
                                                    {{ contactName(row) }}
                                                </div>
                                                <div
                                                    v-if="
                                                        row.recipient_address ||
                                                        row.partner?.address
                                                    "
                                                    class="text-[11px] text-[#63687a]"
                                                >
                                                    <span
                                                        class="mr-2 font-semibold text-[#15171f]"
                                                        >Alamat:</span
                                                    >
                                                    {{
                                                        row.recipient_address ||
                                                        row.partner?.address ||
                                                        "-"
                                                    }}
                                                </div>
                                            </div>

                                            <div
                                                class="mt-4 flex items-center justify-between gap-2 border-t border-[#f1f2f7] pt-3"
                                            >
                                                <button
                                                    v-if="
                                                        row.status ===
                                                            'confirmed' &&
                                                        getConvertAction(
                                                            row.type,
                                                        )
                                                    "
                                                    @click="handleConvert(row)"
                                                    class="inline-flex items-center rounded-md bg-[#2A7C13] px-2 py-1 text-[10px] font-bold text-white"
                                                >
                                                    Convert
                                                </button>
                                                <span v-else class="h-7"></span>

                                                <div
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <button
                                                        @click="
                                                            loadPreview(
                                                                row.id,
                                                                row.document_type,
                                                            )
                                                        "
                                                        class="grid h-7 w-7 place-items-center rounded-[8px] bg-[#e8f1ff] text-[#2f6fed] transition hover:bg-[#dbe9ff]"
                                                        title="Preview"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5 fill-none stroke-current stroke-[1.7]"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5z"
                                                            />
                                                            <circle
                                                                cx="12"
                                                                cy="12"
                                                                r="2.5"
                                                            />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        v-if="
                                                            row.status ===
                                                                'draft' ||
                                                            row.status ===
                                                                'confirmed'
                                                        "
                                                        @click="
                                                            router.push({
                                                                path:
                                                                    '/documents/' +
                                                                    row.id +
                                                                    '/edit',
                                                                query: {
                                                                    type: row.document_type,
                                                                },
                                                            })
                                                        "
                                                        class="grid h-7 w-7 place-items-center rounded-[8px] bg-[#fef3e2] text-[#d18a1b] transition hover:bg-[#fceace]"
                                                        title="Edit"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5 fill-none stroke-current stroke-[1.7]"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z"
                                                            />
                                                            <path
                                                                d="M14.5 7.5l2 2"
                                                            />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="
                                                            handleExport(row.id)
                                                        "
                                                        class="grid h-7 w-7 place-items-center rounded-[8px] bg-[#2A7C13]/20 text-[#2A7C13] transition hover:bg-[#e6e4fd]"
                                                        title="Export DOCX"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5 fill-none stroke-current stroke-[1.7]"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                d="M7 3h7l4 4v14H7z"
                                                            />
                                                            <path
                                                                d="M14 3v5h4"
                                                            />
                                                            <path
                                                                d="M12 11v6"
                                                            />
                                                            <path
                                                                d="M9.5 14.5L12 17l2.5-2.5"
                                                            />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="
                                                            handleDelete(row.id)
                                                        "
                                                        class="grid h-7 w-7 place-items-center rounded-[8px] bg-[#fdecec] text-[#e5484d] transition hover:bg-[#fadada]"
                                                        title="Hapus"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5 fill-none stroke-current stroke-[1.7]"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path d="M5 7h14" />
                                                            <path
                                                                d="M9 7V4h6v3"
                                                            />
                                                            <path
                                                                d="M7 7l1 14h8l1-14"
                                                            />
                                                            <path
                                                                d="M10 11v6M14 11v6"
                                                            />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </section>

                            <div
                                v-if="
                                    (previewDoc || previewLoading) &&
                                    previewColumn === group.value
                                "
                                class="self-start w-full max-w-[600px] shrink-0 overflow-visible"
                                data-preview-panel
                            >
                                <div
                                    class="w-full overflow-hidden rounded-[10px] border border-[#e4e7ec] bg-white"
                                >
                                    <header
                                        class="flex h-[46px] items-center justify-between border-b border-[#e4e7ec] bg-white px-4"
                                    >
                                        <div
                                            class="text-[12.5px] font-bold text-[#2A7C13] flex"
                                        >
                                            Preview Dokumen
                                        </div>

                                        <button
                                            @click="closePreview"
                                            class="grid h-6 w-6 place-items-center rounded-[6px] text-[#98a2b3] transition-colors hover:bg-[#D10056] hover:text-white"
                                            aria-label="Tutup preview"
                                        >
                                            <svg
                                                class="h-[13px] w-[13px] fill-none stroke-current stroke-[1.8]"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    d="M6 6l12 12M18 6L6 18"
                                                />
                                            </svg>
                                        </button>
                                    </header>

                                    <div class="p-4 text-xs">
                                        <div
                                            v-if="previewLoading"
                                            class="flex items-center justify-center py-12"
                                        >
                                            <svg
                                                class="h-5 w-5 animate-spin text-indofilter"
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
                                        </div>

                                        <template v-else>
                                            <div
                                                class="flex items-start justify-between gap-3 pb-4"
                                            >
                                                <div class="min-w-0">
                                                    <h1
                                                        class="m-0 text-[17px] font-bold leading-[1.35] tracking-[-0.01em] text-[#101828]"
                                                    >
                                                        {{
                                                            previewDoc.number ||
                                                            "Draft #" +
                                                                previewDoc.id
                                                        }}
                                                    </h1>
                                                    <div
                                                        class="mt-1 text-[12.5px] font-medium text-[#D10056]"
                                                    >
                                                        {{
                                                            previewDoc.document_type
                                                        }}
                                                        ·
                                                        {{
                                                            formatDate(
                                                                previewDoc.date,
                                                            )
                                                        }}
                                                    </div>
                                                </div>

                                                <span
                                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[#2A7C13]/10 px-2.5 py-1.5 text-[12px] font-bold text-green-600"
                                                >
                                                    <span
                                                        class="h-1.5 w-1.5 rounded-full bg-green-600"
                                                    ></span>
                                                    {{ previewDoc.status }}
                                                </span>
                                            </div>

                                            <section
                                                class="grid grid-cols-2 border-[#eaecf0]"
                                            >
                                                <div class="py-3 pr-4">
                                                    <div
                                                        class="mb-1.5 text-[10px] font-bold text-[#2A7C13]"
                                                    >
                                                        Pengirim
                                                    </div>
                                                    <div
                                                        class="mb-1 text-[13px] font-bold leading-[1.35] text-[#101828]"
                                                    >
                                                        {{
                                                            previewDoc.sender_name ||
                                                            previewDoc.company
                                                                ?.name ||
                                                            "-"
                                                        }}
                                                    </div>
                                                    <div
                                                        v-if="
                                                            previewDoc.sender_address ||
                                                            previewDoc.company
                                                                ?.address
                                                        "
                                                        class="text-[12px] leading-[1.55] text-[#667085]"
                                                    >
                                                        {{
                                                            previewDoc.sender_address ||
                                                            previewDoc.company
                                                                ?.address
                                                        }}
                                                    </div>
                                                    <div
                                                        v-if="
                                                            previewDoc.sender_phone ||
                                                            previewDoc.company
                                                                ?.phone
                                                        "
                                                        class="mt-1 text-[12px] font-medium text-[#475467]"
                                                    >
                                                        {{
                                                            previewDoc.sender_phone ||
                                                            previewDoc.company
                                                                ?.phone
                                                        }}
                                                    </div>
                                                </div>

                                                <div
                                                    class="border-l border-[#eaecf0] py-3 pl-4"
                                                >
                                                    <div
                                                        class="mb-1.5 text-[10px] font-bold text-[#2A7C13]"
                                                    >
                                                        Penerima
                                                    </div>
                                                    <div
                                                        class="mb-1 text-[13px] font-bold leading-[1.35] text-[#101828]"
                                                    >
                                                        {{
                                                            previewDoc.recipient_name ||
                                                            previewDoc.partner
                                                                ?.name ||
                                                            "-"
                                                        }}
                                                    </div>
                                                    <div
                                                        v-if="
                                                            previewDoc.recipient_address ||
                                                            previewDoc.partner
                                                                ?.address
                                                        "
                                                        class="text-[12px] leading-[1.55] text-[#667085]"
                                                    >
                                                        {{
                                                            previewDoc.recipient_address ||
                                                            previewDoc.partner
                                                                ?.address
                                                        }}
                                                    </div>
                                                    <div
                                                        v-if="
                                                            previewDoc.recipient_pic ||
                                                            previewDoc.partner
                                                                ?.contact_person
                                                        "
                                                        class="mt-1 text-[12px] font-medium text-[#D10056]"
                                                    >
                                                        PIC:
                                                        {{
                                                            previewDoc.recipient_pic ||
                                                            previewDoc.partner
                                                                ?.contact_person
                                                        }}
                                                    </div>
                                                    <div
                                                        v-if="
                                                            previewDoc.recipient_phone ||
                                                            previewDoc.partner
                                                                ?.phone
                                                        "
                                                        class="text-[12px] font-bold"
                                                    >
                                                        {{
                                                            previewDoc.recipient_phone ||
                                                            previewDoc.partner
                                                                ?.phone
                                                        }}
                                                    </div>
                                                </div>
                                            </section>

                                            <div
                                                class="grid grid-cols-2 border-[#eaecf0]"
                                            >
                                                <div class="py-3 pr-4">
                                                    <div
                                                        class="mb-1 text-[10px] font-bold text-[#98a2b3]"
                                                    >
                                                        PO Customer
                                                    </div>
                                                    <div
                                                        class="text-[12.5px] font-semibold text-[#101828]"
                                                    >
                                                        {{
                                                            previewDoc.customer_po_number ||
                                                            "-"
                                                        }}
                                                    </div>
                                                </div>
                                                <div
                                                    class="border-l border-[#eaecf0] py-3 pl-4"
                                                >
                                                    <div
                                                        class="mb-1 text-[10px] font-bold text-[#98a2b3]"
                                                    >
                                                        Jatuh Tempo
                                                    </div>
                                                    <div
                                                        class="text-[12.5px] font-semibold text-[#101828]"
                                                    >
                                                        {{
                                                            previewDoc.due_date
                                                                ? formatDate(
                                                                      previewDoc.due_date,
                                                                  )
                                                                : "-"
                                                        }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="border-b border-[#eaecf0] bg-white py-3 text-[12px] text-[#667085]"
                                            >
                                                <span
                                                    class="text-black font-bold"
                                                    >Referensi:</span
                                                >
                                                <span
                                                    v-if="previewDoc.reference"
                                                    class="font-semibold text-[#101828]"
                                                >
                                                    <router-link
                                                        :to="{
                                                            path:
                                                                '/documents/' +
                                                                previewDoc
                                                                    .reference
                                                                    .id,
                                                            query: {
                                                                type: previewDoc
                                                                    .reference
                                                                    .document_type,
                                                            },
                                                        }"
                                                        class="text-[#101828] hover:underline"
                                                    >
                                                        {{
                                                            previewDoc.reference
                                                                .document_number ||
                                                            `Draft #${previewDoc.reference.id}`
                                                        }}
                                                    </router-link>
                                                </span>
                                                <span
                                                    v-else
                                                    class="font-semibold text-[#101828]"
                                                    >-</span
                                                >
                                            </div>

                                            <div class="pt-4">
                                                <div
                                                    class="mb-1 text-[10px] font-semibold text-[#98a2b3]"
                                                >
                                                    Item Barang
                                                </div>

                                                <div
                                                    v-if="
                                                        previewDoc.items &&
                                                        previewDoc.items.length
                                                    "
                                                    class="space-y-2"
                                                >
                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in previewDoc.items"
                                                        :key="idx"
                                                        class="border-b border-[#eef0f2] last:border-0"
                                                    >
                                                        <div
                                                            class="flex items-start justify-between gap-3"
                                                        >
                                                            <div
                                                                class="min-w-0 text-[11px] font-semibold text-[#101828]"
                                                            >
                                                                {{
                                                                    item.product_name ||
                                                                    item.product
                                                                        ?.name ||
                                                                    "-"
                                                                }}
                                                            </div>
                                                            <div
                                                                class="shrink-0 text-[11px] font-semibold text-[#2A7C13]"
                                                            >
                                                                {{
                                                                    formatMoney(
                                                                        item.total ||
                                                                            item.qty *
                                                                                item.unit_price,
                                                                    )
                                                                }}
                                                            </div>
                                                        </div>
                                                        <div
                                                            v-if="
                                                                item.description
                                                            "
                                                            class="text-[11.5px] text-[#98a2b3]"
                                                        >
                                                            {{
                                                                item.description
                                                            }}
                                                        </div>
                                                        <div
                                                            class="text-[11px] text-black"
                                                        >
                                                            {{ item.qty }}
                                                            ×
                                                            <span
                                                                class="text-[#2A7C13]"
                                                                >{{
                                                                    formatMoney(
                                                                        item.unit_price,
                                                                    )
                                                                }}</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-4 border-t border-[#eaecf0] pt-2"
                                            >
                                                <div
                                                    class="flex justify-between py-1 text-[12.5px] text-[#667085]"
                                                >
                                                    <span>Subtotal</span>
                                                    <strong
                                                        class="font-semibold text-[#344054]"
                                                        >{{
                                                            formatMoney(
                                                                previewDoc.subtotal,
                                                            )
                                                        }}</strong
                                                    >
                                                </div>
                                                <div
                                                    v-if="
                                                        Number(
                                                            previewDoc.discount,
                                                        ) > 0
                                                    "
                                                    class="flex justify-between py-1 text-[12.5px] text-[#667085]"
                                                >
                                                    <span>Diskon</span>
                                                    <strong
                                                        class="font-semibold text-[#344054]"
                                                        >-{{
                                                            formatMoney(
                                                                previewDoc.discount,
                                                            )
                                                        }}</strong
                                                    >
                                                </div>
                                                <div
                                                    v-if="previewDoc.is_ppn"
                                                    class="flex justify-between text-[12.5px] text-[#667085]"
                                                >
                                                    <span>PPN (11%)</span>
                                                    <strong
                                                        class="font-semibold text-[#344054]"
                                                        >{{
                                                            formatMoney(
                                                                previewDoc.tax,
                                                            )
                                                        }}</strong
                                                    >
                                                </div>
                                                <div
                                                    class="mt-1 flex items-baseline justify-between bg-[#f8f9fb]"
                                                >
                                                    <span
                                                        class="text-[12.5px] font-semibold text-[#344054]"
                                                        >Grand Total</span
                                                    >
                                                    <strong
                                                        class="text-[13px] font-extrabold tracking-[-0.01em] text-[#2A7C13]"
                                                    >
                                                        {{
                                                            formatMoney(
                                                                previewDoc.grand_total,
                                                            )
                                                        }}
                                                    </strong>
                                                </div>
                                                <div
                                                    v-if="previewDoc.terbilang"
                                                    class="mt-2 text-[11.5px] italic leading-[1.5] text-[#98a2b3]"
                                                >
                                                    "{{ previewDoc.terbilang }}"
                                                </div>
                                            </div>

                                            <footer
                                                class="mt-4 grid grid-cols-2 gap-2 border-t border-[#eaecf0] bg-[#fafbfc] px-4 py-3"
                                            >
                                                <router-link
                                                    :to="{
                                                        path:
                                                            '/documents/' +
                                                            previewDoc.id,
                                                        query: {
                                                            type: previewDoc.document_type,
                                                        },
                                                    }"
                                                    @click="closePreview"
                                                    class="inline-flex h-[38px] items-center justify-center gap-2 rounded-[8px] border border-[#101828] bg-[#101828] text-[13px] font-semibold text-white transition-colors hover:bg-[#1d2939]"
                                                >
                                                    <svg
                                                        class="h-[15px] w-[15px] fill-none stroke-current stroke-[1.8]"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5z"
                                                        />
                                                        <circle
                                                            cx="12"
                                                            cy="12"
                                                            r="2.5"
                                                        />
                                                    </svg>
                                                    Lihat Detail
                                                </router-link>

                                                <router-link
                                                    v-if="
                                                        previewDoc.status ===
                                                            'draft' ||
                                                        previewDoc.status ===
                                                            'confirmed'
                                                    "
                                                    :to="{
                                                        path:
                                                            '/documents/' +
                                                            previewDoc.id +
                                                            '/edit',
                                                        query: {
                                                            type: previewDoc.document_type,
                                                        },
                                                    }"
                                                    @click="closePreview"
                                                    class="inline-flex h-[38px] items-center justify-center gap-2 rounded-[8px] border border-[#d0d5dd] bg-white text-[13px] font-semibold text-[#344054] transition-colors hover:bg-[#f8f9fb]"
                                                >
                                                    <svg
                                                        class="h-[15px] w-[15px] fill-none stroke-current stroke-[1.8]"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z"
                                                        />
                                                        <path
                                                            d="M14.5 7.5l2 2"
                                                        />
                                                    </svg>
                                                    Edit
                                                </router-link>
                                            </footer>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <transition name="fade">
                    <button
                        v-if="showScrollTop"
                        @click="scrollToStart"
                        class="absolute bottom-4 right-4 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white shadow-md text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-950"
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
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            ></path>
                        </svg>
                    </button>
                </transition>
            </div>
        </div>

        <input
            type="file"
            ref="poFileInput"
            style="display: none"
            accept=".pdf,image/*"
            @change="onPoFileSelected"
        />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.kanban-content {
    -ms-overflow-style: none;
    scrollbar-width: none;
    overflow-y: visible;
    overscroll-behavior-x: contain;
}
.kanban-content::-webkit-scrollbar {
    display: none;
}
.kanban-scroll {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.kanban-scroll::-webkit-scrollbar {
    height: 6px;
}
.kanban-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}
.kanban-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
.kanban-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
