<script setup lang="js">
import ProductsExceededToleranceTable from "@/components/ProductsExceededToleranceTable.vue";
import axios from "@/plugins/axios";
import { onMounted, reactive } from "vue";
import { useRoute } from "vue-router";

const route= useRoute()

console.log(route.query)

const module=reactive({
  dataProductos:{},
  productoFallas:[],
  productsExceededTolerance:[],
  productsExceededDidNotTheTolerance:[],
})



const ordenProducts=reactive({
  productsExceededTolerance:[],
  productsExceededDidNotTheTolerance:[],
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



// function generarDatosDeProductosOrden(products){
//   let hashIdSupplier={}
//   let listaNueva=[]
//   for (let index = 0; index < products.length; index++) {
//     const producto = products[index];
//     let uuid=generateUUID()
//     let oreder={...formatoDatosOrder}

//     if(!hashIdSupplier[producto.productSupplier.supplier_id]){

//       oreder.id=uuid
//       oreder.supplier_id=producto.productSupplier.supplier_id

//       let detalleProductoOrden={...formatoDatosProductosOrderDetalles}
//       detalleProductoOrden.product_suppliers_id=producto.productSupplier.id
//       detalleProductoOrden.unit_cost=producto.productSupplier.unit_cost

//       oreder.details=[detalleProductoOrden]

//       hashIdSupplier[producto.productSupplier.supplier_id]=index;

//       listaNueva.push({...oreder})
//       // oreder.data={...producto}
//     }
//     else{
//       let indexSupplier=hashIdSupplier[producto.productSupplier.supplier_id]

//       let detalleProductoOrden={...formatoDatosProductosOrderDetalles}
//       detalleProductoOrden.product_suppliers_id=producto.productSupplier.id
//       detalleProductoOrden.unit_cost=producto.productSupplier.unit_cost

//       listaNueva[indexSupplier].details.push(detalleProductoOrden)

//     }

//   }
//   return listaNueva
// }


onMounted(async () => {
  let data = await generarPedido()
  data.data.productos_a_reponer=data.data.productos_a_reponer.map(a => {
    a.uuid=generateUUID()
    return a
  })
  module.dataProductos=data.data
  module.productoFallas=[...data.data.productos_a_reponer]

  module.productsExceededTolerance=module.productoFallas.filter(pro => pro.increase==true)
  module.productsExceededDidNotTheTolerance=module.productoFallas.filter(pro => pro.increase==false)

  // ordenProducts.productsExceededTolerance=generarDatosDeProductosOrden(module.productsExceededTolerance)
  // ordenProducts.productsExceededDidNotTheTolerance=generarDatosDeProductosOrden(module.productsExceededDidNotTheTolerance)


})

function actualizarCantidadProductoQueSeExcedio(payload){
  let {product_id,supplier_id,product_suppliers_id,input} = payload
  console.log(input.target.value)
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
    <VCard title="IA Assistence de Pedidos" class="mb-6">
      <ProductsExceededToleranceTable
        :dataProductos="module.dataProductos"
        :list="module.productoFallas"
        :productsExceededTolerance="module.productsExceededTolerance"
        @actualizar-cantidad="actualizarCantidadProductoQueSeExcedio"
      />
    </VCard>
  </div>
</template>
