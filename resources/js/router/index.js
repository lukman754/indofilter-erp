import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('../layouts/MainLayout.vue'),
        meta: { requiresAuth: true },
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
                path: 'kas',
                name: 'kas',
                component: () => import('../pages/kas/Index.vue'),
            },
            {
                path: 'kas/summary',
                name: 'kas.summary',
                component: () => import('../pages/kas/Summary.vue'),
            },
        ],
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token')
    if (to.meta.requiresAuth && !token) {
        next('/login')
    } else if (to.meta.guest && token) {
        next('/dashboard')
    } else {
        next()
    }
})

export default router
