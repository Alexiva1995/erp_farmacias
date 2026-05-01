<script setup>
import { ref } from "vue";
import ReturnsClientCard from "@/components/cards/ReturnsClientCard.vue";
import ReturnsOrderTable from "@/components/ReturnsOrderTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";

const clientIdentification = ref("");
const orders = ref([]);
const totalOrder = ref(0);
const loading = ref(false);
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [] });

const { isVendedor } = useAuthStore();

const fetchOrders = async () => {
  if (!clientIdentification.value) {
    orders.value = [];
    totalOrder.value = 0;
    return;
  }

  console.log("Iniciando búsqueda para:", clientIdentification.value);
  loading.value = true;
  try {
    const response = await axios.post("/tpv/returns/search-orders", {
      identification: clientIdentification.value,
      page: options.value.page,
      itemsPerPage: options.value.itemsPerPage,
      sortBy: options.value.sortBy[0]?.key,
      orderBy: options.value.sortBy[0]?.order,
    });

    console.log("Respuesta recibida:", response.data);
    orders.value = response.data.data;
    totalOrder.value = response.data.total;
  } catch (error) {
    const errMsg = error.response?.data?.error ?? error.response?.data?.message ?? error.message;
    console.error("Error al buscar pedidos:", errMsg);
    toast.error(errMsg || "Error al buscar los pedidos.");
  } finally {
    loading.value = false;
  }
};

const verifyClientOrder = (identification) => {
  clientIdentification.value = identification;
  if (!clientIdentification.value) {
    toast.warning(
      "Por favor, ingrese un número de identificación o N° de orden."
    );
    return;
  } else {
    options.value.page = 1;
    fetchOrders();
  }
};

const updateTableOptions = (newOptions) => {
  options.value = newOptions;
  fetchOrders();
};

const handleReturnProduct = async (items) => {
  const itemsArray = Array.isArray(items) ? items : [items];
  loading.value = true;
  try {
    const promises = itemsArray.map(({ product, order, returns_quantity }) =>
      axios.post("/tpv/returns/product", {
        product,
        order,
        returns_quantity,
      })
    );

    await Promise.all(promises);

    const msg =
      itemsArray.length === 1
        ? `Devolución de ${itemsArray[0].product.name} registrada. Pendiente de aprobación del supervisor.`
        : `${itemsArray.length} devoluciones registradas. Pendientes de aprobación del supervisor.`;

    toast.success(msg);
    handleClearSearch();
  } catch (error) {
    console.error("Error al devolver producto:", error.response?.data?.error);
    toast.error(error.response?.data?.error || "Error al devolver producto.");
  } finally {
    loading.value = false;
  }
};

const handleClearSearch = () => {
  clientIdentification.value = "";
  orders.value = [];
  totalOrder.value = 0;
};
</script>

<template>
  <ReturnsClientCard
    v-model="clientIdentification"
    @search-order="verifyClientOrder"
    @clear-search="handleClearSearch"
    @keyup.enter="fetchOrders"
  />
  <VAlert
    v-if="clientIdentification && !loading && totalOrder === 0"
    type="info"
    variant="tonal"
    closable
    class="mb-6"
  >
    No se encontraron órdenes completadas en las últimas 48 horas para "{{ clientIdentification }}". 
    Por favor, verifique la identificación (V/J/E), el N° de orden o si el pedido fue realizado hace más de 2 días.
  </VAlert>
  <ReturnsOrderTable
    :orders="orders"
    :loading="loading"
    :total-order="totalOrder"
    :items-per-page="options.itemsPerPage"
    :page="options.page"
    :is-vendedor="isVendedor"
    @update:options="updateTableOptions"
    @return-product="handleReturnProduct"
  />
</template>
