<script setup>
import { computed } from 'vue';
import { formatCurrency } from "@/utils/currencyFormatter";

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
  selectedDisplayCurrency: {
    type: String,
    default: 'USD',
  },
});


const finalTotalIVAAmount = computed(() => props.quotationDetails?.vat ?? 0);
const finalTotalQuotationAmount = computed(() => props.quotationDetails?.total ?? 0);

const getItemPriceByCurrency = (item, currency) => {
  if (currency === 'BS') {
    return item.price_bs || 0;
  } else if (currency === 'COP') {
    return item.price_cop || 0;
  } else {
    return item.price || 0;
  }
};

</script>
<template>
<div class="ticket-container">
    <div class="ticket-header">
      <h4>COTIZACIÓN #{{ quotationDetails ? quotationDetails.id : 'N/A' }}</h4>
      <p> Fecha: {{ new Date().toLocaleDateString('es-VE') }}</p>
      <p> {{ new Date().toLocaleTimeString('es-VE') }}</p>
    </div>

    <div class="ticket-body">
      <div class="ticket-item" style="font-weight: bold;">
          <span class="ticket-item-qty">#</span>
          <span class="ticket-item-name">Producto</span>
          <span class="ticket-item-total">Precio</span>
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
        <span>{{ formatCurrency(finalTotalIVAAmount, selectedDisplayCurrency) }}</span>
      </div>
      <hr>
      <div class="ticket-line ticket-total">
        <span>TOTAL:</span>
        <span>{{ formatCurrency(finalTotalQuotationAmount, selectedDisplayCurrency) }}</span>
      </div>
    </div>

    <div class="ticket-footer">
      <hr>
      <p>¡Gracias por su preferencia!</p>
      <p>Cotización valida solo por hoy</p>
    </div>
  </div>
</template>
