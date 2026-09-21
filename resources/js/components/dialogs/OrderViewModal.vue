<script setup>
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { getItemPriceByCurrency } from "@/composables/useTpvItemFormatter";
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
  
  // Si es solo una fecha YYYY-MM-DD sin hora, evitar desfase de zona horaria UTC
  if (typeof date === "string" && /^\d{4}-\d{2}-\d{2}$/.test(date.trim())) {
    const [y, m, d] = date.trim().split("-");
    return `${d}/${m}/${y}`;
  }
  
  const d = new Date(date);
  if (isNaN(d.getTime())) return "—";
  return d.toLocaleString("es-VE", {
    day : "2-digit",
    month : "2-digit",
    year : "numeric",
    hour : "2-digit",
    minute : "2-digit",
    hour12: true
  }).replace(',', ' ·');
});

const effectivePayments = computed(() => {
  if (props.payments && props.payments.length > 0) return props.payments;
  const pm = props.orderData?.payment_methods;
  if (Array.isArray(pm)) return pm;
  if (typeof pm === "string") {
    try {
      const parsed = JSON.parse(pm);
      if (Array.isArray(parsed)) return parsed;
    } catch (e) {}
  }
  return [];
});

const paymentBadges = computed(() => {
  if (props.credit || props.orderData?.status === "Credit") {
    return [{ label: "Crédito", currency: props.selectedCurrency || "USD", color: "primary" }];
  }
  const pays = effectivePayments.value;
  if (!pays.length) return [];

  return pays.map((p) => {
    const label = getPaymentMethodLabel(p.method, p.currency);
    const curr = p.currency || props.selectedCurrency || "";
    let color = "success";
    if (p.method === "credit" || p.isDebt) color = "primary";
    else if (["debit_card", "credit_card", "card"].includes(p.method)) color = "info";
    else if (["binance", "paypal"].includes(p.method)) color = "warning";
    return {
      label: label || "Efectivo",
      amount: p.amount,
      currency: curr,
      color,
    };
  });
});

const paymentBadge = computed(() => {
  return paymentBadges.value[0] || null;
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
      { label: "Saldo", value: "balance" },
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

const debtPayments = computed(() => {
  return effectivePayments.value.filter((payment) => payment.isDebt === true);
});
const normalPayments = computed(() => {
  return effectivePayments.value.filter(
    (payment) => payment.isDebt === false || payment.isDebt == null
  );
});

const fiscalSummary = computed(() => {
  const fh = props.orderData?.fiscal_history || props.orderData?.fiscalHistory || null;
  const fhTotal = Number(fh?.total_amount) || 0;
  const fhExempt = Number(fh?.exempt_amount) || 0;
  const fhTaxable = Number(fh?.taxable_amount) || 0;
  const fhIva = Number(fh?.iva_amount) || 0;

  if (fh && (fhTotal > 0 || fhExempt > 0 || fhTaxable > 0 || fhIva > 0)) {
    return {
      hasFiscalRecord: true,
      invoiceNumber: fh.invoice_number || null,
      isQueued: !!fh.is_queued,
      exemptAmount: fhExempt,
      taxableAmount: fhTaxable,
      ivaAmount: fhIva,
      speAmount: Number(fh.spe_surcharge_amount) || 0,
      speRate: Number(fh.spe_surcharge_rate) || 0,
      totalAmountBs: fhTotal,
      exchangeRate: Number(fh.exchange_rate) || 0,
      isSpe: !!fh.spe || Number(fh.spe_surcharge_amount) > 0,
    };
  }

  let exempt = 0;
  let taxable = 0;
  let iva = 0;

  displayProducts.value.forEach((p) => {
    const qty = Number(p.selectedQuantity) || 1;
    const priceBs = Number(p.price_bs) || Number(p.price) || 0;
    const lineTotal = priceBs * qty;
    const isIva = Boolean(p.iva || p.product?.iva == 1);

    if (isIva) {
      const base = lineTotal / 1.16;
      taxable += base;
      iva += (lineTotal - base);
    } else {
      exempt += lineTotal;
    }
  });

  const speAmt = Number(props.orderData?.spe_surcharge_amount) || Number(props.speSurchargeAmount) || 0;
  const isSpe = props.isSpecialTaxpayer || speAmt > 0;
  const speRate = isSpe ? 3.0 : 0.0;
  const totalBs = exempt + taxable + iva + speAmt;

  return {
    hasFiscalRecord: false,
    invoiceNumber: null,
    isQueued: false,
    exemptAmount: exempt,
    taxableAmount: taxable,
    ivaAmount: iva,
    speAmount: speAmt,
    speRate,
    totalAmountBs: totalBs,
    exchangeRate: 0,
    isSpe,
  };
});

const subtotalFiscal = computed(() => {
  return (fiscalSummary.value?.exemptAmount || 0) + (fiscalSummary.value?.taxableAmount || 0) + (fiscalSummary.value?.ivaAmount || 0);
});

const finalTotalAmount = computed(() => {
  if (props.selectedCurrency === "BS" || !props.selectedCurrency) {
    if (fiscalSummary.value?.totalAmountBs > 0) {
      return fiscalSummary.value.totalAmountBs;
    }
  }

  if (props.totalAmount && Number(props.totalAmount) > 0) {
    const speAmt = fiscalSummary.value?.speAmount || 0;
    if (props.selectedCurrency === "BS" && speAmt > 0 && Math.abs(Number(props.totalAmount) - (fiscalSummary.value.exemptAmount + fiscalSummary.value.taxableAmount + fiscalSummary.value.ivaAmount)) < 0.05) {
      return Number(props.totalAmount) + speAmt;
    }
    return Number(props.totalAmount);
  }

  if (props.orderData?.total_amount != null) {
    return Number(props.orderData.total_amount);
  }

  return 0;
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
  let total = 0;
  let label = "Descuento";
  if (!props.orderData?.details) return { total: 0, label };
  props.orderData.details.forEach((detail) => {
    const type = detail.discount_type || 'Gral';
    const price = parseFloat(detail.price) || 0;
    const quantity = parseInt(detail.quantity) || 0;
    const percentage = parseFloat(detail.discount_percentage) || 0;
    const discountAmount = price * quantity * (percentage / 100);
    if (discountAmount > 0) {
      total += discountAmount;
      label = `Descuento ${type}`;
    }
  });
  return { total, label };
});

const displayProducts = computed(() => {
  const list = (props.orderProducts && props.orderProducts.length > 0)
    ? props.orderProducts
    : (props.orderData?.details || []);

  return list.map((item) => {
    const detailMatch = props.orderData?.details?.find(
      (d) => (d.product_id || d.dish_id || d.product?.id) == (item.product_id || item.id)
    );

    const discountPct = parseFloat(item.discount_percentage ?? detailMatch?.discount_percentage) || 0;
    const discountType = item.discount_type || detailMatch?.discount_type || null;
    const priceBefore = parseFloat(item.price_before_discount ?? detailMatch?.price_before_discount) || null;
    const productObj = item.product ?? detailMatch?.product ?? null;
    const isIva = Boolean(productObj?.iva == 1 || item.iva == 1 || detailMatch?.vat_status == 1);

    return {
      ...item,
      id: item.id ?? item.product_id ?? detailMatch?.product_id ?? detailMatch?.dish_id,
      product_id: item.product_id ?? item.id ?? detailMatch?.product_id ?? detailMatch?.dish_id,
      title: item.title ?? item.name ?? detailMatch?.product?.name ?? detailMatch?.dish?.name ?? 'S/N',
      laboratory: item.laboratory ?? detailMatch?.product?.laboratory?.name ?? detailMatch?.product?.laboratory ?? (detailMatch?.dish ? 'PLATO' : 'S/L'),
      selectedQuantity: parseFloat(item.selectedQuantity ?? item.quantity ?? detailMatch?.quantity) || 1,
      price: parseFloat(item.price ?? detailMatch?.price) || 0,
      price_cop: parseFloat(item.price_cop ?? item.price ?? detailMatch?.price_cop ?? detailMatch?.price) || 0,
      price_bs: parseFloat(item.price_bs ?? item.price ?? detailMatch?.price_bs ?? detailMatch?.price) || 0,
      price_before_discount: priceBefore,
      discount_percentage: discountPct,
      discount_type: discountType,
      product: productObj,
      iva: isIva,
    };
  });
});

const getProductDiscount = (product) => {
  const discountPct = parseFloat(product.discount_percentage) || 0;
  if (discountPct <= 0) return null;

  const currentPrice = getItemPriceByCurrency(product, props.selectedCurrency);
  const qty = parseFloat(product.selectedQuantity) || 1;
  const discountType = product.discount_type || 'individual';

  let unitDiscountAmount = 0;
  let basePrice = currentPrice;

  if (product.price_before_discount && Number(product.price_before_discount) > currentPrice) {
    basePrice = Number(product.price_before_discount);
    unitDiscountAmount = basePrice - currentPrice;
  } else {
    unitDiscountAmount = currentPrice * (discountPct / 100);
    basePrice = currentPrice + unitDiscountAmount;
  }

  const totalDiscountAmount = unitDiscountAmount * qty;

  return {
    percentage: discountPct,
    type: discountType,
    basePrice,
    unitAmount: unitDiscountAmount,
    totalAmount: totalDiscountAmount,
  };
};

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

const documentTitle = computed(() => {
  const inv = fiscalSummary.value?.invoiceNumber
    || props.orderData?.invoice_number
    || props.orderData?.fiscal_history?.invoice_number
    || props.orderData?.fiscalHistory?.invoice_number;

  if (inv) {
    return `Factura Fiscal ${inv}`;
  }
  if (props.orderData?.id && props.orderData?.id !== "N/A") {
    return `Orden #${props.orderData.id}`;
  }
  return "Detalle de Documento";
});
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="720"
    persistent
    scrollable
    content-class="order-view-dialog"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="order-view-card rounded-xl border-0 shadow-lg overflow-hidden d-flex flex-column" style="max-block-size: 90vh;">
      <!-- Cabecera Compacta en una sola línea -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="header-gradient px-4 py-2.5 d-flex align-center justify-space-between" style="background: linear-gradient(135deg, #7A0099, #E20074) !important;">
          <div class="d-flex align-center gap-2.5">
            <VAvatar color="white" variant="flat" size="34" class="elevation-1">
              <VIcon color="primary" size="18">tabler-file-invoice</VIcon>
            </VAvatar>
            <div class="d-flex flex-wrap align-center gap-x-3 gap-y-0.5">
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0" style="color: white !important;">
                {{ documentTitle }}
              </h2>
              <div class="d-flex align-center gap-1 opacity-90">
                <VIcon size="13" color="white">tabler-calendar-time</VIcon>
                <span class="text-caption text-white font-weight-medium" style="color: white !important;">
                  {{ formattedOrderDate }}
                </span>
              </div>
            </div>
          </div>
          <VBtn icon variant="tonal" color="white" size="x-small" @click="closeModal" class="rounded-lg">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light flex-grow-1 overflow-y-auto" style="max-block-size: calc(90vh - 110px);">
        <div class="pa-2.5 d-flex flex-column gap-2">
          <!-- Bloque Superior: Información General (Cuadrícula compacta 2x2 sin redundancias) -->
          <VCard variant="flat" class="rounded border shadow-sm bg-white overflow-hidden">
            <VCardText class="pa-2.5">
              <VRow dense align="center">
                <!-- Columna Izquierda: Cliente -->
                <VCol cols="12" sm="6" class="py-0.5">
                  <div class="d-flex flex-column">
                    <span class="text-uppercase mb-0.5" style="color: #666; font-weight: 700; font-size: 10px; letter-spacing: 0.5px;">CLIENTE</span>
                    <span class="text-body-2 font-weight-bold text-high-emphasis text-truncate">
                      {{ orderData.client?.name || "Sin Identificar" }} {{ orderData.client?.last_name || "" }}
                    </span>
                    <span v-if="orderData.client?.identification && orderData.client?.identification !== 'N/A'" class="text-caption text-medium-emphasis font-weight-bold">
                      {{ orderData.client.identification_type ? `${orderData.client.identification_type}-` : "" }}{{ orderData.client.identification }}
                    </span>
                  </div>
                </VCol>

                <!-- Columna Derecha: Cajero y Método de Pago -->
                <VCol cols="12" sm="6" class="py-0.5">
                  <div class="d-flex flex-column align-sm-end">
                    <div class="d-flex flex-column align-sm-end mb-1">
                      <span class="text-uppercase mb-0.5" style="color: #666; font-weight: 700; font-size: 10px; letter-spacing: 0.5px;">CAJERO / VENDEDOR</span>
                      <span class="text-body-2 font-weight-bold text-high-emphasis">
                        {{ orderData.seller?.username ? capitalizeFirstAndLastName(orderData.seller.username) : "—" }}
                      </span>
                    </div>
                    <div v-if="paymentBadges.length" class="d-flex flex-wrap gap-1 justify-sm-end align-center">
                      <span class="text-uppercase me-1" style="color: #666; font-weight: 700; font-size: 9px; letter-spacing: 0.5px;">PAGO:</span>
                      <VChip
                        v-for="(badge, bIdx) in paymentBadges"
                        :key="`badge-${bIdx}`"
                        :color="badge.color"
                        size="x-small"
                        variant="tonal"
                        density="compact"
                        class="font-weight-black"
                      >
                        {{ badge.label }} <span v-if="badge.amount" class="ms-1 opacity-90">({{ formatAmountOnly(badge.amount, badge.currency) }})</span>
                      </VChip>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Tabla Central: Detalle de Productos -->
          <div>
            <div class="d-flex align-center gap-1.5 mb-1">
              <div class="header-indicator" style="background-color: #E20074; inline-size: 3px; block-size: 12px; border-radius: 2px;" />
              <span class="text-xs font-weight-black text-uppercase" style="letter-spacing: 0.5px; color: #E20074 !important; font-size: 11px;">DETALLE</span>
            </div>

            <VCard variant="flat" class="rounded border shadow-sm bg-white overflow-hidden">
              <div class="products-table-wrapper table-responsive" style="max-height: 200px; overflow-y: auto;">
                <table class="products-table">
                  <thead>
                    <tr>
                      <th class="ps-3 py-1 text-left" style="color: #444; font-weight: 700; font-size: 10px;">ITEM</th>
                      <th v-if="!isBlind" class="text-end py-1" style="color: #444; font-weight: 700; font-size: 10px; width: 90px;">P.U</th>
                      <th class="text-center py-1" style="color: #444; font-weight: 700; font-size: 10px; width: 50px;">CANT</th>
                      <th v-if="!isBlind" class="text-end pe-3 py-1" style="color: #444; font-weight: 700; font-size: 10px; width: 100px;">TOTAL</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(product, idx) in displayProducts"
                      :key="product.id || product.product_id || idx"
                      class="products-table-row"
                      :class="{ 'discounted-row-highlight': !!getProductDiscount(product) }"
                    >
                      <td class="product-cell ps-3 py-1">
                        <div class="d-flex flex-column">
                          <div class="d-flex align-center flex-wrap gap-1">
                            <span class="text-caption font-weight-bold text-disabled">#{{ productId(product) }}</span>
                            <span class="text-caption font-weight-black text-high-emphasis text-uppercase truncate-text">{{ product.title }}</span>
                          </div>
                          <span v-if="product.product?.laboratory?.name || product.laboratory" class="text-tiny text-disabled text-uppercase" style="font-size: 9px;">
                            {{ product.product?.laboratory?.name || product.laboratory }}
                          </span>

                          <!-- Badge sutil de Descuento -->
                          <div v-if="getProductDiscount(product)" class="d-flex align-center mt-0.5">
                            <VChip
                              color="error"
                              size="x-small"
                              variant="flat"
                              density="compact"
                              class="font-weight-black text-tiny"
                              style="height: 18px; font-size: 9px;"
                            >
                              <VIcon icon="tabler-tag" size="10" class="me-0.5" />
                              Desc. {{ (getProductDiscount(product).type || 'INDIVIDUAL').toUpperCase() }}: -{{ getProductDiscount(product).percentage }}%
                            </VChip>
                          </div>
                        </div>
                      </td>
                      <td v-if="!isBlind" class="text-end table-amount text-caption font-weight-medium text-medium-emphasis py-1">
                        <div class="d-flex flex-column align-end">
                          <template v-if="getProductDiscount(product)">
                            <span class="font-weight-bold text-error">
                              {{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}
                            </span>
                            <span class="text-tiny text-disabled text-decoration-line-through">
                              {{ formatAmountOnly(getProductDiscount(product).basePrice, selectedCurrency) }}
                            </span>
                          </template>
                          <template v-else>
                            <span>{{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}</span>
                          </template>
                        </div>
                      </td>
                      <td class="text-center py-1">
                        <!-- Chip neutro para no opacar el total ni el producto -->
                        <VChip size="x-small" variant="tonal" color="secondary" density="compact" class="font-weight-bold px-1.5" style="height: 20px; font-size: 11px;">
                          {{ product.selectedQuantity }}
                        </VChip>
                      </td>
                      <td v-if="!isBlind" class="text-end table-amount text-caption font-weight-black pe-3 py-1">
                        <div class="d-flex flex-column align-end">
                          <span :class="getProductDiscount(product) ? 'text-error' : 'text-high-emphasis'" class="font-weight-black">
                            {{ formatAmountOnly(getLineTotal(product), selectedCurrency) }}
                          </span>
                          <span
                            v-if="getProductDiscount(product)"
                            class="text-tiny font-weight-bold text-error leading-tight"
                            style="font-size: 9px;"
                          >
                            -{{ formatAmountOnly(getProductDiscount(product).totalAmount, selectedCurrency) }}
                          </span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </VCard>
          </div>

          <!-- Bloque Inferior: Desglose Fiscal y Total Factura en 2 Columnas -->
          <VCard variant="flat" class="rounded border shadow-sm bg-white overflow-hidden">
            <VCardText class="pa-2.5">
              <VRow dense align="stretch">
                <!-- Columna Izquierda: Desglose Fiscal SENIAT (limpio sin Bs en cada renglón) -->
                <VCol cols="12" sm="7" class="py-1 pe-sm-3 border-sm-e">
                  <div class="d-flex align-center justify-space-between mb-1.5">
                    <div class="d-flex align-center gap-1.5">
                      <div class="header-indicator" style="background-color: #7A0099; inline-size: 3px; block-size: 12px; border-radius: 2px;" />
                      <span class="text-caption font-weight-black text-uppercase" style="letter-spacing: 0.5px; color: #7A0099 !important; font-size: 11px;">DESGLOSE FISCAL (SENIAT)</span>
                    </div>
                    <span class="text-tiny text-medium-emphasis font-weight-medium" style="font-size: 10px;">
                      (Montos en {{ selectedCurrency || 'Bs.' }})
                    </span>
                  </div>

                  <div class="summary-list d-flex flex-column gap-1">
                    <div class="summary-row">
                      <span class="summary-label">Monto Exento (E)</span>
                      <span class="summary-value font-weight-bold text-high-emphasis">{{ formatAmountOnly(fiscalSummary.exemptAmount, selectedCurrency) }}</span>
                    </div>
                    <div class="summary-row">
                      <span class="summary-label">Base Imponible (G 16%)</span>
                      <span class="summary-value font-weight-bold text-high-emphasis">{{ formatAmountOnly(fiscalSummary.taxableAmount, selectedCurrency) }}</span>
                    </div>
                    <div class="summary-row">
                      <span class="summary-label">IVA (16%)</span>
                      <span class="summary-value font-weight-bold text-high-emphasis">{{ formatAmountOnly(fiscalSummary.ivaAmount, selectedCurrency) }}</span>
                    </div>
                    <div class="summary-row">
                      <span class="summary-label font-weight-bold">Subtotal</span>
                      <span class="summary-value font-weight-bold text-high-emphasis">{{ formatAmountOnly(subtotalFiscal, selectedCurrency) }}</span>
                    </div>
                    <div class="summary-row">
                      <span class="summary-label">IGTF (3%) Percibido Divisas</span>
                      <span class="summary-value font-weight-bold text-high-emphasis">
                        {{ formatAmountOnly(fiscalSummary.speAmount, selectedCurrency) }}
                      </span>
                    </div>
                    <div v-if="orderDiscounts.total > 0" class="summary-row">
                      <span class="summary-label">{{ orderDiscounts.label }}</span>
                      <span class="summary-value text-error font-weight-bold">- {{ formatAmountOnly(orderDiscounts.total, selectedCurrency) }}</span>
                    </div>
                  </div>
                </VCol>

                <!-- Columna Derecha: Recuadro Destacado Total Factura -->
                <VCol cols="12" sm="5" class="py-1 ps-sm-3 d-flex flex-column justify-center">
                  <div class="total-box pa-2.5 rounded-lg border d-flex flex-column align-center text-center justify-center h-100">
                    <span class="text-caption font-weight-black text-uppercase tracking-wider text-medium-emphasis mb-0.5" style="letter-spacing: 0.5px; font-size: 11px;">
                      TOTAL FACTURA
                    </span>
                    <span class="font-weight-black text-primary leading-tight my-1" style="font-size: 22px !important; font-weight: 900 !important; color: #7A0099 !important;">
                      {{ formatCurrency(finalTotalAmount, selectedCurrency) }}
                    </span>

                    <div v-if="credit || debtPayments.length" class="mt-1 d-flex flex-column gap-0.5 w-100">
                      <div v-if="credit" class="d-flex justify-space-between text-caption font-weight-bold">
                        <span class="text-disabled">Crédito:</span>
                        <span class="text-primary">{{ formatCurrency(creditAmount, selectedCurrency) }}</span>
                      </div>
                      <div v-if="debtPayments.length" class="d-flex justify-space-between text-caption font-weight-bold">
                        <span class="text-disabled">Pendiente:</span>
                        <span class="text-warning">{{ formatCurrency(debtPayments[0]?.amount || 0, debtPayments[0]?.currency) }}</span>
                      </div>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </div>
      </VCardText>

      <!-- Botón de Salida Accesible -->
      <VCardActions class="pa-2.5 bg-white border-t flex-shrink-0 d-flex justify-end">
        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg font-weight-black text-caption w-100"
          height="36"
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
  padding-block: 6px;
  padding-inline: 8px;
  text-align: start;
  text-transform: uppercase;
}

.products-table td {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05);
  padding-block: 5px;
  padding-inline: 8px;
}

.products-table-row:last-child td {
  border-block-end: none;
}

.products-table-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.02);
}

.products-table-row.discounted-row-highlight {
  background-color: rgba(var(--v-theme-error), 0.05) !important;
  border-inline-start: 3px solid rgb(var(--v-theme-error)) !important;
}

.products-table-row.discounted-row-highlight:hover {
  background-color: rgba(var(--v-theme-error), 0.09) !important;
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
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
}

.summary-value {
  font-size: 0.8rem;
}

.total-box {
  background-color: rgba(122, 0, 153, 0.04);
  border-color: rgba(122, 0, 153, 0.15) !important;
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

.v-theme--dark .total-box {
  background-color: rgba(122, 0, 153, 0.15);
  border-color: rgba(122, 0, 153, 0.3) !important;
}

.v-theme--dark .header-indicator {
  background-color: #60a5fa;
}
</style>
