<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Usuarios</div><div class="text-caption text-grey-7">Accesos y permisos</div></div>
      <q-space/><q-btn v-if="can('Crear Usuarios')" dense unelevated color="primary" icon="person_add" label="Nuevo" no-caps @click="openForm()"/>
    </div>
    <q-card flat bordered>
      <q-table dense flat :rows="filtered" :columns="columns" row-key="id" :loading="loading" :rows-per-page-options="[10,20,50]">
        <template #top><q-input v-model="search" dense outlined debounce="200" placeholder="Buscar usuario" clearable style="width:290px"><template #prepend><q-icon name="search" size="18px"/></template></q-input></template>
        <template #body-cell-user="p"><q-td :props="p"><div class="row items-center no-wrap q-gutter-xs"><q-avatar size="32px" color="blue-1" text-color="primary"><img v-if="p.row.avatar" :src="avatarUrl(p.row.avatar)"/><q-icon v-else name="person"/></q-avatar><div><div class="text-weight-medium">{{p.row.name}}</div><div class="text-caption text-grey-7">@{{p.row.username}}</div></div></div></q-td></template>
        <template #body-cell-permissions="p"><q-td :props="p"><q-badge outline color="primary" :label="`${p.row.permissions?.length||0} permisos`"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p" class="text-left">
          <q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none">
            <q-list dense style="min-width:170px">
              <q-item v-if="can('Editar Usuarios')" clickable v-close-popup @click="openForm(p.row)"><q-item-section avatar><q-icon name="edit" color="primary"/></q-item-section><q-item-section>Editar y permisos</q-item-section></q-item>
              <q-item v-if="can('Editar Usuarios')" clickable v-close-popup @click="resetPassword(p.row)"><q-item-section avatar><q-icon name="lock_reset" color="orange"/></q-item-section><q-item-section>Restablecer clave</q-item-section></q-item>
              <q-separator/>
              <q-item v-if="can('Eliminar Usuarios')&&p.row.id!==proxy.$store.user.id" clickable v-close-popup class="text-negative" @click="remove(p.row)"><q-item-section avatar><q-icon name="delete"/></q-item-section><q-item-section>Eliminar</q-item-section></q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card class="user-form">
        <q-form @submit="save">
          <q-card-section class="row items-center q-py-sm bg-blue-1">
            <q-avatar size="34px" color="primary" text-color="white" icon="manage_accounts" class="q-mr-sm"/>
            <div><div class="text-subtitle1 text-weight-bold">{{form.id?'Editar usuario':'Nuevo usuario'}}</div><div class="text-caption text-grey-7">Datos personales, imagen y permisos</div></div>
            <q-space/><q-btn flat round dense icon="close" @click="closeForm"/>
          </q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-sm">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-sm-auto">
                <div class="avatar-drop" :class="{'avatar-drop--active':dragging}" role="button" tabindex="0"
                  @click="fileInput?.pickFiles()" @keydown.enter.prevent="fileInput?.pickFiles()" @keydown.space.prevent="fileInput?.pickFiles()"
                  @dragenter.prevent="dragging=true" @dragover.prevent="dragging=true" @dragleave.prevent="dragging=false" @drop.prevent="dropAvatar">
                  <q-avatar size="88px" color="blue-1" text-color="primary">
                    <img v-if="avatarPreview || form.avatar" :src="avatarPreview || avatarUrl(form.avatar)"/>
                    <q-icon v-else name="person" size="46px"/>
                  </q-avatar>
                  <div class="avatar-drop__overlay"><q-icon name="photo_camera" size="22px"/><span>{{form.avatar?'Cambiar':'Elegir'}}</span></div>
                </div>
                <q-file ref="fileInput" v-model="avatarFile" class="hidden" accept="image/*" :max-file-size="8388608" @update:model-value="selectAvatar" @rejected="rejectAvatar"/>
                <div class="text-caption text-grey-7 text-center q-mt-xs">Clic o arrastra una imagen</div>
              </div>
              <div class="col">
                <div class="section-title"><q-icon name="badge"/> Información</div>
                <div class="row q-col-gutter-sm">
                  <q-input v-model="form.name" dense outlined label="Nombre completo *" class="col-12 col-sm-6" :rules="[required]" hide-bottom-space/>
                  <q-input v-model="form.username" dense outlined label="Usuario *" class="col-12 col-sm-3" :disable="!!form.id" :rules="[required]" hide-bottom-space/>
                  <q-input v-model="form.ci" dense outlined label="CI" class="col-12 col-sm-3"/>
                  <q-input v-model="form.email" dense outlined type="email" label="Correo" class="col-12 col-sm-5"/>
                  <q-input v-model="form.celular" dense outlined label="Celular" class="col-12 col-sm-3"/>
                  <q-input v-if="!form.id" v-model="form.password" dense outlined type="password" label="Contraseña *" class="col-12 col-sm-4" :rules="[v=>v?.length>=6||'Mínimo 6 caracteres']" hide-bottom-space/>
                </div>
              </div>
            </div>
            <template v-if="can('Gestionar Permisos')">
              <q-separator class="q-my-sm"/>
              <div class="row items-center">
                <div class="section-title q-mb-none"><q-icon name="admin_panel_settings"/> Permisos</div>
                <q-space/><q-btn dense flat size="sm" no-caps label="Todos" @click="selectedPermissions=permissions.map(p=>p.id)"/><q-btn dense flat size="sm" no-caps label="Ninguno" @click="selectedPermissions=[]"/>
              </div>
              <div class="permissions-grid">
                <div v-for="group in permissionGroups" :key="group.name" class="permission-group">
                  <div class="text-caption text-weight-bold text-primary">{{group.name}}</div>
                  <q-checkbox v-for="p in group.items" :key="p.id" v-model="selectedPermissions" :val="p.id" :label="p.name.replace(` ${group.name}`,'')" dense size="sm"/>
                </div>
              </div>
            </template>
          </q-card-section>
          <q-separator/><q-card-actions align="right" class="q-py-xs"><q-btn dense flat label="Cancelar" no-caps @click="closeForm"/><q-btn dense unelevated color="primary" icon="save" label="Guardar" no-caps type="submit" :loading="saving"/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
const { proxy }=getCurrentInstance()
const rows=ref([]),search=ref(''),loading=ref(false),saving=ref(false),dialog=ref(false),permissions=ref([]),selectedPermissions=ref([])
const avatarFile=ref(null),avatarPreview=ref(''),dragging=ref(false),fileInput=ref(null)
const empty=()=>({id:null,name:'',username:'',email:'',celular:'',ci:'',password:'123456',avatar:null}),form=reactive(empty())
const columns=[{name:'actions',label:'Acciones',align:'left'},{name:'user',label:'Usuario',field:'name',align:'left'},{name:'email',label:'Correo',field:'email',align:'left'},{name:'celular',label:'Celular',field:'celular',align:'left'},{name:'ci',label:'CI',field:'ci',align:'left'},{name:'permissions',label:'Permisos',align:'center'}]
const can=p=>proxy.$store.hasPermission(p),required=v=>!!v||'Campo requerido'
const avatarUrl=filename=>`${proxy.$imgBase}/images/${filename}`
const filtered=computed(()=>{const q=(search.value||'').toLowerCase();return rows.value.filter(u=>[u.name,u.username,u.email,u.ci].some(v=>(v||'').toLowerCase().includes(q)))})
const permissionGroups=computed(()=>['Usuarios','Productos','Ventas','Compras','Configuración'].map(name=>({name,items:permissions.value.filter(p=>p.name.includes(name))})))
function load(){loading.value=true;proxy.$axios.get('/users').then(r=>rows.value=r.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los usuarios')).finally(()=>loading.value=false)}
async function openForm(row=null){clearPreview();avatarFile.value=null;dragging.value=false;Object.assign(form,empty(),row||{});selectedPermissions.value=(row?.permissions||[]).map(p=>p.id);if(can('Gestionar Permisos')){try{permissions.value=(await proxy.$axios.get('/permissions')).data}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los permisos')}}dialog.value=true}
function closeForm(){dialog.value=false;clearPreview();avatarFile.value=null}
function clearPreview(){if(avatarPreview.value)URL.revokeObjectURL(avatarPreview.value);avatarPreview.value=''}
function selectAvatar(file){clearPreview();if(file)avatarPreview.value=URL.createObjectURL(file)}
function rejectAvatar(){proxy.$alert.error('Selecciona una imagen de hasta 8 MB')}
function dropAvatar(event){dragging.value=false;const file=event.dataTransfer?.files?.[0];if(!file)return;if(!file.type.startsWith('image/')||file.size>8388608){rejectAvatar();return}avatarFile.value=file;selectAvatar(file)}
async function save(){saving.value=true;try{let user;if(form.id){user=(await proxy.$axios.put(`/users/${form.id}`,form)).data}else{user=(await proxy.$axios.post('/users',form)).data}if(avatarFile.value){const data=new FormData();data.append('avatar',avatarFile.value);const uploaded=await proxy.$axios.post(`/users/${user.id}/avatar`,data);user.avatar=uploaded.data.avatar}if(can('Gestionar Permisos'))await proxy.$axios.put(`/users/${user.id}/permissions`,{permissions:selectedPermissions.value});proxy.$alert.success('Usuario guardado');closeForm();load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{saving.value=false}}
function resetPassword(u){proxy.$alert.dialog(`¿Restablecer la contraseña de ${u.name} a 123456?`).onOk(()=>proxy.$axios.put(`/users/${u.id}/reset-password`).then(()=>proxy.$alert.success('Contraseña restablecida')).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo restablecer')))}
function remove(u){proxy.$alert.dialog(`¿Eliminar al usuario ${u.name}?`).onOk(()=>proxy.$axios.delete(`/users/${u.id}`).then(()=>{proxy.$alert.success('Usuario eliminado');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')))}
onMounted(load)
</script>

<style scoped>
.user-form{width:820px;max-width:96vw}.section-title{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;margin-bottom:6px}.avatar-drop{position:relative;width:104px;height:104px;border:2px dashed #c8d2dc;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;transition:.2s}.avatar-drop:hover,.avatar-drop--active{border-color:#f57c00;background:#fff3e0;transform:scale(1.02)}.avatar-drop__overlay{position:absolute;inset:auto 0 0;background:rgba(0,0,0,.58);color:white;display:flex;align-items:center;justify-content:center;gap:3px;padding:4px;font-size:11px}.permissions-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-top:5px}.permission-group{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 5px;padding:6px;border:1px solid #e4e8eb;border-radius:7px;background:#fafcfd}.permission-group>.text-caption{grid-column:1/-1}@media(max-width:600px){.permissions-grid{grid-template-columns:1fr}.permission-group{grid-template-columns:1fr}.avatar-drop{margin:auto}}
</style>
