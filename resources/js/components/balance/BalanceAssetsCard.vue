<script setup>
import VueApexCharts from "vue3-apexcharts";

defineProps({
  balance: {
    type: Object,
    required: true,
  },
  chartOptions: {
    type: Object,
    required: true,
  },
  donutSeries: {
    type: Array,
    required: true,
  },
  donutHeight: {
    type: Number,
    required: true,
  },
  isMounted: {
    type: Boolean,
    required: true,
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <VCard class="rounded-lg border shadow-sm w-100 h-100 d-flex flex-column">
    <VCardItem>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-trending-up" color="success" class="me-2" />
        Estructura de Activos
      </VCardTitle>
    </VCardItem>
    <VDivider />
    <VRow no-gutters class="flex-grow-1">
      <VCol cols="12" sm="5" class="pa-2 pa-sm-4 d-flex align-center justify-center">
        <VueApexCharts
          v-if="isMounted"
          type="donut"
          :height="donutHeight"
          :options="chartOptions.donut"
          :series="donutSeries"
        />
      </VCol>
      <VCol cols="12" sm="7" class="d-flex flex-column">
        <VList density="comfortable" class="pa-2 pa-sm-4 flex-grow-1">
          <VListItem>
            <template #prepend>
              <VAvatar size="32" color="success" variant="tonal" class="me-3">
                <VIcon icon="tabler-cash" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium">Efectivo en Caja</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">{{ formatCurrency(balance.assets.details.cash) }}</span>
            </template>
          </VListItem>

          <VListItem>
            <template #prepend>
              <VAvatar size="32" color="info" variant="tonal" class="me-3">
                <VIcon icon="tabler-package" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium">Inventario</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">{{ formatCurrency(balance.assets.details.inventory) }}</span>
            </template>
          </VListItem>

          <VListItem>
            <template #prepend>
              <VAvatar size="32" color="warning" variant="tonal" class="me-3">
                <VIcon icon="tabler-sofa" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium">Mobiliario Bruto</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">{{ formatCurrency(balance.assets.details.furniture_bruto) }}</span>
            </template>
          </VListItem>

          <VDivider class="my-2" />

          <VListItem class="text-error">
            <template #prepend>
              <VIcon icon="tabler-trending-down" class="me-3" />
            </template>
            <VListItemTitle>Depreciación Acumulada</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">- {{ formatCurrency(balance.assets.depreciation) }}</span>
            </template>
          </VListItem>
        </VList>
        <div class="px-3 px-sm-6 pb-6 pt-2 mt-auto">
          <VAlert color="success" variant="tonal" class="rounded-lg border-opacity-25" density="compact">
            <div class="d-flex justify-space-between align-center">
              <span class="text-caption font-weight-bold text-uppercase">Total Activos Netos</span>
              <span class="text-h6 font-weight-black">{{ formatCurrency(balance.assets.total_neto) }}</span>
            </div>
          </VAlert>
        </div>
      </VCol>
    </VRow>
  </VCard>
</template>
