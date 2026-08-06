<script setup>
import { computed } from "vue";

const props = defineProps({
  formData: { type: Object, required: true },
  suppliers: { type: Array, default: () => [] },
  loadingSuppliers: { type: Boolean, default: false },
  validationErrors: { type: Object, default: () => ({}) },
  expDateError: { type: String, default: "" },
  selectedSupplier: { type: Object, default: null },
  isInformalSupplier: { type: Boolean, default: false },
  isEditMode: { type: Boolean, default: false },
});

const translatePaymentMethodType = (type) => {
  const translations = {
    invoice_date: "Fecha de factura",
    early_payment: "Pronto Pago",
    custom: "Personalizado",
  };
  return translations[type] || type;
};
</script>

<template>
  <div>
    <!-- Datos Principales de Proveedor y Num. Factura -->
    <VRow density="compact" class="mb-2">
      <VCol cols="12" md="4">
        <VAutocomplete
          v-model="formData.supplier_id"
          :items="suppliers"
          :loading="loadingSuppliers"
          item-title="name"
          item-value="id"
          label="Proveedor"
          placeholder="Busque un proveedor"
          :error-messages="validationErrors.supplier_id"
        />
      </VCol>
      <VCol cols="12" md="4">
        <VTextField
          v-model="formData.invoice_number"
          label="N° de factura"
          :disabled="isInformalSupplier"
          :error-messages="validationErrors.invoice_number"
        />
      </VCol>
      <VCol cols="12" md="4">
        <VTextField
          v-model="formData.control_number"
          label="N° de Control"
          :disabled="isInformalSupplier"
          :error-messages="validationErrors.control_number"
        />
      </VCol>
    </VRow>

    <!-- Fechas del Registro -->
    <VRow density="compact" class="mb-2">
      <VCol cols="12" md="3">
        <VTextField
          v-model="formData.created_invoice_date"
          label="F. de Emisión"
          type="date"
          :error-messages="validationErrors.created_invoice_date"
        />
      </VCol>
      <VCol cols="12" md="3">
        <VTextField
          v-model="formData.received_date"
          label="F. de Recibo"
          type="date"
          :error-messages="validationErrors.received_date"
        />
      </VCol>
      <VCol cols="12" md="3">
        <VTextField
          v-model="formData.exp_date"
          label="Vencimiento"
          type="date"
          :error="!!expDateError"
          :error-messages="validationErrors.exp_date || expDateError"
        />
      </VCol>
      <VCol cols="12" md="3">
        <VTextField
          v-model="formData.payment_date"
          label="Fecha de Pago"
          type="date"
          hint="Auto-calculado"
          persistent-hint
          readonly
          variant="filled"
          :error-messages="validationErrors.payment_date"
        />
      </VCol>
    </VRow>

    <!-- Alerta Informativa de Configuración de Pago del Proveedor -->
    <VRow v-if="!isEditMode && selectedSupplier" class="mb-4">
      <VCol cols="12">
        <VAlert
          color="primary"
          variant="tonal"
          icon="tabler-info-circle"
          class="rounded pa-3"
        >
          <div class="d-flex align-center flex-wrap gap-2">
            <span class="text-body-2 font-weight-medium">Configuración de Pago:</span>
            <VChip color="primary" size="small" variant="flat" class="ml-2">
              {{ translatePaymentMethodType(selectedSupplier.payment_due_type) || "No definido" }}
            </VChip>
            <VChip v-if="selectedSupplier.custom_due_days" color="secondary" size="small" variant="tonal">
              {{ selectedSupplier.custom_due_days }} días
            </VChip>
            <VSpacer />
            <span class="text-caption text-medium-emphasis italic">
              La fecha de pago se recalcula automáticamente basado en estas reglas.
            </span>
          </div>
        </VAlert>
      </VCol>
    </VRow>
  </div>
</template>
