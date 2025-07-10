<script setup lang="js">
// import CompanyTable from "@/components/CompanyTable.vue";
// import CompanyFilters from "@/components/dialogs/CompanyFilters.vue";
// import CompanyFormDialoge from "@/components/dialogs/CompanyFormDialoge.vue";
// import axios from "@/plugins/axios";
// import { toast } from "@/plugins/sweetalert";
// import pdfCompaniesGenerator from "@/utils/pdfCompaniesGenerator";
// import Swal from 'sweetalert2';
import { onMounted, reactive } from 'vue';
import { useRouter } from "vue-router";

const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})


onMounted(async () => {
  // await actualizarTabla()
})
</script>
<template>
  <h1>modulo doctor</h1>
</template>
