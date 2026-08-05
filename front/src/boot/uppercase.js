import { defineBoot } from '#q-app/wrappers'

// Directiva v-uppercase: convierte el valor a mayusculas al escribir.
// Uso:
//   <q-input v-uppercase v-model="form.nombre" />
//
// Tambien aplica la clase visual .uppercase-input.
export default defineBoot(({ app }) => {
  app.directive('uppercase', {
    mounted (el) {
      const target = el.querySelector('input, textarea') || el
      if (!target || !target.addEventListener) return

      target.classList.add('uppercase-input')

      const handler = (e) => {
        const start = e.target.selectionStart
        const end = e.target.selectionEnd
        const upper = (e.target.value || '').toUpperCase()
        if (e.target.value !== upper) {
          e.target.value = upper
          e.target.dispatchEvent(new Event('input', { bubbles: true }))
          // Restaurar la posicion del cursor
          try { e.target.setSelectionRange(start, end) } catch (_) { /* noop */ }
        }
      }

      target.addEventListener('input', handler)
      el.__uppercaseHandler__ = handler
      el.__uppercaseTarget__ = target
    },
    unmounted (el) {
      if (el.__uppercaseTarget__ && el.__uppercaseHandler__) {
        el.__uppercaseTarget__.removeEventListener('input', el.__uppercaseHandler__)
      }
    }
  })
})
