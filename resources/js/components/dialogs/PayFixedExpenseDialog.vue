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

const bsMethods = ["Efectivo", "Tarjeta", "Pago Móvil", "Transferencia", "Cambista"];
const usdMethods = ["Efectivo", "Binance", "PayPal", "Cambista"];
const copMethods = ["Efectivo", "Transferencia", "Cambista"];

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

const availablePaymentMethods = computed(() => {
  if (form.currency === "BS") return bsMethods;
  if (form.currency === "COP") return copMethods;
  return usdMethods;
});

const currencySymbol = computed(() => {
  const map = { BS: "Bs.", USD: "$", COP: "COP" };
  return map[form.currency] || "$";
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
      form.count = fe.default_count || availablePaymentMethods.value[0];
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
    max-width="700px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    transition="dialog-bottom-transition"
    @update:model-value="(val) => emit('update:modelValue', val)"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl bg-surface">
      <!-- Header del Sistema -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-cash-register"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Registrar Pago de Gasto Fijo
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                {{ occurrence?.fixed_expense?.name || "Confirmación de Egreso" }}
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
            @click="close"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-light">
        <!-- Estado de Vencimiento -->
        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-lg border shadow-sm mb-3 d-flex align-center justify-space-between flex-wrap gap-2"
        >
          <div class="d-flex align-center gap-2">
            <VAvatar
              :color="occurrence?.is_overdue ? 'error' : 'info'"
              variant="tonal"
              size="34"
              class="rounded-lg"
            >
              <VIcon
                :icon="occurrence?.is_overdue ? 'tabler-alert-triangle' : 'tabler-calendar-due'"
                size="18"
              />
            </VAvatar>
            <div>
              <div class="text-xs font-weight-black text-high-emphasis">
                {{ occurrence?.fixed_expense?.name }}
              </div>
              <div class="text-super-xs text-medium-emphasis">
                Vencimiento programado: <strong>{{ occurrence?.due_date }}</strong>
              </div>
            </div>
          </div>
          <VChip
            size="x-small"
            :color="occurrence?.is_overdue ? 'error' : 'info'"
            variant="tonal"
            class="font-weight-black uppercase"
          >
            {{ occurrence?.is_overdue ? "Cuota Vencida" : "En Plazo" }}
          </VChip>
        </VCard>

        <!-- Detalles del Pago -->
        <div class="d-flex align-center gap-2 mb-2">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-bold text-high-emphasis uppercase letter-spacing-1">
            Detalles del Pago
          </span>
        </div>

        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-lg border shadow-sm"
        >
          <VRow dense>
            <!-- Monto a Pagar -->
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="form.total_amount"
                type="number"
                min="0.01"
                step="0.01"
                label="Monto a Pagar"
                placeholder="0.00"
                :prefix="currencySymbol"
                variant="outlined"
                density="compact"
                class="rounded-lg font-weight-bold"
                hide-details="auto"
              />
            </VCol>

            <!-- Método de Pago -->
            <VCol cols="12" sm="4">
              <VSelect
                v-model="form.count"
                :items="availablePaymentMethods"
                label="Método de Pago / Cuenta"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-wallet"
                hide-details="auto"
              />
            </VCol>

            <!-- Fecha de Pago -->
            <VCol cols="12" sm="4">
              <AppDateTimePicker
                v-model="form.expense_date"
                placeholder="Fecha de Pago"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-calendar"
                :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                hide-details="auto"
              />
            </VCol>

            <!-- Opciones Fiscales -->
            <VCol cols="12" sm="6">
              <VSwitch
                v-model="form.has_invoice"
                label="Cuenta con factura física / digital"
                color="primary"
                density="compact"
                hide-details
                inset
                class="mt-2"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSwitch
                v-model="form.is_deductible"
                label="Gasto deducible de impuestos"
                color="primary"
                density="compact"
                hide-details
                inset
                class="mt-2"
              />
            </VCol>
          </VRow>

          <!-- Resumen del Egreso -->
          <div class="mt-3 pt-3 border-t">
            <div class="pa-2 px-3 rounded-lg bg-grey-50 border d-flex align-center justify-space-between">
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">
                  Total a Descontar ({{ form.currency }})
                </span>
                <span class="text-subtitle-1 font-weight-black text-success leading-tight">
                  {{ currencySymbol }} {{ (Number(form.total_amount) || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </span>
              </div>
              <VIcon icon="tabler-receipt" size="22" class="text-success" />
            </div>
          </div>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 bg-white border-t px-6">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="loading"
              :disabled="loading || !form.total_amount || Number(form.total_amount) <= 0"
              @click="submit"
            >
              <VIcon start icon="tabler-check" size="18" />
              Confirmar Pago
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
    rgb(var(--v-theme-gradient-end)) 100%
  );
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
</style>
