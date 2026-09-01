<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

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

const { mobile } = useDisplay();

// Headers de la tabla de facturas
const invoiceHeaders = [
  { title: "N° Factura", key: "invoice_number", sortable: false },
  { title: "Monto", key: "total_amount", sortable: false, align: "end" },
  { title: "Vencimiento", key: "exp_date", sortable: false },
  { title: "Estado", key: "status", sortable: false, align: "center" },
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
  if (!amount && amount !== 0) return "N/A";
  const validCurrency = currency === "Bs" ? "VES" : currency === "COP" ? "COP" : "USD";
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: validCurrency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
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
    (sum, invoice) => sum + (parseFloat(invoice.total_amount) || 0),
    0
  );
});

// Moneda principal
const mainCurrency = computed(() => {
  return props.paymentGroup?.currency || "USD";
});

const getAvatarColor = (name) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  const hash = (name || "").split("").reduce((a, b) => a + b.charCodeAt(0), 0);
  return colors[hash % colors.length];
};

const openInvoiceTab = (item) => {
  const invoiceId = item.id || item.invoice_id;
  if (invoiceId) {
    window.open(`/invoice/invoices?id=${invoiceId}`, "_blank");
  } else {
    window.open(`/invoice/invoices?search=${encodeURIComponent(item.invoice_number)}`, "_blank");
  }
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    max-width="800px"
    persistent
    scrollable
    @update:model-value="closeModal"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Barra Superior Premium (Móvil) -->
      <VToolbar v-if="mobile" color="primary" flat>
        <VBtn icon @click="closeModal">
          <VIcon icon="tabler-x" />
        </VBtn>
        <VToolbarTitle class="text-sm font-weight-black uppercase">Detalle de Pago</VToolbarTitle>
      </VToolbar>

      <!-- Cabecera Premium (Escritorio) -->
      <VCardTitle v-else class="pa-6 pb-2 d-flex align-center">
        <VAvatar color="primary" variant="tonal" size="44" class="me-4 rounded-lg">
          <VIcon icon="tabler-receipt" size="24" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-lg font-weight-black uppercase leading-none mb-1">Facturas Pendientes</span>
          <span class="text-xs text-disabled font-weight-medium">Desglose de deuda por proveedor</span>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="secondary"
          size="32"
          class="rounded-lg"
          @click="closeModal"
        />
      </VCardTitle>

      <VCardText class="pa-6">
        <!-- Info del Proveedor Card -->
        <VCard v-if="paymentGroup" variant="tonal" color="primary" class="rounded-xl border-0 mb-6 overflow-hidden">
          <div class="pa-5">
            <VRow align="center">
              <VCol cols="12" md="6" class="d-flex align-center gap-4">
                <VAvatar :color="getAvatarColor(paymentGroup.supplier_name)" variant="flat" size="48" class="rounded-xl shadow-sm text-white">
                  {{ paymentGroup.supplier_name.charAt(0).toUpperCase() }}
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-lg font-weight-black text-primary leading-tight text-capitalize">{{ paymentGroup.supplier_name }}</span>
                  <span class="text-xs font-weight-bold text-disabled uppercase mt-1">
                    FECHA PAGO: {{ formatDate(paymentGroup.payment_date) }}
                  </span>
                </div>
              </VCol>
              <VCol cols="6" md="3" class="text-center md:text-left">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Total Facturas</span>
                <span class="text-xl font-weight-black text-primary">{{ paymentGroup.invoice_count }}</span>
              </VCol>
              <VCol cols="6" md="3" class="text-end">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Monto Consolidado</span>
                <span class="text-xl font-weight-black text-success">{{ formatCurrency(totalAmount, mainCurrency) }}</span>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Tabla de Facturas Premium -->
        <div v-if="invoices.length > 0" class="rounded-xl border-0 overflow-hidden shadow-sm">
          <VDataTable
            :headers="invoiceHeaders"
            :items="invoices"
            density="comfortable"
            hide-default-footer
            class="premium-detail-table font-weight-medium"
          >
            <template #item.invoice_number="{ item }">
              <a
                href="javascript:void(0)"
                class="text-xs font-weight-black text-primary text-decoration-none d-inline-flex align-center gap-1 cursor-pointer"
                @click.stop="openInvoiceTab(item)"
              >
                <span>{{ item.invoice_number }}</span>
                <VIcon icon="tabler-external-link" size="13" class="opacity-75" />
                <VTooltip activator="parent" location="top">Abrir factura en nueva pestaña</VTooltip>
              </a>
            </template>
            
            <template #item.total_amount="{ item }">
              <span class="text-xs font-weight-black">{{ formatCurrency(item.total_amount, item.currency) }}</span>
            </template>

            <template #item.exp_date="{ item }">
              <span class="text-xs text-disabled">{{ formatDate(item.exp_date) }}</span>
            </template>

            <template #item.status="{ item }">
              <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="rounded font-weight-black uppercase">
                {{ formatStatus(item.status) }}
              </VChip>
            </template>
          </VDataTable>

          <!-- Resumen Inferior Estilizado -->
          <div class="pa-6 bg-success-opacity-1 mt-4 rounded-xl border border-dashed border-success">
            <div class="d-flex justify-space-between align-center">
              <div class="d-flex align-center gap-3">
                <VAvatar color="success" variant="tonal" size="38" class="rounded-lg">
                  <VIcon icon="tabler-sum" size="20" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-sm font-weight-black uppercase text-success leading-tight">Total Consolidado</span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase">{{ invoices.length }} FACTURA(S)</span>
                </div>
              </div>
              <div class="text-end">
                <div class="text-h5 font-weight-black text-success leading-none mb-1">
                  {{ formatCurrency(totalAmount, mainCurrency) }}
                </div>
                <span class="text-xs font-weight-black text-disabled uppercase">{{ mainCurrency }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado Vacío -->
        <div v-else class="text-center py-12 opacity-50">
          <VIcon icon="tabler-receipt-off" size="64" class="mb-4" />
          <div class="text-lg font-weight-black uppercase">Sin registros</div>
          <span class="text-sm">No hay facturas asociadas a este grupo.</span>
        </div>
      </VCardText>

      <VCardActions v-if="!mobile" class="pa-6 pt-0">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          class="rounded-lg font-weight-black text-xs px-8"
          @click="closeModal"
        >
          CERRAR VENTANA
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

.leading-tight {
  line-height: 1.25;
}

.bg-success-opacity-1 {
  background-color: rgba(var(--v-theme-success), 0.03) !important;
}

.premium-detail-table :deep(thead th) {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  text-transform: uppercase !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  height: 48px !important;
}

.border-dashed {
  border-style: dashed !important;
}
</style>
