<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div>
        <div class="text-subtitle1 text-weight-bold">Productos</div>
        <div class="text-caption text-grey-7">Inventario inicial, precios e imágenes</div>
      </div>
      <q-space />
      <q-btn-dropdown dense flat color="primary" icon="download" label="Exportar" no-caps class="q-mr-xs"><q-list dense><q-item clickable v-close-popup @click="download('excel')"><q-item-section avatar><q-icon name="table_view" color="positive"/></q-item-section><q-item-section>Excel</q-item-section></q-item><q-item clickable v-close-popup @click="download('pdf')"><q-item-section avatar><q-icon name="picture_as_pdf" color="negative"/></q-item-section><q-item-section>PDF</q-item-section></q-item></q-list></q-btn-dropdown>
      <q-btn v-if="can('Editar Productos')" dense flat color="primary" icon="category" label="Categorías" no-caps class="q-mr-xs" @click="openCategories" />
      <q-btn v-if="can('Crear Productos')" dense color="primary" icon="add" label="Nuevo" no-caps @click="openForm()" />
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm">
        <q-input v-model="search" outlined dense debounce="350" class="col-12 col-md-4"
                 placeholder="Buscar por código, producto o categoría" clearable @update:model-value="load">
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <q-select v-model="category" :options="catalogs.categorias" option-label="nombre" outlined dense clearable
                  label="Categoría" class="col-6 col-md-2" @update:model-value="load" />
        <q-select v-model="unit" :options="unitOptions" outlined dense clearable label="Unidad" class="col-6 col-md-2" @update:model-value="load"/>
        <q-select v-model="stockStatus" :options="stockStatusOptions" emit-value map-options outlined dense clearable label="Estado de stock" class="col-6 col-md-2" @update:model-value="load"/>
        <q-select v-model="sortOrder" :options="sortOptions" emit-value map-options outlined dense label="Ordenar" class="col-6 col-md-2" @update:model-value="load"/>
        <q-input v-model.number="stockMin" outlined dense type="number" min="0" label="Stock mínimo" class="col-6 col-md-2" debounce="350" clearable @update:model-value="load"/>
        <q-input v-model.number="stockMax" outlined dense type="number" min="0" label="Stock máximo" class="col-6 col-md-2" debounce="350" clearable @update:model-value="load"/>
      </q-card-section>
      <q-table dense flat :rows="rows" :columns="columns" row-key="id" :loading="loading"
               v-model:pagination="pagination" :rows-per-page-options="[10,20,50,100,0]" @request="onRequest" binary-state-sort>
        <template #body-cell-foto="p"><q-td :props="p" class="drop-photo" @dragover.prevent @drop.prevent="dropPhoto($event,p.row)"><q-avatar rounded size="30px" color="grey-2"><img v-if="p.row.foto" :src="photoUrl(p.row.foto)"/><q-icon v-else name="add_photo_alternate" color="grey-5"/></q-avatar><q-tooltip>Arrastra aquí una imagen o URL</q-tooltip></q-td></template>
        <template #body-cell-precio_compra="p"><q-td :props="p">Bs {{ money(p.value) }}</q-td></template>
        <template #body-cell-precio_venta="p"><q-td :props="p">Bs {{ money(p.value) }}</q-td></template>
        <template #body-cell-codigo_barras="p"><q-td :props="p"><q-input v-model="p.row.codigo_barras" dense borderless placeholder="Escanear o escribir" input-class="text-caption" @keyup.enter="$event.target.blur()" @blur="saveBarcode(p.row)"><template #append><q-icon name="qr_code_scanner" size="16px"/></template></q-input></q-td></template>
        <template #body-cell-stock_inicial="p">
          <q-td :props="p"><q-badge :color="p.value > 10 ? 'positive' : 'orange'" :label="p.value" /></q-td>
        </template>
        <template #body-cell-actions="p">
          <q-td :props="p" class="text-left">
            <q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none">
              <q-list dense style="min-width:140px">
                <q-item v-if="can('Editar Productos')" clickable v-close-popup @click="openForm(p.row)"><q-item-section avatar><q-icon name="edit" color="primary"/></q-item-section><q-item-section>Editar</q-item-section></q-item>
                <q-item v-if="can('Eliminar Productos')" clickable v-close-popup class="text-negative" @click="remove(p.row)"><q-item-section avatar><q-icon name="delete"/></q-item-section><q-item-section>Eliminar</q-item-section></q-item>
              </q-list>
            </q-btn-dropdown>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card style="width:700px;max-width:95vw">
        <q-form @submit="save">
          <q-card-section class="row items-center">
            <div class="text-h6">{{ form.id ? 'Editar producto' : 'Nuevo producto' }}</div>
            <q-space /><q-btn flat round dense icon="close" v-close-popup />
          </q-card-section>
          <q-separator />
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <div class="col-12 col-sm-3 column items-center">
              <q-avatar rounded size="105px" color="grey-2"><img v-if="photoPreview || form.foto" :src="photoPreview || photoUrl(form.foto)"/><q-icon v-else name="add_photo_alternate" size="36px" color="grey-5"/></q-avatar>
              <q-file v-model="photo" dense borderless accept="image/*" label="Elegir fotografía" class="full-width q-mt-xs" @update:model-value="previewPhoto"/>
              <q-btn dense flat no-caps size="sm" icon="travel_explore" label="Buscar en Internet" @click="searchImage"/>
            </div>
            <div class="col-12 col-sm-9 row q-col-gutter-sm">
            <q-input v-model="form.codigo" outlined dense label="Código *" class="col-12 col-sm-4" :rules="[required]" hide-bottom-space/>
            <q-input v-model="form.codigo_barras" outlined dense label="Código de barras" class="col-12 col-sm-8"><template #prepend><q-icon name="qr_code_2"/></template></q-input>
            <q-input v-model="form.nombre" outlined dense label="Producto *" class="col-12" :rules="[required]" hide-bottom-space/>
            <q-input v-model="form.foto_url" outlined dense label="URL de imagen de Internet" class="col-12"><template #prepend><q-icon name="link"/></template></q-input>
            <q-select v-model="form.categoria_id" :options="catalogs.categorias" option-label="nombre" option-value="id" emit-value map-options outlined dense label="Categoría" class="col-12 col-sm-6"/>
            <q-select v-model="form.unidad" :options="unitOptions" use-input new-value-mode="add-unique"
                      outlined dense label="Unidad *" class="col-12 col-sm-6" :rules="[required]" />
            <q-input v-model.number="form.precio_compra" type="number" step="0.01" min="0"
                     outlined dense label="Precio compra *" prefix="Bs" class="col-12 col-sm-4" :rules="[nonNegative]" />
            <q-input v-model.number="form.precio_venta" type="number" step="0.01" min="0"
                     outlined dense label="Precio venta *" prefix="Bs" class="col-12 col-sm-4" :rules="[nonNegative]" />
            <q-input v-model.number="form.stock_inicial" type="number" min="0" :step="form.unidad==='KG'?0.001:1"
                     outlined dense :label="form.unidad==='KG'?'Stock inicial (kg) *':'Stock inicial *'" class="col-12 col-sm-4" :rules="[nonNegative]" />
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" no-caps v-close-popup />
            <q-btn color="primary" label="Guardar" no-caps type="submit" :loading="saving" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="categoriesDialog">
      <q-card style="width:620px;max-width:96vw">
        <q-card-section class="row items-center q-py-sm"><div class="text-subtitle1 text-weight-bold">Administrar categorías</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section>
        <q-separator/>
        <q-card-section class="q-pa-sm">
          <q-form class="row q-col-gutter-sm items-start" @submit="saveCategory">
            <q-input v-model="categoryForm.nombre" dense outlined label="Nombre *" class="col" :rules="[required]" hide-bottom-space/>
            <q-select v-model="categoryForm.color" dense outlined label="Color" :options="colorOptions" class="col-4"/>
            <q-btn dense unelevated color="primary" :icon="categoryForm.id?'save':'add'" :label="categoryForm.id?'Guardar':'Agregar'" no-caps type="submit"/>
            <q-btn v-if="categoryForm.id" dense flat round icon="close" @click="resetCategory"/>
          </q-form>
          <q-list dense bordered separator class="q-mt-sm rounded-borders">
            <q-item v-for="item in catalogs.categorias" :key="item.id">
              <q-item-section avatar><q-icon name="circle" :color="item.color||'primary'" size="16px"/></q-item-section>
              <q-item-section>{{item.nombre}}</q-item-section>
              <q-item-section side><div><q-btn dense flat round icon="edit" color="primary" @click="editCategory(item)"/><q-btn dense flat round icon="delete" color="negative" @click="removeCategory(item)"/></div></q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'
const { proxy } = getCurrentInstance()
const rows = ref([]), loading = ref(false), saving = ref(false), dialog = ref(false)
const categoriesDialog = ref(false)
const photo = ref(null), photoPreview = ref('')
const search = ref(''), category = ref(null), unit = ref(null), stockStatus = ref(null), stockMin = ref(null), stockMax = ref(null), sortOrder = ref('nombre_asc')
const catalogs = reactive({ categorias: [], unidades: [] })
const unitOptions = ref(['GR', 'KG', 'ML', 'LT', 'UNIDAD'])
const stockStatusOptions=[{label:'Todos',value:null},{label:'Sin stock',value:'sin_stock'},{label:'Stock bajo (1 a 10)',value:'bajo'},{label:'Disponible (más de 10)',value:'disponible'}]
const sortOptions=[{label:'Nombre A–Z',value:'nombre_asc'},{label:'Nombre Z–A',value:'nombre_desc'},{label:'Mayor stock',value:'stock_desc'},{label:'Menor stock',value:'stock_asc'},{label:'Mayor precio',value:'precio_desc'},{label:'Menor precio',value:'precio_asc'}]
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })
const empty = () => ({ id: null, codigo: '', codigo_barras: '', nombre: '', categoria_id: null, unidad: 'UNIDAD', precio_compra: 0, precio_venta: 0, stock_inicial: 0, foto: null, foto_url: '' })
const form = reactive(empty())
const categoryForm = reactive({ id:null, nombre:'', color:'primary' })
const colorOptions=['primary','blue','light-blue','purple','amber','orange','red','pink','brown','blue-grey','green']
const columns = [
  { name:'actions', label:'Acciones', align:'left' },
  { name:'foto', label:'', field:'foto', align:'left' },
  { name:'codigo', label:'Código', field:'codigo', align:'left' },
  { name:'codigo_barras', label:'Código barras', field:'codigo_barras', align:'left' },
  { name:'nombre', label:'Producto', field:'nombre', align:'left' },
  { name:'categoria', label:'Categoría', field:row=>row.categoria_relacion?.nombre||row.categoria, align:'left' },
  { name:'unidad', label:'Unidad', field:'unidad', align:'center' },
  { name:'precio_compra', label:'P. compra', field:'precio_compra', align:'right' },
  { name:'precio_venta', label:'P. venta', field:'precio_venta', align:'right' },
  { name:'stock_inicial', label:'Stock inicial', field:'stock_inicial', align:'center' }
].filter(column => column.name !== 'codigo')
const can = p => proxy.$store.hasPermission(p)
const required = v => (v !== null && v !== '') || 'Campo requerido'
const nonNegative = v => Number(v) >= 0 || 'Debe ser mayor o igual a cero'
const money = v => Number(v || 0).toFixed(2)
const photoUrl = path => `${proxy.$imgBase}/images/${path}`
function load () { onRequest({ pagination: pagination.value }) }
const filterParams=()=>({q:search.value,categoria_id:category.value?.id,unidad:unit.value,estado_stock:stockStatus.value,stock_min:stockMin.value,stock_max:stockMax.value,orden:sortOrder.value})
function onRequest ({ pagination: p }) {
  loading.value = true
  proxy.$axios.get('/productos', { params:{...filterParams(),page:p.page,per_page:p.rowsPerPage} })
    .then(({ data }) => { rows.value=data.data; pagination.value={ ...p, rowsNumber:data.total } })
    .catch(e => proxy.$alert.error(e.response?.data?.message || 'No se pudieron cargar los productos'))
    .finally(() => { loading.value=false })
}
async function download(type){try{const response=await proxy.$axios.get(`/productos-exportar/${type}`,{params:filterParams(),responseType:'blob'});const url=URL.createObjectURL(response.data),a=document.createElement('a');a.href=url;a.download=`productos.${type==='excel'?'xlsx':'pdf'}`;a.click();URL.revokeObjectURL(url)}catch{proxy.$alert.error('No se pudo exportar el reporte')}}
function loadCatalogs () {
  return proxy.$axios.get('/productos-catalogos').then(({data}) => {
    catalogs.categorias=data.categorias
    unitOptions.value=[...new Set([...unitOptions.value, ...data.unidades])]
  })
}
function openForm (row=null) { Object.assign(form, empty(), row || {}); photo.value=null; photoPreview.value=''; dialog.value=true }
async function saveBarcode (row) {
  if (!can('Editar Productos')) return
  try { await proxy.$axios.patch(`/productos/${row.id}/codigo-barras`, { codigo_barras:row.codigo_barras || null }); proxy.$alert.success('Código de barras actualizado') }
  catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||'No se pudo actualizar');load()}
}
function openCategories () { resetCategory(); categoriesDialog.value=true }
function resetCategory () { Object.assign(categoryForm,{id:null,nombre:'',color:'primary'}) }
function editCategory (item) { Object.assign(categoryForm,item) }
async function saveCategory () {
  try {
    if(categoryForm.id) await proxy.$axios.put(`/categorias/${categoryForm.id}`,categoryForm)
    else await proxy.$axios.post('/categorias',categoryForm)
    proxy.$alert.success('Categoría guardada');resetCategory();await loadCatalogs();load()
  } catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}
}
function removeCategory (item) { proxy.$alert.dialog(`¿Eliminar la categoría ${item.nombre}?`).onOk(async()=>{try{await proxy.$axios.delete(`/categorias/${item.id}`);proxy.$alert.success('Categoría eliminada');loadCatalogs()}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')}}) }
function previewPhoto (file) { if (photoPreview.value) URL.revokeObjectURL(photoPreview.value); photoPreview.value=file ? URL.createObjectURL(file) : '' }
function searchImage () { window.open(`https://www.google.com/search?tbm=isch&q=${encodeURIComponent(form.nombre || 'producto')}`, '_blank', 'noopener') }
async function uploadUrl (id, url) { await proxy.$axios.post(`/productos/${id}/foto-url`, { url }) }
async function dropPhoto (event, row) {
  if (!can('Editar Productos')) return
  try {
    const file=event.dataTransfer.files?.[0]
    if(file){const data=new FormData();data.append('foto',file);await proxy.$axios.post(`/productos/${row.id}/foto`,data)}
    else {
      const url=event.dataTransfer.getData('text/uri-list') || event.dataTransfer.getData('text/plain')
      if(!url) throw new Error('Sin imagen')
      await uploadUrl(row.id,url.trim())
    }
    proxy.$alert.success('Fotografía actualizada');load()
  } catch(e){proxy.$alert.error(e.response?.data?.message || 'No se pudo cargar la imagen arrastrada')}
}
async function save () {
  saving.value=true
  try {
    const response=form.id ? await proxy.$axios.put(`/productos/${form.id}`, form) : await proxy.$axios.post('/productos', form)
    if(photo.value){const data=new FormData();data.append('foto',photo.value);await proxy.$axios.post(`/productos/${response.data.id}/foto`,data)}
    else if(form.foto_url){await uploadUrl(response.data.id,form.foto_url)}
    proxy.$alert.success('Producto guardado');dialog.value=false;load();loadCatalogs()
  } catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'No se pudo guardar')}
  finally{saving.value=false}
}
function remove (row) {
  proxy.$alert.dialog(`¿Eliminar ${row.nombre}?`).onOk(() => proxy.$axios.delete(`/productos/${row.id}`)
    .then(() => { proxy.$alert.success('Producto eliminado'); load() })
    .catch(e => proxy.$alert.error(e.response?.data?.message || 'No se pudo eliminar')))
}
onMounted(() => { load(); loadCatalogs() })
</script>

<style scoped>
.drop-photo{border:1px dashed transparent;transition:.15s}.drop-photo:hover{border-color:#f57c00;background:#fff3e0}
</style>
