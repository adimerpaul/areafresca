<template>
  <q-btn dense flat color="green-8" icon="table_view" :label="label" no-caps @click="open">
    <q-tooltip>Exportar a Excel eligiendo nombre y horario</q-tooltip>
  </q-btn>

  <q-dialog v-model="dialog">
    <q-card style="width:560px;max-width:95vw">
      <q-form @submit.prevent="download">
        <q-card-section class="row items-center q-py-sm export-head">
          <q-avatar color="white" text-color="green-9" icon="table_view" size="36px"/>
          <div class="q-ml-sm col">
            <div class="text-subtitle1 text-weight-bold">Exportar a Excel</div>
            <div class="text-caption">{{subtitulo}}</div>
          </div>
          <q-btn flat round dense icon="close" color="white" v-close-popup/>
        </q-card-section>

        <q-card-section class="row q-col-gutter-sm q-pa-md">
          <q-input v-model="filters.nombre" outlined dense clearable class="col-12" :label="revision?'Nombre o código del producto':'Nombre, número, usuario o descripción'">
            <template #prepend><q-icon name="search"/></template>
          </q-input>
          <q-input v-if="revision" v-model="filters.usuario" outlined dense clearable label="Contó (usuario)" class="col-12 col-sm-6">
            <template #prepend><q-icon name="person"/></template>
          </q-input>
          <q-select v-else v-model="filters.estado" :options="stateOptions" emit-value map-options outlined dense clearable label="Estado" class="col-12 col-sm-6"/>
          <q-toggle v-if="revision" v-model="filters.solo_diferencias" dense color="deep-orange" label="Sólo con diferencia" class="col-12 col-sm-6 text-caption"/>
          <div v-else class="col-12 col-sm-6"/>

          <div class="col-12 text-caption text-weight-bold text-grey-8 q-mt-sm">Fecha de creación</div>
          <q-input v-model="filters.desde" outlined dense clearable type="date" stack-label label="Desde" class="col-6"/>
          <q-input v-model="filters.hasta" outlined dense clearable type="date" stack-label label="Hasta" class="col-6"/>

          <div class="col-12 text-caption text-weight-bold text-grey-8 q-mt-sm">Horario de creación</div>
          <q-input v-model="filters.hora_desde" outlined dense clearable type="time" stack-label label="Desde las" class="col-6"/>
          <q-input v-model="filters.hora_hasta" outlined dense clearable type="time" stack-label label="Hasta las" class="col-6"/>
          <div class="col-12 row q-gutter-xs">
            <q-chip v-for="turno in turnos" :key="turno.label" clickable dense outline color="primary" size="sm"
                    :label="turno.label" @click="aplicarTurno(turno)"/>
          </div>

          <div class="col-12 text-caption text-grey-7 q-mt-xs">
            <q-icon name="info" size="14px" class="q-mr-xs"/>{{resumenFiltros}}
          </div>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense label="Limpiar filtros" no-caps color="grey-8" @click="limpiar"/>
          <q-space/>
          <q-btn flat dense label="Cancelar" no-caps v-close-popup/>
          <q-btn type="submit" dense unelevated color="green-8" icon="download" label="Descargar Excel" no-caps :loading="loading"/>
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, getCurrentInstance, reactive, ref } from 'vue'
const props = defineProps({
  endpoint: {type: String, required: true},
  filename: {type: String, default: 'reporte'},
  // 'listado' usa los filtros de la lista de almacenes; 'revision' los de las líneas contadas.
  mode: {type: String, default: 'listado'},
  label: {type: String, default: 'Excel'},
  subtitulo: {type: String, default: 'Elige qué incluir en el reporte'},
  preset: {type: Object, default: () => ({})}
})
const {proxy}=getCurrentInstance()
const dialog=ref(false),loading=ref(false)
const revision=computed(()=>props.mode==='revision')
const filters=reactive({nombre:'',usuario:'',estado:null,desde:'',hasta:'',hora_desde:'',hora_hasta:'',solo_diferencias:false})
const stateOptions=[{label:'EN REVISIÓN',value:'BORRADOR'},{label:'APLICADO',value:'APLICADO'},{label:'ANULADO',value:'ANULADO'}]
const turnos=[{label:'Todo el día',desde:'',hasta:''},{label:'Mañana 06:00–12:00',desde:'06:00',hasta:'12:00'},
  {label:'Tarde 12:00–18:00',desde:'12:00',hasta:'18:00'},{label:'Noche 18:00–23:59',desde:'18:00',hasta:'23:59'}]
const resumenFiltros=computed(()=>{
  const partes=[]
  if(filters.nombre)partes.push(`nombre "${filters.nombre}"`)
  if(filters.usuario)partes.push(`cargado por "${filters.usuario}"`)
  if(filters.estado)partes.push(`estado ${filters.estado==='BORRADOR'?'EN REVISIÓN':filters.estado}`)
  if(filters.desde||filters.hasta)partes.push(`${filters.desde?`desde ${filters.desde}`:''}${filters.desde&&filters.hasta?' ':''}${filters.hasta?`hasta ${filters.hasta}`:''}`)
  if(filters.hora_desde||filters.hora_hasta)partes.push(`horario ${filters.hora_desde||'00:00'} a ${filters.hora_hasta||'23:59'}`)
  if(filters.solo_diferencias)partes.push('sólo productos con diferencia')
  return partes.length?`Se exportará: ${partes.join(' · ')}`:'Se exportará todo, sin filtros'
})
function limpiar(){Object.assign(filters,{nombre:'',usuario:'',estado:null,desde:'',hasta:'',hora_desde:'',hora_hasta:'',solo_diferencias:false})}
function aplicarTurno(turno){filters.hora_desde=turno.desde;filters.hora_hasta=turno.hasta}
function open(){limpiar();Object.assign(filters,props.preset||{});dialog.value=true}
// El backend espera `q` en el listado y `nombre` en el detalle de una revisión.
function params(){
  const base={desde:filters.desde||'',hasta:filters.hasta||'',hora_desde:filters.hora_desde||'',hora_hasta:filters.hora_hasta||''}
  return revision.value
    ? {...base,nombre:filters.nombre||'',usuario:filters.usuario||'',solo_diferencias:filters.solo_diferencias?1:0}
    : {...base,q:filters.nombre||'',estado:filters.estado||''}
}
function stamp(){const d=new Date(),p=n=>String(n).padStart(2,'0');return `${d.getFullYear()}${p(d.getMonth()+1)}${p(d.getDate())}_${p(d.getHours())}${p(d.getMinutes())}`}
async function download(){
  loading.value=true
  try{
    const response=await proxy.$axios.get(props.endpoint,{params:params(),responseType:'blob'})
    const url=URL.createObjectURL(response.data),a=document.createElement('a')
    a.href=url;a.download=`${props.filename}_${stamp()}.xlsx`;a.click();URL.revokeObjectURL(url)
    dialog.value=false
    proxy.$alert.success('Reporte descargado')
  }catch(e){proxy.$alert.error(e.response?.status===403?'No tiene permiso para exportar':'No se pudo exportar el reporte')}
  finally{loading.value=false}
}
</script>

<style scoped>
.export-head{background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff}
</style>
