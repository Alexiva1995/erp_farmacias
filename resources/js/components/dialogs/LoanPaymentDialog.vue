<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loan: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const { mobile } = useDisplay();
const formData = ref({});
const formErrors = ref({});

const paymentMethods = ["Efectivo", "Binance", "PayPal", "Transferencia"];

const formatCurrency = (amount) => {
  if (typeof amount !== "number") return "$0.00";
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("es-ES", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
  } catch (error) {
    return "";
  }
};

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
      // Sugerir por defecto el menor entre la cuota mensual y el saldo restante
      const suggestedAmount = newLoan.remaining_balance !== undefined
        ? Math.min(newLoan.monthly_payment, newLoan.remaining_balance)
        : newLoan.monthly_payment;

      formData.value = {
        amount: suggestedAmount || null,
        payment_date: new Date().toISOString().split("T")[0],
        account: "Efectivo",
      };
    } else {
      formData.value = {
        amount: null,
        payment_date: new Date().toISOString().split("T")[0],
        account: "Efectivo",
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
    amount: parseFloat(formData.value.amount),
    payment_date: formData.value.payment_date,
    account: formData.value.account,
  };

  emit("save", payload);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="550px"
    persistent
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="props.loan" class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-currency-dollar" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Registrar Abono
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Préstamo #{{ props.loan.id }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-light">
        <VForm @submit.prevent="submitForm">
          <!-- Resumen de estado actual del préstamo -->
          <VCard variant="flat" class="pa-3 bg-white rounded-lg border shadow-sm mb-3">
            <VRow dense>
              <VCol cols="6">
                <span class="text-super-xs font-weight-bold text-disabled uppercase d-block mb-1">Saldo Restante</span>
                <span class="text-subtitle-1 font-weight-black text-error">
                  {{ formatCurrency(props.loan.remaining_balance) }}
                </span>
              </VCol>
              <VCol cols="6" class="text-right">
                <span class="text-super-xs font-weight-bold text-disabled uppercase d-block mb-1">Cuota Mensual</span>
                <span class="text-subtitle-1 font-weight-black text-primary">
                  {{ formatCurrency(props.loan.monthly_payment) }}
                </span>
              </VCol>
            </VRow>
            <VDivider class="my-2 border-dashed" />
            <div class="d-flex justify-space-between text-xs text-disabled font-weight-medium">
              <span>Inició: {{ formatDate(props.loan.loan_date) }}</span>
              <span>Cuotas: {{ props.loan.total_installments }}</span>
            </div>
          </VCard>

          <!-- Formulario -->
          <div class="d-flex align-center gap-2 mb-2">
            <div class="header-indicator primary shadow-sm" />
            <span class="text-subtitle-2 font-weight-bold text-high-emphasis uppercase letter-spacing-1">Detalles del Pago</span>
          </div>

          <VCard variant="flat" class="pa-3 bg-white rounded-lg border shadow-sm">
            <VRow dense>
              <VCol cols="12">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Monto del Abono</span>
                <VTextField
                  v-model.number="formData.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  :max="props.loan.remaining_balance"
                  prefix="$"
                  placeholder="0.00"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg font-weight-bold"
                  :error-messages="formErrors.amount"
                />
              </VCol>

              <VCol cols="12">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha de Pago</span>
                <AppDateTimePicker
                  v-model="formData.payment_date"
                  placeholder="Seleccionar fecha..."
                  prepend-inner-icon="tabler-calendar"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg font-weight-bold"
                  :error-messages="formErrors.payment_date"
                  :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </VCol>

              <VCol cols="12">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Método de Pago / Cuenta</span>
                <VSelect
                  v-model="formData.account"
                  :items="paymentMethods"
                  prepend-inner-icon="tabler-wallet"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg font-weight-bold"
                  :error-messages="formErrors.account"
                />
              </VCol>
            </VRow>
          </VCard>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 bg-white border-t px-4">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
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
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :loading="props.submitting"
              :disabled="!formData.amount || !formData.payment_date || !formData.account || props.submitting"
              @click="submitForm"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Confirmar Abono
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

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}
</style>
