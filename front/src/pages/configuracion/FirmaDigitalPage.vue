<template>
  <q-page class="q-pa-md signature-page">
    <div class="page-heading q-mb-md">
      <div class="heading-icon"><q-icon name="verified_user" size="28px" /></div>
      <div><div class="text-h6 text-weight-bold">Firma digital y tokens SIAT</div><div class="text-body2 text-grey-7">Credenciales para la futura facturación electrónica.</div></div>
      <q-space />
      <q-btn outline color="primary" icon="vpn_key" label="Nuevo token" no-caps @click="openTokenDialog" />
      <q-btn color="primary" icon="add" label="Nuevo certificado" no-caps unelevated @click="openCertificateDialog" />
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12"><div class="row items-center"><div><div class="text-subtitle1 text-weight-bold">Credenciales de facturación</div><div class="text-caption text-grey-7">El CUIS es de larga vigencia; el CUFD se renueva diariamente.</div></div><q-space/><q-btn flat round color="primary" icon="refresh" :loading="credentialsLoading" @click="loadCredentials"/></div></div>
      <div class="col-12 col-md-6">
        <q-card flat bordered class="full-height"><q-card-section class="row items-center"><q-avatar :color="siatCredentials.cuis?'positive':'grey-5'" text-color="white" icon="key"/><div class="q-ml-md"><div class="text-subtitle1 text-weight-bold">CUIS anual</div><div class="text-caption text-grey-7">Se genera una vez y se reutiliza mientras esté vigente.</div></div><q-space/><q-chip dense :color="siatCredentials.cuis?'positive':'negative'" text-color="white">{{siatCredentials.cuis?'Vigente':'Falta generar'}}</q-chip></q-card-section><q-separator/><q-card-section v-if="siatCredentials.cuis" class="text-body2">Válido hasta: <b>{{dateTime(siatCredentials.cuis.vence_en)}}</b></q-card-section><q-card-actions align="right"><q-btn outline color="primary" icon="key" :label="siatCredentials.cuis?'CUIS vigente':'Crear CUIS'" no-caps :disable="!!siatCredentials.cuis" :loading="creatingCuis" @click="createCuis"/></q-card-actions></q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat bordered class="full-height"><q-card-section class="row items-center"><q-avatar :color="siatCredentials.cufd?'positive':'grey-5'" text-color="white" icon="today"/><div class="q-ml-md"><div class="text-subtitle1 text-weight-bold">CUFD diario</div><div class="text-caption text-grey-7">Se genera cada día usando un CUIS vigente.</div></div><q-space/><q-chip dense :color="siatCredentials.cufd?'positive':'negative'" text-color="white">{{siatCredentials.cufd?'Vigente hoy':'Falta generar'}}</q-chip></q-card-section><q-separator/><q-card-section v-if="siatCredentials.cufd" class="text-body2">Válido hasta: <b>{{dateTime(siatCredentials.cufd.vence_en)}}</b></q-card-section><q-card-actions align="right"><q-btn color="primary" icon="today" :label="siatCredentials.cufd?'CUFD de hoy vigente':'Crear CUFD de hoy'" no-caps unelevated :disable="!siatCredentials.cuis||!!siatCredentials.cufd" :loading="creatingCufd" @click="createCufd"/></q-card-actions></q-card>
      </div>
      <div class="col-12">
        <q-card flat bordered class="signature-card">
          <q-card-section class="row items-center"><div><div class="text-subtitle1 text-weight-bold">Certificados digitales</div><div class="text-caption text-grey-7">Cada importación crea un registro nuevo.</div></div><q-space/><q-btn flat round color="primary" icon="refresh" :loading="loading" @click="load" /></q-card-section>
          <q-separator/>
          <q-card-section v-if="!loading && certificates.length===0" class="empty-state"><q-icon name="workspace_premium" size="54px" color="grey-4"/><div class="text-weight-medium q-mt-sm">Todavía no hay certificados</div></q-card-section>
          <q-list v-else separator>
            <q-item v-for="certificate in certificates" :key="certificate.id" class="certificate-item">
              <q-item-section avatar top><q-avatar :color="status(certificate).color" text-color="white" icon="workspace_premium"/></q-item-section>
              <q-item-section><q-item-label class="text-weight-bold">{{ certificate.nombre_archivo }}</q-item-label><q-item-label caption>{{ subjectName(certificate) }}</q-item-label><div class="certificate-data q-mt-sm"><span><b>Creado:</b> {{ dateTime(certificate.created_at) }}</span><span><b>Válido desde:</b> {{ dateTime(certificate.valido_desde) }}</span><span><b>Vence:</b> {{ dateTime(certificate.valido_hasta) }}</span><span class="fingerprint"><b>Huella:</b> {{ certificate.huella_sha256 }}</span></div></q-item-section>
              <q-item-section side top><q-badge :color="status(certificate).color" :label="status(certificate).label" class="q-mb-sm"/><q-btn v-if="!certificate.activo && status(certificate).label!=='Vencido'" dense flat no-caps color="primary" label="Activar" @click="activate(certificate)"/><q-btn dense flat round color="negative" icon="delete" @click="removeCertificate(certificate)"/></q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>

      <div class="col-12">
        <q-card flat bordered class="signature-card">
          <q-card-section class="row items-center"><div><div class="text-subtitle1 text-weight-bold">Tokens SIAT</div><div class="text-caption text-grey-7">El contenido se guarda cifrado y solo se muestran sus metadatos.</div></div><q-space/><q-btn flat round color="primary" icon="refresh" :loading="tokensLoading" @click="loadTokens"/></q-card-section>
          <q-separator/>
          <q-card-section v-if="!tokensLoading && tokens.length===0" class="empty-state"><q-icon name="vpn_key" size="50px" color="grey-4"/><div class="text-weight-medium q-mt-sm">Todavía no hay tokens</div></q-card-section>
          <q-list v-else separator>
            <q-item v-for="token in tokens" :key="token.id" class="certificate-item">
              <q-item-section avatar><q-avatar :color="tokenStatus(token).color" text-color="white" icon="vpn_key"/></q-item-section>
              <q-item-section><q-item-label class="text-weight-bold">Token SIAT #{{ token.id }}</q-item-label><q-item-label caption>Creado: {{ dateTime(token.created_at) }}</q-item-label><div class="q-mt-xs text-caption"><b>Fecha de vencimiento:</b> {{ dateTime(token.vence_en) }}</div></q-item-section>
              <q-item-section side><q-badge :color="tokenStatus(token).color" :label="tokenStatus(token).label"/><q-btn dense flat round color="negative" icon="delete" @click="removeToken(token)"/></q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>

    <q-dialog v-model="certificateDialog" persistent>
      <q-card class="dialog-card"><q-form @submit.prevent="upload"><q-card-section class="row items-center"><div class="text-h6 text-weight-bold">Nuevo certificado</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/><q-card-section><q-file v-model="file" outlined accept=".p12,.pfx" label="Archivo P12 o PFX" :rules="[required]"><template #prepend><q-icon name="upload_file" color="primary"/></template></q-file><q-input v-model="password" outlined label="Contraseña del certificado" :type="showPassword?'text':'password'" :rules="[required]"><template #prepend><q-icon name="password" color="primary"/></template><template #append><q-icon :name="showPassword?'visibility_off':'visibility'" class="cursor-pointer" @click="showPassword=!showPassword"/></template></q-input><q-banner rounded class="security-note">La contraseña no se guarda. Los archivos se generan en almacenamiento privado.</q-banner></q-card-section><q-card-actions align="right"><q-btn flat label="Cancelar" no-caps v-close-popup/><q-btn type="submit" color="primary" label="Importar y generar claves" no-caps unelevated :loading="uploading"/></q-card-actions></q-form></q-card>
    </q-dialog>

    <q-dialog v-model="tokenDialog" persistent>
      <q-card class="dialog-card"><q-form @submit.prevent="saveToken"><q-card-section class="row items-center"><div class="text-h6 text-weight-bold">Nuevo token SIAT</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/><q-card-section><q-input v-model="tokenForm.token" outlined type="textarea" autogrow label="Token JWT" :rules="[required]" input-style="min-height:130px;font-family:monospace;font-size:11px"/><q-banner rounded class="security-note">La fecha de vencimiento se obtiene automáticamente del campo <b>exp</b>. El token completo queda cifrado y no vuelve al navegador.</q-banner></q-card-section><q-card-actions align="right"><q-btn flat label="Cancelar" no-caps v-close-popup/><q-btn type="submit" color="primary" label="Guardar token" no-caps unelevated :loading="tokenSaving"/></q-card-actions></q-form></q-card>
    </q-dialog>

  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'
const { proxy } = getCurrentInstance()
const file=ref(null),password=ref(''),showPassword=ref(false),uploading=ref(false),loading=ref(false),certificates=ref([]),certificateDialog=ref(false)
const tokens=ref([]),tokensLoading=ref(false),tokenSaving=ref(false),tokenDialog=ref(false),tokenForm=reactive({token:''})
const siatCredentials=reactive({cuis:null,cufd:null}),credentialsLoading=ref(false),creatingCuis=ref(false),creatingCufd=ref(false)
const required=value=>!!value||'Campo requerido'
function openCertificateDialog(){file.value=null;password.value='';certificateDialog.value=true}
function openTokenDialog(){tokenForm.token='';tokenDialog.value=true}
function load(){loading.value=true;proxy.$axios.get('/certificados-digitales').then(({data})=>{certificates.value=data}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los certificados')).finally(()=>{loading.value=false})}
function loadTokens(){tokensLoading.value=true;proxy.$axios.get('/siat-tokens').then(({data})=>{tokens.value=data}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los tokens')).finally(()=>{tokensLoading.value=false})}
async function loadCredentials(){credentialsLoading.value=true;try{const {data}=await proxy.$axios.get('/siat-credenciales');Object.assign(siatCredentials,data)}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo consultar CUIS/CUFD')}finally{credentialsLoading.value=false}}
async function createCuis(){creatingCuis.value=true;try{const {data}=await proxy.$axios.post('/siat-cuis');Object.assign(siatCredentials,data.credentials);proxy.$alert.success(data.message)}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo generar el CUIS')}finally{creatingCuis.value=false}}
async function createCufd(){creatingCufd.value=true;try{const {data}=await proxy.$axios.post('/siat-cufd');Object.assign(siatCredentials,data.credentials);proxy.$alert.success(data.message)}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo generar el CUFD')}finally{creatingCufd.value=false}}
async function upload(){uploading.value=true;const data=new FormData();data.append('archivo',file.value);data.append('contrasena',password.value);try{await proxy.$axios.post('/certificados-digitales',data);proxy.$alert.success('Certificado importado y claves generadas');certificateDialog.value=false;await load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo importar')}finally{uploading.value=false}}
async function saveToken(){tokenSaving.value=true;try{await proxy.$axios.post('/siat-tokens',tokenForm);proxy.$alert.success('Token guardado');tokenDialog.value=false;await loadTokens()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{tokenSaving.value=false}}
async function activate(c){try{await proxy.$axios.put(`/certificados-digitales/${c.id}/activar`);await load()}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo activar')}}
function removeCertificate(c){proxy.$alert.dialog(`¿Eliminar ${c.nombre_archivo}?`).onOk(async()=>{try{await proxy.$axios.delete(`/certificados-digitales/${c.id}`);proxy.$alert.success('Certificado eliminado');await load()}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')}})}
function removeToken(t){proxy.$alert.dialog(`¿Eliminar el token #${t.id}?`).onOk(async()=>{try{await proxy.$axios.delete(`/siat-tokens/${t.id}`);proxy.$alert.success('Token eliminado');await loadTokens()}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')}})}
function status(c){if(c.valido_hasta&&new Date(c.valido_hasta)<new Date())return{label:'Vencido',color:'negative'};return c.activo?{label:'Activo',color:'positive'}:{label:'Inactivo',color:'grey'}}
function tokenStatus(t){return t.vence_en&&new Date(t.vence_en)<new Date()?{label:'Vencido',color:'negative'}:{label:'Vigente',color:'positive'}}
function subjectName(c){return c.titular?.commonName||c.titular?.CN||c.titular?.O||'Titular no especificado'}
function dateTime(v){return v?new Date(v).toLocaleString('es-BO',{dateStyle:'medium',timeStyle:'short'}):'—'}
onMounted(()=>{load();loadTokens();loadCredentials()})
</script>

<style scoped>
.signature-page{background:linear-gradient(180deg,#fff4f4,#fff 320px)}.page-heading{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.heading-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:white;background:linear-gradient(135deg,#ef4444,#991b1b);box-shadow:0 8px 18px #991b1b33}.signature-card{border-radius:16px;background:#fffffff5;box-shadow:0 8px 28px #5a0f0f0f}.security-note{background:#fff1f2;color:#7f1d1d;font-size:12px}.empty-state{text-align:center;padding:42px 20px}.certificate-item{padding:16px}.certificate-data{display:grid;grid-template-columns:1fr 1fr;gap:5px 14px;font-size:11px;color:#667085}.fingerprint{grid-column:1/-1;word-break:break-all}.dialog-card{width:620px;max-width:95vw;border-radius:16px}@media(max-width:600px){.page-heading>.q-space{display:none}.page-heading .q-btn{flex:1}.certificate-data{grid-template-columns:1fr}.fingerprint{grid-column:auto}.certificate-item{padding:12px 8px}}
</style>
