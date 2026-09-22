<script setup>
import EditUTDialog from "@/components/dialogs/EditUTDialog.vue";
import IslrConsolidatedCard from "@/components/islr/IslrConsolidatedCard.vue";
import IslrMonthlyBreakdownTable from "@/components/islr/IslrMonthlyBreakdownTable.vue";
import IslrFilters from "@/components/IslrFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

// --- Estados Reactivos ---
const loading = ref(false);
const savingUT = ref(false);
const isCeEnabled = ref(false);
const islrData = ref({
  gross_income: 0,
  deductions: 0,
  non_deductible: 0,
  net_income: 0,
  ibg: 0,
  costs: 0,
  withholdings: 0,
  year: new Date().getFullYear(),
  monthly_breakdown: [],
});

const selectedYear = ref(new Date().getFullYear());
const unidadesTributarias = ref(0);
const showEditUTDialog = ref(false);

// --- Propiedades Computadas de Negocio ---
const rentaBruta = computed(() => islrData.value.gross_income || 0);
const deducciones = computed(() => islrData.value.deductions || 0);
const noDeducibles = computed(() => islrData.value.non_deductible || 0);
const montoConDeducciones = computed(() => islrData.value.net_income || 0);
const totalVentas = computed(() => rentaBruta.value);
const totalCompras = computed(() => islrData.value.costs || 0);
const retencionesAcumuladas = computed(() => islrData.value.withholdings || 0);

// Anticipos estimados de Contribuyente Especial (0.75% sobre ingresos brutos)
const anticiposCE = computed(() => {
  return isCeEnabled.value ? rentaBruta.value * 0.0075 : 0;
});

// Cálculo del Impuesto sobre la Renta (Tarifa N° 2 PJ)
const impuestoISLR = computed(() => {
  if (unidadesTributarias.value === 0 || montoConDeducciones.value <= 0) return 0;

  const utCalculadas = montoConDeducciones.value / unidadesTributarias.value;
  let impuesto = 0;

  if (utCalculadas <= 2000) {
    impuesto = utCalculadas * 0.15;
  } else if (utCalculadas <= 3000) {
    impuesto = (utCalculadas * 0.22) - 140;
  } else {
    impuesto = (utCalculadas * 0.34) - 500;
  }

  return Math.max(0, impuesto);
});

const impuestoISLREnBolivares = computed(() => {
  return impuestoISLR.value * unidadesTributarias.value;
});

const totalCreditosYAnticipos = computed(() => {
  return retencionesAcumuladas.value + anticiposCE.value;
});

const totalNetoALiquidar = computed(() => {
  return Math.max(0, impuestoISLREnBolivares.value - totalCreditosYAnticipos.value);
});

const tramoISLR = computed(() => {
  if (unidadesTributarias.value === 0 || montoConDeducciones.value <= 0) {
    return { tramo: "Exento / Sin Base", tasa: 0, sustraendo: 0 };
  }

  const utCalculadas = montoConDeducciones.value / unidadesTributarias.value;

  if (utCalculadas <= 2000) {
    return { tramo: "Hasta 2.000 UT", tasa: 15, sustraendo: 0 };
  } else if (utCalculadas <= 3000) {
    return { tramo: "2.001 a 3.000 UT", tasa: 22, sustraendo: 140 };
  } else {
    return { tramo: "Más de 3.000 UT", tasa: 34, sustraendo: 500 };
  }
});

// Años fiscales disponibles (Últimos 5 años)
const availableYears = computed(() => {
  const currentYear = new Date().getFullYear();
  return Array.from({ length: 5 }, (_, i) => currentYear - i);
});

// --- Métodos de Utilidad ---
const formatCurrency = (amount) => {
  const val = parseFloat(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val);
};

// Tarjetas KPI
const kpiCards = computed(() => [
  {
    title: "RENTA BRUTA",
    value: `Bs. ${formatCurrency(rentaBruta.value)}`,
    subtitle: `Ingresos Fiscales ${selectedYear.value}`,
    icon: "tabler-cash-banknote",
    color: "success",
    bgColor: "bg-success-tonal",
  },
  {
    title: "BASE IMPONIBLE",
    value: `Bs. ${formatCurrency(montoConDeducciones.value)}`,
    subtitle: `Deducibles: -Bs. ${formatCurrency(deducciones.value)}`,
    icon: "tabler-receipt-2",
    color: "primary",
    bgColor: "bg-primary-tonal",
  },
  {
    title: "IMPUESTO ESTIMADO",
    value: `Bs. ${formatCurrency(impuestoISLREnBolivares.value)}`,
    subtitle: `${impuestoISLR.value.toFixed(2)} UT (Tasa ${tramoISLR.value.tasa}%)`,
    icon: "tabler-calculator-tax",
    color: "warning",
    bgColor: "bg-warning-tonal",
  },
  {
    title: isCeEnabled.value ? "RETENCIONES + ANTICIPOS" : "RETENCIONES ISLR",
    value: `Bs. ${formatCurrency(totalCreditosYAnticipos.value)}`,
    subtitle: isCeEnabled.value ? `Anticipo CE: Bs. ${formatCurrency(anticiposCE.value)}` : "Créditos fiscales del año",
    icon: "tabler-receipt-refund",
    color: "secondary",
    bgColor: "bg-secondary-tonal",
  },
  {
    title: "TOTAL A LIQUIDAR",
    value: `Bs. ${formatCurrency(totalNetoALiquidar.value)}`,
    subtitle: totalNetoALiquidar.value > 0 ? "Monto final a pagar al SENIAT" : "Sin impuesto pendiente",
    icon: totalNetoALiquidar.value > 0 ? "tabler-alert-circle" : "tabler-circle-check",
    color: totalNetoALiquidar.value > 0 ? "error" : "success",
    bgColor: totalNetoALiquidar.value > 0 ? "bg-error-tonal" : "bg-success-tonal",
  },
]);

// --- Peticiones de Datos (Paralelas) ---
const fetchIslrData = async () => {
  try {
    const { data } = await axios.get("/islr/summary", {
      params: { year: selectedYear.value },
    });
    islrData.value = data?.data || islrData.value;
  } catch (error) {
    console.error("Error al cargar resumen ISLR:", error);
    toast.error("Error al sincronizar datos del ISLR.");
  }
};

const fetchTaxUnit = async () => {
  try {
    const { data } = await axios.get("/islr/tax-unit");
    unidadesTributarias.value = data?.data?.value || 0;
  } catch (error) {
    console.error("Error al cargar Unidad Tributaria:", error);
    unidadesTributarias.value = 0;
  }
};

// Sincronización paralela sin peticiones en cascada
const refreshAllData = async () => {
  loading.value = true;
  try {
    await Promise.all([fetchIslrData(), fetchTaxUnit()]);
  } catch (error) {
    console.error("Error al refrescar información fiscal:", error);
  } finally {
    loading.value = false;
  }
};

const handleSaveUT = async (data) => {
  savingUT.value = true;
  try {
    const response = await axios.post("/islr/tax-unit", {
      value: data.value,
      effective_date: data.effective_date,
      notes: data.notes,
    });
    unidadesTributarias.value = response.data?.data?.value || data.value;
    toast.success("Unidad Tributaria actualizada con éxito.");
    refreshAllData();
  } catch (error) {
    console.error("Error al actualizar la Unidad Tributaria:", error);
    toast.error("No se pudo actualizar el valor fiscal.");
  } finally {
    savingUT.value = false;
  }
};

const handleClear = () => {
  selectedYear.value = new Date().getFullYear();
  isCeEnabled.value = false;
  refreshAllData();
};

const handleExport = () => {
  const breakdown = islrData.value.monthly_breakdown || [];
  if (breakdown.length === 0) {
    toast.warning("No hay datos para exportar.");
    return;
  }

  let csvContent = "data:text/csv;charset=utf-8,";
  csvContent += "Mes,Ingresos Brutos (Bs),Costos Compras (Bs),Gastos Deducibles (Bs),Gastos No Deducibles (Bs),Base Imponible (Bs),Anticipo CE (Bs)\n";

  breakdown.forEach((row) => {
    csvContent += `"${row.month_name}",${row.fiscal_total},${row.costs},${row.deductions},${row.non_deductible},${row.net_income},${row.estimated_prepayment}\n`;
  });

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `reporte_islr_ejercicio_${selectedYear.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success("Reporte fiscal consolidado descargado exitosamente.");
};

onMounted(() => {
  refreshAllData();
});
</script>

<template>
  <div class="islr-general-page pb-12">
    <div class="d-flex flex-column gap-3 mt-1">
      <!-- Tarjetas KPIs Principales -->
      <VRow dense class="mb-2">
        <VCol
          v-for="(card, index) in kpiCards"
          :key="index"
          cols="12"
          sm="6"
          :md="index === 4 ? 12 : 3"
          :lg="index === 4 ? (kpiCards.length === 5 ? 'auto' : 3) : ''"
          class="flex-grow-1"
        >
          <VSkeletonLoader v-if="loading" type="card" height="135" class="rounded-lg border-0" />
          <VCard
            v-else
            class="kpi-card-modern border-0 overflow-hidden h-100 position-relative shadow-sm"
            elevation="0"
          >
            <!-- Acento lateral izquierdo -->
            <div
              class="kpi-accent-stripe"
              :class="`bg-${card.color}`"
            ></div>

            <VCardText class="pa-4 ps-5">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-overline font-weight-bold text-medium-emphasis tracking-wider">
                  {{ card.title }}
                </span>
                <VAvatar :color="card.color" variant="tonal" size="36" rounded="lg">
                  <VIcon :icon="card.icon" size="20" />
                </VAvatar>
              </div>

              <div class="text-h5 font-weight-black mb-1 d-flex align-baseline">
                {{ card.value }}
              </div>

              <div class="text-caption font-weight-medium text-disabled truncate">
                {{ card.subtitle }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Filtros Anuales y Acciones -->
      <IslrFilters
        v-model:selected-year="selectedYear"
        v-model:is-ce-enabled="isCeEnabled"
        :available-years="availableYears"
        :loading="loading"
        @refresh="refreshAllData"
        @clear="handleClear"
        @adjust-ut="showEditUTDialog = true"
        @export="handleExport"
      />

      <!-- Detalle Financiero Consolidado -->
      <IslrConsolidatedCard
        :selected-year="selectedYear"
        :total-ventas="totalVentas"
        :total-compras="totalCompras"
        :deducciones="deducciones"
        :no-deducibles="noDeducibles"
        :base-imponible="montoConDeducciones"
        :tramo-i-s-l-r="tramoISLR"
        :impuesto-i-s-l-r="impuestoISLR"
        :impuesto-i-s-l-r-en-bolivares="impuestoISLREnBolivares"
        :retenciones-acumuladas="retencionesAcumuladas"
        :anticipos-c-e="anticiposCE"
        :is-ce-enabled="isCeEnabled"
        :loading="loading"
      />

      <!-- Tabla de Desglose Mensual -->
      <IslrMonthlyBreakdownTable
        :items="islrData.monthly_breakdown"
        :loading="loading"
        :is-ce-enabled="isCeEnabled"
      />
    </div>

    <!-- Edit UT Dialog Component -->
    <EditUTDialog
      v-model="showEditUTDialog"
      :current-value="unidadesTributarias"
      :loading="savingUT"
      @save="handleSaveUT"
    />
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.kpi-card-modern {
  border-radius: 10px !important;
  background: rgba(var(--v-theme-surface), 95%) !important;
  border: 1px solid rgba(var(--v-border-color), 0.08) !important;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card-modern:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06) !important;
}

.kpi-accent-stripe {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  width: 4px;
}

.tracking-wider {
  letter-spacing: 0.06em !important;
}
</style>
