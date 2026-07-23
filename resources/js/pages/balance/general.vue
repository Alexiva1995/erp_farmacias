<script setup>
import { useBalance } from "@/composables/useBalance";
import { hexToRgb } from "@layouts/utils";
import { useDisplay, useTheme } from "vuetify";

const vuetifyTheme = useTheme();
const display = useDisplay();
const { balance, loading, fetchBalance, formatCurrency } = useBalance();
import VueApexCharts from "vue3-apexcharts";

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors;
  const variableTheme = vuetifyTheme.current.value.variables;
  const labelColor = `rgba(${hexToRgb(currentTheme["on-surface"])},${variableTheme["disabled-opacity"]})`;

  return {
    donut: {
      labels: ["Efectivo", "Inventario", "Mobiliario"],
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
              name: { fontSize: "0.9rem", show: true, offsetY: -5 },
              value: {
                fontSize: "1.1rem",
                color: currentTheme.primary,
                fontWeight: 700,
                offsetY: 5,
                formatter: (val) => formatCurrency(val),
              },
              total: {
                show: true,
                fontSize: "0.8rem",
                label: "Total Activos",
                fontWeight: 600,
                formatter: () => formatCurrency(balance.assets.total_bruto),
              },
            },
          },
        },
      },
    },
    bar: {
      chart: { type: "bar", toolbar: { show: false } },
      plotOptions: {
        bar: { horizontal: true, borderRadius: 4, barHeight: "60%" },
      },
      dataLabels: { enabled: false },
      xaxis: {
        categories: ["Activos Netos", "Pasivos", "Patrimonio"],
        labels: { style: { colors: labelColor } },
      },
      colors: [currentTheme.success, currentTheme.error, currentTheme.primary],
    },
  };
});

const donutHeight = computed(() => {
  return display.xs.value ? 200 : 250;
});

const donutSeries = computed(() => [
  Number(balance.assets.details.cash || 0),
  Number(balance.assets.details.inventory || 0),
  Number(balance.assets.details.furniture_bruto || 0),
]);

const barSeries = computed(() => [
  {
    name: "Monto",
    data: [
      Number(balance.assets.total_neto || 0),
      Number(balance.liabilities.total || 0),
      Number(balance.equity || 0),
    ],
  },
]);

const isMounted = ref(false);
const errorOccurred = ref(false);
const errorMessage = ref("");

const loadData = async () => {
  errorOccurred.value = false;
  try {
    await fetchBalance();
  } catch (err) {
    errorOccurred.value = true;
    errorMessage.value = err?.message || "No se pudo recuperar la información del balance general.";
  }
};

onMounted(() => {
  isMounted.value = true;
  loadData();
});
</script>

<template>
  <div class="balance-premium pb-12">
    <!-- CABECERA PREMIUM -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6 mt-2 px-1">
      <div class="d-flex align-center gap-3">
        <VAvatar color="primary" variant="tonal" size="48" class="rounded-xl">
          <VIcon icon="tabler-chart-pie" size="26" />
        </VAvatar>
        <div>
          <h1 class="text-h4 font-weight-black text-high-emphasis mb-1">
            Balance General
          </h1>
          <p class="text-caption text-disabled mb-0 font-weight-medium">
            Estado de situación financiera acumulado al día de hoy
          </p>
        </div>
      </div>
      <div class="d-flex align-center gap-3">
        <div v-if="balance.calculated_at" class="text-end d-none d-sm-block">
          <p class="text-super-xs text-disabled font-weight-bold uppercase mb-0">Último Cálculo</p>
          <p class="text-caption font-weight-black mb-0">{{ new Date(balance.calculated_at).toLocaleString('es-ES') }}</p>
        </div>
        <VBtn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="loadData"
          class="rounded-lg font-weight-bold"
        >
          Recargar
        </VBtn>
      </div>
    </div>

    <!-- ERROR STATE -->
    <VAlert
      v-if="errorOccurred"
      type="error"
      variant="tonal"
      closable
      class="mb-6 rounded-lg border-opacity-25"
      @click:close="errorOccurred = false"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- LOADING / SKELETON STATE -->
    <div v-if="loading" class="d-flex flex-column gap-5">
      <VRow class="ma-0 mx-n1" dense>
        <VCol cols="12" sm="4" class="pa-1" v-for="i in 3" :key="i">
          <VCard class="rounded-xl shadow-premium pa-4">
            <VSkeletonLoader type="list-item-avatar, heading" />
          </VCard>
        </VCol>
      </VRow>
      <VRow class="ma-0 mx-n1" dense>
        <VCol cols="12" md="6" class="pa-1">
          <VCard class="rounded-xl shadow-premium pa-4 h-100">
            <VSkeletonLoader type="card-avatar, article" />
          </VCard>
        </VCol>
        <VCol cols="12" md="6" class="pa-1">
          <VCard class="rounded-xl shadow-premium pa-4 h-100">
            <VSkeletonLoader type="article, actions" />
          </VCard>
        </VCol>
      </VRow>
    </div>

    <div v-else class="d-flex flex-column gap-1 mt-1">
      <!-- CARDS DE RATIOS -->
      <VRow class="ma-0 mx-n1 mb-5" dense>
        <VCol cols="12" sm="4" class="pa-1">
          <VHover v-slot="{ isHovering, props }">
            <VCard
              v-bind="props"
              :elevation="isHovering ? 6 : 2"
              class="h-100 rounded-xl border-t-0 bg-surface transition-swing shadow-premium"
            >
              <VCardText class="pa-4 d-flex flex-column h-100">
                <div class="d-flex align-center gap-3 mb-3">
                  <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
                    <VIcon icon="tabler-scale" size="20" />
                  </VAvatar>
                  <span class="text-overline font-weight-black text-disabled" style="line-height: 1; letter-spacing: 0.1em;">
                    Ratio de Liquidez
                  </span>
                </div>
                <div class="mt-auto d-flex align-center justify-space-between">
                  <span class="text-h4 font-weight-black text-primary leading-none">
                    {{ balance.ratios.liquidity }}
                  </span>
                  <VChip size="small" variant="flat" :color="balance.ratios.liquidity >= 1.5 ? 'success' : 'warning'" class="font-weight-black px-3 rounded-lg">
                    {{ balance.ratios.liquidity >= 1.5 ? 'ÓPTIMO' : 'VIGILAR' }}
                  </VChip>
                </div>
              </VCardText>
            </VCard>
          </VHover>
        </VCol>
        
        <VCol cols="12" sm="4" class="pa-1">
          <VHover v-slot="{ isHovering, props }">
            <VCard
              v-bind="props"
              :elevation="isHovering ? 6 : 2"
              class="h-100 rounded-xl border-t-0 bg-surface transition-swing shadow-premium"
            >
              <VCardText class="pa-4 d-flex flex-column h-100">
                <div class="d-flex align-center gap-3 mb-3">
                  <VAvatar color="info" variant="tonal" size="38" class="rounded-lg">
                    <VIcon icon="tabler-shield-check" size="20" />
                  </VAvatar>
                  <span class="text-overline font-weight-black text-disabled" style="line-height: 1; letter-spacing: 0.1em;">
                    Solvencia
                  </span>
                </div>
                <div class="mt-auto d-flex align-center justify-space-between">
                  <span class="text-h4 font-weight-black text-info leading-none">
                    {{ balance.ratios.solvency }}
                  </span>
                  <VChip size="small" variant="flat" color="info" class="font-weight-black px-3 rounded-lg">NIVEL SEGURO</VChip>
                </div>
              </VCardText>
            </VCard>
          </VHover>
        </VCol>
        
        <VCol cols="12" sm="4" class="pa-1">
          <VHover v-slot="{ isHovering, props }">
            <VCard
              v-bind="props"
              :elevation="isHovering ? 6 : 2"
              class="h-100 rounded-xl border-t-0 bg-surface transition-swing shadow-premium"
            >
              <VCardText class="pa-4 d-flex flex-column h-100">
                <div class="d-flex align-center gap-3 mb-3">
                  <VAvatar :color="balance.equity >= 0 ? 'primary' : 'error'" variant="tonal" size="38" class="rounded-lg">
                    <VIcon icon="tabler-building-bank" size="20" />
                  </VAvatar>
                  <span class="text-overline font-weight-black text-disabled" style="line-height: 1; letter-spacing: 0.1em;">
                    Patrimonio Neto
                  </span>
                </div>
                <div class="mt-auto">
                  <span class="text-h4 font-weight-black leading-none" :class="balance.equity >= 0 ? 'text-primary' : 'text-error'">
                    {{ formatCurrency(balance.equity) }}
                  </span>
                </div>
              </VCardText>
            </VCard>
          </VHover>
        </VCol>
      </VRow>

      <VRow class="ma-0 mx-n1" dense>
        <!-- COLUMNA DE ACTIVOS -->
        <VCol cols="12" lg="6" md="6" class="pa-1 d-flex">
          <VCard
            class="rounded-lg border shadow-sm w-100 h-100 d-flex flex-column"
          >
            <VCardItem>
              <VCardTitle class="d-flex align-center">
                <VIcon icon="tabler-trending-up" color="success" class="me-2" />
                Estructura de Activos
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VRow no-gutters class="flex-grow-1">
              <VCol
                cols="12"
                sm="5"
                class="pa-2 pa-sm-4 d-flex align-center justify-center"
              >
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
                      <VAvatar
                        size="32"
                        color="success"
                        variant="tonal"
                        class="me-3"
                      >
                        <VIcon icon="tabler-cash" size="18" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-medium"
                      >Efectivo en Caja</VListItemTitle
                    >
                    <template #append>
                      <span class="font-weight-bold">{{
                        formatCurrency(balance.assets.details.cash)
                      }}</span>
                    </template>
                  </VListItem>
                  <VListItem>
                    <template #prepend>
                      <VAvatar
                        size="32"
                        color="info"
                        variant="tonal"
                        class="me-3"
                      >
                        <VIcon icon="tabler-package" size="18" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-medium"
                      >Inventario</VListItemTitle
                    >
                    <template #append>
                      <span class="font-weight-bold">{{
                        formatCurrency(balance.assets.details.inventory)
                      }}</span>
                    </template>
                  </VListItem>
                  <VListItem>
                    <template #prepend>
                      <VAvatar
                        size="32"
                        color="warning"
                        variant="tonal"
                        class="me-3"
                      >
                        <VIcon icon="tabler-sofa" size="18" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-medium"
                      >Mobiliario Bruto</VListItemTitle
                    >
                    <template #append>
                      <span class="font-weight-bold">{{
                        formatCurrency(balance.assets.details.furniture_bruto)
                      }}</span>
                    </template>
                  </VListItem>

                  <VDivider class="my-2" />

                  <VListItem class="text-error">
                    <template #prepend>
                      <VIcon icon="tabler-trending-down" class="me-3" />
                    </template>
                    <VListItemTitle>Depreciación Acumulada</VListItemTitle>
                    <template #append>
                      <span class="font-weight-bold"
                        >-
                        {{ formatCurrency(balance.assets.depreciation) }}</span
                      >
                    </template>
                  </VListItem>
                </VList>
                <div class="px-3 px-sm-6 pb-6 pt-2 mt-auto">
                  <VAlert
                    color="success"
                    variant="tonal"
                    class="rounded-lg border-opacity-25"
                    density="compact"
                  >
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-caption font-weight-bold text-uppercase"
                        >Total Activos Netos</span
                      >
                      <span class="text-h6 font-weight-black">{{
                        formatCurrency(balance.assets.total_neto)
                      }}</span>
                    </div>
                  </VAlert>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </VCol>

        <!-- COLUMNA DE PASIVOS -->
        <VCol cols="12" lg="6" md="6" class="pa-1 d-flex">
          <VCard
            class="rounded-lg border shadow-sm w-100 h-100 d-flex flex-column"
          >
            <VCardItem>
              <VCardTitle class="d-flex align-center">
                <VIcon icon="tabler-trending-down" color="error" class="me-2" />
                Pasivos y Obligaciones
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText class="d-flex flex-column flex-grow-1">
              <VList density="comfortable" class="flex-grow-1">
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-users" color="error" class="me-3" />
                  </template>
                  <VListItemTitle
                    >Cuentas por Pagar (Proveedores)</VListItemTitle
                  >
                  <template #append>
                    <span class="font-weight-bold">{{
                      formatCurrency(balance.liabilities.details.supplier_debts)
                    }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <template #prepend>
                    <VIcon
                      icon="tabler-building-bank"
                      color="secondary"
                      class="me-3"
                    />
                  </template>
                  <VListItemTitle>Préstamos Bancarios</VListItemTitle>
                  <template #append>
                    <span class="font-weight-bold">{{
                      formatCurrency(balance.liabilities.details.loans)
                    }}</span>
                  </template>
                </VListItem>
              </VList>
              <div class="mt-auto">
                <VDivider class="my-4" />
                <div
                  class="d-flex justify-space-between px-4 align-center mb-2"
                >
                  <span class="text-h6">Total Pasivos</span>
                  <span class="text-h6 text-error font-weight-black">{{
                    formatCurrency(balance.liabilities.total)
                  }}</span>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- GRÁFICO RESUMEN -->
        <VCol cols="12" class="pa-1 mt-6">
          <VCard class="rounded-lg border shadow-sm">
            <VCardText>
              <VueApexCharts
                v-if="isMounted"
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
  </div>
</template>

<style lang="scss">
.balance-premium {
  background-color: #f8fafc;
  .v-card {
    border-radius: 12px !important;
    &.shadow-premium {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02) !important;
    }
  }
}
.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}

.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}
.text-super-xs {
  font-size: 0.65rem !important;
}
</style>
