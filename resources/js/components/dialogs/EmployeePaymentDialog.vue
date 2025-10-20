<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currency: { type: String, default: null },
  selectedEmployee: { type: Object, default: null },
});

const emit = defineEmits(["register-payment"]);

const selectedPayment = ref(null);
const date = new Date();

const options = computed(() => {
  const lessThanYear = props.selectedEmployee?.active_years < 1;

  return [
    {
      title: "Vacaciones",
      value: "vacation_voucher",
      props: {
        disabled: lessThanYear,
      },
    },
    {
      title: "Bono Vacacional",
      value: "vacation_bonus_voucher",
      props: {
        disabled: lessThanYear,
      },
    },
    {
      title: "Utilidades",
      value: "earnings_voucher",
      props: {
        disabled: false, // Permitir selección siempre, validar en el mensaje
      },
    },
  ];
});

const getLastPaymentDate = (paymentType) => {
  switch (paymentType) {
    case "vacation_voucher":
      return props.selectedEmployee?.vacation_last_payment_date;
    case "vacation_bonus_voucher":
      return props.selectedEmployee?.bonus_last_payment_date;
    case "earnings_voucher":
      return props.selectedEmployee?.utilities_last_payment_date;
    default:
      return null;
  }
};

const getPaymentConceptName = (paymentType) => {
  switch (paymentType) {
    case "vacation_voucher":
      return "Vacaciones";
    case "vacation_bonus_voucher":
      return "Bono Vacacional";
    case "earnings_voucher":
      return "Utilidades";
    default:
      return "Concepto";
  }
};

const isPaymentAlreadyMade = (paymentType) => {
  switch (paymentType) {
    case "vacation_voucher":
      return props.selectedEmployee?.vacation_paid_this_year;
    case "vacation_bonus_voucher":
      return props.selectedEmployee?.bonus_paid_this_year;
    case "earnings_voucher":
      return props.selectedEmployee?.utilities_paid_this_year;
    default:
      return false;
  }
};

const closeDialog = () => {
  selectedPayment.value = "";
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
          Pago a empleado ({{ props.selectedEmployee.name }}
          {{ props.selectedEmployee.last_name }})</span
        >
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12">
            <VSelect
              v-model="selectedPayment"
              label="Pago"
              variant="outlined"
              hide-details="auto"
              :items="options"
            />
          </VCol>
          <VCol v-if="selectedPayment" cols="12">
            <!-- Mensaje de pago ya realizado -->
            <VAlert
              v-if="isPaymentAlreadyMade(selectedPayment)"
              type="warning"
              variant="tonal"
              class="mb-4"
              prominent
            >
              <VAlertTitle class="text-h6">
                <VIcon icon="tabler-alert-triangle" class="me-2" />
                Pago ya realizado
              </VAlertTitle>
              <div class="mt-2">
                <strong>{{ getPaymentConceptName(selectedPayment) }}</strong> ya
                fue pagado para este empleado en el año actual ({{
                  new Date().getFullYear()
                }}).
              </div>
              <div class="mt-2">
                <strong>Fecha del último pago:</strong>
                {{ getLastPaymentDate(selectedPayment) || "No disponible" }}
              </div>
              <div class="text-caption mt-2">
                No se pueden realizar pagos duplicados del mismo concepto en el
                mismo año.
              </div>
            </VAlert>

            <VRow>
              <VCol cols="6" class="py-0">
                <p class="font-weight-bold">Total</p>
              </VCol>
              <VCol cols="6" class="py-0">
                <p
                  class="text-right"
                  :class="{
                    'text-disabled': isPaymentAlreadyMade(selectedPayment),
                  }"
                >
                  {{
                    Intl.NumberFormat("es-VE", {
                      maximumFractionDigits: 2,
                      minimumFractionDigits: 2,
                    }).format(props.selectedEmployee[selectedPayment])
                  }}
                  $
                </p>
              </VCol>
            </VRow>
            <VRow>
              <VCol cols="6" class="py-0">
                <p class="font-weight-bold">
                  Tasa BCV ({{
                    Intl.DateTimeFormat("es-Ve", {
                      day: "2-digit",
                      month: "2-digit",
                      year: "numeric",
                    }).format(date)
                  }})
                </p>
              </VCol>
              <VCol cols="6" class="py-0">
                <p
                  class="text-right"
                  :class="{
                    'text-disabled': isPaymentAlreadyMade(selectedPayment),
                  }"
                >
                  {{
                    Intl.NumberFormat("es-Ve", {
                      maximumFractionDigits: 2,
                      minimumFractionDigits: 2,
                    }).format(Number(props.currency))
                  }}
                  Bs.
                </p>
              </VCol>
            </VRow>
            <VRow>
              <VCol cols="6" class="py-0">
                <p class="font-weight-bold">Total bs</p>
              </VCol>
              <VCol cols="6" class="py-0">
                <p
                  class="text-right"
                  :class="{
                    'text-disabled': isPaymentAlreadyMade(selectedPayment),
                  }"
                >
                  {{
                    Intl.NumberFormat("es-Ve", {
                      maximumFractionDigits: 2,
                      minimumFractionDigits: 2,
                    }).format(
                      Number(props.selectedEmployee[selectedPayment]) *
                        Number(props.currency)
                    )
                  }}
                  Bs.
                </p>
              </VCol>
            </VRow>
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
          :disabled="isPaymentAlreadyMade(selectedPayment)"
          @click="
            emit(
              'register-payment',
              selectedEmployee.id,
              selectedPayment,
              props.selectedEmployee[selectedPayment]
            );
            selectedPayment = null;
          "
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          <VIcon
            :icon="
              isPaymentAlreadyMade(selectedPayment)
                ? 'tabler-ban'
                : 'tabler-check'
            "
            class="me-2"
          />
          {{
            isPaymentAlreadyMade(selectedPayment)
              ? "Pago ya realizado"
              : "Confirmar"
          }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
