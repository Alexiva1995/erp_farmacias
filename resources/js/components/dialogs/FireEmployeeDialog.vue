<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currency: { type: String, default: null },
  selectedEmployee: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "refresh-table", "close"]);

const step = ref("employee");
const settlement = ref(null);
const percentage = ref(100);
const exchangeRate = ref(1);
const showSalaryDetails = ref(false);

const errors = ref({});
const payed = ref(null);
const currency = ref(null);
const count = ref(null);

// Overrides
const hireDateOverride = ref(null);
const resignationDateOverride = ref(null);
const baseSalaryOverride = ref(0);
const vacationDeductionOverride = ref(null);
const vacationBonusDeductionOverride = ref(null);
const earningsDeductionOverride = ref(null);

const additionalDeductions = ref([]); // { description: string, amount: number }
const newDeduction = ref({ description: "", amount: 0 });

const countsFilterByCurrency = {
  USD: ["Efectivo", "Binance", "Paypal"],
  COP: ["Efectivo", "Transferencia"],
  BS: ["Efectivo", "Tarjeta", "Pago móvil", "Transferencia"],
};

const fetchSettlement = async () => {
  if (!props.selectedEmployee?.id) return;
  if (!props.modelValue) return;

  try {
    const params = {
      hire_date: hireDateOverride.value,
      resignation_date: resignationDateOverride.value,
      base_salary_usd: baseSalaryOverride.value,
      additional_deductions_usd: totalAdditionalDeductions.value,
      vacation_deduction_bs: vacationDeductionOverride.value,
      vacation_bonus_deduction_bs: vacationBonusDeductionOverride.value,
      earnings_deduction_bs: earningsDeductionOverride.value,
    };

    const { data } = await axios.get(
      `/rrhh/social-benefits/employees/${props.selectedEmployee.id}/settlement-data`,
      { params }
    );
    settlement.value = data.data;

    if (
      settlement.value.base_salary === 0 &&
      settlement.value.average_salary === 0
    ) {
      toast.warning(
        "Al empleado no se le han asignado salarios, no se puede procesar la liquidación"
      );
    }
  } catch {
    toast.error("No se pudo cargar la información de liquidación");
  }
};

const fetchExchangeRate = async () => {
  try {
    const { data } = await axios.get("/finances/exchange-rates/consultOneBCV");

    exchangeRate.value = data.rate;
  } catch (error) {
    toast.error("No se pudo obtener la tasa del día");
  }
};

watch(() => props.selectedEmployee, fetchSettlement, { immediate: true });

watch(
  () => props.selectedEmployee,
  () => {
    if (props.selectedEmployee) {
      fetchExchangeRate();
      // Inicializar overrides con valores actuales
      const resignation = props.selectedEmployee.resignation;
      
      // Fecha de Ingreso: Prioridad en renuncia > creación empleado
      const rawHireDate = resignation?.start_date || props.selectedEmployee.created_at;
      hireDateOverride.value = rawHireDate ? rawHireDate.split("T")[0] : null;

      // Fecha de Egreso: Prioridad en renuncia > hoy
      const rawResignationDate = resignation?.effective_date || new Date().toISOString();
      resignationDateOverride.value = rawResignationDate ? rawResignationDate.split("T")[0] : null;
      
      baseSalaryOverride.value = props.selectedEmployee.salario_base_usd || 0;
    }
  },
  { immediate: true }
);

const addDeduction = () => {
  if (newDeduction.value.description && newDeduction.value.amount > 0) {
    additionalDeductions.value.push({ ...newDeduction.value });
    newDeduction.value = { description: "", amount: 0 };
    fetchSettlement();
  }
};

const removeDeduction = (index) => {
  additionalDeductions.value.splice(index, 1);
  fetchSettlement();
};

const totalAdditionalDeductions = computed(() => {
  return additionalDeductions.value.reduce((acc, curr) => acc + curr.amount, 0);
});

const displayAmount = (amount) =>
  Intl.NumberFormat("es-VE", {
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
  }).format(amount);

const amountToPay = computed(() =>
  settlement.value ? settlement.value.final_usd * (percentage.value / 100) : 0
);

const submitForm = async () => {
  if (step.value === "employee") {
    step.value = "payment";
    return;
  }

  try {
    const payload = {
      percentage: percentage.value,
      total: Number(Number(amountToPay.value || 0).toFixed(2)),
      payed: Number(Number(payed.value || 0).toFixed(2)),
      count: count.value,
      currency: currency.value,
      overrides: {
        hire_date: hireDateOverride.value,
        resignation_date: resignationDateOverride.value,
        base_salary_usd: baseSalaryOverride.value,
        additional_deductions_usd: totalAdditionalDeductions.value,
        vacation_deduction_bs: vacationDeductionOverride.value,
        vacation_bonus_deduction_bs: vacationBonusDeductionOverride.value,
        earnings_deduction_bs: earningsDeductionOverride.value,
      },
    };

    const { data, headers } = await axios.post(
      `/rrhh/social-benefits/employees/${props.selectedEmployee.id}/fire`,
      payload,
      { responseType: "blob" }
    );

    // Manejar descarga del PDF
    const blob = new Blob([data], { type: "application/pdf" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `liquidacion-${props.selectedEmployee.identification}.pdf`
    );
    document.body.appendChild(link);
    link.click();
    link.remove(); window.URL.revokeObjectURL(url);

    toast.success("Empleado liquidado y documento generado con éxito");
    emit("refresh-table");
    closeDialog();
  } catch (error) {
    if (error.response?.data instanceof Blob) {
      if (error.response.data.type === "application/json") {
        const reader = new FileReader();
        reader.onload = () => {
          try {
            const errorData = JSON.parse(reader.result);
            toast.error(errorData.message || "Error en validación de datos");
            if (errorData.errors) {
              errors.value = errorData.errors;
              if (errors.value.percentage) step.value = "employee";
            }
          } catch (e) {
            toast.error("Error al procesar la respuesta del servidor");
          }
        };
        reader.readAsText(error.response.data);
      } else {
        toast.error(`Error del servidor (${error.response.status})`);
      }
    } else {
      console.error("Error en liquidación:", error);
      const message = error.response?.data?.message || error.message || "Error de conexión con el servidor";
      toast.error(message);
      if (error.response?.data?.errors) {
        errors.value = error.response.data.errors;
        if (errors.value.percentage) step.value = "employee";
      }
    }
  }
};

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("close");
};

const formatNumberWithSeparators = (val) => {
  if (val === null || val === undefined || val === '') return '';
  const parts = val.toString().split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return parts.join(',');
};

const parseFormattedNumber = (val) => {
  if (!val) return 0;
  return parseFloat(val.toString().replace(/\./g, '').replace(',', '.')) || 0;
};

const handleNumberInput = (field, value) => {
  const numericValue = parseFormattedNumber(value);
  if (field === 'vacationDeductionOverride') vacationDeductionOverride.value = numericValue;
  if (field === 'vacationBonusDeductionOverride') vacationBonusDeductionOverride.value = numericValue;
  if (field === 'earningsDeductionOverride') earningsDeductionOverride.value = numericValue;
  if (field === 'baseSalaryOverride') baseSalaryOverride.value = numericValue;
  fetchSettlement();
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return dateString;
  return date.toLocaleDateString("es-VE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
  >
    <VCard>
      <VCardTitle class="d-flex align-center py-3 bg-light-primary">
        <span class="text-h6">
          Liquidación: {{ props.selectedEmployee?.name }}
          {{ props.selectedEmployee?.last_name }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" density="compact" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4 bg-surface">
        <VTabs v-model="step" density="compact" class="mb-5 custom-tabs border-b">
          <VTab value="employee" class="text-uppercase font-weight-bold">1. Configuración y Cálculo</VTab>
          <VTab value="payment" class="text-uppercase font-weight-bold">2. Gestión de Pago</VTab>
        </VTabs>

        <VTabsWindow v-model="step">
          <VTabsWindowItem value="employee">
            <!-- Sección Superior: Configuración y Salario -->
            <VRow dense class="mb-5">
              <!-- Card 1: Configuración de Fechas y Base -->
              <VCol cols="12" md="6">
                <VCard variant="outlined" class="rounded-lg pa-2 h-100 bg-surface border">
                  <VCardText class="pa-3">
                    <div class="d-flex align-center mb-6">
                      <VIcon icon="tabler-settings-automation" color="primary" class="me-2" />
                      <span class="text-subtitle-1 font-weight-bold opacity-90">Parámetros de Cálculo</span>
                    </div>
                    <VRow dense>
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="hireDateOverride"
                          label="Fecha de Ingreso"
                          type="date"
                          density="compact"
                          variant="outlined"
                          persistent-placeholder
                          @change="fetchSettlement"
                        />
                      </VCol>
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="resignationDateOverride"
                          label="Fecha de Egreso"
                          type="date"
                          density="compact"
                          variant="outlined"
                          persistent-placeholder
                          @change="fetchSettlement"
                        />
                      </VCol>
                      <VCol cols="12" md="4">
                        <VTextField
                          :model-value="formatNumberWithSeparators(baseSalaryOverride)"
                          label="Salario Base (USD)"
                          density="compact"
                          variant="outlined"
                          prefix="$"
                          persistent-placeholder
                          @update:model-value="(val) => handleNumberInput('baseSalaryOverride', val)"
                        />
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Card 2: Salario Promedio + Integral/Diario -->
              <VCol cols="12" md="6">
                <VCard variant="flat" class="rounded-lg pa-2 h-100 bg-primary-lighten-5 border">
                  <VCardText class="pa-3 text-center d-flex flex-column justify-center align-center">
                    <div class="text-overline text-primary font-weight-bold mb-1">PROMEDIO ÚLTIMOS SALARIOS</div>
                    <div class="text-h3 font-weight-black text-primary">
                      {{ displayAmount(settlement?.average_salary ?? 0) }} <small class="text-h6">Bs</small>
                    </div>

                    <!-- Datos Restituidos: Diario e Integral -->
                    <div class="d-flex gap-4 mt-2 justify-center align-center">
                       <div class="text-center">
                         <div class="text-caption text-medium-emphasis font-weight-bold">DIARIO</div>
                         <div class="text-subtitle-2 font-weight-bold text-primary">{{ displayAmount(settlement?.daily_wage ?? 0) }} Bs</div>
                       </div>
                       <VDivider vertical class="mx-2" />
                       <div class="text-center">
                         <div class="text-caption text-medium-emphasis font-weight-bold">INTEGRAL</div>
                         <div class="text-subtitle-2 font-weight-bold text-primary">{{ displayAmount(settlement?.integral_salary ?? 0) }} Bs</div>
                       </div>
                    </div>

                    <div class="text-caption font-weight-medium mt-2">
                       Calculado sobre {{ settlement?.average_salary_count ?? 0 }} registros
                       <VBtn icon size="x-small" variant="tonal" color="primary" class="ms-1" @click="showSalaryDetails = !showSalaryDetails">
                         <VIcon>{{ showSalaryDetails ? 'tabler-eye-off' : 'tabler-eye' }}</VIcon>
                       </VBtn>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Detalles de salarios (Expandible) -->
              <VCol cols="12" v-if="showSalaryDetails">
                <VCard variant="flat" class="bg-surface-variant rounded-lg pa-3 animate__animated animate__fadeIn border">
                  <div v-if="settlement?.last_salaries?.length > 0" class="d-flex flex-wrap gap-2 justify-center">
                    <VChip v-for="(salary, index) in settlement.last_salaries" :key="index" size="small" variant="elevated" color="surface" border>
                      <span class="text-medium-emphasis me-1">{{ formatDate(salary.payslip_date) }}:</span>
                      <strong class="text-primary">{{ formatCurrency(salary.amount_bs) }} Bs</strong>
                    </VChip>
                  </div>
                  <div v-else class="text-caption text-center italic">No hay historial salarial disponible</div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Tablas de Detalle -->
            <VRow>
              <VCol cols="12" md="6">
                <VCard variant="outlined" class="rounded-lg overflow-hidden h-100 bg-surface border">
                  <div class="bg-surface pa-3 font-weight-bold d-flex align-center border-b">
                    <VIcon icon="tabler-list-check" class="me-2" size="20" color="primary" /> Devengaciones
                  </div>
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-caption font-weight-bold">Concepto</th>
                        <th class="text-center text-uppercase text-caption font-weight-bold">Días</th>
                        <th class="text-end text-uppercase text-caption font-weight-bold">Monto (Bs)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Prestaciones Sociales</td>
                        <td class="text-center">{{ settlement?.social_benefits_days ?? 0 }}</td>
                        <td class="text-end">{{ displayAmount(settlement?.social_benefits_amount ?? 0) }}</td>
                      </tr>
                      <tr>
                        <td>Vacaciones Fracc.</td>
                        <td class="text-center">{{ settlement?.vacation_voucher_days ?? 0 }}</td>
                        <td class="text-end">{{ displayAmount(settlement?.vacation_voucher_amount ?? 0) }}</td>
                      </tr>
                      <tr>
                        <td>Bono Vacacional</td>
                        <td class="text-center">{{ settlement?.vacation_bonus_voucher_days ?? 0 }}</td>
                        <td class="text-end">{{ displayAmount(settlement?.vacation_bonus_voucher_amount ?? 0) }}</td>
                      </tr>
                      <tr>
                        <td>Utilidades</td>
                        <td class="text-center">{{ settlement?.earnings_voucher_days ?? 0 }}</td>
                        <td class="text-end">{{ displayAmount(settlement?.earnings_voucher_amount ?? 0) }}</td>
                      </tr>
                      <tr class="bg-primary-lighten-5 font-weight-black">
                        <td>TOTAL DEVENGADO</td>
                        <td class="text-center">{{ settlement?.total_settlement_days ?? 0 }}</td>
                        <td class="text-end text-primary">{{ displayAmount(settlement?.total_settlement_amount ?? 0) }}</td>
                      </tr>
                    </tbody>
                  </VTable>
                </VCard>
              </VCol>

              <VCol cols="12" md="6">
                <VCard variant="outlined" class="rounded-lg overflow-hidden h-100 bg-surface border">
                  <div class="bg-surface pa-3 font-weight-bold d-flex align-center border-b">
                    <VIcon icon="tabler-minus-vertical" class="me-2" size="20" color="error" /> Deducciones
                  </div>
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-caption font-weight-bold">Concepto</th>
                        <th class="text-end text-uppercase text-caption font-weight-bold">Monto (Bs)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Deducción Vacaciones</td>
                        <td class="text-end">
                          <VTextField
                            :model-value="formatNumberWithSeparators(vacationDeductionOverride)"
                            density="compact"
                            hide-details
                            variant="solo-filled"
                            flat
                            class="text-right-input text-error font-weight-bold custom-deduction-input"
                            @update:model-value="(val) => handleNumberInput('vacationDeductionOverride', val)"
                          />
                        </td>
                      </tr>
                      <tr>
                        <td>Deducción Bono Vac.</td>
                        <td class="text-end">
                          <VTextField
                            :model-value="formatNumberWithSeparators(vacationBonusDeductionOverride)"
                            density="compact"
                            hide-details
                            variant="solo-filled"
                            flat
                            class="text-right-input text-error font-weight-bold custom-deduction-input"
                            @update:model-value="(val) => handleNumberInput('vacationBonusDeductionOverride', val)"
                          />
                        </td>
                      </tr>
                      <tr>
                        <td>Deducción Utilidades</td>
                        <td class="text-end">
                          <VTextField
                            :model-value="formatNumberWithSeparators(earningsDeductionOverride)"
                            density="compact"
                            hide-details
                            variant="solo-filled"
                            flat
                            class="text-right-input text-error font-weight-bold custom-deduction-input"
                            @update:model-value="(val) => handleNumberInput('earningsDeductionOverride', val)"
                          />
                        </td>
                      </tr>
                      <!-- Deducciones Adicionales -->
                      <tr v-for="(ded, idx) in additionalDeductions" :key="idx" class="italic">
                        <td class="d-flex align-center">
                          <VBtn icon size="x-small" variant="text" color="error" class="me-1" @click="removeDeduction(idx)">
                            <VIcon size="14">tabler-trash-x</VIcon>
                          </VBtn>
                          {{ ded.description }}
                        </td>
                        <td class="text-end text-error">- {{ displayAmount(ded.amount * exchangeRate) }}</td>
                      </tr>
                      <!-- Fila Agregar -->
                      <tr class="bg-surface-variant">
                        <td colspan="2" class="pa-1">
                          <div class="d-flex align-center gap-2">
                            <VTextField v-model="newDeduction.description" placeholder="Añadir nueva deducción..." variant="plain" density="compact" hide-details class="text-caption px-2" />
                            <VTextField v-model="newDeduction.amount" type="number" placeholder="0,00" variant="plain" density="compact" hide-details class="text-caption font-weight-bold px-2" style="max-inline-size: 80px;" @keyup.enter="addDeduction" />
                            <VBtn icon="tabler-plus" size="x-small" color="primary" variant="elevated" @click="addDeduction" />
                          </div>
                        </td>
                      </tr>
                      <tr class="bg-error-lighten-5 font-weight-black">
                        <td>TOTAL DEDUCCIONES</td>
                        <td class="text-end text-error">{{ displayAmount((settlement?.total_deductions ?? 0) + (totalAdditionalDeductions * exchangeRate)) }}</td>
                      </tr>
                    </tbody>
                  </VTable>
                </VCard>
              </VCol>
            </VRow>

            <!-- Resumen Inferior Hero -->
            <VCard variant="flat" class="mt-6 border-primary border-t-2 rounded-lg bg-primary-lighten-5 animate__animated animate__slideInUp border">
              <VCardText class="pa-4">
                <VRow align="center">
                  <VCol cols="12" md="3">
                    <div class="d-flex flex-column">
                      <span class="text-caption text-primary font-weight-bold text-uppercase">Tasa del Día</span>
                      <div class="text-h6 font-weight-bold">1 USD = {{ displayAmount(exchangeRate) }} <span class="text-caption">Bs</span></div>
                    </div>
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model="percentage"
                      label="% A Liquidar"
                      type="number"
                      density="compact"
                      variant="outlined"
                      suffix="%"
                      bg-color="surface"
                      hide-details
                    />
                  </VCol>
                  <VCol cols="12" md="6" class="text-end">
                    <div class="d-flex flex-column align-end">
                      <span class="text-subtitle-2 text-primary font-weight-bold mb-n1">TOTAL NETO A PAGAR</span>
                      <div class="text-h2 font-weight-black text-primary d-flex align-end gap-1">
                        {{ displayAmount(amountToPay) }} <span class="text-h4 mb-1">USD</span>
                      </div>
                      <div class="text-subtitle-2 text-medium-emphasis font-weight-medium">
                        ≈ {{ displayAmount(amountToPay * exchangeRate) }} <span class="text-caption">Bs.S</span>
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VTabsWindowItem>

          <VTabsWindowItem value="payment">
            <div class="max-600 mx-auto py-4">
              <VCard variant="outlined" class="rounded-lg bg-surface-variant mb-6 border">
                <VCardText class="pa-6 text-center">
                   <div class="text-overline text-medium-emphasis">Resumen de Liquidación</div>
                   <div class="text-h3 font-weight-black text-primary my-2">{{ displayAmount(amountToPay) }} <small class="text-h6">USD</small></div>
                   <div class="text-subtitle-1 font-weight-bold">Equivalente a {{ displayAmount(amountToPay * exchangeRate) }} Bs</div>
                </VCardText>
              </VCard>

              <VRow dense>
                <VCol cols="12" md="6">
                  <VSelect v-model="currency" label="Método / Moneda" variant="outlined" :items="Object.keys(countsFilterByCurrency).map(c => ({ title: c, value: c }))" :error-messages="errors.currency" />
                </VCol>
                <VCol cols="12" md="6">
                  <VSelect v-model="count" label="Tipo de Pago" variant="outlined" :items="(countsFilterByCurrency[currency] ?? []).map(a => ({ title: a, value: a }))" :error-messages="errors.count" :disabled="!currency" />
                </VCol>
                <VCol cols="12">
                  <VTextField v-model="payed" label="Monto Confirmado (USD)" type="number" variant="outlined" prefix="$" :step="0.01" :error-messages="errors.payed" class="text-h6" />
                </VCol>
              </VRow>
            </div>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-surface d-flex gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          size="large"
          class="flex-grow-1 font-weight-bold"
          @click="closeDialog"
        >
          Cerrar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          elevation="2"
          size="large"
          class="flex-grow-1 font-weight-bold"
          @click="submitForm"
          :disabled="!settlement"
        >
          {{ step === "employee" ? "Ir al Pago" : "Finalizar" }}
          <VIcon end icon="tabler-chevron-right" />
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-right-input :deep(input) {
  text-align: end !important;
}

.custom-deduction-input :deep(.v-field__input) {
  min-block-size: 32px !important;
  padding-block: 4px !important;
}

.custom-tabs :deep(.v-tab--selected) {
  color: rgb(var(--v-theme-primary)) !important;
}

.animate__animated {
  animation-duration: 0.5s;
}

.max-600 {
  max-inline-size: 600px;
}

/* Compatibilidad con Temas */
.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.white-input :deep(.v-field) {
  background-color: var(--v-theme-surface) !important;
}

.bg-surface-variant {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.border {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12) !important;
}

.border-b {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.12) !important;
}
</style>
