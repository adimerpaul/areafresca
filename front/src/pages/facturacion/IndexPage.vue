<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Facturación</div><div class="text-caption text-grey-7">Libro de ventas importado del SIAT</div></div>
      <q-space/><q-btn v-if="can('Importar Facturación')" dense unelevated color="primary" icon="upload_file" label="Importar Excel" no-caps @click="importDialog=true"/>
    </div>

    <div class="kpi-row q-mb-sm">
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="receipt_long" color="blue-1" text-color="primary" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Facturas del mes</div><div class="text-h6 text-weight-bold">{{summary.cantidad}}</div><div class="text-caption text-grey-6">{{summary.anuladas}} anuladas</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="payments" color="green-1" text-color="positive" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Importe válido</div><div class="text-h6 text-weight-bold">Bs {{money(summary.importe_total)}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="check_circle" color="green-1" text-color="positive" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">En el sistema</div><div class="text-h6 text-weight-bold">{{summary.en_sistema}}</div><div class="text-caption text-grey-6">Débito fiscal Bs {{money(summary.debito_fiscal)}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card kpi-missing" @click="showMissing"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="report_problem" color="red-1" text-color="negative" size="38px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Sin registrar</div><div class="text-h6 text-weight-bold text-negative">{{summary.sin_registrar}}</div><div class="text-caption text-grey-6">Bs {{money(summary.importe_sin_registrar)}} por recuperar</div></div></q-card-section><q-tooltip>Facturas que están en Impuestos pero no en el sistema. Clic para verlas.</q-tooltip></q-card>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm items-center">
        <q-input v-model="filters.q" dense outlined clearable debounce="400" class="col-12 col-sm" placeholder="Buscar factura, CUF, NIT o razón social" @update:model-value="reload"><template #prepend><q-icon name="search"/></template></q-input>
        <q-input v-model="filters.mes" dense outlined type="month" label="Mes" stack-label class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-select v-model="filters.estado" :options="['VALIDA','ANULADA']" dense outlined clearable label="Estado" class="col-6 col-sm-2" @update:model-value="reload"/>
        <q-select v-model="filters.en_sistema" :options="[{label:'En el sistema',value:'si'},{label:'Sin registrar',value:'no'}]" emit-value map-options dense outlined clearable label="Registro" class="col-6 col-sm-2" @update:model-value="reload"/>
        <div class="col-12 col-sm-auto row q-gutter-xs">
          <q-btn dense flat no-caps color="primary" label="Mes anterior" @click="setMonth(-1)"/>
          <q-btn dense flat no-caps color="primary" label="Mes actual" @click="setMonth(0)"/>
        </div>
      </q-card-section>
      <q-separator/>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[20,50,100,200]" @request="onRequest">
        <template #body-cell-fecha="p"><q-td :props="p">{{formatDate(p.row.fecha_factura)}}</q-td></template>
        <template #body-cell-cuf="p"><q-td :props="p"><span class="cuf-cell">{{p.row.cuf}}</span><q-tooltip>{{p.row.cuf}}</q-tooltip></q-td></template>
        <template #body-cell-total="p"><q-td :props="p" class="text-right text-weight-bold">Bs {{money(p.row.importe_total)}}</q-td></template>
        <template #body-cell-debito="p"><q-td :props="p" class="text-right">Bs {{money(p.row.debito_fiscal)}}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.row.estado==='VALIDA'?'positive':'grey-6'" :label="p.row.estado"/></q-td></template>
        <template #body-cell-en_sistema="p"><q-td :props="p">
          <q-badge v-if="p.row.venta" color="positive" :label="p.row.venta.numero"><q-tooltip>Registrada como venta {{p.row.venta.numero}} · {{p.row.venta.estado}}</q-tooltip></q-badge>
          <q-badge v-else color="negative" label="Sin registrar"><q-tooltip>El CUF está en Impuestos pero no existe ninguna venta con ese código</q-tooltip></q-badge>
        </q-td></template>
        <template #body-cell-actions="p"><q-td :props="p">
          <q-btn dense flat round color="primary" icon="visibility" @click="openDetail(p.row)"><q-tooltip>Ver detalle</q-tooltip></q-btn>
          <q-btn v-if="can('Eliminar Facturación')" dense flat round color="negative" icon="delete" @click="remove(p.row)"><q-tooltip>Eliminar registro</q-tooltip></q-btn>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="inbox" size="42px"/><div>No hay facturas en {{monthLabel(filters.mes)}}</div></div></template>
      </q-table>
    </q-card>

    <q-dialog v-model="importDialog">
      <q-card style="width:520px;max-width:94vw">
        <q-card-section class="row items-center q-py-sm bg-primary text-white"><q-avatar color="white" text-color="primary" icon="upload_file" size="32px"/><div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Importar libro de ventas</div><div class="text-caption">ZIP o XLSX descargado del SIAT</div></div><q-space/><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
        <q-card-section class="q-pa-sm">
          <q-file v-model="file" dense outlined accept=".zip,.xlsx,.xls" label="Seleccione el archivo (.zip o .xlsx)" clearable><template #prepend><q-icon name="attach_file"/></template></q-file>
          <div class="text-caption text-grey-7 q-mt-sm">Las facturas cuyo <b>código de autorización (CUF)</b> ya esté registrado no se vuelven a insertar, así que puede subir el mismo archivo varias veces sin duplicar nada.</div>
          <div v-if="result" class="q-mt-sm q-pa-sm rounded-borders bg-green-1 text-green-10 text-caption">
            Leídas <b>{{result.total}}</b> · insertadas <b>{{result.insertados}}</b> · omitidas por CUF repetido <b>{{result.duplicados}}</b><span v-if="result.meses?.length"> · meses: <b>{{result.meses.join(', ')}}</b></span>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat dense no-caps label="Cerrar" v-close-popup/><q-btn unelevated dense no-caps color="primary" icon="cloud_upload" label="Importar" :disable="!file" :loading="importing" @click="importFile"/></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="detailDialog">
      <q-card style="width:620px;max-width:94vw">
        <q-card-section class="row items-center q-py-sm bg-primary text-white"><q-avatar color="white" text-color="primary" icon="receipt_long" size="32px"/><div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Factura {{detail.numero_factura}}</div><div class="text-caption">{{formatDate(detail.fecha_factura)}} · {{detail.razon_social}}</div></div><q-space/><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
        <q-card-section class="q-pa-sm">
          <div class="text-caption text-grey-7">Código de autorización (CUF)</div>
          <div class="text-body2 q-mb-sm" style="word-break:break-all">{{detail.cuf}}</div>
          <q-markup-table flat bordered dense>
            <tbody>
              <tr><td>En el sistema</td><td class="text-right"><q-badge v-if="detail.venta" color="positive" :label="`Venta ${detail.venta.numero}`"/><q-badge v-else color="negative" label="Sin registrar"/></td></tr>
              <tr><td>NIT / CI</td><td class="text-right">{{detail.nit_ci_cliente}}<span v-if="detail.complemento">-{{detail.complemento}}</span></td></tr>
              <tr><td>Importe total</td><td class="text-right text-weight-bold">Bs {{money(detail.importe_total)}}</td></tr>
              <tr><td>Subtotal</td><td class="text-right">Bs {{money(detail.subtotal)}}</td></tr>
              <tr><td>Descuentos</td><td class="text-right">Bs {{money(detail.descuentos)}}</td></tr>
              <tr><td>Base para débito fiscal</td><td class="text-right">Bs {{money(detail.importe_base_debito_fiscal)}}</td></tr>
              <tr><td>Débito fiscal</td><td class="text-right">Bs {{money(detail.debito_fiscal)}}</td></tr>
              <tr><td>Estado</td><td class="text-right"><q-badge :color="detail.estado==='VALIDA'?'positive':'grey-6'" :label="detail.estado"/></td></tr>
              <tr><td>Tipo de venta</td><td class="text-right">{{detail.tipo_venta}}</td></tr>
              <tr><td>Con derecho a crédito fiscal</td><td class="text-right">{{detail.credito_fiscal}}</td></tr>
              <tr><td>Consolidación</td><td class="text-right">{{detail.estado_consolidacion}}</td></tr>
              <tr><td>Archivo de origen</td><td class="text-right">{{detail.archivo_origen}}</td></tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'
const {proxy}=getCurrentInstance()
const rows=ref([]),loading=ref(false),importDialog=ref(false),detailDialog=ref(false),importing=ref(false),file=ref(null),result=ref(null)
const detail=reactive({})
const summary=reactive({cantidad:0,validas:0,anuladas:0,importe_total:0,debito_fiscal:0,en_sistema:0,sin_registrar:0,importe_sin_registrar:0,meses:[]})
// El SIAT publica el libro del mes ya cerrado: en septiembre lo que interesa es agosto.
const monthOf=offset=>{const d=new Date();d.setDate(1);d.setMonth(d.getMonth()+offset-1);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`}
const filters=reactive({q:'',mes:monthOf(0),estado:null,en_sistema:null})
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0})
const can=p=>proxy.$store.hasPermission(p),money=v=>Number(v||0).toFixed(2)
const formatDate=value=>value?new Date(String(value).slice(0,10)+'T00:00:00').toLocaleDateString('es-BO'):''
const monthLabel=mes=>mes?new Date(mes+'-01T00:00:00').toLocaleDateString('es-BO',{month:'long',year:'numeric'}):'el mes seleccionado'
const columns=[
  {name:'actions',label:'Acciones',align:'left'},
  {name:'fecha',label:'Fecha',field:'fecha_factura',align:'left'},
  {name:'numero_factura',label:'Nº factura',field:'numero_factura',align:'left'},
  {name:'cuf',label:'CUF',field:'cuf',align:'left'},
  {name:'nit_ci_cliente',label:'NIT / CI',field:'nit_ci_cliente',align:'left'},
  {name:'razon_social',label:'Razón social',field:'razon_social',align:'left'},
  {name:'total',label:'Importe',field:'importe_total',align:'right'},
  {name:'debito',label:'Débito fiscal',field:'debito_fiscal',align:'right'},
  {name:'estado',label:'Estado',field:'estado',align:'center'},
  {name:'en_sistema',label:'En el sistema',field:'venta',align:'center'}
]
const params=()=>({q:filters.q||'',mes:filters.mes||'',estado:filters.estado||'',en_sistema:filters.en_sistema||'',page:pagination.value.page,per_page:pagination.value.rowsPerPage})

async function load(){
  loading.value=true
  try{
    const {data}=await proxy.$axios.get('/facturacion',{params:params()})
    rows.value=data.data;pagination.value.rowsNumber=data.total||0;pagination.value.page=data.current_page||1
    Object.assign(summary,(await proxy.$axios.get('/facturacion-resumen',{params:params()})).data)
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo cargar la facturación')}
  finally{loading.value=false}
}
function reload(){pagination.value.page=1;load()}
function setMonth(offset){filters.mes=monthOf(offset);reload()}
function showMissing(){filters.en_sistema='no';reload()}
function onRequest(request){pagination.value.page=request.pagination.page;pagination.value.rowsPerPage=request.pagination.rowsPerPage;load()}
async function openDetail(row){
  try{Object.assign(detail,(await proxy.$axios.get(`/facturacion/${row.id}`)).data);detailDialog.value=true}
  catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo cargar la factura')}
}
async function importFile(){
  const form=new FormData();form.append('archivo',file.value)
  importing.value=true;result.value=null
  try{
    const {data}=await proxy.$axios.post('/facturacion/importar',form,{headers:{'Content-Type':'multipart/form-data'}})
    result.value=data;file.value=null
    proxy.$alert.success(`${data.insertados} facturas importadas`,`${data.duplicados} omitidas porque el CUF ya existía`)
    // Salta al mes del archivo para que el usuario vea de inmediato lo que subió.
    if(data.meses?.length) filters.mes=data.meses[data.meses.length-1]
    reload()
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo importar el archivo')}
  finally{importing.value=false}
}
function remove(row){
  proxy.$alert.dialog(`¿Eliminar la factura ${row.numero_factura}?`).onOk(async()=>{
    try{await proxy.$axios.delete(`/facturacion/${row.id}`);proxy.$alert.success('Factura eliminada');load()}
    catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar la factura')}
  })
}
onMounted(load)
</script>

<style scoped>
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.kpi-card{border-radius:10px}
.kpi-missing{cursor:pointer}
.cuf-cell{display:inline-block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
