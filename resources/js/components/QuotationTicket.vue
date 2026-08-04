<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useAuthStore } from "@/stores/auth";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed } from "vue";

const brandingStore = useBrandingStore();

const props = defineProps({
  quotationDetails: {
    type: Object,
    default: null,
  },
  quotationItems: {
    type: Array,
    default: () => [],
  },
  totalProductsAmount: {
    type: Number,
    default: 0,
  },
  totalQuotationAmount: {
    type: Number,
    default: 0,
  },
  totalIvaAmount: {
    type: Number,
    default: 0,
  },
  selectedDisplayCurrency: {
    type: String,
    default: "USD",
  },
  baseUrl: {
    type: String,
    default: "/",
  },
});

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const getItemPriceByCurrency = (item, currency) => {
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

const formattedTotalQuotation = computed(() => {
  let amountToFormat = props.totalQuotationAmount;
  if (props.selectedDisplayCurrency === "COP") {
    amountToFormat = Math.ceil(amountToFormat / 100) * 100;
  }
  return formatCurrency(amountToFormat, props.selectedDisplayCurrency);
});

const logoSrc = computed(() => {
  return brandingStore.settings?.app_logo || BASE64_LOGO_DATA;
});

const formattedDateAndFullTime = computed(() => {
  const now = new Date();
  const datePart = now.toLocaleDateString("es-VE", {
    day: "numeric",
    month: "numeric",
    year: "numeric",
  });
  const timePart = now.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  });
  return `${datePart}, ${timePart}`;
});
</script>

<template>
  <div id="orderInvoicePrintArea">
    <div class="thermal-print ticket-bold">
      <div class="thermal-header">
        <img
          :src="logoSrc"
          alt="Logo"
          class="thermal-logo"
          width="130"
          height="auto"
        />
      </div>

      <div class="text-center">
        <div class="thermal-company-name font-weight-black text-uppercase">{{ brandingStore.settings?.app_name || 'TOVA' }}</div>
      </div>

      <div class="thermal-quotation-header">
        <div class="thermal-quotation-number">
          Cotización N°{{ quotationDetails ? quotationDetails.id : "N/A" }}
        </div>
        <div class="thermal-date">
          {{ formattedDateAndFullTime }}
        </div>
      </div>

      <div class="thermal-cashier">
        <span>Cajero:</span>
        <span v-if="currentUser">{{ currentUser.username }}</span>
        <span v-else>No logueado</span>
      </div>

      <div class="thermal-items">
        <div
          v-for="(item, index) in quotationItems"
          :key="item.id"
          class="thermal-item"
        >
          <div class="thermal-item-qty">{{ item.selectedQuantity }}x</div>
          <div class="thermal-item-details">
            <span class="thermal-item-name">
              {{ item.title }}
            </span>
            <span class="thermal-item-laboratory" v-if="item.laboratory">
              {{ item.laboratory }}
            </span>
          </div>
          <div class="thermal-item-price">
            {{
              formatCurrency(
                getItemPriceByCurrency(item, selectedDisplayCurrency) *
                  item.selectedQuantity,
                selectedDisplayCurrency
              )
            }}
          </div>
        </div>
      </div>

      <div class="thermal-total">
        <span class="thermal-total-label">TOTAL:</span>
        <span class="thermal-total-amount">{{ formattedTotalQuotation }}</span>
      </div>

      <div class="thermal-footer">Cotización válida solo por hoy</div>
    </div>
  </div>
</template>

<style scoped>
:root {
  --thermal-width: 54mm;
  --thermal-padding: 2mm;
  --thermal-font-size: 9px;
  --thermal-line-height: 1.2;
}

.thermal-print {
  box-sizing: border-box !important;
  padding: var(--thermal-padding) !important;
  margin: 0 !important;
  color: #000 !important;
  font-family: "Courier New", monospace !important;
  font-size: var(--thermal-font-size) !important;
  font-weight: 900 !important;
  inline-size: var(--thermal-width) !important;
  line-height: var(--thermal-line-height) !important;
  max-inline-size: var(--thermal-width) !important;
  min-inline-size: var(--thermal-width) !important;
}

.thermal-print * {
  box-sizing: border-box !important;
  padding: 0 !important;
  margin: 0 !important;
  color: #000 !important;
  font-weight: 900 !important;
  max-inline-size: calc(var(--thermal-width) - 2 * var(--thermal-padding)) !important;
  overflow-wrap: break-word !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
  word-wrap: break-word !important;
}

.thermal-header {
  margin-block-end: 2mm !important;
  text-align: center !important;
}

.thermal-logo {
  display: block !important;
  block-size: auto !important;
  margin-block: 0 !important;
  margin-inline: auto !important;
  max-inline-size: 40mm !important;
}

.thermal-rif {
  color: #000 !important;
  font-size: 8px !important;
  font-weight: 900 !important;
  margin-block: 1mm !important;
  margin-inline: 0 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-company-name {
  color: #000 !important;
  font-size: 8px !important;
  font-weight: 900 !important;
  line-height: 1.1 !important;
  margin-block: 1mm !important;
  margin-inline: 0 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-address {
  color: #000 !important;
  font-size: 7px !important;
  font-weight: 900 !important;
  line-height: 1.1 !important;
  margin-block: 0.5mm !important;
  margin-inline: 0 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-quotation-header {
  display: flex !important;
  align-items: flex-start !important;
  justify-content: space-between !important;
  margin-block: 2mm !important;
}

.thermal-quotation-number {
  color: #000 !important;
  font-size: 9px !important;
  font-weight: 900 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-date {
  color: #000 !important;
  font-size: 8px !important;
  font-weight: 900 !important;
  text-align: end !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-cashier {
  display: flex !important;
  justify-content: space-between !important;
  color: #000 !important;
  font-size: 8px !important;
  font-weight: 900 !important;
  margin-block-end: 1mm !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-items {
  margin-block-start: 2mm !important;
}

.thermal-item {
  display: flex !important;
  justify-content: space-between !important;
  margin-block-end: 1mm !important;
  padding-block-end: 1mm !important;
}

.thermal-item-qty {
  flex: 0 0 8mm !important;
  color: #000 !important;
  font-size: 8px !important;
  font-weight: 900 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-item-details {
  flex: 1 !important;
  color: #000 !important;
  font-size: 7px !important;
  font-weight: 900 !important;
  line-height: 1.1 !important;
  padding-block: 0 !important;
  padding-inline: 1mm !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-item-price {
  flex: 0 0 15mm !important;
  color: #000 !important;
  font-size: 8px !important;
  font-weight: 900 !important;
  text-align: end !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-item-name {
  display: block !important;
  color: #000 !important;
  font-weight: 900 !important;
  margin-block-end: 0.5mm !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-item-laboratory {
  display: block !important;
  color: #000 !important;
  font-size: 6px !important;
  font-weight: 900 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.thermal-total {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  margin-block: 2mm !important;
  padding-block-start: 2mm !important;
}

.thermal-total-label {
  color: #000 !important;
  font-size: 9px !important;
  font-weight: 900 !important;
  -webkit-text-stroke: 0.15px #000 !important;
  text-stroke: 0.15px #000 !important;
}

.thermal-total-amount {
  color: #000 !important;
  font-size: 10px !important;
  font-weight: 900 !important;
  text-align: end !important;
  -webkit-text-stroke: 0.15px #000 !important;
  text-stroke: 0.15px #000 !important;
}

.thermal-footer {
  color: #000 !important;
  font-size: 7px !important;
  font-weight: 900 !important;
  margin-block-start: 3mm !important;
  padding-block-start: 2mm !important;
  text-align: center !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.text-center {
  text-align: center !important;
}

.text-right {
  text-align: end !important;
}

.font-bold {
  color: #000 !important;
  font-weight: 900 !important;
  -webkit-text-stroke: 0.1px #000 !important;
  text-stroke: 0.1px #000 !important;
}

.mt-1 {
  margin-block-start: 1mm !important;
}

.mb-1 {
  margin-block-end: 1mm !important;
}

.no-print {
  display: none !important;
}
</style>
