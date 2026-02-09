import { defineStore } from "pinia";
import { ref } from "vue";

const POLLING_TIMEOUT_MINUTES = 10;

export const useSupplierConnectionStore = defineStore("supplierConnection", () => {
  const hasPendingConnectionJob = ref(false);
  const connectionStartedAt = ref(null);

  function startConnection() {
    hasPendingConnectionJob.value = true;
    connectionStartedAt.value = Date.now();
  }

  function resetConnection() {
    hasPendingConnectionJob.value = false;
    connectionStartedAt.value = null;
  }

  function shouldStopPolling() {
    if (!connectionStartedAt.value) return true;
    const elapsed = (Date.now() - connectionStartedAt.value) / (60 * 1000);
    return elapsed >= POLLING_TIMEOUT_MINUTES;
  }

  return {
    hasPendingConnectionJob,
    connectionStartedAt,
    startConnection,
    resetConnection,
    shouldStopPolling,
  };
});
