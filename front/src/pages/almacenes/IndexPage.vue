<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Almacenes</div><div class="text-caption text-grey-7">Revisiones del stock físico de la tienda</div></div>
      <q-space/><q-btn v-if="can('Crear Almacenes')" dense unelevated color="primary" icon="add" label="Nueva revisión" no-caps @click="openCreate"/>
    </div>

    <div class="kpi-row q-mb-sm">
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="fact_check" color="orange-1" text-color="orange-9" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">En revisión</div><div class="text-h6 text-weight-bold">{{summary.en_revision}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="published_with_changes" color="green-1" text-color="positive" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Aplicadas</div><div class="text-h6 text-weight-bold">{{summary.aplicados}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="inventory_2" color="blue-1" text-color="primary" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Productos revisados</div><div class="text-h6 text-weight-bold">{{summary.productos_revisados}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="payments" color="purple-1" text-color="purple-9" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Valor de las diferencias</div><div class="text-h6 text-weight-bold" :class="summary.diferencia_valor<0?'text-negative':''">Bs {{money(summary.diferencia_valor)}}</div></div></q-card-section></q-card>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm">
        <q-input v-model="filters.q" dense outlined clearable debounce="300" class="col-12 col-sm" placeholder="Buscar número, usuario o descripción" @update:model-value="reload"><template #prepend><q-icon name="search"/></template></q-input>
        <q-input v-model="filters.desde" dense outlined clearable type="date" label="Desde" stack-label class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-input v-model="filters.hasta" dense outlined clearable type="date" label="Hasta" stack-label class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-select v-model="filters.estado" :options="stateOptions" emit-value map-options dense outlined clearable label="Estado" class="col-6 col-sm-2" @update:model-value="reload"/>
      </q-card-section>
      <q-separator/>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[20,50,100]" @request="onRequest">
        <template #body-cell-fecha="p"><q-td :props="p">{{formatDate(p.row.fecha)}}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="stateColor(p.row.estado)" :label="p.row.estado==='BORRADOR'?'EN REVISIÓN':p.row.estado"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p">
          <q-btn v-if="p.row.estado==='BORRADOR'&&can('Editar Almacenes')" dense flat round color="primary" icon="fact_check" :to="`/almacenes/${p.row.id}`"><q-tooltip>Llenar la revisión</q-tooltip></q-btn>
          <q-btn dense flat round color="blue-grey" icon="insights" :to="`/almacenes/${p.row.id}/avance`"><q-tooltip>Ver avance y actualizar productos</q-tooltip></q-btn>
          <q-btn v-if="p.row.estado!=='ANULADO'&&can('Anular Almacenes')" dense flat round color="negative" icon="undo" @click="cancel(p.row)"><q-tooltip>Anular{{p.row.estado==='APLICADO'?' y devolver el stock anterior':''}}</q-tooltip></q-btn>
          <q-btn v-if="p.row.estado==='BORRADOR'&&can('Anular Almacenes')" dense flat round color="grey-7" icon="delete" @click="remove(p.row)"><q-tooltip>Eliminar revisión</q-tooltip></q-btn>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="warehouse" size="42px"/><div>No hay revisiones registradas</div></div></template>
      </q-table>
    </q-card>

    <q-dialog v-model="createDialog">
      <q-card style="width:430px;max-width:94vw">
        <q-form @submit.prevent="create">
          <q-card-section class="row items-center q-py-sm bg-primary text-white"><q-avatar color="white" text-color="primary" icon="warehouse" size="32px"/><div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Nueva revisión de almacén</div><div class="text-caption">Después la llenan entre todos desde sus celulares</div></div></q-card-section>
          <q-card-section class="q-pa-md">
            <q-input v-model="form.descripcion" autofocus outlined dense label="Descripción" placeholder="Ej. REVISIÓN GENERAL AGOSTO" maxlength="255" class="q-mb-sm"/>
            <q-input v-model="form.observacion" outlined dense autogrow label="Observación (opcional)" maxlength="1000"/>
          </q-card-section>
          <q-separator/>
          <q-card-actions align="right" class="q-pa-sm"><q-btn flat dense label="Cancelar" no-caps v-close-popup/><q-btn type="submit" dense unelevated color="primary" icon="playlist_add" label="Crear y empezar" no-caps :loading="creating"/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
const {proxy}=getCurrentInstance(),router=useRouter()
const rows=ref([]),loading=ref(false),createDialog=ref(false),creating=ref(false)
const summary=reactive({en_revision:0,aplicados:0,productos_revisados:0,diferencia_valor:0})
const filters=reactive({q:'',desde:'',hasta:'',estado:null})
const form=reactive({descripcion:'',observacion:''})
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0})
const can=p=>proxy.$store.hasPermission(p),money=v=>Number(v||0).toFixed(2)
const formatDate=value=>value?new Date(value).toLocaleString('es-BO'):''
const stateColor=estado=>estado==='APLICADO'?'positive':estado==='ANULADO'?'grey-6':'orange'
const stateOptions=[{label:'EN REVISIÓN',value:'BORRADOR'},{label:'APLICADO',value:'APLICADO'},{label:'ANULADO',value:'ANULADO'}]
const columns=[
  {name:'actions',label:'Acciones',align:'left'},
  {name:'numero',label:'Número',field:'numero',align:'left'},
  {name:'fecha',label:'Fecha',field:'fecha',align:'left'},
  {name:'descripcion',label:'Descripción',field:r=>r.descripcion||'—',align:'left'},
  {name:'usuario_nombre',label:'Creó',field:'usuario_nombre',align:'left'},
  {name:'detalles_count',label:'Productos',field:'detalles_count',align:'center'},
  {name:'estado',label:'Estado',field:'estado',align:'center'}
]
const params=()=>({q:filters.q||'',desde:filters.desde||'',hasta:filters.hasta||'',estado:filters.estado||'',
  page:pagination.value.page,per_page:pagination.value.rowsPerPage})

async function load(){
  loading.value=true
  try{
    const {data}=await proxy.$axios.get('/almacenes',{params:params()})
    rows.value=data.data;pagination.value.rowsNumber=data.total||0;pagination.value.page=data.current_page||1
    Object.assign(summary,(await proxy.$axios.get('/almacenes-resumen',{params:params()})).data)
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los almacenes')}
  finally{loading.value=false}
}
function reload(){pagination.value.page=1;load()}
function onRequest(request){pagination.value.page=request.pagination.page;pagination.value.rowsPerPage=request.pagination.rowsPerPage;load()}
function openCreate(){Object.assign(form,{descripcion:'',observacion:''});createDialog.value=true}
async function create(){
  creating.value=true
  try{
    const {data}=await proxy.$axios.post('/almacenes',{descripcion:form.descripcion||null,observacion:form.observacion||null})
    createDialog.value=false
    proxy.$alert.success(`Revisión ${data.numero} creada`)
    router.push(`/almacenes/${data.id}`)
  }catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo crear la revisión')}
  finally{creating.value=false}
}
function cancel(row){
  const aviso=row.estado==='APLICADO'?' El stock de cada producto volverá al valor anterior.':''
  proxy.$alert.dialog(`¿Anular la revisión ${row.numero}?${aviso}`).onOk(async()=>{
    try{await proxy.$axios.put(`/almacenes/${row.id}/anular`);proxy.$alert.success(`Revisión ${row.numero} anulada`);load()}
    catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo anular la revisión')}
  })
}
function remove(row){
  proxy.$alert.dialog(`¿Eliminar la revisión ${row.numero}?`).onOk(async()=>{
    try{await proxy.$axios.delete(`/almacenes/${row.id}`);proxy.$alert.success('Revisión eliminada');load()}
    catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar la revisión')}
  })
}
onMounted(load)
</script>

<style scoped>
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.kpi-card{border-radius:10px}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

