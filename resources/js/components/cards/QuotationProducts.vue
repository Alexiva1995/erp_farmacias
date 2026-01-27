<script setup>
import axiosInstance from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, onMounted, ref } from "vue";

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

  // Need to consider how prescription discount interacts. Assuming it's part of the global discount strategy passed in,
  // but if passed separately, we should maximize.
  // Generally, globalDiscountPercentage prop in quotation.vue seems to aggregate the "selected" discount.

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
  <VCard min-height="280" class="d-flex flex-column">
    <VCardText class="d-flex flex-column pb-0 mb-4">
      <VRow>
        <VCol cols="12">
          <h3>Cotización #{{ lastNumber }}</h3>
        </VCol>
        <VCol cols="12" sm="12" md="12">
          <div class="d-flex align-center gap-4 flex-wrap">
            <AppTextField
              :model-value="props.searchQuery"
              placeholder="Código de Barra"
              clearable
              @update:model-value="emit('update:searchQuery', $event)"
              class="flex-grow-1"
            />

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
          </div>
        </VCol>
      </VRow>
      <VRow>
        <VCol cols="12" sm="12" md="12">
          <div class="d-flex align-center gap-4 flex-wrap">
            <AppTextField
              :model-value="props.clientIdentification"
              placeholder="Cédula del Cliente"
              clearable
              @update:model-value="emit('update:clientIdentification', $event)"
              class="flex-grow-1"
            />

            <IconBtn
              size="small"
              rounded
              variant="tonal"
              color="default"
              @click="emit('search-client')"
            >
              <VIcon icon="tabler-search" />
            </IconBtn>
          </div>
        </VCol>
      </VRow>
      <VRow v-if="props.selectedClient && props.selectedClient.id">
        <VCol cols="12" sm="12" md="12">
          <VAlert icon="tabler-user" color="primary">
            <div class="d-flex align-center gap-4 flex-wrap">
              <p class="font-weight-medium">{{ props.selectedClient.name }} {{ props.selectedClient.last_name }}</p>
            </div>
            <div class="d-flex align-center gap-4 flex-wrap">
              <p>
                {{ props.selectedClient.identification_type
                }}{{ props.selectedClient.identification }}
              </p>
              <p v-if="props.selectedClient.phone">{{ props.selectedClient.phone }}</p>
            </div>
          </VAlert>
        </VCol>
      </VRow>
    </VCardText>

    <VCardText class="pb-2 pt-0">
      <div class="d-flex align-center gap-2 flex-wrap">
        <VSelect
          :model-value="props.selectedDiscountType"
          :items="discountOptions"
          density="compact"
          variant="outlined"
          hide-details
          style="width: 140px"
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
          style="width: 200px"
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
          style="width: 200px"
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
          style="width: 200px"
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
      </div>
    </VCardText>

    <VCardText class="d-flex flex-column pb-0 flex-grow-1">
      <div
        class="scrollable-list-container"
        :class="{ 'show-scroll': props.quotationProducts.length > 2 }"
      >
        <VList class="card-list" density="compact" nav>
          <VListItem v-if="props.quotationProducts.length === 0">
            <VListItemTitle class="text-center text-medium-emphasis"
              >No hay productos en la cotización.</VListItemTitle
            >
          </VListItem>

          <VListItem
            v-for="product in props.quotationProducts"
            :key="product.id"
            class="rounded-0"
          >
            <template #prepend>
              <div class="d-flex align-center" style="width: 60px">
                <VTextField
                  v-model.number="product.selectedQuantity"
                  type="number"
                  variant="outlined"
                  density="compact"
                  hide-details
                  single-line
                  class="cost-input-field text-center"
                  min="1"
                  :max="product.availableQuantity"
                >
                </VTextField>
              </div>
            </template>

            <VListItemTitle class="font-weight-medium me-4 mx-2">
              <div class="d-flex align-center flex-wrap gap-2">
                <span>
                  {{ product.title }}
                  <template
                    v-if="product.laboratory && product.laboratory !== 'N/A'"
                  >
                    - {{ product.laboratory }}
                  </template>
                </span>
                <VChip
                  v-if="product.discount_percentage > 0"
                  color="error"
                  size="x-small"
                  variant="flat"
                  class="font-weight-bold"
                >
                  {{ parseFloat(product.discount_percentage) }}%
                  {{
                    product.discount_type ? `(${product.discount_type})` : ""
                  }}
                </VChip>
                <VChip
                  v-if="
                    getBestDiscountForProduct(product) > 0 &&
                    Math.abs(
                      getBestDiscountForProduct(product) -
                        parseFloat(product.discount_percentage || 0),
                    ) > 0.01
                  "
                  color="warning"
                  size="x-small"
                  variant="tonal"
                >
                  Aplicado: {{ getBestDiscountForProduct(product) }}%
                </VChip>
              </div>
            </VListItemTitle>
            <VListItemSubtitle class="mx-2">
              <template
                v-if="
                  product.active_ingredient &&
                  product.active_ingredient !== 'N/A'
                "
              >
                {{ product.active_ingredient }}
              </template>
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center">
                <span class="text-body-1 me-2">{{
                  formatCurrency(
                    getProductPrice(product, props.selectedDisplayCurrency) *
                      product.selectedQuantity,
                    props.selectedDisplayCurrency,
                  )
                }}</span>
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="removeQuotationProduct(product.id)"
                />
              </div>
            </template>
          </VListItem>
        </VList>
      </div>
    </VCardText>

    <VCardActions class="pa-4 d-flex flex-wrap justify-space-between">
      <VTooltip text="Cancelar" location="top">
        <template #activator="{ props }">
          <IconBtn v-bind="props" class="text-secondary" @click="remove()">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>
      </VTooltip>
      <VTooltip text="Imprimir" location="top">
        <template #activator="{ props }">
          <IconBtn
            v-bind="props"
            class="text-primary"
            @click="handlePrintButtonClick"
          >
            <VIcon icon="tabler-printer" />
          </IconBtn>
        </template>
      </VTooltip>
      <VTooltip text="Culminar" location="top">
        <template #activator="{ props }">
          <IconBtn
            v-bind="props"
            class="text-default"
            @click="handleSaveQuotation"
          >
            <VIcon icon="tabler-check" />
          </IconBtn>
        </template>
      </VTooltip>
      <VTooltip text="Compartir" location="top">
        <template #activator="{ props }">
          <IconBtn
            v-bind="props"
            class="text-success"
            @click="handleShareButtonClick"
          >
            <VIcon icon="tabler-share" />
          </IconBtn>
        </template>
      </VTooltip>
      <VTooltip text="Copiar" location="top">
        <template #activator="{ props }">
          <IconBtn
            v-bind="props"
            class="text-info"
            @click="handleCopyWhatsappMessage"
          >
            <VIcon icon="tabler-copy" />
          </IconBtn>
        </template>
      </VTooltip>
    </VCardActions>
  </VCard>
</template>
