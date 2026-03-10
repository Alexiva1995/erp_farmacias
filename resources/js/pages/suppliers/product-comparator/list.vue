<script setup>
import ApplyDiscountDialog from "@/components/dialogs/ApplyDiscountDialog.vue";
import DeleteOldProductsDialog from "@/components/dialogs/DeleteOldProductsDialog.vue";
import GeneratePublicLinkDialog from "@/components/dialogs/GeneratePublicLinkDialog.vue";
import ShowImportProductsFileDialog from "@/components/dialogs/ShowImportProductsFileDialog.vue";
import ShowSupplierProductsDialog from "@/components/dialogs/ShowSupplierProductsDialog.vue";
import ProductComparisionProductsTable from "@/components/ProductComparisionProductsTable.vue";
import ProductComparisionTable from "@/components/ProductComparisionTable.vue";
import ProductsComparisionProductsFilter from "@/components/ProductsComparisionProductsFilter.vue";
import ProductsWithoutSupplierComparatorFilter from "@/components/ProductsWithoutSupplierComparatorFilter.vue";
import ProductsWithoutSupplierComparatorTable from "@/components/ProductsWithoutSupplierComparatorTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useSupplierConnectionStore } from "@/stores/supplierConnection";
import Swal from "sweetalert2";
import { onMounted, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();

const supplierConnections = ref([]);
const suppliers = ref([]);
const origins = ref([]);
const laboratories = ref([]);
const groups = ref([]);
const products = ref([]);
const loadingSuppliers = ref(false);
const loadingProducts = ref(false);
const quantityErrors = reactive({});

const supplierOption = ref(null);
const selectedSupplier = ref(null);
const selectedOrigin = ref(null);
const filterSearchQuery = ref("");
const stockStatusFilter = ref(null);
const isStrictSearch = ref(false);

const enableDiscounts = ref(false);

const isShowSupplierProductsDialogActive = ref(false);
const isShowImportFileDialogActive = ref(false);

const isApplyDiscountDialogActive = ref(false);
const supplierForDiscount = ref(null);
const isGeneratePublicLinkDialogActive = ref(false);
const supplierForPublicLink = ref(null);

const checkingApiSupplierId = ref(null);
const pollingInterval = ref(null);

const pollingSupplierId = ref(null);
const tab = ref(route.query.tab || "suppliers");
const page = ref(1);
const itemsPerPage = ref(10);
const totalSupplierConnections = ref(0);

// Variables de Productos
const productsPage = ref(1);
const productsItemPerPage = ref(10);
const productsTotal = ref(0);
const sortOptions = ref([]);

const enableUsdAmountCol = ref(true);
const enableDiscountCol = ref(false);

const isDeleteDialogVisible = ref(false);

//Variables de productos sin proveedor
const listProductsWithoutSupplier = ref([]);
const totalProductsWithoutSupplier = ref(0);
const loadingProductsWithoutSupplier = ref(false);
const pageProductsWithoutSupplier = ref(1);
const itemsPerPageProductsWithoutSupplier = ref(10);
const sortByProductsWithoutSupplier = ref();
const orderByProductsWithoutSupplier = ref();

// Variable para rastrear la selección
const selectedProductFromTop = ref(null);
const searchQueryRight = ref("");

//variables para el flitro de productos sin proveedor
const con_descuento = ref(true); // Fallas , Execeso o All
const selectedLaboratory = ref([]);
const selectedGroup = ref([]);
const tipo_de_vista = ref(false); // grupo o individual
const tipo_de_filtracion = ref("combinado"); // mismos defaults que el Asistente IA
const lapso_de_tiempo = ref("1 month"); // mismos defaults que el Asistente IA
const stock = ref("fallas"); // Mostrar siempre fallas (lo que hay que pedir)
const laboratoriesProductsWithoutSupplier = ref([]);

const handleClearFilters = () => {
  con_descuento.value = true;
  tipo_de_vista.value = false;
  tipo_de_filtracion.value = "sales";
  lapso_de_tiempo.value = "3 month";
  stock.value = "all";
  selectedLaboratory.value = [];
  selectedGroup.value = [];
};

const handleSelectProductFromTop = (product) => {
  selectedProductFromTop.value = product;

  if (product.cheapest_barcode) {
    filterSearchQuery.value = product.cheapest_barcode;
  } else if (product.barcode) {
    filterSearchQuery.value = product.barcode;
  } else {
    // Regla de 4 letras de nombre + 4 letras del laboratorio
    const namePart = product.name ? product.name.substring(0, 4) : "";
    const labPart = product.laboratory?.name ? product.laboratory.name.substring(0, 4) : "";
    // Si la combinación queda muy corta, se usa el ID como fallback
    const filter = `${namePart} ${labPart}`.trim();
    filterSearchQuery.value = filter.length > 2 ? filter : String(product.id);
  }

  toast.success(
    `Seleccionado: ${product.name}. Ahora busque el proveedor equivalente arriba.`,
  );
};

const handleShowDiscountDialog = (supplier) => {
  supplierForDiscount.value = supplier;
  isApplyDiscountDialogActive.value = true;
};

const handleDeleteOldProducts = async (date) => {
  try {
    const result = await Swal.fire({
      title: "¿Estás seguro?",
      text: `Se eliminarán todos los productos cuya última actualización sea anterior al ${date}. Esta acción no se puede deshacer.`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
      didOpen: () => {
        const actions = Swal.getActions();
        const confirmButton = Swal.getConfirmButton();
        const cancelButton = Swal.getCancelButton();

        actions.style.display = "flex";
        actions.style.gap = "10px";
        actions.style.width = "100%";
        actions.style.padding = "0 20px";
        confirmButton.style.flex = "1";
        confirmButton.style.width = "50%";
        cancelButton.style.flex = "1";
        cancelButton.style.width = "50%";
      },
    });

    if (result.isConfirmed) {
      // Petición al backend
      const response = await axios.post("/suppliers/products/delete-old", {
        date: date,
      });

      if (response.data.status === "ok") {
        Swal.fire(
          "¡Eliminados!",
          `Se han eliminado ${response.data.count} productos antiguos.`,
          "success",
        );
        fetchProducts();
      }
    }
  } catch (error) {
    console.error(error);
    toast.error("Error al intentar eliminar productos antiguos.");
  }
};
const handleUpdateAllApi = () => {
  Swal.fire({
    title: "¿Actualizar todos los proveedores?",
    text: "Este proceso se ejecutará en segundo plano y actualizará el inventario de todos los proveedores conectados vía API. Puede tardar varios minutos.",
    icon: "info",
    showCancelButton: true,
    confirmButtonColor: "#00bad1", // Color Info
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, actualizar todo",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";
      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";
      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        // Llamada al endpoint que crea el Job
        const response = await axios.post("/suppliers/update-all-job");

        if (response.status === 200) {
          toast.success(
            "El proceso de actualización ha iniciado en segundo plano.",
          );
        }
      } catch (error) {
        console.error(error);
        toast.error("No se pudo iniciar el proceso de actualización.");
      }
    }
  });
};
// Lógica para procesar el descuento
const handleApplyDiscount = async ({ supplier, percentage }) => {
  if (!supplier || !percentage) return;

  try {
    toast.info(
      `Procesando descuento del ${percentage}% para ${supplier.name}...`,
    );

    const response = await axios.post(
      `/suppliers/${supplier.id}/apply-discount`,
      {
        percentage: parseFloat(percentage),
      },
    );

    if (response.status === 200) {
      toast.success(
        response.data.message || "Descuento aplicado correctamente.",
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
    laboratoryId: selectedLaboratory.value, // Usar el mismo laboratorio que IA
    q: filterSearchQuery.value || searchQueryRight.value, // Sincronizar búsqueda
    originId: selectedOrigin.value,
    isStrictSearch: isStrictSearch.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
  };

  // Lógica de Ordenamiento
  if (sortOptions.value && sortOptions.value.length > 0) {
    params.sortBy = sortOptions.value[0].key;
    params.order = sortOptions.value[0].order;
  }

  // No bloqueamos la carga inicial. Si no hay filtros, el backend traerá fallas por defecto o catálogo base.
  
  try {
    loadingProducts.value = true;
    const { data } = await axios.get("/suppliers/available-products", {
      params: {
        ...params,
        groupId: selectedGroup.value, // Unificamos el grupo
      }
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
    search: selectedSupplier.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/suppliers/connections", { params });
    supplierConnections.value = response.data.data;
    totalSupplierConnections.value = response.data.total;

    // Sincronizar el proveedor seleccionado para el link si el diálogo está abierto
    if (isGeneratePublicLinkDialogActive.value && supplierForPublicLink.value) {
      const freshSupplier = supplierConnections.value.find(s => s.id === supplierForPublicLink.value.id);
      if (freshSupplier) {
        supplierForPublicLink.value = freshSupplier;
      }
    }
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
      (s) => s.supplier_id === pollingSupplierId.value,
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

const fetchProductsWithoutSupplier = async () => {
  try {
    if (loadingProductsWithoutSupplier.value) return;
    loadingProductsWithoutSupplier.value = true;

    const payload = {
      laboratoryId: selectedLaboratory.value,
      groups: selectedGroup.value,
      tipo_vista: tipo_de_vista.value,
      tipo_filtracion: tipo_de_filtracion.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      stock: stock.value,
      q: searchQueryRight.value,
      without_supplier: true,
      isStrictSearch: false, // Por ahora general para la tabla derecha
      page: pageProductsWithoutSupplier.value,
      itemsPerPage: itemsPerPageProductsWithoutSupplier.value,
      sortBy: sortByProductsWithoutSupplier.value,
      orderBy: orderByProductsWithoutSupplier.value,
    };

    const response = await axios.post(
      `/suppliers-ia-assistant-report/filtrar-paginate?page=${pageProductsWithoutSupplier.value}`,
      payload,
    );

    // La respuesta de SupplierIaAssistantReportController es directamente el paginador
    // El paginator tiene: .data (array de items), .total (cantidad total)
    const paginate = response?.data?.data ?? null;

    if (paginate && Array.isArray(paginate.data)) {
      listProductsWithoutSupplier.value = paginate.data;
      totalProductsWithoutSupplier.value = paginate.total ?? 0;
    } else {
      listProductsWithoutSupplier.value = [];
      totalProductsWithoutSupplier.value = 0;
      console.warn('[Comparador] Respuesta inesperada del endpoint:', response?.data);
    }
  } catch (error) {
    console.error('[Comparador] Error al obtener productos sin proveedor:', error);
    toast.error('Error al obtener los productos sin proveedor.');
  } finally {
    loadingProductsWithoutSupplier.value = false;
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
    const [labResponse, originResponse, suppliersResponse, groupsResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/available-suppliers"),
      axios.get("/groups"),
    ]);
    laboratories.value = labResponse.data;
    laboratoriesProductsWithoutSupplier.value = labResponse.data;
    origins.value = originResponse.data;
    suppliers.value = suppliersResponse.data.data;
    groups.value = groupsResponse.data;
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
  fetchProductsWithoutSupplier();
});

let supplierDebounceTimer;
watch(
  [page, itemsPerPage, selectedSupplier, searchedSupplier],
  () => {
    clearTimeout(supplierDebounceTimer);
    supplierDebounceTimer = setTimeout(() => fetchSupplierConnections(), 300);
  },
  { deep: true },
);

watch(
  [
    selectedLaboratory,
    selectedGroup,
    tipo_de_vista,
    tipo_de_filtracion,
    lapso_de_tiempo,
    stock,
    searchQueryRight,
    filterSearchQuery,
    selectedSupplier,
    selectedOrigin,
    stockStatusFilter,
    isStrictSearch,
    pageProductsWithoutSupplier,
    itemsPerPageProductsWithoutSupplier,
    productsPage,
    productsItemPerPage,
  ],
  () => {
    clearTimeout(debounceTimerProductsWithoutSupplier);
    debounceTimerProductsWithoutSupplier = setTimeout(() => {
      fetchProductsWithoutSupplier();
      fetchProducts();
    }, 400); // Un poco más de debounce para seguridad
  },
  { deep: true },
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

// FUNCIÓN ACTUALIZADA PARA CAPTURAR ORDENAMIENTO DE LA TABLA
const updateProductsTableOptions = (options) => {
  if (productsPage.value === options.page && 
      productsItemPerPage.value === options.itemsPerPage && 
      JSON.stringify(sortOptions.value) === JSON.stringify(options.sortBy || [])) {
    return;
  }
  
  productsPage.value = options.page;
  productsItemPerPage.value = options.itemsPerPage;
  // VDataTableServer envía 'sortBy' en las opciones
  if (options.sortBy) {
    sortOptions.value = options.sortBy;
  }
};

const handleShowProducts = (supplier) => {
  supplierOption.value = supplier;
  isShowSupplierProductsDialogActive.value = true;
};

const supplierConnectionStore = useSupplierConnectionStore();

const handleCheckSupplierApi = async (supplier) => {
  checkingApiSupplierId.value = supplier.id;
  pollingSupplierId.value = supplier.id;

  try {
    toast.info(
      `Procesando los datos de ${supplier.name}, le notificaremos al finalizar`,
    );
    await axios.get(`/suppliers/${supplier.id}/connection`);
    supplierConnectionStore.startConnection();
    startPolling();
  } catch (error) {
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}`);
    pollingSupplierId.value = null;
  } finally {
    checkingApiSupplierId.value = null;
  }
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
  const mainProductId = selectedProductFromTop.value?.id ?? null;

  const form = new FormData();
  form.append("productId", product.id);
  form.append("main_product_id", mainProductId);
  form.append("quantity", product.quantity);
  form.append("discount", enableDiscounts.value);

  try {
    const response = await axios.post("/suppliers/add-product-to-order", form);
    const { data, message } = response.data;
    toast.success(data || `Se añadieron ${product.quantity} productos.`);
    if (message && message.warning) {
      toast.warning(message.warning, { timeout: 8000 });
    }
    // Limpiar selección activa y refrescar ambas tablas
    selectedProductFromTop.value = null;
    filterSearchQuery.value = "";
    fetchProductsWithoutSupplier();
    fetchProducts();
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
      `/suppliers/${supplier.id}/delete-products`,
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

const handleToggleOrder = async (product) => {
  try {
    const { data } = await axios.patch(`/suppliers/${product.id}/toggle-order`);

    if (data.status === "success") {
      toast.success(data.message || "Estado actualizado correctamente");
      fetchProductsWithoutSupplier();
    }
  } catch (error) {
    toast.error("No se pudieron borrar el productos de la lista.");
  }
};

const handleSaveAnalysis = async ({ item, newValue }) => {
  try {
    toast.info("Enviando pedido directo...");
    const response = await axios.post(
      "/suppliers-ia-order-assistant/direct-order",
      {
        productId: item.id,
        quantity: newValue,
      },
    );

    if (response.data.status === "success") {
      toast.success(response.data.message || "Pedido añadido correctamente.");
      // Refrescar tabla de faltas (el producto puede ya no aparecer si se pidió completo)
      fetchProductsWithoutSupplier();
      fetchProducts();
      // Limpiar selección si era el mismo producto
      if (selectedProductFromTop.value?.id === item.id) {
        selectedProductFromTop.value = null;
        filterSearchQuery.value = "";
      }
    }
  } catch (error) {
    console.error(error);
    toast.error(
      error.response?.data?.message || "Error al procesar el pedido directo.",
    );
  }
};



const updateProductsWithoutSupplierOptions = (options) => {
  if (pageProductsWithoutSupplier.value === options.page && 
      itemsPerPageProductsWithoutSupplier.value === options.itemsPerPage &&
      sortByProductsWithoutSupplier.value === options.sortBy?.[0]?.key &&
      orderByProductsWithoutSupplier.value === options.sortBy?.[0]?.order) {
    return;
  }

  pageProductsWithoutSupplier.value = options.page;
  itemsPerPageProductsWithoutSupplier.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByProductsWithoutSupplier.value = options.sortBy[0].key;
    orderByProductsWithoutSupplier.value = options.sortBy[0].order;
  }
};

const handleOpenPublicLink = (supplier) => {
  supplierForPublicLink.value = supplier;
  isGeneratePublicLinkDialogActive.value = true;
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
    <GeneratePublicLinkDialog
      v-model:isDialogVisible="isGeneratePublicLinkDialogActive"
      :selected-supplier="supplierForPublicLink"
      @refresh="fetchSupplierConnections"
    />
    <DeleteOldProductsDialog
      v-model:isDialogVisible="isDeleteDialogVisible"
      @submit="handleDeleteOldProducts"
    />
    <VCard class="mb-6">
      <VCardText>
        <VTabs v-model="tab">
          <VTab value="suppliers"> Proveedores</VTab>
          <VTab value="products"> Productos</VTab>
        </VTabs>
        <ProductsComparisionProductsFilter
          v-if="tab === 'products'"
          @open-delete-dialog="isDeleteDialogVisible = true"
          @update-all-api="handleUpdateAllApi"
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
          :search-query="selectedSupplier"
          @update:search-query="selectedSupplier = $event"
          @update:options="updateTableOptions"
          @show-products="handleShowProducts"
          @update-products="handleCheckSupplierApi"
          @load-products="handleShowImportProductsDialog"
          @delete-products="handleDeleteSupplierProducts"
          @open-discount-dialog="handleShowDiscountDialog"
          @open-public-link="handleOpenPublicLink"
        />
      </VTabsWindowItem>

      <VTabsWindowItem value="products">
        <!-- FILA DE FILTROS MAESTROS -->
        <VRow class="mb-4">
          <VCol cols="12">
            <ProductsWithoutSupplierComparatorFilter
              v-model:selectConDescuento="con_descuento"
              v-model:selectedLaboratory="selectedLaboratory"
              v-model:selectedGroup="selectedGroup"
              v-model:tipo_de_vista="tipo_de_vista"
              v-model:tipo_de_filtracion="tipo_de_filtracion"
              v-model:lapso_de_tiempo="lapso_de_tiempo"
              v-model:stock="stock"
              :groups="groups"
              :laboratories="laboratoriesProductsWithoutSupplier"
              :tipo_de_filtracion="tipo_de_filtracion"
              :tipo_de_vista="tipo_de_vista"
              :lapso_de_tiempo="lapso_de_tiempo"
              @clear="handleClearFilters"
            />
          </VCol>
        </VRow>

        <VRow class="match-height">
          <!-- COLUMNA IZQUIERDA: Catálogo de Proveedores -->
          <VCol cols="12" md="6">
            <ProductComparisionProductsTable
              :products="products"
              :loading="loadingProducts"
              :total-products="productsTotal"
              :items-per-page="productsItemPerPage"
              :page="productsPage"
              :quantity-errors="quantityErrors"
              :enable-usd-amount-col="enableUsdAmountCol"
              :enable-discount-col="enableDiscountCol"
              :search-query="filterSearchQuery"
              @update:search-query="filterSearchQuery = $event"
              :is-strict-search="isStrictSearch"
              @update:is-strict-search="isStrictSearch = $event"
              :selected-product="selectedProductFromTop"
              @update:options="updateProductsTableOptions"
              @send-product="handleAddItemToAutoOrder"
            />
          </VCol>

          <!-- COLUMNA DERECHA: Productos sin proveedor -->
          <VCol cols="12" md="6">
            <ProductsWithoutSupplierComparatorTable
              v-model="selectedProductFromTop"
              :products="listProductsWithoutSupplier"
              :loading="loadingProductsWithoutSupplier"
              :total-products="totalProductsWithoutSupplier"
              :items-per-page="itemsPerPageProductsWithoutSupplier"
              :page="pageProductsWithoutSupplier"
              :search-query="searchQueryRight"
              @update:search-query="searchQueryRight = $event"
              @update:options="updateProductsWithoutSupplierOptions"
              @select-product="handleSelectProductFromTop"
              @delete="handleToggleOrder"
              @save-analysis="handleSaveAnalysis"
            />
          </VCol>
        </VRow>
      </VTabsWindowItem>
    </VTabsWindow>
  </div>
</template>
