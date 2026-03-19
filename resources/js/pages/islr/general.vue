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
  <div :class="mobile ? 'pa-0' : 'pa-4'">
    <!-- Filtros Premium Colapsables -->
    <IslrFilters
      v-model:selected-year="selectedYear"
      :available-years="availableYears"
      :loading="loading"
      @refresh="handleRefresh"
      @clear="handleClear"
    />

    <!-- Unidades Tributarias Floating Alert -->
    <VAlert
      variant="tonal"
      color="info"
      class="mb-6 rounded-xl border-dashed border-info py-2"
    >
      <div class="d-flex align-center justify-space-between w-100 px-2">
        <div class="d-flex align-center gap-2">
          <VAvatar color="info" variant="tonal" size="32" class="rounded-lg">
            <VIcon icon="tabler-adjustments-alt" size="18" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-super-xs font-weight-black uppercase leading-tight">U.T. Vigente</span>
            <span class="text-sm font-weight-black">Bs. {{ formatCurrency(unidadesTributarias) }}</span>
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

    <!-- Dashboard Premium de ISLR -->
    <VRow class="match-height mb-6">
      <!-- Card Renta Bruta -->
      <VCol cols="12" md="4">
        <VCard class="rounded-xl border-0 premium-summary-card bg-success-gradient overflow-hidden h-100 shadow-md">
          <VCardText class="pa-6 d-flex flex-column align-center text-center">
            <VAvatar color="white" variant="tonal" size="44" class="mb-3 rounded-lg">
              <VIcon icon="tabler-cash-banknote" size="24" color="white" />
            </VAvatar>
            <span class="text-xs font-weight-bold uppercase text-white opacity-70 mb-1">Renta Bruta</span>
            <div class="text-h4 font-weight-black text-white mb-2 leading-none">
              <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(rentaBruta) }}
            </div>
            <div class="mt-auto d-flex align-center gap-2 pt-2">
              <VChip size="x-small" color="white" variant="flat" class="font-weight-black rounded">
                AÑO {{ selectedYear }}
              </VChip>
              <span class="text-super-xs text-white opacity-80 uppercase font-weight-medium">Ingresos Fiscales</span>
            </div>
          </VCardText>
          <div class="card-wave"></div>
        </VCard>
      </VCol>

      <!-- Card Monto con Deducciones -->
      <VCol cols="12" md="4">
        <VCard class="rounded-xl border-0 premium-summary-card bg-primary-gradient overflow-hidden h-100 shadow-md">
          <VCardText class="pa-6 d-flex flex-column align-center text-center">
            <VAvatar color="white" variant="tonal" size="44" class="mb-3 rounded-lg">
              <VIcon icon="tabler-receipt-2" size="24" color="white" />
            </VAvatar>
            <span class="text-xs font-weight-bold uppercase text-white opacity-70 mb-1">Base Imponible</span>
            <div class="text-h4 font-weight-black text-white mb-2 leading-none">
              <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(montoConDeducciones) }}
            </div>
            <div class="mt-auto d-flex flex-column align-center gap-1 pt-2">
              <span class="text-super-xs text-white opacity-90 font-weight-black uppercase">Post Deducciones</span>
              <VChip v-if="deducciones > 0" size="super-xs" color="white" variant="tonal" class="rounded font-weight-black">
                -{{ formatCurrency(deducciones) }}
              </VChip>
            </div>
          </VCardText>
          <div class="card-wave"></div>
        </VCard>
      </VCol>

      <!-- Card Impuesto Estimado -->
      <VCol cols="12" md="4">
        <VCard class="rounded-xl border-0 premium-summary-card bg-warning-gradient overflow-hidden h-100 shadow-md pulse-hover">
          <VCardText class="pa-6 d-flex flex-column align-center text-center">
            <VAvatar color="white" variant="tonal" size="44" class="mb-3 rounded-lg shadow-sm">
              <VIcon icon="tabler-calculator-tax" size="24" color="white" />
            </VAvatar>
            <span class="text-xs font-weight-bold uppercase text-white opacity-70 mb-1">Impuesto a Pagar</span>
            <div class="text-h4 font-weight-black text-white mb-1 leading-none">
              {{ impuestoISLR.toFixed(2) }} <span class="text-xs font-weight-bold">U.T.</span>
            </div>
            <div class="text-sm font-weight-black text-white mb-2 opacity-90">
              Bs. {{ formatCurrency(impuestoISLREnBolivares) }}
            </div>
            <div class="mt-auto d-flex flex-column align-center gap-1">
              <VChip size="x-small" color="white" variant="flat" class="font-weight-black rounded">
                {{ tramoISLR.tasa }}% DE TASA
              </VChip>
              <span class="text-super-xs text-white opacity-80 uppercase font-weight-medium font-italic">Tramo: {{ tramoISLR.tramo }}</span>
            </div>
          </VCardText>
          <div class="card-wave pulse"></div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Detalle Financiero Consolidado -->
    <VCard class="rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
      <VCardTitle class="pa-4 px-6 d-flex align-center">
        <VAvatar color="secondary" variant="tonal" size="32" class="me-3 rounded-lg">
          <VIcon icon="tabler-report-money" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Consolidado Fiscal - Año {{ selectedYear }}</span>
      </VCardTitle>
      
      <VDivider class="opacity-10" />
      
      <VCardText class="pa-6">
        <VRow :dense="mobile">
          <VCol cols="12" md="4">
            <div class="d-flex align-center gap-4 pa-4 rounded-xl bg-surface-variant-opacity-2 border border-dashed border-disabled">
              <VAvatar color="success" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-trending-up" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled font-weight-black uppercase leading-tight">Ingreso Bruto (IBG)</span>
                <span class="text-xl font-weight-black text-success mt-1">Bs. {{ formatCurrency(islrData.ibg) }}</span>
              </div>
            </div>
          </VCol>
          
          <VCol cols="12" md="4">
            <div class="d-flex align-center gap-4 pa-4 rounded-xl bg-surface-variant-opacity-2 border border-dashed border-disabled">
              <VAvatar color="warning" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-receipt" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled font-weight-black uppercase leading-tight">Costos Declarados</span>
                <span class="text-xl font-weight-black text-warning mt-1">Bs. {{ formatCurrency(islrData.costs) }}</span>
              </div>
            </div>
          </VCol>

          <VCol cols="12" md="4">
            <div class="d-flex align-center gap-4 pa-4 rounded-xl bg-surface-variant-opacity-2 border border-dashed border-disabled">
              <VAvatar color="error" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-scissors" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled font-weight-black uppercase leading-tight">Total Deducciones</span>
                <span class="text-xl font-weight-black text-error mt-1">Bs. {{ formatCurrency(deducciones) }}</span>
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Dialog para editar Unidades Tributarias -->
    <EditUTDialog
      v-model="showEditUTDialog"
      :current-value="unidadesTributarias"
      @save="handleSaveUT"
    />
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

.bg-success-gradient {
  background: linear-gradient(135deg, #2AD577 0%, #158E4D 100%) !important;
}

.bg-primary-gradient {
  background: linear-gradient(135deg, #1E90FF 0%, #0056B3 100%) !important;
}

.bg-warning-gradient {
  background: linear-gradient(135deg, #FFB400 0%, #CC9000 100%) !important;
}

.premium-summary-card {
  position: relative;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-summary-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px -10px rgba(0,0,0,0.2) !important;
}

.card-wave {
  position: absolute;
  bottom: -30px;
  right: -30px;
  width: 140px;
  height: 140px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
  pointer-events: none;
}

.card-wave.pulse {
  animation: pulse-wave 4s infinite linear;
}

@keyframes pulse-wave {
  0% { transform: scale(1) rotate(0deg); opacity: 0.1; }
  50% { transform: scale(1.3) rotate(180deg); opacity: 0.2; }
  100% { transform: scale(1) rotate(360deg); opacity: 0.1; }
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
