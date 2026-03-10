<script setup>
import BarcodeSearchModal from "@/components/dialogs/BarcodeSearchModal.vue";
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, nextTick, onMounted, ref, watch } from "vue";

const props = defineProps({
  invoiceId: { type: [Number, String], required: true },
  initialInvoice: { type: Object, default: null },
  mode: { type: String, default: "editable" },
  supplierDiscounts: { type: Array, default: () => [] },
  paymentRules: { type: [Array, Object], default: () => [] },
  isSaving: { type: Boolean, default: false },
});

const emit = defineEmits([
  "back-to-list",
  "confirm-approval",
  "reject-invoice",
]);

const invoice = ref(null);
const invoiceDetails = ref([]);
const formData = ref({});
const loading = ref(true);
const loadingDetails = ref(true);

const isEditableMode = computed(() => props.mode === "editable");
const isLocationMode = computed(() => props.mode === "location");
const isReadOnly = computed(() => props.mode === "read-only");
const isApprovalMode = computed(() => props.mode === "approval");

const isEditMode = ref(false);
const selectedSupplierDiscountId = ref(null);
const selectedPaymentRuleId = ref(null);
const editingDetailId = ref(null);
const editedDetailData = ref({});

const products = ref([]);
const totalProducts = ref(0);
const loadingProducts = ref(false);
const productSearchQuery = ref("");
const productPage = ref(1);
const productItemsPerPage = ref(10);
const productSortBy = ref();
const productOrderBy = ref();
const laboratories = ref([]);
const origins = ref([]);
const categories = ref([]);
const isLoadingFilters = ref(false);
const isEditDialogVisible = ref(false);
const isBarcodeModalVisible = ref(false);
const isProductSearchVisible = ref(false);
const searchingBarcode = ref(false);
const currentProduct = ref({});
const productFormErrors = ref({});
const barcodeModalRef = ref(null);
const isScannerMode = ref(false);
const barcodeInput = ref("");
const scannerLoading = ref(false);
const scannerInputRef = ref(null);

const locations = [
  "E-001",
  "E-002",
  "E-003",
  "E-004",
  "E-005",
  "G-001",
  "G-002",
  "G-003",
  "G-004",
  "G-005",
  "G-006",
  "G-007",
  "G-008",
  "G-009",
  "G-010",
  "G-011",
  "G-012",
  "G-013",
  "G-014",
  "G-015",
  "G-016",
  "G-017",
  "G-018",
  "G-019",
  "G-020",
  "G-021",
  "G-022",
  "G-023",
  "G-024",
  "I-001",
  "I-002",
  "I-003",
  "I-004",
  "I-005",
  "I-006",
  "I-007",
  "I-008",
  "I-009",
  "I-010",
  "I-011",
  "I-012",
  "I-013",
  "I-014",
  "I-015",
  "I-016",
  "N-001",
  "D-001",
  "M-001",
  "M-002",
  "M-003",
  "M-004",
  "M-005",
].sort();

const formattedPaymentRules = computed(() => {
  const rules = props.paymentRules.payment_rules;

  if (!rules || !Array.isArray(rules)) {
    return [];
  }

  return rules.map((rule) => ({
    ...rule,
    displayText: `${rule.discount_percentage}% - ${
      rule.name || rule.days + " Días"
    }`,
  }));
});

const getRowProps = (data) => {
  const item = data.item;
  const classes = [];

  if (isEditableMode.value && isEditMode.value) {
    classes.push("draggable-row");
  }

  // Check if product is "new" (pending approval / is_deleted)

  if (
    item.product &&
    (item.product.is_deleted == 1 || item.product.is_deleted === true)
  ) {
    // Force background color via style for dark mode compatibility
    return {
      class: "new-product-row",
      style:
        "background-color: rgba(30, 200, 50, 0.15) !important; border-left: 4px solid #4CAF50 !important;",
    };
  }

  return {};
};

const processedInvoiceDetails = computed(() => {
  if (!invoice.value || !invoiceDetails.value) return [];

  let discountPercentage = 0;

  if (isEditableMode.value && selectedSupplierDiscountId.value) {
    const discount = props.supplierDiscounts.find(
      (d) => d.id === selectedSupplierDiscountId.value,
    );

    if (discount)
      discountPercentage = Number(discount.discount_percentage) || 0;
  } else if (isApprovalMode.value && selectedPaymentRuleId.value) {
    const rules = Array.isArray(props.paymentRules)
      ? props.paymentRules
      : props.paymentRules?.payment_rules || [];

    const rule = rules.find((r) => r.id === selectedPaymentRuleId.value);

    if (rule) discountPercentage = Number(rule.discount_percentage) || 0;
  }

  return invoiceDetails.value.map((detail) => {
    const quantity = Number(detail.quantity) || 0;
    const unitCost = Number(detail.unit_cost) || 0;

    let finalTotal = 0;
    let taxAmount = 0;

    if (discountPercentage > 0) {
      const discountAmount = unitCost * (discountPercentage / 100);
      const discountedUnitCost = unitCost - discountAmount;
      const baseTotal = quantity * discountedUnitCost;

      if (detail.tax_enabled) {
        taxAmount = baseTotal * 0.16;
      }
      finalTotal = baseTotal + taxAmount;
    } else {
      if (isEditMode.value) {
        const baseTotal = quantity * unitCost;

        if (detail.tax_enabled) {
          taxAmount = baseTotal * 0.16;
          finalTotal = baseTotal + taxAmount;
        } else {
          taxAmount = 0;
          finalTotal = baseTotal;
        }
      } else {
        finalTotal = parseFloat(detail.total_cost) || 0;

        if (detail.tax_enabled) {
          taxAmount = finalTotal - finalTotal / 1.16;
        } else {
          taxAmount = 0;
        }
      }
    }

    const rate = parseFloat(invoice.value.exchange_rate) || 1;
    const isUsd = invoice.value.currency === "USD";
    const hasValidRate = rate && rate > 0;

    const unitCostUsd = isUsd
      ? unitCost
      : hasValidRate
        ? unitCost / rate
        : unitCost;

    const totalCostUsd = isUsd
      ? finalTotal
      : hasValidRate
        ? finalTotal / rate
        : finalTotal;

    return {
      ...detail,
      product_name_with_tax: detail.tax_enabled
        ? `${detail.product?.name || "Sin nombre"} (G)`
        : detail.product?.name || "Sin nombre",
      tax_amount: taxAmount,
      total_cost: finalTotal,
      unit_cost_usd: unitCostUsd,
      total_cost_usd: totalCostUsd,
    };
  });
});

const editableDetailsTotal = computed(() => {
  if (!processedInvoiceDetails.value) return 0;

  return processedInvoiceDetails.value.reduce((accumulator, currentDetail) => {
    const cost = Number(currentDetail.total_cost) || 0;

    return accumulator + cost;
  }, 0);
});

const isTotalMismatch = computed(() => {
  if (!invoice.value) return false;

  // Calcular la tolerancia de ±1 USD en la moneda de la factura
  const isUsd = invoice.value.currency === "USD";
  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const hasValidRate = rate && rate > 0;

  // Tolerancia de 1 USD convertida a la moneda de la factura
  const toleranceInCurrency = isUsd ? 1 : hasValidRate ? 1 * rate : 1;

  const difference = Math.abs(
    editableDetailsTotal.value - invoice.value.total_amount,
  );

  // Permitir diferencia de hasta 1 USD (convertido a la moneda de la factura)
  return difference > toleranceInCurrency;
});

const totalWithDiscount = computed(() => {
  if (!invoice.value || !selectedPaymentRuleId.value) {
    return invoice.value?.total_amount || 0;
  }

  const rules = Array.isArray(props.paymentRules)
    ? props.paymentRules
    : props.paymentRules?.payment_rules || [];

  const rule = rules.find((r) => r.id === selectedPaymentRuleId.value);

  if (!rule) {
    return invoice.value.total_amount;
  }
  const discountPercentage = Number(rule.discount_percentage) || 0;

  const discountAmount =
    invoice.value.total_amount * (discountPercentage / 100);

  return invoice.value.total_amount - discountAmount;
});

const editableDetailsTaxAmount = computed(() => {
  if (!processedInvoiceDetails.value) return 0;

  return processedInvoiceDetails.value.reduce((accumulator, currentDetail) => {
    if (currentDetail.tax_enabled) {
      return accumulator + (currentDetail.tax_amount || 0);
    }

    return accumulator;
  }, 0);
});

const isTaxAmountMismatch = computed(() => {
  if (!invoice.value) return false;
  const invoiceTaxAmount = parseFloat(invoice.value.tax_amount) || 0;
  if (invoiceTaxAmount === 0) return false;

  // Calcular la tolerancia de ±0.5 USD en la moneda de la factura
  const isUsd = invoice.value.currency === "USD";
  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const hasValidRate = rate && rate > 0;

  // Tolerancia de 0.5 USD convertida a la moneda de la factura
  const toleranceInCurrency = isUsd ? 0.5 : hasValidRate ? 0.5 * rate : 0.5;

  const difference = Math.abs(
    editableDetailsTaxAmount.value - invoiceTaxAmount,
  );

  // Permitir diferencia de hasta 0.5 USD (convertido a la moneda de la factura)
  return difference > toleranceInCurrency;
});

const getCostComparisonClass = (item) => {
  if (!isApprovalMode.value) {
    return "";
  }

  if (!item.product || typeof item.product.unit_cost === "undefined") {
    return "";
  }

  const systemCostUSD = Number(item.product.unit_cost);

  if (systemCostUSD === 0 || systemCostUSD === null || isNaN(systemCostUSD)) {
    return "cost-new-product";
  }

  const invoiceCostInLocalCurrency = Number(item.unit_cost);
  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const isUsd = invoice.value.currency === "USD";
  const hasValidRate = rate && rate > 0;

  let invoiceCostUSD;
  if (isUsd) {
    invoiceCostUSD = invoiceCostInLocalCurrency;
  } else if (hasValidRate) {
    invoiceCostUSD = invoiceCostInLocalCurrency / rate;
  } else {
    return "";
  }

  if (isNaN(invoiceCostUSD)) {
    return "";
  }

  const tolerance = 0.001;

  if (invoiceCostUSD > systemCostUSD + tolerance) {
    return "cost-higher";
  } else if (invoiceCostUSD < systemCostUSD - tolerance) {
    return "cost-lower";
  }

  return "";
};

const getCostTooltipText = (item) => {
  if (
    !isApprovalMode.value ||
    !item.product ||
    typeof item.product.unit_cost === "undefined"
  ) {
    return "";
  }

  const systemCostUSD = Number(item.product.unit_cost);

  if (systemCostUSD === 0 || systemCostUSD === null || isNaN(systemCostUSD)) {
    return "Producto Nuevo - Sin costo registrado en el sistema";
  }

  const invoiceCostInLocalCurrency = Number(item.unit_cost);
  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const isUsd = invoice.value.currency === "USD";
  const hasValidRate = rate && rate > 0;

  let invoiceCostUSD;
  if (isUsd) {
    invoiceCostUSD = invoiceCostInLocalCurrency;
  } else if (hasValidRate) {
    invoiceCostUSD = invoiceCostInLocalCurrency / rate;
  } else {
    return `Costo en Sistema: ${formatCurrency(
      systemCostUSD,
      "USD",
    )} (No se puede comparar - tasa inválida)`;
  }

  return `Costo en Sistema: ${formatCurrency(
    systemCostUSD,
    "USD",
  )} | Factura: ${formatCurrency(invoiceCostUSD, "USD")}`;
};

onMounted(async () => {
  await fetchInvoiceData(props.invoiceId);
  if (invoice.value) {
    await fetchInvoiceDetails(props.invoiceId);
  }
});

watch(isEditMode, (newVal) => {
  if (isEditableMode.value && !newVal) {
    cancelEditingDetail();
    isProductSearchVisible.value = false;
    selectedSupplierDiscountId.value =
      invoice.value?.supplier_discount_id || null;
  }
});

const watchProps = () => {
  if (props.mode === "approval") {
    selectedPaymentRuleId.value = null;
  }
};

watch(() => props.mode, watchProps, { immediate: true });

const toggleReturnItem = (itemToToggle) => {
  const index = invoiceDetails.value.findIndex((d) => d.id === itemToToggle.id);
  if (index !== -1) {
    const item = invoiceDetails.value[index];

    if (item.is_return && isNearExpiration(item)) {
      item.manual_return_override = true;
    }

    item.is_return = !item.is_return;
    if (item.is_return) {
      item.location = "N/A";
      item.manual_return_override = false;
    } else {
      item.location = "Por Asignar";
    }
  }
};

const isItemReturned = (item) => {
  return !!item.is_return;
};

const fetchInvoiceData = async (id) => {
  loading.value = true;
  try {
    const response = await axios.get(`/invoices/${id}`);

    invoice.value = response.data.data;
    if (isEditableMode.value) {
      formData.value = JSON.parse(JSON.stringify(invoice.value));
      selectedSupplierDiscountId.value =
        invoice.value.supplier_discount_id || null;
    }
  } catch (error) {
    console.error("Error al cargar la factura:", error);
    toast.error("No se pudo cargar la información de la factura.");
    emit("back-to-list");
  } finally {
    loading.value = false;
  }
};

const fetchInvoiceDetails = async (id) => {
  loadingDetails.value = true;
  try {
    const response = await axios.get(`/invoices/${id}/details`);
    const combinedDetailsFromApi = response.data.data ?? [];

    invoiceDetails.value = combinedDetailsFromApi.map((detail, index) => {
      return {
        ...detail,
        tax_enabled: !!detail.tax_enabled,
        is_return: !!detail.is_return,
        manual_return_override: !!detail.manual_return_override || false,
        display_order: detail.display_order ?? index,
      };
    });
  } catch (error) {
    console.error("Error al cargar los detalles de la factura:", error);
    toast.error("No se pudieron cargar los productos de la factura.");
    invoiceDetails.value = [];
  } finally {
    loadingDetails.value = false;
  }
};

const handleSaveProgress = async () => {
  loading.value = true;

  console.log("Saving progress...", {
    isEditMode: isEditMode.value,
    detailsCount: invoiceDetails.value.length,
    timestamp: new Date().toISOString(),
  });

  const payload = {
    invoice: {
      ...formData.value,
      supplier_discount_id: selectedSupplierDiscountId.value,
    },
    details: invoiceDetails.value
      .filter((d) => d.product && d.product.id)
      .map((d, index) => ({
        product: { id: d.product.id },
        quantity: d.quantity,
        unit_cost: d.unit_cost,
        lot_number: d.lot_number,
        expiration_date: d.expiration_date,
        location: d.location,
        tax_enabled: d.tax_enabled,
        is_return: !!d.is_return,
        display_order: d.display_order ?? index,
      })),
  };

  try {
    const response = await axios.put(
      `/invoices/${props.invoiceId}/save-details`,
      payload,
    );

    toast.success(response.data.message || "Progreso guardado.");
    invoice.value = response.data.invoice;
    await fetchInvoiceDetails(props.invoiceId);

    isEditMode.value = false;

    return true;
  } catch (error) {
    toast.error(
      error.response?.data?.message || "No se pudo guardar el progreso.",
    );

    return false;
  } finally {
    loading.value = false;
  }
};

const handleConfirmApproval = () => {
  emit("confirm-approval", {
    paymentRuleId: selectedPaymentRuleId.value,
  });
};

const handleReject = async () => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Esta factura será devuelta a la lista de carga de productos.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Sí, rechazar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    emit("reject-invoice");
  }
};

const fetchProductSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
    ]);

    laboratories.value = labResponse.data.data ?? labResponse.data ?? [];
    origins.value = originResponse.data.data ?? originResponse.data ?? [];
    categories.value = categoryResponse.data ?? [];
  } catch (error) {
    console.error("Error al cargar opciones de filtros de productos:", error);
    toast.error("No se pudieron cargar los filtros de productos.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loadingProducts.value = true;

  const params = {
    q: productSearchQuery.value,
    page: productPage.value,
    itemsPerPage: productItemsPerPage.value,
    sortBy: productSortBy.value,
    orderBy: productOrderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );
  try {
    const response = await axios.get("/products", { params });

    products.value = response.data.data ?? response.data ?? [];
    totalProducts.value = response.data.total ?? 0;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loadingProducts.value = false;
  }
};

let productDebounceTimer;
watch(
  [
    productPage,
    productItemsPerPage,
    productSortBy,
    productOrderBy,
    productSearchQuery,
  ],
  () => {
    clearTimeout(productDebounceTimer);
    productDebounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true },
);

watch([productSearchQuery], () => {
  if (productPage.value !== 1) productPage.value = 1;
});

const updateProductTableOptions = (options) => {
  productPage.value = options.page;
  productItemsPerPage.value = options.itemsPerPage;
  productSortBy.value = options.sortBy[0]?.key;
  productOrderBy.value = options.sortBy[0]?.order;
};

const toggleEditMode = (enable) => {
  if (isReadOnly.value || isLocationMode.value) return;
  isEditMode.value = enable;
  if (!enable) {
    formData.value = JSON.parse(JSON.stringify(invoice.value));
    fetchInvoiceDetails(props.invoiceId);
  }
};

const addProductToInvoice = (product) => {
  const existingDetail = invoiceDetails.value.find(
    (detail) => detail.product.id === product.id,
  );

  if (existingDetail) {
    existingDetail.quantity += 1;
    startEditingDetail(existingDetail);
  } else {
    const newDetail = {
      id: -Math.floor(Math.random() * 1000),
      product: {
        id: product.id,
        name: product.name,
        iva: product.iva,
        unit_cost: product.unit_cost,
      },
      quantity: 1,
      unit_cost: 0,
      lot_number: "",
      expiration_date: null,
      location: "Por Asignar",
      tax_enabled: invoiceHasIva.value ? !!product.iva : false,
      is_return: false,
      manual_return_override: false,
      display_order: invoiceDetails.value.length,
    };

    invoiceDetails.value.push(newDetail);
    startEditingDetail(newDetail);
  }
};

const toggleTax = (detailToToggle) => {
  if (!invoiceHasIva.value) {
    toast.warning(
      "Esta factura no permite productos con IVA según su configuración fiscal.",
    );

    return;
  }

  const index = invoiceDetails.value.findIndex(
    (d) => d.id === detailToToggle.id,
  );

  if (index !== -1) {
    invoiceDetails.value[index].tax_enabled =
      !invoiceDetails.value[index].tax_enabled;
  }
};

const handleAddProduct = () => {
  isBarcodeModalVisible.value = true;
};

const handleSearchBarcode = async (barcode) => {
  searchingBarcode.value = true;
  try {
    const response = await axios.get(`/products/search-by-barcode`, {
      params: { barcode },
    });

    if (response.data.data) {
      barcodeModalRef.value?.handleProductFound(response.data.data);
    } else {
      barcodeModalRef.value?.handleProductNotFound();
    }
  } catch (error) {
    console.error("Error al buscar producto por código de barras:", error);
    barcodeModalRef.value?.handleProductNotFound();
  } finally {
    searchingBarcode.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "";

  return new Date(dateString).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
};

const handleShowProductSearch = () => {
  isProductSearchVisible.value = true;
  if (laboratories.value.length === 0) fetchProductSelectOptions();
  fetchProducts();
};

const handleAddNewProduct = async () => {
  if (
    laboratories.value.length === 0 ||
    origins.value.length === 0 ||
    categories.value.length === 0
  ) {
    await fetchProductSelectOptions();
  }
  currentProduct.value = {};
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveProduct = async (productFormData) => {
  const url = "/products";
  try {
    await axios.post(url, productFormData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    toast.success("Producto creado con éxito");
    isEditDialogVisible.value = false;
    if (isProductSearchVisible.value) {
      await fetchProducts();
    }
  } catch (error) {
    if (error.response && error.response.status === 422) {
      productFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar el producto:", error);
      toast.error("Hubo un error al guardar el producto.");
    }
  }
};

const removeProductFromInvoice = (detailId) => {
  invoiceDetails.value = invoiceDetails.value.filter(
    (detail) => detail.id !== detailId,
  );
};

const startEditingDetail = (detail) => {
  editedDetailData.value = { ...detail };
  
  // Si no tiene fecha, predefinir el día 1 del mes actual para facilitar la edición manual rápida
  if (!editedDetailData.value.expiration_date) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    editedDetailData.value.expiration_date = `${year}-${month}-01`;
  }
  
  editingDetailId.value = detail.id;
};

const saveEditingDetail = () => {
  if (
    !editedDetailData.value.quantity ||
    editedDetailData.value.quantity <= 0
  ) {
    toast.error("La cantidad debe ser mayor a 0");

    return;
  }
  if (
    editedDetailData.value.unit_cost === null ||
    editedDetailData.value.unit_cost < 0
  ) {
    toast.error("El costo por unidad debe ser 0 o mayor");

    return;
  }

  if (!editedDetailData.value.lot_number?.trim()) {
    const itemType = editedDetailData.value.is_return
      ? "devolución"
      : "producto";

    toast.error(`El número de lote es obligatorio para este ${itemType}`);

    return;
  }
  if (!editedDetailData.value.expiration_date) {
    const itemType = editedDetailData.value.is_return
      ? "devolución"
      : "producto";

    toast.error(`La fecha de vencimiento es obligatoria para este ${itemType}`);

    return;
  }

  const originalDetail = invoiceDetails.value.find(
    (d) => d.id === editingDetailId.value,
  );

  const isFirstTimeSettingDate =
    !originalDetail?.expiration_date && editedDetailData.value.expiration_date;

  if (isFirstTimeSettingDate) {
    checkAndMarkAsReturn(editedDetailData.value, true);
  }

  const detailIndex = invoiceDetails.value.findIndex(
    (d) => d.id === editingDetailId.value,
  );

  if (detailIndex !== -1) {
    invoiceDetails.value[detailIndex] = { ...editedDetailData.value };

    const itemType = editedDetailData.value.is_return
      ? "devolución"
      : "producto";

    toast.success(
      `${
        itemType.charAt(0).toUpperCase() + itemType.slice(1)
      } actualizado correctamente`,
    );

    // Buscar el siguiente producto que necesite datos (Lote o Vencimiento vacío)
    const nextIncompleteDetail = invoiceDetails.value.find(
      (d, index) =>
        index > detailIndex && (!d.lot_number?.trim() || !d.expiration_date),
    );

    if (nextIncompleteDetail) {
      // Pequeño retraso para que el usuario vea el feedback del guardado antes de saltar al siguiente
      setTimeout(() => {
        startEditingDetail(nextIncompleteDetail);
      }, 300);
    } else {
      cancelEditingDetail();
    }
  } else {
    cancelEditingDetail();
  }
};

const cancelEditingDetail = () => {
  editingDetailId.value = null;
  editedDetailData.value = {};
};

const updateLocation = (id, newLocation) => {
  const index = invoiceDetails.value.findIndex((d) => d.id === id);
  if (index !== -1) {
    invoiceDetails.value[index].location = newLocation;
  }
};

const handleSaveLocations = async () => {
  const hasEmptyLocation = invoiceDetails.value.some(
    (d) =>
      !d.is_return &&
      (!d.location ||
        d.location.trim() === "" ||
        d.location.trim() === "Por Asignar" ||
        d.location.trim() === "N/A"),
  );

  if (hasEmptyLocation) {
    toast.error(
      "Por favor, asigne una localización a todos los productos que no son devolución.",
    );

    return;
  }

  loading.value = true;

  const payload = {
    details: invoiceDetails.value
      .filter((d) => !d.is_return)
      .map((d) => ({
        id: d.id,
        location: d.location,
      })),
  };

  try {
    const response = await axios.put(
      `/invoices/${props.invoiceId}/locations`,
      payload,
    );

    toast.success(response.data.message || "Ubicaciones guardadas con éxito.");
    emit("back-to-list");
  } catch (error) {
    toast.error(
      error.response?.data?.message ||
        "No se pudieron guardar las ubicaciones.",
    );
  } finally {
    loading.value = false;
  }
};

const formatNumber = (value) => {
  if (typeof value !== "number") return value;

  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

const formatCurrency = (value, currency = null) => {
  if (typeof value !== "number") return value;
  const targetCurrency = currency || invoice.value?.currency;
  const currencyMap = { BS: "VES", Bs: "VES", COP: "COP", USD: "USD" };
  const mappedCurrency = currencyMap[targetCurrency] || "VES";

  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: mappedCurrency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

const getCurrencySymbol = () => {
  if (!invoice.value?.currency) return "Bs.";
  const symbolMap = { BS: "Bs.", Bs: "Bs.", USD: "$", COP: "COP$" };

  return symbolMap[invoice.value.currency] || "Bs.";
};

const isNearExpiration = (item) => {
  if (!item.expiration_date) return false;

  const expirationDate = new Date(item.expiration_date);
  const today = new Date();
  const sixMonthsFromNow = new Date();

  sixMonthsFromNow.setMonth(today.getMonth() + 6);

  return expirationDate <= sixMonthsFromNow;
};

const checkAndMarkAsReturn = (item, forceCheck = false) => {
  if (!item.expiration_date) return false;

  const expirationDate = new Date(item.expiration_date);
  const today = new Date();
  const sixMonthsFromNow = new Date();

  sixMonthsFromNow.setMonth(today.getMonth() + 6);

  const isNearExp = expirationDate <= sixMonthsFromNow;

  if (
    isNearExp &&
    !item.is_return &&
    !item.manual_return_override &&
    forceCheck
  ) {
    item.is_return = true;
    item.location = "N/A";

    toast.info(
      `Producto "${
        item.product?.name || "Desconocido"
      }" marcado automáticamente como devolución por proximidad a vencimiento (${
        item.expiration_date
      })`,
    );

    return true;
  }

  return isNearExp;
};

const invoiceHasIva = computed(() => {
  if (!invoice.value) return false;
  const taxableBase = parseFloat(invoice.value.taxable_base) || 0;
  const taxAmount = parseFloat(invoice.value.tax_amount) || 0;

  return taxableBase > 0 || taxAmount > 0;
});

const moveItemUp = (item) => {
  if (!isEditableMode.value || !isEditMode.value) return;

  const index = invoiceDetails.value.findIndex((d) => d.id === item.id);
  if (index > 0) {
    const temp = invoiceDetails.value[index];

    invoiceDetails.value[index] = invoiceDetails.value[index - 1];
    invoiceDetails.value[index - 1] = temp;

    // Actualizar display_order
    invoiceDetails.value.forEach((detail, idx) => {
      detail.display_order = idx;
    });
  }
};

const moveItemDown = (item) => {
  if (!isEditableMode.value || !isEditMode.value) return;

  const index = invoiceDetails.value.findIndex((d) => d.id === item.id);
  if (index < invoiceDetails.value.length - 1) {
    const temp = invoiceDetails.value[index];

    invoiceDetails.value[index] = invoiceDetails.value[index + 1];
    invoiceDetails.value[index + 1] = temp;

    // Actualizar display_order
    invoiceDetails.value.forEach((detail, idx) => {
      detail.display_order = idx;
    });
  }
};

const draggedItem = ref(null);
const draggedOverItem = ref(null);

const handleDragStart = (item) => {
  if (!isEditableMode.value || !isEditMode.value) return;
  draggedItem.value = item;
};

const handleDragOver = (event, item) => {
  if (!isEditableMode.value || !isEditMode.value) return;
  event.preventDefault();
  draggedOverItem.value = item;
};

const handleDrop = (item) => {
  if (!isEditableMode.value || !isEditMode.value || !draggedItem.value) return;

  const draggedIndex = invoiceDetails.value.findIndex(
    (d) => d.id === draggedItem.value.id,
  );
  const dropIndex = invoiceDetails.value.findIndex((d) => d.id === item.id);

  if (draggedIndex !== -1 && dropIndex !== -1 && draggedIndex !== dropIndex) {
    const [removed] = invoiceDetails.value.splice(draggedIndex, 1);

    invoiceDetails.value.splice(dropIndex, 0, removed);

    // Actualizar display_order
    invoiceDetails.value.forEach((detail, idx) => {
      detail.display_order = idx;
    });
  }

  draggedItem.value = null;
  draggedOverItem.value = null;
};

const handleDragEnd = () => {
  draggedItem.value = null;
  draggedOverItem.value = null;
};

const handleBarcodeScan = async () => {
  if (!barcodeInput.value || scannerLoading.value) return;

  if (!invoice.value?.auto_order_id) {
    toast.fire({
      icon: "error",
      title: "Esta factura no tiene una Auto-Orden asociada.",
    });
    barcodeInput.value = "";
    return;
  }

  scannerLoading.value = true;
  try {
    const response = await axios.post("/invoices/match-barcode", {
      barcode: barcodeInput.value,
      supplier_id: invoice.value.supplier_id,
      auto_order_id: invoice.value.auto_order_id || null,
    });

    if (response.data.status === "success" || response.data.status === "warning") {
      const newDetail = response.data.data;

      // Asegurar que estamos en modo edición si encontramos algo
      if (!isEditMode.value) {
        toggleEditMode(true);
      }

      // Verificar si ya existe en la lista para evitar duplicados accidentales
      const existingIndex = invoiceDetails.value.findIndex(
        (d) => d.product_id === newDetail.product_id,
      );

      if (existingIndex !== -1) {
        toast.fire({
          icon: "info",
          title: "El producto ya está en la lista.",
        });
      } else {
        // Agregar al inicio de la lista
        invoiceDetails.value.unshift({
          ...newDetail,
          id: `new_${Date.now()}`,
        });

        if (response.data.status === "warning") {
          toast.fire({
            icon: "warning",
            title: "Producto extra",
            text: response.data.message,
            timer: 2000,
          });
        } else {
          toast.fire({
            icon: "success",
            title: `Producto añadido: ${newDetail.product.name}`,
            timer: 1500,
          });
        }
      }
    }
  } catch (error) {
    console.error("Scanner error:", error);
    toast.fire({
      icon: "error",
      title: error.response?.data?.message || "Error al escanear producto",
    });
  } finally {
    barcodeInput.value = "";
    scannerLoading.value = false;
    nextTick(() => {
      scannerInputRef.value?.focus();
    });
  }
};

const loadAutoOrderDetails = async () => {
  if (!invoice.value?.auto_order_id) return;

  const result = await Swal.fire({
    title: "¿Cargar productos de Auto-Orden?",
    text: "Esto reemplazará la lista actual de productos con los sugeridos por la orden original.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, cargar todo",
    cancelButtonText: "Cancelar",
  });

  if (!result.isConfirmed) return;

  loadingDetails.value = true;
  try {
    const response = await axios.get(
      `/invoices/${props.invoiceId}/suggested-details`,
    );

    if (response.data.data) {
      invoiceDetails.value = response.data.data.map((detail, index) => ({
        ...detail,
        display_order: index,
      }));

      isEditMode.value = true;
      toast.fire({
        icon: "success",
        title: "Productos cargados desde Auto-Orden",
      });
    }
  } catch (error) {
    console.error("Error loading suggested details:", error);
    toast.fire({
      icon: "error",
      title: "No se pudieron cargar los datos de la Auto-Orden",
    });
  } finally {
    loadingDetails.value = false;
  }
};

const toggleScannerMode = () => {
  isScannerMode.value = !isScannerMode.value;
  if (isScannerMode.value) {
    isEditMode.value = true;
    nextTick(() => {
      scannerInputRef.value?.focus();
    });
  }
};

const handleFinalizeInvoice = async () => {
  if (isTotalMismatch.value) {
    toast.error(
      `El total de productos (${formatCurrency(
        editableDetailsTotal.value,
        invoice.value.currency,
      )}) debe ser exactamente igual al total de la factura (${formatCurrency(
        invoice.value.total_amount,
        invoice.value.currency,
      )}).`,
    );

    return;
  }

  if (isTaxAmountMismatch.value) {
    toast.error(
      `El monto de IVA de los productos (${formatCurrency(
        editableDetailsTaxAmount.value,
        invoice.value.currency,
      )}) debe ser igual al IVA de la factura (${formatCurrency(
        invoice.value.tax_amount,
        invoice.value.currency,
      )}).`,
    );

    return;
  }
  if (isEditMode.value) {
    console.log("Finalize called in Edit Mode. Saving progress first.");
    const saveSuccessful = await handleSaveProgress();
    if (!saveSuccessful) {
      return;
    }
  } else {
    console.log("Finalize called in Read Mode. Skipping save.");
  }
  loading.value = true;

  try {
    const response = await axios.put(`/invoices/${props.invoiceId}/finalize`);

    toast.success(response.data.message || "Factura finalizada con éxito.");
    emit("back-to-list");
  } catch (error) {
    toast.error(
      error.response?.data?.message || "No se pudo finalizar la factura.",
    );
  } finally {
    loading.value = false;
  }
};

const formattedSupplierDiscounts = computed(() => {
  if (!props.supplierDiscounts || !Array.isArray(props.supplierDiscounts)) {
    return [];
  }

  return props.supplierDiscounts.map((discount) => ({
    ...discount,
    displayText: `${discount.name} - ${discount.discount_percentage}%`,
  }));
});

const detailsHeaders = computed(() => {
  const headers = [
    {
      title: "Descripción",
      key: "product_name_with_tax",
      sortable: false,
      width: isEditMode.value ? "35%" : "25%",
    },
    {
      title: "Lote / Vencimiento",
      key: "lot_and_expiration",
      align: "center",
      sortable: false,
      width: "18%",
    },
  ];

  if (isLocationMode.value) {
    headers.push({
      title: "Localización",
      key: "location",
      align: "center",
      sortable: false,
      width: "12%",
    });
  }

  headers.push(
    {
      title: "Unidades",
      key: "quantity",
      align: "end",
      sortable: false,
      width: "8%",
    },
    {
      title: "Costo Unitario",
      key: "unit_cost",
      align: "end",
      sortable: false,
      width: "10%",
    },
    {
      title: "IVA (16%)",
      key: "tax_amount",
      align: "end",
      sortable: false,
      width: "8%",
    },
    {
      title: "Costo Total",
      key: "total_cost",
      align: "end",
      sortable: false,
      width: "9%",
    },
    {
      title: "Acciones",
      key: "actions",
      sortable: false,
      align: "center",
      width: "5%",
    },
  ];

    // La columna Localización no es necesaria en la vista por defecto de facturas pendientes
    // headers.splice(2, 0, {
    //   title: "Localización",
    //   key: "location",
    //   align: "center",
    //   sortable: false,
    //   width: "10%",
    // });

  if (isLocationMode.value) {
    return headers.filter(
      (h) =>
        !["tax_amount", "total_cost", "actions", "unit_cost"].includes(h.key),
    );
  }

  return headers;
});
</script>

<template>
  <div>
    <div v-if="loading" class="text-center pa-10">
      <VProgressCircular indeterminate color="primary" size="64" />
      <p class="mt-4 text-h6">Cargando datos de la factura...</p>
    </div>

    <div v-else-if="invoice">

      <VCard class="invoice-detail-card mb-6">
        <VForm @submit.prevent>
          <VCardText class="header-section pb-4">
            <VRow align="center" justify="space-between" class="mb-4">
              <VCol cols="auto">
                <VBtn
                  icon="tabler-arrow-left"
                  variant="text"
                  @click="emit('back-to-list')"
                />
              </VCol>
              <VCol cols="auto">
                <VBtn
                  v-if="isEditableMode && !isEditMode"
                  color="primary"
                  variant="tonal"
                  @click="toggleEditMode(true)"
                >
                  <VIcon icon="tabler-edit" class="me-2" />
                  Editar
                </VBtn>
              </VCol>
            </VRow>
            <VRow align="center" justify="space-between" class="mt-0">
              <VCol cols="12" md="6">
                <div>
                  <h1
                    class="font-weight-bold text-primary text-h4 mb-2"
                    style="text-transform: uppercase !important"
                  >
                    {{ invoice.supplier.name }}
                  </h1>
                  <div class="d-flex flex-wrap gap-2 align-center">
                    <VChip size="small" color="error" variant="flat" class="font-weight-bold">
                      Control: {{ invoice.control_number }}
                    </VChip>
                    <VChip size="small" color="error" variant="flat" class="font-weight-bold">
                      Factura: {{ invoice.invoice_number }}
                    </VChip>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="6" class="text-md-end">
                <div class="d-flex flex-wrap justify-md-end gap-2">
                  <VChip size="small" variant="tonal" color="secondary">
                    <VIcon start icon="tabler-calendar" size="14" />
                    Emisión: {{ formatDate(invoice.exp_date) || "N/A" }}
                  </VChip>
                  <VChip size="small" variant="tonal" color="info">
                    <VIcon start icon="tabler-download" size="14" />
                    Recibo: {{ formatDate(invoice.received_date) || "N/A" }}
                  </VChip>
                  <VChip size="small" variant="tonal" color="warning">
                    <VIcon start icon="tabler-calendar-due" size="14" />
                    Vence: {{ formatDate(invoice.payment_date) || "N/A" }}
                  </VChip>
                </div>
              </VCol>
            </VRow>
          </VCardText>
          <VDivider />

          <!-- Alerta de Reglas de Pago Faltantes -->
          <VCardText v-if="isApprovalMode && formattedPaymentRules.length === 0" class="pb-0">
            <VAlert
              type="warning"
              variant="tonal"
              closable
              icon="tabler-alert-triangle"
              class="mb-0"
            >
              <div class="font-weight-bold">Proveedor sin reglas de pago</div>
              <div>Este proveedor no tiene reglas de pago configuradas. Por favor, revise la ficha del proveedor si esto es un error.</div>
            </VAlert>
          </VCardText>

          <VCardText class="products-section pt-6">
            <div class="d-flex align-center mb-4">
              <span class="text-h6 font-weight-medium">Productos</span>
              <VChip color="primary" variant="outlined" class="ms-2">
                {{ invoiceDetails.length }} productos
              </VChip>
              <VSpacer />
              <div class="d-flex align-center ga-4">
                <div class="text-right d-flex align-center">
                  <VTooltip
                    v-if="
                      (isTotalMismatch || isTaxAmountMismatch) && isEditMode
                    "
                    text="Hay discrepancias en los totales que deben corregirse antes de finalizar."
                  >
                    <template #activator="{ props }">
                      <VIcon
                        v-bind="props"
                        icon="tabler-alert-circle"
                        color="warning"
                        class="me-2"
                      />
                    </template>
                  </VTooltip>
                  <span class="text-body-1 me-2 text-error font-weight-medium"
                    >Total Cargado</span
                  >
                  <VChip
                    :color="
                      (isTotalMismatch || isTaxAmountMismatch) && isEditMode
                        ? 'warning'
                        : 'error'
                    "
                    label
                  >
                    {{ formatCurrency(editableDetailsTotal, invoice.currency) }}
                  </VChip>
                </div>
                <!-- Botones de Carga y Escaneo (Visibles en modo editable) -->
                <template v-if="isEditableMode">
                  <VBtn
                    v-if="invoice.auto_order_id && invoiceDetails.length === 0"
                    color="warning"
                    variant="tonal"
                    size="small"
                    class="me-2"
                    :loading="loadingDetails"
                    @click="loadAutoOrderDetails"
                  >
                    <VIcon icon="tabler-refresh" class="me-2" />
                    Cargar Auto-Orden
                  </VBtn>

                  <VBtn
                    :color="isScannerMode ? 'info' : 'secondary'"
                    :variant="isScannerMode ? 'flat' : 'tonal'"
                    size="small"
                    class="me-2"
                    @click="toggleScannerMode"
                  >
                    <VIcon
                      :icon="isScannerMode ? 'tabler-barcode' : 'tabler-barcode-off'"
                      class="me-2"
                    />
                    {{ isScannerMode ? "Modo Escáner Activo" : "Carga por Escáner" }}
                  </VBtn>
                </template>

                <VBtn
                  v-if="isEditableMode && isEditMode"
                  color="primary"
                  variant="flat"
                  size="small"
                  @click="handleAddProduct"
                >
                  <VIcon icon="tabler-plus" class="me-2" />
                  Agregar Producto
                </VBtn>
              </div>
            </div>

            <!-- Área de Escaneo Rápido -->
            <VExpandTransition>
              <div v-if="isScannerMode" class="mb-4 pa-4 bg-primary-lighten-5 rounded border-dashed d-flex align-center">
                <VIcon icon="tabler-scan" color="primary" size="24" class="me-3" />
                <div class="flex-grow-1">
                  <VTextField
                    ref="scannerInputRef"
                    v-model="barcodeInput"
                    placeholder="Escanee el código de barras del producto físico..."
                    prepend-inner-icon="tabler-barcode"
                    variant="solo"
                    density="comfortable"
                    hide-details
                    :loading="scannerLoading"
                    autofocus
                    @keyup.enter="handleBarcodeScan"
                  >
                    <template #append-inner>
                      <VChip v-if="scannerLoading" size="x-small" color="primary">Buscando...</VChip>
                      <kbd v-else class="text-caption px-2 bg-grey-lighten-3 rounded">ENTER</kbd>
                    </template>
                  </VTextField>
                </div>
                <VBtn icon="tabler-x" variant="text" size="small" class="ms-2" @click="isScannerMode = false" />
              </div>
            </VExpandTransition>

            <VDataTable
              :headers="detailsHeaders"
              :items="processedInvoiceDetails"
              :loading="loadingDetails"
              :hide-default-footer="true"
              :items-per-page="-1"
              class="invoice-products-table"
              :row-props="getRowProps"
            >
              <template #item.product_name_with_tax="{ item }">
                <div
                  :class="{
                    'near-expiration-row': isNearExpiration(item),
                    'draggable-row': isEditableMode && isEditMode,
                    'drag-over': draggedOverItem?.id === item.id,
                  }"
                  @dragover="handleDragOver($event, item)"
                  @drop="handleDrop(item)"
                >
                  <span :class="{ 'returned-item': isItemReturned(item) }">
                    {{ item.product_name_with_tax }}
                    <span class="text-sm text-disabled">{{
                      item.product?.laboratory?.name
                    }}</span>
                  </span>
                  <span class="text-sm text-disabled" />
                  <VTooltip v-if="isNearExpiration(item)" location="top">
                    <template #activator="{ props }">
                      <VIcon
                        v-bind="props"
                        icon="tabler-alert-triangle"
                        color="warning"
                        size="16"
                        class="ms-2"
                      />
                    </template>
                    <span
                      >Producto próximo a vencer (menos de 6 meses). Considere
                      marcarlo como devolución.</span
                    >
                  </VTooltip>
                </div>
              </template>

              <template #item.lot_and_expiration="{ item }">
                <div class="d-flex flex-column align-center" :class="{ 'near-expiration-row': isNearExpiration(item) }">
                  <VTextField
                    v-if="isEditableMode && item.id === editingDetailId"
                    v-model="editedDetailData.lot_number"
                    density="compact"
                    hide-details
                    variant="outlined"
                    class="editable-cell mb-1"
                    :placeholder="item.is_return ? 'Lote (Dev)' : 'Ingrese Lote'"
                  />
                  <span v-else :class="{ 'returned-item': isItemReturned(item) }" class="font-weight-medium">
                    {{ item.lot_number || "Sin Lote" }}
                  </span>

                  <VTextField
                    v-if="isEditableMode && item.id === editingDetailId"
                    v-model="editedDetailData.expiration_date"
                    type="date"
                    density="compact"
                    hide-details
                    variant="outlined"
                    class="editable-cell mt-1"
                    :placeholder="item.is_return ? 'Venc. (Dev)' : 'F. Venc'"
                  />
                  <span
                    v-else
                    class="text-caption"
                    :class="{
                      'returned-item': isItemReturned(item),
                      'text-warning font-weight-bold': isNearExpiration(item) && !isItemReturned(item),
                      'text-disabled': !isNearExpiration(item)
                    }"
                  >
                    <VIcon v-if="isNearExpiration(item) && !isItemReturned(item)" icon="tabler-alert-triangle-filled" size="14" class="me-1" />
                    {{ item.expiration_date || "Sin Vencimiento" }}
                  </span>
                </div>
              </template>
              <template #item.location="{ item, index }">
                <VAutocomplete
                  v-if="isLocationMode && !isItemReturned(item)"
                  :model-value="item.location"
                  :items="locations"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  placeholder="Ej: A-01-B"
                  :return-object="false"
                  auto-select-first
                  @update:model-value="updateLocation(item.id, $event)"
                  @focus="updateLocation(item.id, null)"
                />
                <span
                  v-else
                  :class="{ 'returned-item': isItemReturned(item) }"
                  >{{ item.location || "-" }}</span
                >
              </template>
              <template #item.quantity="{ item }">
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model.number="editedDetailData.quantity"
                  type="number"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  min="1"
                /><span
                  v-else
                  :class="{ 'returned-item': isItemReturned(item) }"
                  >{{ item.quantity }}</span
                >
              </template>

              <template #item.unit_cost="{ item }">
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model.number="editedDetailData.unit_cost"
                  type="number"
                  step="0.01"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  min="0"
                  :prefix="getCurrencySymbol()"
                />
                <VTooltip
                  v-else-if="
                    isApprovalMode &&
                    item.product &&
                    typeof item.product.unit_cost !== 'undefined'
                  "
                  location="top"
                >
                  <template #activator="{ props }">
                    <div
                      v-bind="props"
                      class="cost-cell d-flex flex-column align-end"
                      :class="[
                        getCostComparisonClass(item),
                        { 'returned-item': isItemReturned(item) },
                      ]"
                    >
                      <span class="font-weight-medium">{{
                        formatCurrency(item.unit_cost, invoice.currency)
                      }}</span>
                      <span
                        v-if="invoice.currency !== 'USD'"
                        class="text-caption text-medium-emphasis"
                        >{{ formatCurrency(item.unit_cost_usd, "USD") }}</span
                      >
                    </div>
                  </template>
                  <span>{{ getCostTooltipText(item) }}</span>
                </VTooltip>
                <div
                  v-else
                  class="d-flex flex-column align-end"
                  :class="{ 'returned-item': isItemReturned(item) }"
                >
                  <span class="font-weight-medium">{{
                    formatCurrency(item.unit_cost, invoice.currency)
                  }}</span>
                  <span
                    v-if="invoice.currency !== 'USD'"
                    class="text-caption text-medium-emphasis"
                    >{{ formatCurrency(item.unit_cost_usd, "USD") }}</span
                  >
                </div>
              </template>

              <template #item.tax_amount="{ item }">
                <div
                  class="d-flex flex-column align-end"
                  :class="{ 'returned-item': isItemReturned(item) }"
                >
                  <span
                    :class="{ 'font-weight-medium': item.tax_amount > 0 }"
                    >{{
                      formatCurrency(item.tax_amount, invoice.currency)
                    }}</span
                  >
                </div>
              </template>

              <template #item.total_cost="{ item }">
                <div
                  class="d-flex flex-column align-end"
                  :class="{ 'returned-item': isItemReturned(item) }"
                >
                  <span class="font-weight-medium">{{
                    formatCurrency(item.total_cost, invoice.currency)
                  }}</span>
                  <span
                    v-if="invoice.currency !== 'USD'"
                    class="text-caption text-medium-emphasis"
                    >{{ formatCurrency(item.total_cost_usd, "USD") }}</span
                  >
                </div>
              </template>

              <template #item.actions="{ item }">
                <div v-if="isEditableMode && isEditMode">
                  <div v-if="item.id === editingDetailId" class="d-flex">
                    <IconBtn @click="saveEditingDetail">
                      <VIcon icon="tabler-check" color="success" size="22" />
                    </IconBtn>
                    <IconBtn @click="cancelEditingDetail">
                      <VIcon icon="tabler-x" color="error" size="22" />
                    </IconBtn>
                  </div>
                  <div v-else class="d-flex align-center ga-1">
                    <div class="d-flex flex-column ga-0">
                      <VTooltip text="Mover arriba">
                        <template #activator="{ props }">
                          <IconBtn
                            v-bind="props"
                            :disabled="
                              invoiceDetails.findIndex(
                                (d) => d.id === item.id,
                              ) === 0
                            "
                            size="small"
                            @click="moveItemUp(item)"
                          >
                            <VIcon icon="tabler-arrow-up" size="16" />
                          </IconBtn>
                        </template>
                      </VTooltip>
                      <VTooltip text="Mover abajo">
                        <template #activator="{ props }">
                          <IconBtn
                            v-bind="props"
                            :disabled="
                              invoiceDetails.findIndex(
                                (d) => d.id === item.id,
                              ) ===
                              invoiceDetails.length - 1
                            "
                            size="small"
                            @click="moveItemDown(item)"
                          >
                            <VIcon icon="tabler-arrow-down" size="16" />
                          </IconBtn>
                        </template>
                      </VTooltip>
                    </div>
                    <VTooltip text="Arrastrar para reordenar">
                      <template #activator="{ props }">
                        <IconBtn
                          v-bind="props"
                          class="drag-handle"
                          :class="{
                            'drag-over': draggedOverItem?.id === item.id,
                          }"
                          draggable="true"
                          @dragstart="handleDragStart(item)"
                          @dragover.prevent="handleDragOver($event, item)"
                          @drop="handleDrop(item)"
                          @dragend="handleDragEnd"
                        >
                          <VIcon icon="tabler-grip-vertical" size="18" />
                        </IconBtn>
                      </template>
                    </VTooltip>
                    <VTooltip text="Marcar para Devolución">
                      <template #activator="{ props }">
                        <IconBtn v-bind="props" @click="toggleReturnItem(item)">
                          <VIcon
                            :color="
                              isItemReturned(item) ? 'warning' : 'default'
                            "
                            icon="tabler-arrow-back-up"
                            size="20"
                          />
                        </IconBtn>
                      </template>
                    </VTooltip>
                    <VTooltip
                      :text="
                        !invoiceHasIva
                          ? 'Factura sin IVA - No se puede agregar IVA a productos'
                          : item.tax_enabled
                            ? 'Quitar IVA'
                            : 'Agregar IVA'
                      "
                    >
                      <template #activator="{ props }">
                        <IconBtn
                          v-bind="props"
                          :disabled="!invoiceHasIva"
                          :class="{ 'disabled-button': !invoiceHasIva }"
                          @click="toggleTax(item)"
                        >
                          <VIcon
                            :color="
                              !invoiceHasIva
                                ? 'disabled'
                                : item.tax_enabled
                                  ? 'success'
                                  : 'default'
                            "
                            icon="tabler-receipt-tax"
                            size="20"
                          />
                        </IconBtn>
                      </template>
                    </VTooltip>
                    <IconBtn @click="removeProductFromInvoice(item.id)">
                      <VIcon icon="tabler-trash" size="20" />
                    </IconBtn>
                    <IconBtn @click="startEditingDetail(item)">
                      <VIcon icon="tabler-edit" size="20" />
                    </IconBtn>
                  </div>
                </div>
              </template>
              <template #bottom />
            </VDataTable>
          </VCardText>
          <VDivider />

          <VCardText class="totals-section pb-6 pt-4 bg-var-theme-background">
            <h3 class="text-h6 font-weight-medium mb-4">Resumen Financiero</h3>
            <VRow>
              <!-- Tarjeta: Exento y Base Imponible -->
              <VCol cols="12" md="4">
                <VCard variant="outlined" class="h-100 summary-card glassmorphism">
                  <VCardText>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-2 text-medium-emphasis">Total Exento (0%)</span>
                      <span class="text-body-1 font-weight-medium">{{ formatCurrency(invoice.exempt_amount) }}</span>
                    </div>
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-subtitle-2 text-medium-emphasis">Base Imponible (16%)</span>
                      <span class="text-body-1 font-weight-medium">{{ formatCurrency(invoice.taxable_base) }}</span>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Tarjeta: IVA y Descuentos -->
              <VCol cols="12" md="4">
                <VCard variant="outlined" class="h-100 summary-card glassmorphism">
                  <VCardText>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <div class="d-flex align-center">
                        <VTooltip
                          v-if="isTaxAmountMismatch && isEditMode"
                          text="El monto de IVA calculado difiere del original."
                        >
                          <template #activator="{ props }">
                            <VIcon v-bind="props" icon="tabler-alert-circle" color="warning" size="16" class="me-1" />
                          </template>
                        </VTooltip>
                        <span class="text-subtitle-2 text-medium-emphasis">Impuesto IVA (16%)</span>
                      </div>
                      <div class="text-right">
                        <span class="text-body-1 font-weight-medium">{{ formatCurrency(invoice.tax_amount) }}</span>
                        <div v-if="isEditMode" class="text-caption" :class="{ 'text-warning': isTaxAmountMismatch }">
                          Calc: {{ formatCurrency(editableDetailsTaxAmount, invoice.currency) }}
                        </div>
                      </div>
                    </div>
                    
                    <!-- Descuentos Edit Mode -->
                    <div v-if="isEditableMode && isEditMode" class="mt-3">
                      <VSelect
                        v-model="selectedSupplierDiscountId"
                        :items="formattedSupplierDiscounts"
                        item-title="displayText"
                        item-value="id"
                        label="Descuento Proveedor"
                        variant="underlined"
                        density="compact"
                        clearable
                        hide-details
                      />
                    </div>
                    <!-- Descuentos Approval Mode -->
                    <div v-if="isApprovalMode" class="mt-3">
                      <VSelect
                        v-model="selectedPaymentRuleId"
                        :items="formattedPaymentRules"
                        item-title="displayText"
                        item-value="id"
                        label="Pronto Pago"
                        variant="underlined"
                        density="compact"
                        clearable
                        hide-details
                      />
                    </div>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Tarjeta: Totales Principales -->
              <VCol cols="12" md="4">
                <VCard color="primary" variant="tonal" class="h-100 summary-card glassmorphism border-primary-variant">
                  <VCardText>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1 font-weight-bold">Total Factura</span>
                      <span class="text-h5 font-weight-bold text-primary">{{ formatCurrency(invoice.total_amount) }}</span>
                    </div>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-2 opacity-80">Total USD</span>
                      <span class="text-subtitle-1 font-weight-medium text-primary">{{ formatCurrency(invoice.total_usd, "USD") }}</span>
                    </div>
                    <div class="d-flex justify-space-between align-center text-caption opacity-70">
                      <span>Tasa BCV Aplicada</span>
                      <span>{{ formatNumber(invoice.exchange_rate) }}</span>
                    </div>
                    
                    <VDivider v-if="isApprovalMode && selectedPaymentRuleId" class="my-2" />
                    <div v-if="isApprovalMode && selectedPaymentRuleId" class="d-flex justify-space-between align-center text-success mt-2">
                      <span class="text-subtitle-2 font-weight-bold">Con Descuento</span>
                      <span class="text-h6 font-weight-bold">{{ formatCurrency(totalWithDiscount, invoice.currency) }}</span>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>

          <div class="sticky-bottom-actions pa-4 bg-surface elevation-10">
            <VCardActions class="pa-0">
            <div v-if="isLocationMode" class="d-flex w-100">
              <VBtn
                :loading="loading"
                size="large"
                color="primary"
                variant="flat"
                class="w-100"
                @click="handleSaveLocations"
              >
                <VIcon icon="tabler-device-floppy" class="me-2" />Guardar
                Ubicaciones
              </VBtn>
            </div>
            <div v-else-if="isApprovalMode" class="d-flex ga-3 w-100">
              <VBtn
                :loading="props.isSaving"
                size="large"
                color="error"
                variant="outlined"
                class="flex-1-1"
                @click="handleReject"
              >
                Rechazar Factura
              </VBtn>
              <VBtn
                :loading="props.isSaving"
                size="large"
                color="success"
                variant="flat"
                class="flex-1-1"
                @click="handleConfirmApproval"
              >
                <VIcon icon="tabler-check" class="me-2" />
                Confirmar Aprobación
              </VBtn>
            </div>
            <div v-else-if="isEditableMode" class="d-flex ga-3 w-100">
              <VBtn
                v-if="isEditMode"
                color="error"
                variant="outlined"
                size="large"
                class="flex-1-1"
                @click="toggleEditMode(false)"
              >
                Cancelar
              </VBtn>
              <VBtn
                :loading="loading"
                size="large"
                :color="isEditMode ? 'info' : 'primary'"
                variant="flat"
                :class="isEditMode ? 'flex-1-1' : 'w-100'"
                @click="
                  isEditMode ? handleSaveProgress() : handleFinalizeInvoice()
                "
              >
                {{ isEditMode ? "Guardar Progreso" : "Finalizar Factura" }}
              </VBtn>
            </div>
            <div v-else class="d-flex w-100">
              <VBtn
                size="large"
                color="primary"
                variant="tonal"
                class="w-100"
                @click="emit('back-to-list')"
              >
                Volver a la Lista
              </VBtn>
            </div>
            </VCardActions>
          </div>
        </VForm>
      </VCard>

      <template v-if="isEditableMode">
        <div
          v-if="isEditMode && isProductSearchVisible"
          class="product-search-section mt-6"
        >
          <div class="d-flex align-center justify-space-between mb-4">
            <h4 class="text-h4">Buscar Productos en Catálogo</h4>
            <VBtn
              variant="text"
              color="error"
              @click="isProductSearchVisible = false"
            >
              <VIcon icon="tabler-x" class="me-2" />Cerrar Búsqueda
            </VBtn>
          </div>
          <ProductFilters
            v-model:search-query="productSearchQuery"
            :laboratories="laboratories"
            :origins="origins"
            :loading="isLoadingFilters"
            mode="minimal"
            @clear="productSearchQuery = ''"
            @add-product="handleAddNewProduct"
          />
          <ProductTable
            :products="products"
            :loading="loadingProducts"
            :total-product="totalProducts"
            :items-per-page="productItemsPerPage"
            :page="productPage"
            mode="add-to-invoice"
            @update:options="updateProductTableOptions"
            @add-product-to-invoice="addProductToInvoice"
          />
        </div>
        <BarcodeSearchModal
          ref="barcodeModalRef"
          v-model="isBarcodeModalVisible"
          :loading="searchingBarcode"
          @search-barcode="handleSearchBarcode"
          @show-product-search="handleShowProductSearch"
          @add-new-product="handleAddNewProduct"
          @add-product-to-invoice="addProductToInvoice"
        />
        <ProductEditDialog
          v-model="isEditDialogVisible"
          :product="currentProduct"
          :laboratories="laboratories"
          :origins="origins"
          :categories="categories"
          :errors="productFormErrors"
          @save="handleSaveProduct"
          @clear-errors="productFormErrors = {}"
          @laboratory-created="fetchProductSelectOptions"
        />
      </template>
    </div>
  </div>
</template>

<style lang="scss">
.invoice-detail-card {
  overflow: hidden;

  .header-section {
    background-color: rgba(var(--v-theme-on-surface), 0.02);
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  }
  .editable-field {
    max-width: 150px;
  }
  .editable-field-large {
    max-width: 200px;
  }
  .dates-section,
  .totals-section {
    padding-top: 24px;
    padding-bottom: 24px;
  }
  .invoice-products-table {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 6px;
    .v-data-table__tr:not(:last-child) {
      border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    }
  }
  .total-item p {
    white-space: nowrap;
  }
}

.sticky-bottom-actions {
  position: sticky;
  z-index: 10;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  inset-block-end: 0;
}

.summary-card {
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.summary-card:hover {
  box-shadow: 0 4px 15px rgba(0, 0, 0, 8%);
  transform: translateY(-2px);
}

.glassmorphism {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  backdrop-filter: blur(10px);
  background: rgba(var(--v-theme-surface), 0.7) !important;
}

.border-primary-variant {
  border: 1px solid rgba(var(--v-theme-primary), 0.3) !important;
}
.editable-cell {
  min-width: 120px;
}
.flex-1-1 {
  flex: 1 1 50%;
}
.w-100 {
  width: 100%;
}
.totals-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: flex-end;
}
.total-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  max-width: 400px;
}
.returned-item {
  text-decoration: line-through;
  opacity: 0.6;
}

.cost-cell {
  padding: 4px 8px;
  border-radius: 6px;
  transition: background-color 0.3s ease;
  min-width: 120px;
  text-align: right;
}

.cost-higher {
  background-color: rgba(var(--v-theme-error), 0.1);

  .font-weight-medium {
    color: rgb(var(--v-theme-error));
  }
  .text-caption {
    color: rgba(var(--v-theme-error), 0.8) !important;
  }
}

.cost-lower {
  background-color: rgba(var(--v-theme-success), 0.1);

  .font-weight-medium {
    color: rgb(var(--v-theme-success));
  }
  .text-caption {
    color: rgba(var(--v-theme-success), 0.8) !important;
  }
}

.cost-new-product {
  background-color: rgba(var(--v-theme-warning), 0.1);

  .font-weight-medium {
    color: rgb(var(--v-theme-warning));
  }
  .text-caption {
    color: rgba(var(--v-theme-warning), 0.8) !important;
  }
}

.draggable-row {
  cursor: move;
  transition: background-color 0.2s ease;
}

.draggable-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.drag-over {
  background-color: rgba(var(--v-theme-primary), 0.1);
  border-top: 2px solid rgb(var(--v-theme-primary));
}

.drag-handle {
  cursor: grab;
}

.drag-handle:active {
  cursor: grabbing;
}
</style>
