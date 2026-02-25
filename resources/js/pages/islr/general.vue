<script setup>
import EditUTDialog from "@/components/dialogs/EditUTDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";

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

  // Usar el monto con deducciones en lugar de la renta bruta
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

  // Usar el monto con deducciones en lugar de la renta bruta
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
    style: "currency",
    currency: "VES",
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

    toast.success("Datos del ISLR actualizados");
  } catch (error) {
    console.error("Error al cargar datos del ISLR:", error);
    toast.error("No se pudieron cargar los datos del ISLR.");
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

const handleRefresh = () => {
  fetchIslrData();
};

const handleExport = async (format) => {
  toast.info(`Exportando resumen ISLR a ${format}...`);
};

const handleYearChange = () => {
  fetchIslrData();
};

const openEditUTDialog = () => {
  showEditUTDialog.value = true;
};

const handleSaveUT = async (data) => {
  try {
    const response = await axios.post("/islr/tax-unit", {
      value: data.value,
      effective_date: data.effective_date,
      notes: data.notes,
    });
    unidadesTributarias.value = response.data.data.value;
    toast.success("Unidades Tributarias actualizadas con éxito");
  } catch (error) {
    console.error("Error al actualizar Unidades Tributarias:", error);
    toast.error("No se pudo actualizar las Unidades Tributarias");
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
  <div>
    <!-- Card de Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="selectedYear"
              :items="availableYears"
              placeholder="Seleccione el año fiscal"
              variant="outlined"
              density="comfortable"
              label="Estado de Stock"
              @update:model-value="handleYearChange"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" class="d-flex align-center">
            <p class="text-body-2 text-medium-emphasis mb-0">
              <VIcon size="18" class="mr-1" color="primary"
                >mdi-information-outline</VIcon
              >
              Seleccione el año fiscal para visualizar el desglose de renta
              bruta e impuestos.
            </p>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
        <VBtn color="secondary" variant="outlined" @click="handleClear">
          Limpiar Filtros
        </VBtn>

        <VSpacer />

        <VBtn
          color="primary"
          prepend-icon="mdi-refresh"
          :loading="loading"
          @click="handleRefresh"
        >
          Actualizar Datos
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Unidades Tributarias -->
    <VRow class="mb-4">
      <VCol cols="12">
        <VAlert
          type="info"
          variant="tonal"
          border="start"
          class="d-flex align-center"
        >
          <div class="d-flex align-center justify-space-between w-100">
            <div class="d-flex align-center gap-2">
              <VIcon size="24">mdi-calculator-variant</VIcon>
              <span class="text-body-1 font-weight-medium">
                Unidades Tributarias Actuales:
              </span>
              <span class="text-h6 font-weight-bold">
                {{ formatCurrency(unidadesTributarias) }}
              </span>
            </div>
            <VBtn
              color="info"
              variant="outlined"
              size="small"
              class="ml-4"
              @click="openEditUTDialog"
            >
              Editar
            </VBtn>
          </div>
        </VAlert>
      </VCol>
    </VRow>

    <!-- Cards de Renta Bruta, Impuesto ISLR y Monto con Deducciones -->
    <VRow>
      <!-- Card de Renta Bruta -->
      <VCol cols="12" md="4">
        <VCard class="h-100" elevation="3">
          <VCardTitle class="d-flex align-center bg-success text-white py-4">
            <VIcon class="mr-2" size="large">mdi-cash-multiple</VIcon>
            Renta Bruta
          </VCardTitle>

          <VCardText
            class="d-flex flex-column align-center justify-center py-12"
          >
            <VIcon
              icon="mdi-trending-up"
              size="80"
              color="success"
              class="mb-4"
            />
            <div class="text-h2 font-weight-bold text-success mb-2">
              {{ formatCurrency(rentaBruta) }}
            </div>
            <p class="text-body-1 text-medium-emphasis">
              Total de ingresos fiscales
            </p>
            <VChip color="success" variant="outlined" size="small" class="mt-3">
              Año {{ selectedYear }}
            </VChip>
          </VCardText>

          <VCardActions class="px-4 py-3 bg-grey-lighten-5">
            <VIcon color="success" size="small">mdi-information</VIcon>
            <span class="text-body-2 text-medium-emphasis ml-1">
              Sumatoria total de FiscalHistory
            </span>
          </VCardActions>
        </VCard>
      </VCol>

      <!-- Card de Monto con Deducciones -->
      <VCol cols="12" md="4">
        <VCard class="h-100" elevation="3">
          <VCardTitle class="d-flex align-center bg-primary text-white py-4">
            <VIcon class="mr-2" size="large">mdi-calculator</VIcon>
            Monto con Deducciones
          </VCardTitle>

          <VCardText
            class="d-flex flex-column align-center justify-center py-12"
          >
            <VIcon
              icon="mdi-cash-check"
              size="80"
              color="primary"
              class="mb-4"
            />
            <div class="text-h2 font-weight-bold text-primary mb-2">
              {{ formatCurrency(montoConDeducciones) }}
            </div>
            <p class="text-body-1 text-medium-emphasis">
              Ingreso neto después de costos y deducciones
            </p>
            <VChip
              v-if="deducciones > 0"
              color="error"
              variant="outlined"
              size="small"
              class="mt-3"
            >
              -{{ formatCurrency(deducciones) }} deducido
            </VChip>
            <VChip
              v-else
              color="info"
              variant="outlined"
              size="small"
              class="mt-3"
            >
              Sin deducciones
            </VChip>
          </VCardText>

          <VCardActions class="px-4 py-3 bg-grey-lighten-5">
            <VIcon color="primary" size="small">mdi-information</VIcon>
            <span class="text-body-2 text-medium-emphasis ml-1">
              IBG menos costos y deducciones
            </span>
          </VCardActions>
        </VCard>
      </VCol>

      <!-- Card de Impuesto ISLR -->
      <VCol cols="12" md="4">
        <VCard class="h-100" elevation="3">
          <VCardTitle class="d-flex align-center bg-warning text-white py-4">
            <VIcon class="mr-2" size="large"
              >mdi-calculator-variant-outline</VIcon
            >
            Impuesto ISLR
          </VCardTitle>

          <VCardText
            class="d-flex flex-column align-center justify-center py-12"
          >
            <VIcon
              icon="mdi-bank-transfer"
              size="80"
              color="warning"
              class="mb-4"
            />
            <div class="text-h2 font-weight-bold text-warning mb-2">
              {{ impuestoISLR.toFixed(2) }} UT
            </div>
            <p class="text-body-2 text-medium-emphasis mb-3">
              {{ formatCurrency(impuestoISLREnBolivares) }}
            </p>
            <p class="text-body-1 text-medium-emphasis">
              {{ tramoISLR.tramo }}
            </p>
            <VChip color="warning" variant="outlined" size="small" class="mt-3">
              {{ tramoISLR.tasa }}%
              <span v-if="tramoISLR.ajuste > 0">
                - {{ tramoISLR.ajuste }} UT
              </span>
            </VChip>
          </VCardText>

          <VCardActions class="px-4 py-3 bg-grey-lighten-5">
            <VIcon color="warning" size="small">mdi-information</VIcon>
            <span class="text-body-2 text-medium-emphasis ml-1">
              Calculado sobre el monto con deducciones
            </span>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Resumen Financiero -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard elevation="2">
          <VCardTitle class="bg-grey-lighten-4">
            <VIcon class="mr-2">mdi-file-document-outline</VIcon>
            Detalle Financiero - Año {{ selectedYear }}
          </VCardTitle>
          <VCardText class="py-6">
            <VRow class="text-center">
              <VCol cols="12" md="4">
                <VIcon color="success" size="48" class="mb-3">
                  mdi-cash-multiple
                </VIcon>
                <p class="text-caption text-medium-emphasis mb-2">
                  IBG (Ingreso Bruto General)
                </p>
                <div class="text-h4 font-weight-bold text-success">
                  {{ formatCurrency(islrData.ibg) }}
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <VIcon color="warning" size="48" class="mb-3">
                  mdi-receipt-text-outline
                </VIcon>
                <p class="text-caption text-medium-emphasis mb-2">
                  Costos (Con Factura)
                </p>
                <div class="text-h4 font-weight-bold text-warning">
                  {{ formatCurrency(islrData.costs) }}
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <VIcon color="error" size="48" class="mb-3">
                  mdi-calculator
                </VIcon>
                <p class="text-caption text-medium-emphasis mb-2">
                  Total Deducciones
                </p>
                <div class="text-h4 font-weight-bold text-error">
                  {{ formatCurrency(deducciones) }}
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Loading Overlay -->
    <VOverlay v-model="loading" class="align-center justify-center">
      <VProgressCircular color="primary" indeterminate size="64" />
    </VOverlay>

    <!-- Dialog para editar Unidades Tributarias -->
    <EditUTDialog
      v-model="showEditUTDialog"
      :current-value="unidadesTributarias"
      @save="handleSaveUT"
    />
  </div>
</template>
