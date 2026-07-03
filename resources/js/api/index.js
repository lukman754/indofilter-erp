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
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    
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
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('token')
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

export const auth = {
    login: (email, password) => api.post('/login', { email, password }),
    logout: () => api.post('/logout'),
    getUser: () => api.get('/user'),
}

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
}

export const dashboard = {
    stats: () => api.get('/dashboard/stats'),
}

export const settings = {
    getGoogleDrive: () => api.get('/settings'),
    updateGoogleDrive: (data) => api.post('/settings', data),
}

export const search = {
    globalSearch: (q) => api.get('/global-search', { params: { q } }),
}

export default api

