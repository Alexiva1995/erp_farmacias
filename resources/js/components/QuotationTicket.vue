<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { useAuthStore } from "@/stores/auth";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed } from "vue";

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
  return BASE64_LOGO_DATA;
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
    <div class="thermal-print">
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
        <div class="thermal-rif">J-50540695-7</div>
        <div class="thermal-company-name">FARMACIA BARRIO SUCRE 2024, C.A.</div>
        <div class="thermal-address">CALLE PRINCIPAL LOCAL 05 (L5)</div>
        <div class="thermal-address">SECTOR BARRIO SUCRE LA FRIA TACHIRA</div>
        <div class="thermal-address">ZONA POSTAL 5020</div>
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
  width: var(--thermal-width) !important;
  max-width: var(--thermal-width) !important;
  min-width: var(--thermal-width) !important;
  margin: 0 !important;
  padding: var(--thermal-padding) !important;
  font-family: "Courier New", monospace !important;
  font-size: var(--thermal-font-size) !important;
  line-height: var(--thermal-line-height) !important;
  box-sizing: border-box !important;
}

.thermal-print * {
  max-width: calc(var(--thermal-width) - 2 * var(--thermal-padding)) !important;
  box-sizing: border-box !important;
  word-wrap: break-word !important;
  overflow-wrap: break-word !important;
  margin: 0 !important;
  padding: 0 !important;
}

.thermal-header {
  text-align: center !important;
  margin-bottom: 2mm !important;
}

.thermal-logo {
  max-width: 40mm !important;
  height: auto !important;
  margin: 0 auto !important;
  display: block !important;
}

.thermal-rif {
  font-size: 8px !important;
  font-weight: bold !important;
  margin: 1mm 0 !important;
}

.thermal-company-name {
  font-size: 8px !important;
  font-weight: bold !important;
  margin: 1mm 0 !important;
  line-height: 1.1 !important;
}

.thermal-address {
  font-size: 7px !important;
  margin: 0.5mm 0 !important;
  line-height: 1.1 !important;
}

.thermal-quotation-header {
  display: flex !important;
  justify-content: space-between !important;
  align-items: flex-start !important;
  margin-top: 2mm !important;
  margin-bottom: 2mm !important;
}

.thermal-quotation-number {
  font-size: 9px !important;
  font-weight: bold !important;
}

.thermal-date {
  font-size: 8px !important;
  text-align: right !important;
}

.thermal-cashier {
  display: flex !important;
  justify-content: space-between !important;
  font-size: 8px !important;
  margin-bottom: 1mm !important;
}

.thermal-items {
  margin-top: 2mm !important;
}

.thermal-item {
  display: flex !important;
  justify-content: space-between !important;
  margin-bottom: 1mm !important;
  padding-bottom: 1mm !important;
}

.thermal-item-qty {
  flex: 0 0 8mm !important;
  font-size: 8px !important;
}

.thermal-item-details {
  flex: 1 !important;
  padding: 0 1mm !important;
  font-size: 7px !important;
  line-height: 1.1 !important;
}

.thermal-item-price {
  flex: 0 0 15mm !important;
  text-align: right !important;
  font-size: 8px !important;
  font-weight: bold !important;
}

.thermal-item-name {
  display: block !important;
  font-weight: bold !important;
  margin-bottom: 0.5mm !important;
}

.thermal-item-laboratory {
  display: block !important;
  font-size: 6px !important;
  color: #666 !important;
}

.thermal-total {
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  margin-top: 2mm !important;
  margin-bottom: 2mm !important;
  padding-top: 2mm !important;
}

.thermal-total-label {
  font-size: 9px !important;
  font-weight: bold !important;
}

.thermal-total-amount {
  font-size: 10px !important;
  font-weight: bold !important;
  text-align: right !important;
}

.thermal-footer {
  text-align: center !important;
  font-size: 7px !important;
  margin-top: 3mm !important;
  padding-top: 2mm !important;
}

.text-center {
  text-align: center !important;
}

.text-right {
  text-align: right !important;
}

.font-bold {
  font-weight: bold !important;
}

.mt-1 {
  margin-top: 1mm !important;
}

.mb-1 {
  margin-bottom: 1mm !important;
}

.no-print {
  display: none !important;
}
</style>
