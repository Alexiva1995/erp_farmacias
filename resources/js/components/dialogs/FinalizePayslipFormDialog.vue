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
  () => props.selectedPayslip,
  () => {
    if (props.selectedPayslip) {
      fetchExchangeRate();
    }
  }
);
</script>
<template>
  <VDialog
    :model-value="props.modalValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"> Finalizar nómina </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="4">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Total</span>
              <VChip color="primary" label
                >{{ selectedPayslip?.total }} $</VChip
              >
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="4">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Total</span>
              <VChip color="primary" label
                >{{
                  Intl.NumberFormat("es-Ve", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  }).format(selectedPayslip?.total * exchangeRate)
                }}
                Bs.</VChip
              >
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="4">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Fecha</span>
              <VChip color="primary" label>{{
                selectedPayslip?.payslip_date
              }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="6">
            <VSelect
              v-model="currency"
              label="Moneda"
              variant="outlined"
              hide-details="auto"
              item-title="title"
              item-value="value"
              :items="
                Object.keys(countsFilterByCurrency).map((currency) => ({
                  title: currency,
                  value: currency,
                }))
              "
              :error-messages="errors.currency"
            />
          </VCol>
          <VCol cols="6">
            <VSelect
              v-model="count"
              label="Cuenta"
              variant="outlined"
              hide-details="auto"
              item-title="title"
              item-value="value"
              :items="
                (
                  countsFilterByCurrency[currency] ?? [
                    ...new Set(Object.values(countsFilterByCurrency).flat()),
                  ]
                ).map((account) => ({
                  title: account,
                  value: account,
                }))
              "
              :error-messages="errors.count"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="payed"
              label="Monto a pagar"
              type="number"
              variant="outlined"
              hide-details="auto"
              :step="0.01"
              :error-messages="errors.payed"
            />
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
          @click="submit"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
