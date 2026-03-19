<script setup lang="js">
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modalFormulario: { type: Boolean, required: true },
  titulo: { type: String, required: true },
  formData: { type: Object, default: () => ({}) },
  formError: { type: Object, default: () => ({}) },
  categorias: { type: Array, default: () => [] },
  type_of_expense: { type: String, default: "normal" },
});

const emit = defineEmits(["modalClose", "save", "clearErrorForm"]);

// File upload related refs
const invoiceFile = ref(null);
const invoicePreview = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadedFileData = ref(null);
const fileUploadError = ref(null);

const bs = ["Efectivo", "Tarjeta", "Pago móvil", "Transferencia"];

const usd = ["Efectivo", "Binance", "PayPal"];

const cop = ["Efectivo", "Transferencia"];

const currencies = ["BS", "USD", "COP"];

const recurrencia = ["Mensual", "Semestral", "Anual"];

const shouldShowExchangeRate = computed(() => {
  return props.formData.currency === "BS" || props.formData.currency === "COP";
});

const getCurrencySymbol = computed(() => {
  const symbolMap = {
    BS: "Bs.",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[props.formData.currency] || "$";
});

// Watch para calcular impuesto automáticamente
watch(
  () => props.formData.taxable_base,
  (newValue) => {
    if (newValue !== undefined && newValue !== null) {
      const base = Number(newValue) || 0;
      props.formData.tax_amount = parseFloat((base * 0.16).toFixed(2));
      props.formData.iva = base > 0;
    }
  },
);

// Watch para calcular total automáticamente
watch(
  () => [
    props.formData.exempt_amount,
    props.formData.taxable_base,
    props.formData.tax_amount,
  ],
  () => {
    const excento = Number(props.formData.exempt_amount) || 0;
    const base = Number(props.formData.taxable_base) || 0;
    const impuesto = Number(props.formData.tax_amount) || 0;
    props.formData.total_amount = parseFloat(
      (excento + base + impuesto).toFixed(2),
    );
  },
  { deep: true },
);

// Watch para calcular total USD automáticamente
watch(
  () => [
    props.formData.total_amount,
    props.formData.currency,
    props.formData.exchange_rate,
  ],
  () => {
    const totalAmount = Number(props.formData.total_amount) || 0;
    const currency = props.formData.currency;
    const exchangeRate = Number(props.formData.exchange_rate) || 0;

    let totalUsd = 0;
    if (currency === "USD") {
      totalUsd = totalAmount;
    } else if (exchangeRate > 0) {
      totalUsd = totalAmount / exchangeRate;
    }

    props.formData.total_usd = parseFloat(totalUsd.toFixed(2));
  },
  { deep: true },
);

// Watch para limpiar tasa de cambio si la moneda es USD
watch(
  () => props.formData.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      props.formData.exchange_rate = 0;
    }
  },
);

// Watch para calcular amount_bs automáticamente cuando es deducible y la moneda no es BS
watch(
  () => [
    props.formData.is_deductible,
    props.formData.currency,
    props.formData.amount,
    props.formData.conversion_rate,
  ],
  () => {
    if (
      props.formData.is_deductible === true &&
      props.formData.currency !== "BS" &&
      props.formData.conversion_rate > 0 &&
      props.formData.amount > 0
    ) {
      const amount = Number(props.formData.amount) || 0;
      const rate = Number(props.formData.conversion_rate) || 0;
      props.formData.amount_bs = parseFloat((amount * rate).toFixed(2));
    } else if (
      props.formData.currency === "BS" &&
      props.formData.is_deductible === true
    ) {
      // Si la moneda es BS, amount_bs es igual a amount
      props.formData.amount_bs = parseFloat(
        (Number(props.formData.amount) || 0).toFixed(2),
      );
    }
  },
  { deep: true },
);

// FIXED: Handle file upload correctly for Vuetify VFileInput
const handleFileUpload = (files) => {
  // VFileInput passes the files array directly, not an event object
  if (!files || files.length === 0) {
    clearSelectedFile();
    return;
  }

  const file = files[0]; // Get the first file

  // Validate file type
  const allowedTypes = [
    "image/jpeg",
    "image/jpg",
    "image/png",
    "application/pdf",
  ];
  const maxSize = 5 * 1024 * 1024; // 5MB

  // Check if it's a File object or a string (for previously uploaded files)
  if (file instanceof File) {
    if (!allowedTypes.includes(file.type)) {
      const allowedExtensions = allowedTypes
        .map((type) => {
          if (type.startsWith("image/"))
            return type.split("/")[1].toUpperCase();
          return type.split("/")[1].toUpperCase();
        })
        .join(", ");
      fileUploadError.value = `Solo se permiten archivos ${allowedExtensions}`;
      invoiceFile.value = null;
      return;
    }

    if (file.size > maxSize) {
      fileUploadError.value = "El archivo no puede superar los 5MB";
      invoiceFile.value = null;
      return;
    }

    fileUploadError.value = null;
    invoiceFile.value = file;

    // Generate preview for image files
    if (file.type.startsWith("image/")) {
      const reader = new FileReader();
      reader.onload = (e) => {
        invoicePreview.value = e.target.result;
      };
      reader.readAsDataURL(file);
    } else {
      invoicePreview.value = null;
    }

    // Clear any previously uploaded file data
    if (uploadedFileData.value) {
      uploadedFileData.value = null;
    }
  } else {
    // Handle case where file is not a File object (shouldn't happen with proper setup)
    fileUploadError.value = "Archivo inválido seleccionado";
    invoiceFile.value = null;
  }
};

// Clear selected file
const clearSelectedFile = () => {
  invoiceFile.value = null;
  invoicePreview.value = null;
  fileUploadError.value = null;
};

function close() {
  // Clear file references when closing modal
  clearSelectedFile();
  emit("modalClose", false);
}

async function submitForm() {
  emit("clearErrorForm");

  // Validate required fields first
  if (!props.formData.name) {
    toast.error("El nombre del gasto es requerido");
    return;
  }

  if (!props.formData.category_id) {
    toast.error("La categoría es requerida");
    return;
  }

  if (!props.formData.total_amount || props.formData.total_amount <= 0) {
    toast.error("El monto total debe ser mayor a 0");
    return;
  }

  // Emit the save event with form data and file
  emit("save", {
    ...props.formData,
    // Pass the file to be handled by parent after expense creation
    invoice_file: invoiceFile.value,
  });

  // Clear the file input
  clearSelectedFile();
}
</script>

  <VDialog
    :model-value="props.modalFormulario"
    max-width="800px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    transition="dialog-bottom-transition"
  >
    <VCard class="overflow-visible rounded-xl">
      <!-- Header Estilizado -->
      <VCardTitle class="d-flex align-center pa-2 px-6 header-gradient bg-primary">
        <div class="d-flex align-center gap-3">
          <VAvatar color="rgba(255,255,255,0.2)" size="38" class="rounded-lg">
            <VIcon icon="tabler-file-invoice" color="white" size="22" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h3 class="text-h6 font-weight-black text-white leading-normal">
              {{ props.titulo }}
            </h3>
            <span class="text-xs text-white opacity-70 font-weight-bold uppercase letter-spacing-widest">Módulo de Gastos</span>
          </div>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          density="comfortable"
          @click="close"
        />
      </VCardTitle>

      <VCardText class="pa-0">
        <div class="pa-6 pb-2">
          <!-- Información General -->
          <div class="d-flex align-center gap-2 mb-4">
            <VIcon icon="tabler-info-circle" color="primary" size="20" />
            <span class="text-sm font-weight-black text-primary uppercase letter-spacing-widest">Información General</span>
          </div>
          
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="props.formData.name"
                :error-messages="props.formError.name"
                label="Nombre del Gasto"
                placeholder="Ej: Pago de Luz"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-file-description"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="props.formData.category_id"
                label="Categoría"
                :items="props.categorias"
                :error-messages="props.formError.category_id"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-category"
                placeholder="Seleccionar categoría"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6" md="4">
              <VSelect
                v-model="props.formData.currency"
                label="Moneda"
                :items="currencies"
                :error-messages="props.formError.currency"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-coin"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6" md="4">
              <VSelect
                v-model="props.formData.count"
                label="Método de Pago"
                :items="
                  props.formData.currency === 'BS'
                    ? bs
                    : props.formData.currency === 'USD'
                      ? usd
                      : cop
                "
                :error-messages="props.formError.count"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-wallet"
                placeholder="Seleccionar método"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6" md="4">
              <AppDateTimePicker
                v-model="props.formData.expense_date"
                :error-messages="props.formError.expense_date"
                placeholder="Fecha del Gasto"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-calendar"
                :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                hide-details="auto"
              />
            </VCol>
            <VCol v-if="props.type_of_expense === 'recurrente'" cols="12" sm="6" md="4">
              <VSelect
                v-model="props.formData.recurrence"
                label="Recurrencia"
                :items="recurrencia"
                :error-messages="props.formError.recurrence"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-repeat"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </div>

        <!-- Sección Financiera -->
        <div class="bg-surface-variant-light pa-6 my-2 border-y">
          <div class="d-flex align-center gap-2 mb-4">
            <VIcon icon="tabler-receipt-2" color="primary" size="20" />
            <span class="text-sm font-weight-black text-primary uppercase letter-spacing-widest">Detalles Financieros</span>
          </div>

          <VCard variant="flat" class="pa-4 bg-white rounded-xl border-dashed">
            <VRow>
              <VCol cols="6" md="3">
                <VTextField
                  v-model.number="props.formData.exempt_amount"
                  :error-messages="props.formError.exempt_amount"
                  label="Exento IVA"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="6" md="3">
                <VTextField
                  v-model.number="props.formData.taxable_base"
                  :error-messages="props.formError.taxable_base"
                  label="Base Imponible"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="6" md="3">
                <VTextField
                  v-model.number="props.formData.tax_amount"
                  label="IVA (16%)"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  readonly
                  hide-details="auto"
                  class="text-disabled"
                />
              </VCol>
              <VCol cols="6" md="3">
                <VTextField
                  v-model.number="props.formData.total_amount"
                  label="Total Factura"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  readonly
                  class="font-weight-black text-error"
                  hide-details="auto"
                />
              </VCol>
            </VRow>

            <VRow v-if="shouldShowExchangeRate || props.formData.total_amount" class="mt-4 pt-4 border-t border-dashed">
              <VCol v-if="shouldShowExchangeRate" cols="12" sm="6">
                <VTextField
                  v-model.number="props.formData.exchange_rate"
                  :error-messages="props.formError.exchange_rate"
                  label="Tasa de Cambio (BCV)"
                  type="number"
                  variant="solo"
                  density="compact"
                  flat
                  bg-color="grey-lighten-4"
                  prepend-inner-icon="tabler-trending-up"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" :sm="shouldShowExchangeRate ? 6 : 12">
                <div class="pa-3 rounded-lg bg-error-lighten-5 d-flex justify-space-between align-center border border-error border-opacity-25">
                  <div class="d-flex align-center gap-2 font-weight-black text-error text-xs uppercase">
                    <VIcon icon="tabler-currency-dollar" size="18" />
                    Equivalencia USD
                  </div>
                  <div class="text-h6 font-weight-black text-error">
                    ${{ Number(props.formData.total_usd || 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </div>

        <!-- Archivo Adjunto -->
        <div class="pa-6">
          <div class="d-flex align-center gap-2 mb-4">
            <VIcon icon="tabler-paperclip" color="primary" size="20" />
            <span class="text-sm font-weight-black text-primary uppercase letter-spacing-widest">Documentación</span>
          </div>
          
          <VRow>
            <VCol cols="12">
              <VFileInput
                v-model="invoiceFile"
                label="Comprobante / Factura"
                accept="image/*,application/pdf"
                variant="outlined"
                density="comfortable"
                class="rounded-lg"
                prepend-inner-icon="tabler-upload"
                prepend-icon=""
                :disabled="isUploading"
                show-size
                chips
                :error-messages="fileUploadError"
                @update:model-value="handleFileUpload"
                placeholder="Haz clic o arrastra un archivo aquí"
                hide-details="auto"
              >
                <template #selection="{ fileNames }">
                  <template v-for="fileName in fileNames" :key="fileName">
                    <VChip size="small" label color="primary" class="me-2 font-weight-black">
                      {{ fileName }}
                    </VChip>
                  </template>
                </template>
              </VFileInput>

              <!-- Preview Section -->
              <VExpandTransition>
                <div v-if="invoicePreview" class="mt-4 d-flex justify-center border-dashed rounded-xl pa-4 bg-grey-lighten-5 position-relative">
                  <VBtn
                    icon="tabler-x"
                    size="x-small"
                    color="error"
                    variant="flat"
                    class="position-absolute top-0 right-0 ma-2 z-index-1"
                    @click="clearSelectedFile"
                  />
                  <VImg
                    :src="invoicePreview"
                    max-height="300"
                    contain
                    class="rounded-lg shadow-sm"
                  />
                </div>
              </VExpandTransition>
              
              <div class="d-flex align-center gap-2 mt-3 text-disabled">
                <VIcon icon="tabler-alert-circle" size="14" />
                <span class="text-xs font-weight-bold uppercase letter-spacing-widest">JPG, PNG, PDF (Máx. 5MB)</span>
              </div>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-surface d-flex gap-3">
        <VBtn
          color="secondary"
          variant="tonal"
          size="large"
          class="rounded-lg font-weight-black flex-grow-1"
          prepend-icon="tabler-arrow-left"
          @click="close"
        >
          Regresar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          size="large"
          class="rounded-lg font-weight-black flex-grow-1"
          prepend-icon="tabler-device-floppy"
          :loading="isUploading"
          :disabled="isUploading"
          @click="submitForm"
        >
          Guardar Gasto
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #4a90e2 100%) !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.shadow-soft {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 8%) !important;
}

.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}

.leading-normal {
  line-height: 1.2 !important;
}

.bg-error-lighten-5 {
  background-color: rgba(234, 84, 85, 8%) !important;
}

:deep(.v-field--variant-underlined) {
  .v-label {
    opacity: 0.6 !important;
    text-transform: uppercase !important;
  }

  .v-field__input {
    font-size: 1rem !important;
    font-weight: 700 !important;
  }
}

.z-index-1 { z-index: 1; }
</style>
