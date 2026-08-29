import { defineStore, acceptHMRUpdate } from 'pinia'

export const CARRITOS = 5
export const CARTS_STORAGE_KEY = 'carritosAreaFresca'

const nuevaVenta = () => ({items:[],descuento:0,observacion:'',tipo_pago:'EFECTIVO',monto_efectivo:0,monto_qr:0,tipo_documento:'CI',numero_documento:'0',complemento:'',cliente_nombre:'',cliente_email:'',cliente_telefono:'',cliente_direccion:''})
const nuevaCompra = () => ({items:[],proveedor:null,numero_factura:'',comentario:'',tipo_pago:'EFECTIVO',monto_efectivo:0,monto_qr:0})
const subtotal = venta => (venta?.items||[]).reduce((sum,item)=>sum+Number(item.precio_venta||0)*Number(item.cantidad||0),0)

export const useCartsStore = defineStore('carts', {
  state: () => ({
    ventas: Array.from({length:CARRITOS}, nuevaVenta),
    ventaActiva: 0,
    compra: nuevaCompra(),
  }),

  getters: {
    ventaActual: state => state.ventas[state.ventaActiva] || state.ventas[0],
    subtotalVenta: state => index => subtotal(state.ventas[index]),
    totalVenta: state => index => {
      const venta = state.ventas[index]
      if (!venta) return 0
      return subtotal(venta) - Math.min(Math.max(Number(venta.descuento)||0,0), subtotal(venta))
    },
    itemsVenta: state => index => (state.ventas[index]?.items||[]).length,
    totalCompra: state => state.compra.items.reduce((sum,item)=>sum+Number(item.total_editable||0),0),
  },

  actions: {
    // Rellena/recorta lo que venga de localStorage para que siempre existan los 5 carritos.
    normalizar () {
      const ventas = Array.isArray(this.ventas) ? this.ventas.slice(0, CARRITOS) : []
      while (ventas.length < CARRITOS) ventas.push(nuevaVenta())
      this.ventas = ventas.map(venta => ({...nuevaVenta(), ...(venta||{}), items: Array.isArray(venta?.items) ? venta.items : []}))
      this.ventaActiva = Math.min(Math.max(Number(this.ventaActiva)||0, 0), CARRITOS - 1)
      this.compra = {...nuevaCompra(), ...(this.compra||{}), items: Array.isArray(this.compra?.items) ? this.compra.items : []}
    },
    limpiarVenta (index = this.ventaActiva) { this.ventas[index] = nuevaVenta() },
    limpiarVentas () { this.ventas = Array.from({length:CARRITOS}, nuevaVenta); this.ventaActiva = 0 },
    limpiarCompra () { this.compra = nuevaCompra() },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useCartsStore, import.meta.hot))
}
