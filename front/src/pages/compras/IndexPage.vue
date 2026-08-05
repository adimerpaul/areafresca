<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Compras</div><div class="text-caption text-grey-7">Historial de ingresos de mercadería</div></div>
      <q-space/><q-btn color="primary" dense unelevated icon="add_business" label="Nueva compra" no-caps to="/compras/nueva"/>
    </div>
    <div class="row q-col-gutter-sm q-mb-sm"><div v-for="card in cards" :key="card.label" class="col-6 col-md-3"><q-card flat bordered :class="`bg-${card.color}-1 text-${card.color}-9`"><q-card-section class="row items-center q-pa-sm"><q-avatar :color="card.color" text-color="white" :icon="card.icon" size="36px"/><div class="q-ml-sm"><div class="text-caption">{{card.label}}</div><div class="text-h6 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div></div></q-card-section></q-card></div></div>
    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm"><q-input v-model="search" dense outlined debounce="300" clearable placeholder="Buscar número, proveedor o factura" class="col-12 col-md-6" @update:model-value="load"><template #prepend><q-icon name="search"/></template></q-input><q-input v-model="from" dense outlined type="date" label="Desde" class="col-6 col-md-3" @update:model-value="load"/><q-input v-model="to" dense outlined type="date" label="Hasta" class="col-6 col-md-3" @update:model-value="load"/></q-card-section>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" @request="request">
        <template #body-cell-fecha="p"><q-td :props="p">{{date(p.value)}}</q-td></template>
        <template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value==='COMPLETADA'?'positive':'negative'" :label="p.value"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none"><q-list dense><q-item clickable v-close-popup @click="show(p.row)"><q-item-section avatar><q-icon name="visibility"/></q-item-section><q-item-section>Ver detalle</q-item-section></q-item><q-item clickable v-close-popup @click="printRow(p.row)"><q-item-section avatar><q-icon name="print"/></q-item-section><q-item-section>Reimprimir</q-item-section></q-item><q-separator/><q-item v-if="p.row.estado==='COMPLETADA'" clickable v-close-popup class="text-negative" @click="cancel(p.row)"><q-item-section avatar><q-icon name="cancel"/></q-item-section><q-item-section>Anular</q-item-section></q-item></q-list></q-btn-dropdown></q-td></template>
      </q-table>
    </q-card>
    <q-dialog v-model="dialog"><q-card style="width:850px;max-width:96vw"><q-card-section class="row items-center"><div><b>{{selected.numero}}</b><div class="text-caption">{{selected.proveedor_nombre}} · {{date(selected.fecha)}} · Factura {{selected.numero_factura||'S/N'}}</div></div><q-space/><q-btn flat round dense icon="print" color="primary" @click="printPurchase(selected)"/><q-badge :color="selected.estado==='COMPLETADA'?'positive':'negative'" :label="selected.estado"/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/>
      <q-table flat dense :rows="selected.detalles||[]" :columns="detailColumns" row-key="id" hide-pagination :rows-per-page-options="[0]"><template #body-cell-vencimiento="p"><q-td :props="p">{{p.value?dateOnly(p.value):'—'}}</q-td></template><template #body-cell-cantidad="p"><q-td :props="p">{{qty(p.value)}} {{p.row.unidad}}</q-td></template><template #body-cell-precio="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template><template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template></q-table>
      <q-separator/><q-card-section><div class="row"><span>{{selected.tipo_pago}} · {{selected.comentario||'Sin comentario'}}</span><q-space/><b class="text-primary">Total Bs {{money(selected.total)}}</b></div><div class="row text-caption q-mt-xs"><span>Efectivo: Bs {{money(selected.monto_efectivo)}}</span><q-space/><span>QR: Bs {{money(selected.monto_qr)}}</span></div></q-card-section>
    </q-card></q-dialog>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
import { printPurchase } from '../../addons/compraPrint'
const {proxy}=getCurrentInstance(),rows=ref([]),search=ref(''),from=ref(''),to=ref(''),loading=ref(false),dialog=ref(false),selected=ref({}),summary=reactive({efectivo:0,qr:0,total:0,cantidad:0})
const pagination=reactive({page:1,rowsPerPage:20,rowsNumber:0})
const cards=computed(()=>[{label:'Total comprado',value:summary.total,money:true,icon:'shopping_bag',color:'primary'},{label:'Efectivo',value:summary.efectivo,money:true,icon:'payments',color:'green'},{label:'QR',value:summary.qr,money:true,icon:'qr_code_2',color:'blue'},{label:'Compras',value:summary.cantidad,money:false,icon:'receipt_long',color:'purple'}])
const columns=[{name:'actions',label:'',field:'id'},{name:'numero',label:'Nº',field:'numero',align:'left'},{name:'fecha',label:'Fecha',field:'fecha',align:'left'},{name:'proveedor',label:'Proveedor',field:'proveedor_nombre',align:'left'},{name:'factura',label:'Factura',field:'numero_factura',align:'left'},{name:'pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'detalles',label:'Ítems',field:'detalles_count',align:'center'},{name:'total',label:'Total',field:'total',align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
const detailColumns=[{name:'producto',label:'Producto',field:'nombre',align:'left'},{name:'lote',label:'Lote',field:'lote'},{name:'vencimiento',label:'Vencimiento',field:'fecha_vencimiento'},{name:'cantidad',label:'Cantidad',field:'cantidad'},{name:'precio',label:'P. unitario',field:'precio_unitario'},{name:'total',label:'Total',field:'total'}]
const money=v=>Number(v||0).toFixed(2),qty=v=>Number(v||0).toFixed(3),date=v=>new Date(v).toLocaleString('es-BO'),dateOnly=v=>new Date(`${String(v).slice(0,10)}T12:00:00`).toLocaleDateString('es-BO')
function load(){loading.value=true;const params={q:search.value,desde:from.value,hasta:to.value,page:pagination.page,per_page:pagination.rowsPerPage};Promise.all([proxy.$axios.get('/compras',{params}),proxy.$axios.get('/compras-resumen',{params})]).then(([r,s])=>{rows.value=r.data.data;pagination.rowsNumber=r.data.total;Object.assign(summary,s.data)}).finally(()=>loading.value=false)}
function request(p){Object.assign(pagination,p.pagination);load()}
function show(row){proxy.$axios.get(`/compras/${row.id}`).then(r=>{selected.value=r.data;dialog.value=true})}
function printRow(row){proxy.$axios.get(`/compras/${row.id}`).then(r=>printPurchase(r.data))}
function cancel(row){proxy.$alert.dialog(`¿Anular la compra ${row.numero}? El inventario será descontado.`).onOk(()=>proxy.$axios.put(`/compras/${row.id}/anular`).then(()=>{proxy.$alert.success('Compra anulada');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular')))}
onMounted(load)
</script>
