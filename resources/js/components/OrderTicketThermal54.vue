<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed } from "vue";

const brandingStore = useBrandingStore();
const logoSrc = computed(() => brandingStore.settings?.app_logo || BASE64_LOGO_DATA);

const props = defineProps({
  orderData: { type: Object, default: () => ({}) },
  totalAmount: { type: Number, default: 0 },
  selectedCurrency: { type: String, default: "COP" },
  orderProducts: { type: Array, default: () => [] },
  payments: { type: Array, default: () => [] },
  changeAmount: { type: Number, default: 0 },
  changeAmountOrigin: { type: Number, default: 0 },
  creditAmount: { type: Number, default: 0 },
  credit: { type: Boolean, default: false },
  companyDiscountTotal: { type: Number, default: 0 },
  selectedDiscountType: { type: String, default: null },
  doctorDiscountTotal: { type: Number, default: 0 },
  recipeDiscountTotal: { type: Number, default: 0 },
  isSpecialTaxpayer: { type: Boolean, default: false },
  speSurchargeAmount: { type: [Number, String], default: 0 },
});

const getItemPriceByCurrency = (item, currency) => {
  const taxRate = item.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") basePrice = item.price_bs || 0;
  else if (currency === "COP") basePrice = item.price_cop || 0;
  else basePrice = item.price || 0;
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") priceWithIva = roundUpToNearestHundred(priceWithIva);
  return priceWithIva;
};

const getLineTotal = (item) => {
  const price = getItemPriceByCurrency(item, props.selectedCurrency);
  const qty = item.selectedQuantity || 0;
  return price * qty;
};

const getPaymentMethodLabel = (methodValue, currency) => {
  const map = {
    COP: [{ label: "Efectivo", value: "cash_cop" }, { label: "Transferencia", value: "bank_transfer" }],
    BS: [
      { label: "Efectivo", value: "cash_bs" }, { label: "Pago Móvil", value: "mobile_payment" },
      { label: "Transferencia", value: "bank_transfer_bs" }, { label: "Tarjeta", value: "card" },
      { label: "T. Débito", value: "debit_card" }, { label: "T. Crédito", value: "credit_card" },
    ],
    USD: [
      { label: "Efectivo", value: "cash_usd" }, { label: "Binance", value: "binance" },
      { label: "PayPal", value: "paypal" }, { label: "Crédito", value: "credit" }, { label: "Saldo", value: "balance" },
    ],
  };
  const list = map[currency] || Object.values(map).flat();
  const found = list.find((m) => m.value === methodValue);
  return found ? found.label : (methodValue || "").replace(/_/g, " ").toUpperCase();
};

const activeDiscount = computed(() => {
  const type = (props.selectedDiscountType || "").toLowerCase();
  const cur = props.selectedCurrency;
  const config = {
    empresa: { label: "Descuento Empresa", amount: props.companyDiscountTotal },
    company: { label: "Descuento Empresa", amount: props.companyDiscountTotal },
    medico: { label: "Descuento Médico", amount: props.doctorDiscountTotal },
    doctor: { label: "Descuento Médico", amount: props.doctorDiscountTotal },
    recipe: { label: "Descuento Recipe", amount: props.recipeDiscountTotal },
  };
  const c = config[type];
  return c && c.amount > 0 ? c : null;
});

const productLab = (item) => item.laboratory || item.laboratory_name || null;
const productLineText = (item) => {
  const qty = item.selectedQuantity || item.quantity || 1;
  const rawTitle = item.title || item.name || item.dish?.name || item.product?.name || "—";
  const title = String(rawTitle).toUpperCase();
  const lab = productLab(item) && productLab(item) !== 'Restaurante' && productLab(item) !== 'N/A' ? String(productLab(item)).toUpperCase() : "";
  const line = lab ? `${qty} X ${title} ${lab}` : `${qty} X ${title}`;
  return line.trim();
};

const clientDisplayLine = computed(() => {
  const c = props.orderData?.client;
  if (!c) return "—";
  const name = [c.name, c.last_name].filter(Boolean).join(" ").trim();
  const identification = c.identification ? (c.identification_type ? `${c.identification_type}${c.identification}` : c.identification) : "";
  const parts = [name, identification].filter(Boolean);
  return parts.length ? parts.join(" · ") : "—";
});
</script>

<template>
  <div class="thermal-54-ticket">
    <!-- Encabezado: logo e info -->
    <header class="thermal-header">
      <img class="thermal-logo" :src="logoSrc" alt="Logo" style="max-height: 50px; object-fit: contain;" />
      <div class="thermal-company font-weight-black text-uppercase">{{ brandingStore.settings?.app_name || 'TOVA' }}</div>
    </header>

    <!-- Datos de venta: orden y fecha -->
    <div class="thermal-data">
      <div class="thermal-data-row">
        <span class="thermal-label">Nº Orden:</span>
        <span class="thermal-value">{{ orderData.id }}</span>
      </div>
      <div class="thermal-data-row">
        <span class="thermal-label">Fecha:</span>
        <span class="thermal-value">{{ formatDateTime(orderData.created_at, "date") }} {{ formatDateTime(orderData.created_at, "time") }}</span>
      </div>
      <div class="thermal-data-row">
        <span class="thermal-label">Cajero:</span>
        <span class="thermal-value">{{ orderData.seller?.username ? capitalizeFirstAndLastName(orderData.seller.username) : "—" }}</span>
      </div>
      <div class="thermal-data-row">
        <span class="thermal-label">Cliente:</span>
        <span class="thermal-value thermal-value-cliente">{{ clientDisplayLine }}</span>
      </div>
      <div v-if="orderData.client?.phone" class="thermal-data-row thermal-data-row-tel">
        <span class="thermal-label">Tel:</span>
        <span class="thermal-value">{{ orderData.client.phone }}</span>
      </div>
    </div>

    <!-- Tabla de productos: "Cant. X Producto" -->
    <div class="thermal-products">
      <div class="thermal-products-head">
        <span class="thermal-col-desc">Producto</span>
      </div>
      <div
        v-for="(item, idx) in orderProducts"
        :key="item.id || idx"
        class="thermal-product-row"
      >
        <div class="thermal-col-desc">
          <span class="thermal-product-name">{{ productLineText(item) }}</span>
          <span v-if="item.notes" class="thermal-product-meta" style="font-weight: bold; margin-top: 2px;">
            NOTA: {{ item.notes }}
          </span>
        </div>
      </div>
    </div>

    <!-- Pie -->
    <footer class="thermal-footer">
      <div>¡GRACIAS POR SU COMPRA!</div>
      <div class="mt-2 thermal-non-fiscal-highlight">DOCUMENTO NO FISCAL</div>
    </footer>
  </div>
</template>

<style scoped>
.thermal-54-ticket {
  box-sizing: border-box;
  padding: 2mm;
  margin: 0;
  background: #fff;
  color: #000;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10px;
  inline-size: 54mm;
  line-height: 1.25;
  max-inline-size: 54mm;
  min-inline-size: 54mm;
  text-transform: uppercase;
}

.thermal-54-ticket * {
  box-sizing: border-box;
  background: transparent !important;
  color: #000 !important;
  text-transform: uppercase !important;
}

.thermal-header {
  border-block-end: 1px dashed #000;
  margin-block-end: 2mm;
  padding-block-end: 2mm;
  text-align: center;
}

.thermal-logo {
  display: block;
  block-size: auto;
  filter: grayscale(100%) contrast(1.1);
  margin-block: 0 1mm;
  margin-inline: auto;
  max-inline-size: 40mm;
}

.thermal-rif {
  font-size: 9px;
  font-weight: bold;
  margin-block: 0 0.5mm;
  margin-inline: 0;
}

.thermal-company {
  font-size: 10px;
  font-weight: bold;
  margin-block: 0 0.5mm;
  margin-inline: 0;
}

.thermal-address {
  margin: 0;
  font-size: 9px;
  line-height: 1.15;
}

.thermal-data {
  margin-block-end: 2mm;
}

.thermal-data-row {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  margin-block: 0.5mm;
  margin-inline: 0;
}

.thermal-label {
  flex-shrink: 0;
  font-weight: bold;
}

.thermal-value {
  margin-inline-start: 2mm;
  text-align: end;
  word-break: break-word;
}

.thermal-value-cliente {
  text-align: end;
  word-break: break-word;
}

.thermal-data-row-tel {
  font-size: 9px;
  margin-block-start: 0.25mm;
}

.thermal-products {
  border-block-start: 1px dashed #000;
  font-size: 10px;
  margin-block-end: 2mm;
  padding-block-start: 1mm;
}

.thermal-products-head {
  display: flex;
  border-block-end: 1px dashed #000;
  font-size: 10px;
  font-weight: bold;
  padding-block: 0.4mm;
  padding-inline: 0;
}

.thermal-col-desc {
  flex: 1;
  min-inline-size: 0;
  padding-block: 0;
  padding-inline: 0 1.5mm;
}

.thermal-col-amount {
  flex-shrink: 0;
  inline-size: 16mm;
  text-align: end;
}

.thermal-product-row {
  display: flex;
  align-items: flex-start;
  border-block-end: 1px solid #000;
  font-size: 10px;
  line-height: 1.2;
  padding-block: 0.5mm;
  padding-inline: 0;
}

.thermal-product-row .thermal-col-desc {
  display: flex;
  flex-direction: column;
}

/* Producto: 2pt más pequeño que el resto (8px vs 10px base) - !important evita que otra regla lo sobrescriba */
.thermal-product-name {
  font-size: 8px !important;
  overflow-wrap: break-word;
  text-transform: uppercase;
  word-wrap: break-word;
}

.thermal-product-meta {
  color: #000;
  font-size: 8px;
  margin-block-start: 0.2mm;
  opacity: 1;
  text-transform: uppercase;
}

.thermal-totals {
  font-size: 10px;
  margin-block-start: 2mm;
  padding-block-start: 1mm;
}

.thermal-total-row {
  display: flex;
  justify-content: space-between;
  margin-block: 0.5mm;
  margin-inline: 0;
}

.thermal-total-block {
  border-block-end: 2px solid #000;
  border-block-start: 2px solid #000;
  margin-block: 1mm;
  margin-inline: 0;
  padding-block: 1.5mm;
  padding-inline: 0;
}

.thermal-total-main {
  font-size: 11px;
  font-weight: bold;
}

.thermal-footer {
  border-block-start: 1px dashed #000;
  font-size: 11px;
  font-weight: bold;
  margin-block-start: 3mm;
  padding-block-start: 2mm;
  text-align: center;
}
.thermal-non-fiscal-highlight {
  border: 1px solid #000;
  font-size: 13px !important;
  font-weight: 900 !important;
  margin-inline: 4mm;
  padding-block: 1mm;
}
</style>
