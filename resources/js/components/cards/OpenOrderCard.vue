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
   orderReserved: {
    type: Object,
    default: null,
  },
});

const quotationId = ref("");

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
  "reserve-order",
  "add-quotation-products",
  "show-reserved-order"
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

const getProductPriceSinIva = (product, currency) => {
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }

  let priceSinIva = basePrice;
  if (currency === "COP") {
    priceSinIva = roundUpToNearestHundred(priceSinIva);
  }
  return priceSinIva;
};

const getProductPrice = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const getIva = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }
  let Iva = basePrice * taxRate;
  if (currency === "COP") {
    Iva = roundUpToNearestHundred(Iva);
  }
  return Iva;
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
  emit("open-buys-modal");
};

const handleTReserveOrder = () => {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "¡Desea Reservar la Orden!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {
      emit("reserve-order");
    }
  });
};

const fetchQuotationProducts = async (id) => {
  if (!id) return;
  try {
    const response = await axios.get(`/tpv/quotations/${id}/products`);
    const quotationData = response.data;
    emit("add-quotation-products", quotationData.products);
    toast.success("Productos de la cotización cargados exitosamente.");
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      "Error de red o cotización no encontrada.";
    toast.error(errorMessage);
    console.error("Error fetching quotation:", error);
  } finally {
    quotationId.value = "";
  }
};

const handleReserved = () => {
     emit('show-reserved-order');
};

</script>
<template>
  <VCard class="mb-6">
    <VCardItem style="padding-top: 5px;">
      <VCardTitle>{{ clientName }} {{ Identidad }}</VCardTitle>
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

    <VRow>
      <VCol cols="6">
        <VCardItem class="py-1">
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
      </VCol>
      <VCol cols="6">
        <VCardText class="py-1">
          <VRow>
            <VCol cols="6">
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
            </VCol>
            <VCol cols="6">
              <AppTextField
                :model-value="props.searchQuery"
                placeholder="Código de Barra"
                clearable
                @update:model-value="emit('update:searchQuery', $event)"
                class="flex-grow-1"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCol>
    </VRow>

    <VCardText>
      <div>
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
              >{{product.active_ingredient}} {{ product.laboratory ? `- ${product.laboratory}` : '' }}</VListItemSubtitle
            >

            <template #append>
              <div class="d-flex align-center">
                <div class="d-flex flex-column align-end me-4">
                  <span class="text-body-2 text-medium-emphasis">Precio</span>
                  <span class="text-body-1">
                    {{
                      formatCurrency(
                        getProductPriceSinIva(
                          product,
                          props.selectedDisplayCurrency
                        ),
                        props.selectedDisplayCurrency
                      )
                    }}
                  </span>
                </div>

                <div class="d-flex flex-column align-end me-4">
                  <span class="text-body-2 text-medium-emphasis">IVA</span>
                  <span class="text-body-1">
                    {{
                      formatCurrency(
                        getIva(product, props.selectedDisplayCurrency),
                        props.selectedDisplayCurrency
                      )
                    }}
                  </span>
                </div>

                <div class="d-flex flex-column align-end">
                  <span class="text-body-2 text-medium-emphasis">Total</span>
                  <span class="text-body-1 me-2 font-weight-bold">
                    {{
                      formatCurrency(
                        getProductPrice(product, props.selectedDisplayCurrency),
                        props.selectedDisplayCurrency
                      )
                    }}
                  </span>
                </div>
              </div>
            </template>
          </VListItem>
        </VList>
      </div>
    </VCardText>

    <VCardActions class="pa-6 d-flex flex-wrap justify-space-between">
      <VDivider class="mt-auto" />
      <h4 class="text-h4 text-center">Monto Total</h4>
      <div class="text-h4 text-success">
        {{ formattedTotalQuotation }}
      </div>
    </VCardActions>

    <VCardActions class="p-4 d-flex flex-wrap gap-4">
   
          <VBtn
            color="secondary"
            variant="outlined"
            @click="hadleCancelarOrder"
            >Cancelar</VBtn
          >

          <VBtn
            color="primary"
            variant="flat"
            @click="handleCompleteOrder"
         
            >Completar</VBtn
          >
          <VBtn
            color="success"
            variant="flat"
            @click="handleTReserveOrder"
        
            >Reservar</VBtn
          >
        <VSpacer />

        <VSpacer />
          <VBtn  v-if="props.orderReserved"
        color="primary"
        prepend-icon="tabler-arrow-back"
        @click="handleReserved"
      >
        Order Reservada
      </VBtn>
    </VCardActions>
  </VCard>
</template>
