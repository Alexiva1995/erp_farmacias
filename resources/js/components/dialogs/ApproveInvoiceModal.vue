<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  invoice: {
    type: Object,
    default: null,
  },
  discounts: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const selectedDiscountId = ref(null);

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      selectedDiscountId.value = null;
    }
  }
);

const handleConfirm = () => {
  if (props.invoice) {
    emit("confirm", {
      invoiceId: props.invoice.id,
      discountId: selectedDiscountId.value,
    });
  }
};

const closeDialog = () => {
  if (!props.loading) {
    emit("update:modelValue", false);
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
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
        <p class="mb-2">
          Estás a punto de aprobar la factura
          <span class="font-weight-bold"
            >N° {{ props.invoice.invoice_number }}</span
          >
          del proveedor
          <span class="font-weight-bold">{{ props.invoice.supplier.name }}</span
          >.
        </p>
        <VDivider class="my-4" />
        <p class="text-h6 font-weight-medium mb-4">Descuentos Disponibles</p>
        <VSelect
          v-model="selectedDiscountId"
          :items="props.discounts"
          item-title="name"
          item-value="id"
          label="Aplicar descuento (Opcional)"
          placeholder="Sin descuento"
          variant="outlined"
          clearable
          persistent-clear
        />
        <VAlert
          v-if="!props.discounts.length"
          type="info"
          variant="tonal"
          density="compact"
          class="mt-2"
        >
          Este proveedor no tiene descuentos por pronto pago configurados.
        </VAlert>
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
