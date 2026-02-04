<script setup>
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, defineEmits, defineProps } from "vue";

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
  if (!props.orderData?.created_at) return "—";
  return formatDateTime(props.orderData.created_at, "datetime");
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
  <VDialog v-model="dialogVisible" max-width="560" persistent content-class="order-view-dialog">
    <VCard class="order-view-card rounded-lg">
      <VCardTitle class="order-view-header d-flex align-center flex-wrap gap-2 pt-3 px-3 pb-2">
        <span class="text-subtitle-1 font-weight-bold section-title">Orden #{{ orderData.id }}</span>
        <VChip :color="paymentBadge.color" size="x-small" variant="tonal" density="compact">
          {{ paymentBadge.label }}
        </VChip>
        <VChip v-if="paymentBadge.currency" size="x-small" variant="tonal" :color="getCurrencyChipColor(paymentBadge.currency)" density="compact">
          {{ paymentBadge.currency }}
        </VChip>
        <VSpacer />
        <span class="header-date">{{ formattedOrderDate }}</span>
        <VBtn icon variant="text" size="x-small" @click="closeModal">
          <VIcon size="18">tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="px-4 pb-4 pt-3">
        <!-- Cajero | Cliente -->
        <div class="order-view-data mb-4">
          <div class="data-block-unified rounded pa-3 d-flex">
            <div class="data-half flex-grow-1">
              <span class="data-label d-block">Cajero</span>
              <span class="data-value">{{ orderData.seller?.username ? capitalizeFirstAndLastName(orderData.seller.username) : "—" }}</span>
            </div>
            <div class="data-divider" />
            <div class="data-half flex-grow-1">
              <span class="data-label d-block">Cliente</span>
              <span class="data-value">{{ orderData.client?.name || "" }} {{ orderData.client?.last_name || "" }}{{ orderData.client?.identification ? ` · ${orderData.client.identification_type || ""} ${orderData.client.identification}` : "" }}</span>
            </div>
          </div>
        </div>

        <!-- Tabla de productos -->
        <div class="order-view-products mb-4">
          <div class="products-table-wrapper rounded overflow-hidden">
            <table class="products-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th class="text-end">Unit.</th>
                  <th class="quantity-col">Cant.</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(product, idx) in orderProducts" :key="product.id || product.product_id || idx" class="products-table-row">
                  <td class="product-cell">
                    <span class="product-line-full">{{ productLineLabel(product) }}</span>
                    <span
                      v-if="activeDiscount && product.price_before_discount != null"
                      class="text-caption text-decoration-line-through text-error d-block mt-1"
                    >
                      {{ formatAmountOnly(product.price_before_discount, selectedCurrency) }}
                    </span>
                  </td>
                  <td class="text-end table-amount">{{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}</td>
                  <td class="quantity-cell">{{ product.selectedQuantity }}</td>
                  <td class="text-end table-amount font-weight-medium">{{ formatAmountOnly(getLineTotal(product), selectedCurrency) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Resumen de pago (compacto) -->
        <div class="order-view-summary rounded pa-3">
          <div v-if="activeDiscount" class="summary-row">
            <span class="summary-label">{{ activeDiscount.label }}</span>
            <span class="summary-value">- {{ formatCurrency(activeDiscount.amount, selectedCurrency) }}</span>
          </div>
          <div v-if="isSpecialTaxpayer" class="summary-row">
            <span class="summary-label">Recargo SPE (3%)</span>
            <span class="summary-value">{{ props.orderData?.spe_surcharge_amount }} {{ selectedCurrency }}</span>
          </div>
          <div v-if="credit" class="summary-row">
            <span class="summary-label">Crédito</span>
            <span class="summary-value">{{ formatCurrency(creditAmount, selectedCurrency) }}</span>
          </div>
          <div v-if="debtPayments.length" class="summary-row">
            <span class="summary-label">Saldo</span>
            <span class="summary-value">{{ formatCurrency(debtPayments[0]?.amount || 0, debtPayments[0]?.currency) }}</span>
          </div>
          <template v-if="normalPayments.length">
            <div v-for="(payment, pIndex) in normalPayments" :key="`pay-${pIndex}`" class="summary-row">
              <span class="summary-label">{{ getPaymentMethodLabel(payment.method, payment.currency) }}</span>
              <span class="summary-value">{{ formatCurrency(payment.amount || 0, payment.currency) }}</span>
            </div>
          </template>
          <div v-if="changeAmount" class="summary-row">
            <span class="summary-label">Devolución</span>
            <span class="summary-value">{{ formatCurrency(changeAmount, "COP") }}</span>
          </div>
          <VDivider class="summary-divider" />
          <div class="summary-row total-row">
            <span class="total-label">Total</span>
            <span class="total-amount">{{ formatCurrency(totalAmount, selectedCurrency) }}</span>
          </div>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.order-view-dialog :deep(.v-overlay__content) {
  align-items: flex-start;
  padding: 0.75rem 0;
}
.order-view-card {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  background: rgb(var(--v-theme-surface));
}
/* Chips x-small en el modal: 30px altura, 13px fuente */
.order-view-card :deep(.v-chip:not(.v-chip--pill).v-chip--size-x-small) {
  --v-chip-height: 30px;
  min-height: 30px;
  font-size: 13px;
  padding: 4px 10px;
}
.order-view-card :deep(.v-chip__underlay) {
  border-radius: 6px;
  margin: 2px;
}
.order-view-header {
  border-bottom: none;
  background: rgba(var(--v-theme-primary), 0.08);
}
.section-title {
  color: rgb(var(--v-theme-primary));
}
.section-label {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
/* Cards de datos: etiquetas más grandes y legibles */
.data-label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.65);
  margin-bottom: 2px;
}
.data-value {
  font-size: 0.9375rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.92);
}
.data-block-unified {
  background: rgba(var(--v-theme-primary), 0.06);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.data-half {
  min-width: 0;
  padding: 0 12px;
}
.data-divider {
  width: 1px;
  background: rgba(var(--v-theme-primary), 0.18);
  flex-shrink: 0;
}
/* Tabla: más aire, jerarquía visual en producto */
.products-table-wrapper {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  background: rgba(var(--v-theme-primary), 0.04);
  max-height: 280px;
  overflow-y: auto;
}
.products-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.products-table th {
  text-align: left;
  padding: 4px 8px;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
  font-size: 0.6875rem;
  line-height: 1.2;
}
.products-table th.text-end { text-align: right; }
.products-table td {
  padding: 4px 8px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity, 0.12));
  vertical-align: top;
  line-height: 1.25;
}
.products-table-row:nth-child(even) {
  background: rgba(var(--v-theme-primary), 0.04);
}
.products-table-row:last-child td {
  border-bottom: none;
}
.quantity-col { width: 52px; }
.quantity-cell { width: 52px; text-align: center; }
.product-cell { min-width: 0; }
.product-line-full {
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.92);
  word-break: break-word;
}
.table-amount {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.9);
}
/* Resumen de pago */
.order-view-summary {
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  min-height: 1.5rem;
}
.summary-label {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.78);
}
.summary-value {
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.92);
}
.summary-divider {
  margin: 6px 0 !important;
}
.total-row {
  padding-top: 2px;
}
.total-label {
  font-size: 0.9375rem;
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
}
.total-amount {
  font-size: 1.125rem;
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
  letter-spacing: 0.02em;
}
/* — Modo oscuro: contraste y elevación — */
.v-theme--dark .order-view-card {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}
.v-theme--dark .order-view-header {
  background: rgba(255, 255, 255, 0.06);
}
.v-theme--dark .section-title,
.v-theme--dark .section-label,
.v-theme--dark .header-date {
  color: rgba(255, 255, 255, 0.9);
}
.header-date {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.75);
}
.v-theme--dark .data-label {
  color: rgba(255, 255, 255, 0.6);
}
.v-theme--dark .data-value {
  color: rgba(255, 255, 255, 0.92);
}
.v-theme--dark .data-block-unified {
  background: rgba(255, 255, 255, 0.06);
}
.v-theme--dark .data-divider {
  background: rgba(255, 255, 255, 0.12);
}
.v-theme--dark .products-table-wrapper {
  background: rgba(255, 255, 255, 0.05);
}
.v-theme--dark .products-table th {
  color: rgba(255, 255, 255, 0.9);
  background: rgba(255, 255, 255, 0.08);
}
.v-theme--dark .product-line-full {
  color: rgba(255, 255, 255, 0.92);
}
.v-theme--dark .table-amount {
  color: rgba(255, 255, 255, 0.9);
}
.v-theme--dark .products-table-row:nth-child(even) {
  background: rgba(255, 255, 255, 0.03);
}
.v-theme--dark .order-view-summary {
  background: rgba(255, 255, 255, 0.07);
}
.v-theme--dark .summary-label {
  color: rgba(255, 255, 255, 0.7);
}
.v-theme--dark .summary-value {
  color: rgba(255, 255, 255, 0.92);
}
.v-theme--dark .total-label,
.v-theme--dark .total-amount {
  color: rgba(255, 255, 255, 1);
}
</style>
