<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const currentPurchaseOrder = ref({});
const purchaseOrders = ref([]);
const suppliers = ref([]);
const selectedSupplier = ref(null);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const totalPurchaseOrders = ref(0);

const isEditDialogVisible = ref(false);

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/available-suppliers");
    suppliers.value = response.data.data;
  } catch (error) {
    console.error("Hubo un error al obtener los proveedores:", error);
    toast.error("Error al obtener los proveedores.");
  } finally {
    loading.value = false;
  }
};

const fetchPurchaseOrders = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    selectedSupplier: selectedSupplier.value,
  };

  Object.keys(params).forEach((key) => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const response = await axios.get("/suppliers/purchase-orders/history", { params });
    purchaseOrders.value = response.data.data;
    totalPurchaseOrders.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener el historial de las órdenes de compra:", error);
    toast.error("Error al obtener el historial de las órdenes de compra.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSuppliers();
  fetchPurchaseOrders();
});

let debounceTimer;
watch(
  [page, itemsPerPage, selectedSupplier],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchPurchaseOrders(), 300);
  },
  { deep: true },
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleClearFilters = () => {
  selectedSupplier.value = null;
};

const handleSeePurchaseOrderHistory = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isEditDialogVisible.value = true;
};
</script>

<template>
  <div>
    <PurchaseOrderHistoryDialog v-model="isEditDialogVisible" :purchaseOrder="currentPurchaseOrder" />

    <PurchaseOrdersFilter
      v-model:selectedSupplier="selectedSupplier"
      :suppliers="suppliers"
      @clear="handleClearFilters"
    />

    <PurchaseOrderHistoryTable
      :purchaseOrders="purchaseOrders"
      :loading="loading"
      :total-purchaseOrders="totalPurchaseOrders"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @view="handleSeePurchaseOrderHistory"
    />
  </div>
</template>
