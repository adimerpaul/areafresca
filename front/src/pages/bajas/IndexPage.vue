<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Bajas</div><div class="text-caption text-grey-7">Productos descargados del inventario</div></div>
      <q-space/><q-btn v-if="can('Crear Bajas')" dense unelevated color="negative" icon="add" label="Nueva baja" no-caps to="/bajas/nueva"/>
    </div>

    <div class="kpi-row q-mb-sm">
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="payments" color="red-1" text-color="negative" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Costo de las bajas</div><div class="text-h6 text-weight-bold">Bs {{money(summary.costo)}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="receipt_long" color="blue-1" text-color="primary" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Bajas registradas</div><div class="text-h6 text-weight-bold">{{summary.cantidad}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card kpi-motives"><q-card-section class="q-pa-sm"><div class="text-caption text-grey-7 q-mb-xs">Por motivo</div><div class="row q-gutter-xs"><q-badge v-for="m in summary.por_motivo" :key="m.nombre" outline color="negative" :label="`${m.nombre}: Bs ${money(m.costo)}`"/><span v-if="!summary.por_motivo.length" class="text-caption text-grey-6">Sin datos</span></div></q-card-section></q-card>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm">
        <q-input v-model="filters.q" dense outlined clearable debounce="300" class="col-12 col-sm" placeholder="Buscar nÃºmero, usuario o motivo" @update:model-value="reload"><template #prepend><q-icon name="search"/></template></q-input>
        <q-input v-model="filters.desde" dense outlined type="date" label="Desde" stack-label class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-input v-model="filters.hasta" dense outlined type="date" label="Hasta" stack-label class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-select v-model="filters.motivo_id" :options="motives" option-label="nombre" option-value="id" emit-value map-options dense outlined clearable label="Motivo" class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-select v-model="filters.estado" :options="['REGISTRADA','ANULADA']" dense outlined clearable label="Estado" class="col-6 col-sm-2" @update:model-value="reload"/>
      </q-card-section>
      <q-separator/>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[20,50,100]" @request="onRequest">
        <template #body-cell-motivo="p"><q-td :props="p"><q-badge :color="motiveColor(p.row.motivo_id)" :label="p.row.motivo"/></q-td></template>
        <template #body-cell-fecha="p"><q-td :props="p">{{formatDate(p.row.fecha)}}</q-td></template>
        <template #body-cell-costo="p"><q-td :props="p" class="text-right text-weight-bold text-negative">Bs {{money(p.row.total_costo)}}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.row.estado==='ANULADA'?'grey-6':'positive'" :label="p.row.estado"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p">
          <q-btn dense flat round color="primary" icon="visibility" @click="openDetail(p.row)"><q-tooltip>Ver detalle</q-tooltip></q-btn>
          <q-btn dense flat round color="grey-8" icon="print" :loading="printing===p.row.id" @click="print(p.row)"><q-tooltip>Imprimir comprobante</q-tooltip></q-btn>
          <q-btn v-if="can('Anular Bajas')&&p.row.estado!=='ANULADA'" dense flat round color="negative" icon="undo" @click="cancel(p.row)"><q-tooltip>Anular y devolver stock</q-tooltip></q-btn>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="inbox" size="42px"/><div>No hay bajas registradas</div></div></template>
      </q-table>
    </q-card>

    <q-dialog v-model="detailDialog">
      <q-card style="width:680px;max-width:94vw">
        <q-card-section class="row items-center q-py-sm bg-negative text-white"><q-avatar color="white" text-color="negative" icon="delete_forever" size="32px"/><div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">{{detail.numero}}</div><div class="text-caption">{{detail.motivo}} Â· {{formatDate(detail.fecha)}}</div></div><q-space/><q-btn flat dense icon="print" label="Imprimir" no-caps color="white" class="q-mr-xs" @click="printBaja(detail)"/><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
        <q-card-section class="q-pa-sm">
          <div class="row text-caption text-grey-8 q-mb-xs"><span>RegistrÃ³: <b>{{detail.usuario_nombre}}</b></span><q-space/><q-badge :color="detail.estado==='ANULADA'?'grey-6':'positive'" :label="detail.estado"/></div>
          <div v-if="detail.observacion" class="text-caption text-grey-7 q-mb-xs">ObservaciÃ³n: {{detail.observacion}}</div>
          <q-markup-table flat bordered dense class="detail-table">
            <thead><tr><th class="text-left">Producto</th><th class="text-right">Cantidad</th><th class="text-right">Costo unit.</th><th class="text-right">Total</th></tr></thead>
            <tbody><tr v-for="d in detail.detalles||[]" :key="d.id">
              <td>{{d.nombre}}<div v-if="d.observacion" class="text-caption text-grey-6">{{d.observacion}}</div></td>
              <td class="text-right">{{quantity(d.cantidad,d.unidad)}} {{d.unidad}}</td>
              <td class="text-right">Bs {{money(d.precio_compra)}}</td>
              <td class="text-right text-weight-bold">Bs {{money(d.total)}}</td>
            </tr></tbody>
          </q-markup-table>
          <div class="row items-center q-mt-sm q-pa-sm rounded-borders bg-red-1 text-red-10"><b>Costo total de la baja</b><q-space/><b class="text-h6">Bs {{money(detail.total_costo)}}</b></div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'
import { printBaja } from '../../addons/bajaPrint'
const {proxy}=getCurrentInstance()
const rows=ref([]),motives=ref([]),loading=ref(false),detailDialog=ref(false),printing=ref(null)
const detail=reactive({numero:'',motivo:'',fecha:null,usuario_nombre:'',estado:'',observacion:'',total_costo:0,detalles:[]})
const summary=reactive({costo:0,cantidad:0,por_motivo:[]})
const filters=reactive({q:'',desde:'',hasta:'',motivo_id:null,estado:null})
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0})
const can=p=>proxy.$store.hasPermission(p),money=v=>Number(v||0).toFixed(2)
const quantity=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const formatDate=value=>value?new Date(value).toLocaleString('es-BO'):''
const motiveColor=id=>motives.value.find(m=>m.id===id)?.color||'grey-8'
const columns=[
  {name:'actions',label:'Acciones',align:'left'},
  {name:'numero',label:'NÃºmero',field:'numero',align:'left'},
  {name:'fecha',label:'Fecha',field:'fecha',align:'left'},
  {name:'motivo',label:'Motivo',field:'motivo',align:'left'},
  {name:'usuario_nombre',label:'Usuario',field:'usuario_nombre',align:'left'},
  {name:'detalles_count',label:'Ãtems',field:'detalles_count',align:'center'},
  {name:'costo',label:'Costo',field:'total_costo',align:'right'},
  {name:'estado',label:'Estado',field:'estado',align:'center'}
]
const params=()=>({q:filters.q||'',desde:filters.desde||'',hasta:filters.hasta||'',motivo_id:filters.motivo_id||'',estado:filters.estado||'',
  page:pagination.value.page,per_page:pagination.value.rowsPerPage})

async function load(){
  loading.value=true
  try{
    const {data}=await proxy.$axios.get('/bajas',{params:params()})
    rows.value=data.data;pagination.value.rowsNumber=data.total||0;pagination.value.page=data.current_page||1
    const resumen=await proxy.$axios.get('/bajas-resumen',{params:params()})
    Object.assign(summary,resumen.data)
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar las bajas')}
  finally{loading.value=false}
}
function reload(){pagination.value.page=1;load()}
function onRequest(request){pagination.value.page=request.pagination.page;pagination.value.rowsPerPage=request.pagination.rowsPerPage;load()}
async function openDetail(row){
  try{Object.assign(detail,(await proxy.$axios.get(`/bajas/${row.id}`)).data);detailDialog.value=true}
  catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el detalle')}
}
// La fila del listado no trae los detalles: se piden antes de imprimir.
async function print(row){
  printing.value=row.id
  try{printBaja((await proxy.$axios.get(`/bajas/${row.id}`)).data)}
  catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo imprimir el comprobante')}
  finally{printing.value=null}
}
function cancel(row){
  proxy.$alert.dialog(`Â¿Anular la baja ${row.numero}? El stock volverÃ¡ al inventario.`).onOk(async()=>{
    try{await proxy.$axios.put(`/bajas/${row.id}/anular`);proxy.$alert.success(`Baja ${row.numero} anulada`);load()}
    catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo anular la baja')}
  })
}
onMounted(()=>{proxy.$axios.get('/bajas-catalogos').then(r=>motives.value=r.data.motivos).catch(()=>{});load()})
</script>

<style scoped>
.kpi-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr)) 2fr;gap:8px}.kpi-card{border-radius:10px}
.detail-table{max-height:300px;overflow:auto}
@media(max-width:900px){.kpi-row{grid-template-columns:1fr}.kpi-motives{order:3}}
</style>

