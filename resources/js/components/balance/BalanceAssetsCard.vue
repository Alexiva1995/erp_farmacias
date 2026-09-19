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
    <VRow no-gutters class="flex-grow-1 flex-column flex-sm-row">
      <VCol cols="12" sm="5" class="pa-2 d-flex align-center justify-center">
        <VueApexCharts
          v-if="isMounted"
          type="donut"
          width="100%"
          :height="donutHeight"
          :options="chartOptions.donut"
          :series="donutSeries"
        />
      </VCol>
      <VCol cols="12" sm="7" class="d-flex flex-column">
        <VList density="compact" class="pa-2 pa-sm-3 flex-grow-1">
          <!-- Activo Corriente -->
          <div class="text-super-xs font-weight-black text-success text-uppercase px-2 mb-1">
            Activo Corriente
          </div>

          <VListItem class="rounded-lg px-2">
            <template #prepend>
              <VAvatar size="26" color="success" variant="tonal" class="me-2">
                <VIcon icon="tabler-cash" size="15" />
              </VAvatar>
            </template>
            <VListItemTitle class="text-caption font-weight-medium text-wrap leading-tight">Efectivo y Equivalentes</VListItemTitle>
            <template #append>
              <span class="font-weight-bold" :class="balance.assets.details.cash >= 0 ? 'text-high-emphasis' : 'text-error'">
                {{ formatCurrency(balance.assets.details.cash) }}
              </span>
            </template>
          </VListItem>

          <VListItem class="rounded-lg px-2">
            <template #prepend>
              <VAvatar size="26" color="info" variant="tonal" class="me-2">
                <VIcon icon="tabler-package" size="15" />
              </VAvatar>
            </template>
            <VListItemTitle class="text-caption font-weight-medium text-wrap leading-tight">Inventario de Mercancías</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">{{ formatCurrency(balance.assets.details.inventory) }}</span>
            </template>
          </VListItem>

          <VDivider class="my-2" />

          <!-- Activo No Corriente -->
          <div class="text-super-xs font-weight-black text-warning text-uppercase px-2 mb-1">
            Activo No Corriente (Propiedad, Planta y Equipo)
          </div>

          <VListItem class="rounded-lg px-2">
            <template #prepend>
              <VAvatar size="26" color="warning" variant="tonal" class="me-2">
                <VIcon icon="tabler-sofa" size="15" />
              </VAvatar>
            </template>
            <VListItemTitle class="text-caption font-weight-medium text-wrap leading-tight">Mobiliario y Equipos (Bruto)</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">{{ formatCurrency(balance.assets.details.furniture_bruto) }}</span>
            </template>
          </VListItem>

          <VListItem class="rounded-lg px-2 text-error">
            <template #prepend>
              <VAvatar size="26" color="error" variant="tonal" class="me-2">
                <VIcon icon="tabler-trending-down" size="15" />
              </VAvatar>
            </template>
            <VListItemTitle class="text-caption font-weight-medium text-wrap leading-tight">(-) Depreciación Acumulada</VListItemTitle>
            <template #append>
              <span class="font-weight-bold">- {{ formatCurrency(balance.assets.depreciation) }}</span>
            </template>
          </VListItem>
        </VList>
        <div class="px-2 px-sm-3 pb-3 pt-1 mt-auto">
          <VAlert color="success" variant="tonal" class="rounded-lg border-opacity-25 mb-0" density="compact">
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
