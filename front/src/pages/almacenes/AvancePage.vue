<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div>
        <div class="text-subtitle1 text-weight-bold">Avance de la revisión {{almacen.numero}}
          <q-badge :color="stateColor" :label="almacen.estado==='BORRADOR'?'EN REVISIÓN':almacen.estado" class="q-ml-xs"/>
        </div>
        <div class="text-caption text-grey-7">
          <template v-if="almacen.estado==='APLICADO'">Aplicado por {{almacen.aplicado_por_nombre||'—'}} · {{formatDate(almacen.fecha_aplicado)}}</template>
          <template v-else>Compara lo contado con el stock del sistema antes de actualizar los productos</template>
        </div>
      </div>
      <q-space/>
      <q-btn v-if="editable" dense flat icon="fact_check" label="Seguir llenando" no-caps class="q-mr-xs" :to="`/almacenes/${id}`"/>
      <q-btn dense flat round icon="refresh" :loading="loading" class="q-mr-xs" @click="load()"><q-tooltip>Actualizar</q-tooltip></q-btn>
      <q-btn v-if="editable&&can('Aplicar Almacenes')" dense unelevated color="positive" icon="published_with_changes" label="Actualizar productos" no-caps :loading="applying" :disable="!data.revisados" @click="apply"/>
    </div>

    <div class="kpi-row q-mb-sm">
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm">
        <div class="text-caption text-grey-7">Productos revisados</div>
        <div class="text-h6 text-weight-bold">{{data.revisados}} <span class="text-caption text-grey-6">de {{data.total_productos}}</span></div>
        <q-linear-progress :value="progress" color="primary" track-color="grey-3" size="6px" rounded class="q-mt-xs"/>
      </q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="check_circle" color="green-1" text-color="positive" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Cuadran</div><div class="text-h6 text-weight-bold">{{data.sin_diferencia}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="report_problem" color="orange-1" text-color="orange-9" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Con diferencia</div><div class="text-h6 text-weight-bold">{{data.con_diferencia}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="payments" color="purple-1" text-color="purple-9" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Valor de la diferencia</div><div class="text-h6 text-weight-bold" :class="data.diferencia_valor<0?'text-negative':''">Bs {{money(data.diferencia_valor)}}</div></div></q-card-section></q-card>
    </div>

    <q-card v-if="data.por_usuario?.length" flat bordered class="q-mb-sm">
      <q-card-section class="q-pa-sm row items-center">
        <div class="text-caption text-grey-7 q-mr-sm">Quién contó:</div>
        <div class="row q-gutter-xs"><q-badge v-for="u in data.por_usuario" :key="u.usuario" outline color="primary" :label="`${u.usuario}: ${u.productos}`"/></div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-card-section class="row items-center q-py-sm">
        <q-icon name="difference" color="primary" size="20px" class="q-mr-xs"/><b>Detalle de la revisión</b>
        <q-space/>
        <q-checkbox v-model="onlyDifferences" dense size="sm" label="Sólo diferencias" class="text-caption"/>
      </q-card-section>
      <q-separator/>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:0}" hide-pagination>
        <template #body-cell-producto="p"><q-td :props="p"><b>{{p.row.nombre}}</b><div class="text-caption text-grey-7">{{p.row.codigo}} · {{p.row.unidad}}</div></q-td></template>
        <template #body-cell-sistema="p"><q-td :props="p" class="text-right">{{qty(p.row.stock_actual,p.row.unidad)}}</q-td></template>
        <template #body-cell-contado="p"><q-td :props="p" class="text-right text-weight-bold">{{qty(p.row.cantidad,p.row.unidad)}}</q-td></template>
        <template #body-cell-diferencia="p"><q-td :props="p" class="text-right"><q-badge :color="diffColor(p.row)" :label="diffLabel(p.row)"/></q-td></template>
        <template #body-cell-lote="p"><q-td :props="p">
          <div v-if="p.row.conteos?.length" class="lot-cell">
            <q-badge v-for="lot in p.row.conteos" :key="lot.id" outline color="deep-orange"
                     :label="`${lot.lote||'sin lote'} · ${qty(lot.cantidad,p.row.unidad)}${lot.fecha_vencimiento?' · vence '+shortDate(lot.fecha_vencimiento):''}`"/>
          </div>
          <span v-else class="text-grey-6">—</span>
        </q-td></template>
        <template #body-cell-resultado="p"><q-td :props="p" class="text-right">
          <span v-if="p.row.stock_nuevo!==null&&p.row.stock_nuevo!==undefined" class="text-caption"><span class="text-grey-6">{{qty(p.row.stock_anterior,p.row.unidad)}}</span> → <b>{{qty(p.row.stock_nuevo,p.row.unidad)}}</b></span>
          <span v-else class="text-caption text-grey-6">quedará en {{qty(p.row.cantidad,p.row.unidad)}}</span>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="inventory" size="42px"/><div>{{onlyDifferences?'No hay diferencias':'Todavía no se contó ningún producto'}}</div></div></template>
      </q-table>
    </q-card>

    <q-dialog v-model="confirmDialog" :maximized="$q.screen.lt.sm">
      <q-card class="column no-wrap confirm-card">
        <q-card-section class="row items-center q-pa-sm confirm-head">
          <q-avatar color="white" text-color="positive" icon="published_with_changes" size="36px"/>
          <div class="q-ml-sm">
            <div class="text-subtitle1 text-weight-bold">Actualizar productos con la revisión {{almacen.numero}}</div>
            <div class="text-caption">Revisa las diferencias antes de que el stock pase a ser lo contado</div>
          </div>
          <q-space/><q-btn flat round dense icon="close" color="white" v-close-popup/>
        </q-card-section>

        <q-card-section class="q-pa-sm">
          <div class="confirm-kpis">
            <div class="confirm-kpi"><div class="confirm-kpi__label">Productos revisados</div><div class="confirm-kpi__value">{{data.revisados}}</div></div>
            <div class="confirm-kpi confirm-kpi--up"><div class="confirm-kpi__label">Sobrantes</div><div class="confirm-kpi__value">+{{fmt(surplus.total)}}<span class="confirm-kpi__hint">{{surplus.count}} producto{{surplus.count===1?'':'s'}}</span></div></div>
            <div class="confirm-kpi confirm-kpi--down"><div class="confirm-kpi__label">Faltantes</div><div class="confirm-kpi__value">−{{fmt(shortage.total)}}<span class="confirm-kpi__hint">{{shortage.count}} producto{{shortage.count===1?'':'s'}}</span></div></div>
            <div class="confirm-kpi"><div class="confirm-kpi__label">Cuadran</div><div class="confirm-kpi__value">{{data.sin_diferencia}}</div></div>
            <div class="confirm-kpi" :class="data.diferencia_valor<0?'confirm-kpi--down':'confirm-kpi--up'"><div class="confirm-kpi__label">Valor neto</div><div class="confirm-kpi__value">Bs {{money(data.diferencia_valor)}}</div></div>
          </div>
        </q-card-section>

        <q-separator/>
        <q-card-section class="row items-center q-py-xs">
          <q-icon name="difference" size="18px" color="deep-orange" class="q-mr-xs"/>
          <b class="text-caption">{{confirmAll?'Todos los productos revisados':'Productos con diferencia'}} ({{confirmRows.length}})</b>
          <q-space/>
          <q-toggle v-model="confirmAll" dense size="sm" color="primary" label="Ver también los que cuadran" class="text-caption"/>
        </q-card-section>
        <q-separator/>

        <div class="col scroll confirm-table">
          <q-markup-table dense flat separator="horizontal">
            <thead><tr>
              <th class="text-left">Producto</th><th class="text-right">Sistema</th><th class="text-right">Contado</th>
              <th class="text-right">Diferencia</th><th class="text-right">Quedará en</th><th class="text-right">Valor Bs</th>
            </tr></thead>
            <tbody>
              <tr v-for="row in confirmRows" :key="row.id" :class="rowClass(row)">
                <td>
                  <div class="text-weight-medium">{{row.nombre}}</div>
                  <div class="text-caption text-grey-7">{{row.codigo}} · contó {{row.usuario_nombre||'—'}}</div>
                  <div v-if="row.conteos?.length" class="text-caption text-deep-orange-9">{{lotSummary(row)}}</div>
                </td>
                <td class="text-right">{{qty(row.stock_actual,row.unidad)}}</td>
                <td class="text-right text-weight-bold">{{qty(row.cantidad,row.unidad)}}</td>
                <td class="text-right"><q-badge :color="diffColor(row)" :label="diffLabel(row)"/></td>
                <td class="text-right"><span class="text-grey-6">{{qty(row.stock_actual,row.unidad)}}</span> → <b>{{qty(row.cantidad,row.unidad)}}</b></td>
                <td class="text-right" :class="rowValue(row)<0?'text-negative':rowValue(row)>0?'text-positive':'text-grey-6'">{{money(rowValue(row))}}</td>
              </tr>
              <tr v-if="!confirmRows.length"><td colspan="6" class="text-center text-grey-6 q-py-lg">Todo cuadra con el sistema</td></tr>
            </tbody>
          </q-markup-table>
        </div>

        <q-separator/>
        <q-card-section class="q-py-xs bg-orange-1 text-orange-10 text-caption row items-center">
          <q-icon name="info" size="16px" class="q-mr-xs"/>
          Los {{Math.max(0,data.total_productos-data.revisados)}} productos que nadie contó no se modifican. Se guardará el valor anterior de cada producto para poder anular la revisión.
        </q-card-section>
        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense label="Cancelar" no-caps v-close-popup/>
          <q-btn dense unelevated color="positive" icon="published_with_changes" label="Sí, actualizar productos" no-caps :loading="applying" @click="runApply"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
const {proxy}=getCurrentInstance(),route=useRoute(),router=useRouter()
const id=Number(route.params.id)
const almacen=reactive({numero:'',estado:'BORRADOR',descripcion:'',aplicado_por_nombre:null,fecha_aplicado:null})
const data=reactive({detalles:[],total_productos:0,revisados:0,con_diferencia:0,sin_diferencia:0,diferencia_valor:0,por_usuario:[]})
const loading=ref(false),applying=ref(false),onlyDifferences=ref(false),confirmDialog=ref(false),confirmAll=ref(false)
let refreshTimer=null
const can=p=>proxy.$store.hasPermission(p),money=v=>Number(v||0).toFixed(2)
const qty=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const formatDate=value=>value?new Date(value).toLocaleString('es-BO'):''
const shortDate=value=>value?new Date(`${String(value).slice(0,10)}T12:00:00`).toLocaleDateString('es-BO'):''
const editable=computed(()=>almacen.estado==='BORRADOR')
const stateColor=computed(()=>almacen.estado==='APLICADO'?'positive':almacen.estado==='ANULADO'?'grey-6':'orange')
const progress=computed(()=>data.total_productos?Math.min(1,data.revisados/data.total_productos):0)
const diff=row=>Number(row.diferencia_actual||0)
const diffLabel=row=>`${diff(row)>0?'+':''}${diff(row).toFixed(row.unidad==='KG'?3:0)}`
const diffColor=row=>Math.abs(diff(row))<0.0005?'grey-6':diff(row)>0?'positive':'negative'
const rows=computed(()=>onlyDifferences.value?data.detalles.filter(d=>Math.abs(diff(d))>0.0005):data.detalles)
// Resumen del diálogo de confirmación: sobrantes, faltantes y valor de cada línea.
const differences=computed(()=>data.detalles.filter(d=>Math.abs(diff(d))>0.0005))
const confirmRows=computed(()=>confirmAll.value?data.detalles:differences.value)
const surplus=computed(()=>{const list=differences.value.filter(d=>diff(d)>0);return {count:list.length,total:list.reduce((s,d)=>s+diff(d),0)}})
const shortage=computed(()=>{const list=differences.value.filter(d=>diff(d)<0);return {count:list.length,total:Math.abs(list.reduce((s,d)=>s+diff(d),0))}})
const fmt=value=>Number(value||0).toFixed(3).replace(/\.?0+$/,'')||'0'
const rowValue=row=>Number((diff(row)*Number(row.precio_compra||0)).toFixed(2))
const rowClass=row=>Math.abs(diff(row))<0.0005?'':diff(row)>0?'row-up':'row-down'
const lotSummary=row=>(row.conteos||[]).map(l=>`${l.lote||'sin lote'}: ${qty(l.cantidad,row.unidad)}${l.fecha_vencimiento?` (vence ${shortDate(l.fecha_vencimiento)})`:''}`).join(' · ')
const columns=[
  {name:'producto',label:'Producto',field:'nombre',align:'left'},
  {name:'usuario',label:'Contó',field:r=>r.usuario_nombre||'—',align:'left'},
  {name:'sistema',label:'Sistema',field:'stock_actual',align:'right'},
  {name:'contado',label:'Contado',field:'cantidad',align:'right'},
  {name:'diferencia',label:'Diferencia',field:'diferencia_actual',align:'right'},
  {name:'lote',label:'Lote / vencimiento',field:'lote',align:'left'},
  {name:'resultado',label:'Stock resultante',field:'stock_nuevo',align:'right'}
]

async function load(){
  loading.value=true
  try{
    const response=(await proxy.$axios.get(`/almacenes/${id}/avance`)).data
    Object.assign(almacen,response.almacen)
    Object.assign(data,{...response,detalles:response.detalles||[]})
  }catch(e){
    proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el avance')
    if(e.response?.status===404)router.replace('/almacenes')
  }finally{loading.value=false}
}
// Botón "Actualizar productos": primero se revisan las diferencias en el diálogo.
function apply(){confirmAll.value=false;confirmDialog.value=true}
async function runApply(){
  applying.value=true
  try{
    await proxy.$axios.post(`/almacenes/${id}/aplicar`)
    confirmDialog.value=false
    proxy.$alert.success('Productos actualizados con la revisión')
    await load()
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo actualizar los productos')}
  finally{applying.value=false}
}
onMounted(()=>{
  load()
  refreshTimer=setInterval(()=>{if(!document.hidden&&editable.value&&!applying.value)load()},15000)
})
onBeforeUnmount(()=>clearInterval(refreshTimer))
</script>

<style scoped>
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.kpi-card{border-radius:10px}
.lot-cell{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
.confirm-card{width:900px;max-width:96vw;max-height:92vh}
.confirm-head{background:linear-gradient(135deg,#21ba45,#0f8a30);color:#fff}
.confirm-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}
.confirm-kpi{border:1px solid #e0e6ea;border-radius:10px;padding:6px 8px;background:#fafcfd}
.confirm-kpi--up{border-color:#c8e6c9;background:#f4fbf5}.confirm-kpi--down{border-color:#ffcdd2;background:#fff6f6}
.confirm-kpi__label{font-size:10px;color:#607d8b;line-height:13px}
.confirm-kpi__value{font-size:17px;font-weight:800;line-height:21px;color:#263238}
.confirm-kpi--up .confirm-kpi__value{color:#1b5e20}.confirm-kpi--down .confirm-kpi__value{color:#c62828}
.confirm-kpi__hint{display:block;font-size:10px;font-weight:500;color:#78909c;line-height:12px}
.confirm-table{min-height:160px}
.confirm-table tr.row-up td{background:#f4fbf5}.confirm-table tr.row-down td{background:#fff6f6}
@media(max-width:700px){.confirm-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

