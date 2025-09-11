<script setup>
import CashSummary from '@/components/CashSummary.vue';
import axios from "@/plugins/axios";

const loading = ref(false);
const cashClosure = ref([]);

const fetchCashClosure = async () => {
  try {
    loading.value = true;
   const response = await axios.get("/finances/cash-closure/");
    cashClosure.value = response.data
  } catch (error) {
    console.error("Hubo un error al obtener el resumen de caja:", error);
    toast.error("Error al obtener el resumen de caja.");
    cashClosure.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchCashClosure();
});

</script>

<template>
  <div>
    <p v-if="loading">Cargando resumen de caja...</p>
    <p v-else-if="!cashClosure">No hay datos de cierre de caja disponibles.</p>
    <CashSummary
      v-else
      :cashClosureData="cashClosure" 
    />
  </div>
</template>
