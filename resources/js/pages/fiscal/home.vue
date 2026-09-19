<template>
  <div class="fiscal-home-container pb-6">
    <!-- Encabezado con Filtro de Año Fiscal -->
    <VCard class="mb-6 border shadow-sm rounded-lg">
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 pa-4 pa-sm-5">
        <div>
          <div class="d-flex align-center gap-2 mb-1">
            <VAvatar color="primary" variant="tonal" size="32">
              <VIcon icon="tabler-building-bank" size="18" />
            </VAvatar>
            <h4 class="text-h4 font-weight-bold mb-0">
              Panel de Control Fiscal y Tributario (Venezuela)
            </h4>
          </div>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Declaración de ISLR, retenciones de IVA, conciliación contable y balances expresados en Bolívares (Bs.).
          </p>
        </div>
        <div class="d-flex align-center gap-3">
          <VSelect
            v-model="selectedYear"
            :items="availableYears"
            label="Año Fiscal"
            density="compact"
            variant="outlined"
            class="fiscal-year-select"
            hide-details
            @update:model-value="loadDashboardData"
          />
          <VBtn
            color="primary"
            variant="tonal"
            icon="tabler-refresh"
            :loading="loading"
            @click="loadDashboardData"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- Tarjetas de Estadísticas Principales de ISLR -->
    <FiscalProfitStats
      :loading="loading"
      :loading-declaration="loadingDeclaration"
      :renta-bruta="rentaBruta"
      :impuesto-i-s-l-r="impuestoISLR"
      :tramo-i-s-l-r="tramoISLR"
      :latest-declaration="latestDeclaration"
      :year="selectedYear"
      :format-currency="formatCurrency"
      :format-date="formatDate"
      @open-create="openCreateDeclarationDialog"
    />

    <!-- Reporte Gráfico de Ingresos y Gastos en Bolívares -->
    <div class="mb-6">
      <FiscalRevenueReport
        :year="selectedYear"
        :format-currency="formatCurrency"
        @update:year="(y) => { selectedYear = y; loadDashboardData(); }"
      />
    </div>

    <!-- Desglose de Ingresos y Gastos Deducibles -->
    <FiscalIncomeExpenseBreakdown
      :loading="loading"
      :total-income-data="totalIncomeData"
      :deductible-expenses-data="deductibleExpensesData"
      :year="selectedYear"
      :format-currency="formatCurrency"
    />

    <!-- Bloque de Retenciones y Crédito Fiscal -->
    <FiscalRetentionsSummaryCard
      :loading="loading"
      :retentions-data="retentionsData"
      :year="selectedYear"
      :format-currency="formatCurrency"
    />

    <!-- Estado de Resultados y Gastos No Deducibles -->
    <FiscalIncomeExpenseSummary
      :loading="loading"
      :revenue-stats="revenueStats"
      :non-deductible-expenses-data="nonDeductibleExpensesData"
      :year="selectedYear"
      :format-currency="formatCurrency"
      @download-report="handleDownloadReport"
      @print-report="handlePrintReport"
    />

    <!-- Diálogo para crear declaración ISLR -->
    <FiscalDeclarationDialog
      v-model="showCreateDialog"
      :initial-year="selectedYear"
      :estimated-amount="impuestoISLR"
      :loading="savingDeclaration"
      @submit="createDeclaration"
    />
  </div>
</template>

<script setup>
import FiscalDeclarationDialog from "@/components/fiscal/FiscalDeclarationDialog.vue";
import FiscalIncomeExpenseBreakdown from "@/components/fiscal/FiscalIncomeExpenseBreakdown.vue";
import FiscalIncomeExpenseSummary from "@/components/fiscal/FiscalIncomeExpenseSummary.vue";
import FiscalProfitStats from "@/components/fiscal/FiscalProfitStats.vue";
import FiscalRetentionsSummaryCard from "@/components/fiscal/FiscalRetentionsSummaryCard.vue";
import FiscalRevenueReport from "@/components/fiscal/FiscalRevenueReport.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";

const loading = ref(false);
const loadingDeclaration = ref(false);
const savingDeclaration = ref(false);
const showCreateDialog = ref(false);

const currentYear = new Date().getFullYear();
const selectedYear = ref(currentYear);

const availableYears = computed(() => {
  const years = [];
  for (let i = 0; i < 5; i++) {
    years.push(currentYear - i);
  }
  return years;
});

const islrData = ref({
  gross_income: 0,
  deductions: 0,
  net_income: 0,
  ibg: 0,
  costs: 0,
  year: currentYear,
});

const latestDeclaration = ref(null);
const unidadesTributarias = ref(0);

const totalIncomeData = ref({
  total_income: 0,
  exempt_amount: 0,
  taxable_amount: 0,
  exempt_percentage: 0,
  taxable_percentage: 0,
});

const deductibleExpensesData = ref({
  total_deductible: 0,
  categories: [],
});

const revenueStats = ref({
  total_income: 0,
  total_expenses: 0,
  net_revenue: 0,
});

const nonDeductibleExpensesData = ref({
  total_non_deductible: 0,
  categories: [],
});

const retentionsData = ref({
  generated: {
    count: 0,
    total_base: 0,
    total_tax: 0,
    total_withheld: 0,
  },
  pending: {
    count: 0,
    total_base: 0,
    total_tax: 0,
    estimated_withheld: 0,
  },
  year: currentYear,
});

const rentaBruta = computed(() => islrData.value.gross_income || 0);

const impuestoISLR = computed(() => {
  if (unidadesTributarias.value === 0) return 0;

  const utCalculadas = rentaBruta.value / unidadesTributarias.value;
  let impuesto = 0;

  if (utCalculadas <= 2000) {
    impuesto = utCalculadas * 0.15;
  } else if (utCalculadas <= 3000) {
    impuesto = utCalculadas * 0.22 - 140;
  } else {
    impuesto = utCalculadas * 0.34 - 500;
  }

  return Math.max(0, impuesto * unidadesTributarias.value);
});

const tramoISLR = computed(() => {
  if (unidadesTributarias.value === 0)
    return { tramo: "N/A", tasa: 0, ajuste: 0 };

  const utCalculadas = rentaBruta.value / unidadesTributarias.value;

  if (utCalculadas <= 2000) {
    return { tramo: "Hasta 2.000 UT", tasa: 15, ajuste: 0 };
  } else if (utCalculadas <= 3000) {
    return { tramo: "2.001 a 3.000 UT", tasa: 22, ajuste: 140 };
  } else {
    return { tramo: "Más de 3.000 UT", tasa: 34, ajuste: 500 };
  }
});

/**
 * Formateador oficial de moneda para Venezuela: Bs. X.XXX.XXX,XX
 */
const formatCurrency = (amount) => {
  const formatted = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount || 0);
  return `Bs. ${formatted}`;
};

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
};

const fetchTaxUnit = async () => {
  try {
    const { data } = await axios.get("/islr/tax-unit");
    unidadesTributarias.value = data?.data?.value || 0;
  } catch (error) {
    console.error("Error al cargar Unidades Tributarias:", error);
    unidadesTributarias.value = 0;
  }
};

const loadDashboardData = async () => {
  loading.value = true;
  loadingDeclaration.value = true;

  try {
    await fetchTaxUnit();

    const [
      summaryRes,
      declarationRes,
      incomeRes,
      deductibleRes,
      revenueRes,
      nonDeductibleRes,
      retentionsRes,
    ] = await Promise.allSettled([
      axios.get("/islr/summary", { params: { year: selectedYear.value } }),
      axios.get("/islr/declarations", { params: { year: selectedYear.value } }),
      axios.get("/dashboard/total-income", { params: { year: selectedYear.value } }),
      axios.get("/dashboard/deductible-expenses", { params: { year: selectedYear.value } }),
      axios.get("/dashboard/revenue-report", { params: { year: selectedYear.value } }),
      axios.get("/dashboard/non-deductible-expenses", { params: { year: selectedYear.value } }),
      axios.get("/dashboard/fiscal-retentions", { params: { year: selectedYear.value } }),
    ]);

    if (summaryRes.status === "fulfilled") {
      islrData.value = summaryRes.value.data?.data || { gross_income: 0 };
    }

    if (declarationRes.status === "fulfilled") {
      latestDeclaration.value = declarationRes.value.data?.data || null;
    } else {
      latestDeclaration.value = null;
    }

    if (incomeRes.status === "fulfilled") {
      totalIncomeData.value = incomeRes.value.data?.data || {};
    }

    if (deductibleRes.status === "fulfilled") {
      deductibleExpensesData.value = deductibleRes.value.data?.data || {};
    }

    if (revenueRes.status === "fulfilled") {
      revenueStats.value = revenueRes.value.data?.data?.summary || {};
    }

    if (nonDeductibleRes.status === "fulfilled") {
      nonDeductibleExpensesData.value = nonDeductibleRes.value.data?.data || {};
    }

    if (retentionsRes.status === "fulfilled") {
      retentionsData.value = retentionsRes.value.data?.data || {};
    }
  } catch (error) {
    console.error("Error al cargar los datos del dashboard fiscal:", error);
    toast.error("Ocurrió un error al cargar la información fiscal.");
  } finally {
    loading.value = false;
    loadingDeclaration.value = false;
  }
};

const openCreateDeclarationDialog = () => {
  showCreateDialog.value = true;
};

const createDeclaration = async (payload) => {
  savingDeclaration.value = true;
  try {
    await axios.post("/islr/declarations", payload);
    toast.success("Declaración creada exitosamente");
    showCreateDialog.value = false;
    await loadDashboardData();
  } catch (error) {
    console.error("Error al crear declaración:", error);
    toast.error(
      error.response?.data?.message || "Error al crear la declaración",
    );
  } finally {
    savingDeclaration.value = false;
  }
};

const handleDownloadReport = () => {
  toast.info("Generando reporte fiscal en PDF...");
};

const handlePrintReport = () => {
  window.print();
};

onMounted(() => {
  loadDashboardData();
});
</script>

<style scoped>
.match-height :deep(.v-col) {
  display: flex;
}

.match-height :deep(.v-card) {
  width: 100%;
}

.fiscal-year-select {
  width: 140px;
}
</style>
