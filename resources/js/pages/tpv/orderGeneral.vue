<script setup>
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import AppFilterBase from "@/components/AppFilterBase.vue";
import OrderTable from "@/components/OrderTable.vue";
import OrderTicket from "@/components/OrderTicket.vue";
import OrderTicketThermal54 from "@/components/OrderTicketThermal54.vue";
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { useRoute } from "vue-router";
import { computed, nextTick, onMounted, ref, watch } from "vue";

const { isVendedor } = useAuthStore();
const route = useRoute();

const ordersCompleted = ref([]);
const totalOrdersCompleted = ref(0);
const loadingOrdersCompleted = ref(false);

const pageOrdersCompleted = ref(1);
const itemsPerPageOrdersCompleted = ref(25);
const sortByOrdersCompleted = ref();
const orderByOrdersCompleted = ref();

const ordersAll = ref([]);
const totalOrdersAll = ref(0);
const loadingOrdersAll = ref(false);

const pageOrdersAll = ref(1);
const itemsPerPageOrdersAll = ref(25);
const sortByOrdersAll = ref();
const orderByOrdersAll = ref();

const isPrinting = ref(false);

const ordersAbandoned = ref([]);
const totalOrdersAbandoned = ref(0);
const loadingOrdersAbandoned = ref(false);

const pageOrdersAbandoned = ref(1);
const itemsPerPageOrdersAbandoned = ref(25);
const sortByOrdersAbandoned = ref();
const orderByOrdersAbandoned = ref();

const ordersCancelled = ref([]);
const totalOrdersCancelled = ref(0);
const loadingOrdersCancelled = ref(false);

const pageOrdersCancelled = ref(1);
const itemsPerPageOrdersCancelled = ref(25);
const sortByOrdersCancelled = ref();
const orderByOrdersCancelled = ref();

const quotations = ref([]);
const totalQuotations = ref(0);
const loadingQuotations = ref(false);
const pageQuotations = ref(1);
const itemsPerPageQuotations = ref(25);

const sellers = ref([]);
const users = ref([]);

// Filtros unificados para todas las pestañas (un solo card de filtros)
const filterSearchQueryId = ref("");
const filterSearchQuery = ref("");
const currencyFilter = ref(null);
const sellerFilter = ref(null);
const stateFilterAll = ref(null);

const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const amountForPrint = ref(0);
const creditForPrint = ref(false);
const currency = ref("COP");
const orderData = ref(null);
const orderItems = ref([]);

const viewModal = ref(false);
const isAdvancedFiltersVisible = ref(false); // Cambiado para usar VExpandTransition o similar
const activeTab = ref(0);

// Rango de fechas global: por defecto primer día del mes actual (evita tabla vacía por zona horaria o sin ventas hoy)
const toDateString = (d) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

const getToday = () => toDateString(new Date());
const getFirstDayOfMonth = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
};

// Por defecto: día actual desde 00:00 (solo hoy)
const globalStartDate = ref(getToday());
const globalEndDate = ref(getToday());

const setDateRange = (start, end) => {
  globalStartDate.value = start;
  globalEndDate.value = end;
};
const setDateHoy = () => {
  const t = new Date();
  setDateRange(toDateString(t), toDateString(t));
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
  const h = new Date();
  setDateRange(getFirstDayOfMonth(), toDateString(h));
};
const setDateAno = () => {
  const h = new Date();
  const inicio = `${h.getFullYear()}-01-01`;
  setDateRange(inicio, toDateString(h));
};

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Identificación", key: "identification", sortable: true },
  { title: "Cliente", key: "client_full_name", sortable: true },
  { title: "Vendedor", key: "seller.username", sortable: true },
  { title: "Monto", key: "total_amount", sortable: true, align: "end" },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const headersAll = [
  { title: "ID", key: "id", sortable: true },
  { title: "Identificación", key: "identification", sortable: true },
  { title: "Cliente", key: "client_full_name", sortable: true },
  { title: "Vendedor", key: "seller.username", sortable: true },
  { title: "Monto", key: "total_amount", sortable: true, align: "end" },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const headersQuotations = [
  { title: "ID", key: "id", sortable: true },
  { title: "Cliente", key: "client_display", sortable: false },
  { title: "Creado por", key: "creator_display", sortable: false },
  { title: "Total", key: "total", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "created_at", sortable: true },
];

const fetchOrderCompleted = async () => {
  loadingOrdersCompleted.value = true;
  const params = {
    id: filterSearchQueryId.value,
    q: filterSearchQuery.value,
    ...(currencyFilter.value !== null && { currency: currencyFilter.value }),
    ...(sellerFilter.value !== null && { seller_id: sellerFilter.value }),
    ...(globalStartDate.value && { start_date: globalStartDate.value }),
    ...(globalEndDate.value && { end_date: globalEndDate.value }),
    page: pageOrdersCompleted.value,
    itemsPerPage: itemsPerPageOrdersCompleted.value,
    sortBy: sortByOrdersCompleted.value,
    orderBy: orderByOrdersCompleted.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/orders/completed", { params });
    ordersCompleted.value = Array.isArray(response.data?.data) ? response.data.data : [];
    totalOrdersCompleted.value = response.data?.total ?? 0;
  } catch (error) {
    console.error("Hubo un error al obtener las ordenes:", error);
    toast.error("Error al obtener las ordenes.");
  } finally {
    loadingOrdersCompleted.value = false;
  }
};

const fetchOrderAll = async () => {
  loadingOrdersAll.value = true;
  const params = {
    id: filterSearchQueryId.value,
    q: filterSearchQuery.value,
    ...(currencyFilter.value !== null && { currency: currencyFilter.value }),
    ...(sellerFilter.value !== null && { seller_id: sellerFilter.value }),
    ...(stateFilterAll.value !== null && { state: stateFilterAll.value }),
    ...(globalStartDate.value && { start_date: globalStartDate.value }),
    ...(globalEndDate.value && { end_date: globalEndDate.value }),
    page: pageOrdersAll.value,
    itemsPerPage: itemsPerPageOrdersAll.value,
    sortBy: sortByOrdersAll.value,
    orderBy: orderByOrdersAll.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/orders/all", { params });
    ordersAll.value = Array.isArray(response.data?.data) ? response.data.data : [];
    totalOrdersAll.value = response.data?.total ?? 0;
  } catch (error) {
    console.error("Hubo un error al obtener las ordenes:", error);
    toast.error("Error al obtener las ordenes.");
  } finally {
    loadingOrdersAll.value = false;
  }
};

const fetchOrderAbandoned = async () => {
  loadingOrdersAbandoned.value = true;
  const params = {
    id: filterSearchQueryId.value,
    q: filterSearchQuery.value,
    ...(currencyFilter.value !== null && { currency: currencyFilter.value }),
    ...(sellerFilter.value !== null && { seller_id: sellerFilter.value }),
    ...(globalStartDate.value && { start_date: globalStartDate.value }),
    ...(globalEndDate.value && { end_date: globalEndDate.value }),
    page: pageOrdersAbandoned.value,
    itemsPerPage: itemsPerPageOrdersAbandoned.value,
    sortBy: sortByOrdersAbandoned.value,
    orderBy: orderByOrdersAbandoned.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/orders/abandoned", { params });
    ordersAbandoned.value = Array.isArray(response.data?.data) ? response.data.data : [];
    totalOrdersAbandoned.value = response.data?.total ?? 0;
  } catch (error) {
    console.error("Hubo un error al obtener las ordenes:", error);
    toast.error("Error al obtener las ordenes.");
  } finally {
    loadingOrdersAbandoned.value = false;
  }
};

const fetchOrderCancelled = async () => {
  loadingOrdersCancelled.value = true;
  const params = {
    id: filterSearchQueryId.value,
    q: filterSearchQuery.value,
    ...(currencyFilter.value !== null && { currency: currencyFilter.value }),
    ...(sellerFilter.value !== null && { seller_id: sellerFilter.value }),
    ...(globalStartDate.value && { start_date: globalStartDate.value }),
    ...(globalEndDate.value && { end_date: globalEndDate.value }),
    page: pageOrdersCancelled.value,
    itemsPerPage: itemsPerPageOrdersCancelled.value,
    sortBy: sortByOrdersCancelled.value,
    orderBy: orderByOrdersCancelled.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/orders/cancelled", { params });
    ordersCancelled.value = Array.isArray(response.data?.data) ? response.data.data : [];
    totalOrdersCancelled.value = response.data?.total ?? 0;
  } catch (error) {
    console.error("Hubo un error al obtener las ordenes:", error);
    toast.error("Error al obtener las ordenes.");
  } finally {
    loadingOrdersCancelled.value = false;
  }
};

const resetGlobalDateRange = () => setDateHoy();

const fetchQuotations = async () => {
  loadingQuotations.value = true;
  const params = {
    q: filterSearchQuery.value || undefined,
    start_date: globalStartDate.value || undefined,
    end_date: globalEndDate.value || undefined,
    page: pageQuotations.value,
    itemsPerPage: itemsPerPageQuotations.value,
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
    toast.error("No se pudo cargar el listado de vendedores.");
  }
};

onMounted(async () => {
  fetchSellers();
  fetchOrderCompleted();
  fetchOrderAll();
  fetchOrderAbandoned();
  fetchOrderCancelled();
  fetchQuotations();

  if (route.query.orderId) {
    const orderId = parseInt(route.query.orderId, 10);
    if (orderId) {
      await handleViewOrder(orderId);
    }
  }
});

let debounceTimerCompleted;
watch(
  [
    pageOrdersCompleted,
    itemsPerPageOrdersCompleted,
    currencyFilter,
    sellerFilter,
    filterSearchQueryId,
    filterSearchQuery,
    sortByOrdersCompleted,
    orderByOrdersCompleted,
  ],
  () => {
    clearTimeout(debounceTimerCompleted);
    debounceTimerCompleted = setTimeout(() => fetchOrderCompleted(), 300);
  },
  { deep: true }
);

let debounceTimerAll;
watch(
  [
    pageOrdersAll,
    itemsPerPageOrdersAll,
    currencyFilter,
    sellerFilter,
    stateFilterAll,
    filterSearchQueryId,
    filterSearchQuery,
    sortByOrdersAll,
    orderByOrdersAll,
  ],
  () => {
    clearTimeout(debounceTimerAll);
    debounceTimerAll = setTimeout(() => fetchOrderAll(), 300);
  },
  { deep: true }
);

let debounceTimerAbandoned;
watch(
  [
    pageOrdersAbandoned,
    itemsPerPageOrdersAbandoned,
    currencyFilter,
    sellerFilter,
    filterSearchQueryId,
    filterSearchQuery,
    sortByOrdersAbandoned,
    orderByOrdersAbandoned,
  ],
  () => {
    clearTimeout(debounceTimerAbandoned);
    debounceTimerAbandoned = setTimeout(() => fetchOrderAbandoned(), 300);
  },
  { deep: true }
);

let debounceTimerCancelled;
watch(
  [
    pageOrdersCancelled,
    itemsPerPageOrdersCancelled,
    currencyFilter,
    sellerFilter,
    filterSearchQueryId,
    filterSearchQuery,
    sortByOrdersCancelled,
    orderByOrdersCancelled,
  ],
  () => {
    clearTimeout(debounceTimerCancelled);
    debounceTimerCancelled = setTimeout(() => fetchOrderCancelled(), 300);
  },
  { deep: true }
);

const handleClearFilters = () => {
  filterSearchQueryId.value = "";
  filterSearchQuery.value = "";
  currencyFilter.value = null;
  sellerFilter.value = null;
  stateFilterAll.value = null;
  sortByOrdersCompleted.value = undefined;
  orderByOrdersCompleted.value = undefined;
  sortByOrdersAll.value = undefined;
  orderByOrdersAll.value = undefined;
  sortByOrdersAbandoned.value = undefined;
  orderByOrdersAbandoned.value = undefined;
  sortByOrdersCancelled.value = undefined;
  orderByOrdersCancelled.value = undefined;
  pageOrdersCompleted.value = 1;
  pageOrdersAll.value = 1;
  pageOrdersAbandoned.value = 1;
  pageOrdersCancelled.value = 1;
  pageQuotations.value = 1;
};

let debounceTimerQuotations;
watch(
  [pageQuotations, itemsPerPageQuotations, filterSearchQuery, globalStartDate, globalEndDate],
  () => {
    clearTimeout(debounceTimerQuotations);
    debounceTimerQuotations = setTimeout(() => fetchQuotations(), 300);
  },
  { deep: true }
);

watch(
  [filterSearchQuery, currencyFilter, sellerFilter],
  () => { pageOrdersCompleted.value = 1; },
  { deep: true }
);

watch(
  [filterSearchQuery, currencyFilter, sellerFilter, stateFilterAll],
  () => { pageOrdersAll.value = 1; },
  { deep: true }
);

watch(
  [filterSearchQuery, currencyFilter, sellerFilter],
  () => {
    pageOrdersAbandoned.value = 1;
    pageOrdersCancelled.value = 1;
    pageQuotations.value = 1;
  },
  { deep: true }
);

watch([globalStartDate, globalEndDate], () => {
  pageOrdersCompleted.value = 1;
  pageOrdersAll.value = 1;
  pageOrdersAbandoned.value = 1;
  pageOrdersCancelled.value = 1;
  pageQuotations.value = 1;
  fetchOrderCompleted();
  fetchOrderAll();
  fetchOrderAbandoned();
  fetchOrderCancelled();
  fetchQuotations();
}, { deep: true });

const updateTableOptionsOrdersCompleted = (options) => {
  pageOrdersCompleted.value = options.page;
  itemsPerPageOrdersCompleted.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByOrdersCompleted.value = options.sortBy[0].key;
    orderByOrdersCompleted.value = options.sortBy[0].order;
  } else {
    sortByOrdersCompleted.value = null;
    orderByOrdersCompleted.value = null;
  }
};

const updateTableOptionsOrdersAll = (options) => {
  pageOrdersAll.value = options.page;
  itemsPerPageOrdersAll.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByOrdersAll.value = options.sortBy[0].key;
    orderByOrdersAll.value = options.sortBy[0].order;
  } else {
    sortByOrdersAll.value = null;
    orderByOrdersAll.value = null;
  }
};

const updateTableOptionsOrdersAbandoned = (options) => {
  pageOrdersAbandoned.value = options.page;
  itemsPerPageOrdersAbandoned.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByOrdersAbandoned.value = options.sortBy[0].key;
    orderByOrdersAbandoned.value = options.sortBy[0].order;
  } else {
    sortByOrdersAbandoned.value = null;
    orderByOrdersAbandoned.value = null;
  }
};

const updateTableOptionsOrdersCancelled = (options) => {
  pageOrdersCancelled.value = options.page;
  itemsPerPageOrdersCancelled.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByOrdersCancelled.value = options.sortBy[0].key;
    orderByOrdersCancelled.value = options.sortBy[0].order;
  } else {
    sortByOrdersCancelled.value = null;
    orderByOrdersCancelled.value = null;
  }
};

const updateTableOptionsQuotations = (options) => {
  pageQuotations.value = options.page;
  itemsPerPageQuotations.value = options.itemsPerPage;
};

const formatQuotationDate = (dateStr) => {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  return d.toLocaleDateString("es-ES", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
};

const printOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
      orderItems.value = response.data.data.order.details.map((detail) => ({
        id: detail.product?.id ?? detail.product_id,
        product_id: detail.product_id ?? detail.product?.id,
        title: detail.product?.name,
        active_ingredient: detail.product?.active_ingredient || null,
        laboratory: detail.product?.laboratory?.name ?? detail.product?.laboratory ?? null,
        selectedQuantity: detail.quantity,
        taxRate: detail.product?.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount),
      }));
      paymentsForPrint.value = response.data.data.order.payment_methods;
      changeAmountForPrint.value = parseFloat(
        response.data.data.order.money_returns
      );
      amountForPrint.value = parseFloat(response.data.data.order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(response.data.data.order.total_amount)
        : 0;
      creditForPrint.value = response.data.data.hasCreditPayment;
      isPrinting.value = true;
      await nextTick();
      const printContents = document.getElementById("orderPrint");

      if (printContents) {
        const printWindow = window.open("", "", "height=600,width=800");
        printWindow.document.write(
          "<html><head><title>Farmacia Barrio Sucre</title>"
        );

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
              printWindow.document.write(
                `<link rel="stylesheet" href="${sheet.href}">`
              );
            }
          } catch (e) {
            console.warn(
              "No se pudo acceder a la hoja de estilo:",
              sheet.href || sheet,
              e
            );
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
        console.warn(
          "Elemento #orderPrint no encontrado para impresión tipo ticket. Imprimiendo toda la página."
        );
        window.print();
      }
    } else {
      console.error("Respuesta de API con formato incorrecto:", response.data);
      toast.error("La respuesta del servidor no tiene el formato esperado.");
    }
  } catch (error) {
    console.error(
      "Error al imprimir la orden:",
      error.response?.data || error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Hubo un problema al imprimir la orden. Por favor, intente de nuevo.";
    toast.error(errorMessage);
    isPrinting.value = false;
    paymentsForPrint.value = [];
    orderData.value = null;
    orderItems.value = [];
    amountForPrint.value = 0;
    changeAmountForPrint.value = 0;
    creditAmountForPrint.value = 0;
    creditForPrint.value = false;
  } finally {
    setTimeout(() => {
      isPrinting.value = false;
      paymentsForPrint.value = [];
      orderData.value = null;
      orderItems.value = [];
      changeAmountForPrint.value = 0;
      creditAmountForPrint.value = 0;
      amountForPrint.value = 0;
      creditForPrint.value = false;
    }, 500);
  }
};

import { THERMAL_54MM_CSS } from "@/constants/thermalTicket54.js";

const printOrderThermal54 = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data?.data?.order) {
      const { order } = response.data.data;
      orderData.value = order;
      currency.value = order.currency.toUpperCase();
      orderItems.value = order.details.map((detail) => ({
        id: detail.product?.id ?? detail.product_id,
        product_id: detail.product_id ?? detail.product?.id,
        title: detail.product?.name,
        active_ingredient: detail.product?.active_ingredient || null,
        laboratory: detail.product?.laboratory?.name ?? detail.product?.laboratory ?? null,
        selectedQuantity: detail.quantity,
        taxRate: detail.product?.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount),
      }));
      paymentsForPrint.value = order.payment_methods;
      changeAmountForPrint.value = parseFloat(order.money_returns);
      amountForPrint.value = parseFloat(order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(order.total_amount)
        : 0;
      creditForPrint.value = response.data.data.hasCreditPayment;
      isPrinting.value = true;
      await nextTick();
      const printContents = document.getElementById("orderPrintThermal54");
      if (printContents) {
        const win = window.open("", "", "height=400,width=280");
        win.document.write("<html><head><title>Ticket 54mm - Farmacia Barrio Sucre</title>");
        win.document.write("<style>" + THERMAL_54MM_CSS + "</style>");
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
    } else {
      toast.error("La respuesta del servidor no tiene el formato esperado.");
    }
  } catch (error) {
    console.error("Error al imprimir ticket 54mm:", error);
    toast.error(error.response?.data?.message || "Error al imprimir el ticket térmico.");
  } finally {
    setTimeout(() => {
      isPrinting.value = false;
      paymentsForPrint.value = [];
      orderData.value = null;
      orderItems.value = [];
      changeAmountForPrint.value = 0;
      creditAmountForPrint.value = 0;
      amountForPrint.value = 0;
      creditForPrint.value = false;
    }, 500);
  }
};

const handleCloseViewModal = () => {
  viewModal.value = false;
  orderData.value = null;
  orderItems.value = [];
  paymentsForPrint.value = [];
  changeAmountForPrint.value = 0;
  amountForPrint.value = 0;
  creditAmountForPrint.value = 0;
  creditForPrint.value = false;
};

const handleViewOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
      orderItems.value = response.data.data.order.details.map((detail) => ({
        id: detail.product?.id ?? detail.product_id,
        product_id: detail.product_id ?? detail.product?.id,
        title: detail.product?.name,
        active_ingredient: detail.product?.active_ingredient || null,
        laboratory: detail.product?.laboratory?.name ?? detail.product?.laboratory ?? null,
        selectedQuantity: detail.quantity,
        taxRate: detail.product?.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount),
      }));
      paymentsForPrint.value = response.data.data.order.payment_methods;
      changeAmountForPrint.value = parseFloat(
        response.data.data.order.money_returns
      );
      amountForPrint.value = parseFloat(response.data.data.order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(response.data.data.order.total_amount)
        : 0;
      creditForPrint.value = response.data.data.hasCreditPayment;
      viewModal.value = true;
    } else {
      console.error("Respuesta de API con formato incorrecto:", response.data);
      toast.error("La respuesta del servidor no tiene el formato esperado.");
    }
  } catch (error) {
    console.error("Error al obtener los detalles de la orden:", error);
    toast.error("Error al obtener los detalles de la orden.");
  }
};

const selectedDiscountType = computed(() => {
  const itemWithDiscount = orderData.value?.details?.find(
    detail => detail.discount_type !== null && detail.discount_type !== ""
  );
  return itemWithDiscount ? itemWithDiscount.discount_type : null;
});

const totalCompanyDiscountAmount = computed(() => {
  if (!orderData.value || !orderData.value.details) return 0;


  return orderData.value.details.reduce((acc, detail) => {
    if (detail.discount_type === 'Empresa' || detail.discount_type === 'company') {
      const price = parseFloat(detail.price) || 0;
      const quantity = parseInt(detail.quantity) || 0;
      const percentage = parseFloat(detail.discount_percentage) || 0;

      const discountItem = (price * quantity) * (percentage / 100);
      return acc + discountItem;
    }
    return acc;
  }, 0);
});

const totalDoctorDiscountAmount = computed(() => {
  if (!orderData.value || !orderData.value.details) return 0;
  return orderData.value.details.reduce((acc, detail) => {
    if (detail.discount_type === 'Medico' || detail.discount_type === 'doctor') {
      const price = parseFloat(detail.price) || 0;
      const quantity = parseInt(detail.quantity) || 0;
      const percentage = parseFloat(detail.discount_percentage) || 0;

      const discountItem = (price * quantity) * (percentage / 100);
      return acc + discountItem;
    }
    return acc;
  }, 0);
});

const totalRecipeDiscountAmount = computed(() => {
  if (!orderData.value || !orderData.value.details) return 0;
  return orderData.value.details.reduce((acc, detail) => {
    if (detail.discount_type === 'Recipe' || detail.discount_type === 'recipe') {
      const price = parseFloat(detail.price) || 0;
      const quantity = parseInt(detail.quantity) || 0;
      const percentage = parseFloat(detail.discount_percentage) || 0;
      const discountItem = (price * quantity) * (percentage / 100);
      return acc + discountItem;
    }
    return acc;
  }, 0);
});


const isSpecialTaxpayer = computed(() => {
 if (!orderData.value) return false;
  const amount = parseFloat(orderData.value.spe_surcharge_amount || 0);
  const rate = parseFloat(orderData.value.spe_surcharge_rate || 0);
  return amount > 0 || rate > 0;
});

const speSurchargeAmount = computed(() => {
  if (orderData.value && orderData.value.spe_surcharge_amount) {
    return parseFloat(orderData.value.spe_surcharge_amount);
  }
  return 0.00;
});

// Opciones para filtros (mismo diseño que /inventory/products)
const currencyOptions = [
  { title: "BS", value: "BS" },
  { title: "USD", value: "USD" },
  { title: "COP", value: "COP" },
];
const stateOptions = [
  { title: "Completada", value: "Completed" },
  { title: "Abandonada", value: "Abandoned" },
  { title: "Cancelada", value: "Cancelled" },
];
const sellerDisplayName = (item) => (item?.username ? capitalizeFirstAndLastName(item.username) : "");
</script>
<template>
  <div>
    <!-- Contenedor Estándar de Filtros -->
    <AppFilterBase
      :search="filterSearchQuery"
      :has-advanced-filters="isAdvancedFiltersVisible || !!(filterSearchQueryId || currencyFilter || sellerFilter || stateFilterAll || globalStartDate || globalEndDate)"
      :show-export="true"
      search-placeholder="Buscar por Identificación, Vendedor o Cliente..."
      class="py-1"
      @update:search="filterSearchQuery = $event"
      @clear="handleClearFilters"
      @export="ext => ext === 'xlsx' ? $emit('export-excel') : $emit('export-pdf')"
    >
      <!-- Slot extra: Rango Rápido de Fechas -->
      <template #search-extra>
        <div class="d-none d-lg-flex align-center gap-2 ms-4 border-s ps-4">
          <span class="text-caption font-weight-bold text-uppercase text-disabled me-1">Rango:</span>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateHoy">Hoy</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAyer">Ayer</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateSemana">Semana</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateMes">Mes</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAno">Año</VBtn>
        </div>
      </template>

      <!-- Slot Filtros Avanzados -->
      <template #advanced-filters>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            v-model="globalStartDate"
            placeholder="Fecha Inicial"
            prepend-inner-icon="tabler-calendar"
            clearable
            hide-details
            density="compact"
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            v-model="globalEndDate"
            placeholder="Fecha Final"
            prepend-inner-icon="tabler-calendar"
            clearable
            hide-details
            density="compact"
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppTextField
            v-model="filterSearchQueryId"
            placeholder="ID Orden"
            prepend-inner-icon="tabler-hash"
            clearable
            hide-details
            density="compact"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VSelect
            v-model="currencyFilter"
            placeholder="Moneda"
            prepend-inner-icon="tabler-coin"
            :items="currencyOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol v-if="!isVendedor" cols="12" sm="3" md="2">
          <VSelect
            v-model="sellerFilter"
            placeholder="Vendedor"
            prepend-inner-icon="tabler-user-check"
            :items="sellers"
            :item-title="sellerDisplayName"
            item-value="id"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol v-if="activeTab === 1" cols="12" sm="3" md="2">
          <VSelect
            v-model="stateFilterAll"
            placeholder="Estado"
            prepend-inner-icon="tabler-adjustments-horizontal"
            :items="stateOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
      </template>
    </AppFilterBase>

    <!-- Pestañas con badge de cantidad por tipo -->
    <VTabs v-model="activeTab" class="mb-4 orders-tabs" density="comfortable">
      <VTab :value="0" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Completadas
          <VChip
            size="x-small"
            variant="tonal"
            color="success"
            class="tab-count"
          >
            {{ totalOrdersCompleted }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="1" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Todas
          <VChip
            size="x-small"
            variant="tonal"
            color="primary"
            class="tab-count"
          >
            {{ totalOrdersAll }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="2" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Canceladas
          <VChip
            size="x-small"
            variant="tonal"
            color="error"
            class="tab-count"
          >
            {{ totalOrdersCancelled }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="3" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Abandonadas
          <VChip
            size="x-small"
            variant="tonal"
            color="warning"
            class="tab-count"
          >
            {{ totalOrdersAbandoned }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="4" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Cotizaciones
          <VChip
            size="x-small"
            variant="tonal"
            color="info"
            class="tab-count"
          >
            {{ totalQuotations }}
          </VChip>
        </span>
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab" class="orders-window">
      <VWindowItem :value="0">
        <OrderTable
          :orders="ordersCompleted"
          :loading="loadingOrdersCompleted"
          :total-orders="totalOrdersCompleted"
          :items-per-page="itemsPerPageOrdersCompleted"
          :page="pageOrdersCompleted"
          :headers="headers"
          :sort-by="sortByOrdersCompleted"
          :order-by="orderByOrdersCompleted"
          :show-print-actions="false"
          @update:options="updateTableOptionsOrdersCompleted"
          @print-order="printOrder"
          @print-order-thermal="printOrderThermal54"
          @view-order="handleViewOrder"
        />
      </VWindowItem>
      <VWindowItem :value="1">
        <OrderTable
          :orders="ordersAll"
          :loading="loadingOrdersAll"
          :total-orders="totalOrdersAll"
          :items-per-page="itemsPerPageOrdersAll"
          :page="pageOrdersAll"
          :headers="headersAll"
          :sort-by="sortByOrdersAll"
          :order-by="orderByOrdersAll"
          :show-print-actions="false"
          @update:options="updateTableOptionsOrdersAll"
          @print-order="printOrder"
          @print-order-thermal="printOrderThermal54"
          @view-order="handleViewOrder"
        />
      </VWindowItem>
      <VWindowItem :value="2">
        <OrderTable
          :orders="ordersCancelled"
          :loading="loadingOrdersCancelled"
          :total-orders="totalOrdersCancelled"
          :items-per-page="itemsPerPageOrdersCancelled"
          :page="pageOrdersCancelled"
          :headers="headers"
          :sort-by="sortByOrdersCancelled"
          :order-by="orderByOrdersCancelled"
          :show-print-actions="false"
          @update:options="updateTableOptionsOrdersCancelled"
          @print-order="printOrder"
          @print-order-thermal="printOrderThermal54"
          @view-order="handleViewOrder"
        />
      </VWindowItem>
      <VWindowItem :value="3">
        <OrderTable
          :orders="ordersAbandoned"
          :loading="loadingOrdersAbandoned"
          :total-orders="totalOrdersAbandoned"
          :items-per-page="itemsPerPageOrdersAbandoned"
          :page="pageOrdersAbandoned"
          :headers="headers"
          :sort-by="sortByOrdersAbandoned"
          :order-by="orderByOrdersAbandoned"
          :show-print-actions="false"
          @update:options="updateTableOptionsOrdersAbandoned"
          @print-order="printOrder"
          @print-order-thermal="printOrderThermal54"
          @view-order="handleViewOrder"
        />
      </VWindowItem>
      <VWindowItem :value="4">
        <VCard variant="flat" border class="rounded-lg overflow-hidden elevation-1">
          <VDataTableServer
            :items="quotations"
            :headers="headersQuotations"
            :items-length="totalQuotations"
            :loading="loadingQuotations"
            :items-per-page="itemsPerPageQuotations"
            :page="pageQuotations"
            class="text-no-wrap quotation-table-premium"
            @update:options="updateTableOptionsQuotations"
          >
            <template #item.id="{ item }">
              <span class="text-primary font-weight-black">#{{ item.id }}</span>
            </template>
            <template #item.client_display="{ item }">
              {{ item.client ? `${item.client.name || ''} ${item.client.last_name || ''}`.trim() || item.client.identification : '—' }}
            </template>
            <template #item.creator_display="{ item }">
              {{ item.creator?.username ? capitalizeFirstAndLastName(item.creator.username) : '—' }}
            </template>
            <template #item.total="{ item }">
              <span class="font-weight-bold">
                {{ Number(item.total ?? 0).toLocaleString('es', { minimumFractionDigits: 2 }) }}
              </span>
            </template>
            <template #item.created_at="{ item }">
              {{ formatQuotationDate(item.created_at) }}
            </template>
          </VDataTableServer>
        </VCard>
      </VWindowItem>
    </VWindow>

    <div
      id="orderPrint"
      :class="{ 'd-none': !isPrinting, 'print-container': true }"
    >
      <OrderTicket
        v-if="isPrinting && orderData"
        :order-data="orderData"
        :order-products="orderItems"
        :total-amount="amountForPrint"
        :selected-currency="currency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
        :company-discount-total="totalCompanyDiscountAmount"
        :selected-discount-type="selectedDiscountType"
        :doctor-discount-total="totalDoctorDiscountAmount"
        :recipe-discount-total="totalRecipeDiscountAmount"
        :is-special-taxpayer="isSpecialTaxpayer"
        :spe-surcharge-amount="speSurchargeAmount"
      />
    </div>

    <div
      id="orderPrintThermal54"
      :class="{ 'd-none': !isPrinting, 'print-container': true }"
    >
      <OrderTicketThermal54
        v-if="isPrinting && orderData"
        :order-data="orderData"
        :order-products="orderItems"
        :total-amount="amountForPrint"
        :selected-currency="currency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
        :company-discount-total="totalCompanyDiscountAmount"
        :selected-discount-type="selectedDiscountType"
        :doctor-discount-total="totalDoctorDiscountAmount"
        :recipe-discount-total="totalRecipeDiscountAmount"
        :is-special-taxpayer="isSpecialTaxpayer"
        :spe-surcharge-amount="speSurchargeAmount"
      />
    </div>

    <OrderViewModal
      v-model:isDialogVisible="viewModal"
      :order-data="orderData"
      :order-products="orderItems"
      :total-amount="amountForPrint"
      :selected-currency="currency"
      :payments="paymentsForPrint"
      :change-amount="changeAmountForPrint"
      :credit-amount="creditAmountForPrint"
      :credit="creditForPrint"
      @close="handleCloseViewModal"
      :is-special-taxpayer="isSpecialTaxpayer"
    />
  </div>
</template>

<style scoped>
.orders-window {
  overflow: visible;
}

.tab-with-badge .tab-count {
  font-size: 0.7rem;
  justify-content: center;
  font-weight: 600;
  min-inline-size: 1.5rem;
}

.custom-expansion-panel :deep(.v-expansion-panel-title) {
  padding-block: 0.75rem;
  padding-inline: 1.5rem;
}

.custom-expansion-panel :deep(.v-expansion-panel-text__wrapper) {
  padding-block: 0;
  padding-inline: 0;
}

.filter-search-input :deep(.v-field__input) {
  font-size: 0.8125rem !important;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.border-t.border-b {
  border-color: rgba(var(--v-border-color), 0.1) !important;
}

:deep(.quotation-table-premium) {
  .v-data-table-header th {
    background-color: #fff !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  }
}
</style>
