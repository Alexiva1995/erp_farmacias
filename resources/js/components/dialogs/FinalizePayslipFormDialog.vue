<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modalValue: { type: Boolean, default: false },
  selectedPayslip: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "close", "refresh-table"]);

const errors = ref({});
const payedDisplay = ref(""); // Para el formateo visual con puntos
const currency = ref(null);
const count = ref(null);
const exchangeRate = ref(1);

const payed = computed({
  get: () => {
    // Remove dots for thousands separator and replace comma with dot for decimal
    return payedDisplay.value.replace(/\./g, "").replace(",", ".");
  },
  set: (val) => {
    if (!val) {
      payedDisplay.value = "";
      return;
    }
    // Format with thousands separator dots
    payedDisplay.value = Math.round(val)
      .toString()
      .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }
});

const handlePayedInput = (e) => {
  const val = e.target.value.replace(/\D/g, ""); // Remove non-digits
  payedDisplay.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, "."); // Add thousands separator
};

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
  payedDisplay.value = "";
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
    form.append("payed", payed.value); // Use the computed value

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

    if (error.response?.status === 422) {
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
      payed.value = props.selectedPayslip.total_full_cop; // Use the computed setter
    }
  }
);
</script>
<template>
  <VDialog
    :model-value="props.modalValue"
    max-width="500"
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
            <div class="text-h5 font-weight-black text-high-emphasis">Finalizar Pago</div>
            <div class="text-caption text-medium-emphasis">Registrar desembolso en COP</div>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="tonal" color="secondary" size="small" @click="closeDialog" />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <!-- Info Cards - Only COP -->
        <VRow class="mb-6">
          <VCol cols="12">
            <VCard flat variant="tonal" color="success" class="rounded-lg pa-4 text-center">
              <div class="text-caption font-weight-bold text-uppercase mb-1 opacity-70">Monto Total a Pagar (COP)</div>
              <div class="text-h3 font-weight-black mb-1">
                {{ formatCurrency(selectedPayslip?.total_full_cop) }}
              </div>
              <div class="text-caption">Basado en Paquete Salarial + Bono</div>
            </VCard>
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="12" sm="6">
            <p class="text-caption font-weight-medium mb-1 ms-1">Moneda</p>
            <VSelect
              v-model="currency"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              placeholder="Seleccione moneda"
              prepend-inner-icon="tabler-cash-banknote"
              :items="Object.keys(countsFilterByCurrency).map(c => ({ title: c, value: c }))"
              :error-messages="errors.currency"
              class="custom-field"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <p class="text-caption font-weight-medium mb-1 ms-1">Cuenta</p>
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
            <p class="text-caption font-weight-medium mb-1 ms-1">Confirmar Monto (COP)</p>
            <VTextField
              v-model="payedDisplay"
              placeholder="0"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              prepend-inner-icon="tabler-coin"
              prefix="+"
              suffix="COP"
              :error-messages="errors.payed"
              class="custom-field amount-input"
              @input="handlePayedInput"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-light d-flex">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="flex-grow-1 font-weight-bold rounded-lg py-3"
          height="48"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submit"
          class="flex-grow-1 font-weight-bold rounded-lg ms-3 shadow-sm py-3"
          height="48"
          prepend-icon="tabler-check"
        >
          Confirmar
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

.amount-input :deep(input) {
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 1.25rem !important;
  font-weight: 700 !important;
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
