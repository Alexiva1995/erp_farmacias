<script setup>
import InvoicePhotoPreviewDialog from "@/components/InvoicePhotoPreviewDialog.vue";
import InvoiceFinancialSummary from "./components/InvoiceFinancialSummary.vue";
import InvoiceAuditModal from "./components/InvoiceAuditModal.vue";
import InvoicePdfSidePanel from "./components/InvoicePdfSidePanel.vue";
import InvoiceMobileCards from "./components/InvoiceMobileCards.vue";
import InvoiceDesktopTable from "./components/InvoiceDesktopTable.vue";
import InvoiceProductCatalogSearch from "./components/InvoiceProductCatalogSearch.vue";
import InvoiceHeaderBar from "./components/InvoiceHeaderBar.vue";
import InvoiceActionFooter from "./components/InvoiceActionFooter.vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useAuthStore } from "@/stores/auth";

const isPreviewDialogVisible = ref(false);
const isPdfSidePanelOpen = ref(false);
const previewImageUrl = ref("");

const viewInvoicePhoto = (photoPath) => {
  if (!photoPath) return;
  previewImageUrl.value = photoPath.startsWith("http") ? photoPath : `/storage/${photoPath}`;
  if (mobile.value) {
    isPreviewDialogVisible.value = true;
  } else {
    isPdfSidePanelOpen.value = !isPdfSidePanelOpen.value;
  }
};

const openPdfInModal = () => {
  isPreviewDialogVisible.value = true;
};

const brandingStore = useBrandingStore();
const authStore = useAuthStore();
const isRestaurant = computed(() => false);

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

const { mobile } = useDisplay();

const invoice = ref(null);
const showAuditModal = ref(false);
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
const isProductSearchVisible = ref(false);
const catalogSearchRef = ref(null);

const isScannerMode = ref(false);
const barcodeInput = ref("");
const scannerLoading = ref(false);
const scannerInputRef = ref(null);

const locations = ref([]);

const fetchLocations = async () => {
  try {
    const response = await axios.get("/locations");
    locations.value = response.data.data || response.data || [];
  } catch (error) {
    console.error("Error al cargar ubicaciones:", error);
  }
};

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

const isInvoiceDueSoon = computed(() => {
  const targetDate = invoice.value?.payment_date || invoice.value?.exp_date;
  if (!targetDate) return false;
  const due = new Date(targetDate);
  const now = new Date();
  const diffDays = (due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
  return diffDays <= 3;
});

const getRowProps = (data) => {
  const item = data.item;
  const classes = [];

  if (isEditableMode.value && isEditMode.value) {
    classes.push("draggable-row");
  }

  // Fila marcada como devolución
  if (item.is_return) {
    return {
      class: "returned-row",
      style: "background-color: rgba(255, 152, 0, 0.08) !important; border-left: 4px solid #ff9800 !important;",
    };
  }

  // Check if product is "new" (pending approval / is_deleted)
  if (
    item.product &&
    (item.product.is_deleted == 1 || item.product.is_deleted === true)
  ) {
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

  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const isUsd = invoice.value.currency === "USD";
  const hasValidRate = rate && rate > 0;

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

    // Detectar si el costo nominal viene en USD (típico en auto-órdenes y catálogos de droguerías)
    const isCostInUsd = isUsd || (detail.auto_order_unit_cost_usd != null && Math.abs(unitCost - detail.auto_order_unit_cost_usd) < (unitCost * 0.5 + 0.1))
      || (hasValidRate && rate > 10 && unitCost < (rate * 0.2));

    const unitCostUsd = isCostInUsd ? unitCost : (hasValidRate ? unitCost / rate : unitCost);
    const unitCostBs = isCostInUsd ? (unitCost * rate) : unitCost;

    const totalCostUsd = isCostInUsd ? finalTotal : (hasValidRate ? finalTotal / rate : finalTotal);
    const totalCostBs = isCostInUsd ? (finalTotal * rate) : finalTotal;

    return {
      ...detail,
      product_name_with_tax: detail.tax_enabled
        ? `${detail.product?.name || "Sin nombre"} (G)`
        : detail.product?.name || "Sin nombre",
      tax_amount: taxAmount,
      total_cost: finalTotal,
      unit_cost_usd: unitCostUsd,
      unit_cost_bs: unitCostBs,
      total_cost_usd: totalCostUsd,
      total_cost_bs: totalCostBs,
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

  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const hasValidRate = rate && rate > 0;

  const invoiceTotalUsd = parseFloat(invoice.value.total_usd) || (hasValidRate ? invoice.value.total_amount / rate : invoice.value.total_amount);
  const detailsTotalUsd = processedInvoiceDetails.value.reduce((acc, curr) => acc + (Number(curr.total_cost_usd) || 0), 0);

  return Math.abs(detailsTotalUsd - invoiceTotalUsd) > 0.5;
});

const totalWithDiscount = computed(() => {
  if (!invoice.value) return 0;

  if (isApprovalMode.value && selectedPaymentRuleId.value) {
    const rules = Array.isArray(props.paymentRules)
      ? props.paymentRules
      : props.paymentRules?.payment_rules || [];

    const rule = rules.find((r) => r.id === selectedPaymentRuleId.value);
    if (rule) {
      const discountPercentage = Number(rule.discount_percentage) || 0;
      const discountAmount = invoice.value.total_amount * (discountPercentage / 100);
      return invoice.value.total_amount - discountAmount;
    }
  }

  if (isEditableMode.value && selectedSupplierDiscountId.value) {
    const discount = props.supplierDiscounts.find(
      (d) => d.id === selectedSupplierDiscountId.value,
    );
    if (discount) {
      const discountPercentage = Number(discount.discount_percentage) || 0;
      const baseTotal = editableDetailsTotal.value || invoice.value.total_amount || 0;
      const discountAmount = baseTotal * (discountPercentage / 100);
      return baseTotal - discountAmount;
    }
  }

  return invoice.value.total_amount || 0;
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

  const isUsd = invoice.value.currency === "USD";
  const rate = parseFloat(invoice.value.exchange_rate) || 1;
  const hasValidRate = rate && rate > 0;

  const toleranceInCurrency = isUsd ? 0.5 : hasValidRate ? 0.5 * rate : 0.5;
  const difference = Math.abs(editableDetailsTaxAmount.value - invoiceTaxAmount);

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

/**
 * Compara el precio unitario de la factura (en USD) vs el precio de la Auto-Orden.
 * Retorna { icon, color, tooltip, badgeText } o null si no hay referencia de auto-orden.
 */
const getPriceVsAutoOrderIndicator = (item) => {
  if (!isApprovalMode.value && !isEditableMode.value) return null;

  const autoOrderPrice = item.auto_order_unit_cost_usd != null ? Number(item.auto_order_unit_cost_usd) : null;
  if (autoOrderPrice == null || isNaN(autoOrderPrice) || autoOrderPrice <= 0) return null;

  const invoicePrice = item.unit_cost_usd != null ? Number(item.unit_cost_usd) : null;
  if (invoicePrice == null || isNaN(invoicePrice)) return null;

  const tolerance = 0.001;
  const diffPercent = (((invoicePrice - autoOrderPrice) / autoOrderPrice) * 100).toFixed(1);

  if (invoicePrice > autoOrderPrice + tolerance) {
    return {
      icon: 'tabler-trending-up',
      color: 'error',
      badgeText: `+${diffPercent}%`,
      tooltip: `vs Orden de Compra: Más caro (+${diffPercent}%) — Pactado $${autoOrderPrice.toFixed(2)} USD`,
    };
  } else if (invoicePrice < autoOrderPrice - tolerance) {
    return {
      icon: 'tabler-trending-down',
      color: 'success',
      badgeText: `${diffPercent}%`,
      tooltip: `vs Orden de Compra: Más económico (${diffPercent}%) — Pactado $${autoOrderPrice.toFixed(2)} USD`,
    };
  } else {
    return {
      icon: 'tabler-equal',
      color: 'secondary',
      badgeText: '0.0%',
      tooltip: `vs Orden de Compra: Igual al precio pactado ($${autoOrderPrice.toFixed(2)} USD)`,
    };
  }
};

/**
 * Compara el precio unitario de la factura (en USD) vs el costo actual registrado en el sistema.
 * Retorna { icon, color, tooltip, badgeText } o null.
 */
const getPriceVsSystemCostIndicator = (item) => {
  if (!isApprovalMode.value && !isEditableMode.value) return null;

  const systemCost = Number(item.product?.unit_cost);
  if (systemCost == null || isNaN(systemCost) || systemCost <= 0) {
    return {
      icon: 'tabler-sparkles',
      color: 'info',
      badgeText: 'Nuevo',
      tooltip: 'vs Costo Actual ERP: Producto nuevo sin costo previo registrado',
    };
  }

  const invoiceCostUSD = item.unit_cost_usd != null ? Number(item.unit_cost_usd) : null;
  if (invoiceCostUSD == null || isNaN(invoiceCostUSD)) return null;

  const tolerance = 0.001;
  const diffPercent = systemCost > 0
    ? (((invoiceCostUSD - systemCost) / systemCost) * 100).toFixed(1)
    : '0';

  if (invoiceCostUSD > systemCost + tolerance) {
    return {
      icon: 'tabler-trending-up',
      color: 'error',
      badgeText: `+${diffPercent}%`,
      tooltip: `vs Costo Actual ERP: Más caro (+${diffPercent}%) — Costo en sistema $${systemCost.toFixed(2)} USD`,
    };
  } else if (invoiceCostUSD < systemCost - tolerance) {
    return {
      icon: 'tabler-trending-down',
      color: 'success',
      badgeText: `${diffPercent}%`,
      tooltip: `vs Costo Actual ERP: Más económico (${diffPercent}%) — Costo en sistema $${systemCost.toFixed(2)} USD`,
    };
  } else {
    return {
      icon: 'tabler-equal',
      color: 'secondary',
      badgeText: '0.0%',
      tooltip: `vs Costo Actual ERP: Igual al costo actual del sistema ($${systemCost.toFixed(2)} USD)`,
    };
  }
};

/**
 * Copia al portapapeles todos los productos de la factura que tengan un precio
 * facturado superior al costo registrado en el sistema (con tolerancia de 0.01 USD).
 */
const copyMoreExpensiveProducts = () => {
  const expensiveProducts = processedInvoiceDetails.value.filter(item => {
    const systemCost = Number(item.product?.unit_cost);
    if (systemCost == null || isNaN(systemCost) || systemCost <= 0) return false;
    const invoicePrice = Number(item.unit_cost_usd);
    if (invoicePrice == null || isNaN(invoicePrice)) return false;
    
    const tolerance = 0.01;
    return invoicePrice > systemCost + tolerance;
  });

  if (expensiveProducts.length === 0) {
    toast.fire({
      icon: "info",
      title: "No hay productos con precio mayor al costo registrado en el sistema.",
    });
    return;
  }

  let text = `DATOS DE LA FACTURA
Proveedor: ${invoice.value?.supplier?.name || 'N/A'}
Control: ${invoice.value?.control_number || 'N/A'}
Factura: ${invoice.value?.invoice_number || 'N/A'}
Tasa de Cambio: ${invoice.value?.exchange_rate || '1'}
Moneda: ${invoice.value?.currency || 'USD'}
Total Factura: ${formatCurrency(invoice.value?.total_amount, invoice.value?.currency)}

PRODUCTOS CON PRECIO MAYOR AL COSTO EN SISTEMA:
`;

  let totalDiferencia = 0;

  expensiveProducts.forEach((item) => {
    const facturado = Number(item.unit_cost_usd) || 0;
    const costoSistema = Number(item.product?.unit_cost) || 0;
    const qty = Number(item.quantity) || 0;
    const difUnit = facturado - costoSistema;
    const difTotalItem = difUnit * qty;
    totalDiferencia += difTotalItem;

    text += `- ${item.product?.name || 'Producto'}:
  Facturado: $${facturado.toFixed(2)} USD
  Costo Sistema: $${costoSistema.toFixed(2)} USD
  Cantidad: ${qty}
  Diferencia Unitario: $${difUnit.toFixed(2)} USD
  Diferencia Total: $${difTotalItem.toFixed(2)} USD\n`;
  });

  text += `\nDiferencia Total Acumulada (Exceso facturado): $${totalDiferencia.toFixed(2)} USD`;

  navigator.clipboard.writeText(text).then(() => {
    toast.fire({
      icon: "success",
      title: "Productos más caros copiados al portapapeles.",
    });
  }).catch(() => {
    toast.fire({
      icon: "error",
      title: "No se pudo copiar el texto.",
    });
  });
};

onMounted(async () => {
  await fetchInvoiceData(props.invoiceId);
  if (invoice.value) {
    await fetchInvoiceDetails(props.invoiceId);
  }
  await fetchLocations();
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

    const invoiceData = response.data.data ?? response.data;
    invoice.value = invoiceData;
    if (isEditableMode.value) {
      formData.value = JSON.parse(JSON.stringify(invoiceData));
      selectedSupplierDiscountId.value =
        invoiceData.supplier_discount_id || null;
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
    text: `La factura #${invoice.value?.invoice_number || props.invoiceId} junto a todos los productos aceptados serán regresados al estado pendiente.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Devolver a Pendiente",
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
    (detail) => detail.product?.id === product.id,
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
  catalogSearchRef.value?.openBarcodeModal();
};

const handleSearchBarcode = async (barcode) => {
  catalogSearchRef.value?.setSearchingBarcode(true);
  try {
    const response = await axios.get(`/products/search-by-barcode`, {
      params: { barcode },
    });

    if (response.data.data) {
      catalogSearchRef.value?.handleProductFound(response.data.data);
    } else {
      catalogSearchRef.value?.handleProductNotFound();
    }
  } catch (error) {
    console.error("Error al buscar producto por código de barras:", error);
    catalogSearchRef.value?.handleProductNotFound();
  } finally {
    catalogSearchRef.value?.setSearchingBarcode(false);
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

const handleSaveProduct = async (productFormData, { onSuccess, onError }) => {
  const url = "/products";
  try {
    await axios.post(url, productFormData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    toast.success("Producto creado con éxito");
    if (onSuccess) onSuccess();
    if (isProductSearchVisible.value) {
      await fetchProducts();
    }
  } catch (error) {
    if (error.response && error.response.status === 422) {
      if (onError) onError(error.response.data.errors);
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

const startEditingDetail = async (detail) => {
  editedDetailData.value = { ...detail };
  
  if (isRestaurant.value) {
    const qty = Number(editedDetailData.value.quantity) || 0;
    const unitCost = Number(editedDetailData.value.unit_cost) || 0;
    
    if (editedDetailData.value.tax_enabled) {
      editedDetailData.value.total_cost_input = Number((unitCost * 1.16 * qty).toFixed(2));
    } else {
      editedDetailData.value.total_cost_input = Number((unitCost * qty).toFixed(2));
    }
  }

  const isFruit = computed(() => {
    return !!editedDetailData.value.product?.laboratory?.name?.toLowerCase().includes("fruta");
  });

  if (isRestaurant.value && isFruit.value) {
    if (!editedDetailData.value.lot_number?.trim()) {
      try {
        const prodId = editedDetailData.value.product?.id;
        if (prodId) {
          const response = await axios.get(`/products/${prodId}/next-lot-number`);
          if (response.data && response.data.success) {
            editedDetailData.value.lot_number = response.data.next_lot_number;
          }
        }
      } catch (error) {
        console.error("Error al obtener el número de lote automático de fruta:", error);
      }
    }

    if (!editedDetailData.value.expiration_date && invoice.value?.created_invoice_date) {
      try {
        const purchaseDate = new Date(invoice.value.created_invoice_date + 'T00:00:00');
        purchaseDate.setDate(purchaseDate.getDate() + 15);
        const yyyy = purchaseDate.getFullYear();
        const mm = String(purchaseDate.getMonth() + 1).padStart(2, "0");
        const dd = String(purchaseDate.getDate()).padStart(2, "0");
        editedDetailData.value.expiration_date = `${yyyy}-${mm}-${dd}`;
      } catch (error) {
        console.error("Error al calcular vencimiento de fruta:", error);
      }
    }
  }

  if (!editedDetailData.value.expiration_date) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    editedDetailData.value.expiration_date = `${year}-${month}-01`;
  }
  
  editingDetailId.value = detail.id;
};

const recalculateTotalFromUnit = () => {
  const qty = Number(editedDetailData.value.quantity) || 0;
  const unit = Number(editedDetailData.value.unit_cost) || 0;
  if (editedDetailData.value.tax_enabled) {
    editedDetailData.value.total_cost_input = Number((unit * 1.16 * qty).toFixed(2));
  } else {
    editedDetailData.value.total_cost_input = Number((unit * qty).toFixed(2));
  }
};

const recalculateUnitFromTotal = () => {
  const qty = Number(editedDetailData.value.quantity) || 1;
  const total = Number(editedDetailData.value.total_cost_input) || 0;
  if (editedDetailData.value.tax_enabled) {
    const baseTotal = total / 1.16;
    editedDetailData.value.unit_cost = Number((baseTotal / qty).toFixed(6));
  } else {
    editedDetailData.value.unit_cost = Number((total / qty).toFixed(6));
  }
};

watch(() => editedDetailData.value.quantity, () => {
  if (editingDetailId.value) {
    recalculateTotalFromUnit();
  }
});

const saveEditingDetail = () => {
  if (
    !editedDetailData.value.quantity ||
    editedDetailData.value.quantity <= 0
  ) {
    toast.error("La cantidad debe ser mayor a 0");

    return;
  }

  const qty = Number(editedDetailData.value.quantity) || 1;
  if (editedDetailData.value.total_cost_input !== null && editedDetailData.value.total_cost_input !== undefined && editedDetailData.value.total_cost_input !== '') {
    recalculateUnitFromTotal();
  } else if (editedDetailData.value.unit_cost !== null && editedDetailData.value.unit_cost !== undefined) {
    recalculateTotalFromUnit();
  }

  if (
    editedDetailData.value.unit_cost === null ||
    editedDetailData.value.unit_cost < 0
  ) {
    toast.error("El costo por unidad debe ser 0 o mayor");

    return;
  }

  if (!authStore.isAdmin) {
    if (!editedDetailData.value.lot_number?.trim()) {
      const itemType = editedDetailData.value.is_return ? "devolución" : "producto";
      toast.error(`El número de lote es obligatorio para este ${itemType}`);
      return;
    }
    if (!editedDetailData.value.expiration_date) {
      const itemType = editedDetailData.value.is_return ? "devolución" : "producto";
      toast.error(`La fecha de vencimiento es obligatoria para este ${itemType}`);
      return;
    }
  } else {
    if (!editedDetailData.value.lot_number?.trim()) {
      editedDetailData.value.lot_number = null;
    }
    if (!editedDetailData.value.expiration_date) {
      editedDetailData.value.expiration_date = null;
    }
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

    const nextIncompleteDetail = invoiceDetails.value.find(
      (d, index) =>
        index > detailIndex && (!d.lot_number?.trim() || !d.expiration_date),
    );

    if (nextIncompleteDetail) {
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
  const numValue = typeof value === "string" ? parseFloat(value) : value;
  if (typeof numValue !== "number" || isNaN(numValue)) return value;

  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numValue);
};

const formatCurrency = (value, currency = null) => {
  const numValue = typeof value === "string" ? parseFloat(value) : value;
  if (typeof numValue !== "number" || isNaN(numValue)) return value;
  const targetCurrency = (currency || invoice.value?.currency || "BS").toUpperCase();
  const formattedNum = formatNumber(numValue);

  if (targetCurrency === "USD" || targetCurrency === "$") {
    return `$ ${formattedNum}`;
  }
  if (targetCurrency === "COP") {
    return `COP ${formattedNum}`;
  }
  return `Bs ${formattedNum}`;
};

const getCurrencySymbol = () => {
  if (!invoice.value?.currency) return "Bs";
  const symbolMap = { BS: "Bs", Bs: "Bs", VES: "Bs", USD: "$", COP: "COP$" };

  return symbolMap[invoice.value.currency] || "Bs";
};

const isNearExpiration = (item) => {
  if (!item.expiration_date) return false;
  
  const isFruit = !!item.product?.laboratory?.name?.toLowerCase().includes("fruta");
  if (isRestaurant.value && isFruit) {
    return false;
  }

  const expirationDate = new Date(item.expiration_date);
  const today = new Date();
  const sixMonthsFromNow = new Date();

  sixMonthsFromNow.setMonth(today.getMonth() + 6);

  return expirationDate <= sixMonthsFromNow;
};

const checkAndMarkAsReturn = (item, forceCheck = false) => {
  if (!item.expiration_date) return false;

  const isFruit = !!item.product?.laboratory?.name?.toLowerCase().includes("fruta");
  if (isRestaurant.value && isFruit) {
    return false;
  }

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
};

const moveItemUp = (item) => {
  if (!isEditableMode.value || !isEditMode.value) return;

  const index = invoiceDetails.value.findIndex((d) => d.id === item.id);
  if (index > 0) {
    const temp = invoiceDetails.value[index];

    invoiceDetails.value[index] = invoiceDetails.value[index - 1];
    invoiceDetails.value[index - 1] = temp;

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

      if (!isEditMode.value) {
        toggleEditMode(true);
      }

      const existingIndex = invoiceDetails.value.findIndex(
        (d) => d.product_id === newDetail.product_id,
      );

      if (existingIndex !== -1) {
        toast.fire({
          icon: "info",
          title: "El producto ya está en la lista.",
        });
      } else {
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
    const saveSuccessful = await handleSaveProgress();
    if (!saveSuccessful) {
      return;
    }
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
      width: isEditMode.value ? "30%" : "35%",
    },
    {
      title: "Lote",
      key: "lot_and_expiration",
      align: "center",
      sortable: false,
      width: "16%",
    },
  ];

  if (isLocationMode.value) {
    headers.push({
      title: "Localización",
      key: "location",
      align: "center",
      sortable: false,
      width: "14%",
    });
  }

  headers.push(
    {
      title: "Unid.",
      key: "quantity",
      align: "end",
      sortable: false,
      width: "7%",
    },
    {
      title: "Costo",
      key: "unit_cost",
      align: "end",
      sortable: false,
      width: "15%",
    },
    {
      title: "IVA",
      key: "tax_amount",
      align: "end",
      sortable: false,
      width: "7%",
    },
    {
      title: "Total",
      key: "total_cost",
      align: "end",
      sortable: false,
      width: "12%",
    },
  );

  if (isEditableMode.value && isEditMode.value) {
    headers.push({
      title: "Acciones",
      key: "actions",
      sortable: false,
      align: "center",
      width: "8%",
    });
  }

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
      <VRow class="invoice-workspace-row ma-0">
        <VCol
          cols="12"
          :lg="isPdfSidePanelOpen && invoice.invoice_photo ? 8 : 12"
          :xl="isPdfSidePanelOpen && invoice.invoice_photo ? 9 : 12"
          class="invoice-main-col pa-0 pe-lg-2"
        >
          <VCard class="invoice-detail-card mb-6">
            <VForm @submit.prevent>
              <InvoiceHeaderBar
                :invoice="invoice"
                :is-pdf-side-panel-open="isPdfSidePanelOpen"
                :is-invoice-due-soon="isInvoiceDueSoon"
                :is-edit-mode="isEditMode"
                :is-approval-mode="isApprovalMode"
                :is-location-mode="isLocationMode"
                :is-editable-mode="isEditableMode"
                :format-date="formatDate"
                @back-to-list="emit('back-to-list')"
                @show-audit="showAuditModal = true"
                @view-pdf="viewInvoicePhoto"
                @toggle-edit="toggleEditMode"
              />
              <VDivider />

              <VCardText class="products-section pt-6">
                <div class="d-flex flex-column flex-sm-row align-sm-center ga-2 mb-4">
                  <div class="d-flex align-center">
                    <span class="text-h6 font-weight-bold">Productos</span>
                    <VChip color="primary" variant="tonal" size="small" class="ms-2 rounded-lg">
                      {{ invoiceDetails.length }}
                    </VChip>
                  </div>
                  <VSpacer v-if="!mobile" />
                  <div class="d-flex align-center ga-1 ga-sm-4 justify-space-between w-100 w-sm-auto mt-2 mt-sm-0">
                    <div class="text-right d-flex align-center">
                      <VTooltip
                        v-if="
                          (isTotalMismatch || isTaxAmountMismatch) && isEditMode
                        "
                        text="Hay discrepancias en los totales que deben corregirse antes de finalizar."
                      >
                        <template #activator="{ props: tipProps }">
                          <VIcon
                            v-bind="tipProps"
                            icon="tabler-alert-circle"
                            color="warning"
                            class="me-2"
                          />
                        </template>
                      </VTooltip>
                      <span class="text-xs text-medium-emphasis me-1 uppercase font-weight-black"
                        >Total</span
                      >
                      <VChip
                        :color="
                          (isTotalMismatch || isTaxAmountMismatch) && isEditMode
                            ? 'warning'
                            : 'error'
                        "
                        label
                        size="small"
                        class="rounded-lg px-2"
                        variant="flat"
                      >
                        <span class="text-xs font-weight-black">{{ formatCurrency(editableDetailsTotal, invoice.currency) }}</span>
                      </VChip>
                    </div>
                    <!-- Botones de Carga y Escaneo (Visibles en modo editable) -->
                    <template v-if="isEditableMode">
                      <VBtn
                        v-if="invoice.auto_order_id && invoiceDetails.length === 0"
                        color="warning"
                        variant="tonal"
                        size="small"
                        class="rounded-lg px-3 font-weight-bold"
                        :loading="loadingDetails"
                        @click="loadAutoOrderDetails"
                      >
                        <VIcon icon="tabler-refresh" class="me-1" size="16" />
                        <span>Cargar Auto-Orden</span>
                      </VBtn>

                      <VBtn
                        :color="isScannerMode ? 'info' : 'secondary'"
                        :variant="isScannerMode ? 'flat' : 'tonal'"
                        size="small"
                        class="rounded-lg px-3 font-weight-bold"
                        @click="toggleScannerMode"
                      >
                        <VIcon :icon="isScannerMode ? 'tabler-barcode' : 'tabler-barcode-off'" class="me-1" size="16" />
                        <span>{{ isScannerMode ? "Modo Escáner" : "Escanear" }}</span>
                      </VBtn>
                    </template>

                    <template v-if="isApprovalMode">
                      <VBtn
                        color="warning"
                        variant="tonal"
                        size="small"
                        class="rounded-lg px-3 font-weight-bold"
                        @click="copyMoreExpensiveProducts"
                      >
                        <VIcon icon="tabler-copy" class="me-1" size="16" />
                        <span>Copiar más caros</span>
                      </VBtn>
                    </template>

                    <VBtn
                      v-if="isEditableMode && isEditMode && isRestaurant"
                      color="info"
                      variant="tonal"
                      size="small"
                      class="rounded-lg px-3 font-weight-bold"
                      @click="handleShowProductSearch"
                    >
                      <VIcon icon="tabler-search" class="me-1" size="16" />
                      <span>Catálogo</span>
                    </VBtn>

                    <VBtn
                      v-if="isEditableMode && isEditMode"
                      color="primary"
                      variant="flat"
                      size="small"
                      class="rounded-lg px-3 font-weight-bold"
                      @click="handleAddProduct"
                    >
                      <VIcon icon="tabler-plus" class="me-1" size="16" />
                      <span>Agregar Producto</span>
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

                <!-- Vista de Tabla Desktop Desacoplada -->
                <InvoiceDesktopTable
                  v-if="!mobile"
                  :processed-invoice-details="processedInvoiceDetails"
                  :loading-details="loadingDetails"
                  :details-headers="detailsHeaders"
                  :is-editable-mode="isEditableMode"
                  :is-edit-mode="isEditMode"
                  :is-location-mode="isLocationMode"
                  :editing-detail-id="editingDetailId"
                  :edited-detail-data="editedDetailData"
                  :invoice="invoice"
                  :locations="locations"
                  :invoice-has-iva="invoiceHasIva"
                  :dragged-over-item-id="draggedOverItem?.id"
                  :get-row-props="getRowProps"
                  :is-near-expiration="isNearExpiration"
                  :is-item-returned="isItemReturned"
                  :get-cost-comparison-class="getCostComparisonClass"
                  :get-price-vs-auto-order-indicator="getPriceVsAutoOrderIndicator"
                  :get-price-vs-system-cost-indicator="getPriceVsSystemCostIndicator"
                  :format-currency="formatCurrency"
                  :get-currency-symbol="getCurrencySymbol"
                  @drag-start="handleDragStart"
                  @drag-over="handleDragOver"
                  @drop="handleDrop"
                  @drag-end="handleDragEnd"
                  @update-location="updateLocation"
                  @recalculate-total-from-unit="recalculateTotalFromUnit"
                  @recalculate-unit-from-total="recalculateUnitFromTotal"
                  @save-editing-detail="saveEditingDetail"
                  @cancel-editing-detail="cancelEditingDetail"
                  @toggle-return-item="toggleReturnItem"
                  @toggle-tax="toggleTax"
                  @start-editing-detail="startEditingDetail"
                  @remove-product-from-invoice="removeProductFromInvoice"
                />

                <!-- Vista Móvil: Tarjetas Desacopladas -->
                <InvoiceMobileCards
                  v-else
                  :processed-invoice-details="processedInvoiceDetails"
                  :loading-details="loadingDetails"
                  :is-editable-mode="isEditableMode"
                  :is-edit-mode="isEditMode"
                  :editing-detail-id="editingDetailId"
                  :edited-detail-data="editedDetailData"
                  :invoice="invoice"
                  :locations="locations"
                  :is-location-mode="isLocationMode"
                  :invoice-has-iva="invoiceHasIva"
                  :is-near-expiration="isNearExpiration"
                  :is-item-returned="isItemReturned"
                  :format-currency="formatCurrency"
                  :get-price-vs-auto-order-indicator="getPriceVsAutoOrderIndicator"
                  :get-price-vs-system-cost-indicator="getPriceVsSystemCostIndicator"
                  @recalculate-total-from-unit="recalculateTotalFromUnit"
                  @recalculate-unit-from-total="recalculateUnitFromTotal"
                  @update-location="updateLocation"
                  @save-editing-detail="saveEditingDetail"
                  @cancel-editing-detail="cancelEditingDetail"
                  @move-item-up="moveItemUp"
                  @move-item-down="moveItemDown"
                  @toggle-return-item="toggleReturnItem"
                  @toggle-tax="toggleTax"
                  @start-editing-detail="startEditingDetail"
                  @remove-product-from-invoice="removeProductFromInvoice"
                />
              </VCardText>
              <VDivider />

              <!-- Resumen Financiero Desacoplado -->
              <InvoiceFinancialSummary
                :invoice="invoice"
                :is-approval-mode="isApprovalMode"
                :is-editable-mode="isEditableMode"
                :is-edit-mode="isEditMode"
                :selected-supplier-discount-id="selectedSupplierDiscountId"
                :selected-payment-rule-id="selectedPaymentRuleId"
                :formatted-supplier-discounts="formattedSupplierDiscounts"
                :formatted-payment-rules="formattedPaymentRules"
                :total-with-discount="totalWithDiscount"
                :editable-details-tax-amount="editableDetailsTaxAmount"
                :is-tax-amount-mismatch="isTaxAmountMismatch"
                :format-currency="formatCurrency"
                :format-number="formatNumber"
                @update:selected-supplier-discount-id="selectedSupplierDiscountId = $event"
                @update:selected-payment-rule-id="selectedPaymentRuleId = $event"
              />

              <InvoiceActionFooter
                :is-location-mode="isLocationMode"
                :is-approval-mode="isApprovalMode"
                :is-editable-mode="isEditableMode"
                :is-edit-mode="isEditMode"
                :is-saving="props.isSaving"
                :loading="loading"
                @save-locations="handleSaveLocations"
                @reject="handleReject"
                @confirm-approval="handleConfirmApproval"
                @cancel-edit="toggleEditMode(false)"
                @save-progress="handleSaveProgress"
                @finalize="handleFinalizeInvoice"
                @back-to-list="emit('back-to-list')"
              />
            </VForm>
          </VCard>
        </VCol>

        <!-- Panel Lateral de Documento PDF Desacoplado -->
        <VCol
          v-if="isPdfSidePanelOpen && invoice.invoice_photo"
          cols="12"
          lg="4"
          xl="3"
          class="pdf-side-panel-col pa-0 ps-lg-2"
        >
          <InvoicePdfSidePanel
            :preview-image-url="previewImageUrl"
            @open-modal="openPdfInModal"
            @close="isPdfSidePanelOpen = false"
          />
        </VCol>
      </VRow>

      <!-- Modal y Búsqueda de Catálogo Desacoplado -->
      <template v-if="isEditableMode">
        <InvoiceProductCatalogSearch
          ref="catalogSearchRef"
          v-model:is-product-search-visible="isProductSearchVisible"
          v-model:search-query="productSearchQuery"
          :products="products"
          :total-products="totalProducts"
          :loading-products="loadingProducts"
          :laboratories="laboratories"
          :origins="origins"
          :categories="categories"
          :is-loading-filters="isLoadingFilters"
          :product-page="productPage"
          :product-items-per-page="productItemsPerPage"
          @update-table-options="updateProductTableOptions"
          @add-product-to-invoice="addProductToInvoice"
          @save-product="handleSaveProduct"
          @laboratory-created="fetchProductSelectOptions"
          @search-barcode="handleSearchBarcode"
        />
      </template>

      <!-- Modal de Historial de Auditoría Desacoplado -->
      <InvoiceAuditModal
        v-model="showAuditModal"
        :invoice="invoice"
      />

      <!-- Modal de Visualización de PDF / Foto -->
      <InvoicePhotoPreviewDialog
        v-model="isPreviewDialogVisible"
        :preview-image-url="previewImageUrl"
      />
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

.returned-row {
  background-color: rgba(var(--v-theme-warning), 0.08) !important;
  border-left: 4px solid rgb(var(--v-theme-warning)) !important;
}

.near-expiration-row {
  background-color: rgba(var(--v-theme-warning), 0.04);
}

.sticky-pdf-panel {
  position: sticky;
  top: 75px;
  z-index: 5;
  height: calc(100vh - 120px);
}

.pdf-side-card {
  background-color: rgb(var(--v-theme-surface)) !important;
}

.transition-all {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Estilos Móvil de Factura Premium */
.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.mobile-detail-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  background-color: white !important;
  border-radius: 8px !important;
}

.rounded-lg, .v-chip, .v-btn {
  border-radius: 8px !important;
}

.near-expiration-border {
  border-left: 4px solid rgb(var(--v-theme-warning)) !important;
}

.returned-border {
  border-left: 4px solid #94a3b8 !important;
  opacity: 0.8;
}

.grid-financial-info {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.grid-logistics-info {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.detail-item {
  display: flex;
  flex-direction: column;
}

.detail-item .label {
  font-size: 0.65rem;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.detail-item .value {
  font-size: 0.85rem;
  font-weight: 500;
  color: #1e293b;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.x-super-small {
  font-size: 0.6rem !important;
  height: 16px !important;
  padding: 0 4px !important;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.border-t {
  border-top: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

kbd.text-caption {
  font-size: 0.7rem !important;
  padding: 2px 6px !important;
}
</style>
