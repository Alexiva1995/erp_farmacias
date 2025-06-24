<script setup>
import { ref } from "vue";

const selectedCurrency = ref("USD");
const availableCurrency = ref(["USD", "BS", "COP"]);
const totalQuotation = ref("0.00");

// --- Datos que alimentan el card ---
const earningsData = ref({
  amount: 4374.42, // Usar números reales, el formateo será en la función formatCurrency
});

const breakdownItems = ref([
  { title: "Exento", amount: 756.26 },
  { title: "IVA", amount: 2207.03 },
]);

// --- Funciones de Formato ---
const formatCurrency = (value) => {
  const formattedNumber = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2, // Asegura 2 decimales
    maximumFractionDigits: 2,
  }).format(value);

  // Luego, añadir el símbolo de moneda manualmente
  return `${formattedNumber} USD`;
};

const selectCurrency = (currency) => {
  selectedCurrency.value = currency;
};
</script>
<style scoped>
.card-list .v-list-item {
  padding-inline: 0px !important;
}
</style>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Cotización</VCardTitle>

      <template #append>
        <VMenu>
          <template #activator="{ props }">
            <VBtn
              type="button"
              color="primary"
              variant="tonal"
              density="default"
              size="small"
              class="mx-auto"
              v-bind="props"
            >
              <span>{{ selectedCurrency }}</span>

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
    <VCardText>
      <VList class="card-list" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-0"
        >
          <VListItemTitle class="font-weight-medium">{{
            item.title
          }}</VListItemTitle>
          <template #append>
            <div class="d-flex align-center">
              <span class="me-3 text-medium-emphasis">{{
                formatCurrency(item.amount)
              }}</span>
            </div>
          </template>
        </VListItem>
      </VList>

      <VDivider />
      <div class="d-flex align-center justify-space-between gap-x-2 mt-3">
        <h4 class="text-h4 text-center">Total Cotización</h4>

        <div class="text-h4 text-success">
          {{ formatCurrency(totalQuotation) }}
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
