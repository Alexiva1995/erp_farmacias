<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loan: { type: Object, default: () => ({}) },
  loanYears: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({});
const formErrors = ref({});

const isNewLoan = computed(() => !formData.value.id);

const calculateTotalAmount = computed(() => {
  if (!formData.value.monthly_payment || !formData.value.total_installments) {
    return 0;
  }

  return formData.value.monthly_payment * formData.value.total_installments;
});

const calculateRemainingBalance = computed(() => {
  if (
    !formData.value.loan_date ||
    !formData.value.monthly_payment ||
    !formData.value.total_installments
  ) {
    return 0;
  }

  const currentDate = new Date();
  const loanDate = new Date(formData.value.loan_date);
  const monthsPassed = Math.max(
    0,
    Math.floor((currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44))
  );
  const installmentsPaid = Math.min(
    monthsPassed,
    formData.value.total_installments
  );
  const remainingInstallments = Math.max(
    0,
    formData.value.total_installments - installmentsPaid
  );

  return formData.value.monthly_payment * remainingInstallments;
});

const formatCurrency = (amount) => {
  if (typeof amount !== "number") return "$0.00";
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const loanInfo = computed(() => {
  if (!formData.value.loan_date || !formData.value.total_installments) {
    return { monthsPassed: 0, remainingMonths: 0, progressPercentage: 0 };
  }

  const currentDate = new Date();
  const loanDate = new Date(formData.value.loan_date);
  const monthsPassed = Math.max(
    0,
    Math.floor((currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44))
  );
  const remainingMonths = Math.max(
    0,
    formData.value.total_installments - monthsPassed
  );
  const progressPercentage = Math.min(
    100,
    (monthsPassed / formData.value.total_installments) * 100
  );

  return {
    monthsPassed,
    remainingMonths,
    progressPercentage,
  };
});

const loanStatus = computed(() => {
  const remaining = calculateRemainingBalance.value;
  const info = loanInfo.value;

  if (remaining <= 0) {
    return { text: "Completado", color: "success" };
  } else if (info.monthsPassed >= formData.value.total_installments) {
    return { text: "Vencido", color: "error" };
  } else if (info.remainingMonths <= 3) {
    return { text: "Por Vencer", color: "warning" };
  } else {
    return { text: "Activo", color: "info" };
  }
});

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true }
);

watch(
  () => props.loan,
  (newLoan) => {
    if (newLoan && Object.keys(newLoan).length > 0) {
      formData.value = JSON.parse(JSON.stringify(newLoan));
    } else {
      formData.value = {
        loan_date: new Date().toISOString().split("T")[0],
        monthly_payment: null,
        total_installments: null,
      };
    }
    formErrors.value = {};
  },
  { deep: true, immediate: true }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const payload = {
    loan_date: formData.value.loan_date,
    monthly_payment: parseFloat(formData.value.monthly_payment),
    total_installments: parseInt(formData.value.total_installments),
  };

  emit("save", payload);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard v-if="formData" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-credit-card" class="mr-3" color="purple" />
        <span class="text-h5 font-weight-bold">{{
          isNewLoan ? "Añadir Nuevo Préstamo" : "Editar Préstamo"
        }}</span>

        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <!-- Información Básica -->
          <div class="mb-6">
            <p class="text-h6 font-weight-medium mb-4">
              Información del Préstamo
            </p>

            <VRow>
              <VCol cols="12">
                <AppDateTimePicker
                  v-model="formData.loan_date"
                  label="Fecha del Préstamo"
                  placeholder="Seleccionar fecha"
                  prepend-inner-icon="tabler-calendar"
                  :error-messages="formErrors.loan_date"
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="formData.monthly_payment"
                  label="Cuota Mensual"
                  type="number"
                  step="0.01"
                  min="0"
                  prefix="$"
                  variant="outlined"
                  prepend-inner-icon="tabler-currency-dollar"
                  placeholder="Ej: 500.00"
                  :error-messages="formErrors.monthly_payment"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="formData.total_installments"
                  label="Cantidad de Cuotas"
                  type="number"
                  min="1"
                  variant="outlined"
                  prepend-inner-icon="tabler-hash"
                  placeholder="Ej: 36, 48, 60..."
                  :error-messages="formErrors.total_installments"
                />
                <div class="text-caption text-disabled mt-1">
                  Número total de cuotas mensuales a pagar
                </div>
              </VCol>
            </VRow>
          </div>

          <!-- Cálculos y Resumen -->
          <template
            v-if="formData.monthly_payment && formData.total_installments"
          >
            <VDivider class="my-6" />

            <VSheet
              color="grey-lighten-4"
              variant="tonal"
              rounded="lg"
              class="pa-6"
            >
              <p class="text-h6 font-weight-medium mb-4 d-flex align-center">
                <VIcon icon="tabler-calculator" class="mr-2" />
                Resumen del Préstamo
              </p>

              <VRow>
                <VCol cols="12" md="6">
                  <VCard variant="outlined">
                    <VCardText class="text-center">
                      <div class="text-body-2 text-disabled mb-1">
                        Monto Total
                      </div>
                      <div class="text-h6 font-weight-bold">
                        {{ formatCurrency(calculateTotalAmount) }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>

                <VCol cols="12" md="6">
                  <VCard variant="outlined" :color="loanStatus.color">
                    <VCardText class="text-center">
                      <div class="text-body-2 mb-1">Saldo Pendiente</div>
                      <div class="text-h6 font-weight-bold">
                        {{ formatCurrency(calculateRemainingBalance) }}
                      </div>
                      <VChip
                        :color="loanStatus.color"
                        size="small"
                        class="mt-2"
                      >
                        {{ loanStatus.text }}
                      </VChip>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <VRow class="mt-4">
                <VCol cols="12" md="3">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">Cuota Mensual</div>
                    <div class="text-h6 font-weight-bold">
                      {{ formatCurrency(formData.monthly_payment) }}
                    </div>
                  </div>
                </VCol>

                <VCol cols="12" md="3">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">Total Cuotas</div>
                    <div class="text-h6 font-weight-bold">
                      {{ formData.total_installments }}
                    </div>
                  </div>
                </VCol>

                <VCol cols="12" md="3">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">Cuotas Pagadas</div>
                    <div class="text-h6 font-weight-bold">
                      {{
                        loanInfo.monthsPassed > formData.total_installments
                          ? formData.total_installments
                          : loanInfo.monthsPassed
                      }}
                    </div>
                  </div>
                </VCol>

                <VCol cols="12" md="3">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">
                      Cuotas Restantes
                    </div>
                    <div class="text-h6 font-weight-bold">
                      {{ loanInfo.remainingMonths }}
                    </div>
                  </div>
                </VCol>
              </VRow>

              <!-- Gráfico de progreso del préstamo -->
              <template v-if="formData.loan_date">
                <div class="mt-6">
                  <div class="text-body-2 text-disabled mb-2">
                    Progreso del Préstamo
                  </div>
                  <VProgressLinear
                    :model-value="loanInfo.progressPercentage"
                    :color="loanStatus.color"
                    height="8"
                    rounded
                  />
                  <div
                    class="d-flex justify-space-between text-caption text-disabled mt-1"
                  >
                    <span>Inicio</span>
                    <span
                      >{{ loanInfo.progressPercentage.toFixed(1) }}%
                      completado</span
                    >
                    <span>Final</span>
                  </div>
                </div>
              </template>
            </VSheet>
          </template>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
          :disabled="
            !formData.loan_date ||
            !formData.monthly_payment ||
            !formData.total_installments
          "
        >
          {{ isNewLoan ? "Crear" : "Actualizar" }} Préstamo
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
