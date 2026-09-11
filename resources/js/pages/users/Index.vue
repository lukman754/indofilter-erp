<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { users as api } from '../../api/index.js'
import { useAppStore } from '../../stores/app.js'
import DataTable from '../../components/DataTable.vue'
import FormModal from '../../components/FormModal.vue'

const items = ref([])
const loading = ref(false)
const formLoading = ref(false)
const showModal = ref(false)
const editing = ref(null)
const error = ref('')
const search = ref('')

const appStore = useAppStore()

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const form = ref({ name: '', email: '', password: '' })

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'email', label: 'Email' },
    { key: 'created_at', label: 'Tanggal Dibuat' },
    { key: 'actions', label: 'Aksi' },
]

const filteredItems = computed(() => {
    let result = items.value
    if (search.value) {
        const s = search.value.toLowerCase()
        result = result.filter(r => r.name?.toLowerCase().includes(s) || r.email?.toLowerCase().includes(s))
    }
    return result
})

async function fetchData() {
    loading.value = true
    try {
        const res = await api.list()
        items.value = res.data.data || res.data || []
    } catch (e) {
        items.value = []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    editing.value = null
    form.value = { name: '', email: '', password: '' }
    error.value = ''
    showModal.value = true
}

function openEdit(item) {
    editing.value = item.id
    form.value = { name: item.name, email: item.email, password: '' }
    error.value = ''
    showModal.value = true
}

async function save() {
    formLoading.value = true
    error.value = ''
    try {
        if (editing.value) {
            await api.update(editing.value, form.value)
            appStore.showNotification('Sukses', 'Akun berhasil diperbarui.', 'success')
        } else {
            await api.create(form.value)
            appStore.showNotification('Sukses', 'Akun berhasil dibuat.', 'success')
        }
        showModal.value = false
        await fetchData()
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menyimpan data'
    } finally {
        formLoading.value = false
    }
}

async function confirmDelete(id) {
    appStore.showConfirm(
        'Hapus Akun',
        'Yakin ingin menghapus akun ini?',
        async () => {
            try {
                await api.delete(id)
                await fetchData()
                appStore.showNotification('Sukses', 'Akun berhasil dihapus.', 'success')
            } catch (e) {
                appStore.showNotification('Gagal', e.response?.data?.message || 'Gagal menghapus data.', 'error')
            }
        }
    )
}

const route = useRoute()
const searchInput = ref(null)

const handleLocalKeydown = (e) => {
    if (e.ctrlKey && e.key.toLowerCase() === 'f') {
        e.preventDefault()
        if (searchInput.value) {
            searchInput.value.focus()
            searchInput.value.select()
        }
    }
    if (e.ctrlKey && e.key.toLowerCase() === 'n') {
        e.preventDefault()
        openCreate()
    }
    if (e.ctrlKey && e.key.toLowerCase() === 's') {
        if (showModal.value) {
            e.preventDefault()
            save()
        }
    }
}

onMounted(async () => {
    window.addEventListener('keydown', handleLocalKeydown)
    if (route.query.search) {
        search.value = route.query.search
    }
    await fetchData()
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleLocalKeydown)
})

watch(() => route.query.search, (newVal) => {
    search.value = newVal || ''
})
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-3">
                <input ref="searchInput" v-model="search" type="text" placeholder="Cari akun..." class="w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
            </div>
            <button @click="openCreate" class="bg-indofilter hover:bg-indofilter-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Baru
            </button>
        </div>

        <div class="bg-white rounded-lg border border-gray-200">
            <DataTable :columns="columns" :data="filteredItems" :loading="loading" empty-message="Belum ada akun pengguna">
                <template #cell-name="{ row }">
                    <div class="font-medium text-gray-900">{{ row.name }}</div>
                </template>
                <template #cell-email="{ row }">
                    <span class="text-gray-600">{{ row.email }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    {{ formatDate(value) }}
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <button @click="openEdit(row)" class="text-blue-600 hover:text-blue-800 transition-colors" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button @click="confirmDelete(row.id)" class="text-red-500 hover:text-red-700 transition-colors" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <FormModal :show="showModal" :title="editing ? 'Edit Akun' : 'Akun Baru'" @close="showModal = false">
            <div class="space-y-4">
                <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ error }}</div>
                
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama Pengguna *</label>
                    <input v-model="form.name" required placeholder="Contoh: Admin Indofilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                    <input v-model="form.email" type="email" required placeholder="Contoh: admin@indofilter.com" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Password <span v-if="!editing">*</span><span v-else class="text-gray-400 font-normal"> (Kosongkan jika tidak ingin mengubah)</span>
                    </label>
                    <input v-model="form.password" type="password" :required="!editing" placeholder="Minimal 6 karakter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                </div>
            </div>
            <template #footer>
                <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">Batal</button>
                <button @click="save" :disabled="formLoading" class="px-4 py-2 bg-indofilter hover:bg-indofilter-dark text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2">
                    <svg v-if="formLoading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ formLoading ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </template>
        </FormModal>

    </div>
</template>
