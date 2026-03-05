<script setup>
import { useBalance } from "@/composables/useBalance";
import { hexToRgb } from "@layouts/utils";
import { computed, onMounted } from "vue";
import { useTheme } from "vuetify";

const vuetifyTheme = useTheme();
const { balance, loading, fetchBalance, formatCurrency } = useBalance();

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors;
  const variableTheme = vuetifyTheme.current.value.variables;
  const labelColor = `rgba(${hexToRgb(currentTheme["on-surface"])},${variableTheme["disabled-opacity"]})`;

  return {
    donut: {
      labels: {
        show: true,
        name: { fontSize: "1rem" },
        value: {
          fontSize: "1.2rem",
          color: currentTheme.primary,
          formatter: (val) => formatCurrency(val),
        },
        total: {
          show: true,
          label: "Total Activos",
          fontSize: "1rem",
          formatter: () => formatCurrency(balance.assets.total_bruto),
        },
      },
      legend: { show: false },
      dataLabels: { enabled: false },
      stroke: { width: 0 },
      colors: [currentTheme.success, currentTheme.info, currentTheme.warning],
      states: {
        hover: { filter: { type: "none" } },
        active: { filter: { type: "none" } },
      },
      plotOptions: {
        pie: {
          donut: {
            size: "70%",
            labels: {
              show: true,
              total: {
                show: true,
                fontSize: "13px",
                label: "Total Activos",
                formatter: () => formatCurrency(balance.assets.total_bruto),
              },
            },
          },
        },
      },
    },
    bar: {
        chart: { type: 'bar', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
        dataLabels: { enabled: false },
        xaxis: {
            categories: ['Activos Netos', 'Pasivos', 'Patrimonio'],
            labels: { style: { colors: labelColor } }
        },
        colors: [currentTheme.success, currentTheme.error, currentTheme.primary]
    }
  };
});

const donutSeries = computed(() => [
  balance.assets.details.cash,
  balance.assets.details.inventory,
  balance.assets.details.furniture_bruto,
]);

const barSeries = computed(() => [{
    name: 'Monto',
    data: [balance.assets.total_neto, balance.liabilities.total, balance.equity]
}]);

onMounted(() => {
  fetchBalance();
});
</script>

<template>
  <div class="balance-premium">
    <VRow class="mb-6">
        <VCol cols="12">
            <div class="d-flex align-center justify-space-between">
                <div>
                    <h4 class="text-h4 font-weight-bold mb-1">Balance General</h4>
                    <p class="text-body-1 opacity-70">Análisis detallado de la salud financiera de la farmacia</p>
                </div>
                <VBtn 
                    variant="tonal" 
                    prepend-icon="tabler-refresh" 
                    :loading="loading"
                    @click="fetchBalance"
                >
                    Actualizar Datos
                </VBtn>
            </div>
        </VCol>
    </VRow>

    <!-- CARDS DE RATIOS -->
    <VRow class="mb-6">
        <VCol cols="12" md="4">
            <VCard elevation="2" class="h-100">
                <VCardText class="d-flex align-center">
                    <VAvatar color="primary" variant="tonal" rounded size="52" class="me-4 text-h5">
                        <VIcon icon="tabler-scale" />
                    </VAvatar>
                    <div>
                        <p class="mb-0 text-caption opacity-70">Ratio de Liquidez</p>
                        <h5 class="text-h5 font-weight-bold">{{ balance.ratios.liquidity }}</h5>
                        <VChip size="x-small" :color="balance.ratios.liquidity >= 1.5 ? 'success' : 'warning'" class="mt-1">
                            {{ balance.ratios.liquidity >= 1.5 ? 'Óptimo' : 'Vigilar' }}
                        </VChip>
                    </div>
                </VCardText>
            </VCard>
        </VCol>
        <VCol cols="12" md="4">
            <VCard elevation="2" class="h-100">
                <VCardText class="d-flex align-center">
                    <VAvatar color="info" variant="tonal" rounded size="52" class="me-4 text-h5">
                        <VIcon icon="tabler-shield-check" />
                    </VAvatar>
                    <div>
                        <p class="mb-0 text-caption opacity-70">Solvencia</p>
                        <h5 class="text-h5 font-weight-bold">{{ balance.ratios.solvency }}</h5>
                        <VChip size="x-small" color="info" class="mt-1">Nivel seguro</VChip>
                    </div>
                </VCardText>
            </VCard>
        </VCol>
        <VCol cols="12" md="4">
            <VCard elevation="3" :color="balance.equity >= 0 ? 'primary' : 'error'" class="h-100">
                <VCardText class="text-center py-6 text-white">
                    <p class="mb-1 text-uppercase text-caption font-weight-bold">Patrimonio Neto</p>
                    <h3 class="text-h3 font-weight-black">{{ formatCurrency(balance.equity) }}</h3>
                </VCardText>
            </VCard>
        </VCol>
    </VRow>

    <VRow>
      <!-- COLUMNA DE ACTIVOS -->
      <VCol cols="12" lg="7">
        <VCard elevation="2">
            <VCardItem>
                <VCardTitle class="d-flex align-center">
                    <VIcon icon="tabler-trending-up" color="success" class="me-2" />
                    Estructura de Activos
                </VCardTitle>
            </VCardItem>
            <VDivider />
            <VRow no-gutters>
                <VCol cols="12" sm="5" class="pa-4 d-flex align-center justify-center">
                    <VueApexCharts
                        type="donut"
                        height="250"
                        :options="chartOptions.donut"
                        :series="donutSeries"
                    />
                </VCol>
                <VCol cols="12" sm="7">
                    <VList density="comfortable" class="pa-4">
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
                    <div class="px-6 pb-6 pt-2">
                        <VAlert color="success" variant="tonal" rounded density="compact">
                            <div class="d-flex justify-space-between align-center">
                                <span class="text-caption font-weight-bold text-uppercase">Total Activos Netos</span>
                                <span class="text-h6 font-weight-black">{{ formatCurrency(balance.assets.total_neto) }}</span>
                            </div>
                        </VAlert>
                    </div>
                </VCol>
            </VRow>
        </VCard>
      </VCol>

      <!-- COLUMNA DE PASIVOS -->
      <VCol cols="12" lg="5">
        <VCard elevation="2" class="mb-6">
            <VCardItem>
                <VCardTitle class="d-flex align-center">
                    <VIcon icon="tabler-trending-down" color="error" class="me-2" />
                    Pasivos y Obligaciones
                </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
                <VList density="comfortable">
                    <VListItem>
                        <template #prepend>
                            <VIcon icon="tabler-users" color="error" class="me-3" />
                        </template>
                        <VListItemTitle>Cuentas por Pagar (Proveedores)</VListItemTitle>
                        <template #append>
                            <span class="font-weight-bold">{{ formatCurrency(balance.liabilities.details.supplier_debts) }}</span>
                        </template>
                    </VListItem>
                    <VListItem>
                        <template #prepend>
                            <VIcon icon="tabler-building-bank" color="secondary" class="me-3" />
                        </template>
                        <VListItemTitle>Préstamos Bancarios</VListItemTitle>
                        <template #append>
                            <span class="font-weight-bold">{{ formatCurrency(balance.liabilities.details.loans) }}</span>
                        </template>
                    </VListItem>
                </VList>
                <VDivider class="my-4" />
                <div class="d-flex justify-space-between px-4 align-center mb-2">
                    <span class="text-h6">Total Pasivos</span>
                    <span class="text-h6 text-error font-weight-black">{{ formatCurrency(balance.liabilities.total) }}</span>
                </div>
            </VCardText>
        </VCard>

        <!-- GRÁFICO RESUMEN -->
        <VCard elevation="2">
            <VCardText>
                <VueApexCharts
                    type="bar"
                    height="180"
                    :options="chartOptions.bar"
                    :series="barSeries"
                />
            </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss">
.balance-premium {
  .v-card {
    border-radius: 12px;
  }
}
</style>
