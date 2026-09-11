import { defineStore } from 'pinia'
import { companies as companiesApi } from '../api/index.js'

export const useAppStore = defineStore('app', {
    state: () => ({
        activeCompanyId: localStorage.getItem('active_company_id') ? Number(localStorage.getItem('active_company_id')) : null,
        companies: [],
        sidebarCollapsed: false,
        confirmModal: {
            show: false,
            title: '',
            message: '',
            onConfirm: null,
            onCancel: null,
            confirmText: 'OK',
            cancelText: '',
            type: 'success'
        }
    }),
    actions: {
        setActiveCompany(id) {
            this.activeCompanyId = id
            if (id) {
                localStorage.setItem('active_company_id', id)
            } else {
                localStorage.removeItem('active_company_id')
            }
            window.location.reload()
        },
        async loadCompanies() {
            try {
                const response = await companiesApi.list()
                this.companies = response.data.data || response.data
                if (this.companies.length > 0) {
                    const exists = this.companies.some(c => c.id === this.activeCompanyId)
                    if (!this.activeCompanyId || !exists) {
                        this.activeCompanyId = this.companies[0].id
                        localStorage.setItem('active_company_id', this.companies[0].id)
                    }
                }
            } catch (e) {
                this.companies = []
            }
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed
        },
        showConfirm(title, message, onConfirm, onCancel = null) {
            this.confirmModal = {
                show: true,
                title,
                message,
                onConfirm,
                onCancel,
                confirmText: 'Ya',
                cancelText: 'Batal',
                type: 'confirm'
            }
        },
        showNotification(title, message, type = 'success') {
            this.confirmModal = {
                show: true,
                title,
                message,
                onConfirm: null,
                onCancel: null,
                confirmText: 'OK',
                cancelText: '',
                type
            }
            if (type === 'success') {
                setTimeout(() => {
                    if (this.confirmModal.show && this.confirmModal.type === 'success') {
                        this.confirmModal.show = false
                    }
                }, 700)
            }
        },
        closeConfirmModal(confirmed) {
            const { onConfirm, onCancel } = this.confirmModal
            this.confirmModal.show = false
            if (confirmed) {
                if (typeof onConfirm === 'function') {
                    onConfirm()
                }
            } else {
                if (typeof onCancel === 'function') {
                    onCancel()
                }
            }
        }
    },
})
