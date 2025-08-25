<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref } from "vue";

const pollingInterval = ref(null);
const notifiedIds = ref(new Set(JSON.parse(localStorage.getItem("notifiedSupplierStatusIds") || "[]")));

const saveNotifiedIds = () => {
  localStorage.setItem("notifiedSupplierStatusIds", JSON.stringify([...notifiedIds.value]));
};

const fetchStatuses = async () => {
  try {
    const { data } = await axios.get("/suppliers/supplier-connection-statuses");
    const newStatuses = data.statuses;

    newStatuses.forEach((status) => {
      if (!notifiedIds.value.has(status.id) && ["completed", "failed"].includes(status.status)) {  
        const msg =
          status.status === "completed"
            ? `✅ ${status.supplier.name}: ${status.count_product} productos y ${status.count_invoice} facturas procesadas`
            : `❌ ${status.supplier.name}: ${status.message}`;

        toast[status.status === "completed" ? "success" : "error"](msg);
        notifiedIds.value.add(status.id);
        saveNotifiedIds();
      }
    });
  } catch (error) {
    console.error("Error al consultar estados de conexión:", error);
  }
};

const cleanOldIds = () => {
  notifiedIds.value = new Set();
  saveNotifiedIds();
};

setTimeout(cleanOldIds, 24 * 60 * 60 * 1000);

onMounted(() => {
  pollingInterval.value = setInterval(fetchStatuses, 5000);
});
</script>

<template>
  <!-- Este componente no renderiza nada visible -->
  <div style="display: none" />
</template>
