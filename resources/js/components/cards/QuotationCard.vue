<template>
  <VCard>
     <VCardItem>
            <VCardTitle>Cotización</VCardTitle>
            <template #append>
              <IconBtn
                size="small"
                :color="isCodeShown ? 'primary' : 'default'"
                :class="isCodeShown ? '' : 'text-disabled'"
                @click="isCodeShown = !isCodeShown"
              >
                <VIcon size="20" icon="tabler-code" />
              </IconBtn>
            </template>
          </VCardItem>
    <VCardText>
      <VList class="card-list" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-0"
        >
          <VListItemTitle class="font-weight-medium">{{ item.title }}</VListItemTitle>

          <template #append>
            <span class="me-3 text-medium-emphasis">{{ formatCurrency(item.amount) }}</span>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<script setup>
import { ref } from 'vue';

// --- Datos que alimentan el card ---
const earningsData = ref({
  amount: 4374.42, // Usar números reales, el formateo será en la función formatCurrency
  percentageChange: 10.2,
});

const breakdownItems = ref([
  { title: 'Exento', amount: 756.26},
  { title: 'IVA', amount: 2207.03 },
]);
// --- Funciones de Formato ---
const formatCurrency = (value) => {
  // Ajusta 'es-VE' y 'USD' según tu necesidad
  return new Intl.NumberFormat('es-VE', { style: 'currency', currency: 'USD' }).format(value);
};
</script>

<style scoped>
.card-list .v-list-item {
  padding-inline: 0px !important; /* Si necesitas quitar padding extra */
}
</style>
