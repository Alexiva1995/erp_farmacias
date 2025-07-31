<script setup lang="js">
// import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
// import DoctorFilters from "@/components/DoctorFilters.vue";
// import DoctorTable from "@/components/DoctorTable.vue";
// import axios from "@/plugins/axios";
// import { toast } from "@/plugins/sweetalert";
// import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
// import Swal from 'sweetalert2';
// import { onMounted, reactive, watch } from 'vue';
// import { useRouter } from "vue-router";
const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const tipo_de_vista= ref("");// grupo o individual
const tipo_de_filtracion= ref("");// promedio o ventas
const lapso_de_tiempo= ref("3 month");// tiempo
const stock_exceso= ref("3 month");// Fallas , Execeso o All
</script>
<template>
  <h1>UwU</h1>
</template>
