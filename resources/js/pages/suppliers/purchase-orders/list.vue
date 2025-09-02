<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const currentPurchaseOrder = ref({});
const purchaseOrders = ref([]);
const suppliers = ref([]);
const selectedSupplier = ref(null);
const loading = ref(false);
const formErrors = ref({});

const page = ref(1);
const itemsPerPage = ref(10);
const totalPurchaseOrders = ref(0);

const isEditDialogVisible = ref(false);
const isShowDialogVisible = ref(false);

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

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const { data } = await axios.get("/suppliers/purchase-orders", { params });
    purchaseOrders.value = data.data.data;
    totalPurchaseOrders.value = data.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las órdenes de compra:", error);
    toast.error("Error al obtener las órdenes de compra.");
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
  { deep: true }
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleClearFilters = () => {
  selectedSupplier.value = null;
};

const handleEditPurchaseOrder = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isEditDialogVisible.value = true;
};

const handleShowPurchaseOrder = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isShowDialogVisible.value = true;
};

const handleDeletePurchaseOrderDetail = async (purchaseOrderDetailId) => {
  try {
    await axios.delete(
      `/suppliers/purchase-orders/details/${purchaseOrderDetailId}`
    );
    toast.success("Detalle de Orden de compra eliminado correctamente.");
    isEditDialogVisible.value = false;
    currentPurchaseOrder.value = false;
    fetchPurchaseOrders();
  } catch (error) {
    console.error(
      "Hubo un error al eliminar el detalle de la orden de compra:",
      error
    );
    toast.error("Error al eliminar el detalle de la orden de compra.");
  }
};

const handleDeletePurchaseOrder = async (purchaseOrderId) => {
  try {
    const { data } = await axios.delete(
      `/suppliers/purchase-orders/${purchaseOrderId}`
    );

    if (data.data.status === "ok") {
      toast.success("Orden de compra eliminada correctamente.");
      fetchPurchaseOrders();
    } else {
      toast.error(
        `No se pudo eliminar la orden de compra ${currentPurchaseOrder.value.id}`
      );
    }
  } catch (error) {
    console.error(
      "Hubo un error al eliminar el detalle de la orden de compra:",
      error
    );
    toast.error("Error al eliminar la orden de compra.");
  }
};

const handleClearErrors = () => {
  formErrors.value = {};
};

const handleSaveDetails = async (detailsData) => {
  try {
    const { data } = await axios.put(
      `/suppliers/purchase-orders/${currentPurchaseOrder.value.id}`,
      detailsData
    );
    if (data.status === "ok") {
      toast.success(
        `Se actualizaron ${data.count} productos de la orden de compra ${currentPurchaseOrder.value.id}`
      );
    } else {
      toast.error(
        `No se pudo actualizar la orden de compra ${currentPurchaseOrder.value.id}`
      );
    }
    isEditDialogVisible.value = false;
    currentPurchaseOrder.value = false;
    fetchPurchaseOrders();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    }
  }
};
</script>

<template>
  <div>
    <PurchaseOrderEditDialog
      v-model="isEditDialogVisible"
      :purchaseOrder="currentPurchaseOrder"
      :errors="formErrors"
      @clearErrors="handleClearErrors"
      @delete-detail="handleDeletePurchaseOrderDetail"
      @save="handleSaveDetails"
    />

    <PurchaseOrderShowDialog
      v-model="isShowDialogVisible"
      :purchaseOrder="currentPurchaseOrder"
    />

    <PurchaseOrdersFilter
      v-model:selectedSupplier="selectedSupplier"
      :suppliers="suppliers"
      @clear="handleClearFilters"
    />

    <PurchaseOrdersTable
      :purchaseOrders="purchaseOrders"
      :loading="loading"
      :total-purchaseOrders="totalPurchaseOrders"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-purchaseOrder="handleEditPurchaseOrder"
      @delete-purchaseOrder="handleDeletePurchaseOrder"
      @show-purchaseOrder="handleShowPurchaseOrder"
    />
  </div>
</template>
