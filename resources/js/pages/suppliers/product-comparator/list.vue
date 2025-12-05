<script setup>
import ApplyDiscountDialog from "@/components/dialogs/ApplyDiscountDialog.vue";
import ShowImportProductsFileDialog from "@/components/dialogs/ShowImportProductsFileDialog.vue";
import ShowSupplierProductsDialog from "@/components/dialogs/ShowSupplierProductsDialog.vue";
import ProductComparisionProductsTable from "@/components/ProductComparisionProductsTable.vue";
import ProductComparisionTable from "@/components/ProductComparisionTable.vue";
import ProductsComparisionProductsFilter from "@/components/ProductsComparisionProductsFilter.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const supplierConnections = ref([]);
const suppliers = ref([]);
const origins = ref([]);
const laboratories = ref([]);
const products = ref([]);
const loadingSuppliers = ref(false);
const loadingProducts = ref(false);
const quantityErrors = reactive({});

const supplierOption = ref(null);
const selectedSupplier = ref(null);
const searchedSupplier = ref(null);
const searchedLaboratory = ref(null);
const selectedOrigin = ref(null);
const filterSearchQuery = ref("");
const stockStatusFilter = ref(null);
const isStrictSearch = ref(false);

const enableDiscounts = ref(false);

const isShowSupplierProductsDialogActive = ref(false);
const isShowImportFileDialogActive = ref(false);

const isApplyDiscountDialogActive = ref(false);
const supplierForDiscount = ref(null);

const checkingApiSupplierId = ref(null);
const pollingInterval = ref(null);

const pollingSupplierId = ref(null);
const tab = ref("suppliers");
const page = ref(1);
const itemsPerPage = ref(10);
const totalSupplierConnections = ref(0);

const productsPage = ref(1);
const productsItemPerPage = ref(10);
const productsTotal = ref(0);

const enableUsdAmountCol = ref(true);
const enableDiscountCol = ref(true);

const handleShowDiscountDialog = (supplier) => {
  supplierForDiscount.value = supplier;
  isApplyDiscountDialogActive.value = true;
};

// Lógica para procesar el descuento (se conectará al backend luego)
const handleApplyDiscount = async ({ supplier, percentage }) => {
  if (!supplier || !percentage) return;

  try {
    toast.info(
      `Procesando descuento del ${percentage}% para ${supplier.name}...`
    );

    const response = await axios.post(
      `/suppliers/${supplier.id}/apply-discount`,
      {
        percentage: parseFloat(percentage),
      }
    );

    if (response.status === 200) {
      toast.success(
        response.data.message || "Descuento aplicado correctamente."
      );

      await fetchProducts();
    }
  } catch (error) {
    console.error("Error al aplicar descuento:", error);

    if (error.response?.data?.message) {
      toast.error(error.response.data.message);
    } else {
      toast.error("No se pudo aplicar el descuento. Intente nuevamente.");
    }
  }
};
const fetchProducts = async () => {
  const params = {
    page: productsPage.value,
    perPage: productsItemPerPage.value,
    supplierId: searchedSupplier.value,
    laboratoryId: searchedLaboratory.value,
    q: filterSearchQuery.value,
    originId: selectedOrigin.value,
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
    // CAMBIO IMPORTANTE: Enviamos 'search' en lugar de 'selectedSupplier'
    // para indicar al backend que es una búsqueda de texto.
    search: selectedSupplier.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/suppliers/connections", { params });
    supplierConnections.value = response.data.data;
    totalSupplierConnections.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las conexiones:", error);
    toast.error("Error al obtener las conexiones.");
  } finally {
    loadingSuppliers.value = false;
  }
};

const fetchStatuses = async () => {
  try {
    const { data } = await axios.get("/suppliers/supplier-connection-statuses");
    const newStatuses = data.statuses;

    const currentStatus = newStatuses.find(
      (s) => s.supplier_id === pollingSupplierId.value
    );
    if (
      currentStatus &&
      ["completed", "failed"].includes(currentStatus.status)
    ) {
      stopPolling();
      pollingSupplierId.value = null;

      await fetchSupplierConnections();
      await fetchProducts();

      if (currentStatus.status === "failed") {
        toast.error("La sincronización con el proveedor falló");
      } else {
        toast.success("Sincronización completada exitosamente");
      }
    }
  } catch (error) {
    console.error("Error al consultar estados de conexión:", error);
    stopPolling();
    pollingSupplierId.value = null;
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

const fetchOptions = async () => {
  try {
    const [labResponse, originResponse, suppliersResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/available-suppliers"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    suppliers.value = suppliersResponse.data.data;
  } catch (error) {
    console.error("Hubo un error al obtener los datos para filtrar:", error);
    toast.error("Hubo un error al obtener los datos para filtrar.");
  } finally {
    loadingSuppliers.value = false;
  }
};

onMounted(() => {
  fetchOptions();
  fetchSupplierConnections();
  fetchProducts();
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
    selectedOrigin,
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
  pollingSupplierId.value = supplier.id;

  try {
    toast.info(
      `Procesando los datos de ${supplier.name}, le notificaremos al finalizar`
    );
    await axios.get(`/suppliers/${supplier.id}/connection`);

    startPolling();
  } catch (error) {
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}`);
    pollingSupplierId.value = null;
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
  selectedOrigin.value = null;
  isStrictSearch.value = false;
  enableDiscounts.value = false;
  enableUsdAmountCol.value = false;
  enableDiscountCol.value = false;
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
      fetchProducts();
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
      @refresh-products="fetchProducts"
    />
    <ApplyDiscountDialog
      v-model:isDialogVisible="isApplyDiscountDialogActive"
      :selected-supplier="supplierForDiscount"
      @submit="handleApplyDiscount"
    />

    <VCard title="Listados" class="mb-6">
      <VCardText>
        <VTabs v-model="tab">
          <VTab value="suppliers"> Proveedores </VTab>
          <VTab value="products"> Productos </VTab>
        </VTabs>
        <ProductsComparisionProductsFilter
          v-if="tab === 'products'"
          v-model:enable-discounts="enableDiscounts"
          v-model:enable-usd-amount-col="enableUsdAmountCol"
          v-model:enable-discount-col="enableDiscountCol"
          v-model:searchQuery="filterSearchQuery"
          v-model:stockStatusFilter="stockStatusFilter"
          v-model:selectedOrigin="selectedOrigin"
          v-model:isStrictSearch="isStrictSearch"
          :suppliers="suppliers"
          :laboratories="laboratories"
          :selected-laboratory="searchedLaboratory"
          :selected-supplier="searchedSupplier"
          :origins="origins"
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
          :loading="loadingSuppliers"
          :total-supplierConnections="totalSupplierConnections"
          :items-per-page="itemsPerPage"
          :page="page"
          :checking-api-id="checkingApiSupplierId"
          @update:options="updateTableOptions"
          @show-products="handleShowProducts"
          @update-products="handleCheckSupplierApi"
          @load-products="handleShowImportProductsDialog"
          @delete-products="handleDeleteSupplierProducts"
          @open-discount-dialog="handleShowDiscountDialog"
        />
      </VTabsWindowItem>

      <VTabsWindowItem value="products">
        <ProductComparisionProductsTable
          :products="products"
          :loading="loadingProducts"
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
