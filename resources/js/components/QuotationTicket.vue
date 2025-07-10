<script setup>
import { computed } from 'vue';
import { formatCurrency } from "@/utils/currencyFormatter";
import ExpiredDetailView from '@/components/ExpiredDetailView.vue';
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js"
import { BASE64_LOGO_DATA } from '@/constants/logo.js';

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
    default: 'USD',
  },
    baseUrl: {
    type: String,
    default: '/',
  },
});

const getItemPriceByCurrency = (item, currency) => {
  const taxRate = item.taxRate || 0; 
  let basePrice = 0;
  if (currency === 'BS') {
    basePrice = item.price_bs || 0;
  } else if (currency === 'COP') {
    basePrice = item.price_cop || 0;
  } else {
    basePrice = item.price || 0;
  }
  let priceWithIva = basePrice * (1 + taxRate);
   if (currency === 'COP') {
     priceWithIva = roundUpToNearestHundred(priceWithIva);
   }
  return priceWithIva;
};


const formattedTotalQuotation = computed(() => {
  let amountToFormat = props.totalQuotationAmount;
  if (props.selectedDisplayCurrency === 'COP') {
    amountToFormat = Math.ceil(amountToFormat / 100) * 100;
  }
  return formatCurrency(amountToFormat, props.selectedDisplayCurrency);
});


const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

</script>
<template>
 <div class="col-12 col-md-8 mx-auto">
 <VCard   
  variant="outlined"    
  class="pa-2 text-start">

 <div class="text-center pa-2">
        <a href="#">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca">
        </a>
      </div>
    <div class="ticket-header d-flex justify-space-between align-start">
      <span class="font-weight-bold">Cotización #{{ quotationDetails ? quotationDetails.id : 'N/A' }}</span>
      <div class="text-right d-flex flex-column align-end">
      <p class="text-black font-weight-regular mb-0">{{ new Date().toLocaleDateString('es-VE')}}</p>
      <p class="text-black font-weight-regular mb-0"> {{ new Date().toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) }}</p>
      </div>
    </div>

    <div class="ticket-body">
      <div class="ticket-item" style="font-weight: bold;">
          <span class="ticket-item-qty">#</span>
          <span class="ticket-item-name">Producto</span>
          <span class="ticket-item-total">Precio</span>
      </div>
      <VDivider />
      <div v-for="item in quotationItems" :key="item.id" class="ticket-item">
          <span class="ticket-item-qty">{{ item.selectedQuantity }}</span>
          <span class="ticket-item-name">{{ item.title }}</span>
           <span class="ticket-item-total">
            {{ formatCurrency(getItemPriceByCurrency(item, selectedDisplayCurrency) * item.selectedQuantity, selectedDisplayCurrency) }}
          </span>
      </div>
     <hr />
      <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold mb-2">Total</span> 
          <span class="text-end font-weight-black"> {{formattedTotalQuotation}}
          </span>
      </div>
    </div>
    <VDivider />
      <p class=" ticket-footer">Cotización válida solo por hoy</p>
  </VCard>
  </div>
</template>

