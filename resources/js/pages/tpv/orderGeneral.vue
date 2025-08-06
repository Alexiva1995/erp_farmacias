<script setup>
import OrderTable from "@/components/OrderTable.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import QuotationFilters from "@/components/QuotationFilters.vue";
import OrderFiltersGeneral from "@/components/OrderFiltersGeneral.vue";
import OrderTicket from "@/components/OrderTicket.vue";

const ordersCompleted = ref([]);
const totalOrdersCompleted = ref(0);
const loadingOrdersCompleted = ref(false);

const pageOrdersCompleted = ref(1);
const itemsPerPageOrdersCompleted = ref(10);

const ordersAll = ref([]);
const totalOrdersAll = ref(0);
const loadingOrdersAll = ref(false);

const pageOrdersAll = ref(1);
const itemsPerPageOrdersAll = ref(10);
const sortByOrdersAll = ref();
const orderByOrdersAll = ref();

const isPrinting = ref(false);

const ordersAbandoned = ref([]);
const totalOrdersAbandoned = ref(0);
const loadingOrdersAbandoned = ref(false);

const pageOrdersAbandoned = ref(1);
const itemsPerPageOrdersAbandoned = ref(10);
const sortByOrdersAbandoned = ref();
const orderByOrdersAbandoned = ref();

const ordersCancelled = ref([]);
const totalOrdersCancelled = ref(0);
const loadingOrdersCancelled = ref(false);

const pageOrdersCancelled = ref(1);
const itemsPerPageOrdersCancelled = ref(10);
const sortByOrdersCancelled = ref();
const orderByOrdersCancelled = ref();

const currencyFilterCompleted = ref(null);
const filterSearchQueryCompleted = ref("");
const filterSearchQueryIdCompleted = ref("");

const currencyFilterAll = ref(null);
const filterSearchQueryAll = ref("");

const currencyFilterAbandoned = ref(null);
const filterSearchQueryAbandoned = ref("");

const currencyFilterCancelled = ref(null);
const filterSearchQueryCancelled = ref("");

const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const creditForPrint = ref(false);
const currency = ref("COP");
const orderData = ref(null);
const orderItems = ref([]);

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
    pageOrdersCompleted: pageOrdersCompleted.value,
    itemsPerPageOrdersCompleted: itemsPerPageOrdersCompleted.value,
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
    pageOrdersAll: pageOrdersAll.value,
    itemsPerPageOrdersAll: itemsPerPageOrdersAll.value,
    sortByOrdersAll: sortByOrdersAll.value,
    orderByOrdersAll: orderByOrdersAll.value,
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
    pageOrdersAbandoned: pageOrdersAbandoned.value,
    itemsPerPageOrdersAbandoned: itemsPerPageOrdersAbandoned.value,
    sortByOrdersAbandoned: sortByOrdersAbandoned.value,
    orderByOrdersAbandoned: orderByOrdersAbandoned.value,
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
    pageOrdersCancelled: pageOrdersCancelled.value,
    itemsPerPageOrdersCancelled: itemsPerPageOrdersCancelled.value,
    sortByOrdersCancelled: sortByOrdersCancelled.value,
    orderByOrdersCancelled: orderByOrdersCancelled.value,
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

onMounted(() => {
  fetchOrderCompleted();
  fetchOrderAll();
  fetchOrderAbandoned();
  fetchOrderCancelled();
});

let debounceTimer;
watch(
  [
    pageOrdersCompleted,
    itemsPerPageOrdersCompleted,
    currencyFilterCompleted,
    filterSearchQueryIdCompleted,
    filterSearchQueryCompleted,
    pageOrdersAll,
    itemsPerPageOrdersAll,
    sortByOrdersAll,
    orderByOrdersAll,
    pageOrdersAbandoned,
    itemsPerPageOrdersAbandoned,
    sortByOrdersAbandoned,
    orderByOrdersAbandoned,
    pageOrdersCancelled,
    itemsPerPageOrdersCancelled,
    sortByOrdersCancelled,
    orderByOrdersCancelled,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchOrderCompleted();
      fetchOrderAll();
      fetchOrderAbandoned();
      fetchOrderCancelled();
    }, 300);
  },
  { deep: true }
);

const handleClearFiltersCompleted = () => {
  filterSearchQueryIdCompleted.value = "";
  filterSearchQueryCompleted.value = "";
  currencyFilterCompleted.value = null;
};

watch([filterSearchQueryCompleted, currencyFilterCompleted], () => {
  pageOrdersCompleted.value = 1;
});

const updateTableOptionsOrdersCompleted = (options) => {
  pageOrdersCompleted.value = options.pageOrdersCompleted;
  itemsPerPageOrdersCompleted.value = options.itemsPerPageOrdersCompleted;
};

const updateTableOptionsOrdersAll = (options) => {
  pageOrdersAll.value = options.pageOrdersAll;
  itemsPerPageOrdersAll.value = options.itemsPerPageOrdersAll;
  //sortByOrdersAll.value = options.sortByOrdersAll[0]?.key;
  // orderByOrdersAll.value = options.orderByOrdersAll[0]?.order;
};

const updateTableOptionsOrdersAbandoned = (options) => {
  pageOrdersAbandoned.value = options.pageOrdersAbandoned;
  itemsPerPageOrdersAbandoned.value = options.itemsPerPageOrdersAbandoned;
  //sortByOrdersAbandoned.value = options.sortByOrdersAbandoned[0]?.key;
  // orderByOrdersAbandoned.value = options.orderByOrdersAbandoned[0]?.order;
};

const updateTableOptionsOrdersCancelled = (options) => {
  pageOrdersCancelled.value = options.pageOrdersCancelled;
  itemsPerPageOrdersCancelled.value = options.itemsPerPageOrdersCancelled;
  //sortByOrdersCancelled.value = options.sortByOrdersCancelled[0]?.key;
  // orderByOrdersCancelled.value = options.orderByOrdersCancelled[0]?.order;
};

const printOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    console.log(response);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
       orderItems.value = response.data.data.order.details.map(detail => ({
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: detail.product.iva,
        price_bs: detail.product.price_bs,
        price_cop: detail.product.price_cop,
        price: detail.product.price,
      }));
      paymentsForPrint.value = response.data.data.order.payment_methods;
      isPrinting.value = true;
      await nextTick();
      const printContents = document.getElementById("orderPrint");

      if (printContents) {
        const printWindow = window.open("", "", "height=600,width=800");
        printWindow.document.write("<html><head><title>Farmacia Barrio Sucre</title>");
        
        const styleSheets = document.styleSheets;
        for (let i = 0; i < styleSheets.length; i++) {
          const sheet = styleSheets[i];
          try {
            if (sheet.cssRules) {
              let cssText = '';
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
        console.warn("Elemento #orderPrint no encontrado para impresión tipo ticket. Imprimiendo toda la página.");
        window.print();
      }
    } else {
      console.error("Respuesta de API con formato incorrecto:", response.data);
      toast.error('La respuesta del servidor no tiene el formato esperado.');
    }
  } catch (error) {
    // Aquí se capturarán errores de red, 4xx, 5xx, y errores de sintaxis
    console.error("Error al imprimir la orden:", error.response?.data || error.message);
    const errorMessage = error.response?.data?.message || "Hubo un problema al imprimir la orden. Por favor, intente de nuevo.";
    toast.error(errorMessage);
    isPrinting.value = false;
    paymentsForPrint.value = [];
    orderData.value = null;
    orderItems.value = [];
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
      creditForPrint.value = false;
    }, 500);
  }
};

const myCalculatedTotal = computed(() => {
   return 0;
});
</script>
<template>
  <div>
    <OrderFiltersGeneral
      v-model:idSearchQuery="filterSearchQueryIdCompleted"
      v-model:searchQuery="filterSearchQueryCompleted"
      v-model:currencyFilter="currencyFilterCompleted"
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
      />
    </VCard>
    <div class="mb-5"></div>

    <QuotationFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:stockStatusFilter="stockStatusFilter"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
      @clear-sort="handleClearSortOrder"
    ></QuotationFilters>

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
      />
    </VCard>
    <div class="mb-5"></div>

    <QuotationFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
      @clear-sort="handleClearSortOrder"
    ></QuotationFilters>

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
      />
    </VCard>
    <div class="mb-5"></div>

    <QuotationFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
      @clear-sort="handleClearSortOrder"
    ></QuotationFilters>

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
        :total-amount="myCalculatedTotal"
        :selected-currency="currency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
      />
    </div>
  </div>
</template>
