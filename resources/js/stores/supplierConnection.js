import { defineStore } from "pinia";
import { ref } from "vue";

const POLLING_TIMEOUT_MINUTES = 10;

export const useSupplierConnectionStore = defineStore("supplierConnection", () => {
  const hasPendingConnectionJob = ref(false);
  const connectionStartedAt = ref(null);
  const pendingSupplierIds = ref([]);

  function startConnection(supplierId = null) {
    hasPendingConnectionJob.value = true;
    if (!connectionStartedAt.value) {
      connectionStartedAt.value = Date.now();
    }
    if (supplierId !== null && supplierId !== undefined) {
      const idNum = Number(supplierId);
      if (!pendingSupplierIds.value.includes(idNum)) {
        pendingSupplierIds.value = [...pendingSupplierIds.value, idNum];
      }
    } else {
      // Actualización masiva: escuchar cualquier proveedor a partir de ahora
      pendingSupplierIds.value = [];
      connectionStartedAt.value = Date.now();
    }
  }

  function resetConnection() {
    hasPendingConnectionJob.value = false;
    connectionStartedAt.value = null;
    pendingSupplierIds.value = [];
  }

  function shouldStopPolling() {
    if (!connectionStartedAt.value) return true;
    const elapsed = (Date.now() - connectionStartedAt.value) / (60 * 1000);
    return elapsed >= POLLING_TIMEOUT_MINUTES;
  }

  return {
    hasPendingConnectionJob,
    connectionStartedAt,
    pendingSupplierIds,
    startConnection,
    resetConnection,
    shouldStopPolling,
  };
});
