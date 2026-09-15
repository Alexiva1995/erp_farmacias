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

const setFullAmount = () => {
  payed.value = Number(Number(amountToPay.value || 0).toFixed(2));
};

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
              <!-- Sección Izquierda: Parámetros y Tablas -->
              <VCol cols="12" lg="8" class="pa-1">
                <!-- 1. Parámetros de Cálculo con espacio adecuado -->
                <div class="bg-white pa-4 rounded-lg border mb-3 shadow-xs">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center gap-2">
                      <div class="header-indicator primary shadow-sm"></div>
                      <span class="text-caption font-weight-bold text-high-emphasis">Parámetros de Cálculo</span>
                    </div>
                    <VChip size="x-small" color="primary" variant="tonal" class="rounded font-weight-bold">Configuración</VChip>
                  </div>
                  
                  <VRow dense class="mt-2">
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

                <!-- 3. Tablas de Detalle: Devengaciones y Deducciones con padding y alineación -->
                <VRow dense>
                  <!-- Devengaciones -->
                  <VCol cols="12" md="6" class="pa-1">
                    <div class="bg-white rounded-lg border overflow-hidden shadow-xs h-100 d-flex flex-column">
                      <div class="pa-3 bg-light border-b d-flex align-center gap-2">
                        <div class="header-indicator primary shadow-sm"></div>
                        <span class="text-caption font-weight-bold text-high-emphasis">Devengaciones</span>
                      </div>
                      <VTable density="comfortable" class="premium-micro-table flex-grow-1">
                        <thead>
                          <tr>
                            <th class="text-left text-super-xs font-weight-bold text-medium-emphasis uppercase">Concepto</th>
                            <th class="text-right text-super-xs font-weight-bold text-medium-emphasis uppercase">Días</th>
                            <th class="text-right text-super-xs font-weight-bold text-medium-emphasis uppercase">Monto (Bs.)</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td class="concept-cell text-left">Antigüedad / Prestaciones</td>
                            <td class="text-right font-weight-bold text-medium-emphasis">{{ displayedSettlement?.social_benefits_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold text-high-emphasis">{{ displayAmount(displayedSettlement?.social_benefits_amount ?? 0) }}</td>
                          </tr>
                          <tr>
                            <td class="concept-cell text-left">Vacaciones Fracc.</td>
                            <td class="text-right font-weight-bold text-medium-emphasis">{{ displayedSettlement?.vacation_voucher_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold text-high-emphasis">{{ displayAmount(displayedSettlement?.vacation_voucher_amount ?? 0) }}</td>
                          </tr>
                          <tr>
                            <td class="concept-cell text-left">Bono Vacacional</td>
                            <td class="text-right font-weight-bold text-medium-emphasis">{{ displayedSettlement?.vacation_bonus_voucher_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold text-high-emphasis">{{ displayAmount(displayedSettlement?.vacation_bonus_voucher_amount ?? 0) }}</td>
                          </tr>
                          <tr>
                            <td class="concept-cell text-left">Utilidades</td>
                            <td class="text-right font-weight-bold text-medium-emphasis">{{ displayedSettlement?.earnings_voucher_days ?? 0 }}</td>
                            <td class="text-right font-weight-bold text-high-emphasis">{{ displayAmount(displayedSettlement?.earnings_voucher_amount ?? 0) }}</td>
                          </tr>
                          <tr class="bg-success-subtle">
                            <td colspan="2" class="text-xs font-weight-bold text-success text-left">Subtotal Devengado</td>
                            <td class="text-right text-success font-weight-black">{{ displayAmount(displayedSettlement?.total_settlement_amount ?? 0) }}</td>
                          </tr>
                        </tbody>
                      </VTable>
                    </div>
                  </VCol>

                  <!-- Deducciones -->
                  <VCol cols="12" md="6" class="pa-1">
                    <div class="bg-white rounded-lg border overflow-hidden shadow-xs h-100 d-flex flex-column">
                      <div class="pa-3 bg-light border-b d-flex align-center gap-2">
                        <div class="header-indicator secondary shadow-sm"></div>
                        <span class="text-caption font-weight-bold text-high-emphasis">Deducciones</span>
                      </div>
                      <VTable density="comfortable" class="premium-micro-table flex-grow-1">
                        <thead>
                          <tr>
                            <th class="text-left text-super-xs font-weight-bold text-medium-emphasis uppercase">Concepto</th>
                            <th class="text-right text-super-xs font-weight-bold text-medium-emphasis uppercase">Monto (Bs.)</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td class="concept-cell text-left">Deducción Vacaciones</td>
                            <td class="text-right pa-1">
                              <input
                                :value="formatNumberWithSeparators(vacationDeductionOverride)"
                                class="micro-input text-end font-weight-bold text-error"
                                @input="(e) => handleNumberInput('vacationDeductionOverride', e.target.value)"
                              />
                            </td>
                          </tr>
                          <tr>
                            <td class="concept-cell text-left">Ded. Bono Vacacional</td>
                            <td class="text-right pa-1">
                              <input
                                :value="formatNumberWithSeparators(vacationBonusDeductionOverride)"
                                class="micro-input text-end font-weight-bold text-error"
                                @input="(e) => handleNumberInput('vacationBonusDeductionOverride', e.target.value)"
                              />
                            </td>
                          </tr>
                          <tr>
                            <td class="concept-cell text-left">Deducción Utilidades</td>
                            <td class="text-right pa-1">
                              <input
                                :value="formatNumberWithSeparators(earningsDeductionOverride)"
                                class="micro-input text-end font-weight-bold text-error"
                                @input="(e) => handleNumberInput('earningsDeductionOverride', e.target.value)"
                              />
                            </td>
                          </tr>
                          <!-- Deducciones Adicionales -->
                          <tr v-for="(ded, idx) in additionalDeductions" :key="idx" class="bg-error-subtle border-dashed-t">
                            <td class="text-super-xs font-weight-bold d-flex align-center py-2 text-left">
                              <VBtn icon="tabler-trash-x" size="18" variant="text" color="error" class="me-1" @click="removeDeduction(idx)" />
                              {{ ded.description.toUpperCase() }}
                            </td>
                            <td class="text-right text-super-xs font-weight-bold text-error py-2">- {{ displayAmount(ded.amount * exchangeRate) }}</td>
                          </tr>
                          <tr class="bg-light">
                            <td colspan="2" class="pa-2">
                              <div class="d-flex align-center gap-1 bg-white rounded border pa-1">
                                <input v-model="newDeduction.description" placeholder="+ OTRA DEDUCCIÓN" class="micro-input grow flex-grow-1 text-left" />
                                <VDivider vertical class="mx-1" />
                                <input v-model="newDeduction.amount" type="number" placeholder="0.00" class="micro-input font-weight-bold text-end" style="inline-size: 80px;" @keyup.enter="addDeduction" />
                                <VBtn icon="tabler-plus" size="22" color="primary" variant="tonal" class="rounded" @click="addDeduction" />
                              </div>
                            </td>
                          </tr>
                          <tr class="bg-error-subtle">
                            <td class="text-xs font-weight-bold text-error text-left">Subtotal Deducido</td>
                            <td class="text-right text-error font-weight-black">{{ displayAmount((displayedSettlement?.total_deductions ?? 0) + (totalAdditionalDeductions * exchangeRate)) }}</td>
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
                  <!-- Bloque 1: Promedio de Sueldos (blanco con borde fino) -->
                  <div class="bg-white rounded-lg border shadow-xs pa-3">
                    <div class="d-flex justify-space-between align-center mb-1">
                      <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">Promedio Últimos Sueldos</span>
                      <VBtn icon size="x-small" variant="text" color="secondary" @click="showSalaryDetails = !showSalaryDetails">
                        <VIcon size="15">{{ showSalaryDetails ? 'tabler-eye-off' : 'tabler-eye' }}</VIcon>
                      </VBtn>
                    </div>
                    <div class="text-h5 font-weight-black text-high-emphasis leading-tight mb-2">
                      {{ displayAmount(settlement?.average_salary ?? 0) }} <small class="text-caption text-medium-emphasis">Bs.</small>
                    </div>
                    <div class="d-flex gap-3 pt-2 border-t">
                       <div class="d-flex flex-column flex-grow-1 border-r pe-2">
                         <span class="text-super-xs font-weight-medium text-medium-emphasis uppercase">Día / Social</span>
                         <span class="text-xs font-weight-bold text-high-emphasis">{{ displayAmount(settlement?.daily_wage ?? 0) }} Bs.</span>
                       </div>
                       <div class="d-flex flex-column flex-grow-1">
                         <span class="text-super-xs font-weight-medium text-medium-emphasis uppercase">Día / Integral</span>
                         <span class="text-xs font-weight-bold text-high-emphasis">{{ displayAmount(settlement?.integral_salary ?? 0) }} Bs.</span>
                       </div>
                    </div>
                  </div>

                  <VExpandTransition>
                    <div v-show="showSalaryDetails">
                      <div class="rounded-lg border border-dashed pa-3 bg-white shadow-xs">
                        <span class="text-super-xs font-weight-bold text-primary uppercase d-block mb-2">Historial Reciente</span>
                        <div v-if="settlement?.last_salaries?.length > 0" class="d-flex flex-column gap-1">
                          <div v-for="(salary, index) in settlement.last_salaries" :key="index" class="d-flex justify-space-between align-center text-super-xs pa-1 border-b last:border-0">
                            <span class="font-weight-medium text-medium-emphasis">{{ formatDate(salary.payslip_date) }}</span>
                            <span class="font-weight-bold text-high-emphasis">{{ formatCurrency(salary.amount_bs) }} Bs.</span>
                          </div>
                        </div>
                        <div v-else class="text-super-xs text-center text-disabled italic py-1">SIN HISTORIAL</div>
                      </div>
                    </div>
                  </VExpandTransition>

                  <!-- Bloque 2: Tasa BCV + % A Liquidar (separado e independiente) -->
                  <div class="bg-white rounded-lg border shadow-xs pa-3">
                    <!-- Tasa BCV en una línea limpia -->
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-super-xs font-weight-medium text-medium-emphasis">Tasa BCV</span>
                      <span class="text-xs font-weight-bold text-high-emphasis">{{ displayAmount(exchangeRate) }} Bs./USD</span>
                    </div>
                    <!-- % A Liquidar con flex limpio etiqueta-izquierda / input-derecha -->
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">% A Liquidar</span>
                      <div style="inline-size: 85px;">
                        <AppTextField
                          v-model="percentage"
                          type="number"
                          min="1"
                          max="100"
                          density="compact"
                          suffix="%"
                          hide-details
                          class="percentage-input font-weight-bold text-end"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Bloque 3: Tarjeta TOTAL NETO A PAGAR (foco visual principal) -->
                  <div class="total-neto-card rounded-lg pa-4 flex-grow-1 d-flex flex-column justify-center text-center shadow-xs">
                    <!-- Título en mayúsculas pequeño gris corporativo -->
                    <div class="text-super-xs font-weight-bold text-medium-emphasis uppercase tracking-wider mb-3">
                      TOTAL NETO A PAGAR
                    </div>

                    <!-- Cifra principal USD: 27px Extra-Bold magenta, todo en una línea -->
                    <div class="d-flex align-baseline justify-center gap-1.5 mb-2">
                      <span class="total-amount-usd tabular-nums">
                        {{ displayAmount(amountToPay) }}
                      </span>
                      <span class="total-currency-usd font-weight-black text-primary">
                        USD
                      </span>
                    </div>

                    <!-- Equivalencia Bs.: 15px Bold azul oscuro -->
                    <div class="total-equiv-bs">
                      ≈ {{ displayAmount(amountToPay * exchangeRate) }} Bs.
                    </div>
                  </div>
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
                        <div class="d-flex justify-space-between align-center mb-2">
                          <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">Monto Confirmado para Entrega</span>
                          <VBtn
                            size="x-small"
                            variant="tonal"
                            color="primary"
                            class="font-weight-bold text-none rounded px-2"
                            @click="setFullAmount"
                          >
                            Copiar Monto Total
                          </VBtn>
                        </div>
                        <AppTextField
                          v-model="payed"
                          type="number"
                          step="0.01"
                          prefix="$"
                          variant="outlined"
                          density="default"
                          placeholder="0.00"
                          hide-details="auto"
                          class="prominent-amount-input mb-1 font-weight-black"
                          :error="!!errors.payed"
                        />
                        <div class="text-super-xs font-weight-medium text-disabled uppercase mt-1">
                          Ingrese la cantidad exacta en USD o use el botón de autocompletado
                        </div>
                      </div>
                    </VCol>
                  </VRow>
                </div>
              </VCol>

              <!-- Resumen "Ticket" (Derecha) -->
              <VCol cols="12" md="5" class="pa-1">
                <div class="rounded-lg border bg-white shadow-xs overflow-hidden d-flex flex-column h-100">
                  <div class="pa-3.5 text-center border-b bg-light">
                    <div class="text-caption font-weight-bold text-high-emphasis uppercase letter-spacing-1">Resumen de Liquidación</div>
                  </div>
                  
                  <div class="pa-4 flex-grow-1 d-flex flex-column gap-3 justify-center">
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-xs font-weight-medium text-medium-emphasis uppercase">Total Neto (USD)</span>
                      <span class="text-subtitle-1 font-weight-black text-high-emphasis tabular-nums">${{ displayAmount(amountToPay) }}</span>
                    </div>
                    
                    <VDivider class="border-dashed-t" />
                    
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-xs font-weight-medium text-medium-emphasis uppercase">Tasa de Cambio</span>
                      <span class="text-xs font-weight-bold text-medium-emphasis">{{ displayAmount(exchangeRate) }} Bs</span>
                    </div>

                    <div class="pa-3.5 rounded-lg bg-light border text-center mt-2">
                       <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase d-block leading-none mb-1">Total en Bolívares</span>
                       <div class="text-h5 font-weight-black text-high-emphasis tabular-nums">
                         {{ displayAmount(amountToPay * exchangeRate) }} <small class="text-xs text-medium-emphasis">Bs.</small>
                       </div>
                    </div>
                  </div>
                  
                  <div class="warning-alert-banner pa-2.5 border-t text-center">
                    <div class="text-super-xs font-weight-bold uppercase d-flex align-center justify-center gap-1">
                      <VIcon icon="tabler-alert-triangle" size="14" />
                      Documento PDF Requerido
                    </div>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCardText>

      <!-- 5. Footer con padding simétrico 16px 24px -->
      <VCardActions class="dialog-footer pa-4 px-6 bg-light border-t">
        <VRow no-gutters class="w-100 gap-2 justify-end align-center">
          <VCol cols="auto">
            <VBtn
              variant="outlined"
              size="default"
              height="38"
              class="cancel-btn font-weight-bold rounded-lg px-4 text-none"
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

.concept-cell {
  color: #374151 !important;
  font-size: 0.75rem !important;
  font-weight: 500 !important;
}

.premium-micro-table :deep(th) {
  background-color: #f8fafc !important;
  padding-inline: 12px !important;
  padding-block: 8px !important;
}

.premium-micro-table :deep(td) {
  padding-inline: 12px !important;
  padding-block: 10px !important;
}

.bg-success-subtle {
  background-color: #f0fdf4 !important;
}

.bg-error-subtle {
  background-color: #fef2f2 !important;
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

.percentage-input :deep(.v-field__input) {
  font-size: 0.85rem !important;
  padding: 2px 6px !important;
  min-block-size: 32px !important;
  text-align: end;
}

.prominent-amount-input :deep(.v-field__input) {
  font-size: 1.25rem !important;
  text-align: center !important;
  font-weight: 800 !important;
  min-block-size: 44px !important;
}

.warning-alert-banner {
  background-color: #fef3c7 !important;
  color: #b45309 !important;
}

.dialog-footer {
  padding: 16px 24px !important;
}

.cancel-btn {
  border-color: #d1d5db !important;
  color: #4b5563 !important;
}

.cancel-btn:hover {
  background-color: #f3f4f6 !important;
  color: #1f2937 !important;
}

.shadow-xs { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important; }
.shadow-sm { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important; }
.shadow-primary { box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important; }

.border-dashed { border-style: dashed !important; border-width: 1px !important; }
.border-dashed-t { border-block-start: 1px dashed rgba(0,0,0,0.1) !important; }

.text-super-xs { font-size: 0.65rem !important; line-height: 1.2; }
.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.gap-1 { gap: 4px !important; }
.gap-1\.5 { gap: 6px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.tabular-nums { font-variant-numeric: tabular-nums; }

/* Tarjeta TOTAL NETO A PAGAR — Bloque 3 de la columna derecha */
.total-neto-card {
  background-color: #fdf2f8 !important;
  border: 1px solid #fbcfe8 !important;
}

.total-amount-usd {
  font-size: 27px !important;
  font-weight: 900 !important;
  color: rgb(var(--v-theme-primary)) !important;
  line-height: 1.1;
}

.total-currency-usd {
  font-size: 15px !important;
  line-height: 1;
}

.total-equiv-bs {
  font-size: 15px !important;
  font-weight: 700 !important;
  color: #1f2937 !important;
  line-height: 1.4;
}
</style>
