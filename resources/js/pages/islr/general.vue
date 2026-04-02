<script setup>
import EditUTDialog from "@/components/dialogs/EditUTDialog.vue";
import IslrFilters from "@/components/IslrFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const loading = ref(false);
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

const rentaBruta = computed(() => islrData.value.gross_income || 0);
const deducciones = computed(() => islrData.value.deductions || 0);
const montoConDeducciones = computed(() => islrData.value.net_income || 0);

const impuestoISLR = computed(() => {
  if (unidadesTributarias.value === 0) return 0;

  const utCalculadas = montoConDeducciones.value / unidadesTributarias.value;
  let impuesto = 0;

  if (utCalculadas <= 2000) {
    impuesto = utCalculadas * 0.15;
  } else if (utCalculadas <= 3000) {
    impuesto = utCalculadas * 0.22 - 140;
  } else {
    impuesto = utCalculadas * 0.34 - 500;
  }

  return impuesto;
});

const impuestoISLREnBolivares = computed(() => {
  return impuestoISLR.value * unidadesTributarias.value;
});

const tramoISLR = computed(() => {
  if (unidadesTributarias.value === 0)
    return { tramo: "N/A", tasa: 0, ajuste: 0 };

  const utCalculadas = montoConDeducciones.value / unidadesTributarias.value;

  if (utCalculadas <= 2000) {
    return { tramo: "Hasta 2.000 UT", tasa: 15, ajuste: 0 };
  } else if (utCalculadas <= 3000) {
    return { tramo: "2.001 a 3.000 UT", tasa: 22, ajuste: 140 };
  } else {
    return { tramo: "Más de 3.000 UT", tasa: 34, ajuste: 500 };
  }
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const fetchIslrData = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/islr/summary", {
      params: { year: selectedYear.value },
    });
    islrData.value = data.data;
    await fetchTaxUnit();
  } catch (error) {
    console.error("Error al cargar datos del ISLR:", error);
    toast.error("No se pudieron cargar los datos.");
  } finally {
    loading.value = false;
  }
};

const fetchTaxUnit = async () => {
  try {
    const { data } = await axios.get("/islr/tax-unit");
    unidadesTributarias.value = data.data.value || 0;
  } catch (error) {
    console.error("Error al cargar Unidades Tributarias:", error);
    unidadesTributarias.value = 0;
  }
};

const handleRefresh = () => fetchIslrData();

const handleSaveUT = async (data) => {
  try {
    const response = await axios.post("/islr/tax-unit", {
      value: data.value,
      effective_date: data.effective_date,
      notes: data.notes,
    });
    unidadesTributarias.value = response.data.data.value;
    toast.success("Unidad Tributaria actualizada");
    fetchIslrData();
  } catch (error) {
    console.error("Error al actualizar UT:", error);
    toast.error("Error al actualizar el valor fiscal.");
  }
};

const availableYears = computed(() => {
  const currentYear = new Date().getFullYear();
  const years = [];
  for (let i = 0; i < 5; i++) {
    years.push(currentYear - i);
  }
  return years;
});

const handleClear = () => {
  selectedYear.value = new Date().getFullYear();
  fetchIslrData();
};

onMounted(() => {
  fetchIslrData();
});
</script>

<template>
  <div class="islr-general-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Dashboard Premium de ISLR -->
      <VRow class="ma-0 mb-6 mx-n1 match-height">
        <!-- Card Renta Bruta -->
        <VCol cols="12" md="4" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <div
              class="card-bg-decoration"
              style="background: linear-gradient(45deg, rgba(var(--v-theme-success), 0.1), transparent)"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar color="success" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-cash-banknote" size="26" />
                </VAvatar>

                <div class="text-right d-flex flex-column">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">
                    Renta Bruta
                  </span>
                  <h4 class="text-h4 font-weight-black mt-1">
                    <span class="text-sm font-weight-medium me-1">Bs.</span>{{ formatCurrency(rentaBruta) }}
                  </h4>
                </div>
              </div>

              <VDivider class="mb-3 opacity-20" />

              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis uppercase">
                  Ingresos Fiscales {{ selectedYear }}
                </span>
                <VIcon icon="tabler-trending-up" size="16" color="success" class="opacity-50" />
              </div>
            </VCardText>

            <div class="accent-border bg-success"></div>
          </VCard>
        </VCol>

        <!-- Card Base Imponible -->
        <VCol cols="12" md="4" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <div
              class="card-bg-decoration"
              style="background: linear-gradient(45deg, rgba(var(--v-theme-primary), 0.1), transparent)"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar color="primary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-receipt-2" size="26" />
                </VAvatar>

                <div class="text-right d-flex flex-column">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">
                    Base Imponible
                  </span>
                  <h4 class="text-h4 font-weight-black mt-1">
                    <span class="text-sm font-weight-medium me-1">Bs.</span>{{ formatCurrency(montoConDeducciones) }}
                  </h4>
                </div>
              </div>

              <VDivider class="mb-3 opacity-20" />

              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-1">
                  <span class="text-caption font-weight-medium text-medium-emphasis uppercase">
                    Post Deducciones:
                  </span>
                  <span class="text-xs font-weight-black text-primary" v-if="deducciones > 0">
                    -{{ formatCurrency(deducciones) }}
                  </span>
                </div>
                <VIcon icon="tabler-receipt" size="16" color="primary" class="opacity-50" />
              </div>
            </VCardText>

            <div class="accent-border bg-primary"></div>
          </VCard>
        </VCol>

        <!-- Card Impuesto Estimado -->
        <VCol cols="12" md="4" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <div
              class="card-bg-decoration"
              style="background: linear-gradient(45deg, rgba(var(--v-theme-warning), 0.1), transparent)"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar color="warning" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-calculator-tax" size="26" />
                </VAvatar>

                <div class="text-right d-flex flex-column">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">
                    Impuesto ISLR
                  </span>
                  <h4 class="text-h4 font-weight-black mt-1">
                    {{ impuestoISLR.toFixed(2) }} <span class="text-sm font-weight-medium">U.T.</span>
                  </h4>
                </div>
              </div>

              <VDivider class="mb-3 opacity-20" />

              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VChip size="x-small" color="warning" variant="tonal" class="font-weight-black rounded">
                    {{ tramoISLR.tasa }}% TASA
                  </VChip>
                  <span class="text-super-xs text-medium-emphasis font-weight-bold uppercase truncate" style="max-width: 100px;">
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

      <!-- Unidades Tributarias Floating Alert -->
      <VAlert
        variant="tonal"
        color="info"
        class="ma-0 rounded-lg border-dashed border-info py-2"
      >
        <div class="d-flex align-center justify-space-between w-100 px-2">
          <div class="d-flex align-center gap-2">
            <VAvatar color="info" variant="tonal" size="32" class="rounded-lg">
              <VIcon icon="tabler-adjustments-alt" size="18" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span
                class="text-super-xs font-weight-black uppercase leading-tight"
                >U.T. Vigente</span
              >
              <span class="text-sm font-weight-black"
                >Bs. {{ formatCurrency(unidadesTributarias) }}</span
              >
            </div>
          </div>
          <VBtn
            color="info"
            variant="flat"
            size="small"
            class="rounded-lg text-super-xs font-weight-black px-4 shadow-sm"
            @click="showEditUTDialog = true"
          >
            AJUSTAR VALOR
          </VBtn>
        </div>
      </VAlert>

      <!-- Filtros Premium Colapsables -->
      <IslrFilters
        v-model:selected-year="selectedYear"
        :available-years="availableYears"
        :loading="loading"
        @refresh="handleRefresh"
        @clear="handleClear"
      />

      <!-- Detalle Financiero Consolidado -->
      <VCard class="ma-0 rounded-lg border-0 shadow-sm overflow-hidden bg-surface mt-6">
        <VCardTitle class="pa-4 px-6 d-flex align-center">
          <VAvatar
            color="secondary"
            variant="tonal"
            size="32"
            class="me-3 rounded-lg"
          >
            <VIcon icon="tabler-report-analytics" size="18" />
          </VAvatar>
          <span class="text-sm font-weight-black uppercase"
            >Consolidado Fiscal Anual</span
          >
          <VSpacer />
          <VChip color="secondary" size="small" class="font-weight-black"
            >EJERCICIO {{ selectedYear }}</VChip
          >
        </VCardTitle>

        <VDivider class="opacity-10" />

        <VCardText class="pa-0">
          <VRow no-gutters>
            <VCol cols="12" md="6" class="border-e">
              <div class="pa-6">
                <div class="d-flex align-center gap-2 mb-4">
                  <VIcon icon="tabler-building-bank" color="primary" />
                  <span class="text-subtitle-2 font-weight-black uppercase"
                    >Resumen Operativo</span
                  >
                </div>

                <div class="d-flex flex-column gap-3">
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-medium-emphasis"
                      >Total Ventas Brutas:</span
                    >
                    <span class="text-sm font-weight-black"
                      >Bs. {{ formatCurrency(totalVentas) }}</span
                    >
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-medium-emphasis"
                      >Total Compras Brutas:</span
                    >
                    <span class="text-sm font-weight-black"
                      >Bs. {{ formatCurrency(totalCompras) }}</span
                    >
                  </div>
                  <VDivider />
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption font-weight-black"
                      >Utilidad Operativa:</span
                    >
                    <span
                      class="text-sm font-weight-black"
                      :class="totalVentas - totalCompras >= 0 ? 'text-success' : 'text-error'"
                    >
                      Bs. {{ formatCurrency(totalVentas - totalCompras) }}
                    </span>
                  </div>
                </div>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="pa-6">
                <div class="d-flex align-center gap-2 mb-4">
                  <VIcon icon="tabler-scale" color="warning" />
                  <span class="text-subtitle-2 font-weight-black uppercase"
                    >Proyección de Impuesto</span
                  >
                </div>

                <div class="d-flex flex-column gap-3">
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-medium-emphasis"
                      >Tarifa Aplicable:</span
                    >
                    <VChip size="x-small" color="primary" rounded>{{
                      tramoISLR.tasa
                    }}%</VChip>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-medium-emphasis"
                      >Sustraendo Aplicable:</span
                    >
                    <span class="text-sm font-weight-black"
                      >{{ tramoISLR.sustraendo }} U.T.</span
                    >
                  </div>
                  <VDivider />
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption font-weight-black"
                      >Total a Pagar Estimado:</span
                    >
                    <div class="text-right">
                      <div class="text-h6 font-weight-black text-warning">
                        Bs. {{ formatCurrency(impuestoISLREnBolivares) }}
                      </div>
                      <div class="text-super-xs text-disabled">
                        {{ impuestoISLR.toFixed(2) }} U.T.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </div>

    <!-- Edit UT Dialog -->
    <VDialog v-model="showEditUTDialog" max-width="400" persistent>
      <VCard class="rounded-xl overflow-hidden border-0 shadow-lg">
        <div class="bg-primary pa-6 text-center position-relative">
          <VAvatar
            color="white"
            size="64"
            class="mb-3 shadow-sm border border-opacity-10"
          >
            <VIcon icon="tabler-settings-automation" color="primary" size="32" />
          </VAvatar>
          <h3 class="text-h5 font-weight-black text-white">Ajustar U.T.</h3>
          <p class="text-caption text-white opacity-80 mb-0">
            Valores para el ejercicio {{ selectedYear }}
          </p>
        </div>

        <VCardText class="pa-6">
          <VTextField
            v-model="utValueEdit"
            label="Valor Unidad Tributaria"
            prefix="Bs."
            type="number"
            variant="outlined"
            density="comfortable"
            hide-details
            class="rounded-lg mb-4"
          />

          <VAlert
            type="warning"
            variant="tonal"
            class="rounded-lg mb-0 text-caption font-weight-medium"
          >
            Este cambio afectará los cálculos de impuestos de todo el sistema.
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 pt-0 justify-end gap-2 mt-2">
          <VBtn
            variant="text"
            color="secondary"
            class="rounded-lg font-weight-black px-4"
            @click="showEditUTDialog = false"
          >
            CANCELAR
          </VBtn>
          <VBtn
            variant="flat"
            color="primary"
            class="rounded-lg font-weight-black px-6 shadow-sm"
            @click="updateUT"
          >
            GUARDAR CAMBIOS
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.text-super-xs.leading-tight {
  line-height: 1.1;
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 5%) !important;
  transition: all 0.3s ease;
  position: relative;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
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

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.border-dashed {
  border-style: dashed !important;
}

:deep(.v-chip.v-chip--size-super-xs) {
  height: 16px;
  font-size: 0.6rem;
}
</style>
