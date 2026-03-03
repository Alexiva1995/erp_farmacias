<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modalValue: { type: Boolean, default: false },
  selectedPayslip: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "close", "refresh-table"]);

const errors = ref({});
const payed = ref(null);
const currency = ref(null);
const count = ref(null);
const exchangeRate = ref(1);

const countsFilterByCurrency = {
  USD: ["Efectivo", "Binance", "Paypal"],
  COP: ["Efectivo", "Transferencia"],
  BS: ["Efectivo", "Tarjeta", "Pago móvil", "Transferencia"],
};

const formatCurrency = (amount) => {
  return (Number(amount) || 0)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
};

const fetchExchangeRate = async () => {
  try {
    const { data } = await axios.get("/finances/exchange-rates/consultOneBCV");

    exchangeRate.value = data.rate;
  } catch (error) {
    toast.error("No se pudo obtener la tasa del día");
  }
};

const closeDialog = () => {
  payed.value = null;
  currency.value = null;
  count.value = null;
  exchangeRate.value = null;
  errors.value = {};
  emit("close");
};

const submit = async () => {
  errors.value = {};
  try {
    const form = new FormData();
    form.append("_method", "PUT");
    form.append("count", count.value);
    form.append("currency", currency.value);
    form.append("payed", payed.value);

    const { data } = await axios.post(
      `/finances/payslips/${props.selectedPayslip.id}/finalize`,
      form
    );

    if (data.status) {
      toast.success("La nómina ha sido finalizada exitosamente");

      emit("refresh-table");
      closeDialog();
    } else {
      toast.error(
        "No se pudo actualizar el estado de la nómina, intente de nuevo"
      );
    }
  } catch (error) {
    toast.error("Hubo un error al actualizar el estado de la nómina");

    if (error.response.status === 422) {
      errors.value = error.response.data.errors;
    }
  }
};

watch(
  () => props.modalValue,
  (val) => {
    if (val && props.selectedPayslip) {
      fetchExchangeRate();
      currency.value = 'COP';
      count.value = 'Efectivo';
      payed.value = props.selectedPayslip.total_full_cop;
    }
  }
);
</script>
<template>
  <VDialog
    :model-value="props.modalValue"
    max-width="600"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard class="finalize-payslip-dialog glass-morphism overflow-hidden">
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between pa-6">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" rounded size="48" class="me-4 shadow-sm">
            <VIcon icon="tabler-currency-dollar-off" size="28" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-black text-high-emphasis">Finalizar Pago de Nómina</div>
            <div class="text-caption text-medium-emphasis">Registrar el desembolso final de salarios</div>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="tonal" color="secondary" size="small" @click="closeDialog" />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <!-- Info Cards -->
        <VRow class="mb-6">
          <VCol cols="12" sm="6">
            <VCard flat variant="tonal" color="success" class="rounded-lg pa-4 h-100">
              <div class="text-caption font-weight-bold text-uppercase mb-1 opacity-70">Total Completo (COP)</div>
              <div class="text-h4 font-weight-black mb-1">
                {{ formatCurrency(selectedPayslip?.total_full_cop) }}
              </div>
              <div class="text-caption">Monto sugerido para el pago total</div>
            </VCard>
          </VCol>
          <VCol cols="12" sm="6">
            <VCard flat variant="tonal" color="primary" class="rounded-lg pa-4 h-100">
              <div class="text-caption font-weight-bold text-uppercase mb-1 opacity-70">Total Legal (USD)</div>
              <div class="text-h4 font-weight-black mb-1">
                {{ selectedPayslip?.total }}
              </div>
              <div class="text-caption">Monto estipulado en moneda base</div>
            </VCard>
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="12">
            <div class="d-flex align-center mb-4">
              <VIcon icon="tabler-settings" class="me-2 text-primary" size="20" />
              <span class="text-subtitle-2 font-weight-bold">Configuración del Pago</span>
              <VDivider class="ms-4" />
            </div>
          </VCol>

          <VCol cols="12" sm="6">
            <p class="text-caption font-weight-medium mb-1 ms-1">Moneda del Pago</p>
            <VSelect
              v-model="currency"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              item-title="title"
              item-value="value"
              placeholder="Seleccione moneda"
              prepend-inner-icon="tabler-cash-banknote"
              :items="Object.keys(countsFilterByCurrency).map(c => ({ title: c, value: c }))"
              :error-messages="errors.currency"
              class="custom-field"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <p class="text-caption font-weight-medium mb-1 ms-1">Cuenta de Origen</p>
            <VSelect
              v-model="count"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              placeholder="Seleccione cuenta"
              prepend-inner-icon="tabler-wallet"
              :items="(countsFilterByCurrency[currency] ?? [...new Set(Object.values(countsFilterByCurrency).flat())]).map(a => ({ title: a, value: a }))"
              :error-messages="errors.count"
              class="custom-field"
            />
          </VCol>

          <VCol cols="12">
            <p class="text-caption font-weight-medium mb-1 ms-1">Monto Efectivo a Pagar</p>
            <VTextField
              v-model="payed"
              label="Monto"
              type="number"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              prepend-inner-icon="tabler-coin"
              prefix="+"
              :step="0.01"
              :error-messages="errors.payed"
              class="custom-field amount-input"
            />
            <p class="text-caption text-disabled mt-2 ms-1">
              * Ingrese el monto total que se descontará de la cuenta seleccionada.
            </p>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-light">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="px-8 font-weight-bold rounded-lg"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submit"
          class="px-8 font-weight-bold rounded-lg ms-3 shadow-sm"
          prepend-icon="tabler-check"
        >
          Confirmar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.finalize-payslip-dialog {
  border-radius: 20px !important;
}

.glass-morphism {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
  backdrop-filter: blur(10px);
}

.custom-field :deep(.v-field) {
  border-radius: 12px !important;
  background-color: rgba(var(--v-theme-surface), 0.5) !important;
}

.amount-input :deep(.v-field) {
  border: 1px solid rgba(var(--v-theme-primary), 0.2) !important;
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.bg-light {
  background-color: rgba(var(--v-theme-surface), 0.9) !important;
}

.opacity-70 {
  opacity: 0.7;
}

.shadow-sm {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 5%) !important;
}
</style>
