<script setup lang="js">
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from 'vue';

const props = defineProps({
  type_of_expense: { type: String, required: true, default: () => 'normal' },
  modalFormulario: { type: Boolean, required: true },
  titulo: { type: String, required: true },
  formData: { type: Object, default: () => ({}) },
  formError: { type: Object, default: () => ({}) },
  categorias: { type: Array, default: () => [] },
})

const emit = defineEmits(["modalClose", 'save', 'clearErrorForm'])

// File upload related refs
const invoiceFile = ref(null);
const invoicePreview = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadedFileData = ref(null);
const fileUploadError = ref(null);

const bs = [
  "Efectivo",
  "Tarjeta",
  "Pago móvil",
  "Transferencia",
]

const usd = [
  "Efectivo",
  "Binance",
  "PayPal",
]

const cop = [
  "Efectivo",
  "Transferencia",
]

const currencies = ["BS", "USD", "COP"];

const recurrencia = [
  "Mensual", "Semestral", "Anual"
];

const shouldShowExchangeRate = computed(() =>
{
  return props.formData.currency === "BS" || props.formData.currency === "COP";
});

const getCurrencySymbol = computed(() =>
{
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
  (newValue) =>
  {
    if (newValue !== undefined && newValue !== null) {
      const base = Number(newValue) || 0;
      props.formData.tax_amount = parseFloat((base * 0.16).toFixed(2));
      props.formData.iva = base > 0;
    }
  }
);

// Watch para calcular total automáticamente
watch(
  () => [
    props.formData.exempt_amount,
    props.formData.taxable_base,
    props.formData.tax_amount,
  ],
  () =>
  {
    const excento = Number(props.formData.exempt_amount) || 0;
    const base = Number(props.formData.taxable_base) || 0;
    const impuesto = Number(props.formData.tax_amount) || 0;
    props.formData.total_amount = parseFloat((excento + base + impuesto).toFixed(2));
  },
  { deep: true }
);

// Watch para calcular total USD automáticamente
watch(
  () => [
    props.formData.total_amount,
    props.formData.currency,
    props.formData.exchange_rate,
  ],
  () =>
  {
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
  { deep: true }
);

// Watch para limpiar tasa de cambio si la moneda es USD
watch(
  () => props.formData.currency,
  (newCurrency) =>
  {
    if (newCurrency === "USD") {
      props.formData.exchange_rate = 0;
    }
  }
);

// Watch para calcular amount_bs automáticamente cuando es deducible y la moneda no es BS
watch(
  () => [
    props.formData.is_deductible,
    props.formData.currency,
    props.formData.amount,
    props.formData.conversion_rate_to_bs,
  ],
  () =>
  {
    if (
      props.formData.is_deductible === true &&
      props.formData.currency !== "BS" &&
      props.formData.conversion_rate_to_bs > 0 &&
      props.formData.amount > 0
    ) {
      const amount = Number(props.formData.amount) || 0;
      const rate = Number(props.formData.conversion_rate_to_bs) || 0;
      props.formData.amount_bs = parseFloat((amount * rate).toFixed(2));
    } else if (props.formData.currency === "BS" && props.formData.is_deductible === true) {
      // Si la moneda es BS, amount_bs es igual a amount
      props.formData.amount_bs = parseFloat((Number(props.formData.amount) || 0).toFixed(2));
    }
  },
  { deep: true }
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
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
  const maxSize = 5 * 1024 * 1024; // 5MB

  // Check if it's a File object or a string (for previously uploaded files)
  if (file instanceof File) {
    if (!allowedTypes.includes(file.type)) {
      const allowedExtensions = allowedTypes.map(type => {
        if (type.startsWith('image/')) return type.split('/')[1].toUpperCase();
        return type.split('/')[1].toUpperCase();
      }).join(', ');
      fileUploadError.value = `Solo se permiten archivos ${allowedExtensions}`;
      invoiceFile.value = null;
      return;
    }

    if (file.size > maxSize) {
      fileUploadError.value = 'El archivo no puede superar los 5MB';
      invoiceFile.value = null;
      return;
    }

    fileUploadError.value = null;
    invoiceFile.value = file;

    // Generate preview for image files
    if (file.type.startsWith('image/')) {
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
    fileUploadError.value = 'Archivo inválido seleccionado';
    invoiceFile.value = null;
  }
};

// Upload file to server
const uploadInvoiceFile = async () => {
  if (!invoiceFile.value || !props.formData.id) {
    // If there's no ID yet (new expense), we can't upload the file yet
    // The file will be uploaded after the expense is created
    return true;
  }

  isUploading.value = true;
  uploadProgress.value = 0;
  fileUploadError.value = null;

  try {
    const formData = new FormData();
    formData.append('id', props.formData.id);
    formData.append('file_invoice', invoiceFile.value);

    // Use axios with progress tracking
    const response = await axios.post('/finances/expenses/upload-file-invoice', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      },
      onUploadProgress: progressEvent => {
        if (progressEvent.total) {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          uploadProgress.value = percentCompleted;
        }
      }
    });

    if (response.data.success) {
      uploadedFileData.value = response.data.data;
      toast.success('Factura subida correctamente');

      // Update form data with uploaded file info
      Object.assign(props.formData, {
        file_name: uploadedFileData.value.file_name,
        extension_file: uploadedFileData.value.extension_file,
        url_file: uploadedFileData.value.url,
        date_upload: new Date().toISOString()
      });

      // Clear the file input since upload is complete
      invoiceFile.value = null;

      return true;
    } else {
      throw new Error(response.data.message || 'Error al subir la factura');
    }
  } catch (error) {
    console.error('Error uploading file:', error);
    const errorMessage = error.response?.data?.message ||
                        error.message ||
                        'Error al subir el archivo. Intente nuevamente.';
    fileUploadError.value = errorMessage;
    toast.error(errorMessage);
    return false;
  } finally {
    isUploading.value = false;
    uploadProgress.value = 0;
  }
};

// Clear selected file
const clearSelectedFile = () => {
  invoiceFile.value = null;
  invoicePreview.value = null;
  fileUploadError.value = null;
};

// Clear uploaded file (remove from server)
const clearUploadedFile = async () => {
  if (!props.formData.id || !props.formData.url_file) return;

  try {
    const response = await axios.post('/finances/expenses/remove-file-invoice', {
      id: props.formData.id
    });

    if (response.data.success) {
      // Clear file references from form data
      props.formData.file_name = null;
      props.formData.extension_file = null;
      props.formData.url_file = null;
      props.formData.date_upload = null;
      uploadedFileData.value = null;
      toast.success('Factura eliminada correctamente');
    } else {
      throw new Error(response.data.message || 'Error al eliminar la factura');
    }
  } catch (error) {
    console.error('Error removing file:', error);
    const errorMessage = error.response?.data?.message ||
                         'Error al eliminar la factura. Intente nuevamente.';
    toast.error(errorMessage);
  }
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
    toast.error('El nombre del gasto es requerido');
    return;
  }

  if (!props.formData.category_id) {
    toast.error('La categoría es requerida');
    return;
  }

  if (!props.formData.total_amount || props.formData.total_amount <= 0) {
    toast.error('El monto total debe ser mayor a 0');
    return;
  }

  // Emit the save event with form data and file
  emit("save", {
    ...props.formData,
    // Pass the file to be handled by parent after expense creation
    invoice_file: invoiceFile.value
  });

  // Clear the file input
  clearSelectedFile();
}
</script>

<template>
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="props.formData.name"
              :error-messages="props.formError.name"
              label="Nombre"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="props.formData.category_id"
              label="Categoria"
              :items="props.categorias"
              :error-messages="props.formError.category_id"
              item-title="name"
              item-value="id"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="props.formData.currency"
              label="Moneda"
              :items="currencies"
              :error-messages="props.formError.currency"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-if="props.formData.currency == 'BS'"
              v-model="props.formData.count"
              label="Método de Pago"
              :items="bs"
              :error-messages="props.formError.count"
            />
            <VSelect
              v-if="props.formData.currency == 'USD'"
              v-model="props.formData.count"
              label="Método de Pago"
              :items="usd"
              :error-messages="props.formError.count"
            />
            <VSelect
              v-if="props.formData.currency == 'COP'"
              v-model="props.formData.count"
              label="Método de Pago"
              :items="cop"
              :error-messages="props.formError.count"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="type_of_expense == 'recurrente'">
            <VSelect
              v-model="props.formData.recurrence"
              label="Recurrencia"
              :items="recurrencia"
              :error-messages="props.formError.recurrencia"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="type_of_expense == 'normal'">
            <AppDateTimePicker
              v-model="props.formData.expense_date"
              :error-messages="props.formError.expense_date"
              placeholder="Fecha"
              variant="outlined"
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="props.formData.is_deductible"
              label="Es Deducible"
              :items="[
                { title: 'No', value: false },
                { title: 'Sí', value: true },
              ]"
              :error-messages="props.formError.is_deductible"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="6"
            v-if="
              props.formData.is_deductible === true &&
              props.formData.currency !== 'BS'
            "
          >
            <VTextField
              v-model.number="props.formData.conversion_rate_to_bs"
              :error-messages="props.formError.conversion_rate_to_bs"
              label="Tasa de Conversión a BS"
              type="number"
              variant="outlined"
              hint="Ingrese la tasa de cambio para convertir a Bolívares"
              persistent-hint
            />
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.exempt_amount"
              :error-messages="props.formError.exempt_amount"
              label="Monto Exento IVA"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.taxable_base"
              :error-messages="props.formError.taxable_base"
              label="Base Imponible 16%"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.tax_amount"
              :error-messages="props.formError.tax_amount"
              label="Impuesto 16%"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
              readonly
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.total_amount"
              :error-messages="props.formError.total_amount"
              label="Total Factura"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
              readonly
            />
          </VCol>
          <VCol v-if="shouldShowExchangeRate" cols="12" md="4">
            <VTextField
              v-model.number="props.formData.exchange_rate"
              :error-messages="props.formError.exchange_rate"
              label="Tasa de Cambio"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.total_usd"
              :error-messages="props.formError.total_usd"
              label="Total Referencia (USD)"
              type="number"
              variant="outlined"
              prefix="$"
              readonly
            />
          </VCol>
          <VCol cols="12">
            <VFileInput
              v-model="invoiceFile"
              label="Subir factura"
              accept="image/*"
              prepend-icon="tabler-file-invoice"
              :disabled="isUploading"
              show-size
              chips
              :error-messages="fileUploadError"
              :loading="isUploading && uploadProgress > 0"
              :progress="isUploading ? uploadProgress : undefined"
            >
            </VFileInput>

            <p class="text-caption text-grey mt-1">
              Formatos permitidos: JPG, PNG, PDF (máx. 5MB)
            </p>
          </VCol>
        </VRow>
      </VContainer>
      <VDivider class="mt-4" />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="close"
          width="100%"
          class="d-flex flex-grow-1 w-0 me-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="d-flex flex-grow-1 w-0"
          :loading="isUploading"
          :disabled="isUploading"
        >
          <template v-if="isUploading"> Subiendo factura... </template>
          <template v-else> Guardar Cambios </template>
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
