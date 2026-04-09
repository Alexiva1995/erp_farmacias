<script setup>
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, defineEmits, defineProps } from "vue";
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
});

const emit = defineEmits(["update:isDialogVisible"]);

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
    content-class="order-view-dialog"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="order-view-card rounded-xl border-0 shadow-lg overflow-hidden">
      <!-- Premium Header -->
      <VCardTitle class="pa-0">
        <div class="premium-header pa-4 d-flex align-center gap-3">
          <VAvatar
            size="40"
            color="white"
            class="shadow-sm rounded-lg"
          >
            <VIcon
              icon="tabler-receipt"
              color="primary"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h3 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Orden #{{ orderData.id }}
            </h3>
            <div class="d-flex align-center gap-1 mt-1">
              <VIcon icon="tabler-calendar-time" size="10" color="white" class="opacity-75" />
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem; letter-spacing: 0.05em;">
                {{ formattedOrderDate }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeModal"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light">
        <div class="pa-3 pa-sm-4">
          <!-- Document Info -->
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator" />
            <span class="text-subtitle-2 font-weight-black text-uppercase text-primary">Información del Documento</span>
          </div>

          <VCard variant="flat" class="rounded-lg border shadow-sm mb-6 bg-white overflow-hidden">
            <VCardText class="pa-4">
              <VRow dense>
                <VCol cols="12" sm="6">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Fecha de Emisión</span>
                    <span class="text-sm font-weight-bold">{{ formattedOrderDate }}</span>
                  </div>
                </VCol>
                <VCol cols="12" sm="6">
                  <div class="d-flex flex-column align-sm-end">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Método de Pago</span>
                    <VChip :color="paymentBadge.color" size="x-small" variant="tonal" class="font-weight-black">
                      {{ paymentBadge.label }}
                    </VChip>
                  </div>
                </VCol>

                <VCol cols="12">
                  <VDivider class="my-3 opacity-10" />
                </VCol>

                <VCol cols="12" sm="6">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Cajero / Vendedor</span>
                    <span class="text-sm font-weight-bold text-primary">
                      {{ orderData.seller?.username ? capitalizeFirstAndLastName(orderData.seller.username) : "—" }}
                    </span>
                  </div>
                </VCol>
                <VCol cols="12" sm="6">
                  <div class="d-flex flex-column align-sm-end">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Cliente</span>
                    <span class="text-sm font-weight-bold text-truncate" style="max-width: 100%;">
                      {{ orderData.client?.name || "Sin Identificar" }} {{ orderData.client?.last_name || "" }}
                    </span>
                    <span v-if="orderData.client?.identification" class="text-super-xs text-disabled">
                      {{ orderData.client.identification_type || "" }} {{ orderData.client.identification }}
                    </span>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Products Table -->
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator" />
            <span class="text-subtitle-2 font-weight-black text-uppercase text-primary">Detalle de Productos</span>
          </div>

          <VCard variant="flat" class="rounded-lg border shadow-sm mb-6 bg-white overflow-hidden">
            <div class="products-table-wrapper">
              <table class="products-table">
                <thead>
                  <tr>
                    <th class="ps-4">Producto</th>
                    <th class="text-end">Unit.</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-end pe-4">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(product, idx) in orderProducts" :key="product.id || product.product_id || idx" class="products-table-row">
                    <td class="product-cell ps-4 py-3">
                      <div class="d-flex flex-column">
                        <div class="d-flex align-center gap-1">
                          <span class="text-primary font-weight-black text-xs">#{{ productId(product) }}</span>
                          <span class="text-xs font-weight-bold text-uppercase truncate-text">{{ product.title }}</span>
                        </div>
                        <div class="d-flex align-center gap-1 mt-1">
                          <span class="text-super-xs text-disabled font-weight-medium">{{ product.active_ingredient || 'Sin Componente' }}</span>
                          <span class="text-super-xs text-disabled">|</span>
                          <span class="text-super-xs text-primary font-weight-black">{{ product.laboratory || 'Sin Laboratorio' }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="text-end table-amount text-xs font-weight-medium">{{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}</td>
                    <td class="text-center">
                      <VChip size="x-small" variant="tonal" class="font-weight-black" style="font-size: 0.65rem !important;">{{ product.selectedQuantity }}</VChip>
                    </td>
                    <td class="text-end table-amount text-xs font-weight-black pe-4">{{ formatAmountOnly(getLineTotal(product), selectedCurrency) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </VCard>

          <!-- Summary -->
          <VCard variant="flat" class="rounded-lg border shadow-sm bg-white overflow-hidden">
            <VCardText class="pa-4">
              <div class="summary-list d-flex flex-column gap-2">
                <div v-if="activeDiscount" class="summary-row">
                  <span class="summary-label">{{ activeDiscount.label }}</span>
                  <span class="summary-value text-error">- {{ formatCurrency(activeDiscount.amount, selectedCurrency) }}</span>
                </div>
                <div v-if="isSpecialTaxpayer" class="summary-row">
                  <span class="summary-label">Recargo SPE (3%)</span>
                  <span class="summary-value">{{ props.orderData?.spe_surcharge_amount }} {{ selectedCurrency }}</span>
                </div>
                <div v-if="credit" class="summary-row">
                  <span class="summary-label">Crédito Acumulado</span>
                  <span class="summary-value text-primary font-weight-black">{{ formatCurrency(creditAmount, selectedCurrency) }}</span>
                </div>
                <div v-if="debtPayments.length" class="summary-row">
                  <span class="summary-label">Saldo Pendiente</span>
                  <span class="summary-value text-warning font-weight-black">{{ formatCurrency(debtPayments[0]?.amount || 0, debtPayments[0]?.currency) }}</span>
                </div>

                <template v-if="normalPayments.length">
                  <div v-for="(payment, pIndex) in normalPayments" :key="`pay-${pIndex}`" class="summary-row">
                    <span class="summary-label">{{ getPaymentMethodLabel(payment.method, payment.currency) }}</span>
                    <span class="summary-value font-weight-bold">{{ formatCurrency(payment.amount || 0, payment.currency) }}</span>
                  </div>
                </template>

                <div v-if="changeAmount" class="summary-row">
                  <span class="summary-label">Cambio Entregado</span>
                  <span class="summary-value text-success font-weight-black">{{ formatCurrency(changeAmount, "COP") }}</span>
                </div>

                <VDivider class="my-2 opacity-10" />

                <div class="d-flex align-center justify-space-between pt-1">
                  <span class="text-h6 font-weight-black text-primary">TOTAL</span>
                  <div class="d-flex flex-column align-end">
                    <span class="text-h4 font-weight-black text-primary leading-none">{{ formatCurrency(totalAmount, selectedCurrency) }}</span>
                    <span class="text-super-xs font-weight-black text-disabled uppercase mt-1">Sujeto a cambios del día</span>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>

        <!-- Action Buttons -->
        <div class="pa-4 pa-sm-6 pt-0">
          <VBtn
            block
            color="secondary"
            variant="tonal"
            class="rounded-lg h-50 font-weight-black text-xs"
            @click="closeModal"
          >
            CERRAR PESTAÑA
          </VBtn>
        </div>
      </VCardText>
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

.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.header-indicator {
  inline-size: 4px;
  block-size: 18px;
  background-color: #3b82f6;
  border-radius: 4px;
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
