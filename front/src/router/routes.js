const routes = [
  {
    path: '/login',
    component: () => import('pages/LoginPage.vue')
  },
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('pages/IndexPage.vue') },
      { path: 'usuarios', component: () => import('pages/usuarios/IndexPage.vue') },
      { path: 'productos', component: () => import('pages/productos/IndexPage.vue') },
      { path: 'ventas', component: () => import('pages/ventas/IndexPage.vue') },
      { path: 'ventas/nueva', component: () => import('pages/ventas/NuevaPage.vue') },
      { path: 'compras', component: () => import('pages/compras/IndexPage.vue') },
      { path: 'compras/nueva', component: () => import('pages/compras/NuevaPage.vue') },
      { path: 'proveedores', component: () => import('pages/proveedores/IndexPage.vue') },
      { path: 'configuracion', component: () => import('pages/configuracion/IndexPage.vue') },
      { path: 'productos/por-vencer', component: () => import('pages/compras/VencimientosPage.vue'), props: { estado: 'por_vencer' } },
      { path: 'productos/vencidos', component: () => import('pages/compras/VencimientosPage.vue'), props: { estado: 'vencido' } }
    ]
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue')
  }
]

export default routes
