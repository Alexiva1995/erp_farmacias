<script setup>
import { computed } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatCurrency } from "@/utils/currencyFormatter";

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
      <img class="thermal-logo" :src="BASE64_LOGO_DATA" alt="Logo" />
      <div class="thermal-rif">J-50540695-7</div>
      <div class="thermal-company">FARMACIA BARRIO SUCRE 2024, C.A.</div>
      <div class="thermal-address">CALLE PRINCIPAL LOCAL 05 (L5)</div>
      <div class="thermal-address">SECTOR BARRIO SUCRE LA FRIA TACHIRA</div>
      <div class="thermal-address">ZONA POSTAL 5020</div>
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
  width: 54mm;
  max-width: 54mm;
  min-width: 54mm;
  padding: 2mm;
  margin: 0;
  background: #ffffff;
  color: #000000;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10px;
  line-height: 1.25;
  box-sizing: border-box;
  text-transform: uppercase;
}
.thermal-54-ticket * {
  color: #000000 !important;
  background: transparent !important;
  box-sizing: border-box;
  text-transform: uppercase !important;
}
.thermal-header {
  text-align: center;
  margin-bottom: 2mm;
  padding-bottom: 2mm;
  border-bottom: 1px dashed #000000;
}
.thermal-logo {
  display: block;
  margin: 0 auto 1mm;
  max-width: 40mm;
  height: auto;
  filter: grayscale(100%) contrast(1.1);
}
.thermal-rif { font-size: 9px !important; font-weight: bold !important; margin: 0 0 0.5mm !important; }
.thermal-company { font-size: 10px !important; font-weight: bold !important; margin: 0 0 0.5mm !important; }
.thermal-address { font-size: 9px !important; margin: 0 !important; line-height: 1.15 !important; }
.thermal-data { margin-bottom: 2mm !important; }
.thermal-data-row { display: flex !important; justify-content: space-between !important; margin: 0.5mm 0 !important; font-size: 10px !important; }
.thermal-label { font-weight: bold !important; flex-shrink: 0 !important; }
.thermal-value { text-align: right !important; word-break: break-word !important; margin-left: 2mm !important; }
.thermal-value-cliente { text-align: right !important; word-break: break-word !important; }
.thermal-title-row { justify-content: center !important; margin-bottom: 1mm !important; }
.thermal-products { margin-bottom: 2mm !important; border-top: 1px dashed #000000 !important; padding-top: 1mm !important; }
.thermal-products-head { display: flex !important; font-size: 10px !important; font-weight: bold !important; padding: 0.4mm 0 !important; border-bottom: 1px dashed #000000 !important; }
.thermal-col-desc { flex: 1 !important; min-width: 0 !important; padding: 0 1.5mm 0 0 !important; }
.thermal-col-amount { width: 16mm !important; flex-shrink: 0 !important; text-align: right !important; }
.thermal-product-row { display: flex !important; align-items: flex-start !important; padding: 0.5mm 0 !important; font-size: 10px !important; line-height: 1.2 !important; border-bottom: 1px solid #000000 !important; }
.thermal-product-row .thermal-col-desc { display: flex !important; flex-direction: column !important; }
.thermal-product-name { font-size: 8px !important; word-wrap: break-word !important; overflow-wrap: break-word !important; }
.thermal-totals { margin-top: 2mm !important; padding-top: 1mm !important; font-size: 10px !important; }
.thermal-total-row { display: flex !important; justify-content: space-between !important; margin: 0.5mm 0 !important; }
.thermal-total-block { border-top: 2px solid #000000 !important; border-bottom: 2px solid #000000 !important; padding: 1.5mm 0 !important; margin: 1mm 0 !important; }
.thermal-total-main { font-size: 11px !important; font-weight: bold !important; }
.thermal-footer { text-align: center !important; font-size: 11px !important; font-weight: bold !important; margin-top: 3mm !important; padding-top: 2mm !important; border-top: 1px dashed #000000 !important; }
</style>
