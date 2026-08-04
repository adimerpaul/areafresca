<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div>
        <div class="text-subtitle1 text-weight-bold">Revisión {{header.numero}}
          <q-badge :color="stateColor" :label="header.estado==='BORRADOR'?'EN REVISIÓN':header.estado" class="q-ml-xs"/>
        </div>
        <div class="text-caption text-grey-7">{{header.descripcion||'Cuenta el stock físico de la tienda'}} · {{items.length}} productos revisados</div>
      </div>
      <q-space/>
      <q-btn dense flat icon="insights" label="Avance" no-caps class="q-mr-xs" :to="`/almacenes/${id}/avance`"/>
      <q-btn dense flat icon="warehouse" label="Almacenes" no-caps to="/almacenes"/>
    </div>

    <q-banner v-if="!editable" dense class="bg-grey-3 text-grey-9 q-mb-sm rounded-borders">
      <template #avatar><q-icon name="lock" color="grey-8"/></template>
      Esta revisión está {{header.estado.toLowerCase()}} y ya no se puede modificar.
    </q-banner>

    <div class="row q-col-gutter-sm">
      <div v-if="editable" class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <q-input ref="searchInput" v-model="search" dense outlined autofocus clearable class="col-12" placeholder="Buscar o escanear producto" @update:model-value="handleSearchInput" @keydown.enter.prevent="openExact($event.target.value)"><template #prepend><q-icon name="qr_code_scanner"/></template></q-input>
            <q-select v-model="category" :options="categories" option-label="nombre" dense outlined clearable label="Categoría" class="col-12" @update:model-value="resetProductsPage"/>
          </q-card-section>
          <q-separator/>
          <q-card-section v-if="loadingProducts" class="flex flex-center product-loading"><q-spinner color="primary" size="38px"/></q-card-section>
          <q-card-section v-else-if="!products.length" class="text-center text-grey-6 q-py-xl"><q-icon name="search_off" size="42px"/><div>Sin productos para esta búsqueda</div></q-card-section>
          <q-card-section v-else class="q-pa-sm product-grid">
            <q-card v-for="product in products" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--done':countedIds.has(product.id)}" @click="openCount(product)">
              <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="30px" color="grey-4"/><q-icon v-if="countedIds.has(product.id)" name="task_alt" color="positive" size="18px" class="done-mark"/></div>
              <q-card-section class="q-pa-xs">
                <div class="text-weight-bold ellipsis-2-lines product-name">{{product.nombre}}</div>
                <div class="product-meta text-grey-7">{{product.codigo}} · {{product.unidad}}</div>
                <div class="product-meta">Sistema: <b>{{qty(product.stock_inicial,product.unidad)}}</b></div>
              </q-card-section>
            </q-card>
          </q-card-section>
          <q-separator/>
          <q-card-actions class="row items-center justify-between q-px-sm">
            <span class="text-caption text-grey-7">{{productsFrom}}–{{productsTo}} de {{productsTotal}}</span>
            <q-pagination v-model="productsPage" :max="productsLastPage" :max-pages="5" boundary-numbers direction-links color="primary" size="sm" @update:model-value="loadProducts"/>
          </q-card-actions>
        </q-card>
      </div>

      <div class="col-12" :class="editable?'col-md-6':''">
        <q-card flat bordered>
          <q-card-section class="row items-center q-py-sm">
            <q-icon name="fact_check" color="primary" size="22px" class="q-mr-xs"/><b>Productos revisados</b>
            <q-space/>
            <span class="text-caption text-grey-6 q-mr-sm">{{refreshedLabel}}</span>
            <q-btn dense flat round size="sm" icon="refresh" :loading="refreshing" @click="loadAlmacen()"><q-tooltip>Ver lo que cargaron los demás</q-tooltip></q-btn>
            <q-badge color="primary" :label="items.length"/>
          </q-card-section>
          <q-separator/>
          <q-list v-if="items.length" separator class="count-list">
            <q-item v-for="item in items" :key="item.id" dense class="q-px-sm">
              <q-item-section avatar class="count-thumb"><q-avatar rounded size="30px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2" size="16px"/></q-avatar></q-item-section>
              <q-item-section>
                <q-item-label lines="1" class="text-caption text-weight-bold">{{item.nombre}}</q-item-label>
                <q-item-label caption class="count-meta">
                  Sistema {{qty(systemStock(item),item.unidad)}} · Contado <b>{{qty(item.cantidad,item.unidad)}}</b>
                </q-item-label>
                <q-item-label v-if="item.conteos?.length" caption class="count-lots">
                  <q-badge v-for="lot in item.conteos" :key="lot.id" outline color="deep-orange" class="q-mr-xs"
                           :label="`${lot.lote||'sin lote'} · ${qty(lot.cantidad,item.unidad)}${lot.fecha_vencimiento?' · vence '+shortDate(lot.fecha_vencimiento):''}`"/>
                </q-item-label>
                <q-item-label caption class="text-grey-6">Contó {{item.usuario_nombre||'—'}}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <div class="row items-center no-wrap">
                  <q-badge :color="diffColor(item)" :label="diffLabel(item)" class="q-mr-xs"/>
                  <q-btn v-if="editable" dense flat round size="sm" icon="edit" color="primary" @click="openCount(null,item)"/>
                  <q-btn v-if="editable" dense flat round size="sm" icon="delete" color="negative" @click="removeItem(item)"/>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="inventory" size="42px"/><div>Todavía no se contó ningún producto</div></q-card-section>
          <q-separator/>
          <q-card-actions class="q-pa-sm">
            <q-btn class="full-width" color="primary" unelevated icon="insights" label="Ver avance y actualizar productos" no-caps :to="`/almacenes/${id}/avance`"/>
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <q-dialog v-model="countDialog" @hide="focusSearch">
      <q-card style="width:430px;max-width:94vw">
        <q-form @submit.prevent="saveCount">
          <q-card-section class="row items-center q-py-sm bg-primary text-white">
            <q-avatar rounded color="white" text-color="primary" size="38px"><img v-if="form.foto" :src="photoUrl(form.foto)"/><q-icon v-else name="inventory_2"/></q-avatar>
            <div class="q-ml-sm col"><div class="text-subtitle1 text-weight-bold ellipsis">{{form.nombre}}</div><div class="text-caption">{{form.codigo}} · {{form.unidad}}</div></div>
            <q-btn flat round dense icon="close" color="white" v-close-popup/>
          </q-card-section>
          <q-card-section class="row q-col-gutter-sm q-pa-md">
            <div class="col-12 row items-center q-pa-sm rounded-borders bg-blue-grey-1">
              <div><div class="text-caption text-grey-7">Stock del sistema</div><div class="text-h6 text-weight-bold">{{qty(form.stock_sistema,form.unidad)}}</div></div>
              <q-space/>
              <div class="text-right"><div class="text-caption text-grey-7">Diferencia</div><div class="text-h6 text-weight-bold" :class="formDifference>0?'text-positive':formDifference<0?'text-negative':'text-grey-7'">{{formDifference>0?'+':''}}{{formDifference.toFixed(form.unidad==='KG'?3:0)}}</div></div>
            </div>
            <q-input v-if="!form.conteos.length" ref="quantityInput" v-model.number="form.cantidad" autofocus outlined dense type="number" min="0" :step="form.unidad==='KG'?0.001:1" label="Cantidad contada *" class="col-12" input-class="text-h5 text-weight-bold" @focus="$event.target.select()"><template #prepend><q-icon name="scale"/></template></q-input>
            <div v-else class="col-12 row items-center q-pa-sm rounded-borders bg-orange-1 text-orange-10"><span>Cantidad contada (suma de los lotes)</span><q-space/><b class="text-h6">{{qty(form.cantidad,form.unidad)}}</b></div>

            <div class="col-12">
              <div class="row items-center q-mb-xs"><div class="text-caption text-weight-bold text-grey-8">Lotes y vencimientos</div><q-space/><q-btn dense flat no-caps size="sm" color="primary" icon="add" label="Agregar lote" @click="addLot"/></div>
              <div v-if="!form.conteos.length" class="text-caption text-grey-6">Sin lotes: se guarda sólo la cantidad total. Agrega lotes si necesitas registrar vencimientos.</div>
              <div v-for="(lot,index) in form.conteos" :key="index" class="lot-row">
                <input v-model.trim="lot.lote" class="lot-input" placeholder="Lote">
                <input v-model="lot.fecha_vencimiento" class="lot-input date" type="date">
                <input v-model.number="lot.cantidad" class="lot-input qty" type="number" min="0" step="any" placeholder="Cant." @input="syncFormQuantity">
                <q-btn dense flat round size="xs" icon="delete" color="negative" @click="removeLot(index)"/>
              </div>
            </div>

            <q-input v-model.trim="form.observacion" outlined dense label="Observación (opcional)" class="col-12" maxlength="255"/>
          </q-card-section>
          <q-separator/>
          <q-card-actions align="right" class="q-pa-sm">
            <q-btn flat dense label="Cancelar" no-caps v-close-popup/>
            <q-btn type="submit" dense unelevated color="positive" icon="save" label="Guardar producto" no-caps :loading="savingLine"/>
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
const {proxy}=getCurrentInstance(),route=useRoute(),router=useRouter()
const id=Number(route.params.id)
const header=reactive({numero:'',estado:'BORRADOR',descripcion:'',observacion:''})
const items=ref([]),refreshing=ref(false),refreshedAt=ref(null),savingLine=ref(false)
const products=ref([]),categories=ref([]),search=ref(''),category=ref(null),searchInput=ref(null),loadingProducts=ref(false)
const productsPage=ref(1),productsLastPage=ref(1),productsTotal=ref(0),productsFrom=ref(0),productsTo=ref(0),productsPerPage=18
const countDialog=ref(false)
const form=reactive({detalle_id:null,producto_id:null,codigo:'',nombre:'',unidad:'UNIDAD',foto:null,stock_sistema:0,cantidad:0,observacion:'',conteos:[]})
let productsSearchTimer=null,refreshTimer=null
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
const qty=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const shortDate=value=>value?new Date(`${String(value).slice(0,10)}T12:00:00`).toLocaleDateString('es-BO'):''
const editable=computed(()=>header.estado==='BORRADOR')
const stateColor=computed(()=>header.estado==='APLICADO'?'positive':header.estado==='ANULADO'?'grey-6':'orange')
const countedIds=computed(()=>new Set(items.value.map(i=>i.producto_id)))
const refreshedLabel=computed(()=>refreshedAt.value?`Actualizado ${refreshedAt.value.toLocaleTimeString('es-BO')}`:'')
// El stock del sistema se lee del producto en vivo; si el producto ya no viene, queda el que se guardó al contar.
const systemStock=item=>Number(item.producto?.stock_inicial??item.stock_sistema??0)
const difference=item=>Number((Number(item.cantidad||0)-systemStock(item)).toFixed(3))
const diffLabel=item=>{const d=difference(item);return `${d>0?'+':''}${d.toFixed(item.unidad==='KG'?3:0)}`}
const diffColor=item=>{const d=difference(item);return Math.abs(d)<0.0005?'grey-6':d>0?'positive':'negative'}
const formDifference=computed(()=>Number((Number(form.cantidad||0)-Number(form.stock_sistema||0)).toFixed(3)))

async function loadProducts(){
  loadingProducts.value=true
  try{
    const {data}=await proxy.$axios.get('/productos',{params:{q:search.value,categoria_id:category.value?.id,per_page:productsPerPage,page:productsPage.value}})
    products.value=data.data;productsLastPage.value=data.last_page||1;productsTotal.value=data.total||0;productsFrom.value=data.from||0;productsTo.value=data.to||0
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los productos')}
  finally{loadingProducts.value=false}
}
function resetProductsPage(){productsPage.value=1;loadProducts()}
function handleSearchInput(){clearTimeout(productsSearchTimer);productsSearchTimer=setTimeout(resetProductsPage,250)}
function focusSearch(){setTimeout(()=>searchInput.value?.focus(),50)}
async function openExact(value){
  const code=String(value??search.value??'').trim().toUpperCase();if(!code)return
  const match=p=>String(p.codigo||'').toUpperCase()===code||String(p.codigo_barras||'').toUpperCase()===code
  let product=products.value.find(match)
  if(!product){const {data}=await proxy.$axios.get('/productos',{params:{q:code,per_page:20,page:1}});product=(data.data||[]).find(match)}
  if(!product)return proxy.$alert.error(`No existe un producto con código ${code}`)
  search.value='';resetProductsPage();openCount(product)
}
// Abre el conteo desde la grilla (producto) o desde una línea ya cargada (detalle).
// Si el producto ya se contó, se recupera tal cual quedó: cantidad, lotes y vencimientos.
function openCount(product,detail=null){
  if(!editable.value)return
  const counted=detail||items.value.find(i=>i.producto_id===product.id)
  Object.assign(form,{
    detalle_id:counted?.id||null,
    producto_id:counted?.producto_id||product.id,
    codigo:counted?.codigo||product.codigo,
    nombre:counted?.nombre||product.nombre,
    unidad:counted?.unidad||product.unidad,
    foto:counted?.foto??product?.foto??null,
    stock_sistema:counted?systemStock(counted):Number(product.stock_inicial||0),
    cantidad:counted?Number(counted.cantidad):Number(product.stock_inicial||0),
    observacion:counted?.observacion||'',
    conteos:(counted?.conteos||[]).map(c=>({lote:c.lote||'',fecha_vencimiento:c.fecha_vencimiento?String(c.fecha_vencimiento).slice(0,10):'',cantidad:Number(c.cantidad)}))
  })
  countDialog.value=true
}
function addLot(){
  // El primer lote arranca con lo que ya estaba contado para no perder el dato.
  const pending=form.conteos.length?0:Number(form.cantidad||0)
  form.conteos.push({lote:'',fecha_vencimiento:'',cantidad:pending})
  syncFormQuantity()
}
function removeLot(index){form.conteos.splice(index,1);syncFormQuantity()}
function syncFormQuantity(){
  if(form.conteos.length)form.cantidad=Number(form.conteos.reduce((sum,l)=>sum+Number(l.cantidad||0),0).toFixed(3))
}
function linePayload(extra={}){
  return {producto_id:form.producto_id,cantidad:Number(form.cantidad||0),observacion:form.observacion||null,
    conteos:form.conteos.map(l=>({lote:l.lote||null,fecha_vencimiento:l.fecha_vencimiento||null,cantidad:Number(l.cantidad||0)})),...extra}
}
async function saveCount(){
  syncFormQuantity()
  if(form.conteos.some(l=>!(Number(l.cantidad)>0)))return proxy.$alert.error('Cada lote necesita una cantidad mayor a cero')
  if(!(Number(form.cantidad)>=0))return proxy.$alert.error('Ingresa la cantidad contada')
  savingLine.value=true
  try{
    if(form.detalle_id)await proxy.$axios.put(`/almacenes/${id}/detalles/${form.detalle_id}`,linePayload())
    else await proxy.$axios.post(`/almacenes/${id}/detalles`,linePayload())
    proxy.$alert.success(`${form.nombre} registrado`)
    countDialog.value=false
    await loadAlmacen(true)
  }catch(e){
    // 409: otra persona ya contó este producto en esta revisión.
    if(e.response?.status===409)return askReplace(e.response.data.message)
    proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar el producto')
  }finally{savingLine.value=false}
}
function askReplace(message){
  proxy.$alert.dialog(`${message}. ¿Reemplazas ese conteo con el tuyo?`).onOk(async()=>{
    savingLine.value=true
    try{
      await proxy.$axios.post(`/almacenes/${id}/detalles`,linePayload({reemplazar:true}))
      proxy.$alert.success(`${form.nombre} actualizado`)
      countDialog.value=false
      await loadAlmacen(true)
    }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo guardar el producto')}
    finally{savingLine.value=false}
  })
}
function removeItem(item){
  proxy.$alert.dialog(`¿Quitar ${item.nombre} de la revisión?`).onOk(async()=>{
    try{await proxy.$axios.delete(`/almacenes/${id}/detalles/${item.id}`);proxy.$alert.success('Producto quitado');loadAlmacen(true)}
    catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo quitar el producto')}
  })
}
async function loadAlmacen(silent=false){
  if(!silent)refreshing.value=true
  try{
    const {data}=await proxy.$axios.get(`/almacenes/${id}`)
    Object.assign(header,{numero:data.numero,estado:data.estado,descripcion:data.descripcion||'',observacion:data.observacion||''})
    items.value=data.detalles||[]
    refreshedAt.value=new Date()
  }catch(e){
    proxy.$alert.error(e.response?.data?.message||'No se pudo cargar la revisión')
    if(e.response?.status===404)router.replace('/almacenes')
  }finally{refreshing.value=false}
}
onMounted(()=>{
  loadAlmacen();loadProducts()
  proxy.$axios.get('/productos-catalogos').then(r=>categories.value=r.data.categorias).catch(()=>{})
  // Varias personas cargan a la vez: la lista se refresca sola mientras la pestaña está visible.
  refreshTimer=setInterval(()=>{if(!document.hidden&&!countDialog.value&&editable.value)loadAlmacen(true)},10000)
})
onBeforeUnmount(()=>{clearTimeout(productsSearchTimer);clearInterval(refreshTimer)})
</script>

<style scoped>
.product-loading{min-height:280px}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:5px;max-height:calc(100vh - 300px);overflow:auto}
.product-card{overflow:hidden}.product-card:hover{border-color:#f57c00;background:#fffaf5}.product-card--done{border-color:#21ba45;background:#f3fbf5}
.product-image{position:relative;height:54px;background:#fffaf3;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}
.done-mark{position:absolute;top:2px;right:2px;background:#fff;border-radius:50%}
.product-name{height:30px;font-size:11px;line-height:15px}.product-meta{font-size:9px;line-height:13px;white-space:nowrap;overflow:hidden}
.count-list{max-height:calc(100vh - 250px);overflow:auto}.count-thumb{min-width:38px;padding-right:6px}.count-meta{font-size:11px;line-height:14px}
.count-lots{margin-top:2px;line-height:16px}
.lot-row{display:flex;align-items:center;gap:4px;margin-bottom:4px}
.lot-input{height:28px;border:1px solid #cfd8dc;border-radius:4px;padding:2px 6px;font-size:12px;color:#263238;background:#fff;min-width:0;flex:1 1 auto}
.lot-input.date{flex:0 0 128px}.lot-input.qty{flex:0 0 86px;text-align:right;font-weight:700}
.lot-input:focus{outline:1px solid #f57c00;border-color:#f57c00}
@media(max-width:1023px){.product-grid{max-height:none}.count-list{max-height:none}}
</style>

