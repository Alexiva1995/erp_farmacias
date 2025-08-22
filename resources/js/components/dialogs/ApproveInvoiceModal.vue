<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  invoice: { type: Object, default: null },
  discounts: { type: Array, default: () => [] },
  paymentRules: { type: Array, default: () => [] },
  details: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const selectedDiscountId = ref(null);
const selectedPaymentRuleId = ref(null);
const itemsToReturn = ref([]);

const formattedPaymentRules = computed(() => {
  return (props.paymentRules || []).map((rule) => ({
    ...rule,
    display_name: `${rule.discount_percentage}%`,
  }));
});

const processedDetails = computed(() => {
  if (!props.invoice || !props.details.length) return [];

  const rate = parseFloat(props.invoice.exchange_rate);
  const isUsd = props.invoice.currency === "USD";

  return props.details.map((detail) => {
    const unitCostUsd = detail.unit_cost || 0;
    let unitCostLocal = unitCostUsd;

    if (!isUsd && rate > 0) {
      unitCostLocal = unitCostUsd * rate;
    }

    return {
      ...detail,
      unit_cost_usd: unitCostUsd,
      unit_cost_local: unitCostLocal,
    };
  });
});

const detailHeaders = [
  { title: "Nombre Producto", key: "product.name", sortable: false },
  { title: "Costo Unitario", key: "unit_cost", sortable: false, align: "end" },
  { title: "Laboratorio", key: "product.laboratory.name", sortable: false },
];

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      selectedDiscountId.value = null;
      selectedPaymentRuleId.value = null;
      itemsToReturn.value = [];
    }
  }
);

const handleConfirm = () => {
  if (props.invoice) {
    emit("confirm", {
      invoiceId: props.invoice.id,
      discountId: selectedDiscountId.value,
      paymentRuleId: selectedPaymentRuleId.value,
      returnItems: itemsToReturn.value,
    });
  }
};

const closeDialog = () => {
  if (!props.loading) {
    emit("update:modelValue", false);
  }
};

const formatCurrency = (value, currency) => {
  const currencyMap = {
    BS: "VES",
    Bs: "VES",
    COP: "COP",
    USD: "USD",
  };
  const mappedCurrency = currencyMap[currency] || currency;
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: mappedCurrency,
  }).format(value);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="900px"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard
      v-if="props.invoice"
      :loading="props.loading"
      class="d-flex flex-column"
    >
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h5 font-weight-bold">Aprobar Factura</span>
        <VSpacer />
        <VBtn
          icon
          variant="text"
          @click="closeDialog"
          :disabled="props.loading"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VAlert
          v-if="props.invoice.currency !== 'USD'"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <template #prepend>
            <VIcon icon="tabler-info-circle" />
          </template>
          <div>
            <strong>Factura en {{ props.invoice.currency }}</strong>
            <div class="text-caption mt-1">
              Los montos se muestran en la moneda de la factura, calculados
              desde USD con la tasa de cambio de la factura.
            </div>
          </div>
        </VAlert>

        <p class="text-h6 font-weight-medium mb-4">
          Paso 1: Aplicar Descuentos (Opcional)
        </p>

        <VRow>
          <VCol cols="12" md="6">
            <VSelect
              v-model="selectedDiscountId"
              :items="props.discounts"
              item-title="name"
              item-value="id"
              label="Aplicar descuento por proveedor"
              variant="outlined"
              clearable
            />
            <VAlert
              v-if="!props.discounts.length"
              type="info"
              variant="tonal"
              density="compact"
              class="mt-2"
            >
              Este proveedor no tiene descuentos configurados.
            </VAlert>
          </VCol>

          <VCol cols="12" md="6">
            <VSelect
              v-model="selectedPaymentRuleId"
              :items="formattedPaymentRules"
              item-title="display_name"
              item-value="id"
              label="Aplicar descuento de pronto pago"
              variant="outlined"
              clearable
            />
            <VAlert
              v-if="!formattedPaymentRules.length"
              type="info"
              variant="tonal"
              density="compact"
              class="mt-2"
            >
              Este proveedor no tiene reglas de pago configuradas.
            </VAlert>
          </VCol>
        </VRow>

        <VDivider class="my-6" />

        <p class="text-h6 font-weight-medium mb-4">
          Paso 2: Marcar Productos para Devolución (Opcional)
        </p>

        <VDataTable
          v-model="itemsToReturn"
          :headers="detailHeaders"
          :items="processedDetails"
          item-value="id"
          show-select
          density="compact"
          class="border rounded"
          no-data-text="No hay productos en esta factura."
        >
          <template #item.unit_cost="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="font-weight-medium">
                {{
                  formatCurrency(item.unit_cost_local, props.invoice.currency)
                }}
              </span>
              <span
                v-if="props.invoice.currency !== 'USD'"
                class="text-caption text-medium-emphasis"
              >
                {{ formatCurrency(item.unit_cost_usd, "USD") }}
              </span>
            </div>
          </template>

          <template #item.product\.laboratory\.name="{ item }">
            <span>{{ item.product?.laboratory?.name || "N/A" }}</span>
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
          :disabled="props.loading"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleConfirm"
          class="flex-grow-1 w-0"
          :loading="props.loading"
        >
          Confirmar Aprobación
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
