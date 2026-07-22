<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loan: { type: Object, default: () => ({}) },
  loanYears: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
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
    <VCard v-if="formData" class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-credit-card" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              {{ isNewLoan ? "Añadir Préstamo" : "Editar Préstamo" }}
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Gestión de Obligaciones Financieras
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <VForm @submit.prevent="submitForm">
          <!-- Sección: Detalles del Préstamo -->
          <div class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator primary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Detalles del Préstamo</span>
          </div>

          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-6">
            <VRow>
              <VCol cols="12">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Fecha de Inicio</span>
                <AppDateTimePicker
                  v-model="formData.loan_date"
                  placeholder="Seleccionar fecha..."
                  prepend-inner-icon="tabler-calendar"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.loan_date"
                  :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </VCol>

              <VCol cols="12" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Cuota Mensual</span>
                <VTextField
                  v-model.number="formData.monthly_payment"
                  type="number"
                  step="0.01"
                  min="0"
                  prefix="$"
                  prepend-inner-icon="tabler-currency-dollar"
                  placeholder="Ej: 500.00"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.monthly_payment"
                />
              </VCol>

              <VCol cols="12" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Cantidad de Cuotas</span>
                <VTextField
                  v-model.number="formData.total_installments"
                  type="number"
                  min="1"
                  prepend-inner-icon="tabler-hash"
                  placeholder="Ej: 36, 48, 60..."
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.total_installments"
                />
              </VCol>
            </VRow>
          </VCard>

          <!-- Sección: Resumen Financiero -->
          <template v-if="formData.monthly_payment && formData.total_installments">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator secondary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Resumen Financiero</span>
            </div>

            <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
              <!-- Montos principales -->
              <VRow class="mb-4">
                <VCol cols="6">
                  <div class="pa-3 bg-light rounded-xl border-dashed-2 text-center">
                    <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-1">Monto Total</span>
                    <div class="text-h6 font-weight-black text-primary">{{ formatCurrency(calculateTotalAmount) }}</div>
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="pa-3 bg-light rounded-xl border-dashed-2 text-center">
                    <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-1">Saldo Pendiente</span>
                    <div :class="`text-h6 font-weight-black text-${loanStatus.color}`">
                      {{ formatCurrency(calculateRemainingBalance) }}
                    </div>
                  </div>
                </VCol>
              </VRow>

              <VDivider class="mb-4" />

              <!-- Métricas de cuotas -->
              <VRow class="text-center mb-4">
                <VCol cols="3">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Mensual</div>
                  <div class="text-subtitle-2 font-weight-black">{{ formatCurrency(formData.monthly_payment) }}</div>
                </VCol>
                <VCol cols="3">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Pagadas</div>
                  <div class="text-subtitle-2 font-weight-black text-success">{{ Math.min(loanInfo.monthsPassed, formData.total_installments) }}</div>
                </VCol>
                <VCol cols="3">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Restan</div>
                  <div class="text-subtitle-2 font-weight-black text-warning">{{ loanInfo.remainingMonths }}</div>
                </VCol>
                <VCol cols="3">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Estado</div>
                  <VChip :color="loanStatus.color" size="small" class="font-weight-black rounded-lg shadow-sm">
                    {{ loanStatus.text }}
                  </VChip>
                </VCol>
              </VRow>

              <!-- Barra de progreso -->
              <div v-if="formData.loan_date" class="pa-3 bg-light rounded-xl border-dashed-2">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">Progreso de Pago</span>
                  <span class="text-xs font-weight-black text-primary">{{ loanInfo.progressPercentage.toFixed(1) }}%</span>
                </div>
                <VProgressLinear
                  :model-value="loanInfo.progressPercentage"
                  :color="loanStatus.color"
                  height="10"
                  rounded
                  class="rounded-pill mb-1"
                />
                <div class="d-flex justify-space-between text-super-xs font-weight-black text-disabled">
                  <span>INICIO</span>
                  <span>FINAL ({{ formData.total_installments }} CUOTAS)</span>
                </div>
              </div>
            </VCard>
          </template>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              :disabled="props.submitting"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :loading="props.submitting"
              :disabled="!formData.loan_date || !formData.monthly_payment || !formData.total_installments || props.submitting"
              @click="submitForm"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              {{ isNewLoan ? "Crear Préstamo" : "Guardar Cambios" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}
</style>
