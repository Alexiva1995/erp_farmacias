import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useSupplierConnectionStore } from "@/stores/supplierConnection";
import Swal from "sweetalert2";
import { onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";

export function useProductComparator() {
  const route = useRoute();
  const supplierConnectionStore = useSupplierConnectionStore();

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

  const searchedSupplier = ref(null);
  const searchedLaboratory = ref(null);
  let debounceTimerProductsWithoutSupplier;

  const isNeedsVisible = ref(false);
  const enableDiscounts = ref(false);

  const isShowSupplierProductsDialogActive = ref(false);
  const isShowImportFileDialogActive = ref(false);

  const isApplyDiscountDialogActive = ref(false);
  const supplierForDiscount = ref(null);
  const isGeneratePublicLinkDialogActive = ref(false);
  const supplierForPublicLink = ref(null);

  const isCatalogFiltersDialogVisible = ref(false);
  const isNeedsFiltersDialogVisible = ref(false);
  const checkingApiSupplierId = ref(null);
  const pollingSupplierId = ref(null);
  const pollingStatusId = ref(null);
  const pollingInterval = ref(null);
  const pollingStartTime = ref(null);

  const tab = ref(route.query.tab || "suppliers");
  const page = ref(1);
  const itemsPerPage = ref(10);
  const totalSupplierConnections = ref(0);

  // Variables de Productos
  const productsPage = ref(1);
  const productsItemPerPage = ref(10);
  const productsTotal = ref(0);
  const sortOptions = ref([{ key: "unit_cost_usd", order: "asc" }]);

  const enableUsdAmountCol = ref(true);
  const enableDiscountCol = ref(false);

  const isDeleteDialogVisible = ref(false);

  // Variables de productos sin proveedor
  const listProductsWithoutSupplier = ref([]);
  const totalProductsWithoutSupplier = ref(0);
  const loadingProductsWithoutSupplier = ref(false);
  const pageProductsWithoutSupplier = ref(1);
  const itemsPerPageProductsWithoutSupplier = ref(10);
  const sortByProductsWithoutSupplier = ref("solicitar");
  const orderByProductsWithoutSupplier = ref("desc");

  // Variable para rastrear la selección
  const selectedProductFromTop = ref(null);
  const searchQueryRight = ref("");

  // Variables filtro de productos sin proveedor
  const con_descuento = ref(true);
  const selectedLaboratory = ref([]);
  const selectedGroup = ref([]);
  const needsLaboratory = ref([]);
  const needsGroup = ref([]);
  const tipo_de_vista = ref(false);
  const tipo_de_filtracion = ref("combinado");
  const lapso_de_tiempo = ref("1 month");
  const stock = ref("fallas");
  const needsHasStock = ref("all");
  const needsIsColombian = ref(false);
  const needsIsNovaventa = ref(false);
  const laboratoriesProductsWithoutSupplier = ref([]);

  const handleClearFilters = () => {
    con_descuento.value = true;
    tipo_de_vista.value = false;
    tipo_de_filtracion.value = "combinado";
    lapso_de_tiempo.value = "1 month";
    stock.value = "fallas";
    needsHasStock.value = "all";
    needsIsColombian.value = false;
    needsIsNovaventa.value = false;
    selectedLaboratory.value = [];
    selectedGroup.value = [];
    needsLaboratory.value = [];
    needsGroup.value = [];
  };

  const handleSelectProductFromTop = (product) => {
    if (!product) {
      selectedProductFromTop.value = null;
      filterSearchQuery.value = "";
      return;
    }

    selectedProductFromTop.value = product;

    const namePart = product.name ? product.name.substring(0, 5) : "";
    const labPart = product.laboratory?.name ? product.laboratory.name.substring(0, 3) : "";
    const filter = `${namePart} ${labPart}`.trim();
    
    filterSearchQuery.value = filter.length > 1 ? filter : String(product.id);

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
      });

      if (result.isConfirmed) {
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

  const isUpdatingAllApi = ref(false);

  const handleUpdateAllApi = () => {
    Swal.fire({
      title: "¿Actualizar todos los proveedores?",
      text: "Este proceso se ejecutará en segundo plano y actualizará el inventario de todos los proveedores conectados vía API y FTP.",
      icon: "info",
      showCancelButton: true,
      confirmButtonColor: "#00bad1",
      cancelButtonColor: "#d33",
      confirmButtonText: "Sí, actualizar todo",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
    }).then(async (result) => {
      if (result.isConfirmed) {
        isUpdatingAllApi.value = true;
        pollingSupplierId.value = 'ALL';
        pollingStartTime.value = Date.now();
        try {
          const response = await axios.post("/suppliers/update-all-job");
          if (response.status === 200) {
            toast.success(
              "El proceso de actualización ha iniciado en segundo plano.",
            );
            supplierConnectionStore.startConnection();
            startPolling();
          } else {
            isUpdatingAllApi.value = false;
            pollingSupplierId.value = null;
            pollingStartTime.value = null;
          }
        } catch (error) {
          console.error("Error iniciando actualización masiva de APIs:", error);
          toast.error("No se pudo iniciar el proceso de actualización.");
          isUpdatingAllApi.value = false;
          pollingSupplierId.value = null;
          pollingStartTime.value = null;
        }
      }
    });
  };

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
        enableDiscounts.value = true;
        await fetchProducts();
      }
    } catch (error) {
      console.error("Error al aplicar descuento:", error);
      toast.error(error.response?.data?.message || "No se pudo aplicar el descuento.");
    }
  };

  const fetchProducts = async () => {
    const params = {
      page: productsPage.value,
      perPage: productsItemPerPage.value,
      supplierId: searchedSupplier.value,
      laboratoryId: searchedLaboratory.value,
      groupId: selectedGroup.value,
      q: filterSearchQuery.value,
      originId: selectedOrigin.value,
      isStrictSearch: isStrictSearch.value,
      enableDiscounts: enableDiscounts.value,
      ...(stockStatusFilter.value !== null && {
        hasStock: stockStatusFilter.value,
      }),
    };

    if (sortOptions.value && sortOptions.value.length > 0) {
      params.sortBy = sortOptions.value[0].key;
      params.order = sortOptions.value[0].order;
    }

    try {
      loadingProducts.value = true;
      const { data } = await axios.get("/suppliers/available-products", {
        params
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

  const handleRefreshAll = async () => {
    await Promise.all([
      fetchSupplierConnections(),
      fetchProducts(),
      fetchProductsWithoutSupplier()
    ]);
  };

  const fetchSupplierConnections = async () => {
    loadingSuppliers.value = true;
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
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
      const newStatuses = data.statuses || [];

      // Caso 1: Actualización masiva (todas las APIs)
      if (pollingSupplierId.value === 'ALL') {
        const recentStatuses = newStatuses.filter(
          (s) => !pollingStartTime.value || new Date(s.created_at).getTime() >= (pollingStartTime.value - 15000)
        );
        const hasPendingJobs = recentStatuses.some((s) => ['pending', 'processing', 'in_progress'].includes(s.status));

        // Si ya hay registros recientes y ninguno está pendiente/procesando
        if (recentStatuses.length > 0 && !hasPendingJobs) {
          isUpdatingAllApi.value = false;
          pollingSupplierId.value = null;
          pollingStatusId.value = null;
          pollingStartTime.value = null;
          checkingApiSupplierId.value = null;
          stopPolling();

          await fetchSupplierConnections();
          await fetchProducts();
          toast.success("Actualización de todas las listas completada exitosamente.");
        }
        return;
      }

      // Caso 2: Proveedor individual
      let currentStatus = null;
      if (pollingStatusId.value) {
        currentStatus = newStatuses.find((s) => s.id === pollingStatusId.value);
      }
      if (!currentStatus && pollingSupplierId.value) {
        currentStatus = newStatuses.find(
          (s) => s.supplier_id === pollingSupplierId.value &&
            (!pollingStartTime.value || new Date(s.created_at).getTime() >= (pollingStartTime.value - 15000))
        );
      }

      // Si aún no se crea el registro o si continúa procesándose, mantener spinner y seguir esperando
      if (!currentStatus || ["pending", "processing", "in_progress"].includes(currentStatus.status)) {
        return;
      }

      if (["completed", "failed"].includes(currentStatus.status)) {
        stopPolling();
        pollingSupplierId.value = null;
        pollingStatusId.value = null;
        pollingStartTime.value = null;
        checkingApiSupplierId.value = null;

        await fetchSupplierConnections();
        await fetchProducts();

        if (currentStatus.status === "failed") {
          toast.error(`La sincronización con el proveedor falló: ${currentStatus.message || ''}`);
        } else {
          toast.success("Sincronización completada exitosamente");
        }
      }
    } catch (error) {
      console.error("Error al consultar estados de conexión:", error);
    }
  };

  const fetchProductsWithoutSupplier = async (force = false) => {
    // Optimización: Solo consultar si el panel lateral está visible
    if (!isNeedsVisible.value && !force) return;

    try {
      if (loadingProductsWithoutSupplier.value) return;
      loadingProductsWithoutSupplier.value = true;

      const payload = {
        laboratoryId: needsLaboratory.value,
        groups: needsGroup.value,
        tipo_vista: tipo_de_vista.value,
        tipo_filtracion: tipo_de_filtracion.value,
        lapso_de_tiempo: lapso_de_tiempo.value,
        stock: stock.value,
        hasStock: needsHasStock.value,
        isColombian: needsIsColombian.value,
        isNovaventa: needsIsNovaventa.value,
        con_descuento: con_descuento.value,
        q: searchQueryRight.value,
        without_supplier: false,
        isStrictSearch: false,
        page: pageProductsWithoutSupplier.value,
        itemsPerPage: itemsPerPageProductsWithoutSupplier.value,
        sortBy: sortByProductsWithoutSupplier.value,
        orderBy: orderByProductsWithoutSupplier.value,
      };

      const response = await axios.post(
        `/suppliers-ia-assistant-report/filtrar-paginate?page=${pageProductsWithoutSupplier.value}`,
        payload,
      );

      const paginate = response?.data?.data ?? null;

      if (paginate && Array.isArray(paginate.data)) {
        listProductsWithoutSupplier.value = paginate.data;
        totalProductsWithoutSupplier.value = paginate.total ?? 0;
      } else {
        listProductsWithoutSupplier.value = [];
        totalProductsWithoutSupplier.value = 0;
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
    checkingApiSupplierId.value = null;
    pollingSupplierId.value = null;
    pollingStatusId.value = null;
  };

  const fetchOptions = async () => {
    try {
      const [labResponse, originResponse, suppliersResponse, groupsResponse] = await Promise.all([
        axios.get("/laboratories"),
        axios.get("/origins"),
        axios.get("/suppliers/available-suppliers"),
        axios.get("/groups"),
      ]);
      laboratories.value = labResponse.data?.data || labResponse.data || [];
      laboratoriesProductsWithoutSupplier.value = labResponse.data?.data || labResponse.data || [];
      origins.value = originResponse.data?.data || originResponse.data || [];
      suppliers.value = suppliersResponse.data?.data || suppliersResponse.data || [];
      groups.value = groupsResponse.data?.data || groupsResponse.data || [];
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
    // Lazy load: fetchProductsWithoutSupplier se ejecutará solo cuando el usuario abra el panel
  });

  onUnmounted(() => {
    stopPolling();
    clearTimeout(supplierDebounceTimer);
    clearTimeout(debounceTimerProductsWithoutSupplier);
  });

  let supplierDebounceTimer;
  watch(
    () => selectedSupplier.value,
    () => {
      page.value = 1;
      clearTimeout(supplierDebounceTimer);
      supplierDebounceTimer = setTimeout(() => fetchSupplierConnections(), 350);
    },
  );

  // Watcher para abrir el panel de necesidades (Lazy Load al expandir)
  watch(
    () => isNeedsVisible.value,
    (val) => {
      if (val && listProductsWithoutSupplier.value.length === 0) {
        fetchProductsWithoutSupplier(true);
      }
    }
  );

  watch(
    [
      selectedLaboratory,
      selectedGroup,
      needsLaboratory,
      needsGroup,
      tipo_de_vista,
      tipo_de_filtracion,
      lapso_de_tiempo,
      stock,
      needsHasStock,
      needsIsColombian,
      needsIsNovaventa,
      con_descuento,
      searchQueryRight,
      filterSearchQuery,
      searchedSupplier,
      searchedLaboratory,
      selectedOrigin,
      stockStatusFilter,
      isStrictSearch,
    ],
    () => {
      productsPage.value = 1;
      clearTimeout(debounceTimerProductsWithoutSupplier);
      debounceTimerProductsWithoutSupplier = setTimeout(() => {
        if (isNeedsVisible.value) {
          fetchProductsWithoutSupplier();
        }
        fetchProducts();
      }, 400);
    },
    { deep: true },
  );

  const updateTableOptions = (options) => {
    page.value = options.page || 1;
    itemsPerPage.value = options.itemsPerPage || 10;
    fetchSupplierConnections();
  };

  const updateProductsTableOptions = (options) => {
    productsPage.value = options.page || 1;
    productsItemPerPage.value = options.itemsPerPage || 10;
    if (options.sortBy) {
      sortOptions.value = options.sortBy;
    }
    fetchProducts();
  };

  const handleShowProducts = (supplier) => {
    supplierOption.value = supplier;
    isShowSupplierProductsDialogActive.value = true;
  };

  const handleCheckSupplierApi = async (supplier) => {
    checkingApiSupplierId.value = supplier.id;
    pollingSupplierId.value = supplier.id;
    pollingStartTime.value = Date.now();

    try {
      toast.info(
        `Procesando los datos de ${supplier.name}, le notificaremos al finalizar`,
      );
      const response = await axios.get(`/suppliers/${supplier.id}/connection`);
      if (response.data?.status_id) {
        pollingStatusId.value = response.data.status_id;
      }
      
      supplierConnectionStore.startConnection();
      startPolling();
    } catch (error) {
      const errorDetail = error?.response?.data?.message || error?.message || "";
      toast.error(`No se pudo iniciar la conexión con ${supplier.name}${errorDetail ? `: ${errorDetail}` : ""}`);
      pollingSupplierId.value = null;
      pollingStatusId.value = null;
      pollingStartTime.value = null;
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

  const handleAddItemToAutoOrder = async (product, onComplete) => {
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
      fetchProductsWithoutSupplier();
    } catch (error) {
      if (error.response?.status === 422) {
        quantityErrors[product.id] = error.response.data.errors.quantity?.[0];
      }
      console.error("Hubo un error al enviar la petición:", error);
      toast.error(error.response?.data?.message || "Error al añadir productos al pedido.");
    } finally {
      if (typeof onComplete === "function") {
        onComplete();
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
        fetchProductsWithoutSupplier();
        fetchProducts();
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
    fetchProductsWithoutSupplier();
  };

  const handleOpenPublicLink = (supplier) => {
    supplierForPublicLink.value = supplier;
    isGeneratePublicLinkDialogActive.value = true;
  };

  const handleToggleProductSupplierStatus = async (item) => {
    if (!item?.id) return;
    try {
      const response = await axios.patch(
        `/suppliers/product-suppliers/${item.id}/toggle-status`,
      );
      toast.success(response.data.message || "Estado actualizado correctamente.");
      await fetchProducts();
    } catch (error) {
      console.error("Error al alternar estado del producto de proveedor:", error);
      toast.error(
        error.response?.data?.message || "No se pudo cambiar el estado de la oferta.",
      );
    }
  };

  const handleToggleSupplierStatus = async (supplier) => {
    if (!supplier?.id) return;
    try {
      const response = await axios.patch(
        `/suppliers/${supplier.id}/toggle-status`,
      );
      toast.success(response.data.message || "Estado del proveedor actualizado correctamente.");
      await fetchSupplierConnections();
    } catch (error) {
      console.error("Error al alternar estado del proveedor:", error);
      toast.error(
        error.response?.data?.message || "No se pudo cambiar el estado del proveedor.",
      );
    }
  };

  return {
    supplierConnections,
    suppliers,
    origins,
    laboratories,
    groups,
    products,
    loadingSuppliers,
    loadingProducts,
    quantityErrors,
    supplierOption,
    selectedSupplier,
    selectedOrigin,
    filterSearchQuery,
    stockStatusFilter,
    isStrictSearch,
    searchedSupplier,
    searchedLaboratory,
    enableDiscounts,
    isShowSupplierProductsDialogActive,
    isShowImportFileDialogActive,
    isApplyDiscountDialogActive,
    supplierForDiscount,
    isGeneratePublicLinkDialogActive,
    supplierForPublicLink,
    isCatalogFiltersDialogVisible,
    isNeedsFiltersDialogVisible,
    checkingApiSupplierId,
    tab,
    page,
    itemsPerPage,
    totalSupplierConnections,
    productsPage,
    productsItemPerPage,
    productsTotal,
    sortOptions,
    isNeedsVisible,
    enableUsdAmountCol,
    enableDiscountCol,
    isDeleteDialogVisible,
    listProductsWithoutSupplier,
    totalProductsWithoutSupplier,
    loadingProductsWithoutSupplier,
    pageProductsWithoutSupplier,
    itemsPerPageProductsWithoutSupplier,
    selectedProductFromTop,
    searchQueryRight,
    con_descuento,
    selectedLaboratory,
    selectedGroup,
    needsLaboratory,
    needsGroup,
    tipo_de_vista,
    tipo_de_filtracion,
    lapso_de_tiempo,
    stock,
    needsHasStock,
    needsIsColombian,
    needsIsNovaventa,
    handleClearFilters,
    handleSelectProductFromTop,
    handleShowDiscountDialog,
    handleDeleteOldProducts,
    handleUpdateAllApi,
    handleApplyDiscount,
    fetchProducts,
    handleRefreshAll,
    fetchSupplierConnections,
    fetchProductsWithoutSupplier,
    updateTableOptions,
    updateProductsTableOptions,
    handleShowProducts,
    handleCheckSupplierApi,
    handleClearProductsFilters,
    handleAddItemToAutoOrder,
    handleShowImportProductsDialog,
    handleHideImportProductsDialog,
    handleDeleteSupplierProducts,
    handleToggleOrder,
    handleSaveAnalysis,
    updateProductsWithoutSupplierOptions,
    handleOpenPublicLink,
    handleToggleProductSupplierStatus,
    handleToggleSupplierStatus,
    isUpdatingAllApi,
  };
}
