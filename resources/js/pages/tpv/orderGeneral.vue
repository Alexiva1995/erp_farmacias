<script setup>
import OrderFiltersGeneral from "@/components/OrderFiltersGeneral.vue";
import OrderTable from "@/components/OrderTable.vue";
import OrderTicket from "@/components/OrderTicket.vue";
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const ordersCompleted = ref([]);
const totalOrdersCompleted = ref(0);
const loadingOrdersCompleted = ref(false);

const pageOrdersCompleted = ref(1);
const itemsPerPageOrdersCompleted = ref(10);
const sortByOrdersCompleted = ref();
const orderByOrdersCompleted = ref();

const ordersAll = ref([]);
const totalOrdersAll = ref(0);
const loadingOrdersAll = ref(false);

const pageOrdersAll = ref(1);
const itemsPerPageOrdersAll = ref(10);
const sortByOrdersAll = ref();
const orderByOrdersAll = ref();
const startDateFilterAll = ref(null);
const endDateFilterAll = ref(null);

const isPrinting = ref(false);

const ordersAbandoned = ref([]);
const totalOrdersAbandoned = ref(0);
const loadingOrdersAbandoned = ref(false);

const pageOrdersAbandoned = ref(1);
const itemsPerPageOrdersAbandoned = ref(10);
const sortByOrdersAbandoned = ref();
const orderByOrdersAbandoned = ref();
const startDateFilterAbandoned = ref(null);
const endDateFilterAbandoned = ref(null);

const ordersCancelled = ref([]);
const totalOrdersCancelled = ref(0);
const loadingOrdersCancelled = ref(false);

const pageOrdersCancelled = ref(1);
const itemsPerPageOrdersCancelled = ref(10);
const sortByOrdersCancelled = ref();
const orderByOrdersCancelled = ref();
const startDateFilterCancelled = ref(null);
const endDateFilterCancelled = ref(null);

const currencyFilterCompleted = ref(null);
const filterSearchQueryCompleted = ref("");
const filterSearchQueryIdCompleted = ref("");
const offerCompleted = ref("");

const currencyFilterAll = ref(null);
const filterSearchQueryAll = ref("");
const filterSearchQueryIdAll = ref("");
const stateFilterAll = ref(null);

const filterSearchQueryIdAbandoned = ref("");
const currencyFilterAbandoned = ref(null);
const filterSearchQueryAbandoned = ref("");

const filterSearchQueryIdCancelled = ref("");
const currencyFilterCancelled = ref(null);
const filterSearchQueryCancelled = ref("");

const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const amountForPrint = ref(0);
const creditForPrint = ref(false);
const currency = ref("COP");
const orderData = ref(null);
const orderItems = ref([]);

const viewModal = ref(false);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Identificación", key: "identification", sortable: true },
  { title: "Cliente", key: "client_full_name", sortable: true },
  { title: "Vendedor", key: "seller.username", sortable: true },
  { title: "Monto", key: "total_amount", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const headersAll = [
  { title: "id", key: "id", sortable: true },
  { title: "Identificación", key: "identification", sortable: true },
  { title: "Cliente", key: "client_full_name", sortable: true },
  { title: "Vendedor", key: "seller.username", sortable: true },
  { title: "Monto", key: "total_amount", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const fetchOrderCompleted = async () => {
  loadingOrdersCompleted.value = true;
  const params = {
    id: filterSearchQueryIdCompleted.value,
    q: filterSearchQueryCompleted.value,
    ...(currencyFilterCompleted.value !== null && {
      currency: currencyFilterCompleted.value,
    }),
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
    ordersCompleted.value = response.data.data;
    totalOrdersCompleted.value = response.data.total;
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
    id: filterSearchQueryIdAll.value,
    q: filterSearchQueryAll.value,
    ...(currencyFilterAll.value !== null && {
      currency: currencyFilterAll.value,
    }),
    ...(stateFilterAll.value !== null && {
      state: stateFilterAll.value,
    }),
    ...(startDateFilterAll.value !== null && {
      start_date: startDateFilterAll.value,
    }),
    ...(endDateFilterAll.value !== null && {
      end_date: endDateFilterAll.value,
    }),
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
    ordersAll.value = response.data.data;
    totalOrdersAll.value = response.data.total;
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
    id: filterSearchQueryIdAbandoned.value,
    q: filterSearchQueryAbandoned.value,
    ...(currencyFilterAbandoned.value !== null && {
      currency: currencyFilterAbandoned.value,
    }),
    ...(startDateFilterAbandoned.value !== null && {
      start_date: startDateFilterAbandoned.value,
    }),
    ...(endDateFilterAbandoned.value !== null && {
      end_date: endDateFilterAbandoned.value,
    }),
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
    ordersAbandoned.value = response.data.data;
    totalOrdersAbandoned.value = response.data.total;
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
    id: filterSearchQueryIdCancelled.value,
    q: filterSearchQueryCancelled.value,
    ...(currencyFilterCancelled.value !== null && {
      currency: currencyFilterCancelled.value,
    }),
    ...(startDateFilterCancelled.value !== null && {
      start_date: startDateFilterCancelled.value,
    }),
    ...(endDateFilterCancelled.value !== null && {
      end_date: endDateFilterCancelled.value,
    }),
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
    ordersCancelled.value = response.data.data;
    totalOrdersCancelled.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las ordenes:", error);
    toast.error("Error al obtener las ordenes.");
  } finally {
    loadingOrdersCancelled.value = false;
  }
};

const storeSelectedOffer = (offer) => {
  localStorage.setItem("selected_offer", offer);
};

onMounted(() => {
  fetchOrderCompleted();
  fetchOrderAll();
  fetchOrderAbandoned();
  fetchOrderCancelled();
});

let debounceTimerCompleted;
watch(
  [
    pageOrdersCompleted,
    itemsPerPageOrdersCompleted,
    currencyFilterCompleted,
    filterSearchQueryIdCompleted,
    filterSearchQueryCompleted,
    sortByOrdersCompleted,
    orderByOrdersCompleted,
  ],
  () => {
    clearTimeout(debounceTimerCompleted);
    debounceTimerCompleted = setTimeout(() => {
      fetchOrderCompleted();
    }, 300);
  },
  { deep: true }
);

let debounceTimerAll;
watch(
  [
    pageOrdersAll,
    itemsPerPageOrdersAll,
    currencyFilterAll,
    stateFilterAll,
    filterSearchQueryIdAll,
    filterSearchQueryAll,
    sortByOrdersAll,
    orderByOrdersAll,
    startDateFilterAll,
    endDateFilterAll,
  ],
  () => {
    clearTimeout(debounceTimerAll);
    debounceTimerAll = setTimeout(() => {
      fetchOrderAll();
    }, 300);
  },
  { deep: true }
);

let debounceTimerAbandoned;
watch(
  [
    pageOrdersAbandoned,
    itemsPerPageOrdersAbandoned,
    currencyFilterAbandoned,
    filterSearchQueryIdAbandoned,
    filterSearchQueryAbandoned,
    sortByOrdersAbandoned,
    orderByOrdersAbandoned,
    startDateFilterAbandoned,
    endDateFilterAbandoned,
  ],
  () => {
    clearTimeout(debounceTimerAbandoned);
    debounceTimerAbandoned = setTimeout(() => {
      fetchOrderAbandoned();
    }, 300);
  },
  { deep: true }
);

let debounceTimerCancelled;
watch(
  [
    pageOrdersCancelled,
    itemsPerPageOrdersCancelled,
    currencyFilterCancelled,
    filterSearchQueryIdCancelled,
    filterSearchQueryCancelled,
    sortByOrdersCancelled,
    orderByOrdersCancelled,
    startDateFilterCancelled,
    endDateFilterCancelled,
  ],
  () => {
    clearTimeout(debounceTimerCancelled);
    debounceTimerCancelled = setTimeout(() => {
      fetchOrderCancelled();
    }, 300);
  },
  { deep: true }
);

const handleClearFiltersCompleted = () => {
  filterSearchQueryIdCompleted.value = "";
  filterSearchQueryCompleted.value = "";
  currencyFilterCompleted.value = null;
  sortByOrdersCompleted.value = undefined;
  orderByOrdersCompleted.value = undefined;
};

const handleClearFiltersAll = () => {
  filterSearchQueryIdAll.value = "";
  filterSearchQueryAll.value = "";
  currencyFilterAll.value = null;
  stateFilterAll.value = null;
  sortByOrdersAll.value = undefined;
  orderByOrdersAll.value = undefined;
  startDateFilterAll.value = null;
  endDateFilterAll.value = null;
};

const handleClearFiltersAbandoned = () => {
  filterSearchQueryIdAbandoned.value = "";
  filterSearchQueryAbandoned.value = "";
  currencyFilterAbandoned.value = null;
  sortByOrdersAbandoned.value = undefined;
  orderByOrdersAbandoned.value = undefined;
  startDateFilterAbandoned.value = null;
  endDateFilterAbandoned.value = null;
};

const handleClearFiltersCancelled = () => {
  filterSearchQueryIdCancelled.value = "";
  filterSearchQueryCancelled.value = "";
  currencyFilterCancelled.value = null;
  sortByOrdersCancelled.value = undefined;
  orderByOrdersCancelled.value = undefined;
  startDateFilterCancelled.value = null;
  endDateFilterCancelled.value = null;
};

watch([filterSearchQueryCompleted, currencyFilterCompleted], () => {
  pageOrdersCompleted.value = 1;
});

watch([filterSearchQueryAll, currencyFilterAll, stateFilterAll], () => {
  pageOrdersAll.value = 1;
});

watch([filterSearchQueryAbandoned, currencyFilterAbandoned], () => {
  pageOrdersAbandoned.value = 1;
});

watch([filterSearchQueryCancelled, currencyFilterCancelled], () => {
  pageOrdersCancelled.value = 1;
});

watch(
  () => offerCompleted.value,
  (offer) => {
    storeSelectedOffer(offer);
  }
);

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

const printOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
      orderItems.value = response.data.data.order.details.map((detail) => ({
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: detail.product.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
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
      console.log(response.data.data);
      console.log(creditAmountForPrint.value);
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
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: detail.product.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
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
</script>
<template>
  <div>
    <OrderFiltersGeneral
      v-model:idSearchQuery="filterSearchQueryIdCompleted"
      v-model:searchQuery="filterSearchQueryCompleted"
      v-model:currencyFilter="currencyFilterCompleted"
      v-model:offer="offerCompleted"
      @clear="handleClearFiltersCompleted"
    ></OrderFiltersGeneral>

    <VCard title="Órdenes Completadas">
      <div class="mb-2"></div>
      <OrderTable
        :orders="ordersCompleted"
        :loading="loadingOrdersCompleted"
        :total-orders="totalOrdersCompleted"
        :items-per-page="itemsPerPageOrdersCompleted"
        :page="pageOrdersCompleted"
        :headers="headers"
        @update:options="updateTableOptionsOrdersCompleted"
        @print-order="printOrder"
        @view-order="handleViewOrder"
      />
    </VCard>
    <div class="mb-5"></div>

    <OrderFiltersGeneral
      v-model:idSearchQuery="filterSearchQueryIdAll"
      v-model:searchQuery="filterSearchQueryAll"
      v-model:currencyFilter="currencyFilterAll"
      v-model:startDate="startDateFilterAll"
      v-model:endDate="endDateFilterAll"
      v-model:stateFilter="stateFilterAll"
      @clear="handleClearFiltersAll"
      :showDateFilters="true"
      :showStateFilters="true"
    ></OrderFiltersGeneral>

    <VCard title="Todas las Órdenes">
      <div class="mb-2"></div>
      <OrderTable
        :orders="ordersAll"
        :loading="loadingOrdersAll"
        :total-orders="totalOrdersAll"
        :items-per-page="itemsPerPageOrdersAll"
        :page="pageOrdersAll"
        :headers="headersAll"
        @update:options="updateTableOptionsOrdersAll"
        @print-order="printOrder"
        @view-order="handleViewOrder"
      />
    </VCard>
    <div class="mb-5"></div>

    <OrderFiltersGeneral
      v-model:idSearchQuery="filterSearchQueryIdCancelled"
      v-model:searchQuery="filterSearchQueryCancelled"
      v-model:currencyFilter="currencyFilterCancelled"
      @clear="handleClearFiltersCancelled"
      v-model:startDate="startDateFilterCancelled"
      v-model:endDate="endDateFilterCancelled"
      :showDateFilters="true"
    ></OrderFiltersGeneral>

    <VCard title="Órdenes Canceladas">
      <div class="mb-2"></div>
      <OrderTable
        :orders="ordersCancelled"
        :loading="loadingOrdersCancelled"
        :total-orders="totalOrdersCancelled"
        :items-per-page="itemsPerPageOrdersCancelled"
        :page="pageOrdersCancelled"
        :headers="headers"
        @update:options="updateTableOptionsOrdersCancelled"
        @print-order="printOrder"
        @view-order="handleViewOrder"
      />
    </VCard>
    <div class="mb-5"></div>

    <OrderFiltersGeneral
      v-model:idSearchQuery="filterSearchQueryIdAbandoned"
      v-model:searchQuery="filterSearchQueryAbandoned"
      v-model:currencyFilter="currencyFilterAbandoned"
      @clear="handleClearFiltersAbandoned"
      v-model:startDate="startDateFilterAbandoned"
      v-model:endDate="endDateFilterAbandoned"
      :showDateFilters="true"
    ></OrderFiltersGeneral>

    <VCard title="Órdenes Abandonadas">
      <div class="mb-2"></div>
      <OrderTable
        :orders="ordersAbandoned"
        :loading="loadingOrdersAbandoned"
        :total-orders="totalOrdersAbandoned"
        :items-per-page="itemsPerPageOrdersAbandoned"
        :page="pageOrdersAbandoned"
        :headers="headers"
        @update:options="updateTableOptionsOrdersAbandoned"
        @print-order="printOrder"
        @view-order="handleViewOrder"
      />
    </VCard>

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
    />
  </div>
</template>
