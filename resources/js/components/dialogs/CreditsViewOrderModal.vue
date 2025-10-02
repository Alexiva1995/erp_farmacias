<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  creditsData: {
    type: Object,
    default: () => ({}),
  },
  selectedCurrency: {
    type: String,
    default: "USD",
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

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
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center p-2">
        <span class="text-h5 font-weight-bold pr-1"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VCardText>
      <div class="text-center">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </div>
        <div class="text-center">
          <span class="font-weight-regular">J-50540695-7</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular">FARMACIA BARRIO SUCRE 2024, C.A.</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular">CALLE PRINCIPAL LOCAL 05 (L5)</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular">SECTOR BARRIO SUCRE LA FRIA TACHIRA</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular">ZONA POSTAL 5020</span>
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
<div v-for="credit in props.creditsData" :key="credit.order.id" class="my-4">
    <VCard>
      <VCardTitle class="d-flex justify-space-between align-center">
        <span class='font-weight-bold text-h6'>Orden #{{ credit.order.id }}</span>
        <span class="text-body-2 text-medium-emphasis">
          Total: {{ credit.order.total_amount}} {{ credit.order.currency }}
        </span>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': credit.order.details.length > 2 }"
        >
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="details in credit.order.details"
              :key="details.product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ details.quantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2">
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
    </VCard>
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

  </VCardText>
      <VDivider />
    </VCard>
  </VDialog>
</template>
<style scoped>
.card-list .v-list-item:not(:last-child) {
  padding-block: 4px !important;
  padding-block-end: 0 !important;
}

.v-list .v-list-item--nav:not(:only-child) {
  margin-block-end: 0 !important;
}
</style>
