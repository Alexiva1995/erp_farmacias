<script setup lang="js">
import LoaderComponent from "@/components/LoaderComponent.vue";
import NavegationIaAutoOrder from "@/components/NavegationIaAutoOrder.vue";
import OrderProductListTable from "@/components/OrderProductListTable.vue";
import ProductsExceededDidNotToleranceTable from "@/components/ProductsExceededDidNotToleranceTable.vue";
import ProductsExceededToleranceTable from "@/components/ProductsExceededToleranceTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfProductsWithoutSuppliersGenerator from "@/utils/pdfProductsWithoutSuppliersGenerator";
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

  let respuestaApi = await axios.post(`/suppliers-ia-order-assistant/generate-order/products-to-request`, data)

  return { ...respuestaApi.data }
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
  let respuestaApi = await axios.post(`/suppliers-ia-order-assistant/generate-order/unique-opportunity-page`, data);
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
    // if (module.productosOportunidadUnica && module.productosOportunidadUnica.data) {
    //   let listaActualizada = actualizarCantidadAReponerProductosEnFalla(
    //       [...module.productoFallas],
    //       [...module.productosOportunidadUnica.data]
    //   );
    //   module.productosOportunidadUnica.data = listaActualizada;
    // }
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
  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "Esta compra no se podra revertir",
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
        "total_quantity":productSupplier.reponer,
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
      orderSupplier.total_quantity=orderSupplier.total_quantity+productSupplier.reponer
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
  module.loadingApp=true
  let orders=formatiarData([...module.detalleOrder])
  const DATA={
    orders
  }
  console.log("datos enviar => ",orders)

  let response = await axios.post("/suppliers-ia-order-assistant/generate-order/creat",DATA)
  if(response.status!=200){
     module.loadingApp=false
    toast.error("Error al generar al compra")
    return
  }
  module.loadingApp=false
  toast.success("Compra realizada con exito")
  module.loadingApp=true

  let productosSinPorveedor= await consultarProductosSinProveedor()
  pdfProductsWithoutSuppliersGenerator(productosSinPorveedor)

  module.loadingApp=false
  router.push("/suppliers/purchase-orders/list")
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
    <VCard
      title="Productos del Pedido"
      class="mb-6"
      v-if="indexNavegacion == 3"
    >
      <!-- <UniqueMarketOpportunityTable
        :pagination-data="module.productosOportunidadUnica"
        :loading="module.loadingApp"
        @change-page="handleChangePageOportunidad"
      /> -->

      <OrderProductListTable
        :list="module.detalleOrder"
        @eliminar-item-orden="eliminarItemOrden"
      />
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
          <VCard title="Resumen" class="mb-5">
            <VContainer v-if="LISTA_PORVEEDORES_TOTAL.length > 0">
              <VRow
                v-for="totalesProveedores in LISTA_PORVEEDORES_TOTAL"
                class="text-lg"
                align-content="space-between"
                style="padding-left: 24px; padding-right: 24px"
              >
                <VCol class="" style="font-size: 16px">
                  <span>{{ totalesProveedores.name }}:</span>
                </VCol>
                <VCol
                  class="text-end"
                  style="font-weight: bold; font-size: 16px"
                >
                  {{ totalesProveedores.total.toFixed(2) }}
                  <VIcon icon="tabler-currency-dollar" />
                </VCol>
              </VRow>
            </VContainer>

            <VDivider />

            <VContainer>
              <VRow
                class="text-lg"
                align-content="space-between"
                style="padding-left: 24px; padding-right: 24px"
              >
                <VCol> <span style="font-size: 16px">Total:</span> </VCol>
                <VCol
                  class="text-end"
                  style="font-weight: bold; font-size: 16px"
                >
                  {{ TOTAL_ORDER ? TOTAL_ORDER.toFixed(2) : 0 }}
                  <VIcon icon="tabler-currency-dollar" />
                </VCol>
              </VRow>
            </VContainer>
          </VCard>
          <VBtn
            color="primary"
            variant="flat"
            class="w-100"
            @click="confirmarCompra"
          >
            Generar
          </VBtn>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
