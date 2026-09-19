<script setup>
import axios from "@/plugins/axios";
import { hexToRgb } from "@layouts/utils";
import { computed, onMounted, ref, watch } from "vue";
import { useTheme } from "vuetify";

const props = defineProps({
  year: {
    type: Number,
    required: true,
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
});

const emit = defineEmits(["update:year"]);

const vuetifyTheme = useTheme();
const loading = ref(false);
const selectedYear = ref(props.year);

watch(
  () => props.year,
  (newVal) => {
    if (newVal !== selectedYear.value) {
      selectedYear.value = newVal;
      fetchRevenueReport();
    }
  }
);

const revenueData = ref({
  monthly_data: [],
  summary: {
    total_income: 0,
    total_expenses: 0,
    net_revenue: 0,
    year: props.year,
  },
});

const availableYears = computed(() => {
  const currentYear = new Date().getFullYear();
  const years = [];
  for (let i = 0; i < 5; i++) {
    years.push(currentYear - i);
  }
  return years;
});

const series = computed(() => {
  const monthlyData = revenueData.value?.monthly_data || [];

  return {
    bar: [
      {
        name: "Ingresos",
        data: monthlyData.map((item) => Math.round(item.income || 0)),
      },
      {
        name: "Gastos",
        data: monthlyData.map((item) => Math.round(Math.abs(item.expenses || 0))),
      },
    ],
    line: [
      {
        name: "Resultado Neto",
        data: monthlyData.map((item) => Math.round(item.net || 0)),
      },
    ],
  };
});

const monthAbbr = [
  "Ene", "Feb", "Mar", "Abr", "May", "Jun", 
  "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
];

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors;
  const variableTheme = vuetifyTheme.current.value.variables;
  const labelColor = `rgba(${hexToRgb(currentTheme["on-surface"])},${
    variableTheme["disabled-opacity"]
  })`;
  const legendColor = `rgba(${hexToRgb(currentTheme["on-background"])},${
    variableTheme["high-emphasis-opacity"]
  })`;
  const borderColor = `rgba(${hexToRgb(
    String(variableTheme["border-color"]),
  )},${variableTheme["border-opacity"]})`;

  return {
    bar: {
      chart: {
        parentHeightOffset: 0,
        stacked: false,
        type: "bar",
        toolbar: { show: false },
      },
      tooltip: {
        enabled: true,
        theme: "light",
        x: { show: true },
        y: {
          formatter: (value) => props.formatCurrency(value),
        },
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: "45%",
          borderRadius: 4,
          borderRadiusApplication: "end",
        },
      },
      colors: [
        "rgba(var(--v-theme-primary), 1)",
        "rgba(var(--v-theme-warning), 0.9)",
      ],
      dataLabels: { enabled: false },
      stroke: {
        show: true,
        width: 2,
        colors: ["transparent"],
      },
      legend: {
        show: true,
        horizontalAlign: "right",
        position: "top",
        fontFamily: "Public Sans",
        fontSize: "13px",
        markers: {
          height: 12,
          width: 12,
          radius: 12,
          offsetX: -3,
          offsetY: 2,
        },
        labels: { colors: legendColor },
        itemMargin: { horizontal: 8 },
      },
      grid: {
        show: true,
        borderColor,
        strokeDashArray: 3,
        padding: {
          bottom: 0,
          top: 10,
          left: 10,
          right: 10,
        },
      },
      xaxis: {
        categories: monthAbbr,
        labels: {
          style: {
            fontSize: "12px",
            colors: labelColor,
            fontFamily: "Public Sans",
            fontWeight: 500,
          },
        },
        axisTicks: { show: false },
        axisBorder: { show: false },
      },
      yaxis: {
        labels: {
          offsetX: -8,
          style: {
            fontSize: "12px",
            colors: labelColor,
            fontFamily: "Public Sans",
          },
          formatter: (value) => {
            if (value >= 1000000) return `Bs. ${(value / 1000000).toFixed(1)}M`;
            if (value >= 1000) return `Bs. ${(value / 1000).toFixed(0)}k`;
            return `Bs. ${value}`;
          },
        },
        tickAmount: 5,
      },
      states: {
        hover: { filter: { type: "darken", value: 0.9 } },
        active: { filter: { type: "none" } },
      },
    },
    line: {
      chart: {
        toolbar: { show: false },
        zoom: { enabled: false },
        type: "line",
      },
      stroke: {
        curve: "smooth",
        dashArray: [0],
        width: [3],
      },
      legend: { show: false },
      colors: [currentTheme.primary],
      grid: {
        show: false,
        borderColor,
        padding: {
          top: -20,
          bottom: -10,
          left: 10,
        },
      },
      markers: { size: 0 },
      xaxis: {
        categories: monthAbbr,
        labels: { show: false },
        axisTicks: { show: false },
        axisBorder: { show: false },
      },
      yaxis: { show: false },
      tooltip: {
        enabled: true,
        x: { show: true },
        y: {
          formatter: (value) => props.formatCurrency(value),
        },
      },
    },
  };
});

const fetchRevenueReport = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/dashboard/revenue-report", {
      params: { year: selectedYear.value },
    });
    revenueData.value = data.data || {
      monthly_data: [],
      summary: {
        total_income: 0,
        total_expenses: 0,
        net_revenue: 0,
        year: selectedYear.value,
      },
    };
  } catch (error) {
    console.error("Error al cargar revenue report:", error);
  } finally {
    loading.value = false;
  }
};

const handleYearSelect = (year) => {
  selectedYear.value = year;
  emit("update:year", year);
  fetchRevenueReport();
};

const isMounted = ref(false);

onMounted(() => {
  isMounted.value = true;
  fetchRevenueReport();
});
</script>

<template>
  <VCard class="fiscal-revenue-card border shadow-sm" :loading="loading">
    <VRow no-gutters>
      <!-- Gráfica de Barras Comparativa (Ingresos vs Gastos en Bs.) -->
      <VCol
        cols="12"
        md="8"
        :class="$vuetify.display.mdAndUp ? 'border-e' : 'border-b'"
        class="pa-4 pa-sm-5"
      >
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
              Comparativa Fiscal Anual (Ingresos vs. Gastos)
            </h6>
            <span class="text-caption text-medium-emphasis">
              Expresado en Bolívares (Bs.) — Ejercicio Fiscal {{ selectedYear }}
            </span>
          </div>
          <VChip size="small" color="primary" variant="tonal" class="font-weight-semibold">
            Moneda: VES (Bs.)
          </VChip>
        </div>

        <VueApexCharts
          v-if="isMounted && !loading && series.bar && series.bar.length > 0"
          :key="`fiscal-bar-${selectedYear}-${series.bar[0].data.length}`"
          :options="chartOptions.bar"
          :series="series.bar"
          height="320"
        />
      </VCol>

      <!-- Panel Lateral de Resumen Financiero Fiscal -->
      <VCol cols="12" md="4" class="pa-4 pa-sm-5 d-flex flex-column justify-space-between bg-surface">
        <div>
          <div class="d-flex justify-space-between align-center mb-4">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              Año Fiscal
            </span>
            <VBtn variant="outlined" size="small" class="font-weight-bold">
              <span>{{ selectedYear }}</span>
              <template #append>
                <VIcon size="16" icon="tabler-chevron-down" />
              </template>
              <VMenu activator="parent">
                <VList density="compact">
                  <VListItem
                    v-for="yearOption in availableYears"
                    :key="yearOption"
                    :value="yearOption"
                    :active="yearOption === selectedYear"
                    @click="handleYearSelect(yearOption)"
                  >
                    <VListItemTitle class="font-weight-medium">{{ yearOption }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </VBtn>
          </div>

          <!-- Utilidad Neta / Resultado Fiscal -->
          <div class="bg-grey-50 rounded-lg pa-4 border mb-4 text-center">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1 d-block">
              Resultado Neto del Ejercicio
            </span>
            <h4
              class="text-h4 font-weight-black mb-1"
              :class="revenueData?.summary?.net_revenue >= 0 ? 'text-primary' : 'text-error'"
            >
              {{ formatCurrency(revenueData?.summary?.net_revenue || 0) }}
            </h4>
            <span class="text-super-xs text-disabled">
              Ingresos Totales (-) Gastos Totales
            </span>
          </div>

          <!-- Métricas Pareadas -->
          <div class="d-flex flex-column gap-3 mb-4">
            <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
              <div class="d-flex align-center">
                <VAvatar size="28" color="primary" variant="tonal" class="me-2">
                  <VIcon icon="tabler-trending-up" size="16" />
                </VAvatar>
                <span class="text-caption font-weight-medium">Ingresos Totales:</span>
              </div>
              <span class="text-caption font-weight-bold text-success">
                {{ formatCurrency(revenueData?.summary?.total_income || 0) }}
              </span>
            </div>

            <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
              <div class="d-flex align-center">
                <VAvatar size="28" color="warning" variant="tonal" class="me-2">
                  <VIcon icon="tabler-receipt" size="16" />
                </VAvatar>
                <span class="text-caption font-weight-medium">Gastos Totales:</span>
              </div>
              <span class="text-caption font-weight-bold text-warning">
                {{ formatCurrency(revenueData?.summary?.total_expenses || 0) }}
              </span>
            </div>
          </div>

          <!-- Mini Sparkline de tendencia -->
          <div class="mb-2">
            <VueApexCharts
              v-if="isMounted && !loading && series.line && series.line.length > 0"
              :key="`fiscal-line-${selectedYear}-${series.line[0].data.length}`"
              :options="chartOptions.line"
              :series="series.line"
              height="80"
            />
          </div>
        </div>

        <VBtn
          color="primary"
          variant="tonal"
          block
          size="small"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="fetchRevenueReport"
        >
          Sincronizar Balance Fiscal
        </VBtn>
      </VCol>
    </VRow>
  </VCard>
</template>

<style scoped>
.fiscal-revenue-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
