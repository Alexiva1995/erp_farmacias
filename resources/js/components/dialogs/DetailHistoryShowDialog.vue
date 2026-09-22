<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  historyName: { type: String, default: "" },
  details: { type: Array, default: () => [] },
  historyId: { type: Number },
  histories: { type: Object, default: () => ({}) },
  user: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const { mobile } = useDisplay();

const isDialogVisible = computed({
  get() {
    return props.modelValue;
  },
  set(value) {
    emit("update:modelValue", value);
  },
});

const close = () => {
  emit("update:modelValue", false);
};

const formatCurrency = (val) => {
  const num = parseFloat(val) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return "—";
  if (typeof dateStr === "string" && /^\d{4}-\d{2}-\d{2}$/.test(dateStr.trim())) {
    const [y, m, d] = dateStr.trim().split("-");
    return `${d}/${m}/${y}`;
  }
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return String(dateStr);
  return d.toLocaleString("es-VE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  }).replace(",", " ·");
};

// Datos del ticket fiscal
const invoiceNumber = computed(() => {
  return props.histories?.invoice_number || props.histories?.fiscal_id || (props.historyId ? String(props.historyId) : "—");
});

const clientName = computed(() => {
  return props.histories?.business_name || props.historyName || "N/A";
});

const clientRif = computed(() => {
  return props.histories?.identification || "N/A";
});

const cashierName = computed(() => {
  return props.user?.username || props.histories?.user?.username || "—";
});

const invoiceDate = computed(() => {
  return formatDateTime(props.histories?.invoice_date || props.histories?.created_at);
});

// Desglose fiscal
const exemptAmount = computed(() => parseFloat(props.histories?.exempt_amount) || 0);
const taxableAmount = computed(() => parseFloat(props.histories?.taxable_amount ?? props.histories?.taxable_base) || 0);
const ivaAmount = computed(() => parseFloat(props.histories?.iva_amount) || 0);
const igtfAmount = computed(() => parseFloat(props.histories?.spe_surcharge_amount) || 0);
const subtotalAmount = computed(() => {
  const total = parseFloat(props.histories?.total_amount) || 0;
  return total - igtfAmount.value;
});
const totalAmount = computed(() => parseFloat(props.histories?.total_amount) || (subtotalAmount.value + igtfAmount.value));
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    max-width="480"
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
  >
    <VCard class="fiscal-ticket-card overflow-hidden">
      <!-- Cabecera Estilo Gradiente Magenta del Sistema -->
      <VCardTitle class="pa-0">
        <div class="fiscal-ticket-header px-4 py-3 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <VAvatar size="32" color="white" variant="tonal" class="rounded-lg">
              <VIcon icon="tabler-receipt-tax" size="20" color="white" />
            </VAvatar>
            <div class="d-flex flex-column leading-none text-white">
              <span class="text-xs font-weight-black uppercase">Factura Fiscal #{{ invoiceNumber }}</span>
              <span class="text-super-xs opacity-80">{{ invoiceDate }}</span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            color="white"
            size="small"
            class="rounded-circle"
            @click="close"
          />
        </div>
      </VCardTitle>

      <!-- Cuerpo del Ticket Fiscal Digitalizado -->
      <VCardText class="pa-4 bg-light-ticket ticket-scrollable-area">
        <div class="ticket-paper shadow-sm pa-4 rounded-lg bg-white">
          
          <!-- Encabezado de Farmacia / SENIAT -->
          <div class="text-center pb-3 border-bottom-dashed">
            <div class="d-flex justify-center align-center mb-1">
              <VIcon icon="tabler-heart-handshake" size="24" color="primary" class="me-1" />
              <span class="text-subtitle-1 font-weight-black uppercase tracking-wide">FARMACIA</span>
            </div>
            <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">
              SENIAT · COMPROBANTE FISCAL DIGITAL
            </div>
          </div>

          <!-- Metadatos de la Factura y Cliente -->
          <div class="py-3 border-bottom-dashed text-xs">
            <div class="d-flex justify-space-between mb-1">
              <span class="font-weight-bold text-disabled uppercase">FACTURA #:</span>
              <span class="font-weight-black text-primary font-mono text-sm">{{ invoiceNumber }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="font-weight-bold text-disabled uppercase">FECHA:</span>
              <span class="font-weight-medium text-high-emphasis">{{ invoiceDate }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="font-weight-bold text-disabled uppercase">RIF / C.I.:</span>
              <span class="font-weight-black text-high-emphasis uppercase">{{ clientRif }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="font-weight-bold text-disabled uppercase">RAZÓN SOCIAL:</span>
              <span class="font-weight-bold text-high-emphasis uppercase text-right truncate" style="max-width: 230px;">
                {{ clientName }}
              </span>
            </div>
            <div v-if="cashierName !== '—'" class="d-flex justify-space-between">
              <span class="font-weight-bold text-disabled uppercase">CAJERO/A:</span>
              <span class="font-weight-medium text-medium-emphasis uppercase">{{ cashierName }}</span>
            </div>
          </div>

          <!-- Detalle de Renglones / Productos -->
          <div class="py-3 border-bottom-dashed">
            <div class="text-super-xs font-weight-black text-disabled uppercase mb-2">
              DESCRIPCIÓN DE PRODUCTOS
            </div>

            <div v-if="details.length === 0" class="text-center py-2 text-disabled text-xs">
              Sin detalles de productos registrados.
            </div>

            <div v-else class="d-flex flex-column gap-2">
              <div
                v-for="(item, idx) in details"
                :key="idx"
                class="ticket-item-row pb-2"
                :class="{ 'border-bottom-hairline': idx < details.length - 1 }"
              >
                <div class="d-flex justify-space-between align-start">
                  <span class="text-xs font-weight-bold text-high-emphasis text-uppercase line-clamp-2 me-2">
                    {{ item.product_name }}
                  </span>
                  <span class="text-xs font-weight-black font-mono text-high-emphasis text-no-wrap">
                    Bs. {{ formatCurrency(item.total_amount) }}
                  </span>
                </div>
                <div class="d-flex justify-space-between align-center mt-1 text-super-xs text-medium-emphasis">
                  <span>
                    {{ item.quantity }} x Bs. {{ formatCurrency(parseFloat(item.total_amount) / (parseFloat(item.quantity) || 1)) }}
                  </span>
                  <span v-if="item.exempt_amount > 0" class="badge-exento">
                    (E) EXENTO
                  </span>
                  <span v-else class="badge-iva">
                    (G 16%)
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Desglose Fiscal SENIAT -->
          <div class="pt-3 pb-1 text-xs">
            <div class="text-super-xs font-weight-black text-disabled uppercase mb-2">
              LIQUIDACIÓN FISCAL
            </div>

            <div class="d-flex justify-space-between py-1">
              <span class="text-medium-emphasis uppercase">MONTO EXENTO (E):</span>
              <span class="font-weight-bold font-mono">Bs. {{ formatCurrency(exemptAmount) }}</span>
            </div>

            <div class="d-flex justify-space-between py-1">
              <span class="text-medium-emphasis uppercase">BASE IMPONIBLE (G 16%):</span>
              <span class="font-weight-bold font-mono">Bs. {{ formatCurrency(taxableAmount) }}</span>
            </div>

            <div class="d-flex justify-space-between py-1">
              <span class="text-medium-emphasis uppercase">IVA (16%):</span>
              <span class="font-weight-black text-success font-mono">Bs. {{ formatCurrency(ivaAmount) }}</span>
            </div>

            <div class="d-flex justify-space-between py-1">
              <span class="text-medium-emphasis uppercase">SUBTOTAL:</span>
              <span class="font-weight-bold font-mono">Bs. {{ formatCurrency(subtotalAmount) }}</span>
            </div>

            <div v-if="igtfAmount > 0" class="d-flex justify-space-between py-1">
              <span class="text-error font-weight-bold uppercase">IGTF (3%) PERCIBIDO DIVISAS:</span>
              <span class="font-weight-bold text-error font-mono">Bs. {{ formatCurrency(igtfAmount) }}</span>
            </div>

            <!-- Gran Total Destacado -->
            <div class="total-box mt-3 pa-3 rounded d-flex justify-space-between align-center bg-primary-tonal">
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-primary uppercase leading-none">TOTAL A PAGAR</span>
                <span class="text-xs text-disabled uppercase">BOLÍVARES (VES)</span>
              </div>
              <span class="text-h6 font-weight-black text-primary font-mono leading-none">
                Bs. {{ formatCurrency(totalAmount) }}
              </span>
            </div>
          </div>

        </div>
      </VCardText>

      <!-- Botón de Cierre con Estilo Magenta del Sistema -->
      <VCardActions class="pa-3 bg-white border-top">
        <VBtn
          block
          color="primary"
          variant="flat"
          class="font-weight-black uppercase rounded-lg shadow-sm"
          @click="close"
        >
          Cerrar Detalle
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.fiscal-ticket-card {
  border-radius: 12px !important;
}

.fiscal-ticket-header {
  background: linear-gradient(135deg, #7A0099, #E20074) !important;
}

.bg-light-ticket {
  background-color: #f1f5f9;
}

.ticket-paper {
  border: 1px solid rgba(0, 0, 0, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.border-bottom-dashed {
  border-bottom: 1px dashed #cbd5e1;
}

.border-bottom-hairline {
  border-bottom: 1px solid #f1f5f9;
}

.border-top {
  border-top: 1px solid #e2e8f0;
}

.ticket-scrollable-area {
  max-height: 75vh;
  overflow-y: auto;
}

.font-mono {
  font-family: monospace, monospace !important;
  letter-spacing: -0.02em;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.1;
}

.leading-none {
  line-height: 1 !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge-exento {
  font-size: 0.6rem;
  font-weight: 800;
  color: #64748b;
  background: #f1f5f9;
  padding: 1px 4px;
  border-radius: 4px;
}

.badge-iva {
  font-size: 0.6rem;
  font-weight: 800;
  color: #059669;
  background: #ecfdf5;
  padding: 1px 4px;
  border-radius: 4px;
}

.bg-primary-tonal {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}
</style>
