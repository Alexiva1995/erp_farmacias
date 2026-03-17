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
  },
);

watch(
  () => selectedCompany.value,
  (newVal) => {
    emit("company-discount-selected", newVal);
  },
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
  },
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
    props.selectedDisplayCurrency,
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
  if (product.pack_id) return 1;

  const globalPct = props.globalDiscount
    ? parseFloat(props.globalDiscount.percentage)
    : 0;
  const productPct = parseFloat(product.discount_percentage || 0);
  const bestPct = Math.max(globalPct, productPct);

  if (bestPct > 0) {
    return 1 - bestPct / 100;
  }
  return 1;
};

const getOriginalBasePrice = (product, currency) => {
  if (currency === "BS") {
    return (
      product.original_price_bs ??
      product.originalPriceBs ??
      product.price_bs ??
      0
    );
  }
  if (currency === "COP") {
    return (
      product.original_price_cop ??
      product.originalPriceCop ??
      product.price_cop ??
      0
    );
  }
  return (
    product.original_price_usd ??
    product.originalPrice ??
    product.basePrice ??
    product.price ??
    0
  );
};

// Precio unitario con descuento e IVA para mostrar "c/u"
const getPricePerUnit = (product, currency) => {
  let basePrice = 0;
  if (product.discountApplied) {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  } else if (activeDiscountDisplay.value != null && !product.pack_id) {
    basePrice =
      getOriginalBasePrice(product, currency) * getDiscountFactor(product);
  } else {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  }
  const taxRate = product.taxRate || 0;
  let price = basePrice * (1 + taxRate);
  if (currency === "COP") {
    price = roundUpToNearestHundred(price);
  }
  return price;
};

const getProductPriceSinIva = (product, currency) => {
  let basePrice = 0;
  if (product.discountApplied) {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  } else if (activeDiscountDisplay.value != null && !product.pack_id) {
    basePrice =
      getOriginalBasePrice(product, currency) * getDiscountFactor(product);
  } else {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  }
  let priceSinIva = basePrice * product.selectedQuantity;
  if (currency === "COP") {
    priceSinIva = roundUpToNearestHundred(priceSinIva);
  }
  return priceSinIva;
};

const getProductPriceOriginalSinIva = (product, currency) => {
  let basePrice = 0;
  const hasProductDiscount =
    product.discount_percentage > 0 ||
    (product.has_pack_discount && product.pack_id);
  const hasGlobalDiscount =
    activeDiscountDisplay.value != null &&
    !product.pack_id &&
    product.discount_type !== "expiration";
  if (hasProductDiscount || hasGlobalDiscount) {
    if (currency === "BS" || currency === "Bs") {
      basePrice =
        product.original_price_bs ||
        product.originalPriceBs ||
        product.price_bs ||
        0;
    } else if (currency === "COP") {
      basePrice =
        product.original_price_cop ||
        product.originalPriceCop ||
        product.price_cop ||
        0;
    } else {
      basePrice =
        product.original_price_usd ||
        product.originalPrice ||
        product.basePrice ||
        product.price ||
        0;
    }
  } else {
    if (currency === "BS")
      basePrice = product.price_bs || 0;
    else if (currency === "COP") basePrice = product.price_cop || 0;
    else basePrice = product.price || 0;
  }
  let priceSinIva = basePrice * product.selectedQuantity;
  if (currency === "COP") priceSinIva = roundUpToNearestHundred(priceSinIva);
  return priceSinIva;
};

const getProductPrice = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (product.discountApplied) {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  } else if (activeDiscountDisplay.value != null && !product.pack_id) {
    basePrice =
      getOriginalBasePrice(product, currency) * getDiscountFactor(product);
  } else {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  }
  let priceWithIva = basePrice * product.selectedQuantity * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const handleClickProductItem = (product) => {
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
          (p) => p.pack_id === product.pack_id,
        );
        packItems.forEach((item) => {
          emit("remove-item", item.product_id);
        });
        toast.success("Pack eliminado de la orden");
      }
    });
    return;
  }

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
};

const handleCancelarOrder = () => {
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

const handleReserveOrder = () => {
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
          `La cantidad de "${product.title}" se ha ajustado al stock máximo disponible: ${product.availableQuantity}.`,
        );
      }
    });
  },
  { deep: true },
);

watch(
  () => props.cliente,
  (newCliente, oldCliente) => {
    if (newCliente?.id === oldCliente?.id) return;
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
  { immediate: true },
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
  if (current && current.amount > 0) return current;
  return null;
});

const handleIncrement = (product) => {
  if (product.pack_id) {
    const packSimulado = {
      id: product.pack_id,
      pack_config: product.original_pack_config || null,
    };
    emit("add-pack", { pack: packSimulado, quantity: 1 });
  } else {
    emit("update-quantity", {
      productId: product.product_id,
      quantity: product.selectedQuantity + 1,
      orderDetailId: product.order_detail_id,
    });
  }
};

const handleDecrement = (product) => {
  if (product.selectedQuantity > 1 && !product.pack_id) {
    emit("update-quantity", {
      productId: product.product_id,
      quantity: product.selectedQuantity - 1,
      orderDetailId: product.order_detail_id,
    });
  }
};

const appliesSpecialTax = computed(() => {
  return (
    props.isSpecialTaxpayer &&
    (props.selectedDisplayCurrency === "USD" ||
      props.selectedDisplayCurrency === "COP")
  );
});

const specialTaxAmount = computed(() => {
  if (!appliesSpecialTax.value) return 0;
  let tax = props.totalOrderAmount * 0.03;
  if (props.selectedDisplayCurrency === "COP") {
    tax = Math.ceil(tax / 100) * 100;
  }
  return tax;
});

const getIva = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (product.discountApplied) {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  } else if (activeDiscountDisplay.value != null && !product.pack_id) {
    basePrice =
      getOriginalBasePrice(product, currency) * getDiscountFactor(product);
  } else {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : product.price || 0;
  }
  let ivaAmount = basePrice * product.selectedQuantity * taxRate;
  if (currency === "COP") {
    ivaAmount = roundUpToNearestHundred(ivaAmount);
  }
  return ivaAmount;
};
</script>

<template>
  <VCard variant="flat" border class="mb-6 rounded-xl overflow-hidden glass-card shadow-lg elevation-2">
    <!-- Header estilo Cotización -->
    <VCardItem class="py-4 px-6 border-b bg-surface">
      <div class="d-flex align-center flex-wrap gap-4">
        <div class="d-flex align-center">
          <VIcon icon="tabler-file-invoice" size="32" color="primary" class="me-3" />
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-950 text-high-emphasis leading-none mb-1 text-uppercase">
              Orden de Venta
            </h2>
            <div class="d-flex align-center">
              <span class="text-subtitle-2 font-weight-bold text-primary me-2">{{ clientName }}</span>
              <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">{{ Identidad }}</span>
            </div>
          </div>
        </div>

        <VSpacer />

        <div class="d-flex align-center gap-2">
          <VSelect
            :model-value="props.selectedDisplayCurrency"
            :items="availableCurrency"
            density="compact"
            variant="flat"
            bg-color="grey-lighten-4"
            hide-details
            style="inline-size: 80px"
            class="rounded-lg font-weight-black text-center"
            @update:model-value="selectCurrency"
          />
          <VBtn
            icon="tabler-trash"
            variant="tonal"
            color="error"
            size="small"
            class="rounded-lg"
            @click="handleCancelarOrder"
          />
        </div>
      </div>
    </VCardItem>

    <VCardText class="pa-6">
      <!-- Filtros y Selectores Staked -->
      <VRow>
        <VCol cols="12" md="6">
          <div class="d-flex flex-column gap-3">
            <AppTextField
              v-model="quotationId"
              placeholder="Escanear Código de Barra..."
              density="comfortable"
              variant="outlined"
              hide-details
              prepend-inner-icon="tabler-scan"
            >
              <template #append-inner>
                <VBtn
                  color="primary"
                  variant="flat"
                  size="small"
                  class="rounded-lg px-4"
                  :disabled="!quotationId"
                  @click="fetchQuotationProducts(quotationId)"
                >
                  <VIcon icon="tabler-shopping-cart" />
                  <span class="ms-1 font-weight-black">{{ totalSelectedQuantity }}</span>
                </VBtn>
              </template>
            </AppTextField>

            <AppTextField
              :model-value="props.searchQuery"
              placeholder="Cédula del Cliente..."
              density="comfortable"
              variant="outlined"
              hide-details
              prepend-inner-icon="tabler-id"
              @update:model-value="emit('update:searchQuery', $event)"
            >
              <template #append-inner>
                <VBtn icon="tabler-search" variant="tonal" color="primary" size="small" class="rounded-lg" />
              </template>
            </AppTextField>
          </div>
        </VCol>

        <VCol cols="12" md="6">
          <div class="d-flex flex-column gap-3">
            <VSelect
              :model-value="props.selectedDiscountType"
              :items="discountOptions"
              density="comfortable"
              variant="outlined"
              hide-details
              placeholder="Aplicar Descuento..."
              clearable
              class="rounded-lg font-weight-black"
              @update:model-value="emit('update:selectedDiscountType', $event)"
            />

            <VSelect
              v-if="props.selectedDiscountType === 'Empresa'"
              v-model="selectedCompany"
              :items="props.activeCompanyOffers"
              density="comfortable"
              variant="outlined"
              hide-details
              placeholder="Seleccione Empresa"
              item-title="title"
              item-value="value"
              clearable
              class="rounded-lg font-weight-black"
            />
            <VSelect
              v-if="props.selectedDiscountType === 'Medico'"
              v-model="selectedDoctor"
              :items="props.activeDoctorOffers"
              density="comfortable"
              variant="outlined"
              hide-details
              placeholder="Seleccione Médico"
              item-title="title"
              item-value="value"
              clearable
              class="rounded-lg font-weight-black"
            />
            <VFileInput
              v-if="props.selectedDiscountType === 'Recipe'"
              v-model="prescriptionFile"
              density="comfortable"
              variant="outlined"
              hide-details
              placeholder="Subir Imagen del Recipe"
              accept="image/*"
              prepend-icon=""
              append-inner-icon="tabler-upload"
              clearable
              class="rounded-lg font-weight-black"
            />
          </div>
        </VCol>
      </VRow>

      <!-- Lista de Productos Premium -->
      <div class="mt-6">
        <div v-if="props.orderProducts.length === 0" class="text-center py-10 text-disabled">
          <VIcon icon="tabler-shopping-cart-off" size="48" class="mb-2" />
          <div class="font-weight-bold uppercase">La orden está vacía</div>
        </div>
        
        <div v-else class="d-flex flex-column gap-3 overflow-y-auto" style="max-height: 400px; padding-right: 4px;">
          <div 
            v-for="(product, index) in props.orderProducts" 
            :key="product.id" 
            class="product-row pa-4 rounded-xl border elevation-1 bg-white transition-all shadow-sm d-flex align-center gap-4"
          >
            <!-- Cantidad Box -->
            <div class="d-flex flex-column align-center">
              <div class="quantity-display-box font-weight-950 text-primary mb-1">
                {{ product.selectedQuantity }}
              </div>
              <div class="d-flex gap-1" v-if="!product.pack_id">
                <VBtn icon="tabler-minus" size="20" variant="tonal" color="primary" class="rounded-sm" @click="handleDecrement(product)" :disabled="product.selectedQuantity <= 1" />
                <VBtn icon="tabler-plus" size="20" variant="tonal" color="primary" class="rounded-sm" @click="handleIncrement(product)" :disabled="product.selectedQuantity >= product.availableQuantity" />
              </div>
            </div>

            <!-- Información del Producto -->
            <div class="flex-grow-1 overflow-hidden">
              <h3 class="text-subtitle-2 font-weight-950 text-high-emphasis text-uppercase leading-tight mb-1 truncate">
                {{ product.title }}
                <VIcon v-if="product.pack_id" icon="tabler-lock" size="14" color="warning" class="ms-1" />
              </h3>
              <div class="text-super-xs text-disabled font-weight-black uppercase">
                {{ product.active_ingredient }} • {{ product.laboratory || 'GENÉRICO' }}
              </div>
              <div class="d-flex gap-1 mt-1">
                <template v-if="!product.pack_id">
                  <VChip v-if="product.discount_type === 'expiration' && product.discount_percentage > 0" color="error" size="x-small" variant="flat" class="font-weight-black uppercase">Expira</VChip>
                  <VChip v-else-if="product.discount_percentage > 0" color="success" size="x-small" variant="flat" class="font-weight-black uppercase">Oferta</VChip>
                </template>
              </div>
            </div>

            <!-- Precios -->
            <div class="text-right d-flex flex-column align-end" style="min-inline-size: 100px;">
              <span class="text-h6 font-weight-950 text-primary leading-tight">
                {{ formatCurrency(getProductPrice(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
              </span>
              <span class="text-super-xs text-disabled font-weight-black uppercase mt-1">
                {{ formatCurrency(getPricePerUnit(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }} c/u
              </span>
              <div v-if="getIva(product, props.selectedDisplayCurrency) > 0" class="text-super-xs text-success font-weight-black mt-1">
                IVA: {{ formatCurrency(getIva(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
              </div>
            </div>

            <!-- Acción Eliminar -->
            <VBtn 
              icon="tabler-x" 
              variant="tonal" 
              color="error" 
              size="small" 
              class="rounded-lg shadow-sm"
              @click="handleClickProductItem(product)"
            />
          </div>
        </div>
      </div>
    </VCardText>

    <VDivider />

    <!-- Recargo Especial -->
    <div v-if="appliesSpecialTax" class="pa-4 bg-error-lighten-5 d-flex justify-space-between align-center border-b">
        <span class="text-caption font-weight-black text-error uppercase">Recargo Sujeto Pasivo Especial (3%)</span>
        <span class="text-subtitle-2 font-weight-black text-error">
          {{ formatCurrency(specialTaxAmount, props.selectedDisplayCurrency) }}
        </span>
    </div>

    <!-- Footer Action Bar -->
    <VCardActions class="pa-6 bg-surface d-flex align-center flex-wrap gap-4">
      <div class="d-flex gap-2">
        <VBtn icon="tabler-printer" variant="tonal" color="secondary" size="large" class="rounded-xl" />
        <VBtn icon="tabler-copy" variant="tonal" color="secondary" size="large" class="rounded-xl" />
        <VBtn icon="tabler-brand-whatsapp" variant="tonal" color="secondary" size="large" class="rounded-xl" />
      </div>

      <VSpacer />

      <div class="d-flex align-center gap-6 flex-wrap justify-end flex-grow-1">
        <div class="d-flex flex-column align-end">
          <span class="text-super-xs font-weight-950 text-disabled uppercase letter-spacing-1 mb-1">Monto Total</span>
          <span class="text-h4 font-weight-950 text-success leading-none">
            {{ formattedTotalQuotation }}
          </span>
        </div>

        <div class="d-flex gap-2">
           <VBtn
            v-if="!props.orderReserved"
            color="warning"
            variant="tonal"
            height="56"
            class="rounded-xl font-weight-950 px-6"
            @click="handleReserveOrder"
          >
            <VIcon start icon="tabler-hourglass" />
            RESERVAR
          </VBtn>
          <VBtn
            v-if="props.orderReserved"
            color="warning"
            variant="flat"
            height="56"
            class="rounded-xl font-weight-950 px-6"
            @click="handleReserved"
          >
            <VIcon start icon="tabler-lock-check" />
            RESERVADA
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            height="56"
            class="rounded-xl font-weight-950 px-8 elevation-4"
            @click="handleCompleteOrder"
          >
            <VIcon start icon="tabler-circle-check" />
            FINALIZAR
          </VBtn>
        </div>
      </div>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.glass-card {
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 95%) !important;
}

.product-row {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-row:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.08) !important;
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.quantity-display-box {
  background: rgba(var(--v-theme-primary), 0.05);
  border: 1px solid rgba(var(--v-theme-primary), 0.1);
  border-radius: 8px;
  inline-size: 40px;
  block-size: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.leading-none {
  line-height: 1 !important;
}

.font-weight-950 {
  font-weight: 950 !important;
}

.truncate {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.shadow-inner {
  box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.05);
}

.gap-3 { gap: 12px !important; }
.gap-6 { gap: 24px !important; }
</style>
