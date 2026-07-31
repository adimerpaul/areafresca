import { defineStore, acceptHMRUpdate } from 'pinia'

export const useCounterStore = defineStore('counter', {
  state: () => ({
    isLogged: false,
    user: {},
    permissions: [],
  }),

  getters: {
    hasPermission: (state) => (perm) => {
      if (Array.isArray(perm)) return perm.some(p => state.permissions.includes(p))
      return state.permissions.includes(perm)
    },
  },

  actions: {
    logout () {
      this.isLogged = false
      this.user = {}
      this.permissions = []
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useCounterStore, import.meta.hot))
}
