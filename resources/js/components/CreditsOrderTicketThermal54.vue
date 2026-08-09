<script setup>
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";

const brandingStore = useBrandingStore();
const logoSrc = computed(() => brandingStore.settings?.app_logo || BASE64_LOGO_DATA);

const props = defineProps({
  creditsData: {
    type: Array,
    default: () => [],
  },
});

const credits = computed(() => Array.isArray(props.creditsData) ? props.creditsData : []);

const clientInfo = computed(() => {
  const first = credits.value[0];
  if (!first) return null;
  return first.client || first.order?.client;
});

const totalPendingAmount = computed(() => {
  return credits.value.reduce((sum, credit) => {
    return sum + (parseFloat(credit.pending_amount) || 0);
  }, 0);
});

const selectedCurrency = computed(() => {
  const first = credits.value[0]?.order;
  return first?.currency ? String(first.currency).toUpperCase() : "USD";
});

const pendingProducts = computed(() => {
  const items = [];
  for (const credit of credits.value) {
    const order = credit.order;
    if (!order?.details) continue;
    const currency = order.currency ? String(order.currency).toUpperCase() : "USD";
    for (const detail of order.details) {
      const product = detail.product || {};
      const qty = parseInt(detail.quantity) || 0;
      const unitPrice = parseFloat(detail.unit_price_usd ?? detail.price ?? 0) || 0;
      const lineTotal = qty * unitPrice;
      const lab = product.laboratory?.name ?? product.laboratory ?? "";
      const name = product.name ?? product.title ?? "—";
      const lineText = lab ? `${qty} X ${name} ${lab}` : `${qty} X ${name}`;
      items.push({ lineText, lineTotal, currency });
    }
  }
  return items;
});

const productLineText = (item) => item.lineText;
</script>

<template>
  <div class="thermal-54-ticket">
    <header class="thermal-header">
      <img class="thermal-logo" :src="logoSrc" alt="Logo" />
    </header>

    <div class="thermal-data">
      <div class="thermal-data-row thermal-title-row">
        <span class="thermal-label">CRÉDITO PENDIENTE</span>
      </div>
      <div v-if="clientInfo" class="thermal-data-row">
        <span class="thermal-label">Cliente:</span>
        <span class="thermal-value thermal-value-cliente">
          {{ clientInfo.name }} {{ clientInfo.last_name }}
        </span>
      </div>
      <div v-if="clientInfo" class="thermal-data-row">
        <span class="thermal-label">Documento:</span>
        <span class="thermal-value">
          {{ clientInfo.identification_type || "" }}{{ clientInfo.identification || "" }}
        </span>
      </div>
    </div>

    <div class="thermal-products">
      <div class="thermal-products-head">
        <span class="thermal-col-desc">Producto</span>
        <span class="thermal-col-amount">Monto</span>
      </div>
      <div
        v-for="(item, idx) in pendingProducts"
        :key="idx"
        class="thermal-product-row"
      >
        <div class="thermal-col-desc">
          <span class="thermal-product-name">{{ productLineText(item) }}</span>
        </div>
        <span class="thermal-col-amount">{{ formatCurrency(item.lineTotal, item.currency) }}</span>
      </div>
    </div>

    <div class="thermal-totals">
      <div class="thermal-total-block">
        <div class="thermal-total-row thermal-total-main">
          <span>TOTAL PENDIENTE</span>
          <span>{{ formatCurrency(totalPendingAmount, selectedCurrency) }}</span>
        </div>
      </div>
    </div>

    <footer class="thermal-footer">
      ¡GRACIAS POR PREFERIRNOS!
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
.thermal-rif { font-size: 9px !important; font-weight: bold !important; margin-block: 0 0.5mm !important; margin-inline: 0 !important; }
.thermal-company { font-size: 10px !important; font-weight: bold !important; margin-block: 0 0.5mm !important; margin-inline: 0 !important; }
.thermal-address { margin: 0 !important; font-size: 9px !important; line-height: 1.15 !important; }
.thermal-data { margin-block-end: 2mm !important; }
.thermal-data-row { display: flex !important; justify-content: space-between !important; font-size: 10px !important; margin-block: 0.5mm !important; margin-inline: 0 !important; }
.thermal-label { flex-shrink: 0 !important; font-weight: bold !important; }
.thermal-value { margin-inline-start: 2mm !important; text-align: end !important; word-break: break-word !important; }
.thermal-value-cliente { text-align: end !important; word-break: break-word !important; }
.thermal-title-row { justify-content: center !important; margin-block-end: 1mm !important; }
.thermal-products { border-block-start: 1px dashed #000 !important; margin-block-end: 2mm !important; padding-block-start: 1mm !important; }
.thermal-products-head { display: flex !important; border-block-end: 1px dashed #000 !important; font-size: 10px !important; font-weight: bold !important; padding-block: 0.4mm !important; padding-inline: 0 !important; }
.thermal-col-desc { flex: 1 !important; min-inline-size: 0 !important; padding-block: 0 !important; padding-inline: 0 1.5mm !important; }
.thermal-col-amount { flex-shrink: 0 !important; inline-size: 16mm !important; text-align: end !important; }
.thermal-product-row { display: flex !important; align-items: flex-start !important; border-block-end: 1px solid #000 !important; font-size: 10px !important; line-height: 1.2 !important; padding-block: 0.5mm !important; padding-inline: 0 !important; }
.thermal-product-row .thermal-col-desc { display: flex !important; flex-direction: column !important; }
.thermal-product-name { font-size: 8px !important; overflow-wrap: break-word !important; word-wrap: break-word !important; }
.thermal-totals { font-size: 10px !important; margin-block-start: 2mm !important; padding-block-start: 1mm !important; }
.thermal-total-row { display: flex !important; justify-content: space-between !important; margin-block: 0.5mm !important; margin-inline: 0 !important; }
.thermal-total-block { border-block-end: 2px solid #000 !important; border-block-start: 2px solid #000 !important; margin-block: 1mm !important; margin-inline: 0 !important; padding-block: 1.5mm !important; padding-inline: 0 !important; }
.thermal-total-main { font-size: 11px !important; font-weight: bold !important; }
.thermal-footer { border-block-start: 1px dashed #000 !important; font-size: 11px !important; font-weight: bold !important; margin-block-start: 3mm !important; padding-block-start: 2mm !important; text-align: center !important; }
</style>
