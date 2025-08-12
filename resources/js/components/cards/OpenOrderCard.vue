<script setup>
import { defineProps, computed, ref } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import Swal from "sweetalert2";
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";

const props = defineProps({
  orderProducts: {
    type: Array,
    default: () => [],
  },
  order: {
    type: Object,
    required: true,
  },
  cliente: {
    type: Object,
    required: false,
    default: null,
  },
  selectedDisplayCurrency: {
    type: String,
    default: "COP",
  },
  totalIvaAmount: {
    type: Number,
    default: 0,
  },
  totalProductsAmount: {
    type: Number,
    default: 0,
  },
  totalOrderAmount: {
    type: Number,
    default: 0,
  },
  searchQuery: {
    type: String,
    default: "",
  },
  totalAmountBs: {
    type: Number,
    default: 0,
  },
  totalAmountUsd: {
    type: Number,
    default: 0,
  },
  totalAmountCop: {
    type: Number,
    default: 0,
  },
});

const quotationId = ref('');

const clientName = computed(() => {
  return props.cliente
    ? `${props.cliente.name} ${props.cliente.last_name}`
    : "Cliente Desconocido";
});

const Identidad = computed(() => {
  return props.cliente
    ? `${props.cliente.identification_type} ${props.cliente.identification}`
    : "";
});

const availableCurrency = ref(["USD", "BS", "COP"]);
const emit = defineEmits([
  "update:searchQuery",
  "currency-changed",
  "update-quantity",
  "remove-item",
  "cancelar-order",
  "open-buys-modal",
  "add-quotation-products",
]);

const chipColor = "primary";

const breakdownItems = computed(() => {
  let ivaAmount = props.totalIvaAmount;

  // Aplica el redondeo solo si la moneda es 'COP'
  if (props.selectedDisplayCurrency === "COP") {
    ivaAmount = roundUpToNearestHundred(props.totalIvaAmount);
  }

  return [
    { title: "Precio por producto", amount: props.totalProductsAmount },
    { title: "IVA (16%)", amount: ivaAmount },
  ];
});

const formattedTotalQuotation = computed(() => {
  let amountToFormat = props.totalOrderAmount;
  if (props.selectedDisplayCurrency === "COP") {
    amountToFormat = Math.ceil(amountToFormat / 100) * 100;
  }
  return formatCurrency(amountToFormat, props.selectedDisplayCurrency);
});

const selectCurrency = (currency) => {
  emit("currency-changed", currency);
};

const totalSelectedQuantity = computed(() => {
  let total = 0;
  console.log(props.orderProducts);
  props.orderProducts.forEach((product) => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});

const getProductPrice = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    // Default to USD price
    basePrice = product.price || 0;
  }

  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const handleClickProductItem = (productId, currentQuantity) => {
  if (currentQuantity > 1) {
    emit("update-quantity", { productId, quantity: currentQuantity - 1 });
  } else {
    emit("remove-item", productId);
  }
};
const hadleCancelarOrder = () => {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "¡Desea Cancelar la Orden!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {
      emit("cancelar-order");
    }
  });
};

const handleCompleteOrder = () => {
  emit('open-buys-modal');
};


const fetchQuotationProducts = async (id) => {
    if (!id) return;
    try {
        const response = await axios.get(`/tpv/quotations/${id}/products`);
        const quotationData = response.data;
        emit('add-quotation-products', quotationData.products);
        toast.success('Productos de la cotización cargados exitosamente.');
    } catch (error) {
        const errorMessage = error.response?.data?.message || 'Error de red o cotización no encontrada.';
        toast.error(errorMessage);
        console.error('Error fetching quotation:', error);
    } finally {
        quotationId.value = '';
    }
};

</script>
<template>
  <VCard class="mb-6">
    <template #title>
      <span>Cliente: {{ clientName }}</span
      ><br />
      <span>Identidad: {{ Identidad }}</span>
    </template>
    <VRow>
      <VCol cols="6">
        <VCardItem>
          <VCardTitle>Factura</VCardTitle>
          <template #append>
            <VMenu>
              <template #activator="{ props: menuProps }">
                <VBtn
                  type="button"
                  color="primary"
                  variant="tonal"
                  density="default"
                  size="small"
                  class="mx-auto"
                  v-bind="menuProps"
                >
                  <span>{{ props.selectedDisplayCurrency }}</span>

                  <template #append>
                    <VIcon icon="tabler-chevron-down" size="16" />
                  </template>
                </VBtn>
              </template>

              <VList>
                <VListItem
                  v-for="currencyOption in availableCurrency"
                  :key="currencyOption"
                  :value="currencyOption"
                  @click="selectCurrency(currencyOption)"
                >
                  <VListItemTitle>{{ currencyOption }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </template>
        </VCardItem>

        <VCardText class="flex-grow-1 d-flex flex-column mt-6">
          <VList class="card-list mb-6" density="compact" nav>
            <VListItem
              v-for="item in breakdownItems"
              :key="item.title"
              class="rounded-0"
            >
              <VListItemTitle class="font-weight-medium">{{
                item.title
              }}</VListItemTitle>
              <template #append>
                <div class="d-flex align-center">
                  <span class="me-3 text-medium-emphasis">{{
                    formatCurrency(item.amount, props.selectedDisplayCurrency)
                  }}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </VCardText>
         <VCardActions class="pa-6 d-flex flex-wrap justify-space-between">
          <VDivider class="mt-auto" />
            <h4 class="text-h4 text-center">Monto Total</h4>
            <div class="text-h4 text-success">
              {{ formattedTotalQuotation }}
            </div>
        </VCardActions>
      </VCol>

      <VCol cols="6">
        <VCardItem>
          <VCardTitle>Productos</VCardTitle>
          <template #append>
            <VChip
              label
              :color="chipColor"
              variant="tonal"
              density="default"
              size="small"
              draggable="false"
              class="ms-auto"
            >
              <span class="font-weight-medium">{{
                totalSelectedQuantity
              }}</span>
            </VChip>
          </template>
        </VCardItem>

        <VCardText>
         <AppTextField
            v-model="quotationId"
            placeholder="ID de la cotización"
            clearable
            class="flex-grow-1 mb-2"
          >
          <template #append-inner>
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                @click="fetchQuotationProducts(quotationId)"
                :disabled="!quotationId"
              >
                <VIcon icon="tabler-plus" />
              </VBtn>
            </template>
            </AppTextField>
          <VDivider class="mt-auto" />
        

          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Código de Barra"
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
            class="flex-grow-1"
          />
          <VDivider class="mt-auto" />
        </VCardText>

        <VCardText>
          <div
            class="scrollable-list-container"
            :class="{ 'show-scroll': props.orderProducts.length > 2 }"
          >
            <VList class="card-list" density="compact" nav>
              <VListItem
                v-for="product in props.orderProducts"
                :key="product.id"
                class="rounded-0"
                @click="
                  handleClickProductItem(
                    product.product_id,
                    product.selectedQuantity
                  )
                "
                :class="{ 'cursor-pointer': true }"
              >
                <template #prepend>
                  <span>{{ product.selectedQuantity }} x</span>
                </template>

                <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                  product.title
                }}</VListItemTitle>
                <VListItemSubtitle class="mx-2"
                  >{{ product.active_ingredient }}
                  {{ product.laboratory }}</VListItemSubtitle
                >

                <template #append>
                  <div class="d-flex align-center">
                    <span class="text-body-1 me-2">{{
                      formatCurrency(
                        getProductPrice(
                          product,
                          props.selectedDisplayCurrency
                        ) * product.selectedQuantity,
                        props.selectedDisplayCurrency
                      )
                    }}</span>
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>
        </VCardText>

        <VCardActions class="pa-4 d-flex flex-wrap justify-space-between">
          <VBtn
            color="secondary"
            variant="outlined"
            @click="hadleCancelarOrder"
            class="flex-grow-1"
            >Cancelar</VBtn
          >
          <VBtn color="primary" variant="flat" @click="handleCompleteOrder" class="flex-grow-1"
            >Completar</VBtn
          >
        </VCardActions>
      </VCol>
    </VRow>
  </VCard>
</template>
<style scoped>
.scrollable-list-container {
  max-height: 95px;
  overflow-y: hidden;
  transition: overflow-y 0.3s ease-in-out;
}
.scrollable-list-container.show-scroll {
  overflow-y: auto;
}
</style>
