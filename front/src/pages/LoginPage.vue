<template>
  <q-layout class="login-layout">
    <q-page-container>
      <q-page class="full-height">
        <div class="login-bg-overlay"></div>

        <div class="login-wrap">
          <div class="login-shell">
            <section class="login-welcome">
              <div class="welcome-badge"><q-icon name="storefront" /> Area Fresca Oruro</div>
              <h1>Todo tu negocio<br><span>en un solo lugar.</span></h1>
              <p>Controla facturación, compras, ventas e inventario de forma simple y segura.</p>
              <div class="welcome-features">
                <div><q-icon name="point_of_sale" /> Ventas ágiles</div>
                <div><q-icon name="inventory_2" /> Stock actualizado</div>
                <div><q-icon name="receipt_long" /> Reportes claros</div>
              </div>
            </section>

            <q-card flat class="login-card">

            <q-card-section class="q-pt-lg text-center">
              <img :src="companyLogo" :alt="companyName" class="login-logo q-mb-sm" />
              <div class="text-subtitle2 login-brand-caption">
                <b>{{ companyName }}</b> · Sistema comercial
              </div>
            </q-card-section>

            <q-separator spaced />

            <!-- ── Login ── -->
            <template v-if="vista === 'login'">
              <q-card-section class="q-pt-none">
                <q-form @submit.prevent="login">
                  <div class="text-h6 text-bold q-mb-xs">Iniciar sesión</div>
                  <div class="text-body2 text-grey-7 q-mb-md">
                    Accede al panel usando tus credenciales.
                  </div>

                  <div class="q-mb-sm text-caption text-grey-7">Nombre de usuario</div>
                  <q-input v-model="username" outlined dense placeholder="Nombre de usuario"
                           :rules="[v => !!v || 'Ingrese su nombre de usuario']" class="q-mb-md">
                    <template #prepend><q-icon name="account_circle" size="18px" /></template>
                  </q-input>

                  <div class="q-mb-sm text-caption text-grey-7">Contraseña</div>
                  <q-input v-model="password" outlined dense
                           :type="showPassword ? 'text' : 'password'" placeholder="Contraseña"
                           :rules="[v => !!v || 'Ingrese su contraseña']" class="q-mb-md">
                    <template #prepend><q-icon name="lock" size="18px" /></template>
                    <template #append>
                      <q-icon :name="showPassword ? 'visibility' : 'visibility_off'"
                              size="18px" class="cursor-pointer" @click="showPassword = !showPassword" />
                    </template>
                  </q-input>

                  <q-btn color="primary" label="Iniciar sesión" class="full-width"
                         no-caps unelevated size="16px" :loading="loading" type="submit" />
                </q-form>
              </q-card-section>

              <q-card-section class="q-pt-none text-center">
                <q-separator spaced />
                <div class="text-caption text-grey-6">
                  © {{ year }} {{ companyName }}. Todos los derechos reservados.
                </div>
              </q-card-section>
            </template>

            <!-- ── Cambiar contraseña obligatorio ── -->
            <template v-else-if="vista === 'cambiar'">
              <q-card-section class="q-pt-none">
                <div class="row items-center q-mb-xs">
                  <q-icon name="lock_reset" color="warning" size="22px" class="q-mr-sm" />
                  <span class="text-h6 text-bold">Cambia tu contraseña</span>
                </div>
                <q-banner rounded class="bg-orange-1 text-orange-9 q-mb-md" dense>
                  <template #avatar><q-icon name="warning" color="warning" /></template>
                  Por seguridad debes establecer una nueva contraseña antes de continuar.
                </q-banner>

                <q-form @submit.prevent="cambiarPassword">
                  <div class="q-mb-sm text-caption text-grey-7">Nueva contraseña</div>
                  <q-input v-model="newPassword" outlined dense
                           :type="showNewPassword ? 'text' : 'password'" placeholder="Nueva contraseña"
                           :rules="[v => !!v || 'Campo requerido', v => v.length >= 6 || 'Mínimo 6 caracteres']"
                           class="q-mb-md">
                    <template #prepend><q-icon name="lock" size="18px" /></template>
                    <template #append>
                      <q-icon :name="showNewPassword ? 'visibility' : 'visibility_off'"
                              size="18px" class="cursor-pointer" @click="showNewPassword = !showNewPassword" />
                    </template>
                  </q-input>

                  <div class="q-mb-sm text-caption text-grey-7">Repetir nueva contraseña</div>
                  <q-input v-model="newPasswordConfirm" outlined dense
                           :type="showNewPasswordConfirm ? 'text' : 'password'" placeholder="Repetir nueva contraseña"
                           :rules="[v => !!v || 'Campo requerido', v => v === newPassword || 'Las contraseñas no coinciden']"
                           class="q-mb-md">
                    <template #prepend><q-icon name="lock_reset" size="18px" /></template>
                    <template #append>
                      <q-icon :name="showNewPasswordConfirm ? 'visibility' : 'visibility_off'"
                              size="18px" class="cursor-pointer" @click="showNewPasswordConfirm = !showNewPasswordConfirm" />
                    </template>
                  </q-input>

                  <q-btn color="primary" label="Guardar y entrar" class="full-width"
                         no-caps unelevated size="16px" icon="check_circle"
                         :loading="loadingChange" type="submit" />
                </q-form>
              </q-card-section>
            </template>

            </q-card>
          </div>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, computed, getCurrentInstance, onMounted } from 'vue'

const { proxy } = getCurrentInstance()

const vista                  = ref('login')
const username               = ref('')
const password               = ref('')
const showPassword           = ref(false)
const loading                = ref(false)

const newPassword            = ref('')
const newPasswordConfirm     = ref('')
const showNewPassword        = ref(false)
const showNewPasswordConfirm = ref(false)
const loadingChange          = ref(false)
let   tempToken              = ''

const year = computed(() => new Date().getFullYear())
const cachedCompany = JSON.parse(localStorage.getItem('empresaAreaFresca') || '{}')
const companyName = ref(cachedCompany.nombre_empresa || 'Area Fresca')
const companyLogo = ref(cachedCompany.logo_url || '/sofia-logo.png')

onMounted(() => {
  proxy.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${proxy.$imgBase}/images/${data.logo}` : null
    companyName.value = data.nombre_empresa || 'Area Fresca'
    companyLogo.value = data.logo_url || '/sofia-logo.png'
    localStorage.setItem('empresaAreaFresca', JSON.stringify(data))
  })
})

function login () {
  loading.value = true
  proxy.$axios.post('/login', { username: username.value, password: password.value })
    .then(res => {
      const { user, token, must_change_password } = res.data
      proxy.$axios.defaults.headers.common.Authorization = `Bearer ${token}`
      proxy.$store.user = user

      if (must_change_password) {
        tempToken = token
        newPassword.value = ''
        newPasswordConfirm.value = ''
        vista.value = 'cambiar'
      } else {
        const perms = (user.permissions || []).map(p => p.name)
        proxy.$store.isLogged    = true
        proxy.$store.permissions = perms
        localStorage.setItem('tokenAreaFresca', token)
        localStorage.setItem('permissionsAreaFresca', JSON.stringify(perms))
        localStorage.setItem('user', JSON.stringify(user))
        proxy.$alert.success('Bienvenido ' + user.name)
        proxy.$router.push('/')
      }
    })
    .catch(err => {
      proxy.$alert.error(err?.response?.data?.message || 'Error de autenticación')
    })
    .finally(() => { loading.value = false })
}

function cambiarPassword () {
  loadingChange.value = true
  proxy.$axios.put('cambiar-password', {
    password_actual:             '123456',
    password_nuevo:              newPassword.value,
    password_nuevo_confirmation: newPasswordConfirm.value,
  })
    .then(() => {
      const user = proxy.$store.user
      const perms = (user.permissions || []).map(p => p.name)
      proxy.$store.isLogged    = true
      proxy.$store.permissions = perms
      localStorage.setItem('tokenAreaFresca', tempToken)
      localStorage.setItem('permissionsAreaFresca', JSON.stringify(perms))
      localStorage.setItem('user', JSON.stringify(user))
      proxy.$alert.success('Contraseña actualizada. ¡Bienvenido!')
      proxy.$router.push('/')
    })
    .catch(err => {
      proxy.$alert.error(err?.response?.data?.message || 'Error al cambiar contraseña')
    })
    .finally(() => { loadingChange.value = false })
}
</script>

<style scoped>
.login-layout {
  background: #6f1010;
  min-height: 100vh;
}
.full-height { min-height: 100vh; position: relative; }
.login-bg-overlay {
  position: absolute; inset: 0;
  background:
    radial-gradient(circle at 12% 15%, rgba(255, 92, 92, .32), transparent 31%),
    radial-gradient(circle at 85% 80%, rgba(35, 0, 0, .55), transparent 35%),
    linear-gradient(135deg, #b71c1c 0%, #7f1111 48%, #3b0707 100%);
}
.login-bg-overlay::before,
.login-bg-overlay::after {
  content: '';
  position: absolute;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 50%;
}
.login-bg-overlay::before { width: 430px; height: 430px; top: -210px; right: -90px; }
.login-bg-overlay::after { width: 620px; height: 620px; bottom: -430px; left: -170px; }
.login-wrap {
  position: relative; z-index: 1; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 32px;
}
.login-shell {
  width: min(1040px, 100%);
  display: grid; grid-template-columns: 1.15fr .85fr;
  align-items: center; gap: 72px;
}
.login-welcome { color: #fff; padding: 30px 0; }
.welcome-badge {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 8px 13px; border: 1px solid rgba(255,255,255,.22);
  border-radius: 999px; background: rgba(255,255,255,.1);
  font-size: 13px; font-weight: 700; backdrop-filter: blur(8px);
}
.login-welcome h1 {
  margin: 24px 0 14px; font-size: clamp(40px, 5.2vw, 66px);
  line-height: .98; letter-spacing: -.045em; font-weight: 800;
}
.login-welcome h1 span { color: #ffcdd2; }
.login-welcome p { max-width: 520px; margin: 0; color: rgba(255,255,255,.76); font-size: 17px; line-height: 1.6; }
.welcome-features { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
.welcome-features div {
  display: flex; align-items: center; gap: 7px; padding: 9px 12px;
  border-radius: 10px; background: rgba(48,0,0,.25); color: rgba(255,255,255,.9); font-size: 12px;
}
.login-card {
  width: 100%; min-width: 0; overflow: hidden;
  border-radius: 24px;
  background: rgba(255,255,255,.97);
  box-shadow: 0 28px 70px rgba(35,0,0,.4);
  border: 1px solid rgba(255,255,255,.58);
}
.login-logo {
  width: 230px; max-width: 82%; height: 130px; object-fit: contain;
  filter: drop-shadow(0 10px 12px rgba(120, 15, 15, .16));
}
.login-brand-caption { color: #7a2424; }
.login-card :deep(.q-field--outlined .q-field__control) { border-radius: 11px; }
.login-card :deep(.q-btn) { min-height: 46px; border-radius: 11px; font-weight: 700; }

@media (max-width: 850px) {
  .login-wrap { padding: 24px 16px; }
  .login-shell { grid-template-columns: 1fr; gap: 20px; max-width: 500px; }
  .login-welcome { text-align: center; padding: 8px 10px; }
  .login-welcome h1 { margin: 16px 0 10px; font-size: 38px; }
  .login-welcome p { font-size: 14px; }
  .welcome-features { display: none; }
}
@media (max-width: 480px) {
  .login-wrap { padding: 14px 10px; align-items: flex-start; }
  .login-welcome h1 { font-size: 31px; }
  .login-welcome p { display: none; }
  .login-card { border-radius: 18px; }
  .login-logo { height: 105px; }
}
</style>
