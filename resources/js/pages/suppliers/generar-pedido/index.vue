<script setup lang="js">
import axios from "@/plugins/axios";
import { onMounted } from "vue";
import { useRoute } from "vue-router";

const route= useRoute()

console.log(route.query)



const tipo_de_filtracion= ref(route.query.tipo_filtracion);// promedio o ventas
const lapso_de_tiempo= ref(route.query.lapso_de_tiempo);// tiempo
const stock= ref(route.query.stock);// Fallas , Execeso o All


async function generarPedido(){
  let data ={
    "tipo_filtracion":tipo_de_filtracion.value,
    "lapso_de_tiempo":lapso_de_tiempo.value,
    "stock":stock.value,
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
})
</script>
<template>
  <!-- <h1>uwu desune</h1> -->
  <!-- <h1>{{ route.query.tipo_vista }}</h1> -->
</template>
