<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref } from "vue";
import { VNumberInput } from "vuetify/components";

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

const countsFilterByCurrency = {
  USD: ["Efectivo", "Binance", "Paypal"],
  COP: ["Efectivo", "Transferencia"],
  BS: ["Efectivo", "Tarjeta", "Pago móvil", "Transferencia"],
};

const fetchSettlement = async () => {
  if (!props.selectedEmployee?.id) return;
  if (!props.modelValue) return;

  try {
    const { data } = await axios.get(
      `/rrhh/social-benefits/employees/${props.selectedEmployee.id}/settlement-data`
    );
    settlement.value = data.data;

    if (settlement.value.amount === 0) {
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
    }
  }
);

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
      total: amountToPay.value,
      payed: payed.value,
      count: count.value,
      currency: currency.value,
    };

    const { data } = await axios.post(
      `/rrhh/social-benefits/employees/${props.selectedEmployee.id}/fire`,
      payload
    );

    if (data.status) {
      toast.success("Empleado liquidado con éxito");
      emit("refresh-table");
      closeDialog();
    } else {
      toast.error("No se pudo procesar la liquidación del empleado");
    }
  } catch (error) {
    toast.error("Hubo un error procesando la liquidación del empleado");

    if (error.response.status === 422) {
      errors.value = error.response.data.errors;
    }

    if (errors.value.percentage) {
      step.value = "employee";
      return;
    }
  }
};

const closeDialog = () => {
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
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">
          Finalizar relación con {{ props.selectedEmployee.name }}
          {{ props.selectedEmployee.last_name }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VTabs v-model="step">
          <VTab value="employee"> Empleado </VTab>
          <VTab value="payment"> Pago </VTab>
        </VTabs>

        <VTabsWindow v-model="step">
          <VTabsWindowItem value="employee">
            <VTable density="comfortable">
              <thead>
                <tr>
                  <td style="width: 70%" class="font-weight-bold">
                    Fecha de inicio
                  </td>
                  <td></td>
                  <td class="font-weight-bold">
                    {{ settlement?.starting_date }}
                  </td>
                </tr>
                <tr>
                  <td style="width: 70%" class="font-weight-bold">
                    Últimos {{ settlement?.average_salary_count || 0 }} salarios
                  </td>
                  <td></td>
                  <td class="font-weight-bold">
                    <VBtn
                      v-if="settlement?.last_salaries?.length > 0"
                      variant="outlined"
                      size="small"
                      color="primary"
                      @click="showSalaryDetails = !showSalaryDetails"
                      class="mb-2"
                    >
                      <VIcon start>tabler-calendar</VIcon>
                      {{ showSalaryDetails ? "Ocultar" : "Ver" }} detalles
                    </VBtn>
                    <div
                      v-if="
                        showSalaryDetails &&
                        settlement?.last_salaries?.length > 0
                      "
                      class="mt-2"
                    >
                      <div
                        v-for="(salary, index) in settlement.last_salaries"
                        :key="index"
                        class="mb-1 pa-2 bg-grey-lighten-5 rounded text-caption"
                      >
                        {{ formatCurrency(salary.amount_bs) }} Bs. ({{
                          formatDate(salary.payslip_date)
                        }})
                      </div>
                    </div>
                    <div
                      v-if="!settlement?.last_salaries?.length"
                      class="text-caption text-grey"
                    >
                      No hay salarios registrados
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="width: 70%" class="font-weight-bold">
                    Salario Promedio
                  </td>
                  <td></td>
                  <td class="font-weight-bold">
                    {{ formatCurrency(settlement?.average_salary || 0) }} Bs.
                  </td>
                </tr>
                <tr>
                  <th style="width: 70%" class="text-start font-weight-bold">
                    Liquidación
                  </th>
                  <th class="font-weight-bold">Días</th>
                  <th class="font-weight-bold">Monto</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Prestaciones Sociales</td>
                  <td>{{ settlement?.social_benefits_days ?? 0 }}</td>
                  <td>
                    {{ displayAmount(settlement?.social_benefits_amount ?? 0) }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Vacaciones Fraccionadas</td>
                  <td>{{ settlement?.vacation_voucher_days ?? 0 }}</td>
                  <td>
                    {{
                      displayAmount(settlement?.vacation_voucher_amount ?? 0)
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Bono Vacacional</td>
                  <td>{{ settlement?.vacation_bonus_voucher_days ?? 0 }}</td>
                  <td>
                    {{
                      displayAmount(
                        settlement?.vacation_bonus_voucher_amount ?? 0
                      )
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Utilidades</td>
                  <td>{{ settlement?.earnings_voucher_days ?? 0 }}</td>
                  <td>
                    {{
                      displayAmount(settlement?.earnings_voucher_amount ?? 0)
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Total</td>
                  <td>{{ settlement?.total_settlement_days ?? 0 }}</td>
                  <td>
                    {{
                      displayAmount(settlement?.total_settlement_amount ?? 0)
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    Tasa BCV {{ new Date().toLocaleDateString("es-VE") }}
                  </td>
                  <td>{{ displayAmount(settlement?.currency ?? 0) }} Bs.</td>
                </tr>
                <tr>
                  <td colspan="2" class="font-weight-bold">Total USD</td>
                  <td class="font-weight-bold">
                    {{ displayAmount(settlement?.total_settlement_usd ?? 0) }} $
                  </td>
                </tr>
              </tbody>
            </VTable>

            <VSpacer class="py-2" />

            <VTable density="comfortable">
              <thead>
                <tr>
                  <th style="width: 80%" class="text-start font-weight-bold">
                    Deducciones
                  </th>
                  <th class="font-weight-bold">Monto</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Vacaciones Fraccionadas</td>
                  <td>
                    {{
                      displayAmount(settlement?.vacation_voucher_deduction ?? 0)
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Bono Vacacional</td>
                  <td>
                    {{
                      displayAmount(
                        settlement?.vacation_bonus_voucher_deduction ?? 0
                      )
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Utilidades</td>
                  <td>
                    {{
                      displayAmount(settlement?.earnings_voucher_deduction ?? 0)
                    }}
                    Bs.
                  </td>
                </tr>
                <tr>
                  <td>Total</td>
                  <td>
                    {{ displayAmount(settlement?.total_deductions ?? 0) }} Bs.
                  </td>
                </tr>
                <tr>
                  <td>Tasa BCV {{ new Date().toLocaleDateString("es-VE") }}</td>
                  <td>{{ displayAmount(settlement?.currency ?? 0) }} Bs.</td>
                </tr>
                <tr>
                  <td class="font-weight-bold">Total a pagar</td>
                  <td class="font-weight-bold">
                    {{ displayAmount(settlement?.total_deductions_usd ?? 0) }} $
                  </td>
                </tr>
              </tbody>
            </VTable>

            <VSpacer class="py-4" />

            <VTable density="comfortable">
              <tbody>
                <tr>
                  <td style="width: 80%" class="font-weight-bold">
                    Total Final
                  </td>
                  <td class="font-weight-bold">
                    {{ displayAmount(settlement?.final_usd ?? 0) }} $
                  </td>
                </tr>
                <tr>
                  <td style="width: 80%" class="font-weight-bold">
                    Fecha de renuncia
                  </td>
                  <td class="font-weight-bold">
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
                  </td>
                </tr>
              </tbody>
            </VTable>

            <VSpacer class="py-4" />

            <VRow class="d-flex align-center">
              <VCol cols="6">
                <VNumberInput
                  v-model="percentage"
                  label="% a pagar"
                  placeholder="50"
                  :clearable="true"
                  control-variant="hidden"
                  :error-messages="errors.percentage"
                />
              </VCol>
              <VCol cols="3">
                <h5>
                  <span class="text-h5 font-weight-bold">Monto a pagar:</span>
                </h5>
              </VCol>
              <VCol cols="3">
                <h5 class="text-h5 ms-6">{{ displayAmount(amountToPay) }} $</h5>
              </VCol>
            </VRow>
          </VTabsWindowItem>
          <VTabsWindowItem value="payment">
            <VRow>
              <VCol cols="4">
                <div class="d-flex align-center gap-4 mb-4">
                  <span class="font-weight-medium">Total</span>
                  <VChip color="primary" label> {{ amountToPay }} $ </VChip>
                  <VSpacer />
                </div>
              </VCol>
              <VCol cols="4">
                <div class="d-flex align-center gap-4 mb-4">
                  <span class="font-weight-medium">Total</span>
                  <VChip color="primary" label>
                    {{
                      Intl.NumberFormat("es-Ve", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      }).format(amountToPay * exchangeRate)
                    }}
                    Bs.</VChip
                  >
                  <VSpacer />
                </div>
              </VCol>
              <VCol cols="4">
                <div class="d-flex align-center gap-4 mb-4">
                  <span class="font-weight-medium">Fecha</span>
                  <VChip color="primary" label>
                    {{
                      Intl.DateTimeFormat("es-Ve", {
                        dateStyle: "short",
                      }).format(new Date())
                    }}
                  </VChip>
                  <VSpacer />
                </div>
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="6">
                <VSelect
                  v-model="currency"
                  label="Moneda"
                  variant="outlined"
                  hide-details="auto"
                  item-title="title"
                  item-value="value"
                  :items="
                    Object.keys(countsFilterByCurrency).map((currency) => ({
                      title: currency,
                      value: currency,
                    }))
                  "
                  :error-messages="errors.currency"
                />
              </VCol>
              <VCol cols="6">
                <VSelect
                  v-model="count"
                  label="Cuenta"
                  variant="outlined"
                  hide-details="auto"
                  item-title="title"
                  item-value="value"
                  :items="
                    (
                      countsFilterByCurrency[currency] ?? [
                        ...new Set(
                          Object.values(countsFilterByCurrency).flat()
                        ),
                      ]
                    ).map((account) => ({
                      title: account,
                      value: account,
                    }))
                  "
                  :error-messages="errors.count"
                />
              </VCol>
              <VCol cols="6">
                <VTextField
                  v-model="payed"
                  label="Monto a pagar"
                  type="number"
                  variant="outlined"
                  hide-details="auto"
                  :step="0.01"
                  :error-messages="errors.payed"
                />
              </VCol>
            </VRow>
          </VTabsWindowItem>
        </VTabsWindow>
      </VContainer>

      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="flex-grow-1 w-0"
          :disabled="settlement?.amount === 0"
        >
          Confirmar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
