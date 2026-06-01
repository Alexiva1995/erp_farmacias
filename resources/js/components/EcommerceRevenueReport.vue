<script setup>
import axios from "@/plugins/axios";
import { hexToRgb } from "@layouts/utils";
import { computed, onMounted, ref } from "vue";
import { useTheme } from "vuetify";

const vuetifyTheme = useTheme();
const loading = ref(false);
const selectedYear = ref(new Date().getFullYear());
const revenueData = ref({
  monthly_data: [],
  summary: {
    total_income: 0,
    total_expenses: 0,
    net_revenue: 0,
    year: new Date().getFullYear(),
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
        data: monthlyData.map((item) => Math.round(-item.expenses || 0)),
      },
    ],
    line: [
      {
        name: "Ganancia Neta",
        data: monthlyData.map((item) => Math.round(item.net || 0)),
      },
    ],
  };
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const monthTranslations = {
  "January": "Enero",
  "February": "Febrero",
  "March": "Marzo",
  "April": "Abril",
  "May": "Mayo",
  "June": "Junio",
  "July": "Julio",
  "August": "Agosto",
  "September": "Septiembre",
  "October": "Octubre",
  "November": "Noviembre",
  "December": "Diciembre"
};

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

  const categories = (revenueData.value?.monthly_data || []).map(
    (item) => monthTranslations[item.month_name] || item.month_name || "",
  );

  return {
    bar: {
      chart: {
        parentHeightOffset: 0,
        stacked: true,
        type: "bar",
        toolbar: { show: false },
      },
      tooltip: {
        enabled: true,
        theme: "light",
        x: { show: true },
        y: {
          formatter: (value) => formatCurrency(Math.abs(value)),
        },
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: "40%",
          borderRadius: 8,
          borderRadiusApplication: "around",
          borderRadiusWhenStacked: "all",
        },
      },
      colors: [
        "#E20074",
        "rgba(var(--v-theme-warning),1)",
      ],
      dataLabels: { enabled: false },
      stroke: {
        curve: "smooth",
        width: 6,
        lineCap: "round",
        colors: [currentTheme.surface],
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
        itemMargin: { horizontal: 5 },
      },
      grid: {
        show: false,
        padding: {
          bottom: -8,
          top: 20,
        },
      },
      xaxis: {
        categories: categories,
        labels: {
          style: {
            fontSize: "13px",
            colors: labelColor,
            fontFamily: "Public Sans",
          },
        },
        axisTicks: { show: false },
        axisBorder: { show: false },
      },
      yaxis: {
        labels: {
          offsetX: -16,
          style: {
            fontSize: "13px",
            colors: labelColor,
            fontFamily: "Public Sans",
          },
          formatter: (value) => formatCurrency(value),
        },
        tickAmount: 5,
      },
      responsive: [
        {
          breakpoint: 1700,
          options: { plotOptions: { bar: { columnWidth: "43%" } } },
        },
        {
          breakpoint: 1280,
          options: {
            plotOptions: {
              bar: {
                columnWidth: "40%",
                borderRadius: 10,
              },
            },
          },
        },
      ],
      states: {
        hover: { filter: { type: "none" } },
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
          top: -30,
          bottom: -15,
          left: 25,
        },
      },
      markers: { size: 0 },
      xaxis: {
        categories: categories,
        labels: { show: false },
        axisTicks: { show: false },
        axisBorder: { show: false },
      },
      yaxis: { show: false },
      tooltip: {
        enabled: true,
        x: { show: true },
        y: {
          formatter: (value) => formatCurrency(value),
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

const handleYearChange = () => {
  fetchRevenueReport();
};

const isMounted = ref(false);

onMounted(() => {
  isMounted.value = true;
  fetchRevenueReport();
});
</script>

<template>
  <VCard class="revenue-report" :loading="loading">
    <VRow no-gutters>
      <VCol
        cols="12"
        sm="8"
        lg="8"
        :class="$vuetify.display.smAndUp ? 'border-e' : 'border-b'"
      >
        <VCardText>
          <h6 class="text-h5 mb-sm-n8">Reporte de Ingresos</h6>

          <VueApexCharts
            v-if="isMounted && !loading && series.bar && series.bar.length > 0"
            :key="`bar-${selectedYear}-${series.bar[0].data.length}`"
            :options="chartOptions.bar"
            :series="series.bar"
            height="365"
          />
        </VCardText>
      </VCol>

      <VCol cols="12" sm="4">
        <VCardText
          class="d-flex flex-column justify-center align-center text-center h-100"
        >
          <VBtn variant="tonal" size="small" class="d-flex mx-auto">
            <span>{{ selectedYear }}</span>
            <template #append>
              <VIcon size="16" icon="tabler-chevron-down" />
            </template>
            <VMenu activator="parent">
              <VList>
                <VListItem
                  v-for="year in availableYears"
                  :key="year"
                  :value="year"
                  @click="
                    selectedYear = year;
                    handleYearChange();
                  "
                >
                  <VListItemTitle>{{ year }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>

          <div class="d-flex flex-column my-8">
            <h5 class="font-weight-medium text-h3">
              {{ formatCurrency(revenueData?.summary?.net_revenue || 0) }}
            </h5>
            <p class="mb-0">
              <span class="text-high-emphasis font-weight-medium me-1"
                >Ingresos:</span
              >
              <span>{{
                formatCurrency(revenueData?.summary?.total_income || 0)
              }}</span>
            </p>
            <p class="mb-0">
              <span class="text-high-emphasis font-weight-medium me-1"
                >Gastos:</span
              >
              <span>{{
                formatCurrency(revenueData?.summary?.total_expenses || 0)
              }}</span>
            </p>
          </div>

          <VueApexCharts
            v-if="isMounted && !loading && series.line && series.line.length > 0"
            :key="`line-${selectedYear}-${series.line[0].data.length}`"
            :options="chartOptions.line"
            :series="series.line"
            height="100"
          />

          <VBtn
            class="mt-8"
            color="primary"
            variant="tonal"
            @click="handleYearChange"
          >
            Actualizar Datos
          </VBtn>
        </VCardText>
      </VCol>
    </VRow>
  </VCard>
</template>

<style lang="scss">
.revenue-report {
  .apexcharts-legend {
    gap: 1rem;
  }

  @media (max-width: 599px) {
    .apexcharts-legend.apx-legend-position-top.apexcharts-align-right {
      justify-content: flex-start;
      padding: 0;
    }
  }
}
</style>
