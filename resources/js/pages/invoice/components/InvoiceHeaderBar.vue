<script setup>
// Barra de cabecera de la factura (Command Header Bar)
const props = defineProps({
  invoice: {
    type: Object,
    required: true,
  },
  isPdfSidePanelOpen: {
    type: Boolean,
    default: false,
  },
  isInvoiceDueSoon: {
    type: Boolean,
    default: false,
  },
  isEditMode: {
    type: Boolean,
    default: false,
  },
  isApprovalMode: {
    type: Boolean,
    default: false,
  },
  isLocationMode: {
    type: Boolean,
    default: false,
  },
  isEditableMode: {
    type: Boolean,
    default: false,
  },
  formatDate: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'backToList',
  'showAudit',
  'viewPdf',
  'toggleEdit',
])
</script>

<template>
  <VCardText class="header-section py-3 px-4">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3">
      <!-- Bloque Izquierdo: Volver + Proveedor + Acciones Documentales + Identificadores -->
      <div class="d-flex flex-wrap align-center ga-2 ga-sm-3">
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          size="small"
          color="secondary"
          title="Volver a la lista"
          @click="emit('backToList')"
        />
        <div class="d-flex align-center ga-2">
          <span class="text-h5 font-weight-black text-primary text-uppercase">{{ invoice.supplier?.name }}</span>
          <VTooltip text="Ver historial de auditoría">
            <template #activator="{ props: tipProps }">
              <VBtn
                v-bind="tipProps"
                icon="tabler-eye"
                size="x-small"
                color="secondary"
                variant="tonal"
                @click="emit('showAudit')"
              />
            </template>
          </VTooltip>
          <VTooltip v-if="invoice.invoice_photo" text="Ver documento original / PDF">
            <template #activator="{ props: tipProps }">
              <VBtn
                v-bind="tipProps"
                icon="tabler-file-type-pdf"
                size="x-small"
                color="error"
                variant="tonal"
                @click="emit('viewPdf', invoice.invoice_photo)"
              />
            </template>
          </VTooltip>
        </div>
        <div class="d-flex align-center ga-2 ms-sm-2">
          <VChip size="small" color="error" variant="tonal" class="font-weight-bold border">
            Control: {{ invoice.control_number || 'S/N' }}
          </VChip>
          <VChip size="small" color="primary" variant="tonal" class="font-weight-bold border">
            Factura: {{ invoice.invoice_number }}
          </VChip>
        </div>
      </div>

      <!-- Bloque Derecho: Fechas Operativas + Estado Actual + Botón Editar -->
      <div class="d-flex flex-wrap align-center ga-2 justify-end">
        <VChip size="small" variant="outlined" color="secondary" class="font-weight-medium">
          <VIcon start icon="tabler-calendar" size="13" />
          Emisión: {{ formatDate(invoice.created_invoice_date) || "N/A" }}
        </VChip>
        <VChip size="small" variant="outlined" color="info" class="font-weight-medium">
          <VIcon start icon="tabler-download" size="13" />
          Recibo: {{ formatDate(invoice.received_date) || "N/A" }}
        </VChip>
        <VChip
          size="small"
          :variant="isInvoiceDueSoon ? 'flat' : 'outlined'"
          :color="isInvoiceDueSoon ? 'error' : 'warning'"
          class="font-weight-bold"
        >
          <VIcon start :icon="isInvoiceDueSoon ? 'tabler-alert-triangle' : 'tabler-calendar-due'" size="13" />
          Vence: {{ formatDate(invoice.payment_date || invoice.exp_date) || "N/A" }}
        </VChip>

        <VChip
          size="small"
          :color="isEditMode ? 'primary' : isApprovalMode ? 'warning' : isLocationMode ? 'info' : 'secondary'"
          variant="flat"
          class="font-weight-black uppercase"
        >
          <VIcon start :icon="isEditMode ? 'tabler-edit' : isApprovalMode ? 'tabler-check' : isLocationMode ? 'tabler-map-pin' : 'tabler-eye'" size="13" />
          {{ isEditMode ? 'Edición' : isApprovalMode ? 'Aprobación' : isLocationMode ? 'Ubicación' : 'Lectura' }}
        </VChip>

        <VBtn
          v-if="isEditableMode && !isEditMode"
          color="primary"
          variant="flat"
          size="small"
          class="font-weight-bold ms-1"
          @click="emit('toggleEdit', true)"
        >
          <VIcon icon="tabler-edit" class="me-1" size="15" />
          Editar Factura
        </VBtn>
      </div>
    </div>
  </VCardText>
</template>
