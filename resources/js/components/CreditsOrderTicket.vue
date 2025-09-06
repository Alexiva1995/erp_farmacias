<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { defineProps, defineEmits, computed, ref, watch } from "vue";
const props = defineProps({
  creditsData: {
    type: Object,
    default: () => ({}),
  },
});

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});


const totalCredits = computed(() => {
  return props.creditsData.reduce((sum, credit) => {
    const creditAmount = parseFloat(credit.credit_amount) || 0;
    return sum + creditAmount;
  }, 0);
});

const totalPendingAmount = computed(() => {
  return props.creditsData.reduce((sum, credit) => {
    const pendingAmount = parseFloat(credit.pending_amount) || 0;
    return sum + pendingAmount;
  }, 0);
});

</script>
<template>
  <div class="col-12 col-md-8 mx-auto">
    <VCard variant="outlined" class="pa-2 text-start">
    <div class="text-center pa-2 mb-2">
        <a href="#">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </a>
      </div>
      <div class="text-center">
        <span class="headerPrint">J-50540695-7</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">FARMACIA BARRIO SUCRE 2024, C.A.</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">CALLE PRINCIPAL LOCAL 05 (L5)</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">SECTOR BARRIO SUCRE LA FRIA TACHIRA</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">ZONA POSTAL 5020</span>
      </div>

<div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Cliente:</span>
          <span class="font-weight-bold text-h6"
            >{{ props.creditsData[0].client.name }}
                {{ props.creditsData[0].client.last_name }}</span
          >
        </div>
         <div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Documento:</span>
          <span class="font-weight-bold text-h6"
            >   {{ props.creditsData[0].client.identification_type}}
                {{ props.creditsData[0].client.identification}}</span
          >
        </div>

        <div v-for="credit in props.creditsData" :key="credit.order.id" class="my-1">

    <div class='m-0'>
      <VCardTitle class="d-flex justify-space-between align-center">
        <span class='font-weight-bold text-h6'>Orden #{{ credit.order.id }}</span>
        <span class="text-body-2 text-medium-emphasis">
          Total: {{ credit.order.total_amount}} {{ credit.order.currency }}
        </span>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <div>
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="details in credit.order.details"
              :key="details.product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ details.quantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4">
               {{ details.product.name }}
              </VListItemTitle>

              <template #append>
                <div class="d-flex align-center">
                  <span class="text-body-1 me-2">
                    {{details.unit_price_usd }} {{ credit.order.currency }}
                  </span>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
      </VCardText>
    </div>
  </div>
    <hr />
          <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold text-h6">TOTAL CREDITO:</span> 
          <span class="text-end font-weight-bold text-h6">{{totalCredits}} USD
          </span>
      </div>
       <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold text-h6">TOTAL CREDITO PENDIENTE:</span> 
          <span class="text-end font-weight-bold text-h6">{{totalPendingAmount}} USD
          </span>
      </div>

      <p class="font-weight-bold text-center text-success">
        ¡GRACIAS POR PREFERIRNOS!
      </p>
    </VCard>
  </div>
</template>
