<template>
  <q-page class="q-pa-sm">
<!--    <CompanyBanner class="q-mb-sm"/>-->
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Nueva venta</div><div class="text-caption text-grey-7">Selecciona productos y confirma el carrito</div></div>
      <q-space/><q-btn dense flat icon="receipt_long" label="Ver ventas" no-caps to="/ventas"/>
    </div>
    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <q-input ref="searchInput" v-model="search" dense outlined autofocus clearable class="col" placeholder="Buscar nombre, código o escanear etiqueta de balanza" @update:model-value="handleSearchInput" @keydown.enter.prevent="addExact(true,$event.target.value)" @keydown.tab="handleSearchTab">
              <template #prepend><q-icon name="qr_code_scanner"/></template>
            </q-input>
            <q-select v-model="category" :options="categories" option-label="nombre" dense outlined clearable label="Categoría" style="min-width:170px" @update:model-value="resetProductsPage"/>
            <div class="col-auto flex items-center">
              <q-btn dense unelevated color="primary" icon="sync" label="Actualizar precio" no-caps :loading="refreshingPrices" @click="refreshPrices"/>
            </div>
          </q-card-section>
          <q-separator/>
          <q-card-section v-if="loadingProducts" class="flex flex-center product-loading"><q-spinner color="primary" size="38px"/></q-card-section>
          <q-card-section v-else class="q-pa-sm product-grid">
            <q-card v-for="product in products" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--empty':product.stock_inicial<=0}" @click="openProductDialog(product)">
              <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="42px" color="grey-4"/></div>
              <q-card-section class="q-pa-xs">
                <div class="text-caption text-weight-bold ellipsis-2-lines product-name">{{product.nombre}}</div>
                <div class="row items-center"><span class="text-primary text-weight-bold">Bs {{money(product.precio_venta)}}{{product.unidad==='KG'?'/kg':''}}</span><q-space/><q-badge :color="product.stock_inicial>0?'positive':'negative'" :label="`Stock ${quantity(product.stock_inicial,product.unidad)} ${product.unidad}`"/></div>
              </q-card-section>
            </q-card>
          </q-card-section>
          <q-separator/>
          <q-card-actions class="row items-center justify-between q-px-sm">
            <span class="text-caption text-grey-7">{{productsFrom}}–{{productsTo}} de {{productsTotal}} productos</span>
            <q-pagination v-model="productsPage" :max="productsLastPage" :max-pages="6" boundary-numbers direction-links color="primary" size="sm" @update:model-value="loadProducts"/>
          </q-card-actions>
        </q-card>
      </div>

      <div class="col-12 col-md-6">
        <q-card flat bordered class="cart-card">
          <q-card-section class="row items-center q-py-sm"><q-icon name="shopping_cart" color="primary" size="22px" class="q-mr-xs"/><div class="text-subtitle1 text-weight-bold">Carrito</div><q-space/><q-btn v-if="cart.length" dense flat color="negative" icon="delete_sweep" label="Vaciar" no-caps class="q-mr-xs" @click="clearCart"/><q-badge color="primary" :label="itemCount"/></q-card-section>
          <q-separator/>
          <q-list v-if="cart.length" separator class="cart-list">
            <q-item v-for="item in cart" :key="item.line_id" dense class="q-px-xs cart-item">
              <q-item-section avatar class="cart-thumb"><q-avatar rounded size="28px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2" size="18px"/></q-avatar></q-item-section>
              <q-item-section class="cart-item-content">
                <q-item-label lines="1" class="text-caption text-weight-medium cart-item-name">{{item.nombre}}</q-item-label>
                <div class="cart-fields-row">
                  <label class="price-label"><span>{{item.unidad==='KG'?'Kilos':'Cantidad'}}</span><input v-model.number="item.cantidad" class="qty-input" type="number" :min="minimumQty(item)" :step="quantityStep(item)" @blur="validateQty(item)"></label>
                  <label class="price-label"><span>{{item.unidad==='KG'?'Precio Bs/kg':'Precio Bs'}}</span><q-select v-model="item.precio_venta" :options="priceOptions(item)" :option-label="priceOptionLabel" option-value="value" emit-value map-options use-input fill-input hide-selected input-debounce="0" new-value-mode="add-unique" dense outlined hide-bottom-space :display-value="item.precio_venta===null||item.precio_venta===''?'':money(item.precio_venta)" class="price-select" @update:model-value="syncLineTotal(item)"><template #option="scope"><q-item v-bind="scope.itemProps" dense><q-item-section><q-item-label>{{scope.opt.label}}</q-item-label><q-item-label caption>Bs {{money(scope.opt.value)}}{{item.unidad==='KG'?'/kg':''}}</q-item-label></q-item-section></q-item></template></q-select></label>
                  <label class="total-label"><span>Total</span><input v-model.number="item.total_editable" class="total-input" type="number" min="0" step="0.01" @keyup.enter="$event.target.blur()" @blur="applyLineTotal(item)"></label>
                  <div class="cart-actions"><q-btn dense flat round size="xs" icon="remove" @click="changeQty(item,-quantityStep(item))"/><q-btn dense flat round size="xs" icon="add" @click="changeQty(item,quantityStep(item))"/><q-btn dense flat round size="xs" icon="delete" color="negative" @click="removeItem(item)"/></div>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="remove_shopping_cart" size="42px"/><div>Agrega productos</div></q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-md cart-summary">
            <div class="row text-body2"><span>Subtotal</span><q-space/><b>Bs {{money(subtotal)}}</b></div>
            <div class="row text-body2 text-negative"><span>Descuento</span><q-space/><b>- Bs {{money(validDiscount)}}</b></div>
            <div class="row text-h6 text-primary q-mt-xs"><b>Total</b><q-space/><b>Bs {{money(total)}}</b></div>
          </q-card-section>
          <q-card-actions class="q-pa-sm"><q-btn class="full-width checkout-btn" color="positive" unelevated icon="point_of_sale" label="Continuar y cobrar · Enter" no-caps :disable="!cart.length" @click="openCheckout"/></q-card-actions>
        </q-card>
      </div>
    </div>

    <q-dialog v-model="productDialog" @hide="focusSearch">
      <q-card style="width:420px;max-width:94vw">
        <q-form @submit.prevent="confirmProduct">
          <q-card-section class="row items-center q-py-sm bg-primary text-white"><q-avatar rounded color="white" text-color="primary" size="38px"><img v-if="selectedProduct?.foto" :src="photoUrl(selectedProduct.foto)"/><q-icon v-else name="inventory_2"/></q-avatar><div class="q-ml-sm col"><div class="text-subtitle1 text-weight-bold ellipsis">{{selectedProduct?.nombre}}</div><div class="text-caption">Agregar al carrito</div></div><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
          <q-card-section class="row q-col-gutter-sm q-pa-md">
            <q-input ref="quickQtyInput" v-model.number="quickQuantity" autofocus outlined dense type="number" :min="minimumQty(selectedProduct)" :step="quantityStep(selectedProduct)" :label="selectedProduct?.unidad==='KG'?'Kilos':'Cantidad'" class="col-12 col-sm-6" input-class="text-h6 text-weight-bold" @focus="$event.target.select()"><template #prepend><q-icon name="scale"/></template></q-input>
            <q-select v-model="quickPrice" :options="priceOptions(selectedProduct)" :option-label="priceOptionLabel" option-value="value" emit-value map-options use-input fill-input hide-selected input-debounce="0" new-value-mode="add-unique" outlined dense :display-value="quickPrice===null||quickPrice===''?'':money(quickPrice)" :label="selectedProduct?.unidad==='KG'?'Precio Bs/kg':'Precio Bs'" prefix="Bs" class="col-12 col-sm-6" input-class="text-h6 text-weight-bold"><template #prepend><q-icon name="payments"/></template><template #option="scope"><q-item v-bind="scope.itemProps"><q-item-section><q-item-label>{{scope.opt.label}}</q-item-label><q-item-label caption>Bs {{money(scope.opt.value)}}{{selectedProduct?.unidad==='KG'?'/kg':''}}</q-item-label></q-item-section></q-item></template></q-select>
            <div class="col-12 row items-center q-pa-sm rounded-borders bg-orange-1 text-orange-10"><span>Total</span><q-space/><b class="text-h6">Bs {{money(Number(quickQuantity)*Number(quickPrice))}}</b></div>
          </q-card-section>
          <q-separator/><q-card-actions align="right" class="q-pa-sm"><q-btn flat dense label="Cancelar" no-caps v-close-popup/><q-btn type="submit" dense unelevated color="positive" icon="add_shopping_cart" label="Agregar · Enter" no-caps/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="checkoutDialog" :maximized="$q.screen.lt.sm" @hide="focusSearch">
      <q-card class="column no-wrap" style="width:760px;max-width:94vw;max-height:92vh">
        <q-card-section class="row items-center q-pa-sm bg-primary text-white"><q-avatar color="white" text-color="primary" icon="receipt_long" size="32px"/><div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Confirmar venta</div><div class="text-caption">Cliente, pago y resumen</div></div><q-space/><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
        <q-card-section class="col scroll q-pa-sm">
          <div class="section-title"><q-icon name="person_search"/> Datos del cliente</div>
          <div class="row q-col-gutter-sm">
            <q-select v-model="documentType" outlined dense hide-bottom-space :options="['CI','NIT']" label="Tipo" class="col-4 col-sm-2"/>
            <q-input v-model.trim="documentNumber" outlined dense hide-bottom-space label="Número de CI / NIT" class="col-8 col-sm-4" :loading="clientLoading"><template #append><q-icon v-if="clientFound" name="check_circle" color="positive"><q-tooltip>Cliente recuperado</q-tooltip></q-icon></template></q-input>
            <q-input v-if="documentType==='CI'" v-model.trim="documentComplement" outlined dense hide-bottom-space label="Complemento" class="col-4 col-sm-2"/>
            <div class="col-12 gt-xs"></div>
            <q-input v-model.trim="customerName" outlined dense hide-bottom-space label="Nombre / razón social" class="col-12 col-sm-6"/>
            <q-input v-model.trim="customerEmail" outlined dense hide-bottom-space type="email" label="Correo" class="col-12 col-sm-6"/>
          </div>
          <q-banner dense rounded :class="documentNumber&&documentNumber!=='0'?'bg-orange-1 text-orange-10':'bg-grey-2 text-grey-8'" class="q-mt-sm"><q-icon :name="documentNumber&&documentNumber!=='0'?'verified':'receipt'" class="q-mr-xs"/>{{documentNumber&&documentNumber!=='0'?'Se emitirá factura electrónica y el cliente quedará guardado':'Se emitirá un comprobante de venta'}}</q-banner>
          <div class="section-title q-mt-sm"><q-icon name="shopping_bag"/> Resumen de productos</div>
          <q-markup-table flat bordered dense class="summary-table"><thead><tr><th class="text-left">Producto</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th></tr></thead><tbody><tr v-for="item in cart" :key="item.line_id"><td>{{item.nombre}}</td><td class="text-right">{{quantity(item.cantidad,item.unidad)}} {{item.unidad}}</td><td class="text-right">Bs {{money(item.precio_venta)}}</td><td class="text-right text-weight-bold">Bs {{money(Number(item.precio_venta)*item.cantidad)}}</td></tr></tbody></q-markup-table>
          <div class="row q-col-gutter-sm q-mt-sm"><q-input v-model.number="discount" outlined dense type="number" min="0" :max="subtotal" step="0.01" label="Descuento" prefix="Bs" class="col-12 col-sm-4"/><q-select v-model="paymentType" outlined dense :options="paymentTypes" label="Forma de pago" class="col-12 col-sm-4"/><q-input v-if="paymentType!=='COMBINADO'" :model-value="money(total)" outlined dense readonly label="Monto a cobrar" prefix="Bs" class="col-12 col-sm-4"/><q-input v-if="paymentType==='COMBINADO'" v-model.number="cashAmount" outlined dense type="number" min="0" step="0.01" label="Efectivo" prefix="Bs" class="col-6 col-sm-4"/><q-input v-if="paymentType==='COMBINADO'" v-model.number="qrAmount" outlined dense type="number" min="0" step="0.01" label="QR" prefix="Bs" class="col-6 col-sm-4"/><q-input v-model="observation" outlined dense autogrow label="Observación" class="col-12"/></div>
          <div v-if="paymentType==='COMBINADO'" class="text-caption q-mt-xs" :class="paymentDifference===0?'text-positive':'text-negative'">Diferencia por distribuir: Bs {{money(paymentDifference)}}</div>
          <div class="row items-center q-mt-sm q-pa-sm rounded-borders bg-blue-grey-9 text-white"><div><div class="text-caption">TOTAL A COBRAR</div><div class="text-h5 text-weight-bold">Bs {{money(total)}}</div></div><q-space/><div class="text-right text-caption">{{itemCount}} productos · Descuento Bs {{money(validDiscount)}}</div></div>
        </q-card-section>
        <q-separator/><q-card-actions align="right" class="q-pa-sm bg-white"><q-btn flat dense label="Volver" no-caps v-close-popup/><q-btn color="positive" dense unelevated icon="save" label="Guardar venta · Enter" no-caps :loading="saving" @click="confirmSale"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { printSale } from '../../addons/ventaPrint'
import CompanyBanner from '../../components/CompanyBanner.vue'
const {proxy}=getCurrentInstance()
const products=ref([]),categories=ref([]),cart=ref([]),search=ref(''),category=ref(null),discount=ref(0),observation=ref(''),saving=ref(false),searchInput=ref(null)
const productDialog=ref(false),selectedProduct=ref(null),quickQuantity=ref(1),quickPrice=ref(0),quickQtyInput=ref(null)
const productsPage=ref(1),productsLastPage=ref(1),productsTotal=ref(0),productsFrom=ref(0),productsTo=ref(0),loadingProducts=ref(false),refreshingPrices=ref(false)
const productsPerPage=20
const paymentType=ref('EFECTIVO'),paymentTypes=['EFECTIVO','QR','COMBINADO'],cashAmount=ref(0),qrAmount=ref(0)
const documentType=ref('CI'),documentNumber=ref('0'),documentComplement=ref(''),customerName=ref(''),customerEmail=ref(''),customerPhone=ref(''),customerAddress=ref('')
const checkoutDialog=ref(false),clientLoading=ref(false),clientFound=ref(false)
let processingBarcode=false,clientSearchTimer=null,productsSearchTimer=null,checkoutOpenedAt=0
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,money=v=>Number(v||0).toFixed(2)
const isWeighted=item=>item?.unidad==='KG',quantityStep=item=>isWeighted(item)?0.001:1,minimumQty=item=>quantityStep(item)
const quantity=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const priceOptions=product=>[1,2,3,4].map(tier=>({label:`Precio ${tier}`,value:product?.[`precio_${tier}`]})).filter(option=>option.value!==null&&option.value!==''&&Number(option.value)>=0).map(option=>({...option,value:Number(option.value)}))
const priceOptionLabel=option=>money(typeof option==='object'?option.value:option)
const subtotal=computed(()=>cart.value.reduce((sum,i)=>sum+Number(i.precio_venta)*i.cantidad,0))
const validDiscount=computed(()=>Math.min(Math.max(Number(discount.value)||0,0),subtotal.value))
const total=computed(()=>subtotal.value-validDiscount.value)
const itemCount=computed(()=>cart.value.length)
const paymentDifference=computed(()=>Number((total.value-(Number(cashAmount.value)||0)-(Number(qrAmount.value)||0)).toFixed(2)))
watch([paymentType,total],()=>{if(paymentType.value==='EFECTIVO'){cashAmount.value=total.value;qrAmount.value=0}else if(paymentType.value==='QR'){cashAmount.value=0;qrAmount.value=total.value}else if(Number(cashAmount.value)+Number(qrAmount.value)===0){cashAmount.value=total.value;qrAmount.value=0}})
watch([documentType,documentNumber],()=>{clearTimeout(clientSearchTimer);clientFound.value=false;documentComplement.value='';customerName.value='';customerEmail.value='';customerPhone.value='';customerAddress.value='';const number=String(documentNumber.value||'').trim();if(!number||number==='0')return;clientSearchTimer=setTimeout(findCustomer,450)})
async function loadProducts(){loadingProducts.value=true;try{const {data}=await proxy.$axios.get('/productos',{params:{q:search.value,categoria_id:category.value?.id,per_page:productsPerPage,page:productsPage.value}});products.value=data.data;productsLastPage.value=data.last_page||1;productsTotal.value=data.total||0;productsFrom.value=data.from||0;productsTo.value=data.to||0;if(productsPage.value>productsLastPage.value){productsPage.value=productsLastPage.value;await loadProducts()}}finally{loadingProducts.value=false}}
async function refreshPrices(){if(refreshingPrices.value)return;refreshingPrices.value=true;try{await loadProducts();const refreshed=await Promise.all(cart.value.map(async item=>{const lookup=item.codigo_barras||item.codigo;const{data}=await proxy.$axios.get('/productos',{params:{q:lookup,per_page:20,page:1}});return exactProduct(lookup,data.data||[])}));cart.value.forEach((item,index)=>{const current=refreshed[index];if(!current)return;item.precio_venta=Number(current.precio_venta||0);item.stock_inicial=current.stock_inicial;syncLineTotal(item)});proxy.$alert.success(cart.value.length?'Precios del catálogo y carrito actualizados':'Precios actualizados')}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudieron actualizar los precios')}finally{refreshingPrices.value=false;focusSearch()}}
function resetProductsPage(){productsPage.value=1;loadProducts()}
function handleSearchInput(value){const code=String(value||'').trim();clearTimeout(productsSearchTimer);if(parseScaleBarcode(code))return;productsSearchTimer=setTimeout(resetProductsPage,200)}
function handleSearchTab(event){if(!String(event.target?.value||search.value||'').trim())return;event.preventDefault();addExact(true,event.target.value)}
function newCartLine(product,requested,price=Number(product.precio_venta||0)){cart.value.push({...product,line_id:`${product.id}-${Date.now()}-${Math.random()}`,cantidad:requested,precio_venta:price,total_editable:(price*requested).toFixed(2)})}
function add(product,amount=null){const requested=Number(amount??quantityStep(product));newCartLine(product,requested);if(amount!==null)proxy.$alert.success(`${product.nombre}: ${quantity(requested,product.unidad)} ${product.unidad} leído correctamente`)}
function openProductDialog(product,amount=null){selectedProduct.value=product;quickQuantity.value=amount??quantityStep(product);quickPrice.value=Number(product.precio_venta||0);productDialog.value=true}
function confirmProduct(){const product=selectedProduct.value,requested=Number(quickQuantity.value),price=Number(quickPrice.value);if(!product||requested<minimumQty(product))return proxy.$alert.error('Ingrese una cantidad válida');if(price<0||quickPrice.value==='')return proxy.$alert.error('Ingrese un precio válido');newCartLine(product,requested,price);productDialog.value=false;selectedProduct.value=null;search.value='';productsPage.value=1;loadProducts()}
function focusSearch(){setTimeout(()=>searchInput.value?.focus(),50)}
function parseScaleBarcode(value){const code=String(value||'').trim();if(!/^2\d{12}$/.test(code))return null;const expected=ean13CheckDigit(code.slice(0,12));if(expected!==Number(code[12]))return null;return{productCode:code.slice(0,7),weight:Number(code.slice(7,12))/1000}}
function ean13CheckDigit(firstTwelve){const sum=[...firstTwelve].reduce((total,digit,index)=>total+Number(digit)*(index%2===0?1:3),0);return(10-(sum%10))%10}
function exactProduct(code,list=products.value){const normalized=String(code||'').trim().toUpperCase();return list.find(product=>String(product.codigo||'').trim().toUpperCase()===normalized||String(product.codigo_barras||'').trim().toUpperCase()===normalized)}
function addScannedProduct(product,amount=null){add(product,amount??(isWeighted(product)?null:1));search.value='';productsPage.value=1;loadProducts();searchInput.value?.focus()}
async function addExact(showMissing=true,inputValue=null){const q=String(inputValue??search.value??'').trim().toUpperCase();if(!q){if(cart.value.length)openCheckout();return}if(processingBarcode)return;processingBarcode=true;try{const scale=parseScaleBarcode(q);const lookup=scale?.productCode||q;let product=exactProduct(lookup);if(!product){const{data}=await proxy.$axios.get('/productos',{params:{q:lookup,per_page:20,page:1}});product=exactProduct(lookup,data.data||[])}if(!product){if(showMissing)proxy.$alert.error(scale?`No existe un producto con código de balanza ${lookup}`:`No existe un producto con código ${q}`);return}if(scale&&product.unidad!=='KG'){proxy.$alert.error(`${product.nombre} debe tener unidad KG`);return}addScannedProduct(product,scale?.weight??null)}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo leer el código del producto')}finally{processingBarcode=false}}
function changeQty(item,amount){const next=Number((Number(item.cantidad)+amount).toFixed(3));if(next<minimumQty(item))return removeItem(item);item.cantidad=next;syncLineTotal(item)}
function validateQty(item){let value=Number(item.cantidad)||minimumQty(item);value=isWeighted(item)?Math.round(value*1000)/1000:Math.floor(value);item.cantidad=Math.max(minimumQty(item),value);syncLineTotal(item)}
function syncLineTotal(item){item.total_editable=(Number(item.precio_venta||0)*Number(item.cantidad||0)).toFixed(2)}
function applyLineTotal(item){const totalValue=Math.max(0,Number(item.total_editable)||0),lineQuantity=Math.max(minimumQty(item),Number(item.cantidad)||minimumQty(item));item.total_editable=totalValue.toFixed(2);item.precio_venta=Number((totalValue/lineQuantity).toFixed(4))}
function removeItem(item){cart.value=cart.value.filter(i=>i.line_id!==item.line_id)}
function clearCart(){proxy.$alert.dialog('¿Eliminar todos los productos del carrito?').onOk(()=>{cart.value=[];proxy.$alert.success('Carrito vaciado');searchInput.value?.focus()})}
function openCheckout(){cart.value.forEach(item=>{const requestedTotal=item.total_editable;validateQty(item);item.total_editable=requestedTotal;applyLineTotal(item)});if(cart.value.some(i=>Number(i.precio_venta)<0||i.precio_venta===''))return proxy.$alert.error('Revisa los precios de venta');checkoutOpenedAt=Date.now();checkoutDialog.value=true}
function handleSaleKeyboard(event){if(event.key!=='Enter'||!checkoutDialog.value||productDialog.value||saving.value||Date.now()-checkoutOpenedAt<250)return;event.preventDefault();event.stopPropagation();confirmSale()}
async function findCustomer(){clientLoading.value=true;try{const {data}=await proxy.$axios.get('/clientes/buscar',{params:{tipo_documento:documentType.value,numero_documento:String(documentNumber.value).trim()}});const customer=data.cliente;if(customer){documentComplement.value=customer.complemento||'';customerName.value=customer.nombre||'';customerEmail.value=customer.email||'';customerPhone.value=customer.telefono||'';customerAddress.value=customer.direccion||'';clientFound.value=true}}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo buscar al cliente')}finally{clientLoading.value=false}}
async function confirmSale(){if(saving.value)return;if(paymentType.value==='COMBINADO'&&paymentDifference.value!==0)return proxy.$alert.error('Efectivo y QR deben sumar el total');if(documentNumber.value!=='0'&&!customerName.value)return proxy.$alert.error('Ingresa el nombre o razón social');saving.value=true;try{const {data}=await proxy.$axios.post('/ventas',{descuento:validDiscount.value,tipo_pago:paymentType.value,monto_efectivo:cashAmount.value,monto_qr:qrAmount.value,observacion:observation.value,tipo_documento:documentType.value,numero_documento:documentNumber.value||'0',complemento:documentComplement.value||null,cliente_nombre:customerName.value||null,cliente_email:customerEmail.value||null,cliente_telefono:customerPhone.value||null,cliente_direccion:customerAddress.value||null,detalles:cart.value.map(i=>({producto_id:i.id,cantidad:i.cantidad,precio_venta:i.precio_venta}))});checkoutDialog.value=false;if(data.tipo_comprobante==='FACTURA'&&data.estado_siat!=='VALIDADA')proxy.$alert.error(`Venta guardada. Factura ${data.estado_siat}: ${data.siat_mensaje||'pendiente'}`);else proxy.$alert.success(`Venta ${data.numero} registrada`);printSale(data);cart.value=[];discount.value=0;observation.value='';documentNumber.value='0';documentComplement.value='';customerName.value='';customerEmail.value='';customerPhone.value='';customerAddress.value='';clientFound.value=false;paymentType.value='EFECTIVO';loadProducts();searchInput.value?.focus()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar la venta')}finally{saving.value=false}}
onMounted(()=>{window.addEventListener('keydown',handleSaleKeyboard);loadProducts();proxy.$axios.get('/productos-catalogos').then(r=>categories.value=r.data.categorias)})
onBeforeUnmount(()=>window.removeEventListener('keydown',handleSaleKeyboard))
</script>

<style scoped>
.product-loading{min-height:350px}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:5px;max-height:calc(100vh - 180px);overflow:auto}.product-card{transition:.15s;overflow:hidden}.product-card:hover{border-color:#f57c00;transform:translateY(-1px)}.product-card--empty{opacity:.55}.product-image{height:48px;background:#fffaf3;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}.product-name{height:32px;line-height:16px}.cart-card{position:sticky;top:62px}.cart-list{max-height:65vh;overflow:auto}.cart-item{min-height:58px;padding-top:3px;padding-bottom:3px}.cart-thumb{min-width:34px;padding-right:6px}.cart-item-content{min-width:0}.cart-item-name{line-height:14px;margin-bottom:2px}.cart-fields-row{display:flex;align-items:flex-end;gap:6px;white-space:nowrap}.price-label,.total-label{font-size:10px;line-height:11px;color:#607d8b;display:flex;flex-direction:column;gap:2px;margin:0}.price-input,.qty-input,.total-input{height:26px;border:1px solid #cfd8dc;border-radius:4px;padding:2px 6px;font-size:13px;color:#263238;background:#fff}.qty-input{width:78px}.price-input{width:94px}.total-input{width:98px;font-weight:700;color:#f57c00}.cart-actions{display:flex;align-items:center;margin-left:auto}.cart-actions .q-btn{min-width:24px;min-height:24px}.summary-table{max-height:125px;overflow:auto}.price-input:focus,.qty-input:focus,.total-input:focus{outline:1px solid #f57c00;border-color:#f57c00}@media(max-width:1023px){.cart-card{position:static}.product-grid{max-height:none}.cart-list{max-height:none}}@media(max-width:420px){.cart-thumb{display:none}.cart-fields-row{gap:3px;overflow-x:auto}.cart-actions .q-btn{min-width:20px;padding:0}.qty-input{width:68px}.price-input{width:78px}.total-input{width:82px}}
.price-select{width:110px}.price-select :deep(.q-field__control),.price-select :deep(.q-field__native){height:26px;min-height:26px}.price-select :deep(.q-field__control){padding:0 4px}.price-select :deep(.q-field__native){font-size:13px;padding:0}.price-select :deep(.q-field__append){height:26px;padding-left:0}@media(max-width:420px){.price-select{width:98px}}
</style>
