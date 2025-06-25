<script setup lang="js">
import ClientTable from "@/components/ClientTable.vue";
import axios from "@/plugins/axios";
import { onMounted, reactive } from 'vue';


const statuModule= reactive({
  items:[]
})

const totalClients = ref(0)
const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
// const sortBy = ref()
// const orderBy = ref()


function consultAll(){
  loading.value = true;
  axios.get("/crm/clients")
  .then(res => {
    if(res.status==200){
      statuModule.items=[...res.data.data]
      totalClients.value=statuModule.items.length
    }
    // console.log("res => ",res)
    loading.value = false;
  })
  .catch(error => {
    loading.value = false;
    console.error("error => ",error)

  })
}

onMounted(() => {
  consultAll()
})
</script>
<template>
  <ClientTable
    :clients="statuModule.items"
    :total-clients="totalClients"
    :loading="loading"
    :items-per-page="itemsPerPage"
    :page="page"
  />
</template>
