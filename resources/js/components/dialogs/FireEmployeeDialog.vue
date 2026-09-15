<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currency: { type: String, default: null },
  selectedEmployee: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "refresh-table", "close"]);

const { mobile } = useDisplay();

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

const formatIdentification = (val) => {
  if (!val) return "—";
  const cleaned = String(val).replace(/\D/g, "");
  if (!cleaned) return String(val);
  const withDots = cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return `V-${withDots}`;
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
      const resignation = props.selectedEmployee.resignation;
      
      const rawHireDate = resignation?.start_date || props.selectedEmployee.created_at;
      hireDateOverride.value = rawHireDate ? rawHireDate.split("T")[0] : null;

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

const displayedSettlement = computed(() => {
  if (!settlement.value) return null;
  const factor = percentage.value / 100;
  return {
    ...settlement.value,
    social_benefits_amount: settlement.value.social_benefits_amount * factor,
    vacation_voucher_amount: settlement.value.vacation_voucher_amount * factor,
    vacation_bonus_voucher_amount: settlement.value.vacation_bonus_voucher_amount * factor,
    earnings_voucher_amount: settlement.value.earnings_voucher_amount * factor,
    total_settlement_amount: settlement.value.total_settlement_amount * factor,
    total_deductions: settlement.value.total_deductions * factor,
  };
});

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

    const { data } = await axios.post(
      `/rrhh/social-benefits/employees/${props.selectedEmployee.id}/fire`,
      payload,
      { responseType: "blob" }
    );

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
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Empleado liquidado y documento generado con éxito");
    emit("refresh-table");
    closeDialog();
  } catch (error) {
    console.error("Error en liquidación:", error);
    toast.error("Error al procesar la liquidación");
  }
};

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("close");
};

const formatNumberWithSeparators = (val) => {
  if (val === null || val === undefined || val === "") return "";
  const parts = val.toString().split(".");
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return parts.join(",");
};

const parseFormattedNumber = (val) => {
  if (!val) return 0;
  return parseFloat(val.toString().replace(/\./g, "").replace(",", ".")) || 0;
};

const handleNumberInput = (field, value) => {
  const numericValue = parseFormattedNumber(value);
  if (field === "vacationDeductionOverride") vacationDeductionOverride.value = numericValue;
  if (field === "vacationBonusDeductionOverride") vacationBonusDeductionOverride.value = numericValue;
  if (field === "earningsDeductionOverride") earningsDeductionOverride.value = numericValue;
  if (field === "baseSalaryOverride") baseSalaryOverride.value = numericValue;
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
    max-width="1100px"
    persistent
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @update:model-value="closeDialog"
    @click:outside.prevent
    @keydown.esc.prevent="closeDialog"
  >
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'detail-dialog-card overflow-hidden border-0 elevation-12'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-3 elevation-2">
            <VIcon icon="tabler-file-analytics" color="primary" size="24" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Liquidación de Haberes
            </h2>
            <div class="d-flex align-center gap-2 mt-0.5">
              <span class="text-super-xs text-white opacity-75 font-weight-bold">
                {{ props.selectedEmployee?.name }} {{ props.selectedEmployee?.last_name }} • {{ formatIdentification(props.selectedEmployee?.identification) }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg ms-3"
            @click="closeDialog"
          >
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light">
        <div class="pa-0 bg-white border-b overflow-x-auto no-scrollbar">
          <VTabs v-model="step" class="premium-tabs px-4" hide-slider>
            <VTab value="employee" class="rounded-lg me-2 transition-all">
              <VIcon icon="tabler-calculator" class="me-2" size="18" />
              1. Configuración y Cálculo
            </VTab>
            <VTab value="payment" class="rounded-lg transition-all" :disabled="!settlement">
              <VIcon icon="tabler-wallet" class="me-2" size="18" />
              2. Gestión de Pago
            </VTab>
          </VTabs>
        </div>

        <VTabsWindow v-model="step" class="pa-3 pa-md-4">
          <VTabsWindowItem value="employee">
            <VRow dense class="ma-0">
              <!-- Sección: Parámetros -->
              <VCol cols="12" lg="8" class="pa-1">
                <div class="bg-white pa-3.5 rounded-lg border mb-3 shadow-xs">
                  <div class="d-flex align-center justify-space-between mb-2.5">
                    <div class="d-flex align-center gap-2">
                      <div class="header-indicator primary shadow-sm"></div>
                      <span class="text-caption font-weight-bold text-high-emphasis">Parámetros de Cálculo</span>
                    </div>
                    <VChip size="x-small" color="primary" variant="tonal" class="rounded font-weight-bold">Configuración</VChip>
                  </div>
                  <VRow dense>
                    <VCol cols="12" md="4">
                      <AppDateTimePicker
                        v-model="hireDateOverride"
                        label="Fecha de Ingreso"
                        placeholder="Seleccionar fecha"
                        density="compact"
                        hide-details="auto"
                        @update:model-value="fetchSettlement"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppDateTimePicker
                        v-model="resignationDateOverride"
                        label="Fecha de Egreso"
                        placeholder="Seleccionar fecha"
                        density="compact"
                        hide-details="auto"
                        @update:model-value="fetchSettlement"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppTextField
                        :model-value="formatNumberWithSeparators(baseSalaryOverride)"
                        label="Sueldo Base (USD)"
                        placeholder="0.00"
                        density="compact"
                        hide-details="auto"
                        prefix="$"
                        @update:model-value="(val) => handleNumberInput('baseSalaryOverride', val)"
                      />
                    </VCol>
                  </VRow>
                </div>

                <!-- Tablas de Detalle -->
                <VRow dense>
                  <!-- Devengaciones -->
                  <VCol cols="12" md="6" class="pa-1">
                    <div class="bg-white rounded-lg border overflow-hidden shadow-xs h-100 d-flex flex-column">
                      <div class="pa-2.5 bg-light border-b d-flex align-center gap-2">
                        <div class="header-indicator primary shadow-sm"></div>
                        <span class="text-caption font-weight-bold text-high-emphasis">Devengaciones</span>
                      </div>
                      <VTable density="compact" class="premium-micro-table flex-grow-1">
                        <thead>
                          <tr>
                            <th class="text-left text-super-xs font-weight-bold text-medium-emphasis uppercase py-2">Concepto</th>
                            <th class="text-center text-super-xs font-weight-bold text-medium-emphasis uppercase py-2">Días</th>
                            <th class="text-right text-super-xs font-weight-bold text-medium-emphasis uppercase py-2">Monto (Bs)</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td class="text-xs font-weight-medium">Antigüedad / Prestaciones</td>
                            <td class="text-center font-weight-bold">{{ displayedSettlement?.social_benefits_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold">{{ displayAmount(displayedSettlement?.social_benefits_amount ?? 0) }}</td>
                          </tr>
                          <tr>
                            <td class="text-xs font-weight-medium">Vacaciones Fracc.</td>
                            <td class="text-center font-weight-bold">{{ displayedSettlement?.vacation_voucher_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold">{{ displayAmount(displayedSettlement?.vacation_voucher_amount ?? 0) }}</td>
                          </tr>
                          <tr>
                            <td class="text-xs font-weight-medium">Bono Vacacional</td>
                            <td class="text-center font-weight-bold">{{ displayedSettlement?.vacation_bonus_voucher_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold">{{ displayAmount(displayedSettlement?.vacation_bonus_voucher_amount ?? 0) }}</td>
                          </tr>
                          <tr>
                            <td class="text-xs font-weight-medium">Utilidades</td>
                            <td class="text-center font-weight-bold">{{ displayedSettlement?.earnings_voucher_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold">{{ displayAmount(displayedSettlement?.earnings_voucher_amount ?? 0) }}</td>
                          </tr>
                          <tr class="bg-success-lighten-5">
                            <td colspan="2" class="text-xs font-weight-bold text-success">Subtotal Devengado</td>
                            <td class="text-right text-success font-weight-bold">{{ displayAmount(displayedSettlement?.total_settlement_amount ?? 0) }}</td>
                          </tr>
                        </tbody>
                      </VTable>
                    </div>
                  </VCol>

                  <!-- Deducciones -->
                  <VCol cols="12" md="6" class="pa-1">
                    <div class="bg-white rounded-lg border overflow-hidden shadow-xs h-100 d-flex flex-column">
                      <div class="pa-2.5 bg-light border-b d-flex align-center gap-2">
                        <div class="header-indicator secondary shadow-sm"></div>
                        <span class="text-caption font-weight-bold text-high-emphasis">Deducciones</span>
                      </div>
                      <VTable density="compact" class="premium-micro-table flex-grow-1">
                        <thead>
                          <tr>
                            <th class="text-left text-super-xs font-weight-bold text-medium-emphasis uppercase py-2">Concepto</th>
                            <th class="text-right text-super-xs font-weight-bold text-medium-emphasis uppercase py-2">Monto (Bs)</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td class="text-xs font-weight-medium">Deducción Vacaciones</td>
                            <td class="text-right pa-1">
                              <input
                                :value="formatNumberWithSeparators(vacationDeductionOverride)"
                                class="micro-input text-end font-weight-bold text-error"
                                @input="(e) => handleNumberInput('vacationDeductionOverride', e.target.value)"
                              />
                            </td>
                          </tr>
                          <tr>
                            <td class="text-xs font-weight-medium">Ded. Bono Vacacional</td>
                            <td class="text-right pa-1">
                              <input
                                :value="formatNumberWithSeparators(vacationBonusDeductionOverride)"
                                class="micro-input text-end font-weight-bold text-error"
                                @input="(e) => handleNumberInput('vacationBonusDeductionOverride', e.target.value)"
                              />
                            </td>
                          </tr>
                          <tr>
                            <td class="text-xs font-weight-medium">Deducción Utilidades</td>
                            <td class="text-right pa-1">
                              <input
                                :value="formatNumberWithSeparators(earningsDeductionOverride)"
                                class="micro-input text-end font-weight-bold text-error"
                                @input="(e) => handleNumberInput('earningsDeductionOverride', e.target.value)"
                              />
                            </td>
                          </tr>
                          <!-- Deducciones Adicionales -->
                          <tr v-for="(ded, idx) in additionalDeductions" :key="idx" class="bg-error-lighten-5 border-dashed-t">
                            <td class="text-super-xs font-weight-bold d-flex align-center py-1">
                              <VBtn icon="tabler-trash-x" size="16" variant="text" color="error" class="me-1" @click="removeDeduction(idx)" />
                              {{ ded.description.toUpperCase() }}
                            </td>
                            <td class="text-right text-super-xs font-weight-bold text-error py-1">- {{ displayAmount(ded.amount * exchangeRate) }}</td>
                          </tr>
                          <tr class="bg-light">
                            <td colspan="2" class="pa-1">
                              <div class="d-flex align-center gap-1 bg-white rounded border pa-1">
                                <input v-model="newDeduction.description" placeholder="+ OTRA DEDUCCIÓN" class="micro-input grow flex-grow-1" />
                                <VDivider vertical class="mx-1" />
                                <input v-model="newDeduction.amount" type="number" placeholder="0.00" class="micro-input w-50 font-weight-bold" style="max-inline-size: 60px" @keyup.enter="addDeduction" />
                                <VBtn icon="tabler-plus" size="18" color="primary" variant="tonal" class="rounded" @click="addDeduction" />
                              </div>
                            </td>
                          </tr>
                          <tr class="bg-error-lighten-5">
                            <td class="text-xs font-weight-bold text-error">Subtotal Deducido</td>
                            <td class="text-right text-error font-weight-bold">{{ displayAmount((displayedSettlement?.total_deductions ?? 0) + (totalAdditionalDeductions * exchangeRate)) }}</td>
                          </tr>
                        </tbody>
                      </VTable>
                    </div>
                  </VCol>
                </VRow>
              </VCol>

              <!-- Sección Derecha: Resumen -->
              <VCol cols="12" lg="4" class="pa-1">
                <div class="d-flex flex-column gap-3 h-100">
                  <VCard variant="flat" class="rounded-lg border bg-primary text-white shadow-sm pa-3">
                    <div class="d-flex justify-space-between align-center mb-0">
                      <span class="text-super-xs font-weight-bold opacity-75 uppercase">Promedio Últimos Sueldos</span>
                      <VBtn icon size="x-small" variant="tonal" color="white" @click="showSalaryDetails = !showSalaryDetails">
                        <VIcon size="14">{{ showSalaryDetails ? 'tabler-eye-off' : 'tabler-eye' }}</VIcon>
                      </VBtn>
                    </div>
                    <div class="text-h4 font-weight-black shadow-text leading-none mb-1">
                      {{ displayAmount(settlement?.average_salary ?? 0) }} <small class="text-xs opacity-75">Bs</small>
                    </div>
                    <div class="d-flex gap-4 mt-3">
                       <div class="d-flex flex-column flex-grow-1 border-r border-white border-opacity-20 pe-2">
                         <span class="text-super-xs font-weight-bold opacity-60 uppercase">Día / Social</span>
                         <span class="text-xs font-weight-bold">{{ displayAmount(settlement?.daily_wage ?? 0) }} Bs</span>
                       </div>
                       <div class="d-flex flex-column flex-grow-1">
                         <span class="text-super-xs font-weight-bold opacity-60 uppercase">Día / Integral</span>
                         <span class="text-xs font-weight-bold">{{ displayAmount(settlement?.integral_salary ?? 0) }} Bs</span>
                       </div>
                    </div>
                  </VCard>

                  <VExpandTransition>
                    <div v-show="showSalaryDetails">
                      <VCard variant="flat" class="rounded-lg border border-dashed pa-3 bg-white">
                        <span class="text-super-xs font-weight-bold text-primary uppercase d-block mb-2">Historial Reciente</span>
                        <div v-if="settlement?.last_salaries?.length > 0" class="d-flex flex-column gap-1">
                          <div v-for="(salary, index) in settlement.last_salaries" :key="index" class="d-flex justify-space-between align-center text-super-xs pa-1 border-b last:border-0">
                            <span class="font-weight-medium opacity-60">{{ formatDate(salary.payslip_date) }}</span>
                            <span class="font-weight-bold text-high-emphasis">{{ formatCurrency(salary.amount_bs) }} Bs</span>
                          </div>
                        </div>
                        <div v-else class="text-super-xs text-center text-disabled italic">SIN HISTORIAL</div>
                      </VCard>
                    </div>
                  </VExpandTransition>

                  <VCard variant="flat" class="rounded-lg border bg-primary-lighten-5 pa-3 flex-grow-1 d-flex flex-column justify-center shadow-xs">
                    <div class="text-center">
                       <span class="text-super-xs font-weight-bold text-primary uppercase letter-spacing-1 d-block mb-1">Tasa BCV del Día</span>
                       <div class="text-subtitle-2 font-weight-black text-primary mb-1">1 USD = {{ displayAmount(exchangeRate) }} Bs</div>
                       
                       <VDivider class="border-dashed my-2" />

                       <VSlider
                         v-model="percentage"
                         :min="1"
                         :max="100"
                         :step="1"
                         color="primary"
                         density="compact"
                         hide-details
                         class="mb-1"
                       />
                       <div class="d-flex justify-space-between align-center mb-3">
                         <span class="text-super-xs font-weight-bold text-primary uppercase">% A Liquidar</span>
                         <span class="text-subtitle-1 font-weight-black text-primary">{{ percentage }}%</span>
                       </div>

                       <div class="text-overline font-weight-bold text-primary leading-none mb-1 opacity-70">Total Neto a Pagar</div>
                       <div class="text-h4 font-weight-black text-primary leading-none mb-1 tabular-nums">
                         {{ displayAmount(amountToPay) }}
                       </div>
                       <div class="text-caption font-weight-bold text-medium-emphasis mb-0 mt-n1">
                         USD <VIcon icon="tabler-currency-dollar" size="16" />
                       </div>
                       <div class="text-super-xs font-weight-bold text-disabled uppercase mt-1">
                         ≈ {{ displayAmount(amountToPay * exchangeRate) }} Bs.S
                       </div>
                    </div>
                  </VCard>
                </div>
              </VCol>
            </VRow>
          </VTabsWindowItem>

          <VTabsWindowItem value="payment">
            <VRow dense class="ma-0">
              <!-- Formulario de Pago (Izquierda) -->
              <VCol cols="12" md="7" class="pa-1">
                <div class="bg-white pa-4 rounded-lg border h-100 shadow-xs">
                  <div class="d-flex align-center gap-2 mb-4">
                    <div class="header-indicator primary shadow-sm"></div>
                    <span class="text-caption font-weight-bold text-high-emphasis">Detalles del Desembolso</span>
                  </div>
                  
                  <VRow dense>
                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="currency"
                        label="Moneda de Pago"
                        :items="Object.keys(countsFilterByCurrency).map(c => ({ title: c, value: c }))"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                        placeholder="Seleccionar"
                        class="mb-3"
                        :error="!!errors.currency"
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="count"
                        label="Origen de Fondos"
                        :items="(countsFilterByCurrency[currency] ?? []).map(a => ({ title: a, value: a }))"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                        placeholder="Cuenta"
                        class="mb-3"
                        :disabled="!currency"
                        :error="!!errors.count"
                      />
                    </VCol>
                    <VCol cols="12">
                      <div class="pa-4 rounded-lg bg-light border border-dashed text-center">
                        <span class="text-super-xs font-weight-bold text-primary uppercase d-block mb-2">Monto Confirmado para Entrega</span>
                        <AppTextField
                          v-model="payed"
                          type="number"
                          step="0.01"
                          prefix="$"
                          variant="outlined"
                          density="compact"
                          hide-details="auto"
                          class="mb-1 font-weight-bold"
                          :error="!!errors.payed"
                        />
                        <div class="text-super-xs font-weight-medium text-disabled uppercase mt-1">
                          Ingrese la cantidad exacta en USD
                        </div>
                      </div>
                    </VCol>
                  </VRow>
                </div>
              </VCol>

              <!-- Resumen "Ticket" (Derecha) -->
              <VCol cols="12" md="5" class="pa-1">
                <VCard variant="flat" class="rounded-lg border-primary border-t-4 h-100 bg-white shadow-sm overflow-hidden d-flex flex-column">
                  <div class="pa-4 text-center border-b bg-light">
                    <VAvatar color="primary" variant="tonal" size="44" class="mb-2">
                       <VIcon icon="tabler-receipt-tax" size="22" />
                    </VAvatar>
                    <div class="text-super-xs font-weight-bold text-primary uppercase letter-spacing-1">Resumen de Liquidación</div>
                  </div>
                  
                  <div class="pa-4 flex-grow-1 d-flex flex-column gap-3 justify-center">
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">Total Neto (USD)</span>
                      <span class="text-subtitle-1 font-weight-black text-high-emphasis tabular-nums">${{ displayAmount(amountToPay) }}</span>
                    </div>
                    
                    <VDivider class="border-dashed-t" />
                    
                    <div class="d-flex justify-space-between align-center opacity-70">
                      <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">Tasa de Cambio</span>
                      <span class="text-super-xs font-weight-bold text-medium-emphasis">{{ displayAmount(exchangeRate) }} Bs</span>
                    </div>

                    <div class="pa-3 rounded-lg bg-primary mt-2 text-center elevation-2">
                       <span class="text-super-xs font-weight-bold text-white opacity-75 uppercase d-block leading-none mb-1">Total en Bolívares</span>
                       <div class="text-h5 font-weight-black text-white tabular-nums">
                         {{ displayAmount(amountToPay * exchangeRate) }} <small class="text-xs">Bs.S</small>
                       </div>
                    </div>
                  </div>
                  
                  <div class="pa-3 bg-warning-light border-t mt-auto text-center">
                    <div class="text-super-xs font-weight-bold text-warning uppercase d-flex align-center justify-center gap-1">
                      <VIcon icon="tabler-alert-circle" size="14" />
                      Documento PDF Requerido
                    </div>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCardText>

      <VCardActions class="pa-3 bg-light border-t">
        <VRow no-gutters class="w-100 gap-2 justify-end">
          <VCol cols="auto">
            <VBtn
              color="secondary"
              variant="outlined"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-4 text-none"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              color="primary"
              variant="flat"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-5 shadow-primary text-none"
              :disabled="!settlement"
              @click="submitForm"
            >
              {{ step === "employee" ? "Configurar Pago" : "Finalizar y Generar PDF" }}
              <VIcon end icon="tabler-chevron-right" class="ms-1" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.premium-tabs :deep(.v-tab) {
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  color: #64748b !important;
  letter-spacing: 0.3px;
  min-height: 44px !important;
  padding-bottom: 2px !important;
}

.premium-tabs :deep(.v-tab--selected) {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: white !important;
}

.premium-micro-table :deep(th) {
  background-color: #f8fafc !important;
  height: 28px !important;
  padding-inline: 8px !important;
}

.premium-micro-table :deep(td) {
  height: 30px !important;
  color: #334155 !important;
  padding-inline: 8px !important;
}

.micro-input {
  width: 100%;
  border: none;
  background: transparent;
  padding: 2px 4px;
  font-size: 0.75rem;
  outline: none;
  border-radius: 4px;
}

.micro-input:focus {
  background-color: #f1f5f9;
}

.shadow-xs { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important; }
.shadow-sm { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important; }
.shadow-primary { box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important; }

.shadow-text { text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); }

.border-dashed { border-style: dashed !important; border-width: 1px !important; }
.border-dashed-t { border-block-start: 1px dashed rgba(0,0,0,0.1) !important; }

.text-super-xs { font-size: 0.65rem !important; line-height: 1.2; }
.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
.gap-4 { gap: 16px !important; }

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.tabular-nums { font-variant-numeric: tabular-nums; }
</style>
