<script setup lang="js">
import LoaderComponent from "@/components/LoaderComponent.vue";
import NavegationIaAutoOrder from "@/components/NavegationIaAutoOrder.vue";
import OrderProductListTable from "@/components/OrderProductListTable.vue";
import ProductsExceededDidNotToleranceTable from "@/components/ProductsExceededDidNotToleranceTable.vue";
import ProductsExceededToleranceTable from "@/components/ProductsExceededToleranceTable.vue";
import ProductsStablePriceTable from "@/components/ProductsStablePriceTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route= useRoute()
const router= useRouter()

// console.log(route.query)
const pageOportunidad = ref(1);

const indexNavegacion=ref(1)
const showNoEncontradosModal = ref(false);
const searchNoEncontrados = ref('');
const pageNoEncontrados = ref(1);
const itemsPerPageNoEncontrados = 15;

const module = reactive({
  dataProductos: {},
  productoFallas: [],
  productosOportunidadUnica: { data: [], current_page: 1, last_page: 1, total: 0 },
  detalleOrder: [],
  productosSinReponer: [],
  manualQuantities: {},
  excludedLaboratories: [],
  excludeColombian: false,
  overcostMinOne: false,
  availableLaboratories: [],
  totalFallas: 0,
  loadingApp: true,
})

let gruposList=(route.query.groups)?JSON.parse(route.query.groups):[]
let laboratoriosList=(route.query.laboratoryId)?JSON.parse(route.query.laboratoryId):[]


const con_descuento= ref(route.query.con_descuento);// descuento o precio full
const tipo_de_filtracion= ref(route.query.tipo_filtracion);
const lapso_de_tiempo= ref(route.query.lapso_de_tiempo);
const stock = ref(route.query.stock || 'fallas');
const isColombian = ref(route.query.isColombian === 'true');
const groups= ref(gruposList);
const laboratoryId= ref(laboratoriosList);
const q = ref(route.query.q || "");

async function generarPedido(page = 1) {
  let data = {
    "con_descuento": con_descuento.value,
    "tipo_filtracion": tipo_de_filtracion.value,
    "lapso_de_tiempo": lapso_de_tiempo.value,
    "stock": stock.value,
    "isColombian": isColombian.value,
    "groups": groups.value,
    "laboratoryId": laboratoryId.value,
    "q": q.value,
    "page": page
  }

  const respuestaApi = await axios.post(`/suppliers-ia-order-assistant/generate-order/products-to-request`, data);
  return { ...respuestaApi.data };
}

async function fetchSoloOportunidad(page = 1) {
  let data = {
    "con_descuento": con_descuento.value,
    "tipo_filtracion": tipo_de_filtracion.value,
    "lapso_de_tiempo": lapso_de_tiempo.value,
    "stock": stock.value,
    "isColombian": isColombian.value,
    "groups": groups.value,
    "laboratoryId": laboratoryId.value,
    "q": q.value,
    "page": page
  }
  const respuestaApi = await axios.post(`/suppliers-ia-order-assistant/generate-order/unique-opportunity-page`, data);
  return respuestaApi.data.data;
}

async function handleChangePageOportunidad(newPage) {
  module.loadingApp = true;
  pageOportunidad.value = newPage;

  try {
    let paginacionData = await fetchSoloOportunidad(newPage);

    if (paginacionData && paginacionData.data) {
      paginacionData.data = paginacionData.data.map(a => {
        a.uuid = generateUUID();
        return a;
      });

      module.productosOportunidadUnica = paginacionData;
    }
  } catch (error) {
    console.error("Error cambiando pagina", error);
    toast.error("Error al cargar la página");
  } finally {
    module.loadingApp = false;
  }
}

function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

function hydrateItem(item) {
  item.uuid = generateUUID();
  if (module.manualQuantities[item.productSupplier.id] !== undefined) {
    item.reponer = module.manualQuantities[item.productSupplier.id];
  } else if (module.overcostMinOne && item.increase === true && (!item.reponer || item.reponer < 1)) {
      item.reponer = 1;
      module.manualQuantities[item.productSupplier.id] = 1;
  }
  return item;
}

function extractLaboratories(data) {
  const labs = new Map();
  const listsToCheck = [
    data.productos_a_reponer || [],
    data.productos_oportunidad_unica?.data || [],
    module.productoFallas || [],
    module.productosOportunidadUnica?.data || []
  ];

  listsToCheck.forEach(list => {
    list.forEach(item => {
      if (item.product?.laboratory) {
        labs.set(item.product.laboratory.id, item.product.laboratory);
      }
    });
  });

  if (labs.size > 0) {
    // Mantener los existentes y agregar nuevos
    const currentLabs = new Map(module.availableLaboratories.map(l => [l.id, l]));
    labs.forEach((val, key) => currentLabs.set(key, val));
    module.availableLaboratories = Array.from(currentLabs.values()).sort((a,b) => a.name.localeCompare(b.name));
  }
}

function procesarRespuesta(data) {
  extractLaboratories(data.data);

  // Asignar productos a reponer (Fallas visibles en tabla)
  if(data.data.productos_a_reponer) {
      data.data.productos_a_reponer = data.data.productos_a_reponer.map(hydrateItem);
      module.productoFallas = [...data.data.productos_a_reponer];
  }

  if(data.data.productosFallas) {
      module.productosSinReponer = [...data.data.productosFallas];
  }

  if (data.data.productos_oportunidad_unica && data.data.productos_oportunidad_unica.data) {
    data.data.productos_oportunidad_unica.data = data.data.productos_oportunidad_unica.data.map(hydrateItem);
    module.productosOportunidadUnica = data.data.productos_oportunidad_unica;
  } else {
    module.productosOportunidadUnica = { data: [], total: 0 };
  }

  module.totalFallas = data.data.totalFallas || 0;
  module.dataProductos = { ...data.data };
}

onMounted(async () => {
  module.loadingApp = true;
  try {
    let data = await generarPedido(1);
    procesarRespuesta(data);
  } catch (error) {
    console.error(error);
  } finally {
    module.loadingApp = false;
  }
});

import { watch } from "vue";

// Registrar cambios manuales para persistencia
watch(() => [module.productoFallas, module.productosOportunidadUnica.data], () => {
  const allItems = [...module.productoFallas, ...(module.productosOportunidadUnica?.data || [])];
  allItems.forEach(item => {
    if (item.reponer !== undefined && item.productSupplier?.id) {
      module.manualQuantities[item.productSupplier.id] = item.reponer;
    }
  });
}, { deep: true });

// Regla de sobre costo: al activar, aplicar a lo actual. Al desactivar, se mantienen manuales.
watch(() => module.overcostMinOne, (val) => {
  if (val) {
    const applyRule = (item) => {
      if (item.increase === true && (!item.reponer || item.reponer < 1)) {
        item.reponer = 1;
        module.manualQuantities[item.productSupplier.id] = 1;
      }
    };
    module.productoFallas.forEach(applyRule);
    module.productosOportunidadUnica.data?.forEach(applyRule);
  }
});

const FALLAS_FILTRADAS = computed(() => {
  return module.productoFallas.filter(item => {
    if (module.excludeColombian && item.product.is_colombian_origin) return false;
    if (module.excludedLaboratories.length > 0 && module.excludedLaboratories.includes(item.product.laboratory?.id)) return false;
    return true;
  });
});

const OPORTUNIDAD_FILTRADA = computed(() => {
  if (!module.productosOportunidadUnica?.data) return { data: [], current_page: 1, last_page: 1, total: 0 };
  
  const filteredData = module.productosOportunidadUnica.data.filter(item => {
    if (module.excludeColombian && item.product.is_colombian_origin) return false;
    if (module.excludedLaboratories.length > 0 && module.excludedLaboratories.includes(item.product.laboratory?.id)) return false;
    return true;
  });

  return { 
    ...module.productosOportunidadUnica, 
    data: filteredData,
    total: filteredData.length // Ojo: esto es total local, si hay paginación real el total lo da el server
  };
});



function actualizarIndexNavegacion(payload){
  indexNavegacion.value = payload;

  if(payload <= 0){
    router.push("/suppliers/supplieriaorderassistant")
  }

  if(payload == 3 || payload == 4){
    seleccionarProductosParaElDetalle()
  }
}

function actualizarCantidadAReponerProductosEnFalla(productosEnFalla,productosConOportunidadUnica){
  for (let index = 0; index < productosEnFalla.length; index++) {
    let productEnFalla= productosEnFalla[index];

    for (let index2 = 0; index2 < productosConOportunidadUnica.length; index2++) {
      let productConOportunidadUnica = productosConOportunidadUnica[index2];
      if(productEnFalla.productSupplier.id==productConOportunidadUnica.productSupplier.id){
        productConOportunidadUnica.reponer=productEnFalla.reponer
      }
      productosConOportunidadUnica[index2]=productConOportunidadUnica

    }

  }
  return productosConOportunidadUnica

}

function seleccionarProductosParaElDetalle(){
  module.detalleOrder = []
  const listaOportunidad = OPORTUNIDAD_FILTRADA.value?.data || [];

  let productosEnFalla = verificarSiHayProductosEnFallaEnLaLista(
      [...FALLAS_FILTRADAS.value],
      [...listaOportunidad]
  )

  let productosSinFallas = removerProductosConProveedores(
      [...productosEnFalla],
      [...listaOportunidad]
  )

  let detalles = [...productosEnFalla, ...productosSinFallas]
  detalles = detalles.filter(producto => producto.reponer > 0)

  module.detalleOrder = detalles
}

// esta funcion es para remover los productos que estan en la lista de productos en falla de productos oportunidad unica
function removerProductosConProveedores(productosEnFalla,productosOportunidadUnica){
  for (let index = 0; index < productosEnFalla.length; index++) {
    const producto = productosEnFalla[index];
    productosOportunidadUnica=productosOportunidadUnica.filter(productUnique => !(producto.product.id==productUnique.product.id && producto.supplier.id==productUnique.supplier.id))

  }
  return productosOportunidadUnica
}


function verificarSiHayProductosEnFallaEnLaLista(productosEnFalla,listaDeProductosOportunidaUnica){

  for (let index = 0; index < productosEnFalla.length; index++) {
    const producto = productosEnFalla[index];
    let buscarSiTieneOportunidadUnica=listaDeProductosOportunidaUnica.find(productUnique => producto.product.id==productUnique.product.id && producto.supplier.id==productUnique.supplier.id)
    if(buscarSiTieneOportunidadUnica){
      if(buscarSiTieneOportunidadUnica.reponer > producto.reponer || buscarSiTieneOportunidadUnica.reponer < producto.reponer){
        productosEnFalla[index]=buscarSiTieneOportunidadUnica
      }
    }
  }
  return productosEnFalla;

}




const TOTAL_ORDER= computed(() => {
  let listaSubTotales=module.detalleOrder.map(de => de.reponer * de.precio_final_supplier)
  const total=listaSubTotales.reduce((acumulador, valorActual) => acumulador + valorActual, 0)
  return (module.detalleOrder.length>0)?total:0
})

// KPI: productos de la lista de fallas que SÍ tienen un proveedor asignado para reponer
const KPIS_ENCONTRADOS = computed(() => {
  if (!FALLAS_FILTRADAS.value) return 0;
  const uniqueIds = new Set(FALLAS_FILTRADAS.value.map(item => item.product?.id).filter(id => id));
  return uniqueIds.size;
})

// KPI: fallas sin proveedor = total fallas real - los que sí encontraron proveedor
const KPIS_NO_ENCONTRADOS = computed(() => {
  const totalFallasReal = module.totalFallas || 0;
  return Math.max(0, totalFallasReal - KPIS_ENCONTRADOS.value);
})

// Lista de productos sin proveedor: aquellos en productosSinReponer que no tienen cobertura en productoFallas
const LISTA_NO_ENCONTRADOS = computed(() => {
  const idsConCobertura = new Set(FALLAS_FILTRADAS.value.map(item => item.product?.id).filter(id => id));
  return module.productosSinReponer.filter(p => !idsConCobertura.has(p.id));
})

// Lista filtrada por el buscador del modal
const FILTRADA_NO_ENCONTRADOS = computed(() => {
  if (!searchNoEncontrados.value) return LISTA_NO_ENCONTRADOS.value;
  const q = searchNoEncontrados.value.toLowerCase();
  return LISTA_NO_ENCONTRADOS.value.filter(p => 
    p.name?.toLowerCase().includes(q) || 
    p.id?.toString().includes(q) ||
    p.laboratory?.name?.toLowerCase().includes(q)
  );
})

// Lista paginada para la tabla del modal
const PAGINADA_NO_ENCONTRADOS = computed(() => {
  const start = (pageNoEncontrados.value - 1) * itemsPerPageNoEncontrados;
  const end = start + itemsPerPageNoEncontrados;
  return FILTRADA_NO_ENCONTRADOS.value.slice(start, end);
})

// Total de páginas para el modal
const TOTAL_PAGINAS_NO_ENCONTRADOS = computed(() => {
  return Math.ceil(FILTRADA_NO_ENCONTRADOS.value.length / itemsPerPageNoEncontrados);
})

function abrirModalNoEncontrados() {
  pageNoEncontrados.value = 1;
  searchNoEncontrados.value = '';
  showNoEncontradosModal.value = true;
}

const LISTA_PORVEEDORES_TOTAL= computed(() => {
  let keyHashIdProveedor={}
  for (let index = 0; index < module.detalleOrder.length; index++) {
    const item =  module.detalleOrder[index];
    if(!keyHashIdProveedor[item.supplier.id]){
      keyHashIdProveedor[item.supplier.id]={
        name:item.supplier.name,
        total: parseFloat(item.precio_final_supplier).toFixed(2) * item.reponer,
      }
    }
    else{
       keyHashIdProveedor[item.supplier.id].total=keyHashIdProveedor[item.supplier.id].total+(parseFloat(item.precio_final_supplier).toFixed(2) * item.reponer)
    }
  }
  let lista=[]
  for (const key in keyHashIdProveedor) {
    lista.push(keyHashIdProveedor[key])
  }
  return lista

})


async function confirmarCompra(){
  // Validar que haya productos en el detalle
  if (!module.detalleOrder || module.detalleOrder.length === 0) {
    toast.warning("No hay productos para generar la compra. Agrega productos al detalle primero.");
    return;
  }

  // Validar que todos los productos tengan cantidad mayor a 0
  const productosSinCantidad = module.detalleOrder.filter(item => !item.reponer || item.reponer <= 0);
  if (productosSinCantidad.length > 0) {
    toast.warning(`Hay ${productosSinCantidad.length} producto(s) sin cantidad asignada. Por favor, asigna una cantidad mayor a 0.`);
    return;
  }

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: `Se generarán ${module.detalleOrder.length} producto(s) en la compra. Esta acción no se podrá revertir.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'No, ¡Cancelar!',
    buttonsStyling: false,
    customClass: {
      cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2',
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated',
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    await realizarCompra()
  }
}

function formatiarData(data){
  let supplier={}
  let orders=[]
  for (let index = 0; index < data.length; index++) {
    const productSupplier = data[index];
    if(!supplier[productSupplier.supplier.id]){
      supplier[productSupplier.supplier.id]=[productSupplier.product.id]
      let detalleProductoOrder={
        "product_suppliers_id":productSupplier.productSupplier.id,
        "quantity":productSupplier.reponer,
        "unit_cost":productSupplier.precio_final_supplier,
        "subtotal":productSupplier.reponer * productSupplier.precio_final_supplier,
      }

      let orderSupplier={
        "supplier_id":productSupplier.supplier.id,
        "total_items":supplier[productSupplier.supplier.id].length,
        "total_quantity":parseInt(productSupplier.reponer) || 0,
        "total_amount":productSupplier.reponer * productSupplier.precio_final_supplier,
        "details":[detalleProductoOrder],
      }

      orders.push(orderSupplier)
    }
    else{
      let indexOrderSupplier=orders.findIndex(o => o.supplier_id==productSupplier.supplier.id)
      let orderSupplier=orders[indexOrderSupplier]
      if(!supplier[productSupplier.supplier.id].includes(productSupplier.product.id)){
        supplier[productSupplier.supplier.id].push(productSupplier.product.id)
      }

      orderSupplier.total_items=supplier[productSupplier.supplier.id].length
      orderSupplier.total_quantity=(parseInt(orderSupplier.total_quantity) || 0) + (parseInt(productSupplier.reponer) || 0)
      orderSupplier.total_amount=orderSupplier.total_amount+(productSupplier.reponer * productSupplier.precio_final_supplier)
      let detalleProductoOrder={
        "product_suppliers_id":productSupplier.productSupplier.id,
        "quantity":productSupplier.reponer,
        "unit_cost":productSupplier.precio_final_supplier,
        "subtotal":productSupplier.reponer * productSupplier.precio_final_supplier,
      }
      orderSupplier.details.push(detalleProductoOrder)
      orders[indexOrderSupplier]=orderSupplier
    }
  }

  return orders
}

async function realizarCompra(){
  try {
    module.loadingApp=true
    
    // Validación adicional
    if (!module.detalleOrder || module.detalleOrder.length === 0) {
      toast.error("No hay productos para generar la compra")
      module.loadingApp=false
      return
    }

    const idsEnCompra = module.detalleOrder.map(item => item.product.id);
    let orders=formatiarData([...module.detalleOrder])
    
    if (!orders || orders.length === 0) {
      toast.error("Error al formatear los datos de la compra")
      module.loadingApp=false
      return
    }

    const without_supplier_ids = module.productosSinReponer
      .filter(p => !idsEnCompra.includes(p.id))
      .map(p => p.id);



    const DATA = {
      orders,
      without_supplier_ids // Enviamos los IDs para marcarlos en la base de datos
    };
    let response = await axios.post("/suppliers-ia-order-assistant/generate-order/creat", DATA);
    
    if (response.status !== 200) {
      module.loadingApp=false
      toast.error("Error al generar la compra")
      return
    }
    
    module.loadingApp=false
    toast.success("Compra realizada con éxito")
    
    // Redirigir al comparador de productos, pestaña productos, filtro fallas
    router.push({
      path: "/suppliers/product-comparator/list",
      query: { tab: "products", stock: "fallas" }
    })
  } catch (error) {
    console.error("Error al realizar compra:", error)
    module.loadingApp=false
    toast.error(error.response?.data?.message || "Error al generar la compra. Por favor, intenta nuevamente.")
  }
}

async function consultarProductosSinProveedor(){

  const idsQueSeEstanComprando = new Set(module.productoFallas.map(item => item.product?.id).filter(id => id));
  let productos = module.productosSinReponer.filter(p => !idsQueSeEstanComprando.has(p.id));

  let ids = productos.map(p => p.id)

  let idsConFantante = productos.map(p => {
    return {
      "id": p.id,
      "solicitar": p.solicitar,
    }
  })

  // console.log("ids filtrados para pdf => ", ids)

  let data = {
    "tipo_filtracion": tipo_de_filtracion.value,
    "lapso_de_tiempo": lapso_de_tiempo.value,
    ids,
    idsConFantante
  }

  if (ids.length === 0) {
      toast.info("No hay productos pendientes sin proveedor para generar reporte.");
      return [];
  }

  let response = await axios.post("/suppliers-ia-order-assistant/generate-order/products-without-supplier", data)

  if(response.status != 200){
    toast.error("Error al generar el reporte de productos sin proveedor")
    return []
  }

  toast.success("Reporte Generado")
  return [...response.data.data]
}


function eliminarItemOrden(payload){
  console.log("payload => ",payload)

  module.detalleOrder=module.detalleOrder.filter(itemOrder => itemOrder.uuid!=payload.uuid)
}
</script>
<template>
  <LoaderComponent :loadingApp="module.loadingApp" />
  <div class="generate-order-view pb-12 text-sm">

    <!-- Configuraciones de Pedido -->
    <VCard class="mx-6 mb-6 rounded-lg border shadow-sm overflow-hidden bg-var-theme-background">
      <div class="pa-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-settings-automation" color="primary" />
            <span class="text-subtitle-1 font-weight-black">Configuraciones de Pedido</span>
          </div>
          <div class="d-flex align-center flex-wrap gap-6">
            <!-- Excluir Laboratorios -->
            <div style="min-inline-size: 300px;">
              <VSelect
                v-model="module.excludedLaboratories"
                :items="module.availableLaboratories"
                item-title="name"
                item-value="id"
                label="Excluir Laboratorios"
                placeholder="Selecciona labs a quitar"
                multiple
                chips
                closable-chips
                density="compact"
                variant="outlined"
                hide-details
                collapse-chips
              />
            </div>

            <!-- Excluir Colombianos -->
            <VSwitch
              v-model="module.excludeColombian"
              label="Ocultar Colombianos"
              color="error"
              density="compact"
              hide-details
              inset
            />

            <!-- Sobre Costo Min 1 -->
            <VBtn
                :color="module.overcostMinOne ? 'success' : 'secondary'"
                variant="tonal"
                size="small"
                class="font-weight-bold"
                prepend-icon="tabler-trending-up"
                @click="module.overcostMinOne = !module.overcostMinOne"
            >
                Sobre Costo: {{ module.overcostMinOne ? 'Min 1' : 'Sugerido' }}
            </VBtn>
          </div>
        </div>
      </div>
    </VCard>

    <div class="px-6 d-flex flex-column gap-6">
      <NavegationIaAutoOrder
        :index-navegacion="indexNavegacion"
        :encontrados="KPIS_ENCONTRADOS"
        :no-encontrados="KPIS_NO_ENCONTRADOS"
        @actualizar-index-navegacion="actualizarIndexNavegacion"
        @ver-no-encontrados="abrirModalNoEncontrados"
      />

      <VCard class="rounded-lg border shadow-sm overflow-hidden" v-if="indexNavegacion == 1">
        <template #title>
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-trending-up" color="error" size="22" />
            <span class="text-h6 font-weight-black">Productos con Costo Elevado</span>
            <VChip
              color="error"
              variant="tonal"
              size="small"
              class="font-weight-black"
            >
              {{ module.productoFallas.filter((pro) => pro.increase == true).length }}
            </VChip>
          </div>
        </template>
        <ProductsExceededToleranceTable :list="FALLAS_FILTRADAS" />
      </VCard>
      <VCard class="rounded-lg border shadow-sm overflow-hidden" v-if="indexNavegacion == 2">
        <template #title>
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-trending-down" color="success" size="22" />
            <span class="text-h6 font-weight-black">Productos con Costo Bajo</span>
            <VChip
              color="success"
              variant="tonal"
              size="small"
              class="font-weight-black"
            >
              {{ FALLAS_FILTRADAS.filter((pro) => pro.increase == false).length }}
            </VChip>
          </div>
        </template>
        <ProductsExceededDidNotToleranceTable :list="FALLAS_FILTRADAS" />
      </VCard>
      <VCard class="rounded-lg border shadow-sm overflow-hidden" v-if="indexNavegacion == 3">
        <template #title>
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-minus" color="info" size="22" />
            <span class="text-h6 font-weight-black">Productos con Precio Estable</span>
            <VChip
              color="info"
              variant="tonal"
              size="small"
              class="font-weight-black"
            >
              {{ FALLAS_FILTRADAS.filter((pro) => pro.increase === null).length }}
            </VChip>
          </div>
        </template>
        <ProductsStablePriceTable :list="FALLAS_FILTRADAS" />
      </VCard>

    <div v-if="indexNavegacion == 4">
      <VRow>
        <VCol
          order="2"
          order-sm="2"
          order-md="1"
          order-lg="1"
          sm="12"
          md="12"
          lg="9"
        >
          <VCard title="IA Detalle" class="">
            <OrderProductListTable
              :list="module.detalleOrder"
              @eliminar-item-orden="eliminarItemOrden"
            />
          </VCard>
        </VCol>
        <VCol
          order="1"
          order-sm="1"
          order-md="2"
          order-lg="2"
          sm="12"
          md="12"
          lg="3"
        >
          <VCard variant="outlined" class="rounded-lg mb-5 overflow-hidden">
            <div class="pa-4 bg-light-primary border-b">
              <span class="text-subtitle-1 font-weight-black text-uppercase d-flex align-center gap-2">
                <VIcon icon="tabler-list-details" size="20" />
                Resumen de Orden
              </span>
            </div>
            
            <VCardText class="pa-0">
              <VList v-if="LISTA_PORVEEDORES_TOTAL.length > 0" density="compact" lines="one">
                <VListItem
                  v-for="totalesProveedores in LISTA_PORVEEDORES_TOTAL"
                  :key="totalesProveedores.name"
                  class="px-6 py-2"
                >
                  <template #prepend>
                    <VIcon icon="tabler-building-store" size="18" class="text-disabled" />
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    {{ totalesProveedores.name }}
                  </VListItemTitle>
                  <template #append>
                    <span class="text-body-2 font-weight-black">
                      ${{ totalesProveedores.total.toFixed(2) }}
                    </span>
                  </template>
                </VListItem>
              </VList>

              <VDivider />

              <div class="pa-6 d-flex align-center justify-space-between bg-light-primary">
                <span class="text-h6 font-weight-bold">Total General</span>
                <div class="d-flex align-center gap-1">
                  <span class="text-h5 font-weight-black text-primary">
                    ${{ TOTAL_ORDER ? TOTAL_ORDER.toFixed(2) : '0.00' }}
                  </span>
                  <VIcon icon="tabler-currency-dollar" color="primary" />
                </div>
              </div>
            </VCardText>
          </VCard>

          <VBtn
            color="primary"
            variant="elevated"
            size="large"
            class="w-100 font-weight-black"
            prepend-icon="tabler-shopping-cart-check"
            :disabled="!module.detalleOrder || module.detalleOrder.length === 0"
            @click="confirmarCompra"
          >
            CONFIRMAR COMPRA ({{ module.detalleOrder?.length || 0 }} ítems)
          </VBtn>
        </VCol>
      </VRow>
    </div>
  </div>

  <!-- Modal: Productos Sin Proveedor -->
  <VDialog v-model="showNoEncontradosModal" max-width="860" scrollable>
    <VCard class="rounded-lg overflow-hidden">
      <!-- Header -->
      <div class="d-flex align-center justify-space-between px-6 py-4" style="background: linear-gradient(135deg, #ff9800 0%, #f44336 100%)">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="flat" size="38" class="rounded-lg">
            <VIcon icon="tabler-alert-triangle" color="warning" size="20" />
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-black text-white">Productos Sin Proveedor</div>
            <div class="text-caption text-white opacity-80">{{ LISTA_NO_ENCONTRADOS.length }} productos sin oferta asignada</div>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" color="white" size="small" @click="showNoEncontradosModal = false" />
      </div>

      <!-- Buscador -->
      <div class="px-6 py-3 border-b bg-var-theme-background">
        <VTextField
          v-model="searchNoEncontrados"
          placeholder="Buscar por nombre, ID o laboratorio..."
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-search"
          hide-details
          clearable
          @update:model-value="pageNoEncontrados = 1"
        />
      </div>

      <!-- Tabla -->
      <VCardText class="pa-0" style="min-height: 400px;">
        <div v-if="FILTRADA_NO_ENCONTRADOS.length === 0" class="d-flex flex-column align-center py-10 text-disabled">
          <VIcon icon="tabler-package-off" size="48" class="mb-3" />
          <span class="text-body-1 font-weight-medium">No hay productos que coincidan</span>
        </div>
        <VTable v-else density="compact" class="text-sm">
          <thead>
            <tr class="bg-surface">
              <th class="font-weight-black text-uppercase text-caption">ID</th>
              <th class="font-weight-black text-uppercase text-caption">Producto</th>
              <th class="font-weight-black text-uppercase text-caption text-right">Stock</th>
              <th class="font-weight-black text-uppercase text-caption text-right">Solicitado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="prod in PAGINADA_NO_ENCONTRADOS" :key="prod.id">
              <td class="text-primary font-weight-black text-sm">
                <a :href="'/inventory/traceability?q=' + prod.id" target="_blank" class="text-decoration-none text-primary">{{ prod.id }}</a>
              </td>
              <td>
                <div class="text-high-emphasis font-weight-bold text-uppercase text-sm" style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ prod.name }}</div>
                <div class="text-caption text-disabled">{{ prod.laboratory?.name || 'S/L' }}</div>
              </td>
              <td class="text-right">
                <VChip size="x-small" :color="(prod.lote_quantity || 0) > 0 ? 'success' : 'error'" variant="tonal" label>
                  {{ prod.lote_quantity || 0 }}
                </VChip>
              </td>
              <td class="text-right font-weight-black text-warning">{{ prod.stockFaltante ?? prod.solicitar ?? '—' }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VDivider />
      <VCardActions class="px-6 py-2 d-flex align-center justify-space-between bg-var-theme-background">
        <div class="text-caption text-disabled">
          Mostrando {{ PAGINADA_NO_ENCONTRADOS.length }} de {{ FILTRADA_NO_ENCONTRADOS.length }} resultados
        </div>
        <VPagination
          v-model="pageNoEncontrados"
          :length="TOTAL_PAGINAS_NO_ENCONTRADOS"
          :total-visible="5"
          density="comfortable"
          size="small"
        />
        <VBtn variant="tonal" color="secondary" size="small" @click="showNoEncontradosModal = false">Cerrar</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</div>
</template>

<style scoped>
.generate-order-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.header-bg {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #4a90e2 100%);
}

.letter-spacing-tight { letter-spacing: -0.02em; }
.letter-spacing-widest { letter-spacing: 0.1em !important; }

.shadow-soft { box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important; }

.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}

:deep(.v-card-title) {
  padding-block: 1.25rem;
  padding-inline: 1.5rem;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
  background-color: #fff;
}
</style>

