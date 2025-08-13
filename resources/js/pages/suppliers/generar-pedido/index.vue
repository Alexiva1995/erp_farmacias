<script setup lang="js">
import NavegationIaAutoOrder from "@/components/NavegationIaAutoOrder.vue";
import OrderProductListTable from "@/components/OrderProductListTable.vue";
import ProductsExceededDidNotToleranceTable from "@/components/ProductsExceededDidNotToleranceTable.vue";
import ProductsExceededToleranceTable from "@/components/ProductsExceededToleranceTable.vue";
import UniqueMarketOpportunityTable from "@/components/UniqueMarketOpportunityTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from 'sweetalert2';
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route= useRoute()
const router= useRouter()

// console.log(route.query)

const indexNavegacion=ref(1)

const module=reactive({
  dataProductos:{},
  productoFallas:[],
  productosOportunidadUnica:[],
  deltalleOrder:[],
})


const tipo_de_filtracion= ref(route.query.tipo_filtracion);// promedio o ventas
const lapso_de_tiempo= ref(route.query.lapso_de_tiempo);// tiempo
// const stock= ref(route.query.stock);// Fallas , Execeso o All

async function generarPedido(){
  let data ={
    "tipo_filtracion":tipo_de_filtracion.value,
    "lapso_de_tiempo":lapso_de_tiempo.value,
    // "stock":stock.value,
  }
  let respuestaApi = await axios.post(`/suppliers-ia-order-assistant/generate-order/products-to-request`,data)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
    console.log("respues api => ",respuestaApi)

    return {...respuestaApi.data}
}


onMounted(async () => {
  let data = await generarPedido()
  data.data.productos_a_reponer=data.data.productos_a_reponer.map(a => {
    a.uuid=generateUUID()
    return a
  })
  data.data.productos_oportunidad_unica=data.data.productos_oportunidad_unica.map(a => {
    a.uuid=generateUUID()
    return a
  })
  module.dataProductos={...data.data}
  module.productoFallas=[...data.data.productos_a_reponer]
  module.productosOportunidadUnica=[...data.data.productos_oportunidad_unica]

})



function actualizarIndexNavegacion(payload){
  indexNavegacion.value=payload
  if(payload==4){
    seleccionarProductosParaElDetalle()
  }
}

function seleccionarProductosParaElDetalle(){
  // TODO: falta obtener los productos que no estan en falla
  // lo que se puede hacer es hacer comparar la lista productosEnFalla y los que coincidan eliminarlos de la otra lista (trabajar con una copia) y solo dejar los que no coincidan
  // luego uniar las dos listas y eso si guardarlo en un estado
  module.deltalleOrder=[]
  let productosEnFalla=verificarSiHayProductosEnFallaEnLaLista([...module.productoFallas],[...module.productosOportunidadUnica])
  // console.log("productos en falla que puedan ser que tenga una oportunidad unica =>",productosEnFalla)
  let productosSinFallas=removerProductosConProveedores([...productosEnFalla],[...module.productosOportunidadUnica])
  // console.log("productos con oportunidad unica de mercado sin falla =>",productosSinFallas)
  let detalles = [...productosEnFalla,...productosSinFallas]
  module.deltalleOrder=detalles

}

function removerProductosConProveedores(productosEnFalla,productosOportunidadUnica){
  for (let index = 0; index < productosEnFalla.length; index++) {
    const producto = productosEnFalla[index];
    productosOportunidadUnica=productosOportunidadUnica.filter(productUnique => producto.product.id!=productUnique.product.id && producto.supplier.id!=productUnique.supplier.id)

  }
  return productosOportunidadUnica
}


function verificarSiHayProductosEnFallaEnLaLista(productosEnFalla,listaDeProductosOportunidaUnica){

  for (let index = 0; index < productosEnFalla.length; index++) {
    const producto = productosEnFalla[index];
    let buscarSiTieneOportunidadUnica=listaDeProductosOportunidaUnica.find(productUnique => producto.product.id==productUnique.product.id && producto.supplier.id==productUnique.supplier.id)
    if(buscarSiTieneOportunidadUnica){
      if(buscarSiTieneOportunidadUnica.reponer>producto.reponer){
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
  let listaSubTotales=module.deltalleOrder.map(de => de.reponer*de.productSupplier.unit_cost)
  const total=listaSubTotales.reduce((acumulador, valorActual) => acumulador + valorActual, 0)
  return (module.deltalleOrder.length>0)?total:0
})


async function confirmarCompra(){
  const result = await Swal.fire({
    title: '¿Estás seguro que desea realizar esta compra?',
    text: "",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí',
    cancelButtonText: 'No, ¡Cancelar!',
    buttonsStyling: false,
    customClass: {
      // confirmButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      // cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2'
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-success v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      cancelButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated mx-2'
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    await realizarCompra()
  }
}

// const formatoDatosOrder={
//   "supplier_id":null,
//   "total_items":null,
//   "total_quantity":null,
//   "total_amount":null,
//   "details":[], // formatoDatosProductos
// }

// const formatoDatosProductosOrderDetalles={
//   "product_suppliers_id":null,
//   "quantity":null,
//   "unit_cost":null,
//   "subtotal":null,
// }

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
        "unit_cost":productSupplier.productSupplier.unit_cost,
        "subtotal":productSupplier.reponer*productSupplier.productSupplier.unit_cost,
      }

      let orderSupplier={
        "supplier_id":productSupplier.supplier.id,
        "total_items":supplier[productSupplier.supplier.id].length,
        "total_quantity":productSupplier.reponer,
        "total_amount":productSupplier.reponer*productSupplier.productSupplier.unit_cost,
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
      orderSupplier.total_amount=orderSupplier.total_amount+(productSupplier.reponer*productSupplier.productSupplier.unit_cost)
      let detalleProductoOrder={
        "product_suppliers_id":productSupplier.productSupplier.id,
        "quantity":productSupplier.reponer,
        "unit_cost":productSupplier.productSupplier.unit_cost,
        "subtotal":productSupplier.reponer*productSupplier.productSupplier.unit_cost,
      }
      orderSupplier.details.push(detalleProductoOrder)
      orders[indexOrderSupplier]=orderSupplier
    }
  }

  return orders
}

async function realizarCompra(){
  let orders=formatiarData([...module.deltalleOrder])
  const DATA={
    orders
  }
  console.log("datos enviar => ",orders)

  let response = await axios.post("/suppliers-ia-order-assistant/generate-order/creat",DATA)
  if(response.status!=200){
    toast.error("Error al generar al compra")
    return
  }
   toast.success("Compra realizada con exito")
  //  router.push("/suppliers/supplieriaorderassistant")
}
</script>
<template>
  <div>
    <NavegationIaAutoOrder
      :index-navegacion="indexNavegacion"
      @actualizar-index-navegacion="actualizarIndexNavegacion"
    />
    <VCard title="" class="mb-6" v-if="indexNavegacion == 1">
      <ProductsExceededToleranceTable :list="module.productoFallas" />
    </VCard>
    <VCard
      title="productos que no excedieron la tolerancia"
      class="mb-6"
      v-if="indexNavegacion == 2"
    >
      <ProductsExceededDidNotToleranceTable :list="module.productoFallas" />
    </VCard>
    <VCard
      title="Oportunidades Unicas de Mercado"
      class="mb-6"
      v-if="indexNavegacion == 3"
    >
      <UniqueMarketOpportunityTable :list="module.productosOportunidadUnica" />
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
          <VCard title="Detalles de la Orden" class="">
            <OrderProductListTable :list="module.deltalleOrder" />
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
          <VCard
            title="Detalles del precio"
            class="mb-5"
            style="padding-bottom: 24px"
          >
            <VRow
              class="text-lg"
              align-content="space-between"
              style="padding-left: 24px; padding-right: 24px"
            >
              <VCol> <span>Total:</span> </VCol>
              <VCol class="text-end">
                <VIcon icon="tabler-currency-dollar" /> {{ TOTAL_ORDER }}
              </VCol>
            </VRow>
          </VCard>
          <VBtn
            color="primary"
            variant="flat"
            class="w-100"
            @click="confirmarCompra"
          >
            Realizar Compra
          </VBtn>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
