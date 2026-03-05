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

const module = reactive({
  dataProductos: {},
  productoFallas: [],
  productosOportunidadUnica: { data: [], current_page: 1, last_page: 1, total: 0 },
  detalleOrder: [],
  productosSinReponer: [],
  loadingApp: true,
})

let gruposList=(route.query.groups)?JSON.parse(route.query.groups):[]
let laboratoriosList=(route.query.laboratoryId)?JSON.parse(route.query.laboratoryId):[]


const con_descuento= ref(route.query.con_descuento);// descuento o precio full
const tipo_de_filtracion= ref(route.query.tipo_filtracion);
const lapso_de_tiempo= ref(route.query.lapso_de_tiempo);
const groups= ref(gruposList);
const laboratoryId= ref(laboratoriosList);

async function generarPedido(page = 1) {
  let data = {
    "con_descuento": con_descuento.value,
    "tipo_filtracion": tipo_de_filtracion.value,
    "lapso_de_tiempo": lapso_de_tiempo.value,
    "groups": groups.value,
    "laboratoryId": laboratoryId.value,
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
    "groups": groups.value,
    "laboratoryId": laboratoryId.value,
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

function procesarRespuesta(data) {
  // Asignar productos a reponer (Fallas visibles en tabla)
  if(data.data.productos_a_reponer) {
      data.data.productos_a_reponer = data.data.productos_a_reponer.map(a => {
        a.uuid = generateUUID()
        return a
      })
      module.productoFallas = [...data.data.productos_a_reponer];
  }

  if(data.data.productosFallas) {
      module.productosSinReponer = [...data.data.productosFallas];
  }

  if (data.data.productos_oportunidad_unica && data.data.productos_oportunidad_unica.data) {
    data.data.productos_oportunidad_unica.data = data.data.productos_oportunidad_unica.data.map(a => {
      a.uuid = generateUUID()
      return a
    })
    module.productosOportunidadUnica = data.data.productos_oportunidad_unica;
  } else {
    module.productosOportunidadUnica = { data: [], total: 0 };
  }

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



function actualizarIndexNavegacion(payload){
  indexNavegacion.value = payload;

  if(payload <= 0){
    router.push("/suppliers/supplieriaorderassistant")
  }

  if(payload == 3){
    // Al entrar al paso 3, mostramos la tabla de precios estables (se filtra en el componente)
    seleccionarProductosParaElDetalle()
  }

  if(payload == 4){
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
  const listaOportunidad = module.productosOportunidadUnica?.data || [];

  let productosEnFalla = verificarSiHayProductosEnFallaEnLaLista(
      [...module.productoFallas],
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

function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}


const TOTAL_ORDER= computed(() => {
  let listaSubTotales=module.detalleOrder.map(de => de.reponer * de.precio_final_supplier)
  const total=listaSubTotales.reduce((acumulador, valorActual) => acumulador + valorActual, 0)
  return (module.detalleOrder.length>0)?total:0
})

// KPI: productos de la lista de fallas que SÍ tienen un proveedor asignado para reponer
const KPIS_ENCONTRADOS = computed(() => {
  return module.productoFallas?.length || 0;
})

// KPI: fallas sin proveedor = total fallas - las que sí encontraron proveedor
// productosSinReponer ya viene filtrado por "fallas" desde el backend
const KPIS_NO_ENCONTRADOS = computed(() => {
  const totalFallas = module.productosSinReponer?.length || 0;
  return Math.max(0, totalFallas - KPIS_ENCONTRADOS.value);
})

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

  const idsQueSeEstanComprando = module.detalleOrder.map(item => item.product.id);
  let productos = module.productosSinReponer.filter(p =>
      p.solicitar < 0 && !idsQueSeEstanComprando.includes(p.id)
  );

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
  <div>
    <NavegationIaAutoOrder
      :index-navegacion="indexNavegacion"
      :encontrados="KPIS_ENCONTRADOS"
      :no-encontrados="KPIS_NO_ENCONTRADOS"
      @actualizar-index-navegacion="actualizarIndexNavegacion"
    />

    <VCard class="mb-6" v-if="indexNavegacion == 1">
      <template #title>
        Productos con Costo Elevado
        <VChip
          color="primary"
          variant="tonal"
          size="small"
          @click:close="clearSortFilter"
        >
          {{
            module.productoFallas.filter((pro) => pro.increase == true).length
          }}
        </VChip>
      </template>
      <ProductsExceededToleranceTable :list="module.productoFallas" />
    </VCard>
    <VCard class="mb-6" v-if="indexNavegacion == 2">
      <template #title>
        Productos con Costo Bajo
        <VChip
          color="primary"
          variant="tonal"
          size="small"
          @click:close="clearSortFilter"
        >
          {{
            module.productoFallas.filter((pro) => pro.increase == false).length
          }}
        </VChip>
      </template>
      <ProductsExceededDidNotToleranceTable :list="module.productoFallas" />
    </VCard>
    <VCard class="mb-6" v-if="indexNavegacion == 3">
      <template #title>
        Productos con Precio Estable
        <VChip
          color="primary"
          variant="tonal"
          size="small"
          @click:close="clearSortFilter"
        >
          {{
            module.productoFallas.filter((pro) => pro.increase === null).length
          }}
        </VChip>
      </template>
      <ProductsStablePriceTable :list="module.productoFallas" />
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
</template>

<style scoped>
.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>

