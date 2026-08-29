import { defineStore } from '#q-app/wrappers'
import { createPinia } from 'pinia'
import { CARTS_STORAGE_KEY } from './carts'

/*
 * If not building with SSR mode, you can
 * directly export the Store instantiation;
 *
 * The function below can be async too; either use
 * async/await or return a Promise which resolves
 * with the Store instance.
 */

// Persiste los carritos de venta y la compra en curso en localStorage (clave `carritosAreaFresca`),
// para que no se pierdan al recargar o al navegar entre páginas.
function persistCarts ({ store }) {
  if (store.$id !== 'carts') return
  try {
    const saved = localStorage.getItem(CARTS_STORAGE_KEY)
    if (saved) store.$patch(JSON.parse(saved))
  } catch (e) { localStorage.removeItem(CARTS_STORAGE_KEY) }
  store.normalizar()
  store.$subscribe((mutation, state) => {
    try { localStorage.setItem(CARTS_STORAGE_KEY, JSON.stringify(state)) } catch (e) { /* cuota llena */ }
  })
}

export default defineStore((/* { ssrContext } */) => {
  const pinia = createPinia()

  // You can add Pinia plugins here
  pinia.use(persistCarts)

  return pinia
})
