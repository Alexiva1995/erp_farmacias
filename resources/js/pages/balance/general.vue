<script setup>
import { useBalance } from "@/composables/useBalance";
import { hexToRgb } from "@layouts/utils";
import { useDisplay, useTheme } from "vuetify";
import VueApexCharts from "vue3-apexcharts";
import BalanceAssetsCard from "@/components/balance/BalanceAssetsCard.vue";
import BalanceLiabilitiesCard from "@/components/balance/BalanceLiabilitiesCard.vue";
import BalanceEquityCard from "@/components/balance/BalanceEquityCard.vue";
import BalanceRatioCards from "@/components/balance/BalanceRatioCards.vue";

const vuetifyTheme = useTheme();
const display = useDisplay();
const { balance, loading, fetchBalance, formatCurrency } = useBalance();

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
            size: "72%",
            labels: {
              show: true,
              name: { fontSize: "0.75rem", show: true, offsetY: -3 },
              value: {
                fontSize: "0.95rem",
                color: currentTheme.primary,
                fontWeight: 700,
                offsetY: 3,
                formatter: (val) => formatCurrency(val),
              },
              total: {
                show: true,
                fontSize: "0.7rem",
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
        bar: {
          horizontal: true,
          borderRadius: 6,
          barHeight: "55%",
          distributed: true,
        },
      },
      dataLabels: {
        enabled: true,
        formatter: (val) => formatCurrency(val),
        style: {
          fontSize: "11px",
          fontWeight: 700,
          colors: ["#fff"],
        },
        offsetX: 10,
        dropShadow: {
          enabled: true,
          top: 1,
          left: 1,
          blur: 1,
          opacity: 0.45,
        },
      },
      legend: { show: false },
      xaxis: {
        categories: ["Activos Netos", "Total Pasivos", "Patrimonio Neto"],
        labels: {
          style: { colors: labelColor, fontWeight: 600 },
          formatter: (val) => formatCurrency(val),
        },
      },
      yaxis: {
        labels: {
          style: { fontWeight: 700 },
        },
      },
      tooltip: {
        y: {
          formatter: (val) => formatCurrency(val),
        },
      },
      colors: [
        currentTheme.success, // Activos -> Verde
        currentTheme.error,   // Pasivos -> Rojo / Naranja
        balance.equity >= 0 ? currentTheme.primary : "#9333EA", // Patrimonio -> Primario o Púrpura
      ],
    },
  };
});

const donutHeight = computed(() => (display.xs.value ? 190 : 230));

const donutSeries = computed(() => [
  Number(balance.assets.details.cash || 0),
  Number(balance.assets.details.inventory || 0),
  Number(balance.assets.details.furniture_bruto || 0),
]);

const barSeries = computed(() => [
  {
    name: "Monto Consolidado",
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
            Estado de Situación Financiera y Comprobación Patrimonial al día de hoy
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

    <!-- LOADING STATE -->
    <div v-if="loading" class="pa-12 text-center rounded-xl border bg-white my-4 shadow-sm">
      <VProgressCircular indeterminate color="primary" size="42" class="mb-3" />
      <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando Balance General...</div>
    </div>

    <div v-else class="d-flex flex-column gap-1 mt-1">
      <!-- CARDS DE RATIOS -->
      <BalanceRatioCards :balance="balance" :format-currency="formatCurrency" />

      <!-- BLOQUES CONTABLES (ACTIVOS, PASIVOS Y PATRIMONIO) -->
      <VRow class="ma-0 mx-n1" dense>
        <!-- COLUMNA DE ACTIVOS (Más ancha para el gráfico dona) -->
        <VCol cols="12" lg="6" md="12" class="pa-1 d-flex">
          <BalanceAssetsCard
            :balance="balance"
            :chart-options="chartOptions"
            :donut-series="donutSeries"
            :donut-height="donutHeight"
            :is-mounted="isMounted"
            :format-currency="formatCurrency"
          />
        </VCol>

        <!-- COLUMNA DE PASIVOS -->
        <VCol cols="12" lg="3" md="6" class="pa-1 d-flex">
          <BalanceLiabilitiesCard :balance="balance" :format-currency="formatCurrency" />
        </VCol>

        <!-- COLUMNA DE PATRIMONIO -->
        <VCol cols="12" lg="3" md="6" class="pa-1 d-flex">
          <BalanceEquityCard :balance="balance" :format-currency="formatCurrency" />
        </VCol>

        <!-- GRÁFICO COMPARATIVO DE ESTRUCTURA FINANCIERA -->
        <VCol cols="12" class="pa-1 mt-4">
          <VCard class="rounded-lg border shadow-sm">
            <VCardItem class="pb-0">
              <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-black">
                <VIcon icon="tabler-chart-bar" color="primary" class="me-2" />
                Comparativo de Estructura Financiera (Ecuación Patrimonial)
              </VCardTitle>
              <p class="text-caption text-disabled mb-0 font-weight-medium">
                Contraste gráfico de Activos Netos, Total Pasivos y Patrimonio Neto
              </p>
            </VCardItem>
            <VCardText class="pt-2">
              <VueApexCharts
                v-if="isMounted"
                type="bar"
                height="200"
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

.text-super-xs {
  font-size: 0.65rem !important;
}
</style>
