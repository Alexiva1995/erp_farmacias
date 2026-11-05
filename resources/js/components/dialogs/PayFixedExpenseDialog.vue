<script setup lang="js">
import { computed, reactive, watch } from "vue";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  occurrence: { type: Object, default: () => null },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const authStore = useAuthStore();

const paymentMethods = [
  "Efectivo",
  "Tarjeta",
  "Pago Móvil",
  "Transferencia",
  "Binance",
  "PayPal",
  "Cambista",
];

const form = reactive({
  name: "",
  category_id: null,
  total_amount: "",
  total_usd: "",
  currency: "USD",
  count: "Efectivo",
  expense_date: new Date().toISOString().substring(0, 10),
  has_invoice: false,
  is_deductible: false,
  iva: false,
  user_id: null,
  exempt_amount: 0,
  taxable_base: 0,
  tax_amount: 0,
  exchange_rate: 0,
  amount_bs: 0,
  conversion_rate: 0,
});

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen && props.occurrence) {
      const occ = props.occurrence;
      const fe = occ.fixed_expense || {};

      form.name = `Pago: ${fe.name || "Gasto Fijo"}`;
      form.category_id = fe.category?.id || null;
      form.total_amount = occ.amount || "";
      form.total_usd = occ.currency === "USD" ? occ.amount : 0;
      form.currency = occ.currency || "USD";
      form.count = fe.default_count || "Efectivo";
      form.expense_date = new Date().toISOString().substring(0, 10);
      form.has_invoice = false;
      form.is_deductible = false;
      form.iva = false;
      form.user_id = authStore.user?.id || 1;
      form.exempt_amount = Number(occ.amount) || 0;
      form.taxable_base = 0;
      form.tax_amount = 0;
      form.exchange_rate = 0;
      form.amount_bs = occ.currency === "BS" ? occ.amount : 0;
      form.conversion_rate = 0;
    }
  }
);

watch(
  () => form.total_amount,
  (val) => {
    const num = Number(val) || 0;
    form.exempt_amount = num;
    if (form.currency === "USD") {
      form.total_usd = num;
    } else if (form.currency === "BS") {
      form.amount_bs = num;
    }
  }
);

function close() {
  emit("update:modelValue", false);
}

function submit() {
  if (!form.total_amount || Number(form.total_amount) <= 0 || !form.count) {
    return;
  }

  emit("confirm", {
    occurrenceId: props.occurrence.id,
    data: {
      ...form,
      total_amount: Number(form.total_amount),
      total_usd: Number(form.total_usd) || Number(form.total_amount),
      exempt_amount: Number(form.exempt_amount) || Number(form.total_amount),
      user_id: authStore.user?.id || 1,
    },
  });
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    persistent
    @update:model-value="(val) => emit('update:modelValue', val)"
  >
    <VCard class="pa-2">
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar color="success" variant="tonal" class="me-2">
            <VIcon icon="tabler-cash-register" size="24" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          Registrar Pago de Gasto Fijo
        </VCardTitle>
        <VCardSubtitle v-if="occurrence">
          Compromiso: <strong>{{ occurrence.fixed_expense?.name }}</strong>
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <!-- Alerta de vencimiento -->
        <VAlert
          v-if="occurrence?.is_overdue"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <div class="d-flex align-center justify-space-between">
            <span>Esta cuota venció el <strong>{{ occurrence.due_date }}</strong>.</span>
            <VChip size="x-small" color="error" variant="flat">Vencido</VChip>
          </div>
        </VAlert>

        <VAlert
          v-else-if="occurrence"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Fecha programada de pago: <strong>{{ occurrence.due_date }}</strong>
        </VAlert>

        <VRow dense>
          <!-- Monto a pagar -->
          <VCol cols="12" sm="7">
            <VTextField
              v-model="form.total_amount"
              type="number"
              min="0.01"
              step="0.01"
              label="Monto a pagar *"
              :prefix="form.currency === 'USD' ? '$' : form.currency === 'BS' ? 'Bs.' : 'COP'"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Moneda -->
          <VCol cols="12" sm="5">
            <VTextField
              :model-value="form.currency"
              label="Moneda"
              readonly
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Método de pago / Cuenta -->
          <VCol cols="12" sm="6">
            <VSelect
              v-model="form.count"
              :items="paymentMethods"
              label="Método de pago / Cuenta *"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Fecha de pago -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.expense_date"
              type="date"
              label="Fecha de pago *"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Opciones fiscales -->
          <VCol cols="12" sm="6">
            <VCheckbox
              v-model="form.has_invoice"
              label="Tiene factura física / digital"
              density="compact"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VCheckbox
              v-model="form.is_deductible"
              label="Gasto deducible de impuestos"
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pt-0 px-4 pb-3">
        <VSpacer />
        <VBtn variant="text" color="secondary" @click="close">
          Cancelar
        </VBtn>
        <VBtn
          color="success"
          variant="elevated"
          :loading="loading"
          :disabled="loading || !form.total_amount || Number(form.total_amount) <= 0"
          @click="submit"
        >
          Confirmar Pago y Registrar Gasto
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
