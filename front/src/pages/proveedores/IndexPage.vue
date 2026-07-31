<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-subtitle1 text-weight-bold">Proveedores</div><div class="text-caption text-grey-7">Directorio de proveedores registrados</div></div><q-space/><q-btn dense flat icon="add_business" label="Nueva compra" no-caps to="/compras/nueva"/></div>
    <q-card flat bordered><q-card-section class="q-pa-sm"><q-input v-model="search" dense outlined clearable placeholder="Buscar proveedor, NIT o teléfono"><template #prepend><q-icon name="search"/></template></q-input></q-card-section>
      <q-table flat dense :rows="filtered" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:20}">
        <template #body-cell-nombre="p"><q-td :props="p"><q-btn flat dense no-caps color="primary" :label="p.value" @click="selected=p.row;dialog=true"/></q-td></template>
        <template #body-cell-datos="p"><q-td :props="p"><div>{{p.row.telefono||'Sin teléfono'}}</div><div class="text-caption">{{p.row.direccion||'Sin dirección'}}</div></q-td></template>
      </q-table>
    </q-card>
    <q-dialog v-model="dialog"><q-card style="width:480px;max-width:94vw"><q-card-section class="row items-center"><div><div class="text-h6">{{selected.nombre}}</div><div class="text-caption">Datos del proveedor</div></div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/><q-list><q-item><q-item-section avatar><q-icon name="badge" color="primary"/></q-item-section><q-item-section><q-item-label>NIT</q-item-label><q-item-label caption>{{selected.nit||'No registrado'}}</q-item-label></q-item-section></q-item><q-item><q-item-section avatar><q-icon name="phone" color="primary"/></q-item-section><q-item-section><q-item-label>Teléfono</q-item-label><q-item-label caption>{{selected.telefono||'No registrado'}}</q-item-label></q-item-section></q-item><q-item><q-item-section avatar><q-icon name="location_on" color="primary"/></q-item-section><q-item-section><q-item-label>Dirección</q-item-label><q-item-label caption>{{selected.direccion||'No registrada'}}</q-item-label></q-item-section></q-item></q-list></q-card></q-dialog>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'
const {proxy}=getCurrentInstance(),rows=ref([]),search=ref(''),loading=ref(false),dialog=ref(false),selected=ref({})
const columns=[{name:'nombre',label:'Proveedor',field:'nombre',align:'left'},{name:'nit',label:'NIT',field:'nit',align:'left'},{name:'datos',label:'Contacto',field:'telefono',align:'left'}]
const filtered=computed(()=>{const q=search.value?.toLowerCase().trim();return q?rows.value.filter(i=>[i.nombre,i.nit,i.telefono,i.direccion].some(v=>String(v||'').toLowerCase().includes(q))):rows.value})
onMounted(()=>{loading.value=true;proxy.$axios.get('/proveedores').then(r=>rows.value=r.data).finally(()=>loading.value=false)})
</script>
