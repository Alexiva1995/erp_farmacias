<script setup lang="js">
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  modalFormulario: { type: Boolean, required: true },
  titulo: { type: String, required: true },
  formData: { type: Object, default: () => ({}) },
  formError: { type: Object, default: () => ({}) },
  categorias: { type: Array, default: () => [] },
  type_of_expense: { type: String, default: "normal" },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["modalClose", "save", "clearErrorForm"]);

const brandingStore = useBrandingStore();

// Copia local reactiva para evitar mutaciones directas de props
const localForm = ref({ ...props.formData });

// Sincronizar copia local cuando el modal se abre o cambia formData
watch(
  () => props.modalFormulario,
  (isOpen) => {
    if (isOpen) {
      localForm.value = { ...props.formData };
    }
  }
);

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
  return localForm.value.currency === "BS" || localForm.value.currency === "COP";
});

const getCurrencySymbol = computed(() => {
  const symbolMap = {
    BS: "Bs.",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[localForm.value.currency] || "$";
});

// Watch para sincronizar monto total en modo simple
watch(
  () => localForm.value.total_amount,
  (newVal) => {
    if (brandingStore.settings.expense_mode === 'simple') {
      localForm.value.exempt_amount = Number(newVal) || 0;
      localForm.value.taxable_base = 0;
      localForm.value.tax_amount = 0;
      localForm.value.iva = false;
    }
  }
);

// Watch para calcular impuesto automáticamente
watch(
  () => localForm.value.taxable_base,
  (newValue) => {
    if (newValue !== undefined && newValue !== null) {
      const base = Number(newValue) || 0;
      localForm.value.tax_amount = parseFloat((base * 0.16).toFixed(2));
      localForm.value.iva = base > 0;
    }
  },
);

// Watch para calcular total automáticamente
watch(
  () => [
    localForm.value.exempt_amount,
    localForm.value.taxable_base,
    localForm.value.tax_amount,
  ],
  () => {
    const excento = Number(localForm.value.exempt_amount) || 0;
    const base = Number(localForm.value.taxable_base) || 0;
    const impuesto = Number(localForm.value.tax_amount) || 0;
    localForm.value.total_amount = parseFloat(
      (excento + base + impuesto).toFixed(2),
    );
  },
  { deep: true },
);

// Computed para equivalencia USD centralizado
const computedTotalUsd = computed(() => {
  const totalAmount = Number(localForm.value.total_amount) || 0;
  const currency = localForm.value.currency;
  const exchangeRate = Number(localForm.value.exchange_rate) || 0;

  let totalUsd = 0;
  if (currency === "USD") {
    totalUsd = totalAmount;
  } else if (exchangeRate > 0) {
    totalUsd = totalAmount / exchangeRate;
  }

  // Sincronizamos con el localForm para el envío
  localForm.value.total_usd = parseFloat(totalUsd.toFixed(2));
  
  return localForm.value.total_usd;
});

// Watch para limpiar tasa de cambio si la moneda es USD
watch(
  () => localForm.value.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      localForm.value.exchange_rate = 0;
    }
  },
);

// Watch para calcular amount_bs automáticamente cuando es deducible y la moneda no es BS
watch(
  () => [
    localForm.value.is_deductible,
    localForm.value.currency,
    localForm.value.amount,
    localForm.value.conversion_rate,
  ],
  () => {
    if (
      localForm.value.is_deductible === true &&
      localForm.value.currency !== "BS" &&
      localForm.value.conversion_rate > 0 &&
      localForm.value.amount > 0
    ) {
      const amount = Number(localForm.value.amount) || 0;
      const rate = Number(localForm.value.conversion_rate) || 0;
      localForm.value.amount_bs = parseFloat((amount * rate).toFixed(2));
    } else if (
      localForm.value.currency === "BS" &&
      localForm.value.is_deductible === true
    ) {
      // Si la moneda es BS, amount_bs es igual a amount
      localForm.value.amount_bs = parseFloat(
        (Number(localForm.value.amount) || 0).toFixed(2),
      );
    }
  },
  { deep: true },
);

// FIXED: Handle file upload correctly for Vuetify VFileInput
const handleFileUpload = (input) => {
  // Manejo robusto: Vuetify puede pasar un arreglo o un solo objeto File
  let file = null;
  if (Array.isArray(input) && input.length > 0) {
    file = input[0];
  } else if (input instanceof File) {
    file = input;
  }

  if (!file) {
    clearSelectedFile();
    return;
  }

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
  if (!localForm.value.name) {
    toast.error("El nombre del gasto es requerido");
    return;
  }

  if (!localForm.value.category_id) {
    toast.error("La categoría es requerida");
    return;
  }

  if (!localForm.value.total_amount || localForm.value.total_amount <= 0) {
    toast.error("El monto total debe ser mayor a 0");
    return;
  }

  // Emit the save event with form data and file
  emit("save", {
    ...localForm.value,
    // Pass the file to be handled by parent after expense creation
    invoice_file: invoiceFile.value,
  });

  // Clear the file input
  clearSelectedFile();
}
</script>
<template>
  <VDialog
    :model-value="props.modalFormulario"
    max-width="800px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    transition="dialog-bottom-transition"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl bg-surface">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-file-invoice"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.titulo }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Módulo de Gestión de Gastos • Farmacia Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="close"
          />
        </div>
      </VCardTitle>

      <VCardText 
        class="pa-4 pa-sm-6 bg-light"
        style="max-block-size: calc(90vh - 160px); overflow-y: auto;"
      >
        <!-- Información General -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información General del Gasto</span>
        </div>

        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-xl border shadow-sm mb-4"
        >
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="localForm.name"
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
            <VCol
              cols="12"
              sm="6"
            >
              <VSelect
                v-model="localForm.category_id"
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
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="localForm.currency"
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
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="localForm.count"
                label="Método de Pago"
                :items="
                  localForm.currency === 'BS'
                    ? bs
                    : localForm.currency === 'USD'
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
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <AppDateTimePicker
                v-model="localForm.expense_date"
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
            <VCol
              v-if="props.type_of_expense === 'recurrente'"
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="localForm.recurrence"
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
        </VCard>

        <!-- Sección Financiera -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Detalles Financieros e Impuestos</span>
        </div>

        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-xl border shadow-sm mb-4"
        >
          <VRow v-if="brandingStore.settings.expense_mode !== 'simple'">
            <VCol
              cols="6"
              md="3"
            >
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Monto Exento</span>
                <VTextField
                  v-model.number="localForm.exempt_amount"
                  :error-messages="props.formError.exempt_amount"
                  placeholder="0.00"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  hide-details="auto"
                  class="font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Base Imponible</span>
                <VTextField
                  v-model.number="localForm.taxable_base"
                  :error-messages="props.formError.taxable_base"
                  placeholder="0.00"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  hide-details="auto"
                  class="font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">IVA (16%)</span>
                <VTextField
                  v-model.number="localForm.tax_amount"
                  placeholder="0.00"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  readonly
                  hide-details="auto"
                  class="text-disabled font-weight-bold"
                />
              </div>
            </VCol>
            <VCol
              cols="6"
              md="3"
            >
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-error uppercase mb-1">Total Factura</span>
                <VTextField
                  v-model.number="localForm.total_amount"
                  placeholder="0.00"
                  type="number"
                  variant="underlined"
                  density="compact"
                  :prefix="getCurrencySymbol"
                  readonly
                  class="font-weight-black text-error"
                  hide-details="auto"
                />
              </div>
            </VCol>
          </VRow>

          <!-- Vista en Modo Simple -->
          <VRow v-else>
            <VCol
              cols="12"
            >
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-error uppercase mb-1">Monto Total del Gasto</span>
                <VTextField
                  v-model.number="localForm.total_amount"
                  :error-messages="props.formError.total_amount || props.formError.exempt_amount"
                  placeholder="0.00"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                  :prefix="getCurrencySymbol"
                  class="font-weight-black text-error"
                  hide-details="auto"
                />
              </div>
            </VCol>
          </VRow>
  
            <VRow
              v-if="shouldShowExchangeRate || localForm.total_amount"
              class="mt-2 pt-2 border-t border-dashed"
            >
              <VCol
                v-if="shouldShowExchangeRate"
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model.number="localForm.exchange_rate"
                  :error-messages="props.formError.exchange_rate"
                  label="Tasa de Cambio (Oficial)"
                  type="number"
                  variant="solo"
                  density="compact"
                  flat
                  bg-color="grey-lighten-4"
                  prepend-inner-icon="tabler-trending-up"
                  class="rounded-lg font-weight-bold"
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                :sm="shouldShowExchangeRate ? 6 : 12"
              >
                <div class="pa-2 rounded-lg bg-surface border-lg border-error border-opacity-50 d-flex justify-space-between align-center animate__animated animate__fadeIn">
                  <div class="d-flex align-center gap-2 font-weight-black text-error text-xs uppercase">
                    <VIcon
                      icon="tabler-currency-dollar"
                      size="18"
                    />
                    Equivalente USD
                  </div>
                  <div class="text-h6 font-weight-black text-error leading-tight">
                    ${{ computedTotalUsd.toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                  </div>
                </div>
              </VCol>
            </VRow>
        </VCard>

        <!-- Documentación -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Documentación y Comprobantes</span>
        </div>

        <VCard
          variant="flat"
          class="pa-4 bg-white rounded-xl border shadow-sm"
        >
          <VRow>
            <VCol cols="12">
              <VFileInput
                v-model="invoiceFile"
                label="Subir Comprobante / Factura"
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
                placeholder="Haz clic o arrastra el archivo aquí"
                hide-details="auto"
              >
                <template #selection="{ fileNames }">
                  <template
                    v-for="fileName in fileNames"
                    :key="fileName"
                  >
                    <VChip
                      size="small"
                      label
                      color="primary"
                      class="me-2 font-weight-black shadow-sm"
                    >
                      <VIcon
                        start
                        icon="tabler-paperclip"
                        size="14"
                      />
                      {{ fileName }}
                    </VChip>
                  </template>
                </template>
              </VFileInput>

              <!-- Preview Section -->
              <VExpandTransition>
                <div
                  v-if="invoicePreview"
                  class="mt-6 d-flex justify-center border-dashed rounded-xl pa-4 bg-light position-relative"
                >
                  <VBtn
                    icon="tabler-circle-x"
                    size="small"
                    color="error"
                    variant="flat"
                    class="position-absolute top-0 right-0 ma-n2 z-index-1 shadow-sm"
                    @click="clearSelectedFile"
                  />
                  <VImg
                    :src="invoicePreview"
                    max-height="300"
                    contain
                    class="rounded-lg shadow-sm border bg-white"
                  />
                </div>
              </VExpandTransition>
              
              <div class="d-flex align-center gap-2 mt-4 text-disabled">
                <VIcon
                  icon="tabler-info-circle"
                  size="14"
                />
                <span class="text-super-xs font-weight-black uppercase letter-spacing-1">Soporte aceptado: JPG, PNG, PDF (Máx. 5MB)</span>
              </div>
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              <VIcon
                start
                icon="tabler-arrow-left"
                size="18"
              />
              Regresar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="props.loading"
              :disabled="props.loading"
              @click="submitForm"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
              Guardar Gasto
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.z-index-1 {
  z-index: 10;
}

.italic {
  font-style: italic;
}

:deep(.v-field--variant-underlined .v-field__input) {
  font-weight: 800 !important;
  font-size: 0.95rem !important;
}

:deep(.v-field--variant-underlined .v-label) {
  font-weight: 800 !important;
  font-size: 0.65rem !important;
  text-transform: uppercase !important;
  opacity: 0.8 !important;
}
</style>
