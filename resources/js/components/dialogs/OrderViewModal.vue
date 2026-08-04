<script setup>
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  orderData: {
    type: Object,
    default: () => ({}),
  },
  totalAmount: {
    type: Number,
    default: 0,
  },
  selectedCurrency: {
    type: String,
    default: "COP",
  },
  orderProducts: {
    type: Array,
    default: () => [],
  },
  baseUrl: {
    type: String,
    default: "/",
  },
  payments: {
    type: Array,
    default: () => [],
  },
  changeAmount: {
    type: Number,
    default: 0,
  },
  creditAmount: {
    type: Number,
    default: 0,
  },
  credit: {
    type: Boolean,
    default: false,
  },
  isSpecialTaxpayer: {
    type: Boolean,
    default: false,
  },
  isBlind: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed", "cancel-order"]);

const dialogVisible = computed({
  get() {
    return props.isDialogVisible;
  },
  set(value) {
    emit("update:isDialogVisible", value);
  },
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const handleCancel = () => {
  emit("cancel-order", props.orderData?.id);
};

const formattedOrderDate = computed(() => {
  const date = props.orderData?.order_date ?? props.orderData?.created_at;
  if (!date) return "—";
  
  const d = new Date(date);
  return d.toLocaleString("es-ES", {
    day : "2-digit",
    month : "2-digit",
    year : "numeric",
    hour : "2-digit",
    minute : "2-digit",
    hour12: true
  }).replace(',', ' ·');
});

const paymentBadge = computed(() => {
  if (props.credit) return { label: "Crédito", currency: props.selectedCurrency || "", color: "primary" };
  if (!props.payments?.length) return { label: "—", currency: "", color: "secondary" };
  const first = props.payments[0];
  const label = getPaymentMethodLabel(first.method, first.currency);
  const currency = first.currency || props.selectedCurrency || "";
  if (first.method === "credit") return { label: "Crédito", currency, color: "primary" };
  if (["cash_cop", "cash_bs", "cash_usd", "mobile_payment"].includes(first.method))
    return { label: label || "Efectivo", currency, color: "success" };
  if (["debit_card", "credit_card"].includes(first.method))
    return { label: label || "Tarjeta", currency, color: "info" };
  return { label: label || "Pagado", currency, color: "success" };
});

const getCurrencyChipColor = (currency) => {
  if (!currency) return "secondary";
  switch (String(currency).toUpperCase()) {
    case "COP": return "primary";
    case "BS": return "success";
    case "USD": return "warning";
    default: return "info";
  }
};

const getPaymentMethodLabel = (methodValue, currency) => {
  const paymentMethodsByCurrency = {
    COP: [
      { label: "Efectivo", value: "cash_cop" },
      { label: "Transferencia", value: "bank_transfer" },
    ],
    BS: [
      { label: "Efectivo", value: "cash_bs" },
      { label: "Pago Móvil", value: "mobile_payment" },
      { label: "Transferencia", value: "bank_transfer_bs" },
      { label: "Tarjeta", value: "card" },
      { label: "T. Débito", value: "debit_card" },
      { label: "T. Crédito", value: "credit_card" },
    ],
    USD: [
      { label: "Efectivo", value: "cash_usd" },
      { label: "Binance", value: "binance" },
      { label: "PayPal", value: "paypal" },
      { label: "Crédito", value: "credit" },
    ],
  };

  if (!methodValue) return "N/A";
  const methodsForCurrency = paymentMethodsByCurrency[currency];
  if (methodsForCurrency) {
    const foundMethod = methodsForCurrency.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  for (const key in paymentMethodsByCurrency) {
    const methods = paymentMethodsByCurrency[key];
    const foundMethod = methods.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  return methodValue.replace(/_/g, " ").toUpperCase();
};
const getItemPriceByCurrency = (item, currency) => {
  // Si ya se proporciona el precio unitario final (ej. desde el historial), usarlo directamente
  if (item.unit_price !== undefined && item.unit_price !== null) {
    return item.unit_price;
  }
  
  if (item.fixed_price !== undefined && item.fixed_price !== null) {
    return item.fixed_price;
  }
  const taxRate = item.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = item.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = item.price_cop || 0;
  } else {
    basePrice = item.price || 0;
  }
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const debtPayments = computed(() => {
  if (!props.payments) return [];
  return props.payments.filter((payment) => payment.isDebt === true);
});
const normalPayments = computed(() => {
  if (!props.payments) return [];
  return props.payments.filter(
    (payment) => payment.isDebt === false || payment.isDebt == null
  );
});

const hasCompanyDiscount = computed(() => {
  return (
    props.orderData.details?.some(
      (detail) => detail.discount_type === "company"
    ) || false
  );
});

const hasDoctorDiscount = computed(() => {
  return (
    props.orderData.details?.some(
      (detail) => detail.discount_type === "doctor"
    ) || false
  );
});

const hasRecipeDiscount = computed(() => {
  return (
    props.orderData.details?.some(
      (detail) => detail.discount_type === "recipe"
    ) || false
  );
});

const orderDiscounts = computed(() => {
  const totals = { company: 0, doctor: 0, recipe: 0 };
  if (!props.orderData?.details) return totals;
  props.orderData.details.forEach((detail) => {
    const type = detail.discount_type?.toLowerCase();
    const price = parseFloat(detail.price) || 0;
    const quantity = parseInt(detail.quantity) || 0;
    const percentage = parseFloat(detail.discount_percentage) || 0;
    const discountAmount = price * quantity * (percentage / 100);
    if (type === "Empresa" || type === "company") {
      totals.company += discountAmount;
    } else if (type === "Medico" || type === "doctor") {
      totals.doctor += discountAmount;
    } else if (type === "Recipe" || type === "recipe") {
      totals.recipe += discountAmount;
    }
  });
  return totals;
});

const activeDiscount = computed(() => {
  const discounts = orderDiscounts.value;
  if (discounts.company > 0)
    return { label: "Descuento Empresa", amount: discounts.company };
  if (discounts.doctor > 0)
    return { label: "Descuento Médico", amount: discounts.doctor };
  if (discounts.recipe > 0)
    return { label: "Descuento Recipe", amount: discounts.recipe };
  return null;
});

const getLineTotal = (product) => {
  const price = getItemPriceByCurrency(product, props.selectedCurrency);
  const qty = product.selectedQuantity || 0;
  return price * qty;
};

const productId = (product) => {
  const id = product.id ?? product.product_id;
  return id != null && id !== "" ? id : null;
};

/** Formato: ID - Nombre - Laboratorio (solo partes existentes, unidas por " - ") */
const productLineLabel = (product) => {
  const id = productId(product);
  const name = product.title?.trim() || "—";
  const lab = product.laboratory?.trim() || "";
  const parts = [id != null ? String(id) : null, name, lab || null].filter(Boolean);
  return parts.join(" - ");
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="700"
    persistent
    scrollable
    content-class="order-view-dialog"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="order-view-card rounded-xl border-0 shadow-lg overflow-hidden d-flex flex-column" style="max-block-size: 85vh;">
      <!-- Cabecera Premium Estilo Trazabilidad -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="header-gradient pa-4 d-flex align-center" style="background: linear-gradient(135deg, #7A0099, #E20074) !important;">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon color="primary" size="22">tabler-receipt</VIcon>
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0" style="color: white !important;">Orden #{{ orderData.id }}</h2>
              <div class="d-flex align-center gap-1 mt-1">
                <VIcon size="14" color="white" class="opacity-75">tabler-calendar-time</VIcon>
                <span class="text-caption text-white opacity-75 uppercase font-weight-medium" style="color: white !important;">
                  {{ formattedOrderDate }}
                </span>
              </div>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="closeModal" class="rounded-lg">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light flex-grow-1 overflow-y-auto" style="max-block-size: calc(85vh - 120px);">
        <div class="pa-3">
          <!-- Document Info -->
          <div class="d-flex align-center gap-2 mb-2">
            <div class="header-indicator" style="background-color: #E20074; width: 4px; height: 16px; border-radius: 2px;" />
            <span class="text-xs font-weight-black text-uppercase" style="letter-spacing: 0.5px; color: #E20074 !important; font-size: 12px;">INFORMACIÓN GENERAL</span>
          </div>

          <VCard variant="flat" class="rounded border shadow-sm mb-2 bg-white overflow-hidden">
            <VCardText class="pa-2.5">
              <VRow dense>
                <VCol cols="12" sm="6" class="py-1">
                  <div class="d-flex flex-column">
                    <span class="text-uppercase mb-0.5" style="color: #444; font-weight: 700; font-size: 10px; letter-spacing: 0.5px;">FECHA DE EMISIÓN</span>
                    <span class="text-body-2 font-weight-bold text-high-emphasis">{{ formattedOrderDate }}</span>
                  </div>
                </VCol>
                <VCol cols="12" sm="6" class="py-1">
                  <div class="d-flex flex-column align-sm-end">
                    <span class="text-uppercase mb-0.5" style="color: #444; font-weight: 700; font-size: 10px; letter-spacing: 0.5px;">MÉTODO DE PAGO</span>
                    <VChip :color="paymentBadge.color" size="x-small" variant="flat" class="font-weight-black">
                      {{ paymentBadge.label }}
                    </VChip>
                  </div>
                </VCol>

                <VCol cols="12" class="my-0.5">
                  <VDivider class="opacity-10" />
                </VCol>

                <VCol cols="12" sm="6" class="py-1">
                  <div class="d-flex flex-column">
                    <span class="text-uppercase mb-0.5" style="color: #444; font-weight: 700; font-size: 10px; letter-spacing: 0.5px;">CAJERO / VENDEDOR</span>
                    <span class="text-body-2 font-weight-bold text-high-emphasis">
                      {{ orderData.seller?.username ? capitalizeFirstAndLastName(orderData.seller.username) : "—" }}
                    </span>
                  </div>
                </VCol>
                <VCol cols="12" sm="6" class="py-1">
                  <div class="d-flex flex-column align-sm-end">
                    <span class="text-uppercase mb-0.5" style="color: #444; font-weight: 700; font-size: 10px; letter-spacing: 0.5px;">CLIENTE</span>
                    <span class="text-body-2 font-weight-bold text-high-emphasis text-truncate" style="max-width: 100%;">
                      {{ orderData.client?.name || "Sin Identificar" }} {{ orderData.client?.last_name || "" }}
                    </span>
                    <span v-if="orderData.client?.identification" class="text-caption text-medium-emphasis font-weight-bold">
                      {{ orderData.client.identification_type || "" }} {{ orderData.client.identification }}
                    </span>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Products Table -->
          <div class="d-flex align-center gap-2 mb-2">
            <div class="header-indicator" style="background-color: #E20074; width: 4px; height: 16px; border-radius: 2px;" />
            <span class="text-xs font-weight-black text-uppercase" style="letter-spacing: 0.5px; color: #E20074 !important; font-size: 12px;">DETALLE</span>
          </div>

          <VCard variant="flat" class="rounded border shadow-sm mb-2 bg-white overflow-hidden">
            <div class="products-table-wrapper table-responsive">
              <table class="products-table">
                <thead>
                  <tr>
                    <th class="ps-3 py-1.5 text-left" style="color: #444; font-weight: 700; font-size: 10px;">ITEM</th>
                    <th v-if="!isBlind" class="text-end py-1.5" style="color: #444; font-weight: 700; font-size: 10px;">P.U</th>
                    <th class="text-center py-1.5" style="color: #444; font-weight: 700; font-size: 10px;">CANT</th>
                    <th v-if="!isBlind" class="text-end pe-3 py-1.5" style="color: #444; font-weight: 700; font-size: 10px;">TOTAL</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(product, idx) in orderProducts" :key="product.id || product.product_id || idx" class="products-table-row">
                    <td class="product-cell ps-3 py-1.5">
                      <div class="d-flex flex-column">
                        <div class="d-flex align-center flex-wrap gap-1">
                          <span class="text-primary font-weight-black text-caption">#{{ productId(product) }}</span>
                          <span class="text-caption font-weight-black text-high-emphasis text-uppercase truncate-text">{{ product.title }}</span>
                        </div>
                        <span v-if="product.product?.laboratory?.name || product.laboratory" class="text-tiny text-disabled text-uppercase">
                          {{ product.product?.laboratory?.name || product.laboratory }}
                        </span>
                      </div>
                    </td>
                    <td v-if="!isBlind" class="text-end table-amount text-caption font-weight-bold text-medium-emphasis py-1.5">{{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}</td>
                    <td class="text-center py-1.5">
                      <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black">{{ product.selectedQuantity }}</VChip>
                    </td>
                    <td v-if="!isBlind" class="text-end table-amount text-caption font-weight-black pe-3 py-1.5">{{ formatAmountOnly(getLineTotal(product), selectedCurrency) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </VCard>

          <!-- Summary -->
          <VCard v-if="!isBlind" variant="flat" class="rounded border shadow-sm bg-white overflow-hidden mb-2">
            <VCardText class="pa-2.5">
              <div class="summary-list d-flex flex-column gap-1">
                <div v-if="activeDiscount" class="summary-row">
                  <span class="summary-label">Desc.</span>
                  <span class="summary-value text-error font-weight-bold">- {{ formatCurrency(activeDiscount.amount, selectedCurrency) }}</span>
                </div>
                <div v-if="isSpecialTaxpayer" class="summary-row">
                  <span class="summary-label">SPE</span>
                  <span class="summary-value font-weight-bold">{{ props.orderData?.spe_surcharge_amount }} {{ selectedCurrency }}</span>
                </div>
                <div v-if="credit" class="summary-row">
                  <span class="summary-label">Crédito</span>
                  <span class="summary-value text-primary font-weight-black">{{ formatCurrency(creditAmount, selectedCurrency) }}</span>
                </div>
                <div v-if="debtPayments.length" class="summary-row">
                  <span class="summary-label">Saldo Pendiente</span>
                  <span class="summary-value text-warning font-weight-black">{{ formatCurrency(debtPayments[0]?.amount || 0, debtPayments[0]?.currency) }}</span>
                </div>

                <template v-if="normalPayments.length">
                  <div v-for="(payment, pIndex) in normalPayments" :key="`pay-${pIndex}`" class="summary-row">
                    <span class="summary-label">{{ getPaymentMethodLabel(payment.method, payment.currency) }}</span>
                    <span class="summary-value font-weight-bold text-high-emphasis">{{ formatCurrency(payment.amount || 0, payment.currency) }}</span>
                  </div>
                </template>

                <div v-if="changeAmount" class="summary-row">
                  <span class="summary-label">Cambio Entregado</span>
                  <span class="summary-value text-success font-weight-black">{{ formatCurrency(changeAmount, "COP") }}</span>
                </div>

                <VDivider class="my-1 opacity-10" />

                <div class="d-flex align-center justify-space-between pt-0.5">
                  <span class="text-subtitle-1 font-weight-black text-primary">TOTAL</span>
                  <div class="d-flex flex-column align-end">
                    <span class="text-h5 font-weight-black text-primary leading-none">{{ formatCurrency(totalAmount, selectedCurrency) }}</span>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>
      </VCardText>

      <!-- Action Buttons Fijos abajo -->
      <VCardActions class="pa-2.5 bg-white border-t flex-shrink-0 d-flex gap-2">
        <VBtn
          v-if="orderData?.status !== 'Cancelled' && orderData?.status !== 'cancelled' && orderData?.status !== 'Abandonada' && orderData?.status !== 'Abandoned'"
          color="error"
          variant="tonal"
          class="rounded-lg font-weight-black text-xs flex-grow-1"
          height="38"
          @click="handleCancel"
        >
          <VIcon class="me-1">tabler-circle-x</VIcon>
          CANCELAR ORDEN
        </VBtn>
        <VBtn
          color="secondary"
          variant="tonal"
          class="rounded-lg font-weight-black text-xs flex-grow-1"
          height="38"
          @click="closeModal"
        >
          CERRAR DETALLES
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.order-view-dialog :deep(.v-overlay__content) {
  align-items: center;
  padding-block: 0.75rem;
  padding-inline: 0;
}

.order-view-card {
  background: white;
}

.scrollable-content {
  overflow-y: auto;
  flex: 1;
}

.premium-header {
  background: var(--brand-gradient, linear-gradient(135deg, #7A0099, #E20074)) !important;
}

.premium-header h3,
.premium-header span {
  color: #ffffff !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  background-color: rgba(var(--v-theme-on-surface), 0.7);
  border-radius: 2px;
}

.h-50 {
  block-size: 50px !important;
}

.order-view-card :deep(.v-chip.v-chip--size-x-small) {
  font-weight: 800 !important;
  text-transform: uppercase !important;
}

.products-table-wrapper {
  inline-size: 100%;
}

.products-table {
  border-collapse: collapse;
  inline-size: 100%;
}

.products-table th {
  background: rgba(var(--v-theme-on-surface), 0.03);
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  padding-block: 8px;
  padding-inline: 8px;
  text-align: start;
  text-transform: uppercase;
}

.products-table td {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05);
  padding-block: 8px;
  padding-inline: 8px;
}

.products-table-row:last-child td {
  border-block-end: none;
}

.products-table-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.02);
}

.table-amount {
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.summary-label {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.summary-value {
  font-size: 0.875rem;
}

.leading-none {
  line-height: 1 !important;
}

.truncate-text {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.v-theme--dark .order-view-card {
  background: #1e1e1e !important;
}

.v-theme--dark .v-theme--dark .bg-white {
  background-color: #2a2a2a !important;
}

.v-theme--dark .header-indicator {
  background-color: #60a5fa;
}
</style>
