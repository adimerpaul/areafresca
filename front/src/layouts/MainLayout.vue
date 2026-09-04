<template>
  <q-layout view="lHh Lpr lFf">
    <!-- HEADER -->
    <q-header class="app-header">
      <q-toolbar>
        <q-btn
          flat
          color="primary"
          :icon="leftDrawerOpen ? 'keyboard_double_arrow_left' : 'keyboard_double_arrow_right'"
          aria-label="Menu"
          @click="toggleLeftDrawer"
          unelevated
          dense
        />
        <div class="row items-center q-gutter-sm">
          <div class="text-subtitle1 text-weight-medium" style="line-height: 0.9">
            {{ companyName }}
          </div>
        </div>

        <q-space />

        <q-btn-dropdown flat unelevated no-caps dropdown-icon="expand_more">
          <template v-slot:label>
            <div class="header-user row items-center no-wrap">
              <q-avatar rounded size="30px" style="border:2px solid #e4eae7">
                <img :src="$store.user.avatar ? $imgBase + '/images/' + $store.user.avatar : $imgBase + '/images/default.png'"
                     style="object-fit:cover;width:100%;height:100%"
                     @error="$event.target.src = $imgBase + '/images/default.png'" />
              </q-avatar>
              <div class="text-left" style="line-height: 1">
                <div class="ellipsis" style="max-width: 130px;">
                  {{ $store.user.username }}
                </div>
              </div>
            </div>
          </template>

          <q-separator />

          <q-item clickable v-ripple @click="logout" v-close-popup>
            <q-item-section avatar>
              <q-icon name="logout" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </q-btn-dropdown>
      </q-toolbar>
    </q-header>

    <!-- DRAWER -->
    <q-drawer
      v-model="leftDrawerOpen"
      bordered
      show-if-above
      :width="236"
      :breakpoint="700"
      class="app-drawer text-white"
    >
      <q-scroll-area class="fit">
        <div class="drawer-shell">
          <div class="drawer-brand">
            <div class="drawer-brand__logo">
              <img :src="companyLogo" :alt="companyName" />
            </div>
            <div class="drawer-brand__text">
              <div class="drawer-brand__title">{{ companyName }}</div>
              <div class="drawer-brand__caption">Facturación, compras y ventas</div>
            </div>
          </div>

          <div class="drawer-eyebrow">Módulos</div>

          <q-list dense class="drawer-menu">
            <q-item
              v-for="link in visibleLinks"
              :key="link.title"
              dense
              clickable
              :to="link.link"
              exact
              class="drawer-menu-link"
              active-class="drawer-menu-link--active"
            >
              <q-item-section avatar class="drawer-menu-link__avatar">
                <q-icon :name="link.icon" size="15px" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="drawer-menu-link__label" lines="1">{{ link.title }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>

          <div class="drawer-footer">
            <div>Area Fresca v{{ $version }}</div>
            <div>© {{ new Date().getFullYear() }} Area Fresca</div>
          </div>

          <q-item clickable class="drawer-logout" @click="logout">
            <q-item-section avatar class="drawer-menu-link__avatar">
              <q-icon name="logout" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </div>
      </q-scroll-area>
    </q-drawer>

    <!-- PAGE -->
    <q-page-container class="page-bg">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'

const { proxy } = getCurrentInstance()

const leftDrawerOpen = ref(false)
const cachedCompany = JSON.parse(localStorage.getItem('empresaAreaFresca') || '{}')
const companyName = ref(cachedCompany.nombre_empresa || 'Area Fresca')
const companyLogo = ref(cachedCompany.logo_url || '/sofia-logo.png')

const links = [
  { title: 'Inicio',    icon: 'dashboard',   link: '/',         can: null },
  { title: 'Usuarios',  icon: 'people',      link: '/usuarios', can: 'Ver Usuarios' },
  { title: 'Productos', icon: 'inventory_2', link: '/productos', can: 'Ver Productos' },
  { title: 'Nueva venta', icon: 'point_of_sale', link: '/ventas/nueva', can: 'Crear Ventas' },
  { title: 'Ventas', icon: 'receipt_long', link: '/ventas', can: 'Ver Ventas' },
  { title: 'Facturación', icon: 'request_quote', link: '/facturacion', can: 'Ver Facturación' },
  { title: 'Nueva compra', icon: 'add_business', link: '/compras/nueva', can: 'Crear Compras' },
  { title: 'Compras', icon: 'shopping_bag', link: '/compras', can: 'Ver Compras' },
  { title: 'Proveedores', icon: 'groups', link: '/proveedores', can: 'Ver Compras' },
  { title: 'Almacenes', icon: 'warehouse', link: '/almacenes', can: 'Ver Almacenes' },
  { title: 'Nueva baja', icon: 'remove_circle_outline', link: '/bajas/nueva', can: 'Crear Bajas' },
  { title: 'Bajas', icon: 'delete_forever', link: '/bajas', can: 'Ver Bajas' },
  { title: 'Por vencer', icon: 'schedule', link: '/productos/por-vencer', can: ['Ver Compras', 'Ver Almacenes'] },
  { title: 'Vencidos', icon: 'event_busy', link: '/productos/vencidos', can: ['Ver Compras', 'Ver Almacenes'] },
  { title: 'Configuración', icon: 'settings', link: '/configuracion', can: 'Gestionar Configuración' },
  { title: 'Firma digital', icon: 'verified_user', link: '/firma-digital', can: 'Gestionar Configuración' },
]

const visibleLinks = computed(() =>
  links.filter(link => link.can === null ||
    (Array.isArray(link.can) ? link.can.some(p => proxy.$store.hasPermission(p)) : proxy.$store.hasPermission(link.can)))
)

function toggleLeftDrawer () {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

onMounted(() => {
  proxy.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${proxy.$imgBase}/images/${data.logo}` : null
    companyName.value = data.nombre_empresa || 'Area Fresca'
    companyLogo.value = data.logo_url || '/sofia-logo.png'
    localStorage.setItem('empresaAreaFresca', JSON.stringify(data))
  })
})

function logout () {
  proxy.$alert.dialog('¿Desea salir del sistema?').onOk(() => {
    proxy.$axios.post('/logout').finally(() => {
      proxy.$store.logout()
      localStorage.removeItem('tokenAreaFresca')
      localStorage.removeItem('permissionsAreaFresca')
      localStorage.removeItem('user')
      delete proxy.$axios.defaults.headers.common['Authorization']
      proxy.$router.push('/login')
    })
  })
}
</script>

<style>
.app-drawer {
  background:
    radial-gradient(circle at 20% 3%, rgba(255, 112, 112, .38), transparent 24%),
    linear-gradient(180deg, #a91616 0%, #6f0d0d 50%, #310505 100%);
  color: #ffffff;
}

.app-drawer,
.app-drawer .q-drawer__content,
.app-drawer .q-scrollarea,
.app-drawer .q-scrollarea__container,
.app-drawer .q-scrollarea__content {
  background:
    radial-gradient(circle at 20% 3%, rgba(255, 112, 112, .38), transparent 24%),
    linear-gradient(180deg, #a91616 0%, #6f0d0d 50%, #310505 100%) !important;
}

.drawer-shell {
  min-height: 100%;
  padding: 9px 7px 10px;
}

.drawer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  margin-bottom: 9px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  background: rgba(72, 0, 0, .25);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.12), 0 8px 20px rgba(42,0,0,.18);
}

.drawer-brand__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 9px;
  background: #ffffff;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.drawer-brand__logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.drawer-brand__title {
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: -0.01em;
  line-height: 1.1;
}

.drawer-brand__text {
  min-width: 0;
  line-height: 1.05;
}

.drawer-brand__caption {
  margin-top: 2px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 10px;
  line-height: 1.15;
}

.drawer-eyebrow {
  padding: 4px 10px 7px;
  color: rgba(255, 255, 255, 0.66);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.drawer-menu {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.drawer-menu-link {
  min-height: 34px;
  margin: 0 2px;
  padding: 0 9px;
  border: 1px solid rgba(255, 255, 255, .1);
  border-radius: 10px;
  color: rgba(255, 255, 255, .9);
  background: linear-gradient(100deg, rgba(255,255,255,.095), rgba(255,255,255,.035));
  transition: transform .16s ease, background .16s ease, box-shadow .16s ease;
}

.drawer-menu-link:hover {
  transform: translateX(3px);
  background: linear-gradient(100deg, rgba(255, 112, 112, .3), rgba(255,255,255,.08));
  box-shadow: 0 6px 14px rgba(45, 0, 0, .18);
}

.drawer-menu-link__avatar {
  min-width: 27px;
}

.drawer-menu-link__avatar .q-icon {
  width: 23px;
  height: 23px;
  border-radius: 7px;
  background: rgba(255,255,255,.12);
}

.drawer-menu-link__label {
  font-size: 11.5px;
  font-weight: 650;
  line-height: 1.1;
}

.drawer-menu-link--active {
  border-color: rgba(255, 205, 210, .42);
  background: linear-gradient(100deg, #ef4444 0%, #c51d2b 56%, #8e1018 100%);
  color: #ffffff !important;
  box-shadow: inset 3px 0 0 #ffcdd2, 0 8px 18px rgba(60, 0, 0, .28);
}

.drawer-menu-link--active .drawer-menu-link__avatar .q-icon {
  background: rgba(255,255,255,.22);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.2);
}

.drawer-footer {
  padding: 6px 8px 4px;
  margin-top: 6px;
  color: rgba(255, 255, 255, 0.58);
  font-size: 10px;
  line-height: 1.35;
}

.drawer-logout {
  min-height: 32px;
  margin: 2px 5px 0;
  border-radius: 9px;
  color: #ffebee;
  background: rgba(255, 205, 210, .1);
  border: 1px solid rgba(255, 205, 210, .2);
}

.app-header {
  background: #ffffff;
  border-bottom: 1px solid #e4eae7;
  color: #16241f;
}

.app-header .q-toolbar {
  min-height: 54px;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff5f5;
  border-radius: 99px;
  padding: 4px 12px 4px 5px;
}

.page-bg {
  background: #fff8f8;
}
</style>
