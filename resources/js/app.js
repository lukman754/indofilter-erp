import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router/index.js'
import { useAppStore } from './stores/app.js'
import App from './App.vue'
import '../css/app.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.config.errorHandler = (err, instance, info) => {
    console.error('[Vue Error]', err, info)
}

const appStore = useAppStore()

async function init() {
    try {
        await appStore.loadCompanies()
    } catch (e) {
        console.error('[Init Error]', e)
    }
    try {
        app.mount('#app')
    } catch (e) {
        console.error('[Mount Error]', e)
        document.getElementById('app').innerHTML =
            '<div style="padding:2rem;text-align:center;color:red">Gagal memuat aplikasi. Lihat console untuk detail.</div>'
    }
}

init()
