<script setup>
const props = defineProps({
  formData: { type: Object, required: true },
  suppliers: { type: Array, default: () => [] },
  loadingSuppliers: { type: Boolean, default: false },
  validationErrors: { type: Object, default: () => ({}) },
  expDateError: { type: String, default: "" },
  fileError: { type: String, default: "" },
  selectedSupplier: { type: Object, default: null },
  isInformalSupplier: { type: Boolean, default: false },
  isEditMode: { type: Boolean, default: false },
  selectedFile: { type: [Object, File, null], default: null },
});

const emit = defineEmits(["update:selectedFile", "view-current-photo"]);

const onFileChange = (file) => {
  emit("update:selectedFile", file);
};
</script>

<template>
  <div>
    <!-- SECCIÓN 1: Datos del Proveedor y Control -->
    <div class="mb-4">
      <div class="d-flex align-center gap-2 mb-3">
        <VIcon icon="tabler-building-store" size="20" color="primary" />
        <span class="text-subtitle-1 font-weight-bold">1. Información del Proveedor y Documento</span>
      </div>

      <!-- Alerta de Proveedor Informal -->
      <VAlert
        v-if="isInformalSupplier"
        color="warning"
        variant="tonal"
        icon="tabler-alert-circle"
        class="mb-3 py-2 px-3 text-body-2"
        density="compact"
      >
        <span class="font-weight-medium">Proveedor Informal:</span> 
        Los números de factura y control se han generado automáticamente bajo el formato secuencial <code>INF-YYYYMMDD-HHMMSS</code>.
      </VAlert>

      <VRow density="compact">
        <VCol cols="12" md="4">
          <VAutocomplete
            v-model="formData.supplier_id"
            :items="suppliers"
            :loading="loadingSuppliers"
            item-title="name"
            item-value="id"
            label="Proveedor *"
            placeholder="Seleccione un proveedor"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-building"
            :error-messages="validationErrors.supplier_id"
          />
        </VCol>
        <VCol cols="12" md="4">
          <VTextField
            v-model="formData.invoice_number"
            label="N° de Factura *"
            placeholder="Ej: FAC-00123"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-file-invoice"
            :disabled="isInformalSupplier"
            :error-messages="validationErrors.invoice_number"
          />
        </VCol>
        <VCol cols="12" md="4">
          <VTextField
            v-model="formData.control_number"
            label="N° de Control"
            placeholder="Ej: 00-00123"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-hash"
            :disabled="isInformalSupplier"
            :error-messages="validationErrors.control_number"
          />
        </VCol>

        <!-- Campo para Subir / Ver Documento PDF de la Factura -->
        <VCol cols="12" class="mt-1">
          <div class="d-flex flex-column gap-1">
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
              <span class="text-caption font-weight-bold text-high-emphasis">
                Documento Digital de la Factura (PDF / Imagen) <span v-if="!formData.invoice_photo" class="text-error">*</span>
              </span>
              <div v-if="formData.invoice_photo" class="d-flex align-center gap-2">
                <VChip
                  size="x-small"
                  color="success"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  <VIcon start size="14">tabler-file-check</VIcon>
                  Archivo Precargado
                </VChip>
                <VBtn
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="font-weight-bold"
                  @click="emit('view-current-photo')"
                >
                  <VIcon start size="14">tabler-eye</VIcon>
                  Ver Documento
                </VBtn>
              </div>
            </div>

            <VFileInput
              :model-value="selectedFile"
              :label="formData.invoice_photo ? 'Reemplazar documento actual (PDF o Imagen)...' : 'Subir archivo PDF / Foto de la Factura *'"
              accept=".pdf,image/*"
              prepend-icon=""
              prepend-inner-icon="tabler-file-type-pdf"
              variant="outlined"
              density="compact"
              clearable
              show-size
              :error="Boolean(fileError || validationErrors.invoice_photo)"
              :error-messages="fileError || validationErrors.invoice_photo"
              @update:model-value="onFileChange"
            />
          </div>
        </VCol>
      </VRow>
    </div>

    <!-- SECCIÓN 2: Fechas y Condiciones de Pago -->
    <div class="mb-2">
      <div class="d-flex align-center gap-2 mb-3">
        <VIcon icon="tabler-calendar-due" size="20" color="primary" />
        <span class="text-subtitle-1 font-weight-bold">2. Fechas y Condiciones de Pago</span>
      </div>

      <VRow density="compact">
        <VCol cols="12" sm="6" md="3">
          <VTextField
            v-model="formData.created_invoice_date"
            label="F. de Emisión *"
            type="date"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-calendar-event"
            :error-messages="validationErrors.created_invoice_date"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VTextField
            v-model="formData.received_date"
            label="F. de Recepción"
            type="date"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-calendar-down"
            :error-messages="validationErrors.received_date"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VTextField
            v-model="formData.exp_date"
            label="F. de Vencimiento Factura"
            type="date"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-calendar-time"
            :error="!!expDateError"
            :error-messages="validationErrors.exp_date || expDateError"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VTextField
            v-model="formData.payment_date"
            label="Fecha Límite de Pago"
            type="date"
            variant="filled"
            density="compact"
            readonly
            prepend-inner-icon="tabler-cash-banknote"
            hint="Auto-calculada por reglas"
            persistent-hint
            :error-messages="validationErrors.payment_date"
          />
        </VCol>
      </VRow>
    </div>
  </div>
</template>
