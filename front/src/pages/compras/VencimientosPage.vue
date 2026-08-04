<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-subtitle1 text-weight-bold">{{expired?'Productos vencidos':'Productos por vencer'}}</div><div class="text-caption text-grey-7">{{expired?'Lotes con fecha vencida · de compras y de revisiones de almacén':'Lotes próximos a su fecha de vencimiento · de compras y de revisiones de almacén'}}</div></div><q-space/><q-input v-if="!expired" v-model.number="days" dense outlined type="number" min="1" max="365" label="Próximos días" style="width:145px" @update:model-value="load"/></div>

    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row items-center q-py-sm"><q-avatar icon="shopping_bag" color="orange-1" text-color="orange-9" size="38px"/><div class="q-ml-sm"><div class="text-subtitle2 text-weight-bold">Lotes de compras</div><div class="text-caption text-grey-7">Ingresados al registrar una compra</div></div><q-space/><q-badge color="orange" :label="`${comprasRows.length} lotes`"/></q-card-section>
      <q-separator/>
      <q-table flat dense :rows="comprasRows" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:0}" hide-pagination>
        <template #body-cell-producto="p"><q-td :props="p"><b>{{p.row.producto?.nombre}}</b><div class="text-caption">{{p.row.producto?.codigo}}</div></q-td></template>
        <template #body-cell-documento="p"><q-td :props="p"><template v-if="p.row.documento"><b>{{p.row.documento}}</b><div class="text-caption text-grey-7">{{documentDetail(p.row)}}</div></template><span v-else class="text-grey-5">—</span></q-td></template>
        <template #body-cell-cantidad="p"><q-td :props="p">{{qty(p.value,p.row.producto?.unidad)}} {{p.row.producto?.unidad}}</q-td></template>
        <template #body-cell-fecha="p"><q-td :props="p">{{date(p.value)}}</q-td></template>
        <template #body-cell-dias="p"><q-td :props="p"><q-badge :color="p.value<0?'negative':p.value<=7?'orange':'warning'" :label="p.value<0?`${Math.abs(p.value)} días vencido`:`${p.value} días`"/></q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="fact_check" size="42px"/><div>Sin lotes de compras en este rango</div></div></template>
      </q-table>
    </q-card>

    <q-card flat bordered>
      <q-card-section class="row items-center q-py-sm"><q-avatar icon="warehouse" color="red-1" text-color="red-9" size="38px"/><div class="q-ml-sm"><div class="text-subtitle2 text-weight-bold">Lotes de revisiones de almacén</div><div class="text-caption text-grey-7">Cargados al contar el stock físico de la tienda</div></div><q-space/><q-badge color="red" :label="`${almacenRows.length} lotes`"/></q-card-section>
      <q-separator/>
      <q-table flat dense :rows="almacenRows" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:0}" hide-pagination>
        <template #body-cell-producto="p"><q-td :props="p"><b>{{p.row.producto?.nombre}}</b><div class="text-caption">{{p.row.producto?.codigo}}</div></q-td></template>
        <template #body-cell-documento="p"><q-td :props="p"><template v-if="p.row.documento"><b>{{p.row.documento}}</b><div class="text-caption text-grey-7">{{documentDetail(p.row)}}</div></template><span v-else class="text-grey-5">—</span></q-td></template>
        <template #body-cell-cantidad="p"><q-td :props="p">{{qty(p.value,p.row.producto?.unidad)}} {{p.row.producto?.unidad}}</q-td></template>
        <template #body-cell-fecha="p"><q-td :props="p">{{date(p.value)}}</q-td></template>
        <template #body-cell-dias="p"><q-td :props="p"><q-badge :color="p.value<0?'negative':p.value<=7?'orange':'warning'" :label="p.value<0?`${Math.abs(p.value)} días vencido`:`${p.value} días`"/></q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="fact_check" size="42px"/><div>Sin lotes de revisiones en este rango</div></div></template>
      </q-table>
    </q-card>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue'
const props=defineProps({estado:{type:String,default:'por_vencer'}}),{proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),days=ref(30),expired=computed(()=>props.estado==='vencido')
const comprasRows=computed(()=>rows.value.filter(r=>r.origen!=='ALMACEN')),almacenRows=computed(()=>rows.value.filter(r=>r.origen==='ALMACEN'))
const columns=[{name:'producto',label:'Producto',field:'producto',align:'left'},{name:'lote',label:'Lote',field:'lote',align:'left'},{name:'documento',label:'Documento',field:'documento',align:'left'},{name:'cantidad',label:'Disponible',field:'cantidad_disponible',align:'right'},{name:'fecha',label:'Vencimiento',field:'fecha_vencimiento',align:'center'},{name:'dias',label:'Estado',field:'dias_vencimiento',align:'center'}]
const qty=(v,u)=>Number(v||0).toFixed(u==='KG'?3:0),date=v=>new Date(`${String(v).slice(0,10)}T12:00:00`).toLocaleDateString('es-BO')
const documentDetail=row=>[row.documento_detalle,row.documento_fecha?date(row.documento_fecha):null].filter(Boolean).join(' · ')
function load(){loading.value=true;proxy.$axios.get('/vencimientos',{params:{estado:props.estado,dias:days.value}}).then(r=>rows.value=r.data).finally(()=>loading.value=false)}
watch(()=>props.estado,load);onMounted(load)
</script>
