<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const props = defineProps({
  creditsData: {
    type: Object,
    default: () => ({}),
  },
});

const logoSrc = computed(() => {
  return brandingStore.settings?.app_logo || BASE64_LOGO_DATA;
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
  <div class="col-12 col-md-8 mx-auto ticket-black-text">
    <VCard variant="outlined" class="pa-2 text-start border-0 text-black">
      <div class="text-center pa-2 mb-2">
        <a href="#">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </a>
      </div>

      <div class="d-flex justify-space-between align-start mb-1">
        <span class="font-weight-bold text-h6 text-black">Cliente:</span>
        <span class="font-weight-bold text-h6 text-black"
          >{{ props.creditsData[0].client.name }}
          {{ props.creditsData[0].client.last_name }}</span
        >
      </div>
      <div class="d-flex justify-space-between align-start mb-1">
        <span class="font-weight-bold text-h6 text-black">Documento:</span>
        <span class="font-weight-bold text-h6 text-black">
          {{ props.creditsData[0].client.identification_type }}
          {{ props.creditsData[0].client.identification }}</span
        >
      </div>

      <div
        v-for="credit in props.creditsData"
        :key="credit.order.id"
        class="my-1"
      >
        <div class="m-0">
          <VCardTitle class="d-flex justify-space-between align-center">
            <span class="font-weight-bold text-h6 text-black"
              >Orden #{{ credit.order.id }}</span
            >
            <span class="text-body-2 text-black">
              Total: {{ credit.order.total_amount }} {{ credit.order.currency }}
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
                    <span class="text-black">{{ details.quantity }} x</span>
                  </template>

                  <VListItemTitle class="font-weight-medium me-4 text-black">
                    {{ details.product.name }}
                  </VListItemTitle>

                  <template #append>
                    <div class="d-flex align-center">
                      <span class="text-body-1 me-2 text-black">
                        {{ details.unit_price_usd }} {{ credit.order.currency }}
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
        <span class="font-weight-bold text-h6 text-black">TOTAL CREDITO:</span>
        <span class="text-end font-weight-bold text-h6 text-black"
          >{{ totalCredits }} USD
        </span>
      </div>
      <div class="ticket-total d-flex justify-space-between align-center">
        <span class="font-weight-bold text-h6 text-black">TOTAL CREDITO PENDIENTE:</span>
        <span class="text-end font-weight-bold text-h6 text-black"
          >{{ totalPendingAmount }} USD
        </span>
      </div>

      <p class="font-weight-bold text-center text-black mt-2">
        ¡GRACIAS POR PREFERIRNOS!
      </p>
    </VCard>
  </div>
</template>

<style scoped>
.ticket-black-text,
.ticket-black-text * {
  color: #000000 !important;
}
</style>
