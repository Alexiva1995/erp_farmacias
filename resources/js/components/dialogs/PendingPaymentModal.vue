<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  paymentGroup: {
    type: Object,
    default: null,
  },
  invoices: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue", "close"]);

// Headers de la tabla de facturas
const invoiceHeaders = [
  { title: "ID", key: "id", sortable: false },
  { title: "N° Factura", key: "invoice_number", sortable: false },
  { title: "N° Control", key: "control_number", sortable: false },
  { title: "Monto", key: "total_amount", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Fecha Vencimiento", key: "exp_date", sortable: false },
  { title: "Estado", key: "status", sortable: false },
];

// Cerrar modal
const closeModal = () => {
  emit("update:modelValue", false);
  emit("close");
};

// Formatear fecha
const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("es-VE");
};

// Formatear moneda
const formatCurrency = (amount, currency) => {
  if (!amount) return "N/A";
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: currency === "Bs" ? "VES" : currency === "COP" ? "COP" : "USD",
  }).format(amount);
};

// Formatear estado
const formatStatus = (status) => {
  const statusMap = {
    pending: "Pendiente",
    to_order: "Por Ordenar",
    ordered: "Ordenado",
  };
  return statusMap[status] || status;
};

// Obtener color del estado
const getStatusColor = (status) => {
  const colorMap = {
    pending: "warning",
    to_order: "info",
    ordered: "success",
  };
  return colorMap[status] || "default";
};

// Total de facturas
const totalAmount = computed(() => {
  return props.invoices.reduce(
    (sum, invoice) => sum + (invoice.total_amount || 0),
    0
  );
});

// Moneda principal
const mainCurrency = computed(() => {
  return props.paymentGroup?.currency || "USD";
});

// Información del proveedor
const supplierInfo = computed(() => {
  if (!props.paymentGroup) return null;
  return {
    name: props.paymentGroup.supplier_name,
    paymentDate: props.paymentGroup.payment_date,
    currency: props.paymentGroup.currency,
    invoiceCount: props.paymentGroup.invoice_count,
  };
});
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="900px"
    persistent
    @update:model-value="closeModal"
    scrollable
  >
    <VCard class="d-flex flex-column">
      <!-- Header -->
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-receipt" class="me-2" />
        <span class="text-h5 font-weight-bold">Facturas Pendientes</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <!-- Contenido -->
      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <!-- Información del proveedor -->
        <div v-if="supplierInfo" class="mb-6">
          <VCard variant="tonal" color="primary">
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="text-h6 font-weight-bold">
                    {{ supplierInfo.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Fecha de Pago: {{ formatDate(supplierInfo.paymentDate) }}
                  </div>
                </VCol>
                <VCol cols="12" md="3">
                  <div class="text-caption text-medium-emphasis">
                    Total Facturas
                  </div>
                  <div class="text-h6 font-weight-bold">
                    {{ supplierInfo.invoiceCount }}
                  </div>
                </VCol>
                <VCol cols="12" md="3">
                  <div class="text-caption text-medium-emphasis">
                    Monto Total
                  </div>
                  <div class="text-h6 font-weight-bold text-success">
                    {{ formatCurrency(totalAmount, supplierInfo.currency) }}
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </div>

        <!-- Tabla de facturas -->
        <div v-if="invoices.length > 0">
          <VDataTable
            :headers="invoiceHeaders"
            :items="invoices"
            density="compact"
            hide-default-footer
            class="mb-4"
          >
            <!-- Columna de monto -->
            <template #item.total_amount="{ item }">
              <div class="font-weight-bold">
                {{ formatCurrency(item.total_amount, item.currency) }}
              </div>
            </template>

            <!-- Columna de fecha de vencimiento -->
            <template #item.exp_date="{ item }">
              <div>
                {{ formatDate(item.exp_date) }}
              </div>
            </template>

            <!-- Columna de estado -->
            <template #item.status="{ item }">
              <VChip
                :color="getStatusColor(item.status)"
                size="small"
                variant="tonal"
              >
                {{ formatStatus(item.status) }}
              </VChip>
            </template>
          </VDataTable>

          <!-- Resumen -->
          <VCard variant="outlined" color="success">
            <VCardText>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-h6 font-weight-bold">Total a Pagar</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ invoices.length }} factura(s) pendiente(s)
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-h5 font-weight-bold text-success">
                    {{ formatCurrency(totalAmount, mainCurrency) }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ mainCurrency }}
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>

        <!-- Estado vacío -->
        <div v-else class="text-center py-8">
          <VIcon
            icon="tabler-receipt-off"
            size="48"
            class="text-disabled mb-4"
          />
          <div class="text-h6 text-disabled">No hay facturas</div>
          <div class="text-caption text-disabled">
            No se encontraron facturas para este grupo de pago
          </div>
        </div>
      </VCardText>

      <!-- Footer -->
      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="outlined" @click="closeModal"> Cerrar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-medium-emphasis {
  opacity: 0.7;
}
</style>
