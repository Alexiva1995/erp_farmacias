<script setup>
import axiosInstance from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, onMounted, ref, watch } from "vue";

// --- PROPS ---
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    required: false,
    default: "primary",
  },
  icon: {
    type: String,
    required: false,
  },
  stats: {
    type: String,
    required: false,
  },
  height: {
    type: Number,
    required: false,
  },
  series: {
    type: Array,
    required: false,
  },
  chartOptions: {
    type: null,
    required: false,
  },
  searchQuery: {
    type: String,
    default: "",
  },
  clientIdentification: {
    type: String,
    default: "",
  },
  selectedClient: {
    type: Object,
    default: null,
  },
  quotationProducts: {
    type: Array,
    default: () => [],
  },
  selectedDisplayCurrency: {
    type: String,
    default: "USD",
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
  quotationDetails: {
    type: Object,
    default: null,
  },
  onSaveQuotation: {
    type: Function,
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
  globalDiscountPercentage: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:clientIdentification",
  "remove-quotation-product",
  "remove",
  "print-quotation",
  "add-product-by-barcode",
  "search-client",
  "clean-post-save",
  "update:selectedDiscountType",
  "doctor-discount-selected",
  "prescription-file-selected",
  "company-discount-selected",
]);

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

watch(
  () => props.selectedClient,
  (newCliente, oldCliente) => {
    // Prevent reset if it's the same client
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
  { immediate: true, deep: true },
);

const discountOptions = computed(() => {
  const options = ["Medico", "Recipe"];
  if (
    props.selectedClient &&
    props.selectedClient.id &&
    props.selectedClient.company_id !== null &&
    props.selectedClient.company_id !== undefined
  ) {
    options.unshift("Empresa");
  }
  return options;
});

const lastNumber = ref(null);

const removeQuotationProduct = (productId) => {
  emit("remove-quotation-product", productId);
};

const remove = () => {
  emit("remove");
};

const getBestDiscountForProduct = (product) => {
  const itemDiscount = parseFloat(product.discount_percentage || 0);
  const globalDiscount = parseFloat(props.globalDiscountPercentage || 0);
  const prescriptionDiscount = parseFloat(
    props.prescriptionDiscountPercentage || 0,
  );

  return Math.max(itemDiscount, globalDiscount, prescriptionDiscount);
};

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

  // Apply Discount
  const discountPercentage = getBestDiscountForProduct(product);
  if (discountPercentage > 0) {
    basePrice = basePrice * (1 - discountPercentage / 100);
  }

  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.quotationProducts.forEach((product) => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});

const handlePrintButtonClick = () => {
  emit("print-quotation");
};

const generateWhatsappMessage = () => {
  if (props.quotationProducts.length === 0) {
    return ""; // Retorna vacío si no hay productos
  }

  const fecha = new Date();
  const productos_array = [];
  props.quotationProducts.forEach((product) => {
    let priceWithIvaUsd = (product.price || 0) * (1 + (product.taxRate || 0));
    let priceWithIvaBs = (product.price_bs || 0) * (1 + (product.taxRate || 0));
    let priceWithIvaCop =
      (product.price_cop || 0) * (1 + (product.taxRate || 0));

    priceWithIvaCop = roundUpToNearestHundred(priceWithIvaCop);

    let rows =
      "💊 " +
      product.title +
      (product.laboratory && product.laboratory !== "N/A"
        ? " (" + product.laboratory + ")"
        : "") +
      "\n" +
      "Cantidad: " +
      product.selectedQuantity +
      " UND(S) \n" +
      "Precio por unidad: \n" +
      "Bs.: " +
      formatCurrency(priceWithIvaBs, "BS") +
      "\n" +
      "💵 USD: " +
      formatCurrency(priceWithIvaUsd, "USD") +
      "\n" +
      "💰 COP: " +
      formatCurrency(priceWithIvaCop, "COP");
    productos_array.push(rows);
  });

  const quotationNumber = props.quotationDetails?.id
    ? `\nNúmero de Cotización: #${props.quotationDetails.id}\n`
    : "";

  const whatsappMessage =
    "Mensaje de presupuesto\n\n" +
    "Fecha: " +
    fecha.toLocaleDateString("es-ES", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    }) +
    quotationNumber +
    "\nBuenas tardes, Estimado Cliente!\n" +
    "... le da la Bienvenida!\n" +
    "Para nosotros es un gusto servirle.\n\n" +
    "A continuación el detalle de su presupuesto: \n" +
    productos_array.join("\n\n") +
    "\n\n" +
    "\n\nTOTAL PRESUPUESTO:\n" +
    "Bs.: " +
    formatCurrency(props.totalAmountBs, "BS") +
    "\n" +
    "💵 USD: " +
    formatCurrency(props.totalAmountUsd, "USD") +
    "\n" +
    "💰 COP: " +
    formatCurrency(roundUpToNearestHundred(props.totalAmountCop), "COP") +
    "\n" +
    "\n👉 Condiciones\n" +
    "\nPrecios sujetos a cambio sin previo aviso.\n" +
    "\nEl Presupuesto es Válido hasta agotarse las existencias.\n" +
    "\nPara compras en taquilla debe presentar este presupuesto.\n";

  return whatsappMessage;
};

const handleShareButtonClick = async () => {
  try {
    // Guardar la cotización si no está guardada
    if (!props.quotationDetails?.id && props.onSaveQuotation) {
      await props.onSaveQuotation();
    }

    const whatsappMessage = generateWhatsappMessage();
    if (!whatsappMessage) {
      toast.error("No hay productos en la cotización para compartir.");
      return;
    }
    const encodedMessage = encodeURIComponent(whatsappMessage);
    const whatsappUrl = "https://api.whatsapp.com/send?text=" + encodedMessage;
    window.open(whatsappUrl, "_blank");
  } catch (error) {
    console.error("Error al guardar/compartir la cotización:", error);
    toast.error("Error al guardar la cotización. Inténtalo de nuevo.");
  }
};

const handleSaveQuotation = async () => {
  try {
    await props.onSaveQuotation();

    toast.success("Se guardó la cotización exitosamente");

    emit("clean-post-save");
    fetchLastQuotationNumber();
  } catch (error) {
    const noProductsError =
      "Error: No hay productos en la cotización para guardar.";

    if (error == noProductsError) {
      toast.error("No hay productos en la cotización para guardar.");
    } else {
      toast.error("Hubo un error al guardar la cotización");
      console.error("Error trying to save quotation", error);
    }
  }
};

const handleCopyWhatsappMessage = async () => {
  try {
    // Guardar la cotización si no está guardada
    if (!props.quotationDetails?.id && props.onSaveQuotation) {
      await props.onSaveQuotation();
    }

    const whatsappMessage = generateWhatsappMessage();
    if (!whatsappMessage) {
      toast.error("No hay productos en la cotización para copiar.");
      return;
    }
    try {
      await navigator.clipboard.writeText(whatsappMessage);
      toast.success("Mensaje copiado al portapapeles correctamente.");
    } catch (err) {
      console.error("Error al copiar el mensaje:", err);
      toast.error("Error al copiar el mensaje al portapapeles.");
    }
  } catch (error) {
    console.error("Error al guardar/copiar la cotización:", error);
    toast.error("Error al guardar la cotización. Inténtalo de nuevo.");
  }
};
const chipColor = "primary";

const fetchLastQuotationNumber = async () => {
  try {
    const { data } = await axiosInstance.get("/tpv/quotations/last-number");

    lastNumber.value = data.quotation_id + 1;
  } catch (error) {
    lastNumber.value = 1;
    console.error(error);
  }
};

onMounted(() => {
  fetchLastQuotationNumber();
});
</script>

<template>
  <VCard min-height="280" border variant="flat" class="d-flex flex-column rounded-lg elevation-1">
    <VCardText class="d-flex flex-column pb-0 mb-4">
      <VRow>
        <VCol cols="12">
          <h3 class="d-flex align-center gap-2 text-h5 font-weight-black text-primary">
            <VIcon icon="tabler-receipt" size="28" />
            Cotización #{{ lastNumber }}
            <template v-if="props.selectedClient && props.selectedClient.id">
              <span class="text-medium-emphasis font-weight-normal text-subtitle-1 ms-2">
                — {{ props.selectedClient.name }} {{ props.selectedClient.last_name }}
              </span>
            </template>
          </h3>
        </VCol>
        <VCol cols="12">
          <div class="d-flex align-center gap-4 flex-wrap">
            <AppTextField
              :model-value="props.searchQuery"
              placeholder="Escanear Código de Barra..."
              prepend-inner-icon="tabler-barcode"
              clearable
              @update:model-value="emit('update:searchQuery', $event)"
              class="flex-grow-1"
            />

            <VChip
              label
              color="primary"
              variant="flat"
              class="font-weight-black px-4"
              size="large"
            >
              <VIcon start icon="tabler-shopping-cart" size="18" />
              {{ totalSelectedQuantity }}
            </VChip>
          </div>
        </VCol>
      </VRow>
      <VRow v-if="!props.selectedClient || !props.selectedClient.id">
        <VCol cols="12">
          <div class="d-flex align-center gap-2 flex-wrap">
            <AppTextField
              :model-value="props.clientIdentification"
              placeholder="Cédula del Cliente..."
              prepend-inner-icon="tabler-id"
              clearable
              hide-details
              @update:model-value="emit('update:clientIdentification', $event)"
              class="flex-grow-1"
            />

            <VBtn
              variant="tonal"
              color="primary"
              size="40"
              icon
              class="rounded-lg"
              @click="emit('search-client')"
            >
              <VIcon icon="tabler-search" size="20" />
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VCardText>

    <VCardText class="pb-2 pt-0">
      <div class="d-flex align-center gap-2 flex-wrap bg-primary-lighten-5 pa-2 rounded-lg">
        <VSelect
          :model-value="props.selectedDiscountType"
          :items="discountOptions"
          density="compact"
          variant="outlined"
          hide-details
          style="inline-size: 140px;"
          placeholder="Descuento"
          clearable
          class="bg-white rounded-lg"
          @update:model-value="emit('update:selectedDiscountType', $event)"
        />
        <VSelect
          v-if="props.selectedDiscountType === 'Empresa'"
          v-model="selectedCompany"
          :items="props.activeCompanyOffers"
          density="compact"
          variant="outlined"
          hide-details
          style="inline-size: 200px;"
          placeholder="Seleccione Empresa"
          item-title="title"
          item-value="value"
          clearable
          class="bg-white rounded-lg"
        />
        <VSelect
          v-if="props.selectedDiscountType === 'Medico'"
          v-model="selectedDoctor"
          :items="props.activeDoctorOffers"
          density="compact"
          variant="outlined"
          hide-details
          style="inline-size: 200px;"
          placeholder="Seleccione Médico"
          item-title="title"
          item-value="value"
          clearable
          class="bg-white rounded-lg"
        />
        <VFileInput
          v-if="props.selectedDiscountType === 'Recipe'"
          v-model="prescriptionFile"
          density="compact"
          variant="outlined"
          hide-details
          style="inline-size: 200px;"
          placeholder="Subir Recipe"
          accept="image/*"
          prepend-icon=""
          append-inner-icon="tabler-upload"
          clearable
          class="bg-white rounded-lg"
        />
        <VChip
          v-if="props.selectedDiscountType === 'Recipe' && props.prescriptionDiscountPercentage > 0"
          color="success"
          variant="flat"
          size="small"
          class="font-weight-black"
        >
          {{ props.prescriptionDiscountPercentage }}% OFF
        </VChip>
      </div>
    </VCardText>

    <VCardText class="d-flex flex-column pb-0 flex-grow-1">
      <div class="scrollable-list-container flex-grow-1">
        <VList class="card-list bg-transparent" density="compact" nav>
          <VListItem v-if="props.quotationProducts.length === 0" class="text-center py-8">
            <VIcon icon="tabler-shopping-cart-off" size="48" color="medium-emphasis" class="mb-2" />
            <VListItemTitle class="text-medium-emphasis font-weight-medium">
              No hay productos en la cotización.
            </VListItemTitle>
          </VListItem>

          <TransitionGroup name="list-transition">
            <VListItem
              v-for="product in props.quotationProducts"
              :key="product.id"
              class="rounded-lg mb-1 product-item-transition border-opacity-10 bg-white"
            >
              <template #prepend>
                <div class="d-flex align-center" style="inline-size: 65px;">
                  <VTextField
                    v-model.number="product.selectedQuantity"
                    type="number"
                    variant="outlined"
                    density="compact"
                    hide-details
                    single-line
                    class="cost-input-field text-center font-weight-black"
                    min="1"
                    :max="product.availableQuantity"
                  />
                </div>
              </template>

              <VListItemTitle class="mx-3">
                <div class="d-flex align-center gap-1 mb-0 pb-0">
                  <span class="text-primary font-weight-black text-xs">#{{ product.id }}</span>
                  <span class="text-subtitle-2 font-weight-950 text-high-emphasis text-uppercase leading-tight">{{ (product.title || '').toUpperCase() }}</span>
                  <VChip
                    v-if="product.discount_percentage > 0"
                    color="error"
                    size="x-small"
                    variant="flat"
                    class="ms-1 font-weight-black"
                  >
                    {{ parseFloat(product.discount_percentage) }}%
                  </VChip>
                </div>
                <div class="d-flex align-center gap-1 text-super-xs mt-0 pt-0">
                  <span class="text-disabled truncate" style="max-inline-size: 150px;">{{ product.active_ingredient || '—' }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                    {{ product.laboratory && product.laboratory !== 'N/A' ? product.laboratory : 'Genérico' }}
                  </span>
                </div>
              </VListItemTitle>

              <template #append>
                <div class="d-flex align-center">
                  <div class="d-flex flex-column align-end me-2">
                    <span class="text-subtitle-2 font-weight-black text-primary">
                      {{ formatCurrency(getProductPrice(product, props.selectedDisplayCurrency) * product.selectedQuantity, props.selectedDisplayCurrency) }}
                    </span>
                    <span class="text-super-xs text-medium-emphasis">
                      {{ formatCurrency(getProductPrice(product, props.selectedDisplayCurrency), props.selectedDisplayCurrency) }} c/u
                    </span>
                  </div>
                  <VBtn
                    icon="tabler-trash-x"
                    size="32"
                    color="error"
                    variant="tonal"
                    class="rounded-lg"
                    @click="removeQuotationProduct(product.id)"
                  />
                </div>
              </template>
            </VListItem>
          </TransitionGroup>
        </VList>
      </div>
    </VCardText>

    <VCardActions class="pa-4 bg-primary-lighten-5 border-t border-opacity-10">
      <div class="d-flex justify-space-between w-100 gap-2">
        <VTooltip text="Limpiar Cotización" location="top">
          <template #activator="{ props: tProps }">
            <VBtn v-bind="tProps" icon variant="tonal" color="error" class="rounded-lg" @click="remove">
              <VIcon icon="tabler-trash" />
            </VBtn>
          </template>
        </VTooltip>

        <div class="d-flex gap-2">
          <VTooltip text="Imprimir Ticket" location="top">
            <template #activator="{ props: tProps }">
              <VBtn v-bind="tProps" icon variant="tonal" color="primary" class="rounded-lg" @click="handlePrintButtonClick">
                <VIcon icon="tabler-printer" />
              </VBtn>
            </template>
          </VTooltip>
          
          <VTooltip text="Copiar Mensaje" location="top">
            <template #activator="{ props: tProps }">
              <VBtn v-bind="tProps" icon variant="tonal" color="info" class="rounded-lg" @click="handleCopyWhatsappMessage">
                <VIcon icon="tabler-copy" />
              </VBtn>
            </template>
          </VTooltip>

          <VTooltip text="Enviar WhatsApp" location="top">
            <template #activator="{ props: tProps }">
              <VBtn v-bind="tProps" icon variant="tonal" color="success" class="rounded-lg" @click="handleShareButtonClick">
                <VIcon icon="tabler-brand-whatsapp" />
              </VBtn>
            </template>
          </VTooltip>

          <VBtn 
            variant="flat" 
            color="primary" 
            prepend-icon="tabler-circle-check"
            class="rounded-lg px-6 font-weight-black"
            @click="handleSaveQuotation"
          >
            Finalizar
          </VBtn>
        </div>
      </div>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.scrollable-list-container {
  max-block-size: 400px;
  overflow-y: auto;
  padding-inline-end: 4px;
}

.scrollable-list-container::-webkit-scrollbar {
  inline-size: 4px;
}

.scrollable-list-container::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-primary), 0.2);
  border-radius: 10px;
}

.text-super-xs {
  font-size: 0.7rem !important;
}

/* Animaciones */
.list-transition-enter-active,
.list-transition-leave-active {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.list-transition-enter-from {
  opacity: 0;
  transform: translateX(30px);
}

.list-transition-leave-to {
  opacity: 0;
  transform: scale(0.9);
  transform: translateX(-30px);
}

.product-item-transition {
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.product-item-transition:hover {
  border-color: rgba(var(--v-theme-primary), 0.2);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 5%) !important;
  transform: translateY(-2px);
}

.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }
</style>
