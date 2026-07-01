<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { search as api } from "../api/index.js";

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(["close"]);
const router = useRouter();

const searchInput = ref(null);
const query = ref("");
const loading = ref(false);
const results = ref({
    documents: [],
    partners: [],
    products: [],
});

const focusedIndex = ref(-1);
const flatResults = ref([]);

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            query.value = "";
            results.value = { documents: [], partners: [], products: [] };
            flatResults.value = [];
            focusedIndex.value = -1;
            nextTick(() => {
                if (searchInput.value) {
                    searchInput.value.focus();
                }
            });
        }
    }
);

let debounceTimeout = null;

watch(query, (newVal) => {
    clearTimeout(debounceTimeout);
    const q = newVal.trim();
    if (q.length < 2) {
        results.value = { documents: [], partners: [], products: [] };
        flatResults.value = [];
        focusedIndex.value = -1;
        return;
    }

    loading.value = true;
    debounceTimeout = setTimeout(async () => {
        try {
            const res = await api.globalSearch(q);
            results.value = res.data;
            buildFlatResults();
        } catch (e) {
            console.error("Gagal melakukan pencarian global", e);
        } finally {
            loading.value = false;
        }
    }, 300);
});

function buildFlatResults() {
    const list = [];
    
    // Add documents
    (results.value.documents || []).forEach(doc => {
        list.push({
            type: "document",
            id: doc.id,
            title: doc.document_number || "(Draft)",
            subtitle: `${doc.document_type} - ${doc.partner?.name || "No Partner"}`,
            company: doc.company?.alias || doc.company?.name || "",
            path: { path: `/documents/${doc.id}`, query: { type: doc.document_type } }
        });
    });

    // Add partners
    (results.value.partners || []).forEach(p => {
        list.push({
            type: "partner",
            id: p.id,
            title: p.name,
            subtitle: `Partner - ${p.contact_person || p.phone || ""}`,
            company: p.company?.alias || p.company?.name || "",
            path: { path: "/partners", query: { search: p.name } }
        });
    });

    // Add products
    (results.value.products || []).forEach(prod => {
        list.push({
            type: "product",
            id: prod.id,
            title: prod.name,
            subtitle: `Produk - Kode: ${prod.code || ""} - Harga: ${formatMoney(prod.price)}`,
            company: prod.company?.alias || prod.company?.name || "",
            path: { path: "/products", query: { search: prod.name } }
        });
    });

    flatResults.value = list;
    focusedIndex.value = list.length > 0 ? 0 : -1;
}

function formatMoney(amount) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(amount);
}

function openResult(item) {
    emit("close");
    router.push(item.path);
}

function handleKeydown(e) {
    if (!props.show) return;
    
    if (e.key === "Escape") {
        e.preventDefault();
        emit("close");
    } else if (e.key === "ArrowDown") {
        e.preventDefault();
        if (flatResults.value.length > 0) {
            focusedIndex.value = (focusedIndex.value + 1) % flatResults.value.length;
            scrollIntoView();
        }
    } else if (e.key === "ArrowUp") {
        e.preventDefault();
        if (flatResults.value.length > 0) {
            focusedIndex.value = (focusedIndex.value - 1 + flatResults.value.length) % flatResults.value.length;
            scrollIntoView();
        }
    } else if (e.key === "Enter") {
        e.preventDefault();
        if (focusedIndex.value >= 0 && focusedIndex.value < flatResults.value.length) {
            openResult(flatResults.value[focusedIndex.value]);
        }
    }
}

function scrollIntoView() {
    nextTick(() => {
        const activeEl = document.querySelector(".search-result-item-active");
        if (activeEl) {
            activeEl.scrollIntoView({ block: "nearest" });
        }
    });
}

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
    <Transition name="fade">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4 bg-black/40 backdrop-blur-xs"
            @click.self="emit('close')"
        >
            <Transition name="scale">
                <div
                    class="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-xl flex flex-col overflow-hidden max-h-[70vh]"
                >
                    <!-- Search Input Wrapper -->
                    <div class="flex items-center px-4 py-3 border-b border-gray-150 gap-3">
                        <svg
                            class="w-5 h-5 text-gray-400 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            ></path>
                        </svg>
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="text"
                            placeholder="Ketik minimal 2 karakter untuk mencari dokumen, partner, atau produk..."
                            class="flex-1 outline-none text-sm text-gray-800 placeholder-gray-400 bg-transparent"
                        />
                        <button
                            @click="emit('close')"
                            class="text-xs text-gray-400 hover:text-gray-650 bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded transition-colors"
                        >
                            ESC
                        </button>
                    </div>

                    <!-- Search Results Scrollable Area -->
                    <div class="flex-1 overflow-y-auto p-2 min-h-[150px] max-h-[50vh]">
                        <!-- Loading State -->
                        <div v-if="loading" class="flex flex-col items-center justify-center py-10 gap-2">
                            <svg
                                class="animate-spin h-6 w-6 text-indofilter"
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
                            <span class="text-xs text-gray-400">Mencari...</span>
                        </div>

                        <!-- Empty/Instruction state -->
                        <div
                            v-else-if="query.trim().length < 2"
                            class="flex flex-col items-center justify-center py-12 text-gray-400 gap-2 text-center px-4"
                        >
                            <span class="text-sm font-semibold text-gray-700">Pencarian Global Lintas Perusahaan</span>
                            <span class="text-[11px] leading-relaxed max-w-sm">
                                Cari dokumen penawaran, surat jalan, invoice, partner (customer/vendor), atau produk Anda. Pencarian ini mencakup semua perusahaan sekaligus.
                            </span>
                        </div>

                        <!-- No results found state -->
                        <div
                            v-else-if="flatResults.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-gray-405 gap-2 text-center"
                        >
                            <span class="text-sm font-semibold">Tidak Ada Hasil Ditemukan</span>
                            <span class="text-[11px]">
                                Kata kunci "{{ query }}" tidak cocok dengan data mana pun.
                            </span>
                        </div>

                        <!-- Results List -->
                        <div v-else class="space-y-0.5">
                            <button
                                v-for="(item, index) in flatResults"
                                :key="item.type + '-' + item.id"
                                @click="openResult(item)"
                                class="w-full flex items-center justify-between text-left px-3 py-2.5 rounded-lg transition-colors border border-transparent select-none outline-none animate-fade-in"
                                :class="[
                                    index === focusedIndex
                                        ? 'bg-blue-50/75 border-blue-200/50 search-result-item-active'
                                        : 'hover:bg-gray-50'
                                ]"
                                @mouseenter="focusedIndex = index"
                            >
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <!-- Icon based on type -->
                                    <div
                                        class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 shadow-xs"
                                        :class="[
                                            item.type === 'document' ? 'bg-blue-100 text-blue-700' :
                                            item.type === 'partner' ? 'bg-green-100 text-green-700' :
                                            'bg-purple-100 text-purple-700'
                                        ]"
                                    >
                                        <!-- Document Icon -->
                                        <svg v-if="item.type === 'document'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <!-- Partner Icon -->
                                        <svg v-else-if="item.type === 'partner'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <!-- Product Icon -->
                                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="text-sm font-semibold text-gray-850 truncate">{{ item.title }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ item.subtitle }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span
                                        v-if="item.company"
                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border border-gray-200 shadow-xs"
                                    >
                                        {{ item.company }}
                                    </span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Keyboard help footer -->
                    <div class="bg-gray-50 px-4 py-2 text-[10px] text-gray-400 flex justify-between items-center border-t border-gray-150 select-none">
                        <div class="flex gap-3">
                            <span><kbd class="bg-white border px-1 rounded shadow-xs">↑↓</kbd> Navigasi</span>
                            <span><kbd class="bg-white border px-1 rounded shadow-xs">Enter</kbd> Pilih</span>
                        </div>
                        <span>Global Search Lintas Perusahaan</span>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.scale-enter-active,
.scale-leave-active {
    transition: transform 0.15s ease, opacity 0.15s ease;
}
.scale-enter-from,
.scale-leave-to {
    transform: scale(0.97);
    opacity: 0;
}
</style>
