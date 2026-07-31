<template>
  <q-page class="q-pa-sm">
<!--    <CompanyBanner class="q-mb-sm"/>-->
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Nueva venta</div><div class="text-caption text-grey-7">Selecciona productos y confirma el carrito</div></div>
      <q-space/><q-btn dense flat icon="receipt_long" label="Ver ventas" no-caps to="/ventas"/>
    </div>
    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-8">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <q-input ref="searchInput" v-model="search" dense outlined autofocus clearable debounce="250" class="col" placeholder="Buscar nombre, código o escanear etiqueta de balanza" @update:model-value="handleSearchInput" @keydown.enter.prevent="addExact">
              <template #prepend><q-icon name="qr_code_scanner"/></template>
            </q-input>
            <q-select v-model="category" :options="categories" option-label="nombre" dense outlined clearable label="Categoría" style="min-width:170px" @update:model-value="loadProducts"/>
          </q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-sm product-grid">
            <q-card v-for="product in products" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--empty':product.stock_inicial<=0}" @click="add(product)">
              <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="42px" color="grey-4"/></div>
              <q-card-section class="q-pa-xs">
                <div class="text-caption text-weight-bold ellipsis-2-lines product-name">{{product.nombre}}</div>
                <div class="row items-center"><span class="text-primary text-weight-bold">Bs {{money(product.precio_venta)}}{{product.unidad==='KG'?'/kg':''}}</span><q-space/><q-badge :color="product.stock_inicial>0?'positive':'negative'" :label="`Stock ${quantity(product.stock_inicial,product.unidad)} ${product.unidad}`"/></div>
              </q-card-section>
            </q-card>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-4">
        <q-card flat bordered class="cart-card">
          <q-card-section class="row items-center q-py-sm"><q-icon name="shopping_cart" color="primary" size="22px" class="q-mr-xs"/><div class="text-subtitle1 text-weight-bold">Carrito</div><q-space/><q-badge color="primary" :label="itemCount"/></q-card-section>
          <q-separator/>
          <q-list v-if="cart.length" separator class="cart-list">
            <q-item v-for="item in cart" :key="item.id" dense class="q-px-sm">
              <q-item-section avatar><q-avatar rounded size="34px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2"/></q-avatar></q-item-section>
              <q-item-section><q-item-label lines="1" class="text-caption text-weight-medium">{{item.nombre}}</q-item-label><label class="price-label">Cantidad ({{item.unidad}}) <input v-model.number="item.cantidad" class="qty-input" type="number" :min="minimumQty(item)" :max="item.stock_inicial" :step="quantityStep(item)" @blur="validateQty(item)"></label><label class="price-label">Precio Bs {{item.unidad==='KG'?'/ kg':''}} <input v-model.number="item.precio_venta" class="price-input" type="number" min="0" step="0.0001" @blur="syncLineTotal(item)"></label></q-item-section>
              <q-item-section side><div class="row items-center no-wrap"><q-btn dense flat round size="sm" icon="remove" @click="changeQty(item,-quantityStep(item))"/><q-btn dense flat round size="sm" icon="add" @click="changeQty(item,quantityStep(item))"/><q-btn dense flat round size="sm" icon="delete" color="negative" @click="removeItem(item)"/></div><label class="total-label">Total Bs <input v-model.number="item.total_editable" class="total-input" type="number" min="0" step="0.01" @keyup.enter="$event.target.blur()" @blur="applyLineTotal(item)"></label></q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="remove_shopping_cart" size="42px"/><div>Agrega productos</div></q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-sm">
            <q-input v-model.number="discount" dense outlined type="number" min="0" :max="subtotal" step="0.01" label="Descuento" prefix="Bs" class="q-mb-sm"/>
            <q-select v-model="paymentType" dense outlined :options="paymentTypes" label="Tipo de pago" class="q-mb-sm"/>
            <div v-if="paymentType==='COMBINADO'" class="row q-col-gutter-sm q-mb-sm">
              <q-input v-model.number="cashAmount" dense outlined type="number" min="0" step="0.01" label="Monto efectivo" prefix="Bs" class="col-6"/>
              <q-input v-model.number="qrAmount" dense outlined type="number" min="0" step="0.01" label="Monto QR" prefix="Bs" class="col-6"/>
              <div class="col-12 text-caption" :class="paymentDifference===0?'text-positive':'text-negative'">Diferencia: Bs {{money(paymentDifference)}}</div>
            </div>
            <q-banner v-else dense rounded :class="paymentType==='EFECTIVO'?'bg-green-1 text-green-9':'bg-blue-1 text-blue-9'" class="q-mb-sm"><q-icon :name="paymentType==='EFECTIVO'?'payments':'qr_code_2'" class="q-mr-xs"/>Pago {{paymentType}}: Bs {{money(total)}}</q-banner>
            <q-input v-model="observation" dense outlined autogrow label="Observación" class="q-mb-sm"/>
            <div class="row text-body2"><span>Subtotal</span><q-space/><b>Bs {{money(subtotal)}}</b></div>
            <div class="row text-body2 text-negative"><span>Descuento</span><q-space/><b>- Bs {{money(validDiscount)}}</b></div>
            <div class="row text-h6 text-primary q-mt-xs"><b>Total</b><q-space/><b>Bs {{money(total)}}</b></div>
          </q-card-section>
          <q-card-actions class="q-pa-sm"><q-btn class="full-width" color="positive" unelevated icon="point_of_sale" label="Confirmar venta" no-caps :disable="!cart.length" :loading="saving" @click="confirmSale"/></q-card-actions>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue'
import { printSale } from '../../addons/ventaPrint'
import CompanyBanner from '../../components/CompanyBanner.vue'
const {proxy}=getCurrentInstance()
const products=ref([]),categories=ref([]),cart=ref([]),search=ref(''),category=ref(null),discount=ref(0),observation=ref(''),saving=ref(false),searchInput=ref(null)
const paymentType=ref('EFECTIVO'),paymentTypes=['EFECTIVO','QR','COMBINADO'],cashAmount=ref(0),qrAmount=ref(0)
let processingBarcode=false
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,money=v=>Number(v||0).toFixed(2)
const isWeighted=item=>item?.unidad==='KG',quantityStep=item=>isWeighted(item)?0.001:1,minimumQty=item=>quantityStep(item)
const quantity=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const subtotal=computed(()=>cart.value.reduce((sum,i)=>sum+Number(i.precio_venta)*i.cantidad,0))
const validDiscount=computed(()=>Math.min(Math.max(Number(discount.value)||0,0),subtotal.value))
const total=computed(()=>subtotal.value-validDiscount.value)
const itemCount=computed(()=>cart.value.length)
const paymentDifference=computed(()=>Number((total.value-(Number(cashAmount.value)||0)-(Number(qrAmount.value)||0)).toFixed(2)))
watch([paymentType,total],()=>{if(paymentType.value==='EFECTIVO'){cashAmount.value=total.value;qrAmount.value=0}else if(paymentType.value==='QR'){cashAmount.value=0;qrAmount.value=total.value}else if(Number(cashAmount.value)+Number(qrAmount.value)===0){cashAmount.value=total.value;qrAmount.value=0}})
function loadProducts(){proxy.$axios.get('/productos',{params:{q:search.value,categoria_id:category.value?.id,per_page:0}}).then(r=>products.value=r.data.data)}
function handleSearchInput(value){if(parseScaleBarcode(value))addExact();else loadProducts()}
function add(product,amount=null){const requested=Number(amount??quantityStep(product));if(Number(product.stock_inicial)<=0)return proxy.$alert.error(`${product.nombre}: stock 0. Registra primero una compra para cargar existencias.`);const item=cart.value.find(i=>i.id===product.id);const next=Number(((item?.cantidad||0)+requested).toFixed(3));if(next>Number(product.stock_inicial)+.0001)return proxy.$alert.error(`Stock insuficiente: disponible ${quantity(product.stock_inicial,product.unidad)} ${product.unidad}`);if(item){item.cantidad=next;syncLineTotal(item)}else cart.value.push({...product,cantidad:requested,total_editable:(Number(product.precio_venta)*requested).toFixed(2)});if(amount!==null)proxy.$alert.success(`${product.nombre}: ${quantity(requested,product.unidad)} ${product.unidad} leído correctamente`)}
function parseScaleBarcode(value){const code=String(value||'').trim();if(!/^2\d{12}$/.test(code))return null;const expected=ean13CheckDigit(code.slice(0,12));if(expected!==Number(code[12]))return null;return{productCode:code.slice(0,7),weight:Number(code.slice(7,12))/1000}}
function ean13CheckDigit(firstTwelve){const sum=[...firstTwelve].reduce((total,digit,index)=>total+Number(digit)*(index%2===0?1:3),0);return(10-(sum%10))%10}
async function addExact(){const q=(search.value||'').trim().toUpperCase();const scale=parseScaleBarcode(q);if(scale){if(processingBarcode)return;processingBarcode=true;try{const {data}=await proxy.$axios.get('/productos',{params:{q:scale.productCode,per_page:0}});const product=data.data.find(p=>String(p.codigo)===scale.productCode||String(p.codigo_barras)===scale.productCode);if(!product)return proxy.$alert.error(`No existe un producto con código de balanza ${scale.productCode}`);if(product.unidad!=='KG')return proxy.$alert.error(`${product.nombre} debe tener unidad KG`);add(product,scale.weight);search.value='';loadProducts();return}catch(e){return proxy.$alert.error(e.response?.data?.message||'No se pudo leer la etiqueta de balanza')}finally{processingBarcode=false}}const product=products.value.find(p=>String(p.codigo||'').toUpperCase()===q||String(p.codigo_barras||'').toUpperCase()===q);if(product){add(product,isWeighted(product)?null:1);search.value='';loadProducts()}}
function changeQty(item,amount){const next=Number((Number(item.cantidad)+amount).toFixed(3));if(next<minimumQty(item))return removeItem(item);if(next>Number(item.stock_inicial)+.0001)return proxy.$alert.error('Stock insuficiente');item.cantidad=next;syncLineTotal(item)}
function validateQty(item){let value=Number(item.cantidad)||minimumQty(item);value=isWeighted(item)?Math.round(value*1000)/1000:Math.floor(value);if(value>Number(item.stock_inicial)){item.cantidad=Number(item.stock_inicial);proxy.$alert.error('La cantidad fue ajustada al stock disponible')}else item.cantidad=Math.max(minimumQty(item),value);syncLineTotal(item)}
function syncLineTotal(item){item.total_editable=(Number(item.precio_venta||0)*Number(item.cantidad||0)).toFixed(2)}
function applyLineTotal(item){const totalValue=Math.max(0,Number(item.total_editable)||0),lineQuantity=Math.max(minimumQty(item),Number(item.cantidad)||minimumQty(item));item.total_editable=totalValue.toFixed(2);item.precio_venta=Number((totalValue/lineQuantity).toFixed(4))}
function removeItem(item){cart.value=cart.value.filter(i=>i.id!==item.id)}
function confirmSale(){cart.value.forEach(item=>{const requestedTotal=item.total_editable;validateQty(item);item.total_editable=requestedTotal;applyLineTotal(item)});if(cart.value.some(i=>Number(i.precio_venta)<0||i.precio_venta===''))return proxy.$alert.error('Revisa los precios de venta');if(paymentType.value==='COMBINADO'&&paymentDifference.value!==0)return proxy.$alert.error('Efectivo y QR deben sumar el total');proxy.$alert.dialog(`¿Confirmar venta por Bs ${money(total.value)}?`).onOk(async()=>{saving.value=true;try{const {data}=await proxy.$axios.post('/ventas',{descuento:validDiscount.value,tipo_pago:paymentType.value,monto_efectivo:cashAmount.value,monto_qr:qrAmount.value,observacion:observation.value,detalles:cart.value.map(i=>({producto_id:i.id,cantidad:i.cantidad,precio_venta:i.precio_venta}))});proxy.$alert.success(`Venta ${data.numero} registrada`);printSale(data);cart.value=[];discount.value=0;observation.value='';paymentType.value='EFECTIVO';loadProducts();searchInput.value?.focus()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar la venta')}finally{saving.value=false}})}
onMounted(()=>{loadProducts();proxy.$axios.get('/productos-catalogos').then(r=>categories.value=r.data.categorias)})
</script>

<style scoped>
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:7px;max-height:calc(100vh - 180px);overflow:auto}.product-card{transition:.15s;overflow:hidden}.product-card:hover{border-color:#f57c00;transform:translateY(-1px)}.product-card--empty{opacity:.55}.product-image{height:92px;background:#fffaf3;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}.product-name{height:34px}.cart-card{position:sticky;top:62px}.cart-list{max-height:38vh;overflow:auto}.price-label,.total-label{font-size:10px;color:#607d8b;display:flex;align-items:center;gap:4px;margin-top:2px}.price-input,.qty-input,.total-input{width:72px;height:23px;border:1px solid #cfd8dc;border-radius:4px;padding:1px 4px;font-size:12px;color:#263238;background:#fff}.qty-input{width:55px}.total-input{width:76px;font-weight:700;color:#f57c00}.price-input:focus,.qty-input:focus,.total-input:focus{outline:1px solid #f57c00;border-color:#f57c00}@media(max-width:1023px){.cart-card{position:static}.product-grid{max-height:none}}
</style>
