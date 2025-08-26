<script setup>
import { computed } from 'vue';
import { formatCurrency } from "@/utils/currencyFormatter";
import ExpiredDetailView from '@/components/ExpiredDetailView.vue';
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js"
import { BASE64_LOGO_DATA } from '@/constants/logo.js';
import { useAuthStore } from "@/stores/auth";

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


const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

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


const formattedDateAndFullTime = computed(() => {
  const now = new Date();
  const datePart = now.toLocaleDateString('es-VE', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric'
  });
  const timePart = now.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  });
  return `${datePart}, ${timePart}`;
});

</script>
<template>
 <div class="col-12 col-md-8 mx-auto">
 <VCard   
  variant="outlined"    
  class="pa-2 text-start">
    <div class="text-center pa-2 mb-2">
        <a href="#">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca">
        </a>
    </div>
     <div class="text-center">
        <span class='headerPrint'>J-50540695-7</span>
    </div>
    <div class="text-center">
        <span class='headerPrint'>FARMACIA BARRIO SUCRE 2024, C.A.</span>
    </div>
    <div class="text-center">
         <span class='headerPrint'>CALLE PRINCIPAL LOCAL 05 (L5)</span>
    </div>
     <div class="text-center">
         <span class='headerPrint'>SECTOR BARRIO SUCRE LA FRIA TACHIRA</span>
    </div>
    <div class="text-center">
       <span class='headerPrint'>ZONA POSTAL 5020</span>
    </div>
    <div class="ticket-header d-flex justify-space-between align-start mt-2">
      <span class="font-weight-bold tituloAzulPrint">Cotización N°{{ quotationDetails ? quotationDetails.id : 'N/A' }}</span>
      <div class="text-right d-flex flex-column align-end">
      <p class="text-black font-weight-regular mb-0 textoPrint">Fecha: {{ formattedDateAndFullTime }}</p>
      </div>
    </div>
    <div class="d-flex justify-space-between align-start textoPrint mb-1">
      <span class="textoPrint">Cajero:</span>
      <span v-if="currentUser">{{ currentUser.username }}</span>
      <span v-else>No logueado</span>
    </div>

    <div class="ticket-body mt-2">
      <div v-for="item in quotationItems" :key="item.id" class="ticket-item">
          <span class="ticket-item-qty">{{ item.selectedQuantity }}x</span>
          <span class="ticket-item-name">{{ item.title }}</span>
           <span class="ticket-item-total">
            {{ formatCurrency(getItemPriceByCurrency(item, selectedDisplayCurrency) * item.selectedQuantity, selectedDisplayCurrency) }}
          </span>
      </div>
     <hr />
      <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold tituloAzulPrint">TOTAL:</span> 
          <span class="text-end font-weight-black tituloAzulPrint">{{formattedTotalQuotation}}
          </span>
      </div>
    </div>
      <p class="ticket-footer mt-auto">Cotización válida solo por hoy</p>
  </VCard>
  </div>
</template>

