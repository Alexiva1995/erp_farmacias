<script setup>
import ReturnsClientCard from "@/components/cards/ReturnsClientCard.vue";
import ReturnsOrderTable from "@/components/ReturnsOrderTable.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";

const clientIdentification = ref("");
const orders = ref([]);
const totalOrder = ref(0);
const loading = ref(false);
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [] });


const fetchOrders = async () => {
  if (!clientIdentification.value) {
    orders.value = [];
    totalOrder.value = 0;
    return;
  }
  
  loading.value = true;
  try {
    const response = await axios.post('/tpv/returns/search-orders', {
      identification: clientIdentification.value,
      page: options.value.page,
      itemsPerPage: options.value.itemsPerPage,
      sortBy: options.value.sortBy[0]?.key,
      orderBy: options.value.sortBy[0]?.order,
    });
    
    orders.value = response.data.data;
    totalOrder.value = response.data.total;

  } catch (error) {
    console.error("Error al buscar pedidos:", error.response?.data?.error);
    toast.error(error.response?.data?.error || "Error al buscar los pedidos.");
  } finally {
    loading.value = false;
  }
};

const verifyClientOrder = (identification) => {
  clientIdentification.value = identification;
  if (!clientIdentification.value) {
    toast.warning("Por favor, ingrese un número de identificación.");
    return;
  }else{
    options.value.page = 1;
  fetchOrders();
  }
};

const updateTableOptions = (newOptions) => {
    options.value = newOptions;
    fetchOrders();
};


const handleReturnProduct = async ({ product, order, returns_quantity }) =>  {
  try {
    const response = await axios.post('/tpv/returns/product', {
      product: product,
      order: order,
      returns_quantity: returns_quantity,
    });

  toast.success(`Producto ${product.name} devuelto.`);

  } catch (error) {
    console.error("Error al devolver producto:", error.response?.data?.error);
    toast.error(error.response?.data?.error || "Error al devolver producto.");
  } 
};

</script>

<template>
 <ReturnsClientCard
        v-model="clientIdentification"
        @search-order="verifyClientOrder"
      />
      <ReturnsOrderTable
      :orders="orders"
      :loading="loading"
      :total-order="totalOrder"
      :items-per-page="options.itemsPerPage"
      :page="options.page"
      @update:options="updateTableOptions"
      @return-product="handleReturnProduct"
    />
</template>
