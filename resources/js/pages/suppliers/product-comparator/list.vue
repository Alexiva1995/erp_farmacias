<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const supplierConnections = ref([]);
const suppliers = ref([]);
const laboratories = ref([]);
const products = ref([]);
const loadingSuppliers = ref(false);
const loadingProducts = ref(false);
const loadingLaboratories = ref(false);
const quantityErrors = reactive({});

const supplierOption = ref(null);
const selectedSupplier = ref(null);
const searchedSupplier = ref(null);
const searchedLaboratory = ref(null);
const filterSearchQuery = ref("");
const stockStatusFilter = ref(null);
const isStrictSearch = ref(false);

const enableDiscounts = ref(false);

const isShowSupplierProductsDialogActive = ref(false);
const isShowImportFileDialogActive = ref(false);

const checkingApiSupplierId = ref(null);
const pollingInterval = ref(null);

const tab = ref("suppliers");
const page = ref(1);
const itemsPerPage = ref(10);
const totalSupplierConnections = ref(0);

const productsPage = ref(1);
const productsItemPerPage = ref(10);
const productsTotal = ref(0);

const enableUsdAmountCol = ref(false);
const enableDiscountCol = ref(false);

const fetchSuppliers = async () => {
  try {
    const { data } = await axios.get("/available-suppliers");
    suppliers.value = data.data;
  } catch (error) {
    console.error("Hubo un error al obtener los proveedores:", error);
    toast.error("Error al obtener los proveedores.");
  } finally {
    loadingSuppliers.value = false;
  }
};

const fetchLaboratories = async () => {
  try {
    const { data } = await axios.get("/suppliers/available-laboratories");
    laboratories.value = data;
  } catch (error) {
    console.error("Hubo un error al obtener los laboratorios:", error);
    toast.error("Error al obtener los laboratorios.");
  } finally {
    loadingLaboratories.value = false;
  }
};

const fetchProducts = async () => {
  const params = {
    page: productsPage.value,
    perPage: productsItemPerPage.value,
    supplierId: searchedSupplier.value,
    laboratoryId: searchedLaboratory.value,
    q: filterSearchQuery.value,
    isStrictSearch: isStrictSearch.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const { data } = await axios.get("/suppliers/available-products", {
      params,
    });
    products.value = data.data;
    productsTotal.value = data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loadingProducts.value = false;
  }
};

const fetchSupplierConnections = async () => {
  loadingSuppliers.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    selectedSupplier: selectedSupplier.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/suppliers/connections", { params });
    supplierConnections.value = response.data.data;
    totalSupplierConnections.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las órdenes de compra:", error);
    toast.error("Error al obtener las órdenes de compra.");
  } finally {
    loadingSuppliers.value = false;
  }
};

const fetchStatuses = async () => {
  try {
    const { data } = await axios.get("/suppliers/supplier-connection-statuses");
    const newStatuses = data.statuses;

    const hasFinished = newStatuses.some((s) =>
      ["completed", "failed"].includes(s.status)
    );
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
  fetchProducts();
  fetchLaboratories();
});

let supplierDebounceTimer;
watch(
  [page, itemsPerPage, selectedSupplier, searchedSupplier],
  () => {
    clearTimeout(supplierDebounceTimer);
    supplierDebounceTimer = setTimeout(() => fetchSupplierConnections(), 300);
  },
  { deep: true }
);

let productDebounceTimer;
watch(
  [productsPage, productsItemPerPage],
  () => {
    clearTimeout(productDebounceTimer);
    productDebounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

let debounceTimer;
watch(
  [
    searchedSupplier,
    searchedLaboratory,
    filterSearchQuery,
    stockStatusFilter,
    isStrictSearch,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  {
    deep: true,
  }
);

const handleSearchSupplier = (supplier) => {
  searchedSupplier.value = supplier;
};

const handleSearchLaboratory = (laboratory) => {
  searchedLaboratory.value = laboratory;
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const updateProductsTableOptions = (options) => {
  productsPage.value = options.page;
  productsItemPerPage.value = options.itemsPerPage;
};

const handleShowProducts = (supplier) => {
  supplierOption.value = supplier;
  isShowSupplierProductsDialogActive.value = true;
};

const handleCheckSupplierApi = async (supplier) => {
  checkingApiSupplierId.value = supplier.id;

  try {
    toast.info(
      `Procesando los datos de ${supplier.name}, le notificaremos al finalizar`
    );
    await axios.get(`/suppliers/${supplier.id}/connection`);

    startPolling();
  } catch (error) {
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}`);
  } finally {
    checkingApiSupplierId.value = null;
  }
};

const handleClearSuppliersFilters = () => {
  selectedSupplier.value = null;
};

const handleClearProductsFilters = () => {
  searchedSupplier.value = null;
  searchedLaboratory.value = null;
  filterSearchQuery.value = "";
  stockStatusFilter.value = null;
  isStrictSearch.value = false;
};

const handleAddItemToAutoOrder = async (product) => {
  quantityErrors[product.id] = null;
  const form = new FormData();
  form.append("productId", product.id);
  form.append("quantity", product.quantity);
  form.append("discount", enableDiscounts.value);

  try {
    await axios.post("/suppliers/add-product-to-order", form);
    toast.success(
      `Se añadieron ${product.quantity} productos al pedido del día`
    );
  } catch (error) {
    if (error.response?.status === 422) {
      quantityErrors[product.id] = error.response.data.errors.quantity?.[0];
    }

    console.error("Hubo un error al enviar la petición:", error);

    if (error.response?.status === 400) {
      toast.error(error.response.data.message);
    } else {
      toast.error("Error al añadir productos al pedido del día.");
    }
  }
};

const handleShowImportProductsDialog = (supplier) => {
  supplierOption.value = supplier;
  isShowImportFileDialogActive.value = true;
};

const handleHideImportProductsDialog = () => {
  supplierOption.value = {};
  isShowImportFileDialogActive.value = false;
};

const handleDeleteSupplierProducts = async (supplier) => {
  try {
    const { data } = await axios.delete(
      `/suppliers/${supplier.id}/delete-products`
    );
    if (data.status === "ok") {
      toast.success(`Se borraron los productos del proveedor ${supplier.name}`);

      fetchSupplierConnections();
    }
  } catch (error) {
    toast.error("No se pudieron borrar los productos del proveedor.");
  }
};
</script>

<template>
  <div>
    <ShowSupplierProductsDialog
      v-model="isShowSupplierProductsDialogActive"
      :selectedSupplier="supplierOption"
    />
    <ShowImportProductsFileDialog
      v-model="isShowImportFileDialogActive"
      :selectedSupplier="supplierOption"
      @close-dialog="handleHideImportProductsDialog"
    />

    <VCard title="Listados" class="mb-6">
      <VCardText>
        <VTabs v-model="tab">
          <VTab value="suppliers"> Proveedores </VTab>
          <VTab value="products"> Productos </VTab>
        </VTabs>

        <ProductsComparisionSuppliersFilter
          v-if="tab === 'suppliers'"
          v-model:selectedSupplier="selectedSupplier"
          :suppliers="suppliers"
          @clear="handleClearSuppliersFilters"
        />

        <ProductsComparisionProductsFilter
          v-if="tab === 'products'"
          v-model:enable-discounts="enableDiscounts"
          v-model:enable-usd-amount-col="enableUsdAmountCol"
          v-model:enable-discount-col="enableDiscountCol"
          v-model:searchQuery="filterSearchQuery"
          v-model:stockStatusFilter="stockStatusFilter"
          v-model:isStrictSearch="isStrictSearch"
          :suppliers="suppliers"
          :laboratories="laboratories"
          :selected-laboratory="searchedLaboratory"
          :selected-supplier="searchedSupplier"
          @clear="handleClearProductsFilters"
          @update:selectedLaboratory="handleSearchLaboratory"
          @update:selectedSupplier="handleSearchSupplier"
        />
      </VCardText>
    </VCard>

    <VTabsWindow v-model="tab">
      <VTabsWindowItem value="suppliers">
        <ProductComparisionTable
          :supplierConnections="supplierConnections"
          :loading="loading"
          :total-supplierConnections="totalSupplierConnections"
          :items-per-page="itemsPerPage"
          :page="page"
          :checking-api-id="checkingApiSupplierId"
          @update:options="updateTableOptions"
          @show-products="handleShowProducts"
          @update-products="handleCheckSupplierApi"
          @load-products="handleShowImportProductsDialog"
          @delete-products="handleDeleteSupplierProducts"
        />
      </VTabsWindowItem>

      <VTabsWindowItem value="products">
        <ProductComparisionProductsTable
          :products="products"
          :loading="loading"
          :total-products="productsTotal"
          :items-per-page="itemsPerPage"
          :page="productsPage"
          :quantity-errors="quantityErrors"
          :enable-usd-amount-col="enableUsdAmountCol"
          :enable-discount-col="enableDiscountCol"
          @update:options="updateProductsTableOptions"
          @send-product="handleAddItemToAutoOrder"
        />
      </VTabsWindowItem>
    </VTabsWindow>
  </div>
</template>
