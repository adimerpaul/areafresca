<template>
  <q-page class="dashboard q-pa-sm">
    <div class="hero q-mb-sm">
      <div><div class="text-h5 text-weight-bold">Hola, {{ $store.user.name || $store.user.username }}</div><div class="text-body2 hero-subtitle">Así está funcionando Area Fresca</div></div>
      <q-space/><q-btn v-if="$store.hasPermission('Crear Ventas')" unelevated color="white" text-color="primary" icon="point_of_sale" label="Nueva venta" no-caps to="/ventas/nueva"/>
    </div>

    <template v-if="$store.hasPermission('Ver Ventas')">
      <div class="kpi-grid q-mb-sm">
        <q-card v-for="item in kpis" :key="item.label" flat bordered class="kpi-card">
          <q-card-section class="row items-center q-pa-sm">
            <q-avatar :class="`kpi-icon kpi-${item.color}`" :icon="item.icon" size="44px"/>
            <div class="q-ml-sm"><div class="text-caption text-grey-7">{{item.label}}</div><div class="text-h6 text-weight-bold">{{item.money?'Bs ':''}}{{item.money?money(item.value):item.value}}</div><div class="text-caption" :class="`text-${item.color}`">{{item.caption}}</div></div>
          </q-card-section>
        </q-card>
      </div>

      <div class="row q-col-gutter-sm q-mb-sm">
        <div class="col-12 col-lg-8"><q-card flat bordered class="chart-card"><q-card-section class="q-pb-none"><div class="text-subtitle1 text-weight-bold">Ventas de los últimos 7 días</div><div class="text-caption text-grey-6">Ingresos diarios en bolivianos</div></q-card-section><q-card-section class="q-pa-xs"><apexchart type="area" height="285" :options="dailyOptions" :series="dailySeries"/></q-card-section></q-card></div>
        <div class="col-12 col-lg-4"><q-card flat bordered class="chart-card"><q-card-section class="q-pb-none"><div class="text-subtitle1 text-weight-bold">Formas de pago</div><div class="text-caption text-grey-6">Distribución del total vendido</div></q-card-section><q-card-section class="q-pa-xs"><apexchart type="donut" height="285" :options="paymentOptions" :series="paymentSeries"/></q-card-section></q-card></div>
      </div>

      <div class="row q-col-gutter-sm">
        <div class="col-12 col-lg-7"><q-card flat bordered class="chart-card"><q-card-section class="q-pb-none"><div class="text-subtitle1 text-weight-bold">Ventas por usuario</div><div class="text-caption text-grey-6">Total vendido por cada cajero</div></q-card-section><q-card-section class="q-pa-xs"><apexchart type="bar" height="310" :options="userOptions" :series="userSeries"/></q-card-section></q-card></div>
        <div class="col-12 col-lg-5"><q-card flat bordered class="chart-card"><q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">Productos más vendidos</div><div class="text-caption text-grey-6">Por cantidad de unidades</div></div><q-space/><q-btn dense flat icon="inventory_2" color="primary" to="/productos"/></q-card-section><q-separator/>
          <q-list separator class="top-list"><q-item v-for="(product,index) in data.productos_top" :key="product.producto_id" dense>
            <q-item-section avatar><div class="rank">{{index+1}}</div><q-avatar rounded size="42px" color="grey-2"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" color="grey-5"/></q-avatar></q-item-section>
            <q-item-section><q-item-label lines="1" class="text-weight-medium">{{product.nombre}}</q-item-label><q-item-label caption>Bs {{money(product.total)}} vendidos</q-item-label></q-item-section>
            <q-item-section side><q-badge color="primary" :label="`${product.cantidad} uds.`"/></q-item-section>
          </q-item><q-item v-if="!data.productos_top.length"><q-item-section class="text-center text-grey-6 q-py-lg">Todavía no hay productos vendidos</q-item-section></q-item></q-list>
        </q-card></div>
      </div>
    </template>
    <q-card v-else flat bordered class="q-pa-lg text-center"><q-icon name="dashboard" size="60px" color="primary"/><div class="text-h6">Bienvenido a Area Fresca</div><div class="text-grey-7">Selecciona una opción del menú lateral.</div></q-card>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
const apexchart=VueApexCharts
const {proxy}=getCurrentInstance()
const data=reactive({indicadores:{ventas:0,ganancia:0,productos:0,cantidad_ventas:0,ticket_promedio:0},diario:[],usuarios:[],pagos:[],productos_top:[]})
const money=v=>Number(v||0).toLocaleString('es-BO',{minimumFractionDigits:2,maximumFractionDigits:2})
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
const kpis=computed(()=>[
  {label:'Ventas totales',value:data.indicadores.ventas,money:true,icon:'payments',color:'primary',caption:`${data.indicadores.cantidad_ventas} ventas`},
  {label:'Ganancia estimada',value:data.indicadores.ganancia,money:true,icon:'trending_up',color:'positive',caption:'Ventas menos costo y descuento'},
  {label:'Productos vendidos',value:data.indicadores.productos,money:false,icon:'inventory_2',color:'deep-orange',caption:'Unidades acumuladas'},
  {label:'Ticket promedio',value:data.indicadores.ticket_promedio,money:true,icon:'receipt_long',color:'purple',caption:'Promedio por venta'}
])
const baseChart={chart:{toolbar:{show:false},fontFamily:'Roboto, sans-serif'},dataLabels:{enabled:false},grid:{borderColor:'#edf0f2'},tooltip:{theme:'light'}}
const dailySeries=computed(()=>[{name:'Ventas',data:data.diario.map(i=>i.total)}])
const dailyOptions=computed(()=>({...baseChart,chart:{...baseChart.chart,type:'area'},colors:['#c62828'],stroke:{curve:'smooth',width:3},fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.4,opacityTo:.05}},xaxis:{categories:data.diario.map(i=>i.label)},yaxis:{labels:{formatter:v=>`Bs ${Number(v).toFixed(0)}`}}}))
const paymentSeries=computed(()=>data.pagos.map(i=>Number(i.total)))
const paymentOptions=computed(()=>({...baseChart,labels:data.pagos.map(i=>i.nombre),colors:['#21ba45','#2196f3','#9c27b0'],legend:{position:'bottom'},plotOptions:{pie:{donut:{size:'66%',labels:{show:true,total:{show:true,label:'Total',formatter:()=>`Bs ${money(data.indicadores.ventas)}`}}}}}}))
const userSeries=computed(()=>[{name:'Total vendido',data:data.usuarios.map(i=>Number(i.total))}])
const userOptions=computed(()=>({...baseChart,chart:{...baseChart.chart,type:'bar'},colors:['#fb8c00'],plotOptions:{bar:{borderRadius:6,horizontal:true,barHeight:'58%'}},xaxis:{categories:data.usuarios.map(i=>i.nombre),labels:{formatter:v=>`Bs ${Number(v).toFixed(0)}`}}}))
onMounted(()=>{if(proxy.$store.hasPermission('Ver Ventas'))proxy.$axios.get('/dashboard').then(r=>Object.assign(data,r.data)).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el panel'))})
</script>

<style scoped>
.dashboard{background:linear-gradient(180deg,#fff4f3 0,#faf8f8 260px)}.hero{display:flex;align-items:center;padding:18px 22px;border-radius:14px;color:#fff;background:linear-gradient(120deg,#222222,#c62828 60%,#ef5350);box-shadow:0 8px 24px rgba(183,28,28,.22)}.hero-subtitle{color:rgba(255,255,255,.82)}
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.kpi-card,.chart-card{border-radius:12px;background:rgba(255,255,255,.96)}.kpi-icon{color:#fff}.kpi-primary{background:linear-gradient(135deg,#b71c1c,#ef5350)}.kpi-positive{background:linear-gradient(135deg,#1b8f4d,#4caf50)}.kpi-deep-orange{background:linear-gradient(135deg,#e65100,#ff9800)}.kpi-purple{background:linear-gradient(135deg,#6a1b9a,#ab47bc)}
.top-list{max-height:310px;overflow:auto}.rank{position:absolute;margin-left:-7px;margin-top:-5px;width:18px;height:18px;border-radius:50%;background:#c62828;color:white;font-size:10px;display:flex;align-items:center;justify-content:center;z-index:1}
@media(max-width:900px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:500px){.hero{padding:14px}.hero .q-btn{display:none}.kpi-grid{grid-template-columns:1fr}.kpi-card .text-h6{font-size:1.1rem}}
</style>
