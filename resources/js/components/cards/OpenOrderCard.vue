<script setup>
import { defineProps, computed } from 'vue';
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  order: {
    type: Object,
    required: true
  },
  cliente: {
    type: Object,
    required: false,
    default: null
  },
   selectedDisplayCurrency: { 
    type: String,
    default: "COP",
  },
   totalIvaAmount: {
    type: Number,
    default: 0,
  },
    totalProductsAmount: {
    type: Number,
    default: 0,
  },
    totalQuotationAmount: {
    type: Number,
    default: 0,
  },
  quotationProducts: {
    type: Array,
    default: () => [],
  },
});

const clientName = computed(() => {
  return props.cliente ? `${props.cliente.name} ${props.cliente.last_name}` : 'Cliente Desconocido';
});

const Identidad = computed(() => {
  return props.cliente ? `${props.cliente.identification_type} ${props.cliente.identification}` : '';
});

const availableCurrency = ref(["USD", "BS", "COP"]);
const emit = defineEmits(['currency-changed']);

const chipColor = "primary";

const breakdownItems = computed(() => {

  let ivaAmount = props.totalIvaAmount;

  // Aplica el redondeo solo si la moneda es 'COP'
  if (props.selectedDisplayCurrency === 'COP') {
    ivaAmount = roundUpToNearestHundred(props.totalIvaAmount);
  }

   return [
    { title: "Precio por producto", amount: props.totalProductsAmount },
    { title: "IVA (16%)", amount: ivaAmount },
  ];
  
});

const formattedTotalQuotation = computed(() => {
  let amountToFormat = props.totalQuotationAmount;
  if (props.selectedDisplayCurrency === 'COP') {
    amountToFormat = Math.ceil(amountToFormat / 100) * 100;
  }
  return formatCurrency(amountToFormat, props.selectedDisplayCurrency);
});

const handleCurrencyChanged = (newCurrency) => {
  selectedDisplayCurrency.value = newCurrency;
};

const selectCurrency = (currency) => {
  emit('currency-changed', currency);
};

const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.quotationProducts.forEach(product => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});

</script>
<template>
 <VCard class="mb-6">
   <template #title>
      <span>Cliente:   {{ clientName }}</span><br>
      <span>Identidad: {{ Identidad }}</span>
    </template>

    <VRow no-gutters> 
    <VCol cols="6"> 
    <VCardItem>
      <VCardTitle>Factura</VCardTitle>
      <template #append>
        <VMenu>
          <template #activator="{ props: menuProps }"> <VBtn
              type="button"
              color="primary"
              variant="tonal"
              density="default"
              size="small"
              class="mx-auto"
              v-bind="menuProps"
            >
              <span>{{ props.selectedDisplayCurrency }}</span>

              <template #append>
                <VIcon icon="tabler-chevron-down" size="16" />
              </template>
            </VBtn>
          </template>

          <VList>
            <VListItem
              v-for="currencyOption in availableCurrency"
              :key="currencyOption"
              :value="currencyOption"
              @click="selectCurrency(currencyOption)"
            >
              <VListItemTitle>{{ currencyOption }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </template>
    </VCardItem>

    <VCardText class="flex-grow-1 d-flex flex-column">
      <VList class="card-list" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-0"
        >
          <VListItemTitle class="font-weight-medium">{{ item.title}}</VListItemTitle>
          <template #append>
            <div class="d-flex align-center">
              <span class="me-3 text-medium-emphasis">{{formatCurrency(item.amount, props.selectedDisplayCurrency)}}</span>
            </div>
          </template>
        </VListItem>
      </VList>

      <VDivider class="mt-auto"/>
      <div class="d-flex align-center justify-space-between gap-x-2 mt-3">
        <h4 class="text-h4 text-center">Monto Total</h4>
        <div class="text-h4 text-success">
           {{ formattedTotalQuotation }}
        </div>
      </div>
    </VCardText>

 </VCol>

      <VCol cols="6">
           <VCardItem>
          <VCardTitle>Productos</VCardTitle>
          <template #append>
            <VChip
              label
              :color="chipColor"
              variant="tonal"
              density="default"
              size="small"
              draggable="false"
              class="ms-auto"
            >
              <span class="font-weight-medium">{{totalSelectedQuantity}}</span>
            </VChip>
          </template>
          
        </VCardItem>
        
        <VCardText>
        <VDivider class="mt-auto"/>
        </VCardText>

    <VCardActions class="pa-4 d-flex flex-wrap justify-space-between">
    <VBtn color="secondary" variant="outlined" @click="remove()" class="flex-grow-1"> Cancelar </VBtn>
    <VBtn color="primary" variant="flat" @click="handlePrintButtonClick" class="flex-grow-1"> Completar </VBtn>
    </VCardActions>

      </VCol>
    </VRow>

    </VCard>
</template>
