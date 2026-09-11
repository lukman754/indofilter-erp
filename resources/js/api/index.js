import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    withCredentials: false,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
})

api.interceptors.request.use((config) => {
    // Auto-inject selected company ID on GET queries
    const activeCompanyId = localStorage.getItem('active_company_id')
    if (activeCompanyId) {
        if (config.method === 'get') {
            config.params = config.params || {}
            if (config.params.company_id === undefined) {
                config.params.company_id = Number(activeCompanyId)
            }
        }
    }
    
    return config
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        return Promise.reject(error)
    }
)

export const companies = {
    list: () => api.get('/companies'),
    get: (id) => api.get(`/companies/${id}`),
    create: (data) => api.post('/companies', data),
    update: (id, data) => api.put(`/companies/${id}`, data),
    delete: (id) => api.delete(`/companies/${id}`),
}

export const partners = {
    list: (params) => api.get('/partners', { params }),
    get: (id) => api.get(`/partners/${id}`),
    create: (data) => api.post('/partners', data),
    update: (id, data) => api.put(`/partners/${id}`, data),
    delete: (id) => api.delete(`/partners/${id}`),
}

export const products = {
    list: (params) => api.get('/products', { params }),
    get: (id) => api.get(`/products/${id}`),
    create: (data) => api.post('/products', data),
    update: (id, data) => api.put(`/products/${id}`, data),
    delete: (id) => api.delete(`/products/${id}`),
}

export const documents = {
    list: (params) => api.get('/documents', { params }),
    get: (id) => api.get(`/documents/${id}`),
    create: (data) => api.post('/documents', data),
    update: (id, data) => api.put(`/documents/${id}`, data),
    delete: (id) => api.delete(`/documents/${id}`),
    confirm: (id, params) => api.post(`/documents/${id}/confirm`, null, { params }),
    cancel: (id) => api.post(`/documents/${id}/cancel`),
    export: (id, params) => api.get(`/documents/${id}/export`, { params }),
    generateNumber: (params) => api.get('/documents/generate-number', { params }),
    getFormDependencies: (params) => api.get('/documents/form-dependencies', { params }),
    checkLocalFile: (params) => api.get('/documents/check-local-file', { params }),
    parsePdf: (formData) => api.post('/documents/parse-pdf', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }),
    uploadPo: (id, formData) => api.post(`/documents/${id}/upload-po`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }),
    downloadPo: (id) => api.get(`/documents/${id}/download-po`, { responseType: 'blob' }),
    uploadSupporting: (id, formData) => api.post(`/documents/${id}/upload-supporting`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }),
    deleteSupporting: (id, filename) => api.post(`/documents/${id}/delete-supporting`, { filename }),
    downloadSupporting: (id, filename) => api.get(`/documents/${id}/download-supporting/${filename}`, { responseType: 'blob' }),
}

export const dashboard = {
    stats: (params) => api.get('/dashboard/stats', { params }),
}

export const reports = {
    list: (params) => api.get('/reports', { params }),
    stats: (params) => api.get('/reports/stats', { params }),
}

export const settings = {
    getGoogleDrive: () => api.get('/settings'),
    updateGoogleDrive: (data) => api.post('/settings', data),
    changeDriveLetter: (driveLetter) => api.post('/settings/change-drive-letter', { drive_letter: driveLetter }),
}

export const users = {
    list: () => api.get('/users'),
    get: (id) => api.get(`/users/${id}`),
    create: (data) => api.post('/users', data),
    update: (id, data) => api.put(`/users/${id}`, data),
    delete: (id) => api.delete(`/users/${id}`),
}

export const search = {
    globalSearch: (q) => api.get('/global-search', { params: { q } }),
}

export default api

