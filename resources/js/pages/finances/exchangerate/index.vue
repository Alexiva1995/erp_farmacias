<script setup>
import FormExchangeRate from "@/components/FormExchangeRate.vue";
import axios from "@/plugins/axios";
import { onMounted, ref } from "vue";

const rates = ref({
  BS: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
  COP: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
  EUR: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
  COPC: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
  BINANCE: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
  BS_COP: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
  COPS: { rate: 0, id: null, dateUpdate: "", dateColor: "success" },
});

const loading = ref(false);

const formatDateAndColor = (updatedAt) => {
  if (!updatedAt) return { dateStr: "Sin fecha", color: "warning" };
  
  const dateObj = new Date(updatedAt);
  const today = new Date();
  
  const isToday =
    dateObj.getFullYear() === today.getFullYear() &&
    dateObj.getMonth() === today.getMonth() &&
    dateObj.getDate() === today.getDate();
    
  const dateStr = dateObj.toLocaleDateString("es-VE", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  
  return {
    dateStr,
    color: isToday ? "success" : "warning",
  };
};

async function refresh() {
  loading.value = true;
  try {
    const response = await axios.get("/finances/exchange-rates");
    const data = response.data.data || response.data || [];
    
    const codes = ["BS", "COP", "EUR", "COPC", "BINANCE", "BS_COP", "COPS"];
    codes.forEach(code => {
      const found = data.find(item => item.currency_code === code);
      if (found) {
        const { dateStr, color } = formatDateAndColor(found.updated_at);
        rates.value[code] = {
          id: found.id,
          rate: parseFloat(found.rate || 0),
          dateUpdate: dateStr,
          dateColor: color,
        };
      } else {
        rates.value[code] = { rate: 0, id: null, dateUpdate: "N/A", dateColor: "warning" };
      }
    });
  } catch (error) {
    console.error("Error al refrescar las tasas:", error);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  refresh();
});
</script>

<template>
  <FormExchangeRate
    :rates="rates"
    :loading="loading"
    @refresh="refresh"
  />
</template>
