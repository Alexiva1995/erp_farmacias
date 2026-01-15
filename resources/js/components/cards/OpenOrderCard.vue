<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import Swal from "sweetalert2";
import { computed, defineProps, ref, watch } from "vue";

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
  selectedDiscountType: {
    type: String,
    default: null,
  },
  activeDoctorOffers: {
    type: Array,
    default: () => [],
  },
  prescriptionDiscountPercentage: {
    type: Number,
    default: 0,
  },
  activeCompanyOffers: {
    type: Array,
    default: () => [],
  },
  companyDiscountTotal: {
    type: Number,
    default: 0,
  },
  doctorDiscountTotal: {
    type: Number,
    default: 0,
  },
  recipeDiscountTotal: {
    type: Number,
    default: 0,
  },
  expirationDiscountTotal: {
    type: Number,
    default: 0,
  },
  globalDiscount: {
    type: Object,
    default: () => null,
  },
  isSpecialTaxpayer: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:searchQuery",
  "currency-changed",
  "update-quantity",
  "remove-item",
  "cancelar-order",
  "open-buys-modal",
  "reserve-order",
  "add-quotation-products",
  "add-reserved-order",
  "update:selectedDiscountType",
  "doctor-discount-selected",
  "prescription-file-selected",
  "company-discount-selected",
  "add-pack",
]);

const discountOptions = computed(() => {
  const options = ["Medico", "Recipe"];
  if (
    props.cliente &&
    props.cliente.company_id !== null &&
    props.cliente.company_id !== undefined
  ) {
    options.unshift("Empresa");
  }
  return options;
});

const quotationId = ref("");
const selectedDoctor = ref(null);
const selectedCompany = ref(null);
const prescriptionFile = ref(null);

watch(
  () => selectedDoctor.value,
  (newVal) => {
    emit("doctor-discount-selected", newVal);
  }
);

watch(
  () => selectedCompany.value,
  (newVal) => {
    emit("company-discount-selected", newVal);
  }
);

watch(prescriptionFile, (newVal) => {
  emit("prescription-file-selected", newVal);
});

watch(
  () => props.selectedDiscountType,
  (newVal) => {
    if (newVal !== "Recipe") {
      prescriptionFile.value = null;
    }
    if (newVal !== "Empresa") {
      selectedCompany.value = null;
    }
    if (newVal !== "Medico") {
      selectedDoctor.value = null;
    }
  }
);

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
const chipColor = "primary";

const breakdownItems = computed(() => {
  let ivaAmount = props.totalIvaAmount;
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


  if (appliesSpecialTax.value) {
    amountToFormat += specialTaxAmount.value;
  }

  if (props.selectedDisplayCurrency === "COP") {
    amountToFormat = Math.ceil(amountToFormat / 100) * 100;
  }
  return formatCurrency(
    parseFloat(amountToFormat.toFixed(2)),
    props.selectedDisplayCurrency
  );
});

const selectCurrency = (currency) => {
  emit("currency-changed", currency);
};

const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.orderProducts.forEach((product) => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });

  return total;
});

const getDiscountFactor = (product) => {
  if (
    props.globalDiscount &&
    props.globalDiscount.percentage > 0 &&
    product.discount_type !== "expiration"
  ) {
    return 1 - props.globalDiscount.percentage / 100;
  }
  return 1;
};

const getProductPriceSinIva = (product, currency) => {
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }

  // Apply visual discount
  //basePrice = basePrice * getDiscountFactor(product);

  let priceSinIva = basePrice * product.selectedQuantity;
  if (currency === "COP") {
    priceSinIva = roundUpToNearestHundred(priceSinIva);
  }

  return priceSinIva;
};

const getProductPriceSinDescuento = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  
  // Si tiene descuento de pack, usar el precio original
  if (product.has_pack_discount && product.pack_id) {
    if (currency === "BS") {
      basePrice = product.original_price_bs || 0;
    } else if (currency === "COP") {
      basePrice = product.original_price_cop || 0;
    } else {
      basePrice = product.original_price_usd || 0;
    }
  } else {
    // Precio normal sin descuento
    if (currency === "BS") {
      basePrice = product.price_bs || 0;
    } else if (currency === "COP") {
      basePrice = product.price_cop || 0;
    } else {
      basePrice = product.price || 0;
    }
  }

  let priceWithIva = basePrice * product.selectedQuantity * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }

  return priceWithIva;
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

  // Apply visual discount
  if (activeDiscountDisplay.value != null) {
    basePrice = basePrice * getDiscountFactor(product);
  }

  let priceWithIva = basePrice * product.selectedQuantity * (1 + taxRate);
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

  // Apply visual discount
  basePrice = basePrice * getDiscountFactor(product);

  let Iva = basePrice * product.selectedQuantity * taxRate;
  if (currency === "COP") {
    Iva = roundUpToNearestHundred(Iva);
  }

  return Iva;
};

/*const handleClickProductItem = (product) => {
  if (product.selectedQuantity > 1) {
    emit("update-quantity", {
      productId: product.product_id,
      quantity: product.selectedQuantity - 1,
      orderDetailId: product.order_detail_id,
    });
  } else {
    emit("remove-item", product.product_id);
  }
};*/

const handleClickProductItem = (product) => {
  // 1. Verificar si el producto pertenece a un pack

  if (product.pack_id) {
    Swal.fire({
      title: "¿Eliminar pack completo?",
      text: "Este producto pertenece a un pack. Si continúas, se eliminarán todos los productos asociados a este pack de la orden.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Sí, eliminar pack",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        const packItems = props.orderProducts.filter(
          (p) => p.pack_id === product.pack_id
        );
        console.log(packItems);
        packItems.forEach((item) => {
          emit("remove-item", item.product_id);
        });

        toast.success("Pack eliminado de la orden");
      }
    });
    return;
  }

  // 2. Lógica normal para productos que NO son pack
  if (product.selectedQuantity > 1) {
    emit("update-quantity", {
      productId: product.product_id,
      quantity: product.selectedQuantity - 1,
      orderDetailId: product.order_detail_id,
    });
  } else {
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
        emit("remove-item", product.product_id);
      }
    });
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
  emit("add-reserved-order");
};

watch(
  () => props.orderProducts,
  (newProducts) => {
    newProducts.forEach((product) => {
      if (product.selectedQuantity > product.availableQuantity) {
        emit("update-quantity", {
          productId: product.product_id,
          quantity: product.availableQuantity,
        });
        toast.warning(
          `La cantidad de "${product.title}" se ha ajustado al stock máximo disponible: ${product.availableQuantity}.`
        );
      }
    });
  },
  { deep: true }
);

// Watcher para detectar si el cliente tiene empresa y autoseleccionar
watch(
  () => props.cliente,
  (newCliente, oldCliente) => {
    // Prevent reset if it's the same client (e.g. during reload/currency change)
    if (newCliente?.id === oldCliente?.id) {
      return;
    }

    if (
      newCliente &&
      newCliente.company_id !== null &&
      newCliente.company_id !== undefined
    ) {
      emit("update:selectedDiscountType", "Empresa");
      selectedCompany.value = newCliente.company_id;
    } else {
      selectedCompany.value = null;
    }
  },
  { immediate: true }
);

const formattedDiscounts = computed(() => {
  const currency = props.selectedDisplayCurrency;
  return {
    company: formatCurrency(props.companyDiscountTotal || 0, currency),
    doctor: formatCurrency(props.doctorDiscountTotal || 0, currency),
    recipe: formatCurrency(props.recipeDiscountTotal || 0, currency),
  };
});

const activeDiscountDisplay = computed(() => {
  const type = props.selectedDiscountType;
  const config = {
    Empresa: {
      label: "Descuento Empresa",
      amount: props.companyDiscountTotal,
      formatted: formattedDiscounts.value.company,
    },
    Medico: {
      label: "Descuento Médico",
      amount: props.doctorDiscountTotal,
      formatted: formattedDiscounts.value.doctor,
    },
    Recipe: {
      label: "Descuento Recipe",
      amount: props.recipeDiscountTotal,
      formatted: formattedDiscounts.value.recipe,
    },
  };
  const current = config[type];
  if (current && current.amount > 0) {
    return current;
  }
  return null;
});


const handleIncrement = (product) => {

 if (product.pack_id) {
    const packSimulado = {
      id: product.pack_id,
      pack_config: product.original_pack_config || null
    };
    emit('add-pack', { 
      pack: packSimulado, 
      quantity: 1 
    });
  }else {
    emit('update-quantity', {
      productId: product.product_id,
      quantity: product.selectedQuantity + 1,
      orderDetailId: product.order_detail_id,
    });
  }
};


const appliesSpecialTax = computed(() => {
  return props.isSpecialTaxpayer && (props.selectedDisplayCurrency === 'USD' || props.selectedDisplayCurrency === 'COP');
});

const specialTaxAmount = computed(() => {
  if (!appliesSpecialTax.value) return 0;
  let tax = props.totalOrderAmount * 0.03;
  if (props.selectedDisplayCurrency === "COP") {
    tax = Math.ceil(tax / 100) * 100;
  }
  return tax;
});


</script>

<template>
  <VCard class="mb-6">
    <VCardItem class="py-2">
      <VCardTitle class="text-h4">
        {{ clientName }} {{ Identidad }}
      </VCardTitle>
      <template #append>
        <div class="d-flex align-center">
          <VSelect
            :model-value="props.selectedDiscountType"
            :items="discountOptions"
            density="compact"
            variant="outlined"
            hide-details
            style="width: 140px"
            class="me-2"
            placeholder="Descuento"
            clearable
            @update:model-value="emit('update:selectedDiscountType', $event)"
          />
          <VSelect
            v-if="props.selectedDiscountType === 'Empresa'"
            v-model="selectedCompany"
            :items="props.activeCompanyOffers"
            density="compact"
            variant="outlined"
            hide-details
            style="width: 250px"
            class="me-2"
            placeholder="Seleccione Empresa"
            item-title="title"
            item-value="value"
            clearable
          />
          <VSelect
            v-if="props.selectedDiscountType === 'Medico'"
            v-model="selectedDoctor"
            :items="props.activeDoctorOffers"
            density="compact"
            variant="outlined"
            hide-details
            style="width: 250px"
            class="me-2"
            placeholder="Seleccione Médico"
            item-title="title"
            item-value="value"
            clearable
          />
          <VFileInput
            v-if="props.selectedDiscountType === 'Recipe'"
            v-model="prescriptionFile"
            density="compact"
            variant="outlined"
            hide-details
            style="width: 250px"
            class="me-2"
            placeholder="Subir Recipe"
            accept="image/*"
            prepend-icon=""
            append-inner-icon="tabler-upload"
            clearable
          />
          <span
            v-if="
              props.selectedDiscountType === 'Recipe' &&
              props.prescriptionDiscountPercentage > 0
            "
            class="text-body-2 text-success font-weight-bold"
            style="white-space: nowrap"
          >
            {{ props.prescriptionDiscountPercentage }}% Descuento
          </span>
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                type="button"
                variant="tonal"
                density="default"
                size="small"
                class="ms-2"
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
        </div>
      </template>
    </VCardItem>

    <VCardText class="py-2">
      <VRow>
        <VCol cols="12" md="6" class="d-flex align-center">
          <VCardItem class="py-2 px-0">
            <VCardTitle class="text-h6"> Productos </VCardTitle>
            <template #append>
              <VChip
                label
                :color="chipColor"
                variant="tonal"
                density="default"
                size="small"
                :draggable="false"
              >
                <span class="font-weight-medium">
                  {{ totalSelectedQuantity }}</span
                >
              </VChip>
            </template>
          </VCardItem>
        </VCol>
        <VCol cols="12" md="6" class="d-flex align-center">
          <VRow class="flex-grow-1">
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="quotationId"
                placeholder="ID de la cotización"
                clearable
                class="py-1"
              >
                <template #append-inner>
                  <VBtn
                    icon
                    variant="text"
                    color="primary"
                    size="small"
                    :disabled="!quotationId"
                    @click="fetchQuotationProducts(quotationId)"
                  >
                    <VIcon icon="tabler-plus" />
                  </VBtn>
                </template>
              </AppTextField>
            </VCol>
            <VCol cols="12" sm="6" class="d-flex justify-end">
              <AppTextField
                :model-value="props.searchQuery"
                placeholder="Código de Barra"
                clearable
                class="py-1"
                style="max-width: 240px"
                @update:model-value="emit('update:searchQuery', $event)"
              />
            </VCol>
          </VRow>
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />

    <VCardText class="py-2 bg-grey-lighten-4">
      <VTable density="compact" lines="none">
        <tbody>
          <tr v-for="(product, index) in props.orderProducts" :key="product.id">
            <td>
              <div class="d-flex flex-column">
                <span class="text-body-1 font-weight-medium text-high-emphasis">
                  {{ product.title }}
                  <VIcon
                    v-if="product.pack_id"
                    icon="tabler-lock"
                    size="x-small"
                    color="warning"
                    class="ms-1"
                    title="Pack Item (Cantidad Fija)"
                  />
                  <VChip
                    v-if="
                      product.discount_type === 'expiration' &&
                      product.discount_percentage > 0
                    "
                    color="error"
                    size="x-small"
                    class="ms-1"
                    label
                  >
                    Expira (-{{ product.discount_percentage }}%)
                  </VChip>

                  <!-- Badge para Descuentos Globales (Empresa, Médico, Recipe) -->
                  <VChip
                    v-if="
                      props.globalDiscount &&
                      product.discount_type !== 'expiration'
                    "
                    color="primary"
                    size="x-small"
                    class="ms-1"
                    label
                  >
                    {{ props.globalDiscount.label }} (-{{
                      props.globalDiscount.percentage
                    }}%)
                  </VChip>
                </span>
                <span class="text-sm text-disabled">
                  {{ product.active_ingredient }}
                  {{ product.laboratory ? `- ${product.laboratory}` : "" }}
                </span>
              </div>
            </td>
            <td>
              <div class="d-flex align-center">
                <VBtn
                  icon
                  size="x-small"
                  variant="text"
                  @click="handleClickProductItem(product)"
                >
                  <VIcon icon="tabler-minus" />
                </VBtn>
                <VTextField
                  v-model.number="product.selectedQuantity"
                  variant="outlined"
                  density="compact"
                  single-line
                  hide-details
                  class="mx-1"
                  style="width: 50px; text-align: center"
                  :disabled="!!product.pack_id"
                  @change="
                    $emit('update-quantity', {
                      productId: product.product_id,
                      quantity: product.selectedQuantity,
                      orderDetailId: product.order_detail_id,
                    })
                  "
                />
                <VBtn
                  icon
                  size="x-small"
                  variant="text"
                  @click="handleIncrement(product)"
                  :disabled="
                    product.selectedQuantity >= product.availableQuantity || !!product.pack_id
                  "
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
            </td>
            <td class="text-right">
              <div class="d-flex flex-column align-end me-4">
                <span
                  v-if="index === 0"
                  class="text-caption text-medium-emphasis"
                  >Precio</span
                >
                <span class="text-body-1 font-weight-regular">
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
            </td>
            <td class="text-right">
              <div class="d-flex flex-column align-end me-4">
                <span
                  v-if="index === 0"
                  class="text-caption text-medium-emphasis"
                  >IVA</span
                >
                <span class="text-body-1 font-weight-regular">
                  {{
                    formatCurrency(
                      getIva(product, props.selectedDisplayCurrency),
                      props.selectedDisplayCurrency
                    )
                  }}
                </span>
              </div>
            </td>

            <td class="text-right">
              <div class="d-flex flex-column align-end">
                <span
                  v-if="index === 0"
                  class="text-caption text-medium-emphasis"
                  >Total</span
                >
                <div class="d-flex align-center gap-1">
                  <!-- Mostrar precio original tachado si hay descuento global o descuento de pack -->
                  <span
                    v-if="activeDiscountDisplay || (product.has_pack_discount && product.pack_id)"
                    class="text-caption text-disabled text-decoration-line-through me-1 text-error"
                  >
                    {{
                      formatCurrency(
                        getProductPriceSinDescuento(
                          product,
                          props.selectedDisplayCurrency
                        ),
                        props.selectedDisplayCurrency
                      )
                    }}
                  </span>

                  <span class="text-body-1 font-weight-bold text-black">
                    {{
                      formatCurrency(
                        getProductPrice(product, props.selectedDisplayCurrency),
                        props.selectedDisplayCurrency
                      )
                    }}
                  </span>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
    <VDivider class="mt-auto" />

    <div v-if="activeDiscountDisplay">
      <VCardText class="py-2 bg-grey-lighten-4">
        <VTable density="compact" lines="none">
          <tbody>
            <tr>
              <td>
                <div class="d-flex flex-column">
                  <span
                    class="text-subtitle-1 me-2 text-error font-weight-medium"
                  >
                    {{ activeDiscountDisplay.label }}:
                  </span>
                </div>
              </td>
              <td><div class="d-flex align-center"></div></td>
              <td class="text-right"></td>
              <td class="text-right"></td>
              <td class="text-right">
                <div class="d-flex flex-column align-end">
                  <span class="text-body-1 font-weight-bold text-error">
                    - {{ activeDiscountDisplay.formatted }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
      <VDivider class="mt-auto" />
    </div>

    <div v-if="props.expirationDiscountTotal > 0">
      <VCardText class="py-2 bg-grey-lighten-4">
        <VTable density="compact" lines="none">
          <tbody>
            <tr>
              <td>
                <div class="d-flex flex-column">
                  <span
                    class="text-subtitle-1 me-2 text-error font-weight-medium"
                  >
                    Descuento por Vencimiento:
                  </span>
                </div>
              </td>
              <td><div class="d-flex align-center"></div></td>
              <td class="text-right"></td>
              <td class="text-right"></td>
              <td class="text-right">
                <div class="d-flex flex-column align-end">
                  <span class="text-body-1 font-weight-bold text-error">
                    -
                    {{
                      formatCurrency(
                        props.expirationDiscountTotal,
                        props.selectedDisplayCurrency
                      )
                    }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
      <VDivider class="mt-auto" />
    </div>

        <div v-if="appliesSpecialTax">
      <VCardText class="py-2 bg-grey-lighten-4">
        <VTable density="compact" lines="none">
          <tbody>
            <tr>
              <td>
                <div class="d-flex flex-column">
                  <span
                    class="text-subtitle-1 me-2 text-error font-weight-medium"
                  >
                    Incluye Recargo Sujeto Pasivo Especial (3%):
                  </span>
                </div>
              </td>
              <td><div class="d-flex align-center"></div></td>
              <td class="text-right"></td>
              <td class="text-right"></td>
              <td class="text-right">
                <div class="d-flex flex-column align-end">
                  <span class="text-body-1 font-weight-bold text-error">{{ formatCurrency(specialTaxAmount, props.selectedDisplayCurrency) }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
      <VDivider class="mt-auto" />
    </div>



    <VCardActions class="pa-4 d-flex flex-wrap justify-space-between">
      <div class="d-flex flex-wrap gap-4 flex-grow-1">
        <VBtn
          color="secondary"
          variant="outlined"
          class="flex-grow-1"
          @click="hadleCancelarOrder"
        >
          CANCELAR
        </VBtn>
        <VBtn
          v-if="!props.orderReserved"
          color="warning"
          variant="flat"
          class="flex-grow-1"
          @click="handleTReserveOrder"
        >
          RESERVAR
        </VBtn>
        <VBtn
          v-if="props.orderReserved"
          color="warning"
          variant="flat"
          class="flex-grow-1"
          @click="handleReserved"
        >
          RESERVADA
        </VBtn>
        <VBtn
          color="success"
          variant="flat"
          class="flex-grow-1"
          @click="handleCompleteOrder"
        >
          COMPLETAR
        </VBtn>
      </div>
      <div class="d-flex align-center">
        <h4 class="text-h4 me-2">Monto Total</h4>
        <span class="text-h4 text-success"> {{ formattedTotalQuotation }}</span>
      </div>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.v-table__wrapper > table > tbody > tr > td {
  border-bottom: none !important;
}
</style>
