<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Nueva baja</div><div class="text-caption text-grey-7">Descarga productos del inventario indicando el motivo</div></div>
      <q-space/><q-btn dense flat icon="list_alt" label="Ver bajas" no-caps to="/bajas"/>
    </div>

    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-7">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <q-input ref="searchInput" v-model="search" dense outlined autofocus clearable class="col" placeholder="Buscar producto por nombre o código" @update:model-value="handleSearchInput" @keydown.enter.prevent="addExact($event.target.value)" @keydown.down.prevent="focusGrid">
              <template #prepend><q-icon name="search"/></template>
              <template #hint><span class="text-grey-6">↓ entra a la lista · ← → ↑ ↓ navega · Enter selecciona</span></template>
            </q-input>
            <q-select v-model="category" :options="categories" option-label="nombre" dense outlined clearable label="Categoría" style="min-width:170px" @update:model-value="resetProductsPage"/>
          </q-card-section>
          <q-separator/>
          <q-card-section v-if="loadingProducts" class="flex flex-center product-loading"><q-spinner color="primary" size="38px"/></q-card-section>
          <q-card-section v-else-if="!products.length" class="text-center text-grey-6 q-py-xl"><q-icon name="search_off" size="42px"/><div>Sin productos para esta búsqueda</div></q-card-section>
          <div v-else ref="gridRef" tabindex="0" class="q-pa-sm product-grid" @keydown="handleGridKeyboard" @blur="highlighted=-1">
            <q-card v-for="(product,index) in products" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--empty':product.stock_inicial<=0,'product-card--active':index===highlighted}" @click="openProductDialog(product)">
              <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="42px" color="grey-4"/></div>
              <q-card-section class="q-pa-xs">
                <div class="text-caption text-weight-bold ellipsis-2-lines product-name">{{product.nombre}}</div>
                <div class="row items-center"><span class="text-grey-8">Bs {{money(product.precio_compra)}}</span><q-space/><q-badge :color="product.stock_inicial>0?'positive':'negative'" :label="`Stock ${quantity(product.stock_inicial,product.unidad)} ${product.unidad}`"/></div>
              </q-card-section>
            </q-card>
          </div>
          <q-separator/>
          <q-card-actions class="row items-center justify-between q-px-sm">
            <span class="text-caption text-grey-7">{{productsFrom}}–{{productsTo}} de {{productsTotal}} productos</span>
            <q-pagination v-model="productsPage" :max="productsLastPage" :max-pages="6" boundary-numbers direction-links color="primary" size="sm" @update:model-value="loadProducts"/>
          </q-card-actions>
        </q-card>
      </div>

      <div class="col-12 col-md-5">
        <q-card flat bordered class="list-card">
          <q-card-section class="q-py-sm">
            <div class="row items-center q-mb-sm"><q-icon name="delete_forever" color="negative" size="22px" class="q-mr-xs"/><div class="text-subtitle1 text-weight-bold">Productos a dar de baja</div><q-space/><q-btn v-if="items.length" dense flat color="negative" icon="delete_sweep" label="Vaciar" no-caps @click="clearItems"/><q-badge color="negative" :label="items.length"/></div>
            <q-select v-model="motive" :options="motives" option-label="nombre" dense outlined label="Motivo de la baja *" emit-value map-options option-value="id">
              <template #prepend><q-icon name="report_problem"/></template>
            </q-select>
          </q-card-section>
          <q-separator/>
          <q-list v-if="items.length" separator class="item-list">
            <q-item v-for="item in items" :key="item.key" dense class="q-px-xs">
              <q-item-section avatar class="item-thumb"><q-avatar rounded size="28px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2" size="18px"/></q-avatar></q-item-section>
              <q-item-section>
                <q-item-label lines="1" class="text-caption text-weight-medium">{{item.nombre}}</q-item-label>
                <q-item-label v-if="item.lote_etiqueta" caption class="text-orange-9">Lote {{item.lote_etiqueta}}</q-item-label>
                <div class="fields-row">
                  <label class="field-label"><span>{{item.unidad==='KG'?'Kilos':'Cantidad'}}</span><input v-model.number="item.cantidad" class="qty-input" type="number" :min="minimumQty(item)" :max="item.maximo" :step="quantityStep(item)" @blur="validateQty(item)"></label>
                  <label class="field-label"><span>Costo Bs</span><input :value="money(item.precio_compra*item.cantidad)" class="cost-input" readonly tabindex="-1"></label>
                  <div class="item-actions"><q-btn dense flat round size="xs" icon="remove" @click="changeQty(item,-quantityStep(item))"/><q-btn dense flat round size="xs" icon="add" @click="changeQty(item,quantityStep(item))"/><q-btn dense flat round size="xs" icon="delete" color="negative" @click="removeItem(item)"/></div>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="inventory" size="42px"/><div>Agrega productos a dar de baja</div></q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-md">
            <q-input v-model="observation" dense outlined autogrow label="Observación" class="q-mb-sm"/>
            <div class="row items-center text-h6 text-negative"><b>Costo de la baja</b><q-space/><b>Bs {{money(totalCost)}}</b></div>
          </q-card-section>
          <q-card-actions class="q-pa-sm"><q-btn class="full-width" color="negative" unelevated icon="save" label="Registrar baja" no-caps :disable="!items.length||!motive" :loading="saving" @click="confirmBaja"/></q-card-actions>
        </q-card>
      </div>
    </div>

    <q-dialog v-model="productDialog" @hide="focusSearch">
      <q-card style="width:440px;max-width:94vw">
        <q-form @submit.prevent="confirmProduct">
          <q-card-section class="row items-center q-py-sm bg-negative text-white"><q-avatar rounded color="white" text-color="negative" size="38px"><img v-if="selectedProduct?.foto" :src="photoUrl(selectedProduct.foto)"/><q-icon v-else name="inventory_2"/></q-avatar><div class="q-ml-sm col"><div class="text-subtitle1 text-weight-bold ellipsis">{{selectedProduct?.nombre}}</div><div class="text-caption">Disponible {{quantity(availableStock,selectedProduct?.unidad)}} {{selectedProduct?.unidad}}</div></div><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
          <q-card-section class="row q-col-gutter-sm q-pa-md">
            <q-input ref="quickQtyInput" v-model.number="quickQuantity" autofocus outlined dense type="number" :min="minimumQty(selectedProduct)" :max="availableStock" :step="quantityStep(selectedProduct)" :label="selectedProduct?.unidad==='KG'?'Kilos a dar de baja':'Cantidad a dar de baja'" class="col-12" input-class="text-h6 text-weight-bold" @focus="$event.target.select()"><template #prepend><q-icon name="scale"/></template></q-input>
            <q-select v-if="lots.length" v-model="quickLot" :options="lots" dense outlined clearable class="col-12" label="Lote (opcional)" :option-label="lotLabel" :loading="loadingLots"><template #prepend><q-icon name="inventory_2"/></template></q-select>
            <q-input v-model.trim="quickNote" outlined dense class="col-12" label="Detalle (opcional)" maxlength="255"/>
            <div class="col-12 row items-center q-pa-sm rounded-borders bg-red-1 text-red-10"><span>Costo de la pérdida</span><q-space/><b class="text-h6">Bs {{money(Number(quickQuantity)*Number(selectedProduct?.precio_compra||0))}}</b></div>
          </q-card-section>
          <q-separator/><q-card-actions align="right" class="q-pa-sm"><q-btn flat dense label="Cancelar" no-caps v-close-popup/><q-btn type="submit" dense unelevated color="negative" icon="playlist_add" label="Agregar · Enter" no-caps/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref } from 'vue'
import { printBaja } from '../../addons/bajaPrint'
const {proxy}=getCurrentInstance()
const products=ref([]),categories=ref([]),motives=ref([]),items=ref([]),search=ref(''),category=ref(null),motive=ref(null),observation=ref(''),saving=ref(false),searchInput=ref(null)
const productDialog=ref(false),selectedProduct=ref(null),quickQuantity=ref(1),quickLot=ref(null),quickNote=ref(''),lots=ref([]),loadingLots=ref(false)
const productsPage=ref(1),productsLastPage=ref(1),productsTotal=ref(0),productsFrom=ref(0),productsTo=ref(0),loadingProducts=ref(false)
const productsPerPage=20,gridRef=ref(null),highlighted=ref(-1)
let productsSearchTimer=null,itemSequence=0
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,money=v=>Number(v||0).toFixed(2)
const isWeighted=item=>item?.unidad==='KG',quantityStep=item=>isWeighted(item)?0.001:1,minimumQty=item=>quantityStep(item)
const quantity=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const lotLabel=lot=>`${lot.lote||'sin lote'} · vence ${lot.fecha_vencimiento||'—'} · ${Number(lot.cantidad_disponible).toFixed(3)}`
const totalCost=computed(()=>items.value.reduce((sum,i)=>sum+Number(i.precio_compra)*Number(i.cantidad),0))
// Stock libre del producto abierto, descontando lo que ya está en la lista de la baja.
const availableStock=computed(()=>{if(!selectedProduct.value)return 0;const used=items.value.filter(i=>i.producto_id===selectedProduct.value.id).reduce((s,i)=>s+Number(i.cantidad||0),0);return Math.max(0,Number((Number(selectedProduct.value.stock_inicial)-used).toFixed(3)))})

async function loadProducts(){loadingProducts.value=true;highlighted.value=-1;try{const {data}=await proxy.$axios.get('/productos',{params:{q:search.value,categoria_id:category.value?.id,per_page:productsPerPage,page:productsPage.value}});products.value=data.data;productsLastPage.value=data.last_page||1;productsTotal.value=data.total||0;productsFrom.value=data.from||0;productsTo.value=data.to||0}finally{loadingProducts.value=false}}
function resetProductsPage(){productsPage.value=1;loadProducts()}
function handleSearchInput(){clearTimeout(productsSearchTimer);productsSearchTimer=setTimeout(resetProductsPage,250)}
function focusSearch(){setTimeout(()=>searchInput.value?.focus(),50)}

function gridColumns(){const el=gridRef.value;if(!el)return 1;return Math.max(1,getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean).length)}
function scrollToHighlighted(){requestAnimationFrame(()=>gridRef.value?.children?.[highlighted.value]?.scrollIntoView({block:'nearest'}))}
function focusGrid(){if(!products.value.length)return;highlighted.value=0;gridRef.value?.focus();scrollToHighlighted()}
function backToSearch(){highlighted.value=-1;searchInput.value?.focus()}
function moveHighlight(delta){const last=products.value.length-1;if(last<0)return;highlighted.value=Math.min(last,Math.max(0,highlighted.value+delta));scrollToHighlighted()}
function handleGridKeyboard(event){const columns=gridColumns(),index=highlighted.value;switch(event.key){case 'ArrowRight':event.preventDefault();return moveHighlight(1);case 'ArrowLeft':event.preventDefault();return index<=0?backToSearch():moveHighlight(-1);case 'ArrowDown':event.preventDefault();return moveHighlight(columns);case 'ArrowUp':event.preventDefault();return index<columns?backToSearch():moveHighlight(-columns);case 'Enter':event.preventDefault();if(products.value[index])openProductDialog(products.value[index]);return;case 'Escape':event.preventDefault();return backToSearch()}}

async function addExact(value){const q=String(value??search.value??'').trim().toUpperCase();if(!q)return;const match=p=>String(p.codigo||'').toUpperCase()===q||String(p.codigo_barras||'').toUpperCase()===q;let product=products.value.find(match);if(!product){const {data}=await proxy.$axios.get('/productos',{params:{q,per_page:20,page:1}});product=(data.data||[]).find(match)}if(!product)return proxy.$alert.error(`No existe un producto con código ${q}`);openProductDialog(product)}

async function openProductDialog(product){
  if(Number(product.stock_inicial)<=0)return proxy.$alert.error(`${product.nombre}: no tiene stock para dar de baja`)
  selectedProduct.value=product
  const free=availableStock.value
  if(free<minimumQty(product))return proxy.$alert.error(`${product.nombre}: ya agregaste todo el stock disponible`)
  quickQuantity.value=Math.min(1,free);quickLot.value=null;quickNote.value='';lots.value=[]
  productDialog.value=true
  loadingLots.value=true
  try{lots.value=(await proxy.$axios.get('/bajas-lotes',{params:{producto_id:product.id}})).data}catch{lots.value=[]}finally{loadingLots.value=false}
}
function confirmProduct(){
  const product=selectedProduct.value,requested=Number(quickQuantity.value)
  if(!product||!(requested>=minimumQty(product)))return proxy.$alert.error('Ingrese una cantidad válida')
  if(requested>availableStock.value+.0001)return proxy.$alert.error(`Stock insuficiente: disponible ${quantity(availableStock.value,product.unidad)} ${product.unidad}`)
  if(quickLot.value&&requested>Number(quickLot.value.cantidad_disponible)+.0001)return proxy.$alert.error(`El lote seleccionado solo tiene ${Number(quickLot.value.cantidad_disponible).toFixed(3)}`)
  const rounded=isWeighted(product)?Math.round(requested*1000)/1000:Math.floor(requested)
  items.value.push({key:`${product.id}-${++itemSequence}`,producto_id:product.id,nombre:product.nombre,unidad:product.unidad,foto:product.foto,
    precio_compra:Number(product.precio_compra||0),cantidad:rounded,maximo:Number(product.stock_inicial),
    lote_id:quickLot.value?.id||null,lote_etiqueta:quickLot.value?(quickLot.value.lote||'sin lote'):null,observacion:quickNote.value||null})
  productDialog.value=false;selectedProduct.value=null;search.value='';productsPage.value=1;loadProducts()
}
function changeQty(item,amount){const next=Number((Number(item.cantidad)+amount).toFixed(3));if(next<minimumQty(item))return removeItem(item);if(next>Number(item.maximo)+.0001)return proxy.$alert.error('Supera el stock disponible');item.cantidad=next}
function validateQty(item){let value=Number(item.cantidad)||minimumQty(item);value=isWeighted(item)?Math.round(value*1000)/1000:Math.floor(value);if(value>Number(item.maximo)){item.cantidad=Number(item.maximo);proxy.$alert.error('La cantidad fue ajustada al stock disponible')}else item.cantidad=Math.max(minimumQty(item),value)}
function removeItem(item){items.value=items.value.filter(i=>i.key!==item.key)}
function clearItems(){proxy.$alert.dialog('¿Quitar todos los productos de la baja?').onOk(()=>{items.value=[];searchInput.value?.focus()})}

function confirmBaja(){
  if(!motive.value)return proxy.$alert.error('Selecciona el motivo de la baja')
  const motiveName=motives.value.find(m=>m.id===motive.value)?.nombre||'baja'
  proxy.$alert.dialog(`¿Registrar la ${motiveName.toLowerCase()} por Bs ${money(totalCost.value)}? El stock se descontará del inventario.`).onOk(async()=>{
    saving.value=true
    try{
      const {data}=await proxy.$axios.post('/bajas',{motivo_id:motive.value,observacion:observation.value,
        detalles:items.value.map(i=>({producto_id:i.producto_id,cantidad:i.cantidad,lote_id:i.lote_id,observacion:i.observacion}))})
      proxy.$alert.success(`Baja ${data.numero} registrada`)
      printBaja(data)
      items.value=[];observation.value='';loadProducts();searchInput.value?.focus()
    }catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar la baja')}
    finally{saving.value=false}
  })
}

onMounted(()=>{loadProducts()
  proxy.$axios.get('/productos-catalogos').then(r=>categories.value=r.data.categorias).catch(()=>{})
  proxy.$axios.get('/bajas-catalogos').then(r=>{motives.value=r.data.motivos;if(motives.value.length===1)motive.value=motives.value[0].id}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los motivos'))})
onBeforeUnmount(()=>clearTimeout(productsSearchTimer))
</script>

<style scoped>
.product-loading{min-height:350px}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(125px,1fr));gap:6px;max-height:calc(100vh - 210px);overflow:auto}.product-grid:focus{outline:none}
.product-card{transition:.15s;overflow:hidden}.product-card:hover{border-color:#e53935;transform:translateY(-1px)}.product-card--empty{opacity:.55}.product-card--active{border-color:#e53935;box-shadow:0 0 0 2px rgba(229,57,53,.4);transform:translateY(-1px)}
.product-image{height:60px;background:#fdf5f5;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}.product-name{height:32px;line-height:16px}
.list-card{position:sticky;top:62px}.item-list{max-height:52vh;overflow:auto}.item-thumb{min-width:34px;padding-right:6px}
.fields-row{display:flex;align-items:flex-end;gap:6px;white-space:nowrap;margin-top:2px}.field-label{font-size:10px;line-height:11px;color:#607d8b;display:flex;flex-direction:column;gap:2px;margin:0}
.qty-input,.cost-input{height:26px;border:1px solid #cfd8dc;border-radius:4px;padding:2px 6px;font-size:13px;color:#263238;background:#fff}.qty-input{width:86px}.cost-input{width:92px;font-weight:700;color:#c62828;background:#fafafa}
.qty-input:focus{outline:1px solid #e53935;border-color:#e53935}.item-actions{display:flex;align-items:center;margin-left:auto}.item-actions .q-btn{min-width:24px;min-height:24px}
@media(max-width:1023px){.list-card{position:static}.product-grid{max-height:none}}@media(max-width:420px){.item-thumb{display:none}.fields-row{gap:3px;overflow-x:auto}.qty-input{width:72px}.cost-input{width:78px}}
</style>

