<script setup>
import { ref, onMounted } from "vue";
import { settings as api } from "../../api/index.js";
import { useAppStore } from "../../stores/app.js";

const loading = ref(true);
const saving = ref(false);
const error = ref("");
const success = ref("");
const appStore = useAppStore();

const form = ref({
    documents_storage_path: "",
    global_folders: {},
    company_folders: {},
    gemini_api_key: "",
    gemini_model: "gemini-1.5-flash",
});

const companies = ref([]);

const documentTypes = [
    { key: "quotation", label: "Quotation" },
    { key: "proforma_invoice", label: "Proforma Invoice" },
    { key: "invoice", label: "Invoice" },
    { key: "delivery_slip", label: "Delivery Slip (Surat Jalan)" },
    { key: "delivery_address", label: "Delivery Address (Alamat Surat)" },
    { key: "purchase_order", label: "Purchase Order" },
    { key: "po_masuk", label: "PO Masuk (Customer PO)" },
    { key: "supporting_docs", label: "Dokumen Pendukung Invoice" },
];

const activeCompanyTab = ref("global"); // 'global' or company ID

async function loadSettings() {
    loading.value = true;
    error.value = "";
    success.value = "";
    try {
        const res = await api.getGoogleDrive();
        const data = res.data;
        form.value.documents_storage_path = data.documents_storage_path || "";
        form.value.gemini_api_key = data.gemini_api_key || "";
        form.value.gemini_model = data.gemini_model || "gemini-1.5-flash";

        // Initialize global folders safely
        form.value.global_folders = {};
        const responseGlobalFolders = data.global_folders || {};
        for (const dt of documentTypes) {
            form.value.global_folders[dt.key] =
                responseGlobalFolders[dt.key] || "";
        }

        // Initialize company folders safely
        form.value.company_folders = {};
        const responseCompanyFolders = data.company_folders || {};
        companies.value = data.companies || [];

        for (const company of companies.value) {
            form.value.company_folders[company.id] = {};
            const compFolderData = responseCompanyFolders[company.id] || {};
            for (const dt of documentTypes) {
                form.value.company_folders[company.id][dt.key] =
                    compFolderData[dt.key] || "";
            }
        }
    } catch (e) {
        error.value = "Gagal memuat pengaturan penyimpanan lokal.";
    }
    fillSettings();
}

async function fillSettings() {
    // Helper to run safely
    loading.value = false;
}

async function handleSave() {
    saving.value = true;
    error.value = "";
    success.value = "";
    try {
        await api.updateGoogleDrive(form.value);
        success.value = "Pengaturan folder penyimpanan berhasil disimpan.";
        await loadSettings();
    } catch (e) {
        error.value = e.response?.data?.message || "Gagal menyimpan pengaturan";
    } finally {
        saving.value = false;
    }
}

function ensureCompanyFolderInitialized(companyId) {
    if (!form.value.company_folders[companyId]) {
        form.value.company_folders[companyId] = {};
    }
    for (const dt of documentTypes) {
        if (form.value.company_folders[companyId][dt.key] === undefined) {
            form.value.company_folders[companyId][dt.key] = "";
        }
    }
}

const targetDriveLetter = ref("");
const changingDrive = ref(false);

async function handleBulkChangeDrive() {
    if (!targetDriveLetter.value) return;

    appStore.showConfirm(
        "Ubah Huruf Drive",
        `Apakah Anda yakin ingin mengubah semua huruf drive di database dan file .env menjadi "${targetDriveLetter.value.toUpperCase()}:"?`,
        async () => {
            changingDrive.value = true;
            error.value = "";
            success.value = "";
            try {
                const response = await api.changeDriveLetter(
                    targetDriveLetter.value,
                );
                success.value = response.data.message;
                targetDriveLetter.value = "";
                await loadSettings();
            } catch (e) {
                error.value =
                    e.response?.data?.message || "Gagal mengubah huruf drive";
            } finally {
                changingDrive.value = false;
            }
        },
    );
}

onMounted(() => {
    loadSettings();
});
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

        <template v-else>
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    Pengaturan Folder Penyimpanan Lokal
                </h2>
                <div class="flex gap-2">
                    <!-- Save Settings -->
                    <button
                        @click="handleSave"
                        :disabled="saving"
                        class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg
                            v-if="saving"
                            class="animate-spin h-4 w-4"
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
                        Simpan
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <div
                v-if="error"
                class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4"
            >
                {{ error }}
            </div>
            <div
                v-if="success"
                class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3 mb-4"
            >
                {{ success }}
            </div>

            <div class="space-y-4">
                <!-- Base Path Section -->
                <div
                    class="bg-white rounded-lg border border-gray-200 p-6 space-y-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-150 pb-2 mb-2"
                    >
                        <h3
                            class="text-xs font-semibold text-gray-500 uppercase tracking-wider"
                        >
                            Base Path Utama (Default)
                        </h3>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-655 mb-1 font-semibold"
                            >
                                Base Path Penyimpanan Lokal
                            </label>
                            <input
                                v-model="form.documents_storage_path"
                                type="text"
                                placeholder="Contoh: G:\My Drive\Arthawa"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                            <div
                                class="bg-blue-50/50 border border-blue-100 rounded-lg p-3 text-xs text-blue-800 space-y-1 mt-2"
                            >
                                <p class="font-bold flex items-center gap-1">
                                    💡 Info Otomatisasi Path:
                                </p>
                                <p class="text-[11px] leading-relaxed">
                                    Jika Anda mengisi **Base Path Utama**
                                    (contoh:
                                    <code
                                        class="bg-blue-100 px-1 py-0.5 rounded font-mono font-bold text-[10px]"
                                        >G:\My Drive\Arthawa</code
                                    >), sistem akan secara otomatis menyusun dan
                                    membuat folder penyimpanan dokumen di
                                    komputer Anda dengan struktur:
                                    <br />
                                    <code
                                        class="bg-blue-100 px-1 py-0.5 rounded font-mono font-bold text-[10px] select-all"
                                        >[Base Path] \ [Nama Perusahaan] \ [TIPE
                                        DOKUMEN]</code
                                    >
                                    <br />
                                    Misal untuk Invoice pada PT. INDO FILTER
                                    SEMESTA:
                                    <br />
                                    <code
                                        class="bg-blue-100 px-1 py-0.5 rounded font-mono font-bold text-[10px] select-all"
                                        >G:\My Drive\Arthawa\PT INDO FILTER
                                        SEMESTA\INVOICE</code
                                    >
                                </p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-150">
                                <label
                                    class="block text-xs font-semibold text-gray-700 mb-1"
                                >
                                    ⚡ Ubah Huruf Drive Massal
                                </label>
                                <p
                                    class="text-[11px] text-gray-500 mb-2 leading-relaxed"
                                >
                                    Jika drive Anda sering berubah (misal dari
                                    G: ke H:), gunakan fitur ini untuk mengubah
                                    huruf drive depannya secara sekaligus pada
                                    semua path database dan file konfigurasi
                                    <code
                                        class="bg-gray-100 px-1 py-0.5 rounded font-mono font-bold text-[10px]"
                                        >.env</code
                                    >.
                                </p>
                                <div class="flex gap-2 max-w-xs">
                                    <input
                                        v-model="targetDriveLetter"
                                        type="text"
                                        maxlength="1"
                                        placeholder="H"
                                        class="w-16 px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-center font-bold uppercase focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    />
                                    <button
                                        @click="handleBulkChangeDrive"
                                        type="button"
                                        :disabled="
                                            changingDrive || !targetDriveLetter
                                        "
                                        class="bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
                                    >
                                        <svg
                                            v-if="changingDrive"
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
                                        Ganti Huruf Drive
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gemini API Key Section -->
                <div
                    class="bg-white rounded-lg border border-gray-200 p-6 space-y-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-150 pb-2 mb-2"
                    >
                        <h3
                            class="text-xs font-semibold text-gray-500 uppercase tracking-wider"
                        >
                            Integrasi AI (Google Gemini)
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-700 mb-1"
                            >
                                Gemini API Key
                            </label>
                            <input
                                v-model="form.gemini_api_key"
                                type="password"
                                placeholder="Masukkan API Key Gemini Anda"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-700 mb-1"
                            >
                                Gemini Model
                            </label>
                            <input
                                v-model="form.gemini_model"
                                type="text"
                                placeholder="Contoh: gemini-1.5-flash atau gemini-2.5-flash"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>
                    <div
                        class="bg-purple-50/50 border border-purple-100 rounded-lg p-3 text-xs text-purple-950 space-y-1"
                    >
                        <p class="font-bold flex items-center gap-1">
                            💡 Petunjuk Penggunaan Gemini API:
                        </p>
                        <p class="text-[11px] leading-relaxed">
                            API Key digunakan untuk fitur pembacaan PDF otomatis
                            menggunakan kecerdasan buatan. Anda dapat membuat
                            API Key secara gratis di
                            <a
                                href="https://aistudio.google.com/"
                                target="_blank"
                                class="text-purple-700 hover:text-purple-900 underline font-bold transition-colors"
                                >Google AI Studio</a
                            >. Jika kolom ini dikosongkan, sistem akan mencoba
                            menggunakan konfigurasi
                            <code
                                class="bg-purple-100 px-1 py-0.5 rounded font-mono font-bold text-[10px]"
                                >GEMINI_API_KEY</code
                            >
                            dari file .env. Sedangkan Model Gemini menentukan
                            model mana yang akan digunakan untuk pemrosesan
                            dokumen (bawaan:
                            <code
                                class="bg-purple-100 px-1 py-0.5 rounded font-mono font-bold text-[10px]"
                                >gemini-1.5-flash</code
                            >).
                        </p>
                    </div>
                </div>

                <!-- Folders Settings Section -->
                <div
                    class="bg-white rounded-lg border border-gray-200 p-6 space-y-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-150 pb-2 mb-2"
                    >
                        <h3
                            class="text-xs font-semibold text-gray-500 uppercase tracking-wider"
                        >
                            Pemetaan Kustom (Manual / Opsional)
                        </h3>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium"
                                >Konfigurasi Untuk:</span
                            >
                            <select
                                v-model="activeCompanyTab"
                                @change="
                                    activeCompanyTab !== 'global' &&
                                    ensureCompanyFolderInitialized(
                                        activeCompanyTab,
                                    )
                                "
                                class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                                <option value="global">
                                    Bawaan (Semua Perusahaan / Global)
                                </option>
                                <option
                                    v-for="c in companies"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }} ({{ c.alias }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="bg-amber-50/50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800"
                    >
                        <p class="font-bold flex items-center gap-1 mb-1">
                            ⚠️ Informasi Pemetaan Manual:
                        </p>
                        Jika kolom-kolom path di bawah ini diisi, sistem akan
                        menggunakan path absolut yang Anda masukkan secara
                        manual untuk menimpa aturan default di atas.
                        <strong>Biarkan kosong</strong> jika ingin menggunakan
                        aturan subfolder otomatis.
                    </div>

                    <!-- Global Folders form -->
                    <div
                        v-if="
                            activeCompanyTab === 'global' && form.global_folders
                        "
                        class="grid grid-cols-1 md:grid-cols-2 gap-4"
                    >
                        <div v-for="dt in documentTypes" :key="dt.key">
                            <label
                                class="block text-xs font-medium text-gray-655 mb-1"
                            >
                                {{ dt.label }}
                            </label>
                            <input
                                v-model="form.global_folders[dt.key]"
                                type="text"
                                :placeholder="
                                    'Contoh: G:\\My Drive\\Arthawa\\Global\\' +
                                    dt.key.toUpperCase()
                                "
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>

                    <!-- Company Folders form -->
                    <div
                        v-else-if="
                            activeCompanyTab !== 'global' &&
                            form.company_folders &&
                            form.company_folders[activeCompanyTab]
                        "
                        class="grid grid-cols-1 md:grid-cols-2 gap-4"
                    >
                        <div v-for="dt in documentTypes" :key="dt.key">
                            <label
                                class="block text-xs font-medium text-gray-655 mb-1"
                            >
                                {{ dt.label }}
                            </label>
                            <input
                                v-model="
                                    form.company_folders[activeCompanyTab][
                                        dt.key
                                    ]
                                "
                                type="text"
                                :placeholder="
                                    'Contoh: G:\\My Drive\\Arthawa\\Khusus\\' +
                                    dt.key.toUpperCase()
                                "
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
