<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { getItemPriceByCurrency } from "@/composables/useTpvItemFormatter";

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
  isProcessingFiscal: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String,
    default: "pharmacy",
  },
  // Props opcionales para vista fiscal integrada y acciones contextuales
  showFiscalTab: {
    type: Boolean,
    default: false,
  },
  activeTab: {
    type: String,
    default: "detail",
  },
  fiscalPreviewData: {
    type: Object,
    default: null,
  },
  loadingFiscalPreview: {
    type: Boolean,
    default: false,
  },
  isConfirmingFiscal: {
    type: Boolean,
    default: false,
  },
  showPrintActions: {
    type: Boolean,
    default: true,
  },
  showOrderActions: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "update:activeTab",
  "modal-closed",
  "close",
  "cancel-order",
  "return-order",
  "create-fiscal-invoice",
  "confirm-fiscal-invoice",
  "print-order",
  "print-order-thermal",
]);

const dialogVisible = computed({
  get() {
    return props.isDialogVisible;
  },
  set(value) {
    emit("update:isDialogVisible", value);
  },
});

const currentTab = computed({
  get() {
    return props.activeTab;
  },
  set(value) {
    emit("update:activeTab", value);
  },
});

const closeModal = () => {
  if (props.isConfirmingFiscal) return;
  emit("update:isDialogVisible", false);
  emit("modal-closed");
  emit("close");
};

const formatBs = (val) => {
  const num = Number(val) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
};

const formattedOrderDate = computed(() => {
  const date = props.orderData?.order_date ?? props.orderData?.created_at;
  if (!date) return "—";

  if (typeof date === "string" && /^\d{4}-\d{2}-\d{2}$/.test(date.trim())) {
    const [y, m, d] = date.trim().split("-");
    return `${d}/${m}/${y}`;
  }

  const d = new Date(date);
  if (isNaN(d.getTime())) return "—";
  return d
    .toLocaleString("es-VE", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      hour12: true,
    })
    .replace(",", " ·");
});

const statusMeta = computed(() => {
  const st = String(props.orderData?.status || "");
  if (st === "Completed") return { label: "Completada", color: "success" };
  if (st === "Abandoned") return { label: "Abandonada", color: "warning" };
  if (st === "Cancelled") return { label: "Cancelada", color: "error" };
  if (st === "Pending") return { label: "Abierta", color: "info" };
  if (st === "Reserved") return { label: "En Mesa", color: "info" };
  return { label: st || "—", color: "secondary" };
});

const isOrderCancelledOrAbandoned = computed(() => {
  const st = String(props.orderData?.status || "");
  return ["Cancelled", "cancelled", "Abandoned", "Abandonada"].includes(st);
});

const hasFiscalInvoiceNumber = computed(() => {
  return !!(
    props.orderData?.fiscal_history?.invoice_number ||
    props.orderData?.fiscalHistory?.invoice_number
  );
});

const canShowFiscalTab = computed(() => {
  return props.showFiscalTab && !isOrderCancelledOrAbandoned.value;
});

const canCancel = computed(() => {
  if (typeof props.orderData?.can_cancel === "boolean") {
    return props.orderData.can_cancel;
  }
  if (isOrderCancelledOrAbandoned.value) return false;
  return !hasFiscalInvoiceNumber.value;
});

const canReturn = computed(() => {
  if (typeof props.orderData?.can_return === "boolean") {
    return props.orderData.can_return;
  }
  return props.orderData?.status === "Completed" && !canCancel.value;
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
    const found = methodsForCurrency.find((m) => m.value === methodValue);
    if (found) return found.label;
  }
  for (const key in paymentMethodsByCurrency) {
    const found = paymentMethodsByCurrency[key].find((m) => m.value === methodValue);
    if (found) return found.label;
  }
  return methodValue.replace(/_/g, " ").toUpperCase();
};

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

const debtPayments = computed(() => {
  return effectivePayments.value.filter((payment) => payment.isDebt === true);
});

const displayProducts = computed(() => {
  const list =
    props.orderProducts && props.orderProducts.length > 0
      ? props.orderProducts
      : props.orderData?.details || [];

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
      title: item.title ?? item.name ?? detailMatch?.product?.name ?? detailMatch?.dish?.name ?? "S/N",
      laboratory:
        item.laboratory ??
        detailMatch?.product?.laboratory?.name ??
        detailMatch?.product?.laboratory ??
        (detailMatch?.dish ? "Plato / Menú" : null),
      selectedQuantity: parseFloat(item.selectedQuantity ?? item.quantity ?? detailMatch?.quantity) || 1,
      price: parseFloat(item.price ?? detailMatch?.price) || 0,
      price_cop: parseFloat(item.price_cop ?? item.price ?? detailMatch?.price_cop ?? detailMatch?.price) || 0,
      price_bs: parseFloat(item.price_bs ?? item.price ?? detailMatch?.price_bs ?? detailMatch?.price) || 0,
      price_before_discount: priceBefore,
      discount_percentage: discountPct,
      discount_type: discountType,
      product: productObj,
      lot: item.lot ?? detailMatch?.lot ?? null,
      modifiers: Array.isArray(item.modifiers)
        ? item.modifiers
        : Array.isArray(detailMatch?.modifiers)
        ? detailMatch.modifiers
        : [],
      notes: item.notes ?? detailMatch?.notes ?? null,
      kitchen_status_label: item.kitchen_status_label ?? detailMatch?.kitchen_status_label ?? null,
      iva: isIva,
    };
  });
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
    const price = Number(p.price_bs) || Number(p.price) || 0;
    const lineTotal = price * qty;
    const isIva = Boolean(p.iva || p.product?.iva == 1);

    if (isIva) {
      const base = lineTotal / 1.16;
      taxable += base;
      iva += lineTotal - base;
    } else {
      exempt += lineTotal;
    }
  });

  const speAmt = Number(props.orderData?.spe_surcharge_amount) || 0;
  const isSpe = props.isSpecialTaxpayer || speAmt > 0;
  const totalBs = exempt + taxable + iva + speAmt;

  return {
    hasFiscalRecord: false,
    invoiceNumber: null,
    isQueued: false,
    exemptAmount: exempt,
    taxableAmount: taxable,
    ivaAmount: iva,
    speAmount: speAmt,
    speRate: isSpe ? 3.0 : 0.0,
    totalAmountBs: totalBs,
    exchangeRate: 0,
    isSpe,
  };
});

const subtotalFiscal = computed(() => {
  return (
    (fiscalSummary.value?.exemptAmount || 0) +
    (fiscalSummary.value?.taxableAmount || 0) +
    (fiscalSummary.value?.ivaAmount || 0)
  );
});

const finalTotalAmount = computed(() => {
  if (props.selectedCurrency === "BS" || !props.selectedCurrency) {
    if (fiscalSummary.value?.totalAmountBs > 0) {
      return fiscalSummary.value.totalAmountBs;
    }
  }
  if (props.totalAmount && Number(props.totalAmount) > 0) {
    return Number(props.totalAmount);
  }
  if (props.orderData?.total_amount != null) {
    return Number(props.orderData.total_amount);
  }
  return 0;
});

const orderDiscounts = computed(() => {
  let total = 0;
  let label = "Descuento";
  if (!props.orderData?.details) return { total: 0, label };
  props.orderData.details.forEach((detail) => {
    const type = detail.discount_type || "Gral";
    const price = parseFloat(detail.price) || 0;
    const quantity = parseFloat(detail.quantity) || 0;
    const percentage = parseFloat(detail.discount_percentage) || 0;
    const discountAmount = price * quantity * (percentage / 100);
    if (discountAmount > 0) {
      total += discountAmount;
      label = `Descuento ${type}`;
    }
  });
  return { total, label };
});

const getProductDiscount = (product) => {
  const discountPct = parseFloat(product.discount_percentage) || 0;
  if (discountPct <= 0) return null;

  const currentPrice = getItemPriceByCurrency(product, props.selectedCurrency);
  const qty = parseFloat(product.selectedQuantity) || 1;
  const discountType = product.discount_type || "individual";

  let unitDiscountAmount = 0;
  let basePrice = currentPrice;

  if (product.price_before_discount && Number(product.price_before_discount) > currentPrice) {
    basePrice = Number(product.price_before_discount);
    unitDiscountAmount = basePrice - currentPrice;
  } else {
    unitDiscountAmount = currentPrice * (discountPct / 100);
    basePrice = currentPrice + unitDiscountAmount;
  }

  return {
    percentage: discountPct,
    type: discountType,
    basePrice,
    unitAmount: unitDiscountAmount,
    totalAmount: unitDiscountAmount * qty,
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

const documentTitle = computed(() => {
  const inv =
    fiscalSummary.value?.invoiceNumber ||
    props.orderData?.invoice_number ||
    props.orderData?.fiscal_history?.invoice_number ||
    props.orderData?.fiscalHistory?.invoice_number;

  if (inv) return `Factura Fiscal ${inv}`;
  if (props.orderData?.id && props.orderData?.id !== "N/A") return `Orden #${props.orderData.id}`;
  return "Detalle de Orden";
});

const handlePrimaryFiscalAction = () => {
  if (!props.orderData?.id) return;
  // Si ya estamos en la pestaña fiscal (con previa cargada), confirmamos emisión; si no, pasamos a la pestaña fiscal
  if (props.showFiscalTab && currentTab.value !== "fiscal") {
    currentTab.value = "fiscal";
    return;
  }
  if (props.showFiscalTab) {
    emit("confirm-fiscal-invoice", props.orderData.id);
  } else {
    emit("create-fiscal-invoice", props.orderData.id);
  }
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="760"
    persistent
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="rounded-lg d-flex flex-column" style="max-block-size: 90vh;">
      <!-- Cabecera limpia alineada al tema del sistema -->
      <VCardTitle class="px-5 py-3 border-b d-flex align-center justify-space-between flex-shrink-0">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" size="38" rounded="lg">
            <VIcon icon="tabler-file-invoice" size="20" />
          </VAvatar>
          <div>
            <div class="d-flex align-center flex-wrap gap-2">
              <span class="text-subtitle-1 font-weight-bold text-high-emphasis">
                {{ documentTitle }}
              </span>
              <VChip size="x-small" :color="statusMeta.color" variant="tonal" class="font-weight-bold">
                {{ statusMeta.label }}
              </VChip>
            </div>
            <div class="text-caption text-medium-emphasis d-flex align-center gap-1">
              <VIcon size="13" icon="tabler-calendar-time" />
              <span>{{ formattedOrderDate }}</span>
            </div>
          </div>
        </div>

        <VBtn
          icon="tabler-x"
          variant="text"
          color="default"
          size="small"
          :disabled="props.isConfirmingFiscal"
          @click="closeModal"
        />
      </VCardTitle>

      <!-- Pestañas internas unificadas (Detalle + Vista Fiscal) -->
      <div v-if="canShowFiscalTab" class="px-5 border-b flex-shrink-0">
        <VTabs v-model="currentTab" density="compact" color="primary">
          <VTab value="detail" prepend-icon="tabler-list-details">
            Detalle de Orden
          </VTab>
          <VTab value="fiscal" prepend-icon="tabler-file-invoice">
            Previa Fiscal (Bs)
            <VChip
              v-if="hasFiscalInvoiceNumber"
              size="x-small"
              color="success"
              variant="tonal"
              class="ms-2 font-weight-bold"
            >
              Emitida
            </VChip>
          </VTab>
        </VTabs>
      </div>

      <VCardText class="pa-4 flex-grow-1 overflow-y-auto">
        <!-- PESTAÑA 1: DETALLE DE ORDEN -->
        <div v-if="!canShowFiscalTab || currentTab === 'detail'" class="d-flex flex-column gap-3">
          <!-- Información general -->
          <VCard variant="flat" border class="rounded-lg pa-3">
            <VRow dense align="center">
              <VCol cols="12" sm="6">
                <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
                  Cliente
                </div>
                <div class="text-body-2 font-weight-bold text-high-emphasis text-truncate">
                  {{ orderData?.client?.name || "Consumidor Final" }} {{ orderData?.client?.last_name || "" }}
                </div>
                <div
                  v-if="orderData?.client?.identification && orderData?.client?.identification !== 'N/A'"
                  class="text-caption text-medium-emphasis"
                >
                  {{ orderData.client.identification_type ? `${orderData.client.identification_type}-` : "" }}{{ orderData.client.identification }}
                </div>
              </VCol>

              <VCol cols="12" sm="6">
                <div class="d-flex flex-column align-sm-end gap-1">
                  <!-- Badges contextuales por vertical -->
                  <div v-if="mode === 'restaurant'" class="d-flex flex-wrap gap-1 justify-sm-end">
                    <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                      <VIcon icon="tabler-armchair" size="12" class="me-1" />
                      {{ orderData?.dining_table?.name || "Para llevar / Barra" }}
                    </VChip>
                    <VChip v-if="orderData?.guests_count" size="x-small" color="secondary" variant="tonal" class="font-weight-bold">
                      <VIcon icon="tabler-users" size="12" class="me-1" />
                      {{ orderData.guests_count }} comensales
                    </VChip>
                    <VChip v-if="orderData?.parent_order_id" size="x-small" color="info" variant="tonal" class="font-weight-bold">
                      Dividida de #{{ orderData.parent_order_id }}
                    </VChip>
                  </div>
                  <div v-else class="d-flex flex-wrap gap-1 justify-sm-end">
                    <VChip v-if="orderData?.psychotropic_controls_count > 0" size="x-small" color="warning" variant="tonal" class="font-weight-bold">
                      Psicotrópico / Récipe
                    </VChip>
                    <VChip v-if="orderData?.returns_count > 0" size="x-small" color="secondary" variant="tonal" class="font-weight-bold">
                      {{ orderData.returns_count }} Devolución(es)
                    </VChip>
                  </div>

                  <div class="text-caption text-medium-emphasis">
                    {{ mode === "restaurant" ? "Mesero / Cajero:" : "Cajero / Vendedor:" }}
                    <strong class="text-high-emphasis">
                      {{ orderData?.seller?.username ? capitalizeFirstAndLastName(orderData.seller.username) : "—" }}
                    </strong>
                  </div>

                  <div v-if="paymentBadges.length" class="d-flex flex-wrap gap-1 justify-sm-end align-center">
                    <VChip
                      v-for="(badge, bIdx) in paymentBadges"
                      :key="`badge-${bIdx}`"
                      :color="badge.color"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ badge.label }}
                      <span v-if="badge.amount" class="ms-1">({{ formatAmountOnly(badge.amount, badge.currency) }})</span>
                    </VChip>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCard>

          <!-- Tabla de ítems -->
          <div>
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1.5">
              Detalle de Ítems
            </div>
            <VTable density="compact" class="border rounded-lg overflow-hidden">
              <thead>
                <tr>
                  <th class="text-left text-caption font-weight-bold">Ítem</th>
                  <th v-if="!isBlind" class="text-end text-caption font-weight-bold" style="width: 100px;">P.U.</th>
                  <th class="text-center text-caption font-weight-bold" style="width: 65px;">Cant.</th>
                  <th v-if="!isBlind" class="text-end text-caption font-weight-bold" style="width: 110px;">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(product, idx) in displayProducts" :key="product.id || product.product_id || idx">
                  <td class="py-2">
                    <div class="d-flex flex-column gap-0.5">
                      <div class="d-flex align-center flex-wrap gap-1">
                        <span class="text-caption text-disabled font-weight-bold">#{{ productId(product) }}</span>
                        <span class="text-caption font-weight-bold text-high-emphasis">{{ product.title }}</span>
                      </div>
                      <span v-if="product.product?.laboratory?.name || product.laboratory" class="text-caption text-medium-emphasis">
                        {{ product.product?.laboratory?.name || product.laboratory }}
                      </span>
                      <div v-if="product.lot?.lot_number" class="d-flex flex-wrap gap-2 text-caption text-medium-emphasis">
                        <span>Lote: <strong>{{ product.lot.lot_number }}</strong></span>
                        <span v-if="product.lot.expiration_date">Vence: <strong>{{ product.lot.expiration_date }}</strong></span>
                      </div>
                      <div v-if="product.modifiers?.length" class="d-flex flex-wrap gap-1 mt-0.5">
                        <VChip
                          v-for="mod in product.modifiers"
                          :key="mod.id"
                          size="x-small"
                          variant="outlined"
                          color="secondary"
                        >
                          + {{ mod.name }}
                        </VChip>
                      </div>
                      <span v-if="product.notes" class="text-caption text-info fst-italic">
                        Nota: {{ product.notes }}
                      </span>
                      <span v-if="mode === 'restaurant' && product.kitchen_status_label" class="text-caption text-medium-emphasis">
                        Cocina: {{ product.kitchen_status_label }}
                      </span>
                      <div v-if="getProductDiscount(product)" class="mt-0.5">
                        <VChip color="error" size="x-small" variant="tonal" class="font-weight-bold">
                          Desc. {{ (getProductDiscount(product).type || "INDIVIDUAL").toUpperCase() }}: -{{ getProductDiscount(product).percentage }}%
                        </VChip>
                      </div>
                    </div>
                  </td>

                  <td v-if="!isBlind" class="text-end text-caption">
                    <template v-if="getProductDiscount(product)">
                      <div class="font-weight-bold text-error">
                        {{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}
                      </div>
                      <div class="text-disabled text-decoration-line-through">
                        {{ formatAmountOnly(getProductDiscount(product).basePrice, selectedCurrency) }}
                      </div>
                    </template>
                    <template v-else>
                      {{ formatAmountOnly(getItemPriceByCurrency(product, selectedCurrency), selectedCurrency) }}
                    </template>
                  </td>

                  <td class="text-center">
                    <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-bold">
                      {{ product.selectedQuantity }}
                    </VChip>
                  </td>

                  <td v-if="!isBlind" class="text-end text-caption font-weight-bold">
                    <span :class="getProductDiscount(product) ? 'text-error' : 'text-high-emphasis'">
                      {{ formatAmountOnly(getLineTotal(product), selectedCurrency) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Resumen y Total -->
          <VCard variant="flat" border class="rounded-lg pa-3">
            <VRow dense align="stretch">
              <VCol cols="12" sm="7" class="pe-sm-3 border-sm-e">
                <div class="d-flex justify-space-between align-center mb-1.5">
                  <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                    Resumen ({{ selectedCurrency || "Bs." }})
                  </span>
                </div>
                <div class="d-flex flex-column gap-1 text-caption">
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Monto Exento (E)</span>
                    <span class="font-weight-medium">{{ formatAmountOnly(fiscalSummary.exemptAmount, selectedCurrency) }}</span>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Base Imponible (G 16%)</span>
                    <span class="font-weight-medium">{{ formatAmountOnly(fiscalSummary.taxableAmount, selectedCurrency) }}</span>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">IVA (16%)</span>
                    <span class="font-weight-medium">{{ formatAmountOnly(fiscalSummary.ivaAmount, selectedCurrency) }}</span>
                  </div>
                  <div v-if="fiscalSummary.speAmount > 0" class="d-flex justify-space-between text-error">
                    <span class="font-weight-bold">IGTF (3%) Divisas</span>
                    <span class="font-weight-bold">{{ formatAmountOnly(fiscalSummary.speAmount, selectedCurrency) }}</span>
                  </div>
                  <div v-if="Number(orderData?.service_charge_amount) > 0" class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Servicio ({{ orderData.service_charge_percentage || 0 }}%)</span>
                    <span class="font-weight-medium">{{ formatAmountOnly(Number(orderData.service_charge_amount), selectedCurrency) }}</span>
                  </div>
                  <div v-if="orderDiscounts.total > 0" class="d-flex justify-space-between text-error">
                    <span>{{ orderDiscounts.label }}</span>
                    <span class="font-weight-bold">- {{ formatAmountOnly(orderDiscounts.total, selectedCurrency) }}</span>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" sm="5" class="ps-sm-3 d-flex flex-column justify-center align-center text-center">
                <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                  Total Orden
                </span>
                <span class="text-h5 font-weight-black text-primary my-1">
                  {{ formatCurrency(finalTotalAmount, selectedCurrency) }}
                </span>
                <div v-if="credit || debtPayments.length" class="w-100 text-caption mt-1">
                  <div v-if="credit" class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Crédito:</span>
                    <strong class="text-primary">{{ formatCurrency(creditAmount, selectedCurrency) }}</strong>
                  </div>
                  <div v-if="debtPayments.length" class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Pendiente:</span>
                    <strong class="text-warning">{{ formatCurrency(debtPayments[0]?.amount || 0, debtPayments[0]?.currency) }}</strong>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </div>

        <!-- PESTAÑA 2: PREVIA FISCAL OFICIAL EN BS (UNIFICADA) -->
        <div v-else class="d-flex flex-column gap-3">
          <VAlert
            v-if="hasFiscalInvoiceNumber"
            type="success"
            variant="tonal"
            density="compact"
            icon="tabler-circle-check"
          >
            Esta orden ya posee la Factura Fiscal <strong>{{ fiscalSummary.invoiceNumber }}</strong>. Si requiere anulación o ajuste, debe emitir una <strong>Devolución / Nota de Crédito</strong>.
          </VAlert>

          <div v-if="props.loadingFiscalPreview" class="py-4">
            <VSkeletonLoader type="list-item-two-line, table-row-divider@3, list-item-two-line" />
          </div>

          <template v-else-if="props.fiscalPreviewData">
            <VCard variant="flat" border class="rounded-lg pa-3">
              <VRow dense>
                <VCol cols="12" sm="7">
                  <div class="text-caption text-medium-emphasis">Cliente / Razón Social</div>
                  <div class="text-body-2 font-weight-bold text-high-emphasis">
                    {{ props.fiscalPreviewData.client_name }}
                  </div>
                </VCol>
                <VCol cols="12" sm="5">
                  <div class="text-caption text-medium-emphasis">RIF / Cédula</div>
                  <div class="text-body-2 font-weight-bold text-high-emphasis">
                    {{ props.fiscalPreviewData.client_identification }}
                  </div>
                </VCol>
                <VCol cols="12" sm="7" class="mt-1">
                  <div class="text-caption text-medium-emphasis">Dirección Fiscal</div>
                  <div class="text-caption text-high-emphasis text-truncate">
                    {{ props.fiscalPreviewData.client_address || "Sin dirección fiscal registrada" }}
                  </div>
                </VCol>
                <VCol cols="12" sm="5" class="mt-1">
                  <div class="text-caption text-medium-emphasis">Tasa de Cambio Oficial</div>
                  <div class="text-body-2 font-weight-bold text-primary">
                    {{ formatBs(props.fiscalPreviewData.exchange_rate) }} Bs / USD
                  </div>
                </VCol>
              </VRow>
            </VCard>

            <div>
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1.5">
                Detalle de Renglones Fiscales (Bs)
              </div>
              <VTable density="compact" class="border rounded-lg overflow-hidden">
                <thead>
                  <tr>
                    <th class="text-left text-caption font-weight-bold">Producto / Servicio</th>
                    <th class="text-center text-caption font-weight-bold">Cant.</th>
                    <th class="text-end text-caption font-weight-bold">Precio Unit. (Bs)</th>
                    <th class="text-center text-caption font-weight-bold">Condición</th>
                    <th class="text-end text-caption font-weight-bold">Total (Bs)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in props.fiscalPreviewData.items" :key="idx">
                    <td class="text-caption font-weight-medium">{{ item.name }}</td>
                    <td class="text-center text-caption">{{ item.quantity }}</td>
                    <td class="text-end text-caption">{{ formatBs(item.price_bs) }}</td>
                    <td class="text-center">
                      <VChip
                        size="x-small"
                        :color="item.is_taxable ? 'primary' : 'secondary'"
                        variant="tonal"
                        class="font-weight-bold"
                      >
                        {{ item.is_taxable ? "G 16%" : "Exento (E)" }}
                      </VChip>
                    </td>
                    <td class="text-end text-caption font-weight-bold">
                      {{ formatBs(item.total_amount_bs ?? item.total_amount) }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <VCard variant="flat" border class="rounded-lg pa-3">
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1.5">
                Desglose Fiscal (SENIAT)
              </div>
              <div class="d-flex justify-space-between text-caption py-0.5">
                <span class="text-medium-emphasis">Monto Exento (E)</span>
                <span class="font-weight-medium">{{ formatBs(props.fiscalPreviewData.exempt_amount_bs) }} Bs</span>
              </div>
              <div class="d-flex justify-space-between text-caption py-0.5">
                <span class="text-medium-emphasis">Base Imponible (G 16%)</span>
                <span class="font-weight-medium">{{ formatBs(props.fiscalPreviewData.taxable_amount_bs) }} Bs</span>
              </div>
              <div class="d-flex justify-space-between text-caption py-0.5">
                <span class="text-medium-emphasis">IVA (16%)</span>
                <span class="font-weight-medium">{{ formatBs(props.fiscalPreviewData.iva_amount_bs) }} Bs</span>
              </div>
              <div class="d-flex justify-space-between text-caption py-1 border-t mt-1">
                <span class="font-weight-bold">Subtotal</span>
                <span class="font-weight-bold">{{ formatBs(props.fiscalPreviewData.subtotal_bs) }} Bs</span>
              </div>
              <div
                v-if="props.fiscalPreviewData.spe || props.fiscalPreviewData.spe_surcharge_amount_bs > 0"
                class="d-flex justify-space-between text-caption py-0.5 text-error"
              >
                <span class="font-weight-bold">IGTF (3%) Percibido Divisas</span>
                <span class="font-weight-bold">+ {{ formatBs(props.fiscalPreviewData.spe_surcharge_amount_bs) }} Bs</span>
              </div>
              <VDivider class="my-1.5" />
              <div class="d-flex justify-space-between align-center">
                <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Total Factura Fiscal</span>
                <span class="text-h6 font-weight-black text-primary">
                  {{ formatBs(props.fiscalPreviewData.total_amount_bs) }} Bs
                </span>
              </div>
            </VCard>
          </template>

          <div v-else class="text-center py-6 text-medium-emphasis text-caption">
            No hay datos de previa fiscal disponibles para esta orden.
          </div>
        </div>
      </VCardText>

      <!-- Barra de acciones estandarizada -->
      <VCardActions class="px-5 py-3 border-t flex-shrink-0 d-flex flex-wrap justify-space-between gap-2">
        <!-- Acciones de estado (Cancelar vs Devolver) -->
        <div class="d-flex flex-wrap gap-2">
          <VBtn
            v-if="props.showOrderActions && canCancel"
            color="error"
            variant="tonal"
            size="small"
            prepend-icon="tabler-circle-x"
            @click="emit('cancel-order', props.orderData?.id)"
          >
            Cancelar Orden
          </VBtn>
          <VBtn
            v-else-if="props.showOrderActions && canReturn"
            color="warning"
            variant="tonal"
            size="small"
            prepend-icon="tabler-receipt-refund"
            @click="emit('return-order', props.orderData?.id)"
          >
            Devolver / Nota de Crédito
          </VBtn>
        </div>

        <!-- Acciones de impresión, emisión fiscal y cierre -->
        <div class="d-flex flex-wrap gap-2 ms-auto">
          <VBtn
            v-if="props.showPrintActions"
            color="secondary"
            variant="text"
            size="small"
            prepend-icon="tabler-receipt"
            @click="emit('print-order-thermal', props.orderData?.id)"
          >
            Ticket 54mm
          </VBtn>
          <VBtn
            v-if="props.showPrintActions"
            color="secondary"
            variant="outlined"
            size="small"
            prepend-icon="tabler-printer"
            @click="emit('print-order', props.orderData?.id)"
          >
            Imprimir
          </VBtn>
          <VBtn
            v-if="!hasFiscalInvoiceNumber && !isOrderCancelledOrAbandoned"
            color="primary"
            variant="flat"
            size="small"
            prepend-icon="tabler-file-invoice"
            :loading="props.isProcessingFiscal || props.isConfirmingFiscal"
            :disabled="props.isProcessingFiscal || props.isConfirmingFiscal || (currentTab === 'fiscal' && props.loadingFiscalPreview)"
            @click="handlePrimaryFiscalAction"
          >
            {{ currentTab === "fiscal" ? "Emitir Factura Fiscal" : "Previa Fiscal" }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="small"
            :disabled="props.isConfirmingFiscal"
            @click="closeModal"
          >
            Cerrar
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
