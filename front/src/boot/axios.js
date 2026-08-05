import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'
import { Alert } from '../addons/Alert'
import { useCounterStore } from '../stores/example-store'

// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
const api = axios.create({ baseURL: 'https://api.example.com' })

export default defineBoot(({ app, router }) => {
  const store = useCounterStore()

  app.config.globalProperties.$axios = axios.create({ baseURL: import.meta.env.VITE_API_BACK })
  app.config.globalProperties.$alert = Alert
  app.config.globalProperties.$store = store
  app.config.globalProperties.$url = import.meta.env.VITE_API_BACK
  app.config.globalProperties.$imgBase = (import.meta.env.VITE_API_BACK || '').replace(/\/api\/?$/, '')
  app.config.globalProperties.$version = import.meta.env.VITE_VERSION
  app.config.globalProperties.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${app.config.globalProperties.$imgBase}/images/${data.logo}` : null
    localStorage.setItem('empresaAreaFresca', JSON.stringify(data))
    app.config.globalProperties.$empresa = data
  })

  const token = localStorage.getItem('tokenAreaFresca')
  if (token) {
    app.config.globalProperties.$axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

    // Cargar permisos cacheados para que los "can*" estén listos antes de que responda /me
    try {
      const cachedPerms = JSON.parse(localStorage.getItem('permissionsAreaFresca') || '[]')
      if (cachedPerms.length) {
        store.permissions = cachedPerms
        store.isLogged = true
      }
    } catch (e) { /* noop */ }

    app.config.globalProperties.$axios.get('me').then(response => {
      store.isLogged = true
      store.user = response.data
      const perms = (response.data.permissions || []).map(p => p.name)
      store.permissions = perms
      localStorage.setItem('user', JSON.stringify(response.data))
      localStorage.setItem('permissionsAreaFresca', JSON.stringify(perms))
    }).catch(() => {
      localStorage.removeItem('tokenAreaFresca')
      localStorage.removeItem('permissionsAreaFresca')
      localStorage.removeItem('user')
      delete app.config.globalProperties.$axios.defaults.headers.common['Authorization']
      store.isLogged = false
      store.permissions = []
      store.user = {}
      router.push('/login')
    })
  }

  app.config.globalProperties.$api = api
  // ^ ^ ^ this will allow you to use this.$api (for Vue Options API form)
  //       so you can easily perform requests against your app's API
})

export { api }
