<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed } from "vue";
import { VNumberInput } from "vuetify/components";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currency: { type: String, default: null },
  selectedEmployee: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "refresh-table"]);

const settlement = ref(null);
const percentage = ref(100);

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

watch(() => props.selectedEmployee, fetchSettlement, { immediate: true });

const displayAmount = (amount) =>
  Intl.NumberFormat("es-VE", {
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
  }).format(amount);

const amountToPay = computed(() =>
  settlement.value ? settlement.value.final_usd * (percentage.value / 100) : 0
);

const submitForm = async () => {
  try {
    const payload = {
      percentage: percentage.value,
      total: amountToPay.value,
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
  } catch {
    toast.error("Hubo un error procesando la liquidación del empleado");
  }
};

const closeDialog = () => {
  emit("update:modelValue", false);
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
        <VTable density="comfortable">
          <thead>
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
                {{ displayAmount(settlement?.social_benefits_amount ?? 0) }} Bs.
              </td>
            </tr>
            <tr>
              <td>Vacaciones Fraccionadas</td>
              <td>{{ settlement?.vacation_voucher_days ?? 0 }}</td>
              <td>
                {{ displayAmount(settlement?.vacation_voucher_amount ?? 0) }}
                Bs.
              </td>
            </tr>
            <tr>
              <td>Bono Vacacional</td>
              <td>{{ settlement?.vacation_bonus_voucher_days ?? 0 }}</td>
              <td>
                {{
                  displayAmount(settlement?.vacation_bonus_voucher_amount ?? 0)
                }}
                Bs.
              </td>
            </tr>
            <tr>
              <td>Utilidades</td>
              <td>{{ settlement?.earnings_voucher_days ?? 0 }}</td>
              <td>
                {{ displayAmount(settlement?.earnings_voucher_amount ?? 0) }}
                Bs.
              </td>
            </tr>
            <tr>
              <td>Total</td>
              <td>{{ settlement?.total_settlement_days ?? 0 }}</td>
              <td>
                {{ displayAmount(settlement?.total_settlement_amount ?? 0) }}
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
                {{ displayAmount(settlement?.vacation_voucher_deduction ?? 0) }}
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
                {{ displayAmount(settlement?.earnings_voucher_deduction ?? 0) }}
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
              <td style="width: 80%" class="font-weight-bold">Total Final</td>
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
