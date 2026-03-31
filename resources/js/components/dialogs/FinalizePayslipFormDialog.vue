<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  modalValue: { type: Boolean, default: false },
  selectedPayslip: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "close", "refresh-table"]);

const errors = ref({});
const loading = ref(false); // Estado de carga para el botón
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
  loading.value = false;
  emit("close");
};

const submit = async () => {
  errors.value = {};
  loading.value = true; // Activar spinner
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
  } finally {
    loading.value = false; // Desactivar spinner
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
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-currency-dollar-off"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Finalizar Pago
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Cierre de Nómina en Moneda Local
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Resumen Financiero Destacado -->
        <VCard
          variant="flat"
          class="rounded-xl border shadow-sm mb-6 bg-white overflow-hidden"
        >
          <div class="pa-5 d-flex flex-column align-center text-center">
            <VAvatar
              color="success"
              variant="tonal"
              size="48"
              class="mb-3"
            >
              <VIcon
                icon="tabler-receipt-2"
                size="28"
              />
            </VAvatar>
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Monto Total a Desembolsar</span>
            <h3 class="text-h3 font-weight-black text-success leading-none mb-1">
              {{ formatCurrency(selectedPayslip?.total_full_cop) }}
            </h3>
            <span class="text-super-xs text-disabled uppercase font-weight-bold">Paquete Salarial + Bonificaciones</span>
          </div>
        </VCard>

        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración del Pago</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-lg elevation-1 border"
        >
          <VRow dense>
            <VCol
              cols="12"
              sm="6"
            >
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Moneda</span>
              <VSelect
                v-model="currency"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                placeholder="Seleccione moneda"
                prepend-inner-icon="tabler-cash-banknote"
                :items="Object.keys(countsFilterByCurrency).map(c => ({ title: c, value: c }))"
                :error-messages="errors.currency"
                class="premium-input mb-4"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Cuenta Origen</span>
              <VSelect
                v-model="count"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                placeholder="Seleccione cuenta"
                prepend-inner-icon="tabler-wallet"
                :items="(countsFilterByCurrency[currency] ?? [...new Set(Object.values(countsFilterByCurrency).flat())]).map(a => ({ title: a, value: a }))"
                :error-messages="errors.count"
                class="premium-input mb-4"
              />
            </VCol>

            <VCol cols="12">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Confirmar Monto en COP</span>
              <VTextField
                v-model="payedDisplay"
                placeholder="0"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-coin"
                prefix="$"
                suffix="COP"
                :error-messages="errors.payed"
                class="premium-amount-input"
                @input="handlePayedInput"
              />
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <!-- Botones de Acción -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="loading"
              :disabled="loading"
              @click="submit"
            >
              <VIcon
                start
                icon="tabler-check"
                size="18"
                class="me-2"
              />
              Confirmar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.premium-amount-input :deep(.v-field) {
  border: 1px solid rgba(var(--v-theme-primary), 0.2) !important;
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.premium-amount-input :deep(input) {
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 1.25rem !important;
  font-weight: 800 !important;
}
</style>
