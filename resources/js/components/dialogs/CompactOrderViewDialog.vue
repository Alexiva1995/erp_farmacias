<script setup>
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  orderData: { type: Object, default: () => ({}) },
  totalAmount: { type: Number, default: 0 },
  selectedCurrency: { type: String, default: "COP" },
  orderProducts: { type: Array, default: () => [] },
  payments: { type: Array, default: () => [] },
  changeAmount: { type: Number, default: 0 },
  creditAmount: { type: Number, default: 0 },
  credit: { type: Boolean, default: false },
});

const emit = defineEmits(["update:isDialogVisible"]);

const dialogVisible = computed({
  get() { return props.isDialogVisible; },
  set(value) { emit("update:isDialogVisible", value); },
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
};

const formattedOrderDate = computed(() => {
  const date = props.orderData?.order_date ?? props.orderData?.created_at;
  if (!date) return "—";
  const d = new Date(date);
  return d.toLocaleString("es-ES", {
    day: "2-digit", month: "2-digit", year: "numeric",
    hour: "2-digit", minute: "2-digit", hour12: true
  }).replace(',', ' ·');
});

const paymentBadge = computed(() => {
  if (props.credit) return { label: "Crédito", color: "primary" };
  if (!props.payments?.length) return { label: "—", color: "secondary" };
  return { label: "Pagado", color: "success" };
});

const getItemPriceByCurrency = (item, currency) => {
  if (item.fixed_price != null) return item.fixed_price;
  const taxRate = item.taxRate || 0;
  let basePrice = currency === "BS" ? (item.price_bs || 0) : (currency === "COP" ? (item.price_cop || 0) : (item.price || 0));
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") priceWithIva = roundUpToNearestHundred(priceWithIva);
  return priceWithIva;
};

const getLineTotal = (product) => {
  const price = getItemPriceByCurrency(product, props.selectedCurrency);
  return price * (product.selectedQuantity || 0);
};

const productId = (product) => product.id ?? product.product_id;

const activeDiscount = computed(() => {
  let total = 0;
  let label = "Descuento";
  if (!props.orderData?.details) return null;
  props.orderData.details.forEach((detail) => {
    const amount = (parseFloat(detail.price) || 0) * (parseInt(detail.quantity) || 0) * ((parseFloat(detail.discount_percentage) || 0) / 100);
    if (amount > 0) {
      total += amount;
      label = `Descuento ${detail.discount_type || 'Gral'}`;
    }
  });
  return total > 0 ? { label, amount: total } : null;
});
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="650"
    persistent
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
  >
    <VCard class="compact-order-card overflow-hidden">
      <!-- Header Estilizado y denso -->
      <VCardTitle class="pa-0">
        <div class="compact-header px-4 py-2 d-flex align-center bg-primary">
          <VIcon icon="tabler-receipt" color="white" size="20" class="me-2" />
          <div class="d-flex flex-column leading-none">
            <h3 class="text-sm font-weight-black text-white uppercase mb-0">Órden #{{ orderData.id }}</h3>
            <span class="text-super-xs text-white opacity-75 font-weight-bold">{{ formattedOrderDate }}</span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="text" color="white" size="x-small" @click="closeModal" />
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light-gray h-100">
        <div class="pa-3 pa-sm-4 d-flex flex-column gap-3">
          
          <!-- Info Grid Superior -->
          <VRow dense>
            <VCol cols="12" sm="6">
              <VCard variant="flat" class="border pa-2 h-100 bg-white shadow-xs">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Cliente / Identificación</span>
                <div class="d-flex flex-column">
                   <span class="text-xs font-weight-black text-high-emphasis uppercase truncate">{{ orderData.client?.name || "SIN NOMBRE" }} {{ orderData.client?.last_name || "" }}</span>
                   <span class="text-xs font-weight-bold text-primary">{{ orderData.client?.identification || 'SIN ID' }}</span>
                </div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6">
              <VCard variant="flat" class="border pa-2 h-100 bg-white shadow-xs">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Vendedor / Pago</span>
                <div class="d-flex justify-space-between align-center">
                   <span class="text-xs font-weight-bold text-medium-emphasis">{{ orderData.seller?.username || '—' }}</span>
                   <VChip :color="paymentBadge.color" size="x-small" label variant="flat" class="font-weight-black px-1">{{ paymentBadge.label }}</VChip>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Tabla de Productos Alta Densidad -->
          <VCard variant="flat" class="border bg-white shadow-xs overflow-hidden">
            <div class="compact-table-container">
              <table class="compact-table">
                <thead>
                  <tr>
                    <th class="ps-3 text-start">PRODUCTO</th>
                    <th class="text-end" style="width: 80px;">UNIT.</th>
                    <th class="text-center" style="width: 50px;">CANT.</th>
                    <th class="text-end pe-3" style="width: 90px;">TOTAL</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(product, idx) in orderProducts" :key="idx">
                    <td class="ps-3 py-1">
                      <div class="d-flex flex-column">
                        <span class="text-xs font-weight-black uppercase truncate leading-tight">{{ product.title }}</span>
                        <div class="d-flex align-center gap-1 text-super-xs text-disabled">
                          <span class="font-weight-black text-primary">#{{ productId(product) }}</span>
                          <span>|</span>
                          <span class="truncate">{{ product.laboratory || 'S/L' }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="text-end text-xs font-weight-medium">{{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}</td>
                    <td class="text-center">
                      <span class="text-xs font-weight-black text-primary bg-primary-lighten-5 px-1 rounded">{{ product.selectedQuantity }}</span>
                    </td>
                    <td class="text-end text-xs font-weight-black pe-3">{{ formatAmountOnly(getLineTotal(product), selectedCurrency) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </VCard>

          <!-- Resumen de Totales Compacto -->
          <VCard variant="flat" class="border bg-white shadow-xs pa-3">
             <div class="d-flex flex-column gap-1">
                <div v-if="activeDiscount" class="d-flex justify-space-between align-center">
                   <span class="text-super-xs font-weight-black text-disabled uppercase">{{ activeDiscount.label }}</span>
                   <span class="text-xs font-weight-bold text-error">- {{ formatCurrency(activeDiscount.amount, selectedCurrency) }}</span>
                </div>
                
                <div v-if="changeAmount" class="d-flex justify-space-between align-center">
                   <span class="text-super-xs font-weight-black text-disabled uppercase">Cambio Entregado</span>
                   <span class="text-xs font-weight-bold text-success">{{ formatCurrency(changeAmount, "COP") }}</span>
                </div>

                <div v-if="creditAmount" class="d-flex justify-space-between align-center">
                   <span class="text-super-xs font-weight-black text-disabled uppercase">Crédito</span>
                   <span class="text-xs font-weight-bold text-primary">{{ formatCurrency(creditAmount, selectedCurrency) }}</span>
                </div>

                <VDivider class="my-1 opacity-10" />

                <div class="d-flex justify-space-between align-end pt-1">
                   <div class="d-flex flex-column">
                      <span class="text-super-xs font-weight-black text-disabled uppercase leading-none">Total Pagado</span>
                      <span class="text-super-xs text-primary font-weight-black uppercase">Moneda: {{ selectedCurrency }}</span>
                   </div>
                   <span class="text-h5 font-weight-black text-primary leading-none">{{ formatCurrency(totalAmount, selectedCurrency) }}</span>
                </div>
             </div>
          </VCard>
          
        </div>
      </VCardText>

      <!-- Botón de Cierre -->
      <VCardActions class="pa-3 bg-white border-t">
        <VBtn
          block
          color="secondary"
          variant="tonal"
          size="small"
          class="font-weight-black uppercase py-4"
          @click="closeModal"
        >
          Cerrar Detalle de Orden
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.compact-order-card {
  border-radius: 12px !important;
}

.compact-header {
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)) 0%, #1a4d23 100%);
}

.bg-light-gray {
  background-color: #f8fafc;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.1) !important;
}

.shadow-xs {
  box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
}

.text-super-xs {
  font-size: 0.62rem !important;
  line-height: normal;
}

.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.compact-table-container {
  max-block-size: 300px;
  overflow-y: auto;
}

.compact-table {
  inline-size: 100%;
  border-collapse: collapse;
}

.compact-table th {
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.6rem;
  font-weight: 800;
  padding: 6px 8px;
  text-transform: uppercase;
  position: sticky;
  top: 0;
  z-index: 1;
}

.compact-table td {
  padding: 4px 8px;
  border-block-end: 1px solid #f1f5f9;
}

.compact-table tr:hover {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.border-t {
  border-block-start: 1px solid #f1f5f9 !important;
}

.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
</style>
