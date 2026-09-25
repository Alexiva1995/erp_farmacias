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
  <VCardText class="header-section py-2 px-4">
    <div class="command-header-toolbar d-flex flex-wrap align-center justify-space-between ga-2">
      <!-- Bloque Izquierdo: Volver + Proveedor + Acciones + Identificadores -->
      <div class="d-flex flex-wrap align-center ga-2">
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          size="small"
          color="secondary"
          class="header-btn"
          title="Volver a la lista"
          @click="emit('backToList')"
        />

        <div class="d-flex align-center ga-1">
          <span class="text-subtitle-1 font-weight-black text-primary text-uppercase px-1 header-supplier-name">
            {{ invoice.supplier?.name }}
          </span>
          <VTooltip text="Ver historial de auditoría">
            <template #activator="{ props: tipProps }">
              <VBtn
                v-bind="tipProps"
                icon="tabler-eye"
                size="small"
                color="secondary"
                variant="tonal"
                class="header-btn"
                @click="emit('showAudit')"
              />
            </template>
          </VTooltip>
          <VTooltip v-if="invoice.invoice_photo" text="Ver documento original / PDF">
            <template #activator="{ props: tipProps }">
              <VBtn
                v-bind="tipProps"
                icon="tabler-file-type-pdf"
                size="small"
                color="error"
                variant="tonal"
                class="header-btn"
                @click="emit('viewPdf', invoice.invoice_photo)"
              />
            </template>
          </VTooltip>
        </div>

        <VChip size="small" color="error" variant="tonal" class="font-weight-bold border header-chip">
          Control: {{ invoice.control_number || 'S/N' }}
        </VChip>
        <VChip size="small" color="primary" variant="tonal" class="font-weight-bold border header-chip">
          Factura: {{ invoice.invoice_number }}
        </VChip>
      </div>

      <!-- Bloque Derecho: Fechas Operativas + Estado Actual + Botón Editar -->
      <div class="d-flex flex-wrap align-center ga-2 justify-end">
        <VChip size="small" variant="outlined" color="secondary" class="font-weight-medium header-chip">
          <VIcon start icon="tabler-calendar" size="14" />
          Emisión: {{ formatDate(invoice.created_invoice_date) || "N/A" }}
        </VChip>
        <VChip size="small" variant="outlined" color="info" class="font-weight-medium header-chip">
          <VIcon start icon="tabler-download" size="14" />
          Recibo: {{ formatDate(invoice.received_date) || "N/A" }}
        </VChip>
        <VChip
          size="small"
          :variant="isInvoiceDueSoon ? 'flat' : 'outlined'"
          :color="isInvoiceDueSoon ? 'error' : 'warning'"
          class="font-weight-bold header-chip"
        >
          <VIcon start :icon="isInvoiceDueSoon ? 'tabler-alert-triangle' : 'tabler-calendar-due'" size="14" />
          Vence: {{ formatDate(invoice.payment_date || invoice.exp_date) || "N/A" }}
        </VChip>

        <VChip
          size="small"
          :color="isEditMode ? 'primary' : isApprovalMode ? 'warning' : isLocationMode ? 'info' : 'secondary'"
          variant="flat"
          class="font-weight-black uppercase header-chip"
        >
          <VIcon start :icon="isEditMode ? 'tabler-edit' : isApprovalMode ? 'tabler-check' : isLocationMode ? 'tabler-map-pin' : 'tabler-eye'" size="14" />
          {{ isEditMode ? 'Edición' : isApprovalMode ? 'Aprobación' : isLocationMode ? 'Ubicación' : 'Lectura' }}
        </VChip>

        <VBtn
          v-if="isEditableMode && !isEditMode"
          color="primary"
          variant="flat"
          size="small"
          class="font-weight-bold header-action-btn"
          @click="emit('toggleEdit', true)"
        >
          <VIcon icon="tabler-edit" class="me-1" size="15" />
          Editar Factura
        </VBtn>
      </div>
    </div>
  </VCardText>
</template>

<style scoped>
.command-header-toolbar {
  min-height: 36px;
}

.header-supplier-name {
  line-height: 1.2;
}

.header-chip,
.header-action-btn {
  height: 30px !important;
  display: inline-flex;
  align-items: center;
  border-radius: 5px !important;
}

.header-btn {
  height: 30px !important;
  width: 30px !important;
  min-width: 30px !important;
  border-radius: 5px !important;
}
</style>
