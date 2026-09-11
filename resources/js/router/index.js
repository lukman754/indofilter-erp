import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/',
        component: () => import('../layouts/MainLayout.vue'),
        children: [
            {
                path: '',
                redirect: '/dashboard',
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('../pages/Dashboard.vue'),
            },
            {
                path: 'companies',
                name: 'companies',
                component: () => import('../pages/companies/Index.vue'),
            },
            {
                path: 'partners',
                name: 'partners',
                component: () => import('../pages/partners/Index.vue'),
            },
            {
                path: 'products',
                name: 'products',
                component: () => import('../pages/products/Index.vue'),
            },
            {
                path: 'products/:id/history',
                name: 'products.history',
                component: () => import('../pages/products/History.vue'),
            },
            {
                path: 'partners/:id/history',
                name: 'partners.history',
                component: () => import('../pages/partners/History.vue'),
            },
            {
                path: 'documents',
                name: 'documents',
                component: () => import('../pages/documents/Index.vue'),
            },
            {
                path: 'documents/create',
                name: 'documents.create',
                component: () => import('../pages/documents/Form.vue'),
            },
            {
                path: 'documents/:id/edit',
                name: 'documents.edit',
                component: () => import('../pages/documents/Form.vue'),
            },
            {
                path: 'documents/:id',
                name: 'documents.show',
                component: () => import('../pages/documents/Show.vue'),
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../pages/settings/GoogleDrive.vue'),
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('../pages/users/Index.vue'),
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('../pages/reports/Report.vue'),
            },
        ],
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
