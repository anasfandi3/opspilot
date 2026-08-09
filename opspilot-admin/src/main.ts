import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'

import App from './App.vue'
import router from './router'
import './assets/main.css'
import { queryClient } from './lib/queryClient'
import { installGuards } from './router/guards'
import { installSessionExpiryHandler } from './features/auth/sessionExpiry'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)
app.use(VueQueryPlugin, { queryClient })
installSessionExpiryHandler(router, pinia)
installGuards(router, pinia)
app.use(router)

app.mount('#app')
