<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const supplierConnections = ref([]);
const suppliers = ref([]);
const loading = ref(false);

const selectedSupplier = ref(null);
const searchedSupplier = ref(null);

const enablePaymentRules = ref(false);
const enableDiscounts = ref(false);

const isShowSupplierProductsDialogActive = ref(false);
const isShowSupplierDiscountAndPaymentRulesDialogActive = ref(false);

const checkingApiSupplierId = ref(null);
const pollingInterval = ref(null);

const tab = ref("suppliers");
const page = ref(1);
const itemsPerPage = ref(10);
const totalSupplierConnections = ref(0);

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

const fetchSupplierConnections = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    selectedSupplier: searchedSupplier.value,
  };

  Object.keys(params).forEach((key) => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const response = await axios.get("/suppliers/connections", { params });
    supplierConnections.value = response.data.data;
    totalSupplierConnections.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las órdenes de compra:", error);
    toast.error("Error al obtener las órdenes de compra.");
  } finally {
    loading.value = false;
  }
};

const fetchStatuses = async () => {
  try {
    const { data } = await axios.get("/suppliers/supplier-connection-statuses");
    const newStatuses = data.statuses;

    const hasFinished = newStatuses.some((s) => ["completed", "failed"].includes(s.status));
    if (hasFinished) {
      stopPolling();
      await fetchSupplierConnections();
    }
  } catch (error) {
    console.error("Error al consultar estados de conexión:", error);
    stopPolling();
  }
};

const startPolling = () => {
  stopPolling();
  pollingInterval.value = setInterval(fetchStatuses, 5000);
};

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
};

onMounted(() => {
  fetchSuppliers();
  fetchSupplierConnections();
});

let debounceTimer;
watch(
  [page, itemsPerPage, selectedSupplier, searchedSupplier],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSupplierConnections(), 300);
  },
  { deep: true },
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleShowProducts = (supplier) => {
  selectedSupplier.value = supplier;
  isShowSupplierProductsDialogActive.value = true;
};

const handleShowDiscountAndPaymentRules = (supplier) => {
  selectedSupplier.value = supplier;
  isShowSupplierDiscountAndPaymentRulesDialogActive.value = true;
};

const handleCheckSupplierApi = async (supplier) => {
  checkingApiSupplierId.value = supplier.id;

  try {
    toast.info(`Procesando los datos de ${supplier.name}, le notificaremos al finalizar`);
    await axios.get(`/suppliers/${supplier.id}/connection`);

    startPolling();
  } catch (error) {
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}`);
  } finally {
    checkingApiSupplierId.value = null;
  }
};

const handleClearFilters = () => {
  searchedSupplier.value = null;
};
</script>

<template>
  <div>
    <ShowSupplierProductsDialog v-model="isShowSupplierProductsDialogActive" :selectedSupplier="selectedSupplier" />
    <ShowSupplierDiscountAndPaymentRulesDialog
      v-model="isShowSupplierDiscountAndPaymentRulesDialogActive"
      :selectedSupplier="selectedSupplier"
      :enableDiscounts="enableDiscounts"
      :enablePaymentRules="enablePaymentRules"
    />

    <VCard title="Listados" class="mb-6">
      <VCardText>
        <VTabs v-model="tab">
          <VTab value="suppliers"> Proveedores </VTab>
          <VTab value="products"> Productos </VTab>
        </VTabs>
      </VCardText>
    </VCard>

    <VTabsWindow v-model="tab">
      <VTabsWindowItem value="suppliers">
        <ProductsComparisionFilter
          v-model:selectedSupplier="searchedSupplier"
          v-model:enable-payment-rules="enablePaymentRules"
          v-model:enable-discounts="enableDiscounts"
          :suppliers="suppliers"
          @clear="handleClearFilters"
        />

        <ProductComparisionTable
          :supplierConnections="supplierConnections"
          :loading="loading"
          :total-supplierConnections="totalSupplierConnections"
          :items-per-page="itemsPerPage"
          :page="page"
          :checking-api-id="checkingApiSupplierId"
          @update:options="updateTableOptions"
          @show-products="handleShowProducts"
          @show-discount-and-payment-rules="handleShowDiscountAndPaymentRules"
          @update-products="handleCheckSupplierApi"
        />
      </VTabsWindowItem>
    </VTabsWindow>
  </div>
</template>
