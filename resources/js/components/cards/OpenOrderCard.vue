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
  exchangeRates: {
    type: Object,
    default: () => ({}),
  },
  isRestaurant: {
    type: Boolean,
    default: false,
  },
  isSportsRental: {
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
  "edit-cliente",
  "add-note",
  "flash-checkout",
]);

const paymentMethodsByCurrency = {
  COP: [
    { label: 'Efectivo', value: 'cash' }
  ],
  USD: [
    { label: 'Efectivo', value: 'cash' },
    { label: 'Binance', value: 'binance' }
  ],
  BS: [
    { label: 'Pago Móvil', value: 'mobile_payment' }
  ]
};

const selectedPaymentMethod = ref('cash');

watch(() => props.selectedDisplayCurrency, (newCurrency) => {
  const methods = paymentMethodsByCurrency[newCurrency] || [];
  if (methods.length > 0) {
    selectedPaymentMethod.value = methods[0].value;
  } else {
    selectedPaymentMethod.value = 'cash';
  }
}, { immediate: true });

const handleFlashCheckout = () => {
  emit('flash-checkout', {
    method: selectedPaymentMethod.value,
    currency: props.selectedDisplayCurrency
  });
};

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

// Proxy para v-model:searchQuery del padre (usado por el scanner)
const internalSearchQuery = computed({
  get: () => props.searchQuery,
  set: (val) => emit("update:searchQuery", val),
});

const handleSearchAction = (value) => {
  if (!value) return;

  // Si parece un código de barras (longitud >= 10), el padre lo maneja vía watcher
  if (value.length >= 10) {
    // Solo nos aseguramos de que el padre reciba el valor más reciente
    // Aunque usualmente el v-model ya lo hace, esto previene desincronización
    internalSearchQuery.value = value;
    // No llamamos a cotizaciones para códigos de barras
    return;
  }

  // Si es corto, intentamos cargar como cotización
  fetchQuotationProducts(value);
};

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

const getEffectiveRate = (fromCurrency, toCurrency) => {
  if (fromCurrency === toCurrency) return 1;

  const rates = props.exchangeRates?.[fromCurrency];
  if (!rates) return 0;

  // REGLA NEGOCIO: Si convertimos de USD a COP, usar COPC (Tasa Manual) si existe
  if (fromCurrency === "USD" && toCurrency === "COP" && rates["COPC"]) {
    return rates["COPC"];
  }

  return rates[toCurrency] || 0;
};

const getDynamicPrice = (item, basePrice, targetCurrency) => {
  return parseFloat(basePrice) || 0;
};

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
  // Para USD, usar original_price_usd como fuente de verdad (product.price puede ser COP en canchas)
  const usdBase = product.original_price_usd ?? product.base_price ?? product.price ?? 0;
  let basePrice = 0;
  if (product.discountApplied) {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : getDynamicPrice(product, usdBase, "USD");
  } else if (activeDiscountDisplay.value != null && !product.pack_id) {
    basePrice =
      getOriginalBasePrice(product, currency) * getDiscountFactor(product);
  } else {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : getDynamicPrice(product, usdBase, "USD");
  }
  const taxRate = product.taxRate || 0;
  let price = basePrice * (1 + taxRate);
  if (currency === "COP") {
    price = roundUpToNearestHundred(price);
  }
  return price;
};

const getProductPriceSinIva = (product, currency) => {
  // Para USD, usar original_price_usd como fuente de verdad
  const usdBase = product.original_price_usd ?? product.base_price ?? product.price ?? 0;
  let basePrice = 0;
  if (product.discountApplied) {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : getDynamicPrice(product, usdBase, "USD");
  } else if (activeDiscountDisplay.value != null && !product.pack_id) {
    basePrice =
      getOriginalBasePrice(product, currency) * getDiscountFactor(product);
  } else {
    basePrice =
      currency === "BS"
        ? product.price_bs || 0
        : currency === "COP"
          ? product.price_cop || 0
          : getDynamicPrice(product, usdBase, "USD");
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
      basePrice = getDynamicPrice(
        product,
        product.original_price_usd ||
          product.originalPrice ||
          product.basePrice ||
          product.price ||
          0,
        "USD",
      );
    }
  } else {
    if (currency === "BS")
      basePrice = product.price_bs || 0;
    else if (currency === "COP") basePrice = product.price_cop || 0;
    else basePrice = getDynamicPrice(product, product.price, "USD");
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
    text: product.is_dish ? "¡Desea eliminar el plato!" : "¡Desea eliminar el producto!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {
      if (product.is_dish) {
        emit("remove-item", null, product.order_detail_id);
      } else {
        emit("remove-item", product.product_id);
      }
    }
  });
};
const handleAddNote = (product) => {
  Swal.fire({
    title: 'Anotaciones del Plato',
    input: 'text',
    inputLabel: 'Ej. Sin cebolla, sin salsa, etc.',
    inputValue: product.notes || '',
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    inputValidator: (value) => {
      // Opcional, permitimos nota vacía para borrar anotación
    }
  }).then((result) => {
    if (result.isConfirmed) {
      emit('add-note', {
        product: product,
        notes: result.value || ''
      });
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
      productId: product.is_dish ? null : product.product_id,
      quantity: product.selectedQuantity + 1,
      orderDetailId: product.order_detail_id,
    });
  }
};

const handleDecrement = (product) => {
  if (product.selectedQuantity > 1 && !product.pack_id) {
    emit("update-quantity", {
      productId: product.is_dish ? null : product.product_id,
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
  <VCard variant="flat" border class="mb-6 rounded-lg overflow-hidden elevation-1">
    <!-- Header estilo Cotización -->
    <VCardItem class="pa-3 border-b bg-surface">
      <div class="d-flex align-center justify-space-between w-100 gap-2 flex-wrap flex-sm-nowrap">
        <div class="d-flex align-center min-width-0 flex-grow-1">
          <VIcon icon="tabler-file-invoice" size="24" color="primary" class="me-2" />
          <div class="d-flex flex-column min-width-0">
            <h2 class="text-subtitle-1 font-weight-950 text-high-emphasis leading-none mb-1 text-uppercase truncate">
              Orden
            </h2>
            <div class="d-flex align-center gap-2">
              <span class="text-caption font-weight-bold text-primary truncate">{{ clientName }}</span>
              <VBtn
                v-if="props.cliente"
                icon="tabler-edit"
                variant="text"
                color="primary"
                size="x-small"
                class="ms-1"
                density="compact"
                @click="emit('edit-cliente', props.cliente)"
              >
                <VIcon size="14">tabler-edit</VIcon>
                <VTooltip activator="parent" location="top">Editar datos de cliente</VTooltip>
              </VBtn>
              <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">{{ Identidad }}</span>
            </div>
          </div>
        </div>

        <div class="d-flex align-center gap-1 gap-sm-2 flex-shrink-0 ms-auto">
          <!-- Selector de Descuento (VMenu Style) -->
          <VMenu v-if="!props.isSportsRental">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                variant="tonal"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black text-uppercase"
              >
                <span>
                  <span class="d-inline d-sm-none">{{ props.selectedDiscountType ? props.selectedDiscountType.substring(0, 3) : 'Desc' }}</span>
                  <span class="d-none d-sm-inline">{{ props.selectedDiscountType || 'Descuento' }}</span>
                </span>
                <VIcon end icon="tabler-chevron-down" size="14" />
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg shadow-lg">
              <VListItem
                v-for="option in discountOptions"
                :key="option"
                :value="option"
                :active="props.selectedDiscountType === option"
                color="primary"
                @click="emit('update:selectedDiscountType', option)"
              >
                <VListItemTitle class="font-weight-bold text-uppercase text-caption">{{ option }}</VListItemTitle>
              </VListItem>
              <VDivider v-if="props.selectedDiscountType" />
              <VListItem v-if="props.selectedDiscountType" color="error" @click="emit('update:selectedDiscountType', null)">
                <VListItemTitle class="font-weight-bold text-uppercase text-caption text-error">Quitar</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Métodos de Pago Rápidos para la moneda activa -->
          <div class="d-flex align-center gap-1 border rounded-lg pa-1 bg-white shadow-sm me-1 me-sm-2 flex-wrap">
            <span class="text-super-xs font-weight-black text-uppercase text-disabled px-1">Pago:</span>
            <VBtn
              v-for="method in (paymentMethodsByCurrency[props.selectedDisplayCurrency] || [])"
              :key="method.value"
              :color="selectedPaymentMethod === method.value ? 'primary' : 'grey-lighten-3'"
              :variant="selectedPaymentMethod === method.value ? 'flat' : 'text'"
              size="x-small"
              class="rounded-md font-weight-black text-uppercase px-2"
              height="24"
              @click="selectedPaymentMethod = method.value"
            >
              {{ method.label }}
            </VBtn>
          </div>

          <!-- Selector de Moneda (VMenu Style) -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                variant="tonal"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black"
              >
                <span>{{ props.selectedDisplayCurrency }}</span>
                <VIcon end icon="tabler-chevron-down" size="14" />
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg shadow-lg">
              <VListItem
                v-for="currencyOption in availableCurrency"
                :key="currencyOption"
                :value="currencyOption"
                :active="props.selectedDisplayCurrency === currencyOption"
                color="primary"
                @click="selectCurrency(currencyOption)"
              >
                <VListItemTitle class="font-weight-bold text-caption">{{ currencyOption }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VBtn
            icon="tabler-trash"
            variant="tonal"
            color="error"
            size="x-small"
            @click="handleCancelarOrder"
          />
        </div>
      </div>
      
      <!-- Selectores de Médico / Empresa / Recipe (Minimalista) -->
      <VExpandTransition>
        <div v-if="props.selectedDiscountType" class="mt-2 pt-2 border-t px-1">
          <div class="d-flex align-center gap-3">
            <VIcon :icon="props.selectedDiscountType === 'Medico' ? 'tabler-stethoscope' : (props.selectedDiscountType === 'Empresa' ? 'tabler-building-hospital' : 'tabler-file-text')" size="18" color="primary" />
            
            <!-- Selector de Médico -->
            <VAutocomplete
              v-if="props.selectedDiscountType === 'Medico'"
              v-model="selectedDoctor"
              :items="props.activeDoctorOffers"
              item-title="title"
              item-value="value"
              placeholder="Seleccionar Médico..."
              density="compact"
              variant="flat"
              bg-color="grey-lighten-4"
              hide-details
              class="rounded-lg flex-grow-1 font-weight-bold uppercase text-caption"
            />

            <!-- Selector de Empresa -->
            <VAutocomplete
              v-if="props.selectedDiscountType === 'Empresa'"
              v-model="selectedCompany"
              :items="props.activeCompanyOffers"
              item-title="title"
              item-value="value"
              placeholder="Seleccionar Empresa..."
              density="compact"
              variant="flat"
              bg-color="grey-lighten-4"
              hide-details
              class="rounded-lg flex-grow-1 font-weight-bold uppercase text-caption"
            />

            <!-- Selector de Recipe -->
            <VFileInput
              v-if="props.selectedDiscountType === 'Recipe'"
              v-model="prescriptionFile"
              placeholder="Cargar Recipe..."
              density="compact"
              variant="flat"
              bg-color="grey-lighten-4"
              hide-details
              prepend-icon=""
              prepend-inner-icon="tabler-camera"
              class="rounded-lg flex-grow-1 font-weight-bold uppercase text-caption"
            />
          </div>
        </div>
      </VExpandTransition>
    </VCardItem>

    <VCardText class="pa-3">
      <!-- Barra de búsqueda compacta: Visible siempre -->
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-2 px-1">
         <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-list-details" color="primary" size="18" class="opacity-80" />
            <span class="text-caption font-weight-950 text-primary uppercase letter-spacing-1">Ítems</span>
            <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black px-2">{{ totalSelectedQuantity }}</VChip>
         </div>

         <!-- Buscador Refinado y Compacto (Ampliado para mejor visibilidad) -->
         <div class="d-flex align-center gap-2 flex-grow-1" style="min-inline-size: 280px; max-inline-size: 100%;">
            <AppTextField
              v-model="internalSearchQuery"
              placeholder="Escanear código o ingresar cotización..."
              density="compact"
              variant="flat"
              bg-color="grey-lighten-4"
              hide-details
              prepend-inner-icon="tabler-scan"
              class="rounded-lg custom-search-slim shadow-sm border flex-grow-1"
              autofocus
              @keyup.enter="handleSearchAction(internalSearchQuery)"
            >
              <template #append-inner>
                 <VBtn
                    icon="tabler-arrow-right"
                    variant="text"
                    color="primary"
                    size="x-small"
                    class="me-n1"
                    :disabled="!internalSearchQuery"
                    @click="handleSearchAction(internalSearchQuery)"
                 />
              </template>
            </AppTextField>
         </div>
      </div>

      <!-- Lista de Productos Premium (Minimalista) -->
      <div v-if="props.orderProducts.length === 0" class="text-center py-8 text-disabled bg-grey-lighten-5 rounded-xl border border-dashed mx-3">
        <VIcon icon="tabler-shopping-cart-off" size="48" class="mb-3 opacity-20" />
        <p class="text-subtitle-2 font-weight-950 uppercase opacity-60">La orden está vacía</p>
        <p class="text-super-xs">Use el buscador de productos o el escáner para comenzar su venta</p>
      </div>

      <div v-else class="mt-2">
        <!-- Lista de Productos Premium -->
        <div class="mt-2">
          <div class="d-flex flex-column gap-2 overflow-y-auto" style="max-block-size: 380px; padding-inline-end: 4px;">
            <div 
              v-for="(product, index) in props.orderProducts" 
              :key="product.id" 
              class="product-row pa-2 rounded-lg border bg-surface d-flex align-center gap-3"
            >
              <!-- Cantidad Selector Estilo Premium -->
              <div class="d-flex align-center gap-1 bg-grey-lighten-4 rounded-lg px-1 border" style="block-size: 36px;">
                <VBtn 
                  icon="tabler-minus" 
                  size="24" 
                  variant="text" 
                  color="primary" 
                  @click="handleDecrement(product)" 
                  :disabled="product.selectedQuantity <= 1 || !!product.pack_id" 
                />
                
                <div class="px-2 font-weight-950 text-primary text-body-2 min-width-24 text-center">
                  {{ product.selectedQuantity }}
                </div>

                <VBtn 
                  icon="tabler-plus" 
                  size="24" 
                  variant="text" 
                  color="primary" 
                  @click="handleIncrement(product)" 
                  :disabled="product.selectedQuantity >= product.availableQuantity || !!product.pack_id" 
                />
              </div>

              <!-- Información del Producto y Desglose Inline -->
              <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <h3 class="text-caption font-weight-950 text-high-emphasis text-uppercase leading-tight mb-0">
                    {{ (product.title || '').toUpperCase() }}
                  </h3>
                  <div 
                    class="d-flex align-center gap-1 text-super-xs flex-wrap"
                    style="white-space: pre-wrap;"
                  >
                    <span class="text-disabled">{{ product.active_ingredient || '—' }}</span>
                    <template v-if="!product.pack_id && !product.is_dish">
                      <span class="text-disabled">|</span>
                      <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                        {{ product.laboratory && product.laboratory !== 'N/A' ? product.laboratory : 'Genérico' }}
                      </span>
                    </template>
                  </div>

                  <!-- Botón de Anotación (Solo Platos) -->
                  <div v-if="product.is_dish" class="d-flex align-center ml-auto">
                    <VBtn
                      icon="tabler-note"
                      size="24"
                      variant="text"
                      :color="product.notes ? 'success' : 'grey'"
                      @click="handleAddNote(product)"
                      title="Agregar anotaciones"
                    />
                  </div>

                  <!-- Mostrar nota si existe -->
                  <div v-if="product.notes" class="text-super-xs font-weight-bold text-success w-100 mt-1 d-flex align-center gap-1">
                    <VIcon icon="tabler-message" size="12" />
                    <span>Nota: {{ product.notes }}</span>
                  </div>

                  <!-- Desglose de Precios Inline (Inmediatamente después del título) -->
                  <div class="d-none d-sm-flex align-center gap-1 flex-wrap w-100">
                    <div class="d-flex align-center gap-1 bg-grey-lighten-4 px-1 rounded border">
                       <span class="text-super-xs text-disabled font-weight-black uppercase">U:</span>
                       <span class="text-super-xs font-weight-black text-secondary">
                         {{ formatCurrency(getPricePerUnit(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
                       </span>
                    </div>
                    
                    <div class="d-flex align-center gap-1 bg-grey-lighten-4 px-1 rounded border">
                       <span class="text-super-xs text-disabled font-weight-black uppercase">S:</span>
                       <span class="text-super-xs font-weight-black text-high-emphasis">
                         {{ formatCurrency(getProductPriceSinIva(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
                       </span>
                    </div>

                    <div class="d-flex align-center gap-1 bg-grey-lighten-4 px-1 rounded border">
                       <span class="text-super-xs text-disabled font-weight-black uppercase">I:</span>
                       <span class="text-super-xs font-weight-black text-success">
                         {{ formatCurrency(getIva(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
                       </span>
                    </div>

                    <VChip v-if="product.discount_percentage > 0" color="success" size="x-small" variant="flat" class="text-super-xs px-1">-{{ product.discount_percentage }}%</VChip>
                  </div>
                </div>
              </div>

              <!-- Precio Total Ítem -->
              <div class="text-right d-flex flex-column align-end" style="min-inline-size: 100px;">
                <!-- Precio Tachado (Original) -->
                <span 
                  v-if="product.discount_percentage > 0 || activeDiscountDisplay" 
                  class="text-super-xs text-disabled font-weight-black text-decoration-line-through uppercase leading-none mb-1"
                >
                  {{ formatCurrency(getProductPriceOriginalSinIva(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
                </span>
                
                <!-- Precio Actual (Con Descuento + IVA) -->
                <span class="text-subtitle-2 font-weight-950 text-primary leading-tight">
                  {{ formatCurrency(getProductPrice(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }}
                </span>
              </div>

              <!-- Acción Eliminar -->
              <VBtn 
                icon="tabler-x" 
                variant="text" 
                color="error" 
                size="x-small" 
                class="opacity-60"
                @click="handleClickProductItem(product)"
              />
            </div>
          </div>
        </div>
      </div>
    </VCardText>

    <!-- Footer Unificado: Totales y Acciones -->
    <VCardText class="pa-3 bg-grey-lighten-5 border-t mt-4">
       <div class="d-flex align-center justify-space-between flex-wrap gap-2 px-2">
          <!-- Desglose Horizontal de Totales -->
          <div class="d-flex align-center gap-4 flex-grow-1 flex-wrap py-1">
             <!-- Subtotal -->
             <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase leading-none mb-1">Subtotal</span>
                <span class="text-caption font-weight-black text-high-emphasis leading-none">
                   {{ formatCurrency(props.totalProductsAmount, props.selectedDisplayCurrency) }}
                </span>
             </div>

             <!-- Descuento Activo -->
             <div v-if="activeDiscountDisplay" class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-error uppercase leading-none mb-1">{{ activeDiscountDisplay.label }}</span>
                <span class="text-caption font-weight-black text-error leading-none">
                   - {{ activeDiscountDisplay.formatted }}
                </span>
             </div>

             <!-- IVA -->
             <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase leading-none mb-1">IVA (16%)</span>
                <span class="text-caption font-weight-black text-success leading-none">
                   + {{ formatCurrency(props.totalIvaAmount, props.selectedDisplayCurrency) }}
                </span>
             </div>

             <VDivider vertical class="mx-2" style="block-size: 32px;" />

             <!-- TOTAL -->
             <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-primary uppercase leading-none mb-1">Total Final</span>
                <span class="text-h6 font-weight-950 text-primary leading-none">
                   {{ formattedTotalQuotation }}
                </span>
             </div>
          </div>
           
           <VSpacer />

           <!-- Acciones Rápidas (En la misma línea) -->
           <div class="d-flex align-center gap-2 mt-1">
              <template v-if="isRestaurant">
                <VBtn
                  color="primary"
                  variant="flat"
                  height="40"
                  min-inline-size="0"
                  class="rounded-lg font-weight-950 px-3 px-sm-6 elevation-2"
                  @click="handleReserveOrder"
                >
                  <VIcon icon="tabler-device-floppy" size="20" class="me-0 me-sm-2" />
                  <span class="d-none d-sm-inline">GUARDAR</span>
                </VBtn>
                <VBtn
                  color="success"
                  variant="tonal"
                  height="40"
                  min-inline-size="0"
                  class="rounded-lg font-weight-950 px-3 px-sm-4"
                  @click="handleCompleteOrder"
                >
                  <VIcon icon="tabler-circle-check" size="18" class="me-0 me-sm-2" />
                  <span class="d-none d-sm-inline">COBRAR / CERRAR</span>
                </VBtn>
                <VTooltip text="Cobro Rápido Flash (Método seleccionado)" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      color="warning"
                      variant="flat"
                      height="40"
                      class="rounded-lg px-3 elevation-2 ms-2 font-weight-black"
                      @click="handleFlashCheckout"
                    >
                      <VIcon icon="tabler-bolt" size="18" />
                      <span class="d-none d-sm-inline ms-1">FLASH</span>
                    </VBtn>
                  </template>
                </VTooltip>
              </template>
              <template v-else>
                <VBtn
                  v-if="!props.orderReserved"
                  color="warning"
                  variant="tonal"
                  height="40"
                  min-inline-size="0"
                  class="rounded-lg font-weight-950 px-3 px-sm-4"
                  @click="handleReserveOrder"
                >
                  <VIcon icon="tabler-hourglass" size="18" class="me-0 me-sm-2" />
                  <span class="d-none d-sm-inline">RESERVAR</span>
                </VBtn>
                
                <VBtn
                  color="primary"
                  variant="flat"
                  height="40"
                  min-inline-size="0"
                  class="rounded-lg font-weight-950 px-3 px-sm-6 elevation-2"
                  @click="handleCompleteOrder"
                >
                  <VIcon icon="tabler-circle-check" size="20" class="me-0 me-sm-2" />
                  <span class="d-none d-sm-inline">COBRAR AHORA</span>
                </VBtn>
                <VTooltip text="Cobro Rápido Flash (Método seleccionado)" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      color="warning"
                      variant="flat"
                      height="40"
                      class="rounded-lg px-3 elevation-2 ms-2 font-weight-black"
                      @click="handleFlashCheckout"
                    >
                      <VIcon icon="tabler-bolt" size="18" />
                      <span class="d-none d-sm-inline ms-1">FLASH</span>
                    </VBtn>
                  </template>
                </VTooltip>
              </template>
           </div>
        </div>
     </VCardText>
  </VCard>
</template>

<style scoped>
.glass-card {
  background: white !important;
}

.product-row {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-row:hover {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.08) !important;
  transform: translateY(-2px);
}

.quantity-display-box {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-theme-primary), 0.1);
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.05);
  block-size: 40px;
  font-size: 1.1rem;
  inline-size: 40px;
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
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.breakdown-container {
  border: 1px dashed rgba(var(--v-theme-primary), 0.2);
  background: rgba(var(--v-theme-primary), 0.03);
}

.custom-select-premium :deep(.v-field__input) {
  font-weight: 950 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.custom-select-premium :deep(.v-field--variant-flat) {
  background: #f5f5f5 !important;
}

.custom-search-slim :deep(.v-field__input) {
  font-size: 0.9rem !important;
  font-weight: 700 !important;
}

.gap-3 { gap: 12px !important; }
.gap-6 { gap: 24px !important; }
</style>
