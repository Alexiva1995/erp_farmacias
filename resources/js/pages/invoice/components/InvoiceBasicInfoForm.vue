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
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-building-store" size="20" color="primary" />
          <span class="text-subtitle-1 font-weight-bold">1. Información del Proveedor y Documento</span>
        </div>
        <div class="d-flex align-center gap-2">
          <VCheckbox
            v-model="formData.is_fiscal"
            label="Fiscal"
            color="primary"
            density="compact"
            hide-details
            class="mt-0"
          />
        </div>
      </div>

      <!-- Alerta de Compra Informal / No Fiscal -->
      <VAlert
        v-if="!formData.is_fiscal"
        color="info"
        variant="tonal"
        icon="tabler-info-circle"
        class="mb-3 py-2 px-3 text-body-2"
        density="compact"
      >
        <span class="font-weight-medium">Modo Compra Informal / No Fiscal:</span> 
        Número correlativo sugerido automáticamente, sin exigir N° de control. La fecha de recepción se aplicará automáticamente a emisión, vencimiento y pago.
      </VAlert>

      <VRow density="compact">
        <VCol cols="12" :md="formData.is_fiscal ? 4 : 6">
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
        <VCol cols="12" :md="formData.is_fiscal ? 4 : 6">
          <VTextField
            v-model="formData.invoice_number"
            :label="formData.is_fiscal ? 'N° de Factura *' : 'N° de Factura / Nota (Auto) *'"
            placeholder="Ej: FAC-00123"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-file-invoice"
            :error-messages="validationErrors.invoice_number"
          />
        </VCol>
        <VCol v-if="formData.is_fiscal" cols="12" md="4">
          <VTextField
            v-model="formData.control_number"
            label="N° de Control"
            placeholder="Ej: 00-00123"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-hash"
            :error-messages="validationErrors.control_number"
          />
        </VCol>

        <!-- Campo para Subir / Ver Documento PDF de la Factura -->
        <VCol cols="12" class="mt-1">
          <div class="d-flex flex-column gap-1">
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
              <span class="text-caption font-weight-bold text-high-emphasis">
                Documento Digital de la Factura (PDF / Imagen)
                <span v-if="formData.is_fiscal && !formData.invoice_photo" class="text-error">*</span>
                <span v-else-if="!formData.is_fiscal" class="text-caption text-medium-emphasis ms-1 font-weight-regular">(Opcional)</span>
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
              :label="formData.invoice_photo ? 'Reemplazar documento actual (PDF o Imagen)...' : (formData.is_fiscal ? 'Subir archivo PDF / Foto de la Factura *' : 'Subir archivo PDF / Foto de la Factura o Nota (Opcional)')"
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

    <!-- SECCIÓN: Clasificación de la Factura (Factura de Gasto / ISLR) -->
    <VCard variant="tonal" color="primary" class="mb-4 pa-3 rounded-lg border-primary">
      <VRow density="compact" align="center">
        <VCol cols="12" sm="6">
          <div class="d-flex align-center">
            <VCheckbox
              v-model="formData.is_expense"
              color="primary"
              hide-details
              density="compact"
              @update:model-value="(val) => { if (!val) formData.has_islr_retention = false; }"
            >
              <template #label>
                <div class="d-flex flex-column ms-1">
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">¿Es Factura de Tipo Gasto?</span>
                  <span class="text-caption text-medium-emphasis">Se ordenará automáticamente sin requerir recepción de lotes ni inventario.</span>
                </div>
              </template>
            </VCheckbox>
          </div>
        </VCol>

        <VCol cols="12" sm="6">
          <VExpandTransition>
            <div v-if="formData.is_expense" class="d-flex align-center ps-sm-4 border-sm-s">
              <VCheckbox
                v-model="formData.has_islr_retention"
                color="info"
                hide-details
                density="compact"
              >
                <template #label>
                  <div class="d-flex flex-column ms-1">
                    <span class="text-subtitle-2 font-weight-bold text-info">¿Genera Retención de ISLR?</span>
                    <span class="text-caption text-medium-emphasis">Se habilitará en el módulo de Retenciones para comprobante ISLR.</span>
                  </div>
                </template>
              </VCheckbox>
            </div>
          </VExpandTransition>
        </VCol>
      </VRow>
    </VCard>

    <!-- SECCIÓN 2: Fechas y Condiciones de Pago -->
    <div class="mb-2">
      <div class="d-flex align-center gap-2 mb-3">
        <VIcon icon="tabler-calendar-due" size="20" color="primary" />
        <span class="text-subtitle-1 font-weight-bold">2. Fechas y Condiciones de Pago</span>
      </div>

      <!-- Modo Fiscal: 4 campos de fechas -->
      <VRow v-if="formData.is_fiscal" density="compact">
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

      <!-- Modo No Fiscal / Informal: Solo Fecha de Recepción -->
      <VRow v-else density="compact">
        <VCol cols="12" sm="6">
          <VTextField
            v-model="formData.received_date"
            label="Fecha de Recepción *"
            type="date"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-calendar-down"
            hint="Esta fecha se asigna automáticamente a emisión, vencimiento y pago"
            persistent-hint
            :error-messages="validationErrors.received_date"
          />
        </VCol>
      </VRow>
    </div>
  </div>
</template>
