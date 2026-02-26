<template>
  <div>
    <!-- Stats Cards Row -->
    <VRow class="mb-6 match-height">
      <!-- Card 1: Utilidad Gravable Estimada -->
      <VCol cols="12" md="4">
        <VCard :loading="loading" class="h-100">
          <VCardText>
            <div class="d-flex align-center mb-2">
              <VAvatar color="purple-lighten-5" size="40" class="mr-3">
                <VIcon icon="tabler-currency-dollar" color="purple" size="20" />
              </VAvatar>
              <span class="text-h5 font-weight-semibold">{{
                formatCurrency(rentaBruta)
              }}</span>
            </div>
            <div class="text-body-2 text-medium-emphasis mb-1">
              Utilidad Gravable Estimada
            </div>
            <div class="d-flex align-center text-caption">
              <span class="text-success font-weight-medium mr-1"
                >Renta Bruta</span
              >
              <span class="text-medium-emphasis">año {{ selectedYear }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Card 2: ISLR a Pagar Estimado -->
      <VCol cols="12" md="4">
        <VCard :loading="loading" class="h-100">
          <VCardText>
            <div class="d-flex align-center mb-2">
              <VAvatar color="orange-lighten-5" size="40" class="mr-3">
                <VIcon icon="tabler-file-invoice" color="orange" size="20" />
              </VAvatar>
              <span class="text-h5 font-weight-semibold">{{
                formatCurrency(impuestoISLR)
              }}</span>
            </div>
            <div class="text-body-2 text-medium-emphasis mb-1">
              ISLR a Pagar Estimado
            </div>
            <div class="d-flex align-center text-caption">
              <span class="text-warning font-weight-medium mr-1"
                >{{ tramoISLR.tasa }}%</span
              >
              <span class="text-medium-emphasis">{{ tramoISLR.tramo }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Card 3: Estado Última Declaración -->
      <VCol cols="12" md="4">
        <VCard :loading="loadingDeclaration" class="h-100">
          <VCardText class="d-flex flex-column" style="min-height: 160px">
            <template v-if="latestDeclaration">
              <div class="d-flex align-center mb-2">
                <VAvatar
                  :color="
                    latestDeclaration.status === 'paid'
                      ? 'success-lighten-5'
                      : 'warning-lighten-5'
                  "
                  size="40"
                  class="mr-3"
                >
                  <VIcon
                    :icon="
                      latestDeclaration.status === 'paid'
                        ? 'tabler-circle-check'
                        : 'tabler-clock'
                    "
                    :color="
                      latestDeclaration.status === 'paid'
                        ? 'success'
                        : 'warning'
                    "
                    size="20"
                  />
                </VAvatar>
                <span class="text-h5 font-weight-semibold">
                  {{ latestDeclaration.status_text }}
                </span>
              </div>
              <div class="text-body-2 text-medium-emphasis mb-1">
                Estado Última Declaración
              </div>
              <div class="d-flex align-center text-caption">
                <span class="text-medium-emphasis mr-1">Declarada el</span>
                <span class="text-disabled">{{
                  formatDate(latestDeclaration.declaration_date)
                }}</span>
              </div>
              <div class="d-flex align-center text-caption mt-1">
                <span class="text-medium-emphasis mr-1">Monto:</span>
                <span class="font-weight-bold">{{
                  formatCurrency(latestDeclaration.amount)
                }}</span>
              </div>
            </template>

            <template v-else>
              <div
                class="d-flex flex-column align-center justify-center flex-grow-1"
              >
                <VIcon
                  icon="tabler-file-x"
                  size="40"
                  color="error"
                  class="mb-2"
                />
                <div class="text-body-2 text-medium-emphasis mb-3 text-center">
                  No hay declaración para {{ selectedYear }}
                </div>
                <VBtn
                  color="primary"
                  size="small"
                  prepend-icon="tabler-plus"
                  @click="openCreateDeclarationDialog"
                >
                  Crear Declaración
                </VBtn>
              </div>
            </template>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Revenue Report -->
    <VRow class="mb-6">
      <VCol cols="12">
        <EcommerceRevenueReport />
      </VCol>
    </VRow>

    <!-- Nueva sección: Cálculo de Utilidades -->
    <VRow class="mb-6 match-height">
      <!-- Ingresos Totales -->
      <VCol cols="12" md="6">
        <VCard class="h-100" :loading="loading">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h6 class="text-h6 font-weight-medium">Ingresos Totales</h6>
              <VIcon icon="tabler-trending-up" color="success" />
            </div>

            <div class="mb-6">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-body-1">Total Acumulado</span>
                <span class="text-h4 font-weight-bold text-success">
                  {{ formatCurrency(totalIncomeData?.total_income || 0) }}
                </span>
              </div>
            </div>

            <VDivider class="my-4" />

            <!-- Desglose de Ingresos -->
            <div class="mb-4">
              <div class="d-flex justify-space-between align-center mb-3">
                <div class="d-flex align-center">
                  <VIcon
                    icon="tabler-checkbox-circle"
                    color="success"
                    size="20"
                    class="mr-2"
                  />
                  <span class="text-body-2">Ventas Gravadas</span>
                </div>
                <span class="text-body-1 font-weight-medium">
                  {{ formatCurrency(totalIncomeData?.taxable_amount || 0) }}
                </span>
              </div>

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex align-center">
                  <VIcon
                    icon="tabler-circle-check"
                    color="info"
                    size="20"
                    class="mr-2"
                  />
                  <span class="text-body-2">Ventas Exentas</span>
                </div>
                <span class="text-body-1 font-weight-medium">
                  {{ formatCurrency(totalIncomeData?.exempt_amount || 0) }}
                </span>
              </div>
            </div>

            <VProgressLinear
              :model-value="totalIncomeData?.taxable_percentage || 0"
              color="success"
              height="8"
              rounded
              class="mb-2"
            />
            <div class="text-caption text-medium-emphasis">
              {{ (totalIncomeData?.taxable_percentage || 0).toFixed(0) }}%
              Gravadas |
              {{ (totalIncomeData?.exempt_percentage || 0).toFixed(0) }}%
              Exentas
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Gastos Deducibles -->
      <VCol cols="12" md="6">
        <VCard class="h-100" :loading="loading">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h6 class="text-h6 font-weight-medium">Gastos Deducibles</h6>
              <VIcon icon="tabler-receipt" color="warning" />
            </div>

            <div class="mb-6">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-body-1">Total Deducible</span>
                <span class="text-h4 font-weight-bold text-warning">
                  {{
                    formatCurrency(
                      deductibleExpensesData?.total_deductible || 0,
                    )
                  }}
                </span>
              </div>
            </div>

            <VDivider class="my-4" />

            <!-- Lista de Gastos Deducibles -->
            <VList density="compact" class="pa-0">
              <VListItem
                v-for="category in deductibleExpensesData?.categories || []"
                :key="category.category_id"
                class="px-0 mb-2"
              >
                <VListItemTitle class="text-body-2">
                  {{ category.category_name }}
                </VListItemTitle>
                <template #append>
                  <span class="text-body-1 font-weight-medium">
                    {{ formatCurrency(category.total_amount) }}
                  </span>
                </template>
              </VListItem>

              <VListItem
                v-if="!deductibleExpensesData?.categories?.length"
                class="px-0"
              >
                <VListItemTitle
                  class="text-body-2 text-center text-medium-emphasis"
                >
                  No hay gastos deducibles para {{ selectedYear }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Estado de Resultados y Gastos No Deducibles -->
    <VRow class="mb-6">
      <!-- Estado de Resultados -->
      <VCol cols="12" md="8" order="2" order-md="1">
        <VCard class="h-100" :loading="loading">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-6">
              <h6 class="text-h6 font-weight-medium">Estado de Resultados</h6>
              <VChip color="primary" variant="tonal">
                <VIcon icon="tabler-calculator" size="16" class="mr-1" />
                Año {{ selectedYear }}
              </VChip>
            </div>

            <!-- Cálculo de Ingresos y Gastos -->
            <VRow>
              <VCol cols="12" sm="6">
                <VCard variant="outlined" class="mb-4">
                  <VCardText>
                    <div class="text-center">
                      <div class="text-body-2 text-medium-emphasis mb-2">
                        Ingresos Totales
                      </div>
                      <div class="text-h4 font-weight-bold text-success mb-2">
                        {{ formatCurrency(revenueStats?.total_income || 0) }}
                      </div>
                      <VChip size="small" color="success" variant="tonal">
                        <VIcon
                          icon="tabler-trending-up"
                          size="14"
                          class="mr-1"
                        />
                        Fiscal History
                      </VChip>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>

              <VCol cols="12" sm="6">
                <VCard variant="outlined" class="mb-4">
                  <VCardText>
                    <div class="text-center">
                      <div class="text-body-2 text-medium-emphasis mb-2">
                        Gastos Totales
                      </div>
                      <div class="text-h4 font-weight-bold text-warning mb-2">
                        {{ formatCurrency(revenueStats?.total_expenses || 0) }}
                      </div>
                      <VChip size="small" color="warning" variant="tonal">
                        <VIcon icon="tabler-receipt" size="14" class="mr-1" />
                        Expenses
                      </VChip>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>

            <!-- Acciones -->
            <div class="d-flex gap-3 mt-4">
              <VBtn
                color="primary"
                variant="tonal"
                prepend-icon="tabler-file-download"
              >
                Descargar Reporte
              </VBtn>
              <VBtn
                color="secondary"
                variant="outlined"
                prepend-icon="tabler-printer"
              >
                Imprimir
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Gastos No Deducibles -->
      <VCol cols="12" md="4" order="1" order-md="2">
        <VCard
          color="error-lighten-5"
          variant="tonal"
          class="h-100"
          :loading="loading"
        >
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h6 class="text-h6 font-weight-medium">Gastos No Deducibles</h6>
              <VIcon icon="tabler-alert-circle" color="error" />
            </div>

            <div class="mb-4">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-body-1">Total No Deducible</span>
                <span class="text-h5 font-weight-bold text-error">
                  {{
                    formatCurrency(
                      nonDeductibleExpensesData?.total_non_deductible || 0,
                    )
                  }}
                </span>
              </div>
            </div>

            <VDivider class="my-4" />

            <VList density="compact" class="pa-0 bg-transparent">
              <VListItem
                v-for="category in nonDeductibleExpensesData?.categories || []"
                :key="category.category_id"
                class="px-0 mb-2"
              >
                <VListItemTitle class="text-body-2">
                  {{ category.category_name }}
                </VListItemTitle>
                <template #append>
                  <span class="text-body-2 font-weight-medium">
                    {{ formatCurrency(category.total_amount) }}
                  </span>
                </template>
              </VListItem>

              <VListItem
                v-if="!nonDeductibleExpensesData?.categories?.length"
                class="px-0"
              >
                <VListItemTitle
                  class="text-body-2 text-center text-medium-emphasis"
                >
                  No hay gastos no deducibles para {{ selectedYear }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Dialog para crear declaración -->
    <VDialog v-model="showCreateDialog" max-width="600">
      <VCard>
        <VCardTitle class="d-flex align-center bg-primary text-white">
          <VIcon icon="tabler-file-plus" class="mr-2" />
          Crear Nueva Declaración ISLR
        </VCardTitle>

        <VCardText class="pt-6">
          <VForm ref="formRef" @submit.prevent="createDeclaration">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="declarationForm.year"
                  label="Año"
                  type="number"
                  variant="outlined"
                  :rules="[(v) => !!v || 'El año es requerido']"
                  readonly
                  disabled
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="declarationForm.amount"
                  label="Monto a Pagar"
                  type="number"
                  variant="outlined"
                  prefix="Bs."
                  :rules="[
                    (v) => !!v || 'El monto es requerido',
                    (v) => v >= 0 || 'El monto debe ser mayor o igual a 0',
                  ]"
                />
              </VCol>

              <VCol cols="12">
                <VSelect
                  v-model="declarationForm.status"
                  label="Estado"
                  :items="[
                    { title: 'Pagado', value: 'paid' },
                    { title: 'No Pagado', value: 'unpaid' },
                  ]"
                  variant="outlined"
                  :rules="[(v) => !!v || 'El estado es requerido']"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="declarationForm.declaration_date"
                  label="Fecha de Declaración"
                  type="date"
                  variant="outlined"
                  :rules="[(v) => !!v || 'La fecha es requerida']"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="px-6 pb-6">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="outlined"
            @click="showCreateDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="savingDeclaration"
            @click="createDeclaration"
          >
            Crear Declaración
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import EcommerceRevenueReport from "@/components/EcommerceRevenueReport.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";

const loading = ref(false);
const loadingDeclaration = ref(false);
const savingDeclaration = ref(false);
const showCreateDialog = ref(false);
const formRef = ref(null);

const islrData = ref({
  gross_income: 0,
  deductions: 0,
  net_income: 0,
  ibg: 0,
  costs: 0,
  year: new Date().getFullYear(),
});

const latestDeclaration = ref(null);
const selectedYear = ref(new Date().getFullYear());
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

const declarationForm = ref({
  year: new Date().getFullYear(),
  amount: 0,
  status: "unpaid",
  declaration_date: new Date().toISOString().split("T")[0],
});

const rentaBruta = computed(() => islrData.value.gross_income || 0);
const deducciones = computed(() => islrData.value.deductions || 0);
const montoConDeducciones = computed(() => islrData.value.net_income || 0);

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

  return impuesto * unidadesTributarias.value;
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

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "VES",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
};

const fetchIslrData = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/islr/summary", {
      params: { year: selectedYear.value },
    });
    islrData.value = data.data || {
      gross_income: 0,
      deductions: 0,
      net_income: 0,
      ibg: 0,
      costs: 0,
      year: selectedYear.value,
    };

    await fetchTaxUnit();
  } catch (error) {
    console.error("Error al cargar datos del ISLR:", error);
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

const fetchTotalIncome = async () => {
  try {
    const { data } = await axios.get("/dashboard/total-income", {
      params: { year: selectedYear.value },
    });
    totalIncomeData.value = data.data || {
      total_income: 0,
      exempt_amount: 0,
      taxable_amount: 0,
      exempt_percentage: 0,
      taxable_percentage: 0,
    };
  } catch (error) {
    console.error("Error al cargar ingresos totales:", error);
  }
};

const fetchDeductibleExpenses = async () => {
  try {
    const { data } = await axios.get("/dashboard/deductible-expenses", {
      params: { year: selectedYear.value },
    });
    deductibleExpensesData.value = data.data || {
      total_deductible: 0,
      categories: [],
    };
  } catch (error) {
    console.error("Error al cargar gastos deducibles:", error);
  }
};

const fetchRevenueStats = async () => {
  try {
    const { data } = await axios.get("/dashboard/revenue-report", {
      params: { year: selectedYear.value },
    });
    revenueStats.value = data.data?.summary || {
      total_income: 0,
      total_expenses: 0,
      net_revenue: 0,
    };
  } catch (error) {
    console.error("Error al cargar estadísticas de ingresos:", error);
  }
};

const fetchNonDeductibleExpenses = async () => {
  try {
    const { data } = await axios.get("/dashboard/non-deductible-expenses", {
      params: { year: selectedYear.value },
    });
    nonDeductibleExpensesData.value = data.data || {
      total_non_deductible: 0,
      categories: [],
    };
  } catch (error) {
    console.error("Error al cargar gastos no deducibles:", error);
  }
};

const fetchLatestDeclaration = async () => {
  loadingDeclaration.value = true;
  try {
    const { data } = await axios.get("/islr/declarations", {
      params: { year: selectedYear.value },
    });
    latestDeclaration.value = data.data;
  } catch (error) {
    if (error.response?.status === 404) {
      latestDeclaration.value = null;
    } else {
      console.error("Error al cargar declaración:", error);
      toast.error("Error al cargar la declaración");
    }
  } finally {
    loadingDeclaration.value = false;
  }
};

const openCreateDeclarationDialog = () => {
  declarationForm.value = {
    year: selectedYear.value,
    amount: impuestoISLR.value,
    status: "unpaid",
    declaration_date: new Date().toISOString().split("T")[0],
  };
  showCreateDialog.value = true;
};

const createDeclaration = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  savingDeclaration.value = true;
  try {
    await axios.post("/islr/declarations", declarationForm.value);
    toast.success("Declaración creada exitosamente");
    showCreateDialog.value = false;
    await fetchLatestDeclaration();
  } catch (error) {
    console.error("Error al crear declaración:", error);
    toast.error(
      error.response?.data?.message || "Error al crear la declaración",
    );
  } finally {
    savingDeclaration.value = false;
  }
};

onMounted(() => {
  fetchIslrData();
  fetchLatestDeclaration();
  fetchTotalIncome();
  fetchDeductibleExpenses();
  fetchRevenueStats();
  fetchNonDeductibleExpenses();
});
</script>

<style scoped>
.match-height .v-col {
  display: flex;
}

.match-height .v-card {
  width: 100%;
}
</style>
