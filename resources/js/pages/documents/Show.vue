<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { documents as api } from "../../api/index.js";
import { useAppStore } from "../../stores/app.js";

const route = useRoute();
const router = useRouter();
const doc = ref(null);
const loading = ref(true);
const error = ref("");
const documentList = ref([]);
const documentListLoading = ref(false);
let documentLoadRequest = 0;
let previewClickHandler = null;
const appStore = useAppStore();

const currentDocumentType = computed(
    () => doc.value?.type || route.query.type || "",
);
const selectedDocumentId = computed(() => String(route.params.id || ""));

const statusColors = {
    draft: "bg-gray-100 text-gray-600",
    confirmed: "bg-green-100 text-green-700",
    canceled: "bg-red-100 text-red-600",
};

function formatDate(d) {
    if (!d) return "-";
    return new Date(d).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
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

async function loadDocument(documentId = route.params.id) {
    const requestId = ++documentLoadRequest;
    loading.value = true;
    error.value = "";
    try {
        const res = await api.get(documentId);
        if (requestId !== documentLoadRequest) return;
        doc.value = res.data.data || res.data;
        if (!route.query.type && doc.value) {
            router.replace({
                query: {
                    ...route.query,
                    type: doc.value.document_type || doc.value.type,
                },
            });
        }
    } catch (e) {
        if (requestId !== documentLoadRequest) return;
        error.value = "Gagal memuat dokumen";
    } finally {
        loading.value = false;
    }
}

async function loadDocumentList() {
    if (!currentDocumentType.value) return;
    documentListLoading.value = true;
    try {
        const res = await api.list({ type: currentDocumentType.value });
        documentList.value = res.data.data || res.data || [];
    } catch (e) {
        documentList.value = [];
    } finally {
        documentListLoading.value = false;
    }
}

async function selectDocument(item) {
    if (String(item.id) === String(route.params.id)) return;
    await router.push({
        name: "documents.show",
        params: { id: item.id },
        query: { type: item.document_type || item.type },
    });
    await loadDocument(item.id);
    await loadDocumentList();
}

// Reload when navigating between documents (same component, different :id)
watch(
    () => route.params.id,
    (newId, oldId) => {
        if (newId && newId !== oldId) {
            loadDocument(newId).then(loadDocumentList);
        }
    },
);

watch(
    () => route.query.type,
    () => {
        if (doc.value) loadDocumentList();
    },
);

function showConfirm(title, message, onConfirm, onCancel = null) {
    appStore.showConfirm(title, message, onConfirm, onCancel);
}

function showNotification(title, message, type = "success") {
    appStore.showNotification(title, message, type);
}

async function executeConfirm(overwrite) {
    try {
        await api.confirm(route.params.id, { overwrite });
        await loadDocument();
        showNotification("Sukses", "Dokumen berhasil dikonfirmasi.", "success");
    } catch (e) {
        showNotification("Gagal", "Gagal mengkonfirmasi dokumen.", "error");
    }
}

async function handleConfirm() {
    showConfirm(
        "Konfirmasi Dokumen",
        "Apakah Anda yakin ingin mengkonfirmasi dokumen ini?",
        async () => {
            let overwrite = false;
            if (doc.value && doc.value.document_number) {
                try {
                    const checkRes = await api.checkLocalFile({
                        number: doc.value.document_number,
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
                                await executeConfirm(true);
                            },
                        );
                        return;
                    }
                } catch (e) {
                    console.error("Gagal memeriksa berkas lokal", e);
                }
            }
            await executeConfirm(false);
        },
    );
}

async function handleCancel() {
    showConfirm(
        "Batalkan Dokumen",
        "Apakah Anda yakin ingin membatalkan dokumen ini?",
        async () => {
            try {
                await api.cancel(route.params.id);
                await loadDocument();
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

async function handleDelete() {
    showConfirm(
        "Hapus Dokumen",
        "Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.",
        async () => {
            try {
                await api.delete(route.params.id);
                router.push({
                    path: "/documents",
                    query: { type: doc.value?.document_type },
                });
            } catch (e) {
                showNotification("Gagal", "Gagal menghapus dokumen.", "error");
            }
        },
    );
}

async function handleExport() {
    try {
        let res = await api.export(route.params.id, { overwrite: false });

        if (res.data && res.data.exists) {
            showConfirm(
                "Berkas Sudah Ada",
                `Berkas "${res.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                async () => {
                    try {
                        const overwriteRes = await api.export(route.params.id, {
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

async function handleViewPoFile() {
    if (!doc.value || !doc.value.id) return;
    try {
        const response = await api.downloadPo(doc.value.id);
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

const isUploadingSupporting = ref(false);
const supportingTitle = ref("");
const supportingFile = ref(null);
const supportingFileInput = ref(null);

function triggerSupportingUpload() {
    if (supportingFileInput.value) {
        supportingFileInput.value.click();
    }
}

async function onSupportingFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;
    supportingFile.value = file;
}

async function uploadSupportingFile() {
    if (!supportingFile.value) {
        showNotification("Validasi", "Pilih file terlebih dahulu!", "warning");
        return;
    }

    isUploadingSupporting.value = true;
    const formData = new FormData();
    formData.append("file", supportingFile.value);
    if (supportingTitle.value) {
        formData.append("title", supportingTitle.value);
    }

    try {
        const res = await api.uploadSupporting(doc.value.id, formData);
        showNotification(
            "Sukses",
            "Dokumen pendukung berhasil diunggah.",
            "success",
        );
        doc.value.supporting_documents = res.data.supporting_documents;
        supportingFile.value = null;
        supportingTitle.value = "";
        if (supportingFileInput.value) {
            supportingFileInput.value.value = "";
        }
    } catch (err) {
        showNotification(
            "Gagal",
            "Gagal mengunggah dokumen: " +
                (err.response?.data?.message || err.message),
            "error",
        );
    } finally {
        isUploadingSupporting.value = false;
    }
}

async function deleteSupportingFile(filename) {
    showConfirm(
        "Hapus Dokumen Pendukung",
        "Apakah Anda yakin ingin menghapus dokumen pendukung ini?",
        async () => {
            try {
                const res = await api.deleteSupporting(doc.value.id, filename);
                showNotification(
                    "Sukses",
                    "Dokumen pendukung berhasil dihapus.",
                    "success",
                );
                doc.value.supporting_documents = res.data.supporting_documents;
            } catch (err) {
                showNotification(
                    "Gagal",
                    "Gagal menghapus dokumen: " +
                        (err.response?.data?.message || err.message),
                    "error",
                );
            }
        },
    );
}

async function downloadSupportingFile(filename) {
    try {
        const response = await api.downloadSupporting(doc.value.id, filename);
        const blob = new Blob([response.data], {
            type: response.headers["content-type"],
        });
        const url = window.URL.createObjectURL(blob);

        const contentType = response.headers["content-type"] || "";
        if (contentType.includes("pdf") || contentType.includes("image")) {
            window.open(url, "_blank");
        } else {
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    } catch (e) {
        showNotification(
            "Gagal",
            "Gagal mengunduh dokumen pendukung: " +
                (e.response?.data?.message || e.message),
            "error",
        );
    }
}

onMounted(async () => {
    await loadDocument();
    await loadDocumentList();

    // In preview mode, intercept router-link clicks to stay in iframe preview
    if (route.query.preview === "1") {
        previewClickHandler = (e) => {
            const link = e.target.closest("a[href]");
            if (!link) return;
            const href = link.getAttribute("href");
            if (
                !href ||
                href.startsWith("http") ||
                href.startsWith("#") ||
                href.startsWith("mailto:")
            )
                return;
            e.preventDefault();
            window.location.href =
                href + (href.includes("?") ? "&" : "?") + "preview=1";
        };
        document.addEventListener("click", previewClickHandler);
    }
});

onUnmounted(() => {
    if (previewClickHandler) {
        document.removeEventListener("click", previewClickHandler);
        previewClickHandler = null;
    }
});
</script>

<template>
    <div class="min-h-screen bg-[#f6f7f9] text-gray-900">
        <!-- Loading -->
        <div
            v-if="loading && !doc"
            class="flex items-center justify-center py-24"
        >
            <svg
                class="animate-spin h-7 w-7 text-indofilter"
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

        <!-- Error -->
        <div v-if="error" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"
            >
                {{ error }}
            </div>
        </div>

        <template v-if="doc">
            <!-- =========================================================
                 ACTION BAR
            ========================================================== -->
            <div
                v-if="!route.query.preview"
                class="sticky top-0 z-30 w-full bg-[#f6f7f9]/95 backdrop-blur-sm"
            >
                <div class="flex items-center justify-between gap-4 py-2">
                    <button
                        @click="
                            router.push({
                                path: '/documents',
                                query: { type: doc.document_type },
                            })
                        "
                        class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors"
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
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            ></path>
                        </svg>
                        Kembali ke Daftar
                    </button>

                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <!-- Convert -->
                        <button
                            v-if="
                                doc.status === 'confirmed' &&
                                getConvertAction(doc.type)
                            "
                            @click="handleConvert(doc)"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-gray-900 text-white hover:bg-gray-800 transition-colors"
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
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"
                                ></path>
                            </svg>
                            {{ getConvertAction(doc.type).label }}
                        </button>

                        <!-- Edit -->
                        <button
                            v-if="
                                doc.status === 'draft' ||
                                doc.status === 'confirmed'
                            "
                            @click="
                                router.push({
                                    path: '/documents/' + doc.id + '/edit',
                                    query: { type: doc.type },
                                })
                            "
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
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
                            Edit
                        </button>

                        <!-- Confirm -->
                        <button
                            v-if="doc.status === 'draft'"
                            @click="handleConfirm"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg
                                class="w-4 h-4 text-green-600"
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
                            Konfirmasi
                        </button>

                        <!-- Cancel -->
                        <button
                            v-if="doc.status === 'draft'"
                            @click="handleCancel"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg
                                class="w-4 h-4 text-orange-500"
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
                            Batalkan
                        </button>

                        <!-- Delete -->
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-red-600 hover:bg-red-50 transition-colors"
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
                            Hapus
                        </button>

                        <!-- Customer PO -->
                        <button
                            v-if="
                                (doc.type === 'invoice' ||
                                    doc.type === 'proforma_invoice') &&
                                doc.customer_po_file
                            "
                            @click="handleViewPoFile"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
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
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            Lihat PO Customer
                        </button>

                        <!-- Export -->
                        <button
                            @click="handleExport"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-indofilter text-white hover:opacity-90 transition-colors"
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
                            Export DOCX
                        </button>
                    </div>
                </div>
            </div>

            <!-- =========================================================
                 DOCUMENT
            ========================================================== -->
            <div
                class="w-full px-3 sm:px-6 lg:px-8 pb-12 grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)_300px] gap-5 items-start"
            >
                <aside
                    class="lg:sticky lg:top-14 bg-white border border-gray-200 shadow-sm overflow-hidden"
                >
                    <div class="px-4 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="text-[10px] font-semibold text-gray-400">
                            Daftar Dokumen
                        </div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">
                            {{ doc.document_type?.replace(/_/g, " ") }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ documentList.length }} dokumen
                        </div>
                    </div>

                    <div class="max-h-[calc(100vh-220px)] overflow-y-auto">
                        <div
                            v-if="
                                !documentListLoading &&
                                documentList.length === 0
                            "
                            class="px-4 py-8 text-center text-xs text-gray-400"
                        >
                            Belum ada dokumen.
                        </div>
                        <button
                            v-for="item in documentList"
                            :key="item.id"
                            type="button"
                            class="w-full px-4 py-3 text-left border-b border-gray-100 transition-colors"
                            :class="
                                String(item.id) === selectedDocumentId
                                    ? 'bg-green-50 border-l-2 border-l-indofilter'
                                    : 'hover:bg-gray-50 border-l-2 border-l-transparent'
                            "
                            @click="selectDocument(item)"
                        >
                            <span
                                class="block truncate text-xs font-semibold"
                                :class="
                                    String(item.id) === selectedDocumentId
                                        ? 'text-indofilter'
                                        : 'text-gray-700'
                                "
                            >
                                {{
                                    item.document_number ||
                                    item.number ||
                                    `Draft #${item.id}`
                                }}
                            </span>
                        </button>
                    </div>
                </aside>

                <aside
                    id="document-side-panel"
                    class="min-w-0 space-y-5 lg:col-start-3"
                >
                    <!-- Reference -->
                    <div
                        class="bg-white border border-gray-200 shadow-sm px-5 py-4"
                    >
                        <div class="text-[10px] font-bold text-gray-400">
                            Referensi
                        </div>
                        <div class="text-sm font-semibold">
                            <router-link
                                v-if="doc.reference"
                                :to="{
                                    path: '/documents/' + doc.reference.id,
                                    query: {
                                        type: doc.reference.document_type,
                                    },
                                }"
                                class="text-indofilter hover:underline"
                            >
                                {{
                                    doc.reference.document_number ||
                                    `Draft #${doc.reference.id}`
                                }}
                            </router-link>

                            <span v-else class="text-gray-400"> - </span>
                        </div>
                    </div>
                </aside>

                <main class="min-w-0 lg:col-start-2 lg:row-start-1">
                    <div
                        class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
                    >
                        <!-- =================================================
                         INVOICE HEADER
                    ================================================== -->
                        <div
                            class="px-6 sm:px-8 lg:px-10 py-8 border-b border-gray-200 bg-linear-to-b from-white to-gray-50/70"
                        >
                            <div
                                class="flex flex-col md:flex-row md:items-start md:justify-between gap-8"
                            >
                                <!-- Company -->
                                <div class="min-w-0">
                                    <div
                                        class="text-[11px] font-semibold text-gray-400 mb-2"
                                    >
                                        {{ doc.company?.name || "-" }}
                                    </div>

                                    <h1
                                        class="text-3xl sm:text-4xl font-semibold tracking-tight text-gray-900 uppercase"
                                    >
                                        {{
                                            doc.document_type?.replace(
                                                /_/g,
                                                " ",
                                            )
                                        }}
                                    </h1>

                                    <div class="mt-2 text-sm text-gray-500">
                                        No. {{ doc.number || "-" }}
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="md:text-right">
                                    <span
                                        class="inline-flex items-center px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                                        :class="
                                            statusColors[doc.status] ||
                                            'bg-gray-100 text-gray-600'
                                        "
                                    >
                                        {{ doc.status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- =================================================
                         DOCUMENT META
                    ================================================== -->
                        <div
                            class="px-6 sm:px-8 lg:px-10 py-7 border-b border-gray-200"
                        >
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-7"
                            >
                                <!-- Company -->
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-2"
                                    >
                                        Perusahaan
                                    </div>
                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ doc.company?.name || "-" }}
                                    </div>
                                </div>

                                <!-- Partner -->
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-2"
                                    >
                                        Partner
                                    </div>
                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ doc.partner?.name || "-" }}
                                    </div>

                                    <div
                                        v-if="doc.partner?.address"
                                        class="text-xs text-gray-500 mt-1 leading-relaxed"
                                    >
                                        {{ doc.partner.address }}
                                    </div>
                                </div>

                                <!-- Date -->
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-2"
                                    >
                                        Tanggal
                                    </div>
                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ formatDate(doc.date) }}
                                    </div>

                                    <div
                                        v-if="
                                            doc.due_date &&
                                            (doc.type === 'invoice' ||
                                                doc.type === 'proforma_invoice')
                                        "
                                        class="text-xs text-gray-500 mt-1"
                                    >
                                        Jatuh tempo:
                                        {{ formatDate(doc.due_date) }}
                                    </div>
                                </div>

                                <!-- Customer Ref -->
                                <div
                                    v-if="
                                        doc.type === 'invoice' ||
                                        doc.type === 'proforma_invoice'
                                    "
                                >
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-2"
                                    >
                                        Customer Reference
                                    </div>
                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ doc.customer_po_number || "-" }}
                                    </div>
                                </div>

                                <!-- Delivery Slip PO -->
                                <div v-if="doc.type === 'delivery_slip'">
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-2"
                                    >
                                        No. PO Customer
                                    </div>
                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ doc.customer_po_number || "-" }}
                                    </div>
                                </div>

                                <div v-if="doc.type === 'delivery_slip'">
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-2"
                                    >
                                        Tanggal PO Customer
                                    </div>
                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ formatDate(doc.customer_po_date) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- =================================================
                         DELIVERY ADDRESS
                    ================================================== -->
                        <div
                            v-if="doc.type === 'delivery_address'"
                            class="px-6 sm:px-8 lg:px-10 py-7 border-b border-gray-200"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                                <!-- Sender -->
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-3"
                                    >
                                        Pengirim
                                    </div>

                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{
                                            doc.sender_name ||
                                            doc.company?.name ||
                                            "-"
                                        }}
                                    </div>

                                    <p
                                        v-if="
                                            doc.sender_phone ||
                                            doc.company?.phone
                                        "
                                        class="text-sm text-gray-600 mt-1"
                                    >
                                        {{
                                            doc.sender_phone ||
                                            doc.company?.phone
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            doc.sender_address ||
                                            doc.company?.address
                                        "
                                        class="text-sm text-gray-500 mt-1 leading-relaxed max-w-md"
                                    >
                                        {{
                                            doc.sender_address ||
                                            doc.company?.address
                                        }}
                                    </p>
                                </div>

                                <!-- Recipient -->
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-gray-400 mb-3"
                                    >
                                        Penerima
                                    </div>

                                    <div
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{
                                            doc.recipient_name ||
                                            doc.partner?.name ||
                                            "-"
                                        }}
                                    </div>

                                    <p
                                        v-if="doc.recipient_pic"
                                        class="text-xs text-gray-500 mt-1"
                                    >
                                        Up. {{ doc.recipient_pic }}
                                    </p>

                                    <p
                                        v-if="
                                            doc.recipient_phone ||
                                            doc.partner?.phone
                                        "
                                        class="text-sm text-gray-600 mt-1"
                                    >
                                        {{
                                            doc.recipient_phone ||
                                            doc.partner?.phone
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            doc.recipient_address ||
                                            doc.partner?.address
                                        "
                                        class="text-sm text-gray-500 mt-1 leading-relaxed max-w-md"
                                    >
                                        {{
                                            doc.recipient_address ||
                                            doc.partner?.address
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- =================================================
                         ITEMS
                    ================================================== -->
                        <div
                            v-if="doc.type !== 'delivery_address'"
                            class="px-6 sm:px-8 lg:px-10 py-7 border-b border-gray-200"
                        >
                            <div class="flex items-end justify-between mb-4">
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-gray-400"
                                    >
                                        Detail
                                    </div>
                                    <h2
                                        class="text-base font-semibold text-gray-900 mt-1"
                                    >
                                        Item Barang
                                    </h2>
                                </div>

                                <div
                                    class="text-[11px] text-gray-400 uppercase tracking-wide"
                                >
                                    {{ doc.items?.length || 0 }} Item
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-y border-gray-200">
                                            <th
                                                class="py-3 pr-4 text-left text-[10px] font-bold text-gray-400"
                                            >
                                                Barang
                                            </th>

                                            <th
                                                class="py-3 px-4 text-left text-[10px] font-bold text-gray-400"
                                            >
                                                Deskripsi
                                            </th>

                                            <th
                                                class="py-3 px-4 text-right text-[10px] font-bold text-gray-400"
                                            >
                                                Qty
                                            </th>

                                            <th
                                                class="py-3 px-4 text-left text-[10px] font-bold text-gray-400"
                                            >
                                                Satuan
                                            </th>

                                            <th
                                                v-if="
                                                    doc.type !== 'delivery_slip'
                                                "
                                                class="py-3 px-4 text-right text-[10px] font-bold text-gray-400"
                                            >
                                                Harga
                                            </th>

                                            <th
                                                v-if="
                                                    doc.type !== 'delivery_slip'
                                                "
                                                class="py-3 pl-4 text-right text-[10px] font-bold text-gray-400"
                                            >
                                                Total
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr
                                            v-for="(item, i) in doc.items"
                                            :key="i"
                                            class="border-b border-gray-100 align-top"
                                        >
                                            <!-- Product -->
                                            <td
                                                class="py-4 pr-4 text-sm text-gray-900"
                                            >
                                                <div class="font-semibold">
                                                    {{ item.product_name }}
                                                </div>

                                                <div
                                                    v-if="
                                                        item.variations &&
                                                        item.variations.length >
                                                            0
                                                    "
                                                    class="mt-2 space-y-1"
                                                >
                                                    <div
                                                        v-for="(
                                                            v, vi
                                                        ) in item.variations"
                                                        :key="vi"
                                                        class="text-[11px] text-gray-500 pl-3 border-l-2 border-gray-200"
                                                    >
                                                        {{ vi + 1 }}.
                                                        {{ v.name }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Description -->
                                            <td
                                                class="py-4 px-4 text-sm text-gray-500"
                                            >
                                                <div>
                                                    {{
                                                        item.description || "-"
                                                    }}
                                                </div>

                                                <div
                                                    v-if="
                                                        item.variations &&
                                                        item.variations.length >
                                                            0
                                                    "
                                                    class="mt-2 space-y-1"
                                                >
                                                    <div
                                                        v-for="(
                                                            v, vi
                                                        ) in item.variations"
                                                        :key="vi"
                                                        class="text-[11px] text-transparent"
                                                    >
                                                        &nbsp;
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Qty -->
                                            <td
                                                class="py-4 px-4 text-sm text-gray-700 text-right whitespace-nowrap"
                                            >
                                                <div
                                                    :class="{
                                                        'text-gray-400 font-normal':
                                                            item.variations &&
                                                            item.variations
                                                                .length > 0,
                                                    }"
                                                >
                                                    {{ item.qty }}
                                                </div>

                                                <div
                                                    v-if="
                                                        item.variations &&
                                                        item.variations.length >
                                                            0
                                                    "
                                                    class="mt-2 space-y-1"
                                                >
                                                    <div
                                                        v-for="(
                                                            v, vi
                                                        ) in item.variations"
                                                        :key="vi"
                                                        class="text-[11px] text-gray-500"
                                                    >
                                                        {{ v.qty }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- UOM -->
                                            <td
                                                class="py-4 px-4 text-sm text-gray-500 whitespace-nowrap"
                                            >
                                                <div>{{ item.uom }}</div>

                                                <div
                                                    v-if="
                                                        item.variations &&
                                                        item.variations.length >
                                                            0
                                                    "
                                                    class="mt-2 space-y-1"
                                                >
                                                    <div
                                                        v-for="(
                                                            v, vi
                                                        ) in item.variations"
                                                        :key="vi"
                                                        class="text-[11px] text-gray-400"
                                                    >
                                                        {{ item.uom }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Unit Price -->
                                            <td
                                                v-if="
                                                    doc.type !== 'delivery_slip'
                                                "
                                                class="py-4 px-4 text-sm text-gray-700 text-right whitespace-nowrap"
                                            >
                                                <div
                                                    :class="{
                                                        'text-gray-400 font-normal text-xs':
                                                            item.variations &&
                                                            item.variations
                                                                .length > 0,
                                                    }"
                                                >
                                                    <span
                                                        v-if="
                                                            item.variations &&
                                                            item.variations
                                                                .length > 0
                                                        "
                                                        class="text-[10px] text-gray-400 block mb-0.5"
                                                    >
                                                        (Rata-rata)
                                                    </span>

                                                    {{
                                                        formatMoney(
                                                            item.unit_price,
                                                        )
                                                    }}
                                                </div>

                                                <div
                                                    v-if="
                                                        item.variations &&
                                                        item.variations.length >
                                                            0
                                                    "
                                                    class="mt-2 space-y-1"
                                                >
                                                    <div
                                                        v-for="(
                                                            v, vi
                                                        ) in item.variations"
                                                        :key="vi"
                                                        class="text-[11px] text-gray-600"
                                                    >
                                                        {{
                                                            formatMoney(
                                                                v.unit_price,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Total -->
                                            <td
                                                v-if="
                                                    doc.type !== 'delivery_slip'
                                                "
                                                class="py-4 pl-4 text-sm text-gray-900 text-right font-semibold whitespace-nowrap"
                                            >
                                                <div>
                                                    {{
                                                        formatMoney(item.total)
                                                    }}
                                                </div>

                                                <div
                                                    v-if="
                                                        item.variations &&
                                                        item.variations.length >
                                                            0
                                                    "
                                                    class="mt-2 space-y-1"
                                                >
                                                    <div
                                                        v-for="(
                                                            v, vi
                                                        ) in item.variations"
                                                        :key="vi"
                                                        class="text-[11px] text-gray-500 font-normal"
                                                    >
                                                        {{
                                                            formatMoney(v.total)
                                                        }}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =================================================
                         TOTALS
                    ================================================== -->
                        <div
                            v-if="
                                doc.type !== 'delivery_slip' &&
                                doc.type !== 'delivery_address'
                            "
                            class="px-6 sm:px-8 lg:px-10 py-8 border-b border-gray-200"
                        >
                            <div class="flex justify-end">
                                <div class="w-full max-w-md">
                                    <div class="space-y-3">
                                        <!-- Subtotal -->
                                        <div
                                            class="flex justify-between gap-8 text-sm"
                                        >
                                            <span class="text-gray-500">
                                                Subtotal
                                            </span>

                                            <span
                                                class="font-medium text-gray-900"
                                            >
                                                {{
                                                    formatMoney(
                                                        doc.subtotal ||
                                                            doc.items?.reduce(
                                                                (s, i) =>
                                                                    s +
                                                                    (i.total ||
                                                                        0),
                                                                0,
                                                            ),
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <!-- Discount -->
                                        <div
                                            v-if="
                                                doc.discount &&
                                                Number(doc.discount) > 0
                                            "
                                            class="flex justify-between gap-8 text-sm"
                                        >
                                            <span class="text-gray-500">
                                                Diskon
                                            </span>

                                            <span class="text-red-600">
                                                -{{ formatMoney(doc.discount) }}
                                            </span>
                                        </div>

                                        <!-- Tax -->
                                        <div
                                            class="flex justify-between gap-8 text-sm"
                                        >
                                            <span class="text-gray-500">
                                                {{
                                                    doc.is_ppn
                                                        ? "PPN (11%)"
                                                        : "Non PPN"
                                                }}
                                            </span>

                                            <span class="text-gray-700">
                                                +{{ formatMoney(doc.tax) }}
                                            </span>
                                        </div>

                                        <!-- Full Bill -->
                                        <div
                                            v-if="
                                                doc.payment_type &&
                                                doc.payment_type !== 'full'
                                            "
                                            class="flex justify-between gap-8 text-sm border-t border-gray-200 pt-3"
                                        >
                                            <span
                                                class="text-gray-500 font-medium"
                                            >
                                                Total Tagihan Penuh
                                            </span>

                                            <span
                                                class="font-medium text-gray-900"
                                            >
                                                {{
                                                    formatMoney(
                                                        Number(doc.subtotal) +
                                                            Number(doc.tax),
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <!-- DP -->
                                        <div
                                            v-if="doc.payment_type === 'dp'"
                                            class="space-y-2 border-t border-gray-200 pt-3"
                                        >
                                            <div
                                                class="flex justify-between gap-8 text-sm"
                                            >
                                                <span
                                                    class="text-gray-500 font-medium"
                                                >
                                                    Uang Muka (DP
                                                    {{
                                                        parseFloat(
                                                            doc.dp_percent,
                                                        )
                                                    }}%)
                                                </span>

                                                <span
                                                    class="font-semibold text-gray-900"
                                                >
                                                    {{
                                                        formatMoney(
                                                            doc.dp_amount,
                                                        )
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                class="flex justify-between gap-8 text-sm"
                                            >
                                                <span class="text-gray-500">
                                                    Nominal Pelunasan ({{
                                                        100 -
                                                        parseFloat(
                                                            doc.dp_percent,
                                                        )
                                                    }}%)
                                                </span>

                                                <span
                                                    class="font-medium text-gray-500"
                                                >
                                                    {{
                                                        formatMoney(
                                                            Number(
                                                                doc.subtotal,
                                                            ) +
                                                                Number(
                                                                    doc.tax,
                                                                ) -
                                                                Number(
                                                                    doc.dp_amount,
                                                                ),
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Pelunasan -->
                                        <div
                                            v-if="
                                                doc.payment_type === 'pelunasan'
                                            "
                                            class="space-y-2 border-t border-gray-200 pt-3"
                                        >
                                            <div
                                                class="flex justify-between gap-8 text-sm"
                                            >
                                                <span class="text-gray-500">
                                                    Uang Muka (DP
                                                    {{
                                                        parseFloat(
                                                            doc.dp_percent,
                                                        )
                                                    }}%)
                                                </span>

                                                <span
                                                    class="font-medium text-gray-500"
                                                >
                                                    {{
                                                        formatMoney(
                                                            doc.dp_amount,
                                                        )
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                class="flex justify-between gap-8 text-sm"
                                            >
                                                <span
                                                    class="text-gray-500 font-medium"
                                                >
                                                    Nominal Pelunasan ({{
                                                        100 -
                                                        parseFloat(
                                                            doc.dp_percent,
                                                        )
                                                    }}%)
                                                </span>

                                                <span
                                                    class="font-semibold text-gray-900"
                                                >
                                                    {{
                                                        formatMoney(
                                                            Number(
                                                                doc.subtotal,
                                                            ) +
                                                                Number(
                                                                    doc.tax,
                                                                ) -
                                                                Number(
                                                                    doc.dp_amount,
                                                                ),
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Grand Total -->
                                    <div
                                        class="mt-5 pt-5 border-t-2 border-gray-900"
                                    >
                                        <div
                                            class="flex items-end justify-between gap-8"
                                        >
                                            <div>
                                                <div
                                                    class="text-[10px] font-bold text-gray-400"
                                                >
                                                    Grand Total
                                                </div>

                                                <div
                                                    class="text-sm font-medium text-gray-700 mt-1"
                                                >
                                                    {{
                                                        doc.payment_type ===
                                                        "dp"
                                                            ? "(DP)"
                                                            : doc.payment_type ===
                                                                "pelunasan"
                                                              ? "(Pelunasan)"
                                                              : ""
                                                    }}
                                                </div>
                                            </div>

                                            <div
                                                class="text-2xl font-bold text-gray-900 text-right"
                                            >
                                                {{
                                                    formatMoney(doc.grand_total)
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Terbilang -->
                                    <div
                                        v-if="
                                            (doc.type === 'invoice' ||
                                                doc.type ===
                                                    'proforma_invoice') &&
                                            doc.terbilang
                                        "
                                        class="mt-4 pt-3 border-t border-gray-100 text-xs text-gray-500 italic text-right"
                                    >
                                        <span
                                            class="font-semibold text-gray-700 not-italic"
                                        >
                                            Terbilang:
                                        </span>
                                        {{ doc.terbilang }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- =================================================
                         QUOTATION TERMS
                    ================================================== -->
                        <div
                            v-if="doc.type === 'quotation'"
                            class="px-6 sm:px-8 lg:px-10 py-7 border-b border-gray-200"
                        >
                            <div
                                class="text-[10px] font-bold text-gray-400 mb-5"
                            >
                                Ketentuan Penawaran
                            </div>

                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-x-8 gap-y-6"
                            >
                                <div>
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                    >
                                        Stock Conditions
                                    </div>
                                    <div class="text-sm text-gray-700 mt-1.5">
                                        {{ doc.stock_conditions || "-" }}
                                    </div>
                                </div>

                                <div>
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                    >
                                        Term of Payment
                                    </div>
                                    <div class="text-sm text-gray-700 mt-1.5">
                                        {{ doc.term_of_payment || "-" }}
                                    </div>
                                </div>

                                <div>
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                    >
                                        Price Conditions
                                    </div>
                                    <div class="text-sm text-gray-700 mt-1.5">
                                        {{ doc.price_conditions || "-" }}
                                    </div>
                                </div>

                                <div>
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                    >
                                        Standard Packing
                                    </div>
                                    <div class="text-sm text-gray-700 mt-1.5">
                                        {{ doc.standard_packing || "-" }}
                                    </div>
                                </div>

                                <div>
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                    >
                                        Offer Validity
                                    </div>
                                    <div class="text-sm text-gray-700 mt-1.5">
                                        {{ doc.offer_validity || "-" }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Teleport defer to="#document-side-panel">
                            <!-- =================================================
                            REFERENCED DOCUMENTS
                        ================================================== -->
                            <div
                                v-if="
                                    doc.referenced_by &&
                                    doc.referenced_by.length > 0
                                "
                                class="px-5 py-5 bg-white border border-gray-200 shadow-sm"
                            >
                                <div class="flex items-center gap-2 mb-4">
                                    <div
                                        class="text-[10px] font-bold text-gray-400"
                                    >
                                        Dokumen Terkait
                                    </div>

                                    <span
                                        class="text-[10px] font-bold text-gray-500"
                                    >
                                        {{ doc.referenced_by.length }}
                                    </span>
                                </div>

                                <div
                                    class="divide-y divide-gray-100 border-y border-gray-100"
                                >
                                    <router-link
                                        v-for="ref in doc.referenced_by"
                                        :key="ref.id"
                                        :to="{
                                            path: '/documents/' + ref.id,
                                            query: { type: ref.document_type },
                                        }"
                                        class="flex items-center justify-between gap-4 py-3.5 hover:bg-gray-50 transition-colors"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="text-sm font-semibold text-gray-900"
                                            >
                                                {{
                                                    ref.document_number ||
                                                    `Draft #${ref.id}`
                                                }}
                                            </p>

                                            <p
                                                class="text-xs text-gray-400 mt-1"
                                            >
                                                {{ ref.document_type }}

                                                <span v-if="ref.partner?.name">
                                                    · {{ ref.partner.name }}
                                                </span>

                                                <span v-if="ref.date">
                                                    · {{ formatDate(ref.date) }}
                                                </span>
                                            </p>
                                        </div>

                                        <div
                                            class="flex items-center gap-3 shrink-0"
                                        >
                                            <span
                                                class="text-[10px] font-semibold uppercase tracking-wide px-2 py-1"
                                                :class="{
                                                    'bg-gray-100 text-gray-500':
                                                        ref.status === 'draft',
                                                    'bg-green-50 text-green-700':
                                                        ref.status ===
                                                        'confirmed',
                                                    'bg-red-50 text-red-600':
                                                        ref.status ===
                                                        'canceled',
                                                }"
                                            >
                                                {{ ref.status }}
                                            </span>

                                            <svg
                                                class="w-4 h-4 text-gray-300"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                        </div>
                                    </router-link>
                                </div>
                            </div>

                            <!-- =================================================
                         SUPPORTING DOCUMENTS
                    ================================================== -->
                            <div
                                v-if="doc.type === 'invoice'"
                                class="px-5 py-5 bg-white border border-gray-200 shadow-sm"
                            >
                                <div
                                    class="flex items-center justify-between mb-5"
                                >
                                    <div>
                                        <div
                                            class="text-[10px] font-bold text-gray-400"
                                        >
                                            Dokumen Pendukung
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1">
                                            File yang terhubung dengan invoice
                                        </div>
                                    </div>

                                    <span
                                        class="text-xs font-semibold text-gray-500"
                                    >
                                        {{
                                            (doc.supporting_documents || [])
                                                .length
                                        }}
                                        / 10
                                    </span>
                                </div>

                                <!-- Upload -->
                                <div
                                    v-if="
                                        (doc.status === 'draft' ||
                                            doc.status === 'confirmed') &&
                                        (doc.supporting_documents || [])
                                            .length < 10
                                    "
                                    class="mb-5 border border-gray-200 bg-gray-50 p-5"
                                >
                                    <p
                                        class="text-xs font-semibold text-gray-700 mb-4"
                                    >
                                        Unggah Dokumen Pendukung Baru
                                    </p>

                                    <div
                                        class="flex flex-col md:flex-row gap-3 items-end"
                                    >
                                        <div class="w-full md:w-1/2">
                                            <label
                                                class="block text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5"
                                            >
                                                Prefiks Nama File (Opsional)
                                            </label>

                                            <input
                                                v-model="supportingTitle"
                                                type="text"
                                                placeholder="Contoh: Kwitansi DP, Foto Timbangan"
                                                class="w-full h-9 px-3 border border-gray-200 bg-white text-xs outline-none focus:border-gray-400 transition-colors"
                                            />
                                        </div>

                                        <div
                                            class="w-full md:flex-1 flex gap-2"
                                        >
                                            <button
                                                @click="triggerSupportingUpload"
                                                type="button"
                                                class="flex-1 h-9 px-3 border border-gray-200 bg-white text-gray-700 hover:bg-gray-100 text-xs font-semibold flex items-center justify-center gap-1.5 cursor-pointer"
                                            >
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
                                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                                    />
                                                </svg>

                                                {{
                                                    supportingFile
                                                        ? supportingFile.name
                                                        : "Pilih File"
                                                }}
                                            </button>

                                            <input
                                                type="file"
                                                ref="supportingFileInput"
                                                style="display: none"
                                                @change="
                                                    onSupportingFileSelected
                                                "
                                                accept=".pdf,.jpeg,.png,.jpg,.gif,.doc,.docx,.xls,.xlsx,.zip,.rar"
                                            />

                                            <button
                                                @click="uploadSupportingFile"
                                                type="button"
                                                :disabled="
                                                    isUploadingSupporting ||
                                                    !supportingFile
                                                "
                                                class="h-9 px-4 bg-gray-900 text-white hover:bg-gray-800 text-xs font-semibold flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <svg
                                                    v-if="isUploadingSupporting"
                                                    class="animate-spin h-3 w-3"
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

                                                <svg
                                                    v-else
                                                    class="w-3.5 h-3.5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                                    />
                                                </svg>

                                                Unggah
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Files -->
                                <div
                                    v-if="
                                        doc.supporting_documents &&
                                        doc.supporting_documents.length > 0
                                    "
                                    class="divide-y divide-gray-100 border-y border-gray-100"
                                >
                                    <div
                                        v-for="(
                                            file, index
                                        ) in doc.supporting_documents"
                                        :key="index"
                                        class="flex items-center justify-between gap-4 py-4"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <button
                                                @click="
                                                    downloadSupportingFile(
                                                        file.filename,
                                                    )
                                                "
                                                type="button"
                                                class="text-sm font-semibold text-gray-900 hover:text-indofilter hover:underline text-left block truncate cursor-pointer"
                                            >
                                                {{
                                                    file.title ||
                                                    file.original_name
                                                }}
                                            </button>

                                            <p
                                                class="text-[10px] text-gray-400 mt-1 flex flex-wrap gap-x-2"
                                            >
                                                <span>
                                                    Nama file:
                                                    {{ file.filename }}
                                                </span>

                                                <span>•</span>

                                                <span>
                                                    Diunggah:
                                                    {{
                                                        file.uploaded_at ||
                                                        formatDate(
                                                            doc.created_at,
                                                        )
                                                    }}
                                                </span>
                                            </p>
                                        </div>

                                        <div
                                            class="flex items-center gap-2 shrink-0"
                                        >
                                            <button
                                                @click="
                                                    downloadSupportingFile(
                                                        file.filename,
                                                    )
                                                "
                                                type="button"
                                                class="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition-colors cursor-pointer"
                                                title="Unduh / Buka"
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
                                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                                    />
                                                </svg>
                                            </button>

                                            <button
                                                v-if="
                                                    doc.status === 'draft' ||
                                                    doc.status === 'confirmed'
                                                "
                                                @click="
                                                    deleteSupportingFile(
                                                        file.filename,
                                                    )
                                                "
                                                type="button"
                                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
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
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 01-1 1v3M4 7h16"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty -->
                                <div
                                    v-else
                                    class="text-center py-8 border border-dashed border-gray-200"
                                >
                                    <svg
                                        class="mx-auto h-7 w-7 text-gray-300"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 13h6m-3-3v6m-9 1V4a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"
                                        />
                                    </svg>

                                    <p class="mt-2 text-xs text-gray-400">
                                        Belum ada dokumen pendukung diunggah
                                    </p>
                                </div>
                            </div>

                            <!-- =================================================
                         TERMS / NOTES / PAYMENT
                    ================================================== -->
                            <div
                                class="px-5 py-5 bg-white border border-gray-200 shadow-sm"
                            >
                                <!-- Terms & Notes -->
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-10"
                                >
                                    <div v-if="doc.terms">
                                        <div
                                            class="text-[10px] font-bold text-gray-400 mb-2"
                                        >
                                            Syarat Pembayaran
                                        </div>

                                        <p
                                            class="text-sm text-gray-600 leading-relaxed"
                                        >
                                            {{ doc.terms }}
                                        </p>
                                    </div>

                                    <div v-if="doc.notes">
                                        <div
                                            class="text-[10px] font-bold text-gray-400 mb-2"
                                        >
                                            Catatan
                                        </div>

                                        <p
                                            class="text-sm text-gray-600 leading-relaxed"
                                        >
                                            {{ doc.notes }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Company Bank -->
                                <div
                                    v-if="
                                        (doc.type === 'invoice' ||
                                            doc.type === 'proforma_invoice') &&
                                        (doc.bank_account || doc.bankAccount)
                                    "
                                    class="mt-8 pt-6 border-t border-gray-200"
                                >
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-2 gap-8"
                                    >
                                        <div>
                                            <div
                                                class="text-[10px] font-bold text-gray-400 mb-2"
                                            >
                                                Informasi Pembayaran
                                            </div>

                                            <p
                                                class="text-xs text-gray-500 leading-relaxed"
                                            >
                                                Pembayaran dapat ditujukan ke
                                                rekening perusahaan berikut:
                                            </p>
                                        </div>

                                        <div>
                                            <p
                                                class="text-sm font-semibold text-gray-900"
                                            >
                                                {{
                                                    (
                                                        doc.bank_account ||
                                                        doc.bankAccount
                                                    ).bank_name
                                                }}
                                            </p>

                                            <p
                                                class="text-sm text-gray-600 mt-0.5"
                                            >
                                                {{
                                                    (
                                                        doc.bank_account ||
                                                        doc.bankAccount
                                                    ).account_name
                                                }}
                                            </p>

                                            <p
                                                class="text-sm font-bold text-indofilter mt-2 tracking-wide"
                                            >
                                                {{
                                                    (
                                                        doc.bank_account ||
                                                        doc.bankAccount
                                                    ).account_number
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vendor Bank -->
                                <div
                                    v-if="
                                        doc.type === 'purchase_order' &&
                                        (doc.vendor_bank_name ||
                                            doc.partner?.bank_name)
                                    "
                                    class="mt-8 pt-6 border-t border-gray-200"
                                >
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-2 gap-8"
                                    >
                                        <div>
                                            <div
                                                class="text-[10px] font-bold text-gray-400 mb-2"
                                            >
                                                Informasi Pembayaran Vendor
                                            </div>

                                            <p
                                                class="text-xs text-gray-500 leading-relaxed"
                                            >
                                                Pembayaran PO ini ditujukan ke
                                                rekening vendor berikut:
                                            </p>
                                        </div>

                                        <div>
                                            <p
                                                class="text-sm font-semibold text-gray-900"
                                            >
                                                {{
                                                    doc.vendor_bank_name ||
                                                    doc.partner?.bank_name
                                                }}
                                            </p>

                                            <p
                                                class="text-sm text-gray-600 mt-0.5"
                                            >
                                                {{
                                                    doc.vendor_bank_account_name ||
                                                    doc.partner
                                                        ?.bank_account_name
                                                }}
                                            </p>

                                            <p
                                                class="text-sm font-bold text-indofilter mt-2 tracking-wide"
                                            >
                                                {{
                                                    doc.vendor_bank_account_number ||
                                                    doc.partner
                                                        ?.bank_account_number
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>

                        <!-- Document footer -->
                        <div
                            class="px-6 sm:px-8 lg:px-10 py-4 border-t border-gray-100 bg-gray-50"
                        >
                            <div
                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-[10px] text-gray-400"
                            >
                                <span>
                                    {{ doc.document_type?.replace(/_/g, " ") }}
                                </span>

                                <span> No. {{ doc.number || "-" }} </span>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </template>
    </div>
</template>
