<script setup>
import BarcodeSearchModal from "@/components/dialogs/BarcodeSearchModal.vue";
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  invoiceId: { type: [Number, String], required: true },
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
    const rule = props.paymentRules.find(
      (r) => r.id === selectedPaymentRuleId.value,
    );

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

  const rule = props.paymentRules.find(
    (r) => r.id === selectedPaymentRuleId.value,
  );

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
      startEditingDetail(item);
    } else {
      item.location = "Por Asignar";
      startEditingDetail(item);
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

const handleAddNewProduct = () => {
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
  }
  cancelEditingDetail();
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
      width: "25%",
    },
    {
      title: "N° Lote",
      key: "lot_number",
      align: "center",
      sortable: false,
      width: "10%",
    },
    {
      title: "F. Vencimiento",
      key: "expiration_date",
      align: "center",
      sortable: false,
      width: "10%",
    },
    {
      title: "Localización",
      key: "location",
      align: "center",
      sortable: false,
      width: "10%",
    },
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
      width: "10%",
    },
    {
      title: "Costo Total",
      key: "total_cost",
      align: "end",
      sortable: false,
      width: "12%",
    },
    {
      title: "Acciones",
      key: "actions",
      sortable: false,
      align: "center",
      width: "5%",
    },
  ];

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
      <VAlert
        v-if="invoice.currency !== 'USD'"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <template #prepend>
          <VIcon icon="tabler-info-circle" />
        </template>
        <div>
          <strong>Factura en {{ invoice.currency }}</strong>
          <div class="text-caption mt-1">
            Se muestra el equivalente en USD calculado con la tasa de la factura
            ({{ formatNumber(invoice.exchange_rate) }})
          </div>
        </div>
      </VAlert>
      <VAlert
        v-if="!invoiceHasIva"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <template #prepend>
          <VIcon icon="tabler-info-circle" />
        </template>
        <div>
          <strong>Factura sin IVA</strong>
          <div class="text-caption mt-1">
            Esta factura no incluye IVA según su configuración fiscal. Los
            productos agregados no podrán tener IVA aplicado.
          </div>
        </div>
      </VAlert>
      <VCard class="invoice-detail-card mb-6">
        <VForm @submit.prevent>
          <VCardText class="header-section">
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
            <VRow align="start" justify="space-between">
              <VCol cols="12" md="auto">
                <div>
                  <h1
                    class="font-weight-bold text-primary text-h4"
                    style="text-transform: uppercase !important"
                  >
                    {{ invoice.supplier.name }}
                  </h1>
                  <div class="d-flex align-center mt-2">
                    <span class="text-subtitle-1 font-weight-medium me-2"
                      >N° DE CONTROL</span
                    >
                    <span class="text-h4 font-weight-bold text-error">{{
                      invoice.control_number
                    }}</span>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="auto" class="text-md-end">
                <div class="d-flex align-center justify-md-end">
                  <span class="text-subtitle-1 font-weight-medium me-2"
                    >FACTURA N°</span
                  >
                  <span class="text-h4 font-weight-bold text-error">{{
                    invoice.invoice_number
                  }}</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
          <VDivider />

          <VCardText class="dates-section">
            <VRow>
              <VCol
                v-for="(dateField, index) in [
                  'exp_date',
                  'received_date',
                  'payment_date',
                ]"
                :key="dateField"
                cols="12"
                md="4"
                :class="{
                  'text-start': index === 0,
                  'text-center': index === 1,
                  'text-end': index === 2,
                }"
              >
                <p class="text-subtitle-2 text-disabled">
                  {{
                    {
                      exp_date: "Fecha de Emisión",
                      received_date: "Fecha de Recibo",
                      payment_date: "Fecha de Vencimiento",
                    }[dateField]
                  }}
                </p>
                <p class="text-body-1 font-weight-medium mt-1">
                  {{ formatDate(invoice[dateField]) || "N/A" }}
                </p>
              </VCol>
            </VRow>
          </VCardText>
          <VDivider />

          <VCardText class="products-section">
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

            <VDataTable
              :headers="detailsHeaders"
              :items="processedInvoiceDetails"
              :loading="loadingDetails"
              :hide-default-footer="true"
              :items-per-page="-1"
              class="invoice-products-table"
              :item-class="
                (item) => (isEditableMode && isEditMode ? 'draggable-row' : '')
              "
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

              <template #item.lot_number="{ item }">
                <div :class="{ 'near-expiration-row': isNearExpiration(item) }">
                  <VTextField
                    v-if="isEditableMode && item.id === editingDetailId"
                    v-model="editedDetailData.lot_number"
                    density="compact"
                    hide-details
                    variant="outlined"
                    class="editable-cell"
                    :placeholder="
                      item.is_return ? 'Lote (Devolución)' : 'Ingrese lote'
                    "
                  />
                  <span
                    v-else
                    :class="{ 'returned-item': isItemReturned(item) }"
                  >
                    {{ item.lot_number || "-" }}
                  </span>
                </div>
              </template>

              <template #item.expiration_date="{ item }">
                <div :class="{ 'near-expiration-row': isNearExpiration(item) }">
                  <AppDateTimePicker
                    v-if="isEditableMode && item.id === editingDetailId"
                    v-model="editedDetailData.expiration_date"
                    density="compact"
                    class="editable-cell"
                    :placeholder="
                      item.is_return
                        ? 'F. Venc. (Devolución)'
                        : 'F. Vencimiento'
                    "
                  />
                  <span
                    v-else
                    :class="{
                      'returned-item': isItemReturned(item),
                      'text-warning':
                        isNearExpiration(item) && !isItemReturned(item),
                    }"
                  >
                    {{ item.expiration_date || "-" }}
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
                          draggable="true"
                          @dragstart="handleDragStart(item)"
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

          <VCardText class="totals-section">
            <div class="totals-list">
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Monto Total Excento de IVA:</span
                >
                <span class="text-h6 ms-2">{{
                  formatCurrency(invoice.exempt_amount)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Base Imponible segun Alicuota 16 %:</span
                >
                <span class="text-h6 ms-2">{{
                  formatCurrency(invoice.taxable_base)
                }}</span>
              </div>

              <div class="total-item-row">
                <div class="d-flex align-center">
                  <VTooltip
                    v-if="isTaxAmountMismatch && isEditMode"
                    text="El monto de IVA calculado debe coincidir con el configurado en la factura."
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
                  <span class="text-subtitle-2 text-disabled"
                    >Impuesto segun Alicuota 16 %:</span
                  >
                </div>
                <div class="d-flex flex-column align-end">
                  <span class="text-h6">{{
                    formatCurrency(invoice.tax_amount)
                  }}</span>
                  <span
                    v-if="isEditMode"
                    class="text-caption"
                    :class="{ 'text-warning': isTaxAmountMismatch }"
                  >
                    Calculado:
                    {{
                      formatCurrency(editableDetailsTaxAmount, invoice.currency)
                    }}
                  </span>
                </div>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Base Imponible segun Alicuota 16 %:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.taxable_base)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Impuesto segun Alicuota 16 %:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.tax_amount)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled">Total Factura:</span
                ><span class="text-h6 ms-2 font-weight-bold">{{
                  formatCurrency(invoice.total_amount)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled">Tasa BCV:</span
                ><span class="text-h6 ms-2">{{
                  formatNumber(invoice.exchange_rate)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled">Total USD:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.total_usd, "USD")
                }}</span>
              </div>
              <div
                v-if="isEditableMode && isEditMode"
                class="total-item-row mt-4"
                style="max-width: 400px; width: 100%"
              >
                <VSelect
                  v-model="selectedSupplierDiscountId"
                  :items="formattedSupplierDiscounts"
                  item-title="displayText"
                  item-value="id"
                  label="Descuento por Proveedor"
                  variant="outlined"
                  density="compact"
                  clearable
                  hide-details
                />
              </div>
              <div v-if="isApprovalMode" class="total-item-row mt-4">
                <VSelect
                  v-model="selectedPaymentRuleId"
                  :items="formattedPaymentRules"
                  item-title="displayText"
                  item-value="id"
                  label="Descuento Pronto Pago (Opcional)"
                  variant="outlined"
                  density="compact"
                  clearable
                  hide-details
                />
              </div>
              <div
                v-if="isApprovalMode && selectedPaymentRuleId"
                class="total-item-row mt-3 text-success"
              >
                <span class="text-subtitle-1 font-weight-medium"
                  >Total con Descuento:</span
                >
                <span class="text-h5 ms-2 font-weight-bold">{{
                  formatCurrency(totalWithDiscount, invoice.currency)
                }}</span>
              </div>
            </div>
          </VCardText>
          <VDivider />

          <VCardActions class="pa-6">
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
        />
      </template>
    </div>
  </div>
</template>

<style lang="scss">
.invoice-detail-card {
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

.returned-item {
  text-decoration: line-through;
  opacity: 0.6;
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
