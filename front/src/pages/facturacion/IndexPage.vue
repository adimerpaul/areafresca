<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-xs">
      <div class="text-subtitle1 text-weight-bold">Facturación</div><div class="text-caption text-grey-7 q-ml-sm gt-xs">Libro de ventas importado del SIAT</div>
      <q-space/><q-btn v-if="can('Importar Facturación')" dense unelevated size="sm" color="primary" icon="upload_file" label="Importar Excel" no-caps class="q-px-sm" @click="importDialog=true"/>
    </div>

    <div class="kpi-row q-mb-xs">
      <q-card flat bordered class="kpi-card"><q-card-section class="kpi-body"><q-avatar icon="receipt_long" color="blue-1" text-color="primary" size="26px" font-size="15px"/><div class="q-ml-sm ellipsis"><div class="kpi-label">Facturas del mes</div><div class="kpi-value">{{summary.cantidad}} <span class="kpi-sub">· {{summary.anuladas}} anuladas</span></div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="kpi-body"><q-avatar icon="payments" color="green-1" text-color="positive" size="26px" font-size="15px"/><div class="q-ml-sm ellipsis"><div class="kpi-label">Importe válido</div><div class="kpi-value">Bs {{money(summary.importe_total)}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="kpi-body"><q-avatar icon="check_circle" color="green-1" text-color="positive" size="26px" font-size="15px"/><div class="q-ml-sm ellipsis"><div class="kpi-label">En el sistema</div><div class="kpi-value">{{summary.en_sistema}} <span class="kpi-sub">· débito Bs {{money(summary.debito_fiscal)}}</span></div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card kpi-missing" @click="showMissing"><q-card-section class="kpi-body"><q-avatar icon="report_problem" color="red-1" text-color="negative" size="26px" font-size="15px"/><div class="q-ml-sm ellipsis"><div class="kpi-label">Sin registrar</div><div class="kpi-value text-negative">{{summary.sin_registrar}} <span class="kpi-sub">· Bs {{money(summary.importe_sin_registrar)}} por recuperar</span></div></div></q-card-section><q-tooltip>Facturas que están en Impuestos pero no en el sistema. Clic para verlas.</q-tooltip></q-card>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-xs q-pa-xs items-center filters">
        <q-input v-model="filters.q" dense outlined clearable debounce="400" class="col-12 col-sm search-input" placeholder="Buscar factura, CUF, NIT o razón social" @update:model-value="reload"><template #prepend><q-icon name="search" size="18px"/></template></q-input>
        <div class="col-12 col-sm-auto row no-wrap items-center">
          <q-btn dense flat round size="sm" color="primary" icon="chevron_left" @click="shiftMonth(-1)"><q-tooltip>Mes anterior</q-tooltip></q-btn>
          <q-input v-model="filters.mes" dense outlined type="month" label="Mes" stack-label class="month-input" @update:model-value="reload"/>
          <q-btn dense flat round size="sm" color="primary" icon="chevron_right" @click="shiftMonth(1)"><q-tooltip>Mes siguiente</q-tooltip></q-btn>
          <q-btn dense flat round size="sm" color="primary" icon="today" @click="setMonth(0)"><q-tooltip>Último mes cerrado</q-tooltip></q-btn>
        </div>
        <q-select v-if="tab!=='no_impuestos'" v-model="filters.estado" :options="['VALIDA','ANULADA']" dense options-dense outlined clearable label="Estado" class="col-6 col-sm-2" @update:model-value="reload"/>
      </q-card-section>
      <q-tabs v-model="tab" dense no-caps inline-label align="left" active-color="primary" indicator-color="primary" class="text-grey-8 fact-tabs" @update:model-value="changeTab">
        <q-tab name="todas" icon="receipt_long" :label="`Libro SIAT (${summary.cantidad})`"/>
        <q-tab name="en_sistema" icon="check_circle" :label="`En el sistema (${summary.en_sistema})`"/>
        <q-tab name="sin_registrar" icon="report_problem" class="text-negative" :label="`En Impuestos, no en el sistema (${summary.sin_registrar})`"><q-tooltip>Facturas del libro del SIAT cuyo CUF no existe en ninguna venta</q-tooltip></q-tab>
        <q-tab name="no_impuestos" icon="cloud_off" class="text-deep-orange" :label="`En el sistema, no en Impuestos (${summary.no_en_impuestos})`"><q-tooltip>Ventas con CUF que no aparecen en el libro del SIAT importado para este mes</q-tooltip></q-tab>
      </q-tabs>
      <q-separator/>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[20,50,100,200]" class="compact-table" @request="onRequest">
        <template #body-cell-fecha="p"><q-td :props="p">{{formatDate(p.row.fecha_factura)}}</q-td></template>
        <template #body-cell-cuf="p"><q-td :props="p"><span class="cuf-cell">{{p.row.cuf}}</span><q-tooltip>{{p.row.cuf}}</q-tooltip></q-td></template>
        <template #body-cell-razon_social="p"><q-td :props="p"><div class="ellipsis razon-cell">{{p.row.razon_social}}</div><q-tooltip>{{p.row.razon_social}}</q-tooltip></q-td></template>
        <template #body-cell-total="p"><q-td :props="p" class="text-right text-weight-bold">Bs {{money(p.row.importe_total)}}</q-td></template>
        <template #body-cell-debito="p"><q-td :props="p" class="text-right">Bs {{money(p.row.debito_fiscal)}}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.row.estado==='VALIDA'?'positive':'grey-6'" :label="p.row.estado"/></q-td></template>
        <template #body-cell-en_sistema="p"><q-td :props="p">
          <q-badge v-if="p.row.venta" color="positive" :label="p.row.venta.numero"><q-tooltip>Registrada como venta {{p.row.venta.numero}} · {{p.row.venta.estado}}</q-tooltip></q-badge>
          <q-badge v-else color="negative" label="Sin registrar"><q-tooltip>El CUF está en Impuestos pero no existe ninguna venta con ese código</q-tooltip></q-badge>
        </q-td></template>
        <template #body-cell-v_fecha="p"><q-td :props="p">{{formatDate(p.row.fecha_emision_siat||p.row.fecha)}}</q-td></template>
        <template #body-cell-v_cliente="p"><q-td :props="p"><div class="ellipsis razon-cell">{{p.row.cliente_nombre||'Sin nombre'}}</div><q-tooltip v-if="p.row.cliente_nombre">{{p.row.cliente_nombre}}</q-tooltip></q-td></template>
        <template #body-cell-v_total="p"><q-td :props="p" class="text-right text-weight-bold">Bs {{money(p.row.total)}}</q-td></template>
        <template #body-cell-v_estado="p"><q-td :props="p"><q-badge :color="p.row.estado==='COMPLETADA'?'positive':'grey-6'" :label="p.row.estado"/></q-td></template>
        <template #body-cell-v_siat="p"><q-td :props="p"><q-badge :color="siatColor(p.row.estado_siat)" :label="p.row.estado_siat||'—'"/><q-tooltip v-if="p.row.siat_mensaje">{{p.row.siat_mensaje}}</q-tooltip></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p">
          <q-btn dense flat round size="sm" color="primary" icon="visibility" @click="viewInSiat(p.row)"><q-tooltip>Ver la factura en Impuestos</q-tooltip></q-btn>
          <template v-if="tab!=='no_impuestos'">
            <q-btn dense flat round size="sm" color="blue-grey" icon="info" @click="openDetail(p.row)"><q-tooltip>Ver detalle del libro</q-tooltip></q-btn>
            <q-btn v-if="can('Eliminar Facturación')" dense flat round size="sm" color="negative" icon="delete" @click="remove(p.row)"><q-tooltip>Eliminar registro</q-tooltip></q-btn>
          </template>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-lg"><q-icon name="inbox" size="36px"/><div>{{tab==='no_impuestos'?`Todas las ventas con CUF de ${monthLabel(filters.mes)} están en el libro del SIAT`:`No hay facturas en ${monthLabel(filters.mes)}`}}</div></div></template>
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
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
import { openSiatBookInvoice, openSiatInvoice } from '../../addons/ventaPrint'
const {proxy}=getCurrentInstance()
const rows=ref([]),loading=ref(false),importDialog=ref(false),detailDialog=ref(false),importing=ref(false),file=ref(null),result=ref(null)
const detail=reactive({})
const summary=reactive({cantidad:0,validas:0,anuladas:0,importe_total:0,debito_fiscal:0,en_sistema:0,sin_registrar:0,importe_sin_registrar:0,no_en_impuestos:0,meses:[]})
// El SIAT publica el libro del mes ya cerrado: en septiembre lo que interesa es agosto.
const monthOf=offset=>{const d=new Date();d.setDate(1);d.setMonth(d.getMonth()+offset-1);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`}
const filters=reactive({q:'',mes:monthOf(0),estado:null}),tab=ref('todas')
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0})
const can=p=>proxy.$store.hasPermission(p),money=v=>Number(v||0).toFixed(2)
const formatDate=value=>value?new Date(String(value).slice(0,10)+'T00:00:00').toLocaleDateString('es-BO'):''
const monthLabel=mes=>mes?new Date(mes+'-01T00:00:00').toLocaleDateString('es-BO',{month:'long',year:'numeric'}):'el mes seleccionado'
const columns=computed(()=>tab.value==='no_impuestos'?missingColumns:bookColumns)
const bookColumns=[
  {name:'actions',label:'',align:'left'},
  {name:'fecha',label:'Fecha',field:'fecha_factura',align:'left'},
  {name:'numero_factura',label:'Nº factura',field:'numero_factura',align:'left'},
  {name:'cuf',label:'CUF',field:'cuf',align:'left',classes:'wide-only',headerClasses:'wide-only'},
  {name:'nit_ci_cliente',label:'NIT / CI',field:'nit_ci_cliente',align:'left'},
  {name:'razon_social',label:'Razón social',field:'razon_social',align:'left'},
  {name:'total',label:'Importe',field:'importe_total',align:'right'},
  {name:'debito',label:'Débito',field:'debito_fiscal',align:'right',classes:'wide-only',headerClasses:'wide-only'},
  {name:'estado',label:'Estado',field:'estado',align:'center'},
  {name:'en_sistema',label:'Sistema',field:'venta',align:'center'}
]
// Ventas con CUF que el libro del SIAT no tiene: vienen de otro endpoint y con otras columnas.
const missingColumns=[
  {name:'actions',label:'',align:'left'},
  {name:'v_fecha',label:'Fecha',field:'fecha',align:'left'},
  {name:'numero',label:'Nº venta',field:'numero',align:'left'},
  {name:'cuf',label:'CUF',field:'cuf',align:'left'},
  {name:'numero_documento',label:'NIT / CI',field:r=>`${r.numero_documento||''}${r.complemento?'-'+r.complemento:''}`,align:'left'},
  {name:'v_cliente',label:'Cliente',field:'cliente_nombre',align:'left'},
  {name:'v_total',label:'Total',field:'total',align:'right'},
  {name:'v_estado',label:'Venta',field:'estado',align:'center'},
  {name:'v_siat',label:'Estado SIAT',field:'estado_siat',align:'center'}
]
const siatColor=s=>s==='VALIDADA'?'positive':s==='ANULADA'?'grey-7':s==='OBSERVADA'?'negative':'warning'
const TAB_FILTER={todas:'',en_sistema:'si',sin_registrar:'no'}
const params=()=>({q:filters.q||'',mes:filters.mes||'',estado:filters.estado||'',en_sistema:TAB_FILTER[tab.value]||'',page:pagination.value.page,per_page:pagination.value.rowsPerPage})

async function load(){
  loading.value=true
  try{
    const {data}=await proxy.$axios.get(tab.value==='no_impuestos'?'/facturacion-faltantes':'/facturacion',{params:params()})
    rows.value=data.data;pagination.value.rowsNumber=data.total||0;pagination.value.page=data.current_page||1
    Object.assign(summary,(await proxy.$axios.get('/facturacion-resumen',{params:params()})).data)
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo cargar la facturación')}
  finally{loading.value=false}
}
function reload(){pagination.value.page=1;load()}
function setMonth(offset){filters.mes=monthOf(offset);reload()}
// Mueve el mes elegido (no el actual) para poder recorrer meses seguidos.
function shiftMonth(step){const [y,m]=(filters.mes||monthOf(0)).split('-').map(Number),d=new Date(y,m-1+step,1);filters.mes=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;reload()}
function showMissing(){tab.value='sin_registrar';changeTab()}
function changeTab(){rows.value=[];reload()}
// Las filas del libro traen su número de factura; las ventas faltantes usan el mismo número que su ticket.
function viewInSiat(row){try{if(tab.value==='no_impuestos')openSiatInvoice(row,'2');else openSiatBookInvoice(row)}catch(e){proxy.$alert.error(e.message)}}
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
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px}.kpi-card{border-radius:8px}
.kpi-body{display:flex;align-items:center;flex-wrap:nowrap;padding:4px 8px}
.kpi-label{font-size:11px;line-height:1.1;color:#757575}.kpi-value{font-size:16px;font-weight:700;line-height:1.25}
.kpi-sub{font-size:11px;font-weight:400;color:#9e9e9e}
.kpi-missing{cursor:pointer}
.month-input{width:180px}
.razon-cell{max-width:230px}
.fact-tabs :deep(.q-tab){min-height:32px;padding:0 10px}.fact-tabs :deep(.q-tab__label){font-size:12.5px}.fact-tabs :deep(.q-tab__icon){font-size:17px}
.search-input{min-width:240px}
.cuf-cell{display:inline-block;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom}
.compact-table :deep(th),.compact-table :deep(td){padding:2px 6px;height:auto}
.compact-table :deep(th:first-child),.compact-table :deep(td:first-child){padding-left:4px}
.compact-table :deep(thead tr){height:30px}.compact-table :deep(tbody tr){height:28px}
.filters :deep(.q-field--dense .q-field__control),.filters :deep(.q-field--dense .q-field__marginal){height:34px;min-height:34px}
@media (max-width:1200px){.compact-table :deep(.wide-only){display:none}.razon-cell{max-width:190px}}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
