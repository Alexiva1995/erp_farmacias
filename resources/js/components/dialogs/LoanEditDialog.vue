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
    Math.floor((currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44)),
  );
  const installmentsPaid = Math.min(
    monthsPassed,
    formData.value.total_installments,
  );
  const remainingInstallments = Math.max(
    0,
    formData.value.total_installments - installmentsPaid,
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
    Math.floor((currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44)),
  );
  const remainingMonths = Math.max(
    0,
    formData.value.total_installments - monthsPassed,
  );
  const progressPercentage = Math.min(
    100,
    (monthsPassed / formData.value.total_installments) * 100,
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
  { deep: true },
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
  { deep: true, immediate: true },
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
      <!-- Header Estilizado -->
      <VCardTitle class="d-flex align-center pa-4 bg-primary text-white">
        <VIcon icon="tabler-credit-card" size="24" color="white" class="me-2" />
        <span class="text-h5 font-weight-bold">
          {{ isNewLoan ? "Añadir Nuevo Préstamo" : "Editar Préstamo" }}
        </span>

        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-6" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <!-- Información Básica -->
          <div class="text-overline mb-4 text-primary font-weight-bold">
            Información del Préstamo
          </div>

          <VRow>
            <VCol cols="12">
              <AppDateTimePicker
                v-model="formData.loan_date"
                label="Fecha del Préstamo"
                placeholder="Seleccionar fecha"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-calendar"
                :error-messages="formErrors.loan_date"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model.number="formData.monthly_payment"
                label="Cuota Mensual"
                type="number"
                step="0.01"
                min="0"
                prefix="$"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-currency-dollar"
                placeholder="Ej: 500.00"
                :error-messages="formErrors.monthly_payment"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model.number="formData.total_installments"
                label="Cantidad de Cuotas"
                type="number"
                min="1"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-hash"
                placeholder="Ej: 36, 48, 60..."
                :error-messages="formErrors.total_installments"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12">
              <div class="text-caption text-disabled d-flex align-center">
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Número total de cuotas mensuales a pagar
              </div>
            </VCol>
          </VRow>

          <!-- Cálculos y Resumen -->
          <template
            v-if="formData.monthly_payment && formData.total_installments"
          >
            <div class="text-overline mt-6 mb-4 text-primary font-weight-bold">
              Resumen del Préstamo
            </div>

            <VCard variant="tonal" color="secondary" class="pa-4 rounded-lg">
              <VRow>
                <VCol cols="12" md="6">
                  <VCard variant="flat" class="text-center pa-2">
                    <div class="text-caption text-disabled mb-1">
                      Monto Total
                    </div>
                    <div class="text-h6 font-weight-bold primary--text">
                      {{ formatCurrency(calculateTotalAmount) }}
                    </div>
                  </VCard>
                </VCol>

                <VCol cols="12" md="6">
                  <VCard
                    variant="flat"
                    class="text-center pa-2"
                    :color="loanStatus.color + '-lighten-5'"
                  >
                    <div class="text-caption mb-1">Saldo Pendiente</div>
                    <div
                      class="text-h6 font-weight-bold"
                      :class="`text-${loanStatus.color}`"
                    >
                      {{ formatCurrency(calculateRemainingBalance) }}
                    </div>
                    <VChip
                      :color="loanStatus.color"
                      size="x-small"
                      label
                      class="mt-1"
                    >
                      {{ loanStatus.text }}
                    </VChip>
                  </VCard>
                </VCol>
              </VRow>

              <VRow class="mt-2">
                <VCol cols="6" md="3" class="text-center border-e">
                  <div class="text-caption text-disabled">Cuota Mensual</div>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ formatCurrency(formData.monthly_payment) }}
                  </div>
                </VCol>

                <VCol cols="6" md="3" class="text-center border-e">
                  <div class="text-caption text-disabled">Total Cuotas</div>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ formData.total_installments }}
                  </div>
                </VCol>

                <VCol cols="6" md="3" class="text-center border-e">
                  <div class="text-caption text-disabled">Pagadas</div>
                  <div class="text-subtitle-1 font-weight-bold text-success">
                    {{
                      loanInfo.monthsPassed > formData.total_installments
                        ? formData.total_installments
                        : loanInfo.monthsPassed
                    }}
                  </div>
                </VCol>

                <VCol cols="6" md="3" class="text-center">
                  <div class="text-caption text-disabled">Restantes</div>
                  <div class="text-subtitle-1 font-weight-bold text-warning">
                    {{ loanInfo.remainingMonths }}
                  </div>
                </VCol>
              </VRow>

              <!-- Gráfico de progreso del préstamo -->
              <template v-if="formData.loan_date">
                <div class="mt-4">
                  <div
                    class="d-flex justify-space-between text-caption text-disabled mb-1"
                  >
                    <span>Progreso del Préstamo</span>
                    <span
                      >{{ loanInfo.progressPercentage.toFixed(1) }}%
                      completado</span
                    >
                  </div>
                  <VProgressLinear
                    :model-value="loanInfo.progressPercentage"
                    :color="loanStatus.color"
                    height="8"
                    rounded
                  />
                </div>
              </template>
            </VCard>
          </template>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1"
          :disabled="
            !formData.loan_date ||
            !formData.monthly_payment ||
            !formData.total_installments
          "
        >
          {{ isNewLoan ? "Crear" : "Actualizar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
