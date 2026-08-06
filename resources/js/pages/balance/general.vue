<script setup>
import { useBalance } from "@/composables/useBalance";
import { hexToRgb } from "@layouts/utils";
import { useDisplay, useTheme } from "vuetify";
import VueApexCharts from "vue3-apexcharts";
import BalanceAssetsCard from "@/components/balance/BalanceAssetsCard.vue";
import BalanceLiabilitiesCard from "@/components/balance/BalanceLiabilitiesCard.vue";
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

const donutHeight = computed(() => (display.xs.value ? 200 : 250));

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

    <!-- LOADING STATE -->
    <div v-if="loading" class="pa-12 text-center rounded-xl border bg-white my-4 shadow-sm">
      <VProgressCircular indeterminate color="primary" size="42" class="mb-3" />
      <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando Balance General...</div>
    </div>

    <div v-else class="d-flex flex-column gap-1 mt-1">
      <!-- CARDS DE RATIOS -->
      <BalanceRatioCards :balance="balance" :format-currency="formatCurrency" />

      <VRow class="ma-0 mx-n1" dense>
        <!-- COLUMNA DE ACTIVOS -->
        <VCol cols="12" lg="6" md="6" class="pa-1 d-flex">
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
        <VCol cols="12" lg="6" md="6" class="pa-1 d-flex">
          <BalanceLiabilitiesCard :balance="balance" :format-currency="formatCurrency" />
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

.text-super-xs {
  font-size: 0.65rem !important;
}
</style>
