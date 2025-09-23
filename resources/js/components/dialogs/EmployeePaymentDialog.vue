<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currency: { type: String, default: null },
  selectedEmployee: { type: Object, default: null },
});

const emit = defineEmits(["register-payment"]);

const selectedPayment = ref(null);
const date = new Date();

const options = [
  {
    title: "Vacaciones",
    value: "vacation_voucher",
  },
  {
    title: "Bono Vacacional",
    value: "vacation_bonus_voucher",
  },
  {
    title: "Utilidades",
    value: "earnings_voucher",
  },
];

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
            <VRow>
              <VCol cols="6" class="py-0">
                <p class="font-weight-bold">Total</p>
              </VCol>
              <VCol cols="6" class="py-0">
                <p class="text-right">
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
                <p class="text-right">
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
                <p class="text-right">
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
          Confirmar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
