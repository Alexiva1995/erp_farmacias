<script setup>
import OrderProductsTable from "@/components/OrderProductsTable.vue";
import OrderFilters from "@/components/OrderFilters.vue";
import OrderClienteCard from "@/components/cards/OrderClienteCard.vue";
import OpenOrderCard from "@/components/cards/OpenOrderCard.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { useAuthStore } from "@/stores/auth";

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);

const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);

const barcodeSearchQuery = ref("");
let barcodeInputTimer;
const BARCODE_LENGTH_THRESHOLD = 10;

const clientIdentification = ref("");
const showRegisterClientModal = ref(false);
const selectedClient = ref(null);
const isLoadingInitialOrder = ref(true);

const selectedDisplayCurrency = ref("COP");

const newClientFormData = ref({
  id: null,
  identification_type: "",
  identification: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  birthdate: "",
  company_id: null,
  address: "",
});

const newClientFormErrors = reactive({
  id: "",
  identification: "",
  identification_type: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  address: "",
  birthdate: "",
  company_id: "",
});

const companies = ref([]);

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const hasOpenOrder = ref(false);
const openOrderData = ref(null);

const orderItems = ref([]);

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: filterSearchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/tpv/order", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    filterSearchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

const consultAllcomapanies = async () => {
  const companiesResponse = await axios.get("/crm/companies");
  companies.value = companiesResponse.data.data;
};

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
  consultAllcomapanies();
});

const formatOrderItemForFrontend = (backendItem) => {
  const product = backendItem.product;
  const availableQuantity = product.lots_sum_quantity ?? 0;

  return {
    order_detail_id: backendItem.id,
    product_id: product.id,
    title: product.name,
    active_ingredient: product.active_ingredient,
    itemCode: product.barcode,
    price: parseFloat(product.sale_price) || 0,
    price_bs: parseFloat(product.price_bs) || 0,
    price_cop: parseFloat(product.price_cop) || 0,
    availableQuantity: parseInt(product.valid_stock_sum) || 0,
    selectedQuantity: parseInt(backendItem.quantity) || 0,
    laboratory: product.laboratory ? product.laboratory.name : "N/A",
    taxRate: product.iva == 1 ? 0.16 : 0,
  };
};

onMounted(async () => {
  try {
    const response = await axios.get("/tpv/order/seller/my-open-order");
    if (response.data.data && response.data.data.order) {
      openOrderData.value = response.data.data.order;
      selectedClient.value = response.data.data.order.client;
      hasOpenOrder.value = true;
      if (openOrderData.value.currency) {
        selectedDisplayCurrency.value =
          openOrderData.value.currency.toUpperCase();
      }
      if (response.data.data.order.details) {
        orderItems.value = response.data.data.order.details.map((item) =>
          formatOrderItemForFrontend(item)
        );
      } else {
        orderItems.value = [];
      }
    } else {
      hasOpenOrder.value = false;
      openOrderData.value = null;
      selectedClient.value = null;
      orderItems.value = [];
    }
  } catch (error) {
    console.error("Error al verificar orden abierta del vendedor:", error);
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
  } finally {
    isLoadingInitialOrder.value = false;
  }
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const addProductToQuotation = async ({ productId, quantity }) => {};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  }
);

watch(barcodeSearchQuery, (newValueBar) => {
  clearTimeout(barcodeInputTimer);
  if (!newValueBar) {
    return;
  }
  if (newValueBar.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToOrderByBarcode(newValueBar);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

const addProductToOrderByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;
    await addProductToOrder({ productId: productDetails.id, quantity: 1 });
  } catch (error) {
    console.error(
      "Error al agregar producto por código de barras:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Producto no encontrado o error al agregar por código de barras."
    );
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const verifyClient = async (identification) => {
  clientIdentification.value = identification;
  if (!identification) {
    toast.warning("Por favor, ingrese un número de identificación.");
    return;
  }
  try {
    const response = await axios.get(`/tpv/order/client/${identification}`);
    const responseData = response.data.data;
    if (responseData.found === false) {
      toast.info("Cliente no encontrado. Por favor, regístrelo.");
      newClientFormData.value = {
        ...newClientFormData.value,
        identification: identification,
      };
      showRegisterClientModal.value = true;
    } else {
      const clientData = response.data.data.client;
      selectedClient.value = clientData;
      toast.success(
        `Cliente ${clientData.name} ${clientData.last_name} encontrado.`
      );

      if (responseData.found_open_order) {
        hasOpenOrder.value = true;
        openOrderData.value = responseData.order;

        if (openOrderData.value.currency) {
          selectedDisplayCurrency.value =
            openOrderData.value.currency.toUpperCase();
        }
      } else {
        hasOpenOrder.value = false;
        openOrderData.value = null;
        addOrden(clientData.id);
      }
    }
  } catch (error) {
    console.error("Error al verificar cliente:", error);
    toast.error("Error al verificar el cliente.");
  }
};

const addOrden = async (id) => {
  const params = {
    client_id: id,
    seller_id: currentUser.value?.id || 3,
    currency: selectedDisplayCurrency.value,
  };
  try {
    const response = await axios.post("/tpv/orders", params);
    openOrderData.value = response.data.data.order;
    hasOpenOrder.value = true;
    toast.success("Orden creada exitosamente.");
  } catch (error) {
    console.error("Error al agregar la orden:", error);
    toast.error("Error al agregar la orden.");
  }
};

function cargarErrores(errores) {
  newClientFormErrors.id = errores.id ? errores.id.join(", ") : "";
  newClientFormErrors.identification = errores.identification
    ? errores.identification.join(", ")
    : "";
  newClientFormErrors.identification_type = errores.identification_type
    ? errores.identification_type.join(", ")
    : "";
  newClientFormErrors.name = errores.name ? errores.name.join(", ") : "";
  newClientFormErrors.last_name = errores.last_name
    ? errores.last_name.join(", ")
    : "";
  newClientFormErrors.email = errores.email ? errores.email.join(", ") : "";
  newClientFormErrors.phone = errores.phone ? errores.phone.join(", ") : "";
  newClientFormErrors.address = errores.address
    ? errores.address.join(", ")
    : "";
  newClientFormErrors.birthdate = errores.birthdate
    ? errores.birthdate.join(", ")
    : "";
  newClientFormErrors.company_id = errores.company_id
    ? errores.company_id.join(", ")
    : "";
}

const handleSaveNewClient = async (formData) => {
  try {
    let respuesApi = await axios.post("/crm/clients", formData);
    if (respuesApi.status == 200) {
      toast.success("El cliente se a guardado correctamente");
      handleCloseRegisterModal();
      addOrden(respuesApi.data.data.id);
    }
  } catch (error) {
    toast.error("Error al crear el cliente");
    console.log("error en el servidor => ", error);
    let errores = { ...error.response.data.data.errors };
    cargarErrores(errores);
  }
};

const handleCloseRegisterModal = () => {
  showRegisterClientModal.value = false;
  limpiarDatosFormulario();
  limpiarErroresFormulario();
};

function limpiarDatosFormulario() {
  newClientFormData.id = null;
  newClientFormData.identification = "";
  newClientFormData.identification_type = "";
  newClientFormData.name = "";
  newClientFormData.last_name = "";
  newClientFormData.email = "";
  newClientFormData.phone = "";
  newClientFormData.address = "";
  newClientFormData.birthdate = null;
  newClientFormData.company_id = "";
}

function limpiarErroresFormulario() {
  newClientFormErrors.id = "";
  newClientFormErrors.identification = "";
  newClientFormErrors.identification_type = "";
  newClientFormErrors.name = "";
  newClientFormErrors.last_name = "";
  newClientFormErrors.email = "";
  newClientFormErrors.phone = "";
  newClientFormErrors.address = "";
  newClientFormErrors.birthdate = "";
  newClientFormErrors.company_id = "";
}

const clearFormErrors = () => {
  newClientFormErrors.value = {};
};

const handleCurrencyChanged = (newCurrency) => {
  selectedDisplayCurrency.value = newCurrency;
};

const totalOrderAmount = computed(() => {
  return totalProductsAmount.value + totalIVAAmount.value;
});

const totalIVAAmount = computed(() => {
  let totalIVA = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    totalIVA += price * quantity * taxRate;
  });
  return totalIVA;
});

const totalProductsAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});

const totalAmountBs = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceBs = item.price_bs || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += basePriceBs * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountUsd = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceUsd = item.price || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += basePriceUsd * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountCop = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceCop = item.price_cop || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += basePriceCop * quantity * (1 + taxRate);
  });
  return total;
});

const updateOrderTotalsInBackend = async () => {
  if (!openOrderData.value || !openOrderData.value.id) {
    return;
  }
  try {
    const payload = {
      total_amount: totalOrderAmount.value,
      currency: selectedDisplayCurrency.value,
    };
    await axios.patch(`/tpv/orders/${openOrderData.value.id}`, payload);
  } catch (error) {
    toast.error("Error al actualizar los totales de la orden.");
  }
};

const updateOrderItemQuantity = async ({ productId, quantity }) => {
  if (quantity <= 0) {
    return;
  }

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para modificar productos.");
    return;
  }

  try {
    const currentItem = orderItems.value.find(
      (item) => item.product_id === productId
    );
    if (!currentItem) {
      toast.error(
        "Producto no encontrado en la orden para actualizar su cantidad."
      );
      return;
    }
    const payload = {
      product_id: productId,
      quantity: quantity,
      price_at_product: currentItem.orderPrice || currentItem.price,
      currency_at_order: selectedDisplayCurrency.value,
    };

    const backendResponse = await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload
    );
    const backendOrderItem = backendResponse.data.data.order_item;

    const existingItemIndex = orderItems.value.findIndex(
      (item) => item.product_id === backendOrderItem.product_id
    );
    if (existingItemIndex !== -1) {
      orderItems.value[existingItemIndex] =
        formatOrderItemForFrontend(backendOrderItem);
      toast.success(
        `Cantidad de "${orderItems.value[existingItemIndex].title}" actualizada a ${backendOrderItem.quantity}.`
      );
    } else {
      const itemToAdd = formatOrderItemForFrontend(backendOrderItem);
      orderItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la orden.`);
    }
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      "Error al actualizar el producto en la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
    if (
      error.response &&
      error.response.status === 400 &&
      error.response.data.data
    ) {
      const { available_stock, requested_quantity, product_name } =
        error.response.data.data;
      toast.error(
        `Stock insuficiente para "${product_name}". Disponible: ${available_stock}. Solicitado: ${requested_quantity}.`
      );
    }
  }
};

const addProductToOrder = async ({ productId, quantity }) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para agregar productos.");
    return;
  }

  try {
    const response = await axios.get(`/product/${productId}`);
    const productDetails = response.data;
    const availableQuantity = productDetails.valid_stock_sum;

    const currentItemInOrder = orderItems.value.find(
      (item) => item.product_id === productId
    );
    const currentQuantityInOrder = currentItemInOrder
      ? currentItemInOrder.selectedQuantity
      : 0;
    const newTotalQuantity = currentQuantityInOrder + quantity;

    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`
      );
      return;
    }

    const priceInSelectedCurrency = getItemPriceByCurrency(
      productDetails,
      selectedDisplayCurrency.value
    );
    const payload = {
      product_id: productDetails.id,
      quantity: newTotalQuantity,
      price_at_product: priceInSelectedCurrency,
      tax_rate_at_order: productDetails.iva == 1 ? 0.16 : 0,
      currency_at_order: selectedDisplayCurrency.value,
    };

    const backendResponse = await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload
    );
    const backendOrderItem = backendResponse.data.data.order_item;
    const existingItemIndex = orderItems.value.findIndex(
      (item) => item.product_id === backendOrderItem.product_id
    );

    if (existingItemIndex !== -1) {
      orderItems.value[existingItemIndex] =
        formatOrderItemForFrontend(backendOrderItem);
      toast.success(
        `Cantidad de "${productDetails.name}" incrementada a ${backendOrderItem.quantity}.`
      );
    } else {
      const itemToAdd = formatOrderItemForFrontend(backendOrderItem);
      orderItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la orden.`);
    }
  } catch (error) {
    console.error(
      "Error al obtener o agregar el producto a la orden:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al agregar el producto a la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
    if (
      error.response &&
      error.response.status === 400 &&
      error.response.data.data
    ) {
      const { available_stock, requested_quantity, product_name } =
        error.response.data.data;
      toast.error(
        `Stock insuficiente para "${product_name}". Disponible: ${available_stock}. Solicitado: ${requested_quantity}.`
      );
    }
  }
};

watch(
  [totalOrderAmount, selectedDisplayCurrency],
  async (newValue, oldValue) => {
    if (newValue[0] !== oldValue[0] || newValue[1] !== oldValue[1]) {
      if (hasOpenOrder.value && openOrderData.value?.id) {
        await updateOrderTotalsInBackend();
      }
    }
  },
  { deep: false }
);

const getItemPriceByCurrency = (item, currency) => {
  if (currency === "BS") {
    return item.price_bs || 0;
  } else if (currency === "COP") {
    return item.price_cop || 0;
  } else {
    return item.price || item.sale_price || 0;
  }
};

const removeOrderItem = async (productIdToRemove) => {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "¡Desea eliminar el producto!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {
      if (
        !hasOpenOrder.value ||
        !openOrderData.value ||
        !openOrderData.value.id
      ) {
        toast.error("No hay una orden abierta para eliminar productos.");
        return;
      }
      try {
        const itemToRemove = orderItems.value.find(
          (item) => item.product_id === productIdToRemove
        );
        if (!itemToRemove || !itemToRemove.order_detail_id) {
          toast.error(
            "No se encontró el detalle del producto en la orden para eliminar."
          );
          return;
        }

        await axios.delete(
          `/tpv/orders/${openOrderData.value.id}/items/${itemToRemove.order_detail_id}`
        );
        orderItems.value = orderItems.value.filter(
          (item) => item.product_id !== productIdToRemove
        );
        toast.success("Producto eliminado de la orden.");
      } catch (error) {
        toast.error("Error al eliminar el producto de la orden.");
      }
    }
  });
};

const cancelarOrder = async () => {
  try {
    await axios.patch(`/tpv/orders/${openOrderData.value.id}/abandon`);
    toast.success("Orden abandonada exitosamente.");
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
  } catch (error) {
    console.error(
      "Error al abandonar la orden:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al abandonar la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};
</script>
<template>
  <div>
    <div v-if="isLoadingInitialOrder">
      <p>Cargando sesión de orden...</p>
    </div>

    <div v-else-if="hasOpenOrder">
      <OpenOrderCard
        v-model:searchQuery="barcodeSearchQuery"
        :order-products="orderItems"
        :order="openOrderData"
        :total-products-amount="totalProductsAmount"
        :total-iva-amount="totalIVAAmount"
        :total-order-amount="totalOrderAmount"
        :cliente="selectedClient"
        :selected-display-currency="selectedDisplayCurrency"
        @currency-changed="handleCurrencyChanged"
        @update-quantity="updateOrderItemQuantity"
        @remove-item="removeOrderItem"
        @cancelar-order="cancelarOrder"
      />
    </div>
    <div v-else>
      <OrderClienteCard
        v-model="clientIdentification"
        @verify-client="verifyClient"
      />
    </div>

    <OrderFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
    >
    </OrderFilters>

    <OrderProductsTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @add-product="addProductToOrder"
    />

    <RegisterClientModal
      :companies="companies"
      :modalFormulario="showRegisterClientModal"
      titulo="Registrar Nuevo Cliente"
      :formData="newClientFormData"
      :formError="newClientFormErrors"
      @modalClose="handleCloseRegisterModal"
      @save="handleSaveNewClient"
      @clearErrorForm="clearFormErrors"
    />
  </div>
</template>
