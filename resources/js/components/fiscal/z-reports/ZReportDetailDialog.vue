<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  report: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const printTicket = () => {
  window.print();
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden">
      <!-- Encabezado Modal -->
      <VCardItem class="bg-primary text-white py-3 px-4">
        <template #prepend>
          <VIcon icon="tabler-receipt" size="24" class="me-2" />
        </template>
        <VCardTitle class="text-white font-weight-black text-subtitle-1">
          REPORTE Z {{ props.report?.report_number_padded || `Z${String(props.report?.report_number || '').padStart(6, '0')}` }}
        </VCardTitle>
        <template #append>
          <VBtn
            icon
            variant="text"
            color="white"
            size="small"
            @click="emit('update:modelValue', false)"
          >
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="pa-4 bg-background">
        <!-- Ticket Térmico Z -->
        <div class="fiscal-ticket pa-4 rounded-lg bg-surface border">
          <div class="text-center mb-3">
            <div class="font-weight-black text-uppercase text-body-1">
              FARMACIA BARRIO SUCRE 2024, C.A.
            </div>
            <div class="text-caption text-disabled">RIF: J-50474660-6</div>
            <div class="text-caption text-disabled">CORTE FISCAL DIARIO</div>
            <div class="text-h6 font-weight-black text-primary mt-1">
              REPORTE Z N° {{ props.report?.report_number_padded || `Z${String(props.report?.report_number || '').padStart(6, '0')}` }}
            </div>
          </div>

          <VDivider class="border-dashed my-2" />

          <!-- Datos de Fecha y Auditoría -->
          <div class="d-flex flex-column gap-1 text-caption mb-3">
            <div class="d-flex justify-space-between">
              <span class="text-disabled">FECHA DE EMISIÓN:</span>
              <span class="font-weight-bold">{{ props.report?.report_date }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">HORA DE APERTURA:</span>
              <span>{{ props.report?.opening_time || '00:00:00' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">HORA DE CIERRE:</span>
              <span>{{ props.report?.closing_time || '23:59:59' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">PRIMERA FACTURA:</span>
              <span class="font-weight-medium">{{ props.report?.first_invoice_number || 'N/A' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">ÚLTIMA FACTURA:</span>
              <span class="font-weight-medium">{{ props.report?.last_invoice_number || 'N/A' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">CANTIDAD DE FACTURAS:</span>
              <span class="font-weight-bold text-info">{{ props.report?.invoices_count || 0 }} DOCS</span>
            </div>
          </div>

          <VDivider class="border-dashed my-2" />

          <!-- Desglose Fiscal -->
          <div class="d-flex flex-column gap-1 text-sm">
            <div class="d-flex justify-space-between">
              <span class="font-weight-medium">VENTAS EXENTAS (E):</span>
              <span class="font-weight-bold">Bs. {{ formatCurrency(props.report?.exempt_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="font-weight-medium">BASE IMPONIBLE (G 16%):</span>
              <span class="font-weight-bold">Bs. {{ formatCurrency(props.report?.base_16_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="font-weight-medium text-warning">IMPUESTO IVA (G 16%):</span>
              <span class="font-weight-bold text-warning">Bs. {{ formatCurrency(props.report?.iva_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="font-weight-medium">BASE IGTF / SPE:</span>
              <span class="font-weight-bold">Bs. {{ formatCurrency(props.report?.igtf_base_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="font-weight-medium text-error">IGTF PERCIBIDO (3%):</span>
              <span class="font-weight-bold text-error">Bs. {{ formatCurrency(props.report?.igtf_amount) }}</span>
            </div>
          </div>

          <VDivider class="border-dashed my-3" />

          <!-- Total Final -->
          <div class="d-flex justify-space-between align-center py-2 px-3 rounded bg-success-tonal">
            <span class="text-subtitle-1 font-weight-black">TOTAL REPORTE Z:</span>
            <span class="text-h6 font-weight-black text-success">
              Bs. {{ formatCurrency(props.report?.total_amount) }}
            </span>
          </div>

          <div class="text-center text-super-xs text-disabled mt-3">
            DOCUMENTO FISCAL DE CIERRE DIARIO GENERADO AUTOMÁTICAMENTE
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-4 pt-0 d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          Cerrar
        </VBtn>
        <VBtn
          variant="flat"
          color="primary"
          prepend-icon="tabler-printer"
          @click="printTicket"
        >
          Imprimir Ticket
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.fiscal-ticket {
  font-family: monospace;
}

.text-super-xs {
  font-size: 0.65rem;
}

.bg-success-tonal {
  background-color: rgba(var(--v-theme-success), 0.12);
}
</style>
