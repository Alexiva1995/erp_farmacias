<script setup lang="js">
import NavegationIaAutoOrder from "@/components/NavegationIaAutoOrder.vue";
import ProductsExceededDidNotToleranceTable from "@/components/ProductsExceededDidNotToleranceTable.vue";
import ProductsExceededToleranceTable from "@/components/ProductsExceededToleranceTable.vue";
import UniqueMarketOpportunityTable from "@/components/UniqueMarketOpportunityTable.vue";
import axios from "@/plugins/axios";
import { onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";

const route= useRoute()

console.log(route.query)

const indexNavegacion=ref(1)

const module=reactive({
  dataProductos:{},
  productoFallas:[],
  productosOportunidadUnica:[],
  productosConProveedorFinal:[],
})







const tipo_de_filtracion= ref(route.query.tipo_filtracion);// promedio o ventas
const lapso_de_tiempo= ref(route.query.lapso_de_tiempo);// tiempo
// const stock= ref(route.query.stock);// Fallas , Execeso o All

// const formatoDatosOrder={
//   "id":null,
//   "supplier_id":null,
//   "order_date":null,
//   "total_items":null,
//   "total_quantity":null,
//   "total_amount":null,
//   "details":[], // formatoDatosProductos
//   "data":{}
// }

// const formatoDatosProductosOrderDetalles={
//   "product_suppliers_id":null,
//   "quantity":null,
//   "unit_cost":null,
//   "subtotal":null,
// }


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
  module.detalles=[]
  let productosEnFalla=verificarSiHayProductosEnFallaEnLaLista([...module.productoFallas],[...module.productosOportunidadUnica])
  console.log("productos en falla que puedan ser que tenga una oportunidad unica =>",productosEnFalla)

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
    <VCard
      title="Detalles de la Orden"
      class="mb-6"
      v-if="indexNavegacion == 4"
    >
      <h1>pantalla 4</h1>
    </VCard>
  </div>
</template>
