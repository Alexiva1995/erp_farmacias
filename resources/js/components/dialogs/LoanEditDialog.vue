<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loan: { type: Object, default: () => ({}) },
  loanYears: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const { mobile } = useDisplay();
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
    ((monthsPassed > formData.value.total_installments ? formData.value.total_installments : monthsPassed) / formData.value.total_installments) * 100,
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
    return { text: "Completado", color: "success", icon: "tabler-circle-check" };
  } else if (info.monthsPassed >= formData.value.total_installments) {
    return { text: "Vencido", color: "error", icon: "tabler-alert-circle" };
  } else if (info.remainingMonths <= 3) {
    return { text: "Por Vencer", color: "warning", icon: "tabler-clock-hour-4" };
  } else {
    return { text: "Activo", color: "info", icon: "tabler-progress" };
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
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border shadow-sm overlap-overflow">
      <!-- Header Premium -->
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b bg-surface">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded class="rounded-lg shadow-sm">
            <VIcon icon="tabler-credit-card" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-black mb-0 uppercase leading-none">
              {{ isNewLoan ? "Añadir Préstamo" : "Editar Préstamo" }}
            </h3>
            <span class="text-xs text-disabled font-weight-medium uppercase">Gestión de obligaciones financieras</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeDialog" />
      </VCardTitle>

      <VCardText class="pa-6">
        <VForm @submit.prevent="submitForm">
          <!-- Información Básica -->
          <div class="d-flex align-center gap-2 mb-4">
            <VIcon icon="tabler-info-circle" size="18" color="primary" />
            <span class="text-subtitle-2 font-weight-black uppercase text-primary">Detalles del Préstamo</span>
          </div>

          <VRow>
            <VCol cols="12">
              <AppDateTimePicker
                v-model="formData.loan_date"
                label="Fecha de Inicio del Préstamo"
                placeholder="Seleccionar fecha"
                prepend-inner-icon="tabler-calendar"
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
                :error-messages="formErrors.loan_date"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
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
                prepend-inner-icon="tabler-currency-dollar"
                placeholder="Ej: 500.00"
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
                :error-messages="formErrors.monthly_payment"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model.number="formData.total_installments"
                label="Cantidad de Cuotas"
                type="number"
                min="1"
                prepend-inner-icon="tabler-hash"
                placeholder="Ej: 36, 48, 60..."
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
                :error-messages="formErrors.total_installments"
              />
            </VCol>
          </VRow>

          <!-- Cálculos y Resumen -->
          <template v-if="formData.monthly_payment && formData.total_installments">
            <div class="d-flex align-center gap-2 mt-8 mb-4">
              <VIcon icon="tabler-calculator" size="18" color="primary" />
              <span class="text-subtitle-2 font-weight-black uppercase text-primary">Resumen Financiero</span>
            </div>

            <VCard variant="tonal" color="secondary" class="rounded-xl border-dashed overflow-hidden">
              <VCardText class="pa-4 bg-surface-variant bg-opacity-10">
                <VRow>
                  <VCol cols="6" class="pr-2">
                    <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Monto Total</div>
                    <div class="text-h6 font-weight-black text-primary">{{ formatCurrency(calculateTotalAmount) }}</div>
                  </VCol>

                  <VCol cols="6" class="text-right border-l-dashed pl-4">
                    <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Saldo Pendiente</div>
                    <div :class="`text-h6 font-weight-black text-${loanStatus.color}`">
                      {{ formatCurrency(calculateRemainingBalance) }}
                    </div>
                  </VCol>
                </VRow>

                <VDivider class="my-3 border-dashed" />

                <VRow class="text-center">
                  <VCol cols="3" class="border-e-dashed">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Mensual</div>
                    <div class="text-body-2 font-weight-bold">{{ formatCurrency(formData.monthly_payment) }}</div>
                  </VCol>
                  <VCol cols="3" class="border-e-dashed">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Pagadas</div>
                    <div class="text-body-2 font-weight-bold text-success">{{ Math.min(loanInfo.monthsPassed, formData.total_installments) }}</div>
                  </VCol>
                  <VCol cols="3" class="border-e-dashed">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Restan</div>
                    <div class="text-body-2 font-weight-bold text-warning">{{ loanInfo.remainingMonths }}</div>
                  </VCol>
                  <VCol cols="3">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Estado</div>
                    <VChip :color="loanStatus.color" size="x-small" label class="font-weight-bold">
                      {{ loanStatus.text }}
                    </VChip>
                  </VCol>
                </VRow>

                <div v-if="formData.loan_date" class="mt-4">
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-xs text-disabled font-weight-bold uppercase">Progreso de Pago</span>
                    <span class="text-xs font-weight-black text-primary">{{ loanInfo.progressPercentage.toFixed(1) }}%</span>
                  </div>
                  <VProgressLinear
                    :model-value="loanInfo.progressPercentage"
                    :color="loanStatus.color"
                    height="10"
                    rounded
                    class="rounded-pill"
                  />
                  <div class="d-flex justify-space-between text-xs font-weight-bold text-disabled mt-1">
                    <span>INICIO</span>
                    <span>FINAL ({{ formData.total_installments }} CUOTAS)</span>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </template>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4 px-6 border-t bg-surface">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="rounded-lg flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="rounded-lg flex-grow-1"
          :disabled="!formData.loan_date || !formData.monthly_payment || !formData.total_installments"
        >
          {{ isNewLoan ? "Crear Préstamo" : "Guardar Cambios" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.border-dashed {
  border-style: dashed !important;
  opacity: 1;
}

.border-l-dashed {
  border-inline-start: 1px dashed rgba(var(--v-border-color), 0.15);
}

.border-e-dashed {
  border-inline-end: 1px dashed rgba(var(--v-border-color), 0.15);
}

.bg-surface-variant {
  background-color: rgb(var(--v-theme-surface-variant));
}

.leading-none {
  line-height: 1 !important;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}
</style>
