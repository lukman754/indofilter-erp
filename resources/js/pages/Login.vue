<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useAppStore } from '../stores/app.js'

const router = useRouter()
const authStore = useAuthStore()
const appStore = useAppStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function handleLogin() {
    loading.value = true
    error.value = ''
    try {
        await authStore.login({ email: email.value, password: password.value })
        await appStore.loadCompanies()
        router.push('/dashboard')
    } catch (e) {
        error.value = e.response?.data?.message || 'Email atau password salah'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-900 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-xl bg-indofilter flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Indofilter ERP</h1>
                <p class="text-sm text-gray-500 mt-1">Masuk ke akun Anda</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
                    {{ error }}
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input v-model="email" type="email" required placeholder="email@perusahaan.com" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input v-model="password" type="password" required placeholder="Masukkan password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow" />
                </div>

                <button type="submit" :disabled="loading" class="w-full py-2.5 bg-indofilter hover:bg-indofilter-dark text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                    <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ loading ? 'Memuat...' : 'Masuk' }}
                </button>
            </form>
        </div>
    </div>
</template>
