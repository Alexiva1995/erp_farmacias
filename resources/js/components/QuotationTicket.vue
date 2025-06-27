<script setup>
import { computed } from 'vue';
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
  totalIVAAmount: {
    type: Number,
    default: 0,
  },
  totalQuotationAmount: {
    type: Number,
    default: 0,
  },
  selectedDisplayCurrency: {
    type: String,
    default: 'USD',
  },
});


const getItemPriceByCurrency = (item, currency) => {
  if (currency === 'BS') {
    return item.price_bs || 0;
  } else if (currency === 'COP') {
    return item.price_cop || 0;
  } else {
    return item.price || 0;
  }
};

const formatCurrency = (value, currency = props.selectedDisplayCurrency) => {
  if (typeof value !== 'number' || isNaN(value)) {
    value = 0;
  }
  let locale = 'en-US';
  let currencyCode = currency;

  if (currency === 'BS') {
    locale = 'es-VE';
    currencyCode = 'VEF'; // Usa VEF o VES dependiendo de tu estándar ISO para el Bolívar
  } else if (currency === 'COP') {
    locale = 'es-CO';
    currencyCode = 'COP';
  } else if (currency === 'USD') {
    locale = 'en-US';
    currencyCode = 'USD';
  }

  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

watch(() => props.totalIVAAmount, (newValue, oldValue) => {
  console.log('QuotationTicket: totalIVAAmount ha cambiado de', oldValue, 'a', newValue);
}, { immediate: true });

</script>
<template>
<div class="ticket-container">
    <div class="ticket-header">
      <h3>Nombre de tu Empresa/Tienda</h3>
      <p>Dirección: Calle 1, Sector X, Santa Teresa del Tuy</p>
      <p>Teléfono: (0412) 123-4567</p>
      <p>RIF: J-12345678-9</p>
      <hr>
      <h4>COTIZACIÓN #{{ quotationDetails ? quotationDetails.id : 'N/A' }}</h4>
      <p>Fecha: {{ new Date().toLocaleDateString('es-VE') }}</p>
      <p>Hora: {{ new Date().toLocaleTimeString('es-VE') }}</p>
      <hr>
    </div>

    <div class="ticket-body">
      <div class="ticket-item" style="font-weight: bold;">
          <span class="ticket-item-qty">Cantidad.</span>
          <span class="ticket-item-name">Producto</span>
          <span class="ticket-item-total">Total</span>
      </div>
      <hr>
      <div v-for="item in quotationItems" :key="item.id" class="ticket-item">
          <span class="ticket-item-qty">{{ item.selectedQuantity }}</span>
          <span class="ticket-item-name">{{ item.title }}</span>
           <span class="ticket-item-total">
            {{ formatCurrency(getItemPriceByCurrency(item, selectedDisplayCurrency) * item.selectedQuantity, selectedDisplayCurrency) }}
          </span>
         
      </div>
      <hr>
       <div class="ticket-line">
        <span>Subtotal:</span>
        <span>{{ formatCurrency(totalProductsAmount, selectedDisplayCurrency) }}</span>
      </div>
      <div class="ticket-line">
        <span>IVA (16%):</span>
        <span>{{ totalIVAAmount }}</span>
      </div>
      <hr>
      <div class="ticket-line ticket-total">
        <span>TOTAL:</span>
        <span>{{ formatCurrency(totalQuotationAmount, selectedDisplayCurrency) }}</span>
      </div>
    </div>

    <div class="ticket-footer">
      <hr>
      <p>¡Gracias por su preferencia!</p>
      <p>Validez de la cotización: XX días.</p>
    </div>
  </div>
</template>
