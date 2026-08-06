<script setup>
import EditUTDialog from "@/components/dialogs/EditUTDialog.vue";
import IslrConsolidatedCard from "@/components/islr/IslrConsolidatedCard.vue";
import IslrFilters from "@/components/IslrFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

// --- Estados Reactivos ---
const loading = ref(false);
const savingUT = ref(false);
const islrData = ref({
  gross_income: 0,
  deductions: 0,
  net_income: 0,
  ibg: 0,
  costs: 0,
  year: new Date().getFullYear(),
});

const selectedYear = ref(new Date().getFullYear());
const unidadesTributarias = ref(0);
const showEditUTDialog = ref(false);

// --- Propiedades Computadas de Negocio ---
const rentaBruta = computed(() => islrData.value.gross_income || 0);
const deducciones = computed(() => islrData.value.deductions || 0);
const montoConDeducciones = computed(() => islrData.value.net_income || 0);
const totalVentas = computed(() => rentaBruta.value);
const totalCompras = computed(() => islrData.value.costs || 0);

// Cálculo del Impuesto sobre la Renta (Tarifa N° 2 PN / PJ)
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

// Anos fiscales disponibles (Últimos 5 años)
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
  refreshAllData();
};

onMounted(() => {
  refreshAllData();
});
</script>

<template>
  <div class="islr-general-page pb-12">
    <div class="d-flex flex-column gap-3 mt-1">
      <!-- Tarjetas Principales de ISLR (Responsivas + Skeletons) -->
      <VRow dense class="mb-2">
        <!-- Card Renta Bruta -->
        <VCol cols="12" sm="6" md="4">
          <VSkeletonLoader v-if="loading" type="card" height="140" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div
              class="card-bg-decoration"
              style="background: linear-gradient(45deg, rgba(var(--v-theme-success), 0.12), transparent)"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="success" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-cash-banknote" size="24" />
                </VAvatar>

                <div class="text-right d-flex flex-column">
                  <span class="text-overline font-weight-bold text-disabled">
                    Renta Bruta
                  </span>
                  <h4 class="text-h4 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(rentaBruta) }}
                  </h4>
                </div>
              </div>

              <VDivider class="mb-2 opacity-20" />

              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis uppercase">
                  Ingresos Fiscales {{ selectedYear }}
                </span>
                <VIcon icon="tabler-trending-up" size="16" color="success" class="opacity-70" />
              </div>
            </VCardText>

            <div class="accent-border bg-success"></div>
          </VCard>
        </VCol>

        <!-- Card Base Imponible -->
        <VCol cols="12" sm="6" md="4">
          <VSkeletonLoader v-if="loading" type="card" height="140" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div
              class="card-bg-decoration"
              style="background: linear-gradient(45deg, rgba(var(--v-theme-primary), 0.12), transparent)"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="primary" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-receipt-2" size="24" />
                </VAvatar>

                <div class="text-right d-flex flex-column">
                  <span class="text-overline font-weight-bold text-disabled">
                    Base Imponible
                  </span>
                  <h4 class="text-h4 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(montoConDeducciones) }}
                  </h4>
                </div>
              </div>

              <VDivider class="mb-2 opacity-20" />

              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-1">
                  <span class="text-caption font-weight-medium text-medium-emphasis uppercase">
                    Deducciones:
                  </span>
                  <span class="text-xs font-weight-black text-primary" v-if="deducciones > 0">
                    -{{ formatCurrency(deducciones) }}
                  </span>
                  <span class="text-xs text-disabled" v-else>Bs. 0.00</span>
                </div>
                <VIcon icon="tabler-receipt" size="16" color="primary" class="opacity-70" />
              </div>
            </VCardText>

            <div class="accent-border bg-primary"></div>
          </VCard>
        </VCol>

        <!-- Card Impuesto Estimado -->
        <VCol cols="12" sm="12" md="4">
          <VSkeletonLoader v-if="loading" type="card" height="140" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div
              class="card-bg-decoration"
              style="background: linear-gradient(45deg, rgba(var(--v-theme-warning), 0.12), transparent)"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="warning" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-calculator-tax" size="24" />
                </VAvatar>

                <div class="text-right d-flex flex-column">
                  <span class="text-overline font-weight-bold text-disabled">
                    Impuesto ISLR
                  </span>
                  <h4 class="text-h4 font-weight-black mt-1">
                    {{ impuestoISLR.toFixed(2) }} <span class="text-xs font-weight-medium">U.T.</span>
                  </h4>
                </div>
              </div>

              <VDivider class="mb-2 opacity-20" />

              <div class="d-flex align-center justify-space-between flex-wrap gap-1">
                <div class="d-flex align-center gap-1">
                  <VChip size="x-small" color="warning" variant="tonal" class="font-weight-black rounded">
                    {{ tramoISLR.tasa }}% TASA
                  </VChip>
                  <span class="text-super-xs text-medium-emphasis font-weight-bold uppercase truncate" style="max-width: 110px;">
                    {{ tramoISLR.tramo }}
                  </span>
                </div>
                <span class="text-xs font-weight-black text-warning">
                  Bs. {{ formatCurrency(impuestoISLREnBolivares) }}
                </span>
              </div>
            </VCardText>

            <div class="accent-border bg-warning"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Filtros Anuales Colapsables -->
      <IslrFilters
        v-model:selected-year="selectedYear"
        :available-years="availableYears"
        :loading="loading"
        @refresh="refreshAllData"
        @clear="handleClear"
        @adjust-ut="showEditUTDialog = true"
      />

      <!-- Detalle Financiero Consolidado -->
      <IslrConsolidatedCard
        :selected-year="selectedYear"
        :total-ventas="totalVentas"
        :total-compras="totalCompras"
        :tramo-i-s-l-r="tramoISLR"
        :impuesto-i-s-l-r="impuestoISLR"
        :impuesto-i-s-l-r-en-bolivares="impuestoISLREnBolivares"
        :loading="loading"
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

.stats-card {
  border-radius: 10px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  box-shadow: 0 4px 18px 0 rgba(0, 0, 0, 4%) !important;
  transition: all 0.25s ease-in-out;
  position: relative;
}

.stats-card:hover {
  box-shadow: 0 6px 22px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-3px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 90px;
  filter: blur(35px);
  inline-size: 90px;
  inset-block-start: -15px;
  inset-inline-end: -15px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
}
</style>
