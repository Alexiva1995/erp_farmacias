import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import Swal from "sweetalert2";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { THERMAL_54MM_CSS } from "@/constants/thermalTicket54.js";

/**
 * Composable unificado para la gestión del historial general de órdenes del TPV.
 * Soporta perfiles de Farmacia y Restaurante sin duplicar estado ni peticiones.
 */
export function useOrderGeneral({ mode = "pharmacy", defaultTab = "completed" } = {}) {
  const { isVendedor } = useAuthStore();
  const brandingStore = useBrandingStore();
  const route = useRoute();

  // Estado principal de tabla activa
  const activeTab = ref(defaultTab);
  const orders = ref([]);
  const totalOrders = ref(0);
  const loadingOrders = ref(false);
  const page = ref(1);
  const itemsPerPage = ref(25);
  const sortBy = ref(null);
  const orderBy = ref(null);

  // Estado de cotizaciones (pestaña exclusiva de Farmacia)
  const quotations = ref([]);
  const totalQuotations = ref(0);
  const loadingQuotations = ref(false);
  const pageQuotations = ref(1);
  const itemsPerPageQuotations = ref(25);
  const sortByQuotations = ref(null);
  const orderByQuotations = ref(null);

  // Contadores y KPIs agregados desde /tpv/orders/summary
  const summaryLoading = ref(false);
  const tabCounts = ref({
    completed: 0,
    all: 0,
    cancelled: 0,
    abandoned: 0,
    open: 0,
    quotations: 0,
  });
  const kpis = ref({
    completed_count: 0,
    fiscalized_count: 0,
    pending_fiscal_count: 0,
    sales_by_currency: { BS: 0, USD: 0, COP: 0 },
    total_usd: 0,
    avg_ticket_usd: 0,
    total_service_usd: 0,
  });

  // Catálogos para filtros
  const sellers = ref([]);
  const diningTables = ref([]);

  // Filtros unificados
  const filterSearchQueryId = ref("");
  const filterSearchQuery = ref("");
  const currencyFilter = ref(null);
  const sellerFilter = ref(null);
  const stateFilterAll = ref(null);
  const hasFiscalFilter = ref(null);
  const paymentMethodFilter = ref(null);
  const onlyPsychotropicFilter = ref(false);
  const discountTypeFilter = ref(null);
  const diningTableFilter = ref(null);
  const isAdvancedFiltersVisible = ref(false);

  // Estado de modal de detalle e impresión
  const viewModal = ref(false);
  const isPrinting = ref(false);
  const processingFiscalIds = ref([]);
  const isFiscalPreviewVisible = ref(false);
  const fiscalPreviewData = ref(null);
  const loadingFiscalPreview = ref(false);
  const isConfirmingFiscal = ref(false);

  const paymentsForPrint = ref([]);
  const changeAmountForPrint = ref(0);
  const creditAmountForPrint = ref(0);
  const amountForPrint = ref(0);
  const creditForPrint = ref(false);
  const currency = ref("COP");
  const orderData = ref(null);
  const orderItems = ref([]);

  // Manejo de fechas (por defecto: hoy)
  const toDateString = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

  const getToday = () => toDateString(new Date());
  const getFirstDayOfMonth = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
  };

  const globalStartDate = ref(getToday());
  const globalEndDate = ref(getToday());

  const setDateRange = (start, end) => {
    globalStartDate.value = start;
    globalEndDate.value = end;
  };
  const setDateHoy = () => {
    const t = getToday();
    setDateRange(t, t);
  };
  const setDateAyer = () => {
    const a = new Date();
    a.setDate(a.getDate() - 1);
    const s = toDateString(a);
    setDateRange(s, s);
  };
  const setDateSemana = () => {
    const h = new Date();
    const inicio = new Date(h);
    const dia = inicio.getDay();
    const diff = inicio.getDate() - dia + (dia === 0 ? -6 : 1);
    inicio.setDate(diff);
    setDateRange(toDateString(inicio), toDateString(h));
  };
  const setDateMes = () => {
    setDateRange(getFirstDayOfMonth(), getToday());
  };
  const setDateAno = () => {
    const h = new Date();
    setDateRange(`${h.getFullYear()}-01-01`, getToday());
  };

  const tabStatusMap = {
    completed: "Completed",
    all: "all",
    cancelled: "Cancelled",
    abandoned: "Abandoned",
    open: "open",
  };

  // Construye parámetros limpios para consultas al backend
  const buildFilterParams = (extra = {}) => {
    const params = {
      id: filterSearchQueryId.value,
      q: filterSearchQuery.value,
      currency: currencyFilter.value,
      seller_id: sellerFilter.value,
      state: activeTab.value === "all" ? stateFilterAll.value : null,
      has_fiscal: hasFiscalFilter.value,
      payment_method: paymentMethodFilter.value,
      only_psychotropic: mode === "pharmacy" && onlyPsychotropicFilter.value ? 1 : null,
      discount_type: mode === "pharmacy" ? discountTypeFilter.value : null,
      dining_table_id: mode === "restaurant" ? diningTableFilter.value : null,
      start_date: globalStartDate.value,
      end_date: globalEndDate.value,
      ...extra,
    };

    Object.keys(params).forEach((key) => {
      if (params[key] === null || params[key] === undefined || params[key] === "") {
        delete params[key];
      }
    });

    return params;
  };

  const fetchSummary = async () => {
    summaryLoading.value = true;
    try {
      const params = buildFilterParams();
      const response = await axios.get("/tpv/orders/summary", { params });
      const data = response.data?.data;
      if (data?.counts) {
        tabCounts.value = { ...tabCounts.value, ...data.counts };
      }
      if (data?.kpis) {
        kpis.value = { ...kpis.value, ...data.kpis };
      }
    } catch (error) {
      console.error("Error al obtener resumen de órdenes:", error);
    } finally {
      summaryLoading.value = false;
    }
  };

  const fetchOrders = async () => {
    if (activeTab.value === "quotations") {
      await fetchQuotations();
      return;
    }

    loadingOrders.value = true;
    const status = tabStatusMap[activeTab.value] || "Completed";
    const params = buildFilterParams({
      status,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    });

    try {
      const response = await axios.get("/tpv/orders", { params });
      orders.value = Array.isArray(response.data?.data) ? response.data.data : [];
      totalOrders.value = response.data?.total ?? 0;
    } catch (error) {
      console.error("Error al obtener las órdenes:", error);
      toast.error("Error al obtener las órdenes.");
      orders.value = [];
      totalOrders.value = 0;
    } finally {
      loadingOrders.value = false;
    }
  };

  const fetchQuotations = async () => {
    loadingQuotations.value = true;
    const params = {
      q: filterSearchQuery.value || undefined,
      start_date: globalStartDate.value || undefined,
      end_date: globalEndDate.value || undefined,
      page: pageQuotations.value,
      itemsPerPage: itemsPerPageQuotations.value,
      sortBy: sortByQuotations.value || undefined,
      orderBy: orderByQuotations.value || undefined,
    };
    Object.keys(params).forEach((k) => (params[k] == null || params[k] === "") && delete params[k]);

    try {
      const response = await axios.get("/tpv/quotations/list", { params });
      quotations.value = Array.isArray(response.data?.data) ? response.data.data : [];
      totalQuotations.value = response.data?.total ?? 0;
    } catch (error) {
      console.error("Error al obtener cotizaciones:", error);
      toast.error("Error al obtener las cotizaciones.");
      quotations.value = [];
      totalQuotations.value = 0;
    } finally {
      loadingQuotations.value = false;
    }
  };

  const fetchSellers = async () => {
    try {
      const { data } = await axios.get("/users");
      sellers.value = data?.data ?? data ?? [];
    } catch (error) {
      toast.error("No se pudo cargar el listado de personal.");
    }
  };

  const fetchDiningTables = async () => {
    if (mode !== "restaurant") return;
    try {
      const { data } = await axios.get("/dining-floor/overview");
      const zones = data?.data?.zones || data?.zones || [];
      const tables = [];
      zones.forEach((zone) => {
        (zone.tables || []).forEach((t) => {
          tables.push({
            id: t.id,
            title: zone.name ? `${t.name} (${zone.name})` : t.name,
          });
        });
      });
      diningTables.value = tables;
    } catch (error) {
      console.warn("No se pudieron cargar las mesas:", error);
    }
  };

  // Handlers de paginación y ordenamiento
  const handleUpdateTableOptions = (options) => {
    if (!options) return;
    page.value = options.page ?? 1;
    itemsPerPage.value = options.itemsPerPage ?? 25;
    if (Array.isArray(options.sortBy) && options.sortBy.length > 0) {
      sortBy.value = options.sortBy[0].key;
      orderBy.value = options.sortBy[0].order;
    } else {
      sortBy.value = null;
      orderBy.value = null;
    }
  };

  const handleUpdateQuotationOptions = (options) => {
    if (!options) return;
    pageQuotations.value = options.page ?? 1;
    itemsPerPageQuotations.value = options.itemsPerPage ?? 25;
    if (Array.isArray(options.sortBy) && options.sortBy.length > 0) {
      sortByQuotations.value = options.sortBy[0].key;
      orderByQuotations.value = options.sortBy[0].order;
    } else {
      sortByQuotations.value = null;
      orderByQuotations.value = null;
    }
  };

  const handleClearFilters = () => {
    filterSearchQueryId.value = "";
    filterSearchQuery.value = "";
    currencyFilter.value = null;
    sellerFilter.value = null;
    stateFilterAll.value = null;
    hasFiscalFilter.value = null;
    paymentMethodFilter.value = null;
    onlyPsychotropicFilter.value = false;
    discountTypeFilter.value = null;
    diningTableFilter.value = null;
    sortBy.value = null;
    orderBy.value = null;
    page.value = 1;
    pageQuotations.value = 1;
  };

  const hasActiveAdvancedFilters = computed(() => {
    return !!(
      isAdvancedFiltersVisible.value ||
      filterSearchQueryId.value ||
      currencyFilter.value ||
      sellerFilter.value ||
      stateFilterAll.value ||
      hasFiscalFilter.value ||
      paymentMethodFilter.value ||
      onlyPsychotropicFilter.value ||
      discountTypeFilter.value ||
      diningTableFilter.value ||
      globalStartDate.value ||
      globalEndDate.value
    );
  });

  // Mapea detalles de orden para modal e impresión
  const mapOrderDetailsForView = (details = []) => {
    return details.map((detail) => ({
      id: detail.product?.id ?? detail.dish?.id ?? detail.court?.id ?? detail.product_id ?? detail.dish_id ?? detail.court_id,
      product_id: detail.product_id ?? detail.product?.id,
      dish_id: detail.dish_id ?? detail.dish?.id,
      court_id: detail.court_id ?? detail.court?.id,
      title: detail.product?.name ?? detail.dish?.name ?? detail.court?.name ?? "—",
      active_ingredient: detail.court ? "Cancha" : (detail.product?.active_ingredient || null),
      laboratory: detail.court
        ? "Alquiler Deportivo"
        : (detail.dish ? "Plato / Menú" : (detail.product?.laboratory?.name ?? detail.product?.laboratory ?? null)),
      selectedQuantity: detail.quantity,
      taxRate: 0,
      unit_price: parseFloat(detail.price) || 0,
      price_bs: parseFloat(detail.price_bs ?? detail.price) || 0,
      price_cop: parseFloat(detail.price) || 0,
      price: parseFloat(detail.price) || 0,
      price_before_discount: parseFloat(detail.price_before_discount) || 0,
      product: detail.product,
      dish: detail.dish,
      lot: detail.lot || null,
      modifiers: Array.isArray(detail.modifiers) ? detail.modifiers : [],
      notes: detail.notes || null,
      kitchen_status: detail.kitchen_status || null,
      kitchen_status_label: detail.kitchen_status_label || null,
      seat_number: detail.seat_number || null,
      course_number: detail.course_number || null,
      iva: detail.product?.iva ?? (detail.dish?.iva ? 1 : 0),
    }));
  };

  const resetPrintState = () => {
    isPrinting.value = false;
    paymentsForPrint.value = [];
    orderData.value = null;
    orderItems.value = [];
    changeAmountForPrint.value = 0;
    creditAmountForPrint.value = 0;
    amountForPrint.value = 0;
    creditForPrint.value = false;
  };

  const loadOrderPrintPayload = async (orderId) => {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    const payload = response.data?.data;
    if (!payload?.order) {
      throw new Error("La respuesta del servidor no tiene el formato esperado.");
    }

    const { order, hasCreditPayment } = payload;
    orderData.value = order;
    currency.value = String(order.currency || "COP").toUpperCase();
    orderItems.value = mapOrderDetailsForView(order.details || []);
    paymentsForPrint.value = order.payment_methods || [];
    changeAmountForPrint.value = parseFloat(order.money_returns) || 0;
    amountForPrint.value = parseFloat(order.total_amount) || 0;
    creditAmountForPrint.value = hasCreditPayment ? (parseFloat(order.total_amount) || 0) : 0;
    creditForPrint.value = Boolean(hasCreditPayment);
  };

  const printOrder = async (orderId) => {
    try {
      await loadOrderPrintPayload(orderId);
      isPrinting.value = true;
      await nextTick();

      const printContents = document.getElementById("orderPrint");
      const businessName = brandingStore.settings?.company_name || "Comprobante de Venta";

      if (printContents) {
        const printWindow = window.open("", "", "height=600,width=800");
        printWindow.document.write(`<html><head><title>${businessName}</title>`);

        const styleSheets = document.styleSheets;
        for (let i = 0; i < styleSheets.length; i++) {
          const sheet = styleSheets[i];
          try {
            if (sheet.cssRules) {
              let cssText = "";
              for (let j = 0; j < sheet.cssRules.length; j++) {
                cssText += sheet.cssRules[j].cssText;
              }
              printWindow.document.write(`<style>${cssText}</style>`);
            } else if (sheet.href) {
              printWindow.document.write(`<link rel="stylesheet" href="${sheet.href}">`);
            }
          } catch (e) {
            console.warn("No se pudo acceder a la hoja de estilo:", sheet.href || sheet, e);
          }
        }

        printWindow.document.write("</head><body>");
        printWindow.document.write(printContents.innerHTML);
        printWindow.document.write("</body></html>");
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
      } else {
        window.print();
      }
    } catch (error) {
      console.error("Error al imprimir la orden:", error);
      toast.error(error.response?.data?.message || error.message || "Error al imprimir la orden.");
    } finally {
      setTimeout(() => {
        if (!viewModal.value) resetPrintState();
        else isPrinting.value = false;
      }, 500);
    }
  };

  const printOrderThermal54 = async (orderId) => {
    try {
      await loadOrderPrintPayload(orderId);
      isPrinting.value = true;
      await nextTick();

      const printContents = document.getElementById("orderPrintThermal54");
      const businessName = brandingStore.settings?.company_name || "Ticket 54mm";

      if (printContents) {
        const win = window.open("", "", "height=400,width=280");
        win.document.write(`<html><head><title>Ticket 54mm - ${businessName}</title>`);
        win.document.write(`<style>${THERMAL_54MM_CSS}</style>`);
        win.document.write("</head><body>");
        win.document.write(printContents.innerHTML);
        win.document.write("</body></html>");
        win.document.close();
        win.focus();
        win.print();
        win.close();
      } else {
        toast.error("No se encontró el contenido del ticket térmico.");
      }
    } catch (error) {
      console.error("Error al imprimir ticket 54mm:", error);
      toast.error(error.response?.data?.message || error.message || "Error al imprimir el ticket térmico.");
    } finally {
      setTimeout(() => {
        if (!viewModal.value) resetPrintState();
        else isPrinting.value = false;
      }, 500);
    }
  };

  const handleViewOrder = async (orderId) => {
    try {
      await loadOrderPrintPayload(orderId);
      viewModal.value = true;
    } catch (error) {
      console.error("Error al obtener los detalles de la orden:", error);
      toast.error("Error al obtener los detalles de la orden.");
    }
  };

  const handleCloseViewModal = () => {
    viewModal.value = false;
    resetPrintState();
  };

  const handleOpenFiscalPreview = async (orderId) => {
    if (!orderId) return;
    loadingFiscalPreview.value = true;
    fiscalPreviewData.value = null;
    isFiscalPreviewVisible.value = true;

    try {
      const response = await axios.get(`/tpv/orders/${orderId}/fiscal-preview`);
      fiscalPreviewData.value = response.data?.data ?? null;
    } catch (error) {
      console.error("Error al obtener previa fiscal:", error);
      toast.error(error.response?.data?.message || "No se pudo obtener la previa de la factura fiscal.");
      isFiscalPreviewVisible.value = false;
    } finally {
      loadingFiscalPreview.value = false;
    }
  };

  const handleConfirmFiscalInvoice = async (orderId) => {
    if (!orderId || isConfirmingFiscal.value) return;
    isConfirmingFiscal.value = true;
    processingFiscalIds.value.push(orderId);
    try {
      const response = await axios.post(`/tpv/orders/${orderId}/fiscal-invoice`);
      toast.success(response.data?.message || "Comprobante fiscal generado e impresión iniciada exitosamente.");
      isFiscalPreviewVisible.value = false;
      await Promise.allSettled([fetchOrders(), fetchSummary()]);
      if (viewModal.value && orderData.value?.id === orderId) {
        await handleViewOrder(orderId);
      }
    } catch (error) {
      console.error("Error al crear e imprimir factura fiscal:", error);
      toast.error(error.response?.data?.message || "Error al crear e imprimir la factura fiscal.");
    } finally {
      isConfirmingFiscal.value = false;
      processingFiscalIds.value = processingFiscalIds.value.filter((id) => id !== orderId);
    }
  };

  const handleCancelOrder = async (orderId) => {
    if (!orderId) return;
    const result = await Swal.fire({
      title: "¿Cancelar Orden?",
      text: `¿Estás seguro de cancelar la orden #${orderId}?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6e7881",
      confirmButtonText: "Sí, Cancelar Orden",
      cancelButtonText: "No, Mantener",
    });

    if (result.isConfirmed) {
      try {
        await axios.patch(`/tpv/orders/${orderId}/cancelled`);
        toast.success(`Orden #${orderId} cancelada exitosamente.`);
        if (viewModal.value) handleCloseViewModal();
        await Promise.allSettled([fetchOrders(), fetchSummary()]);
      } catch (err) {
        console.error("Error cancelando orden:", err);
        toast.error(err.response?.data?.message || err.response?.data?.error || "No se pudo cancelar la orden.");
      }
    }
  };

  // Computados para impresión de tickets
  const selectedDiscountType = computed(() => {
    const itemWithDiscount = orderData.value?.details?.find(
      (detail) => detail.discount_type !== null && detail.discount_type !== ""
    );
    return itemWithDiscount ? itemWithDiscount.discount_type : null;
  });

  const sumDiscountByTypes = (types) => {
    if (!orderData.value?.details) return 0;
    return orderData.value.details.reduce((acc, detail) => {
      if (types.includes(detail.discount_type)) {
        const price = parseFloat(detail.price) || 0;
        const quantity = parseFloat(detail.quantity) || 0;
        const percentage = parseFloat(detail.discount_percentage) || 0;
        return acc + price * quantity * (percentage / 100);
      }
      return acc;
    }, 0);
  };

  const totalCompanyDiscountAmount = computed(() => sumDiscountByTypes(["Empresa", "company"]));
  const totalDoctorDiscountAmount = computed(() => sumDiscountByTypes(["Medico", "doctor"]));
  const totalRecipeDiscountAmount = computed(() => sumDiscountByTypes(["Recipe", "recipe"]));

  const isSpecialTaxpayer = computed(() => {
    if (!orderData.value) return false;
    return (parseFloat(orderData.value.spe_surcharge_amount || 0) > 0) ||
      (parseFloat(orderData.value.spe_surcharge_rate || 0) > 0);
  });

  const speSurchargeAmount = computed(() => parseFloat(orderData.value?.spe_surcharge_amount || 0));

  const sellerDisplayName = (item) => (item?.username ? capitalizeFirstAndLastName(item.username) : "");

  const formatQuotationDate = (dateStr) => {
    if (!dateStr) return "—";
    const d = new Date(dateStr);
    return d.toLocaleDateString("es-ES", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  // Watchers con debounce único
  let fetchDebounceTimer = null;
  let summaryDebounceTimer = null;

  const scheduleFetchOrders = () => {
    clearTimeout(fetchDebounceTimer);
    fetchDebounceTimer = setTimeout(() => {
      fetchOrders();
    }, 280);
  };

  const scheduleFetchSummary = () => {
    clearTimeout(summaryDebounceTimer);
    summaryDebounceTimer = setTimeout(() => {
      fetchSummary();
    }, 320);
  };

  watch(activeTab, () => {
    page.value = 1;
    fetchOrders();
  });

  watch(
    [page, itemsPerPage, sortBy, orderBy],
    () => {
      if (activeTab.value !== "quotations") {
        scheduleFetchOrders();
      }
    }
  );

  watch(
    [pageQuotations, itemsPerPageQuotations, sortByQuotations, orderByQuotations],
    () => {
      if (activeTab.value === "quotations") {
        scheduleFetchOrders();
      }
    }
  );

  watch(
    [
      filterSearchQueryId,
      filterSearchQuery,
      currencyFilter,
      sellerFilter,
      stateFilterAll,
      hasFiscalFilter,
      paymentMethodFilter,
      onlyPsychotropicFilter,
      discountTypeFilter,
      diningTableFilter,
      globalStartDate,
      globalEndDate,
    ],
    () => {
      page.value = 1;
      pageQuotations.value = 1;
      scheduleFetchOrders();
      scheduleFetchSummary();
    }
  );

  onMounted(async () => {
    fetchSellers();
    if (mode === "restaurant") {
      fetchDiningTables();
    }
    await Promise.allSettled([fetchOrders(), fetchSummary()]);

    if (route.query.orderId) {
      const orderId = parseInt(route.query.orderId, 10);
      if (orderId) {
        await handleViewOrder(orderId);
      }
    }
  });

  onUnmounted(() => {
    clearTimeout(fetchDebounceTimer);
    clearTimeout(summaryDebounceTimer);
  });

  return {
    isVendedor,
    activeTab,
    orders,
    totalOrders,
    loadingOrders,
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    quotations,
    totalQuotations,
    loadingQuotations,
    pageQuotations,
    itemsPerPageQuotations,
    sortByQuotations,
    orderByQuotations,
    summaryLoading,
    tabCounts,
    kpis,
    sellers,
    diningTables,
    filterSearchQueryId,
    filterSearchQuery,
    currencyFilter,
    sellerFilter,
    stateFilterAll,
    hasFiscalFilter,
    paymentMethodFilter,
    onlyPsychotropicFilter,
    discountTypeFilter,
    diningTableFilter,
    isAdvancedFiltersVisible,
    hasActiveAdvancedFilters,
    globalStartDate,
    globalEndDate,
    setDateHoy,
    setDateAyer,
    setDateSemana,
    setDateMes,
    setDateAno,
    viewModal,
    isPrinting,
    processingFiscalIds,
    isFiscalPreviewVisible,
    fiscalPreviewData,
    loadingFiscalPreview,
    isConfirmingFiscal,
    paymentsForPrint,
    changeAmountForPrint,
    creditAmountForPrint,
    amountForPrint,
    creditForPrint,
    currency,
    orderData,
    orderItems,
    selectedDiscountType,
    totalCompanyDiscountAmount,
    totalDoctorDiscountAmount,
    totalRecipeDiscountAmount,
    isSpecialTaxpayer,
    speSurchargeAmount,
    sellerDisplayName,
    formatQuotationDate,
    handleUpdateTableOptions,
    handleUpdateQuotationOptions,
    handleClearFilters,
    handleViewOrder,
    handleCloseViewModal,
    handleOpenFiscalPreview,
    handleConfirmFiscalInvoice,
    handleCancelOrder,
    printOrder,
    printOrderThermal54,
    fetchOrders,
    fetchSummary,
  };
}
