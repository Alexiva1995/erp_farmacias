<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useSupplierConnectionStore } from "@/stores/supplierConnection";
import { onUnmounted, ref, watch } from "vue";

const store = useSupplierConnectionStore();
const pollingInterval = ref(null);
// Mapa de IDs notificados con fecha
const notifiedMap = ref(JSON.parse(localStorage.getItem("notifiedSupplierStatusMap") || "{}"));

// Guardar en localStorage
const saveNotifiedMap = () => {
  localStorage.setItem("notifiedSupplierStatusMap", JSON.stringify(notifiedMap.value));
};

// Verificar si ya fue notificado recientemente
const isRecentlyNotified = (status) => {
  const lastShown = notifiedMap.value[status.id];
  if (!lastShown) return false;

  const lastTimestamp = new Date(lastShown).getTime();
  const currentTimestamp = new Date(status.created_at).getTime();

  // Si el status fue creado después del último mostrado, es nuevo
  return currentTimestamp <= lastTimestamp;
};

// Consultar estados desde el backend
const fetchStatuses = async () => {
  try {
    const { data } = await axios.get("/suppliers/supplier-connection-statuses");
    const newStatuses = data.statuses;

    newStatuses.forEach((status) => {
      if (!isRecentlyNotified(status) && ["completed", "failed"].includes(status.status)) {
        const msg =
          status.status === "completed"
            ? `✅ ${status.supplier.name}: ${status.count_product} productos y ${status.count_invoice} facturas procesadas`
            : `❌ ${status.supplier.name}: ${status.message}`;

        toast[status.status === "completed" ? "success" : "error"](msg);

        notifiedMap.value[status.id] = status.created_at;
        saveNotifiedMap();
      }
    });
  } catch (error) {
    console.error("Error al consultar estados de conexión:", error);
  }
};

// Limpieza automática cada 24h
const cleanOldMap = () => {
  notifiedMap.value = {};
  localStorage.removeItem("notifiedSupplierStatusMap");
};

setTimeout(cleanOldMap, 24 * 60 * 60 * 1000); // 24 horas

const startPolling = () => {
  if (pollingInterval.value) return;
  fetchStatuses(); // primera petición inmediata
  pollingInterval.value = setInterval(() => {
    if (store.shouldStopPolling()) {
      stopPolling();
      return;
    }
    fetchStatuses();
  }, 5000);
};

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
  store.resetConnection();
};

watch(
  () => store.hasPendingConnectionJob,
  (active) => {
    if (active) startPolling();
    else stopPolling();
  },
);

onUnmounted(stopPolling);
</script>

<template>
  <!-- Este componente no renderiza nada visible -->
  <div style="display: none" />
</template>
