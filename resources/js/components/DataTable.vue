<script setup>
defineProps({
    columns: { type: Array, required: true },
    data: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyMessage: { type: String, default: "Tidak ada data" },
    sortKey: { type: String, default: "" },
    sortDirection: { type: String, default: "asc" },
});

const emit = defineEmits(["sort"]);
</script>

<template>
    <div class="relative">
        <div
            v-if="loading"
            class="absolute inset-0 bg-white/80 flex items-center justify-center z-10 rounded-lg"
        >
            <div class="flex items-center gap-2 text-gray-500">
                <svg
                    class="animate-spin h-5 w-5"
                    xmlns="http://www.w3.org/2000/svg"
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
                <span>Memuat...</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            <button
                                v-if="col.sortable"
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-gray-800 transition-colors"
                                @click="emit('sort', col.key)"
                            >
                                {{ col.label }}
                                <svg
                                    v-if="
                                        sortKey === col.key &&
                                        sortDirection === 'asc'
                                    "
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="m5 15 7-7 7 7"
                                    />
                                </svg>
                                <svg
                                    v-else-if="sortKey === col.key"
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="m19 9-7 7-7-7"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="w-3.5 h-3.5 text-gray-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="m8 9 4-4 4 4m0 6-4 4-4-4"
                                    />
                                </svg>
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="data.length === 0 && !loading">
                        <td
                            :colspan="columns.length"
                            class="px-4 py-8 text-center text-gray-400"
                        >
                            {{ emptyMessage }}
                        </td>
                    </tr>
                    <tr
                        v-for="(row, i) in data"
                        :key="row.id || i"
                        :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                        class="hover:bg-blue-50 transition-colors"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 text-sm text-gray-700"
                            :class="
                                col.wrap ||
                                ['name', 'description', 'address'].includes(
                                    col.key,
                                )
                                    ? 'whitespace-normal min-w-[200px]'
                                    : 'whitespace-nowrap'
                            "
                        >
                            <slot
                                :name="'cell-' + col.key"
                                :row="row"
                                :value="row[col.key]"
                            >
                                {{ row[col.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
