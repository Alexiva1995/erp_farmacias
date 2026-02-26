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

// Funciones auxiliares para formateo
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

      <VCardText class="pa-4">
        <VTabs v-model="step" density="compact" class="mb-4">
          <VTab value="employee">Cálculo</VTab>
          <VTab value="payment">Pago</VTab>
        </VTabs>

        <VTabsWindow v-model="step">
          <VTabsWindowItem value="employee">
            <!-- Información General en Cards -->
            <VRow dense class="mb-4">
              <VCol cols="12" md="4">
                <VCard variant="outlined" class="h-100">
                  <VCardText
                    class="d-flex flex-column align-center justify-center py-2 text-center"
                  >
                    <span class="text-caption text-medium-emphasis"
                      >Fecha de Inicio</span
                    >
                    <span class="text-subtitle-1 font-weight-bold">{{
                      settlement?.starting_date
                    }}</span>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12" md="4">
                <VCard variant="outlined" class="h-100">
                  <VCardItem class="py-2 bg-grey-50">
                    <template #prepend
                      ><VIcon size="18" icon="tabler-coin" class="me-2"
                    /></template>
                    <VCardTitle class="text-subtitle-2 font-weight-bold"
                      >Salario Promedio</VCardTitle
                    >
                  </VCardItem>
                  <VDivider />
                  <VCardText class="pa-4 text-center">
                    <div class="text-h4 font-weight-bold text-primary mb-1">
                      {{ displayAmount(settlement?.average_salary ?? 0) }} Bs
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Basado en {{ settlement?.average_salary_count ?? 0 }} salarios
                    </div>
                    <div class="mt-2 text-center">
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        @click="showSalaryDetails = !showSalaryDetails"
                      >
                        {{
                          showSalaryDetails ? "Ocultar detalles" : "Ver detalles"
                        }}</VChip
                      >
                    </div>
                  </VCardText>
                </VCard>
              </VCol>

              <VCol cols="12" md="4">
                <VCard variant="outlined" class="h-100">
                  <VCardText class="py-2">
                    <span class="text-caption text-medium-emphasis d-block text-center"
                      >Configuración de Cálculo</span
                    >
                    <VRow dense mt-1>
                      <VCol cols="12">
                        <VTextField
                          v-model="hireDateOverride"
                          label="Fecha de Ingreso"
                          type="date"
                          density="compact"
                          @change="fetchSettlement"
                        />
                      </VCol>
                      <VCol cols="12">
                        <VTextField
                          v-model="resignationDateOverride"
                          label="Fecha de Egreso / Renuncia"
                          type="date"
                          density="compact"
                          @change="fetchSettlement"
                        />
                      </VCol>
                      <VCol cols="12">
                        <AppTextField
                          v-model="baseSalaryOverride"
                          label="Salario Mensual (USD)"
                          type="number"
                          density="compact"
                          prefix="$"
                          @change="fetchSettlement"
                        />
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Detalles de salarios expandibles -->
              <VCol cols="12" v-if="showSalaryDetails">
                <VCard variant="flat" color="grey-100" class="pa-2">
                  <div
                    v-if="settlement?.last_salaries?.length > 0"
                    class="d-flex flex-wrap gap-2"
                  >
                    <VChip
                      v-for="(salary, index) in settlement.last_salaries"
                      :key="index"
                      size="small"
                      color="secondary"
                      variant="outlined"
                    >
                      {{ formatDate(salary.payslip_date) }}:
                      <strong
                        >{{ formatCurrency(salary.amount_bs) }} Bs.</strong
                      >
                    </VChip>
                  </div>
                  <div v-else class="text-caption text-center">
                    No hay salarios registrados
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Tablas de Cálculo lado a lado -->
            <VRow>
              <!-- Liquidación -->
              <VCol cols="12" md="6">
                <VCard variant="outlined">
                  <VCardItem class="py-2 bg-grey-50">
                    <template #prepend
                      ><VIcon size="18" icon="tabler-calculator" class="me-2"
                    /></template>
                    <VCardTitle class="text-subtitle-2 font-weight-bold"
                      >Devengaciones</VCardTitle
                    >
                  </VCardItem>
                  <VDivider />
                  <VTable density="compact" class="text-caption">
                    <thead>
                      <tr>
                        <th>Concepto</th>
                        <th class="text-center">Días</th>
                        <th class="text-end">Monto (Bs)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Prestaciones Sociales</td>
                        <td class="text-center">
                          {{ settlement?.social_benefits_days ?? 0 }}
                        </td>
                        <td class="text-end">
                          {{
                            displayAmount(
                              settlement?.social_benefits_amount ?? 0
                            )
                          }}
                        </td>
                      </tr>
                      <tr>
                        <td>Vacaciones Fracc.</td>
                        <td class="text-center">
                          {{ settlement?.vacation_voucher_days ?? 0 }}
                        </td>
                        <td class="text-end">
                          {{
                            displayAmount(
                              settlement?.vacation_voucher_amount ?? 0
                            )
                          }}
                        </td>
                      </tr>
                      <tr>
                        <td>Bono Vacacional</td>
                        <td class="text-center">
                          {{ settlement?.vacation_bonus_voucher_days ?? 0 }}
                        </td>
                        <td class="text-end">
                          {{
                            displayAmount(
                              settlement?.vacation_bonus_voucher_amount ?? 0
                            )
                          }}
                        </td>
                      </tr>
                      <tr>
                        <td>Utilidades</td>
                        <td class="text-center">
                          {{ settlement?.earnings_voucher_days ?? 0 }}
                        </td>
                        <td class="text-end">
                          {{
                            displayAmount(
                              settlement?.earnings_voucher_amount ?? 0
                            )
                          }}
                        </td>
                      </tr>
                      <tr class="font-weight-bold bg-grey-50">
                        <td>Total Devengado</td>
                        <td class="text-center">
                          {{ settlement?.total_settlement_days ?? 0 }}
                        </td>
                        <td class="text-end">
                          {{
                            displayAmount(
                              settlement?.total_settlement_amount ?? 0
                            )
                          }}
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </VCard>
              </VCol>

              <!-- Deducciones y Totals -->
              <VCol cols="12" md="6">
                <VCard variant="outlined" class="h-100">
                  <VCardItem class="py-2 bg-grey-50">
                    <template #prepend
                      ><VIcon size="18" icon="tabler-minus" class="me-2"
                    /></template>
                    <VCardTitle class="text-subtitle-2 font-weight-bold"
                      >Deducciones y Totales</VCardTitle
                    >
                  </VCardItem>
                  <VDivider />
                  <VTable density="compact" class="text-caption">
                    <tbody>
                      <tr>
                        <td>Vacaciones Fracc.</td>
                        <td class="text-end">
                          <VTextField
                            v-model="vacationDeductionOverride"
                            density="compact"
                            type="number"
                            hide-details
                            variant="plain"
                            class="text-caption text-error text-right-input"
                            @change="fetchSettlement"
                            :placeholder="displayAmount(settlement?.vacation_voucher_deduction || 0)"
                          />
                        </td>
                      </tr>
                      <tr>
                        <td>Bono Vacacional</td>
                        <td class="text-end">
                          <VTextField
                            v-model="vacationBonusDeductionOverride"
                            density="compact"
                            type="number"
                            hide-details
                            variant="plain"
                            class="text-caption text-error text-right-input"
                            @change="fetchSettlement"
                            :placeholder="displayAmount(settlement?.vacation_bonus_voucher_deduction || 0)"
                          />
                        </td>
                      </tr>
                      <tr>
                        <td>Utilidades</td>
                        <td class="text-end">
                           <VTextField
                            v-model="earningsDeductionOverride"
                            density="compact"
                            type="number"
                            hide-details
                            variant="plain"
                            class="text-caption text-error text-right-input"
                            @change="fetchSettlement"
                            :placeholder="displayAmount(settlement?.earnings_voucher_deduction || 0)"
                          />
                        </td>
                      </tr>
                      <!-- Deducciones Manuales -->
                      <tr v-for="(ded, idx) in additionalDeductions" :key="idx">
                        <td class="d-flex align-center">
                          <VBtn icon size="x-small" variant="text" color="error" class="me-1" @click="removeDeduction(idx)">
                            <VIcon size="14">tabler-trash</VIcon>
                          </VBtn>
                          {{ ded.description }}
                        </td>
                        <td class="text-end text-error">
                          -{{ displayAmount(ded.amount * (exchangeRate || 1)) }} Bs
                        </td>
                      </tr>
                      
                      <!-- Input Nueva Deducción -->
                      <tr class="bg-light-info">
                        <td colspan="2" class="pa-1">
                          <div class="d-flex gap-2 align-center">
                            <VTextField
                              v-model="newDeduction.description"
                              placeholder="Nueva deducción..."
                              density="compact"
                              hide-details
                              variant="plain"
                              class="text-caption"
                            />
                            <VTextField
                              v-model="newDeduction.amount"
                              placeholder="Monto $"
                              type="number"
                              density="compact"
                              hide-details
                              variant="plain"
                              class="text-caption"
                              style="inline-size: 80px;"
                              @keyup.enter="addDeduction"
                            />
                            <VBtn icon size="x-small" color="primary" @click="addDeduction">
                              <VIcon size="16">tabler-plus</VIcon>
                            </VBtn>
                          </div>
                        </td>
                      </tr>

                      <tr class="font-weight-bold bg-grey-50">
                        <td>Total Deducciones</td>
                        <td class="text-end text-error">
                         {{
                           displayAmount(
                             (settlement?.total_deductions ?? 0) + (totalAdditionalDeductions * exchangeRate)
                           )
                         }} Bs
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2"><VDivider class="my-1" /></td>
                      </tr>
                      <tr>
                        <td>Tasa BCV</td>
                        <td class="text-end">
                          {{ displayAmount(exchangeRate ?? 0) }} Bs
                        </td>
                      </tr>
                      <tr>
                        <td class="font-weight-bold">Total a Pagar (USD)</td>
                        <td class="text-end font-weight-bold text-primary">
                          {{ displayAmount(settlement?.final_usd ?? 0) }} $
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </VCard>
              </VCol>
            </VRow>


            <!-- Footer Controls -->
            <VRow align="center">
              <VCol cols="12" md="4">
                <div class="d-flex align-center">
                  <span class="text-caption text-medium-emphasis me-2"
                    >Fecha Renuncia:</span
                  >
                  <span class="font-weight-medium">
                    {{
                      (() => {
                        const dateValue = settlement?.resignation_date
                          ? new Date(settlement.resignation_date)
                          : new Date();
                        return isNaN(dateValue.getTime())
                          ? "-"
                          : Intl.DateTimeFormat("es-VE", {
                              year: "numeric",
                              month: "2-digit",
                              day: "2-digit",
                            }).format(dateValue);
                      })()
                    }}
                  </span>
                </div>
                <div class="d-flex align-center mt-1">
                  <span class="text-caption text-medium-emphasis me-2"
                    >Total Calculado:</span
                  >
                  <span class="font-weight-bold text-success"
                    >{{ displayAmount(settlement?.final_usd ?? 0) }} $</span
                  >
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <AppTextField
                  v-model="percentage"
                  label="% Liquidación"
                  placeholder="Ej: 25"
                  type="number"
                  suffix="%"
                  autofocus
                />
              </VCol>
              <VCol cols="12" md="4" class="text-end">
                <div class="text-caption text-medium-emphasis">
                  Monto Real a Pagar
                </div>
                <div class="text-h5 font-weight-bold text-primary">
                  {{ displayAmount(amountToPay) }} $
                </div>
              </VCol>
            </VRow>
          </VTabsWindowItem>

          <!-- TAB PAGO -->
          <VTabsWindowItem value="payment">
            <VAlert color="info" variant="tonal" class="mb-4" density="compact">
              <div class="d-flex justify-space-between align-center">
                <span><strong>Total:</strong> {{ displayAmount(amountToPay) }} $</span>
                <span
                  ><strong>En Bs:</strong>
                  {{
                    displayAmount(amountToPay * (exchangeRate || 1))
                  }}</span
                >
                <span><strong>Tasa:</strong> {{ displayAmount(exchangeRate || 0) }}</span>
              </div>
            </VAlert>

            <VRow dense>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="currency"
                  label="Método / Moneda"
                  variant="outlined"
                  density="compact"
                  item-title="title"
                  item-value="value"
                  placeholder="Seleccione moneda"
                  :items="
                    Object.keys(countsFilterByCurrency).map((c) => ({
                      title: c,
                      value: c,
                    }))
                  "
                  :error-messages="errors.currency"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="count"
                  label="Tipo de Pago"
                  variant="outlined"
                  density="compact"
                  item-title="title"
                  item-value="value"
                  placeholder="Seleccione tipo"
                  :items="
                    (countsFilterByCurrency[currency] ?? []).map((account) => ({
                      title: account,
                      value: account,
                    }))
                  "
                  :error-messages="errors.count"
                  :disabled="!currency"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="payed"
                  label="Monto Confirmado"
                  type="number"
                  variant="outlined"
                  density="compact"
                  prefix="$"
                  :step="0.01"
                  :error-messages="errors.payed"
                />
              </VCol>
            </VRow>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          class="flex-grow-1"
          color="secondary"
          variant="tonal"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          class="flex-grow-1"
          color="primary"
          variant="elevated"
          @click="submitForm"
          :disabled="!settlement"
        >
          {{ step === "employee" ? "Siguiente" : "Confirmar Liquidación" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
