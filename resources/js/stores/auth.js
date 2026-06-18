import { defineStore } from 'pinia'
import { auth as authApi } from '../api/index.js'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token'),
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
        currentUser: (state) => state.user,
    },
    actions: {
        setToken(token) {
            this.token = token
            if (token) {
                localStorage.setItem('token', token)
            } else {
                localStorage.removeItem('token')
            }
        },
        async login(credentials) {
            const response = await authApi.login(credentials.email, credentials.password)
            this.setToken(response.data.token)
            await this.fetchUser()
            return response
        },
        async logout() {
            try {
                await authApi.logout()
            } catch (e) {
                // ignore
            }
            this.setToken(null)
            this.user = null
        },
        async fetchUser() {
            try {
                const response = await authApi.getUser()
                this.user = response.data
            } catch (e) {
                this.setToken(null)
                this.user = null
            }
        },
    },
})
