<script setup>
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  historyName: { type: String, default: "" },
  details: { type: Array, default: () => [] },
  historyId: { type: Number },
});

const emit = defineEmits(["update:modelValue"]);

const onCancel = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    @update:model-value="onCancel"
  >
    <VCard>
      <VCardTitle
        >Destalles del Historial Fiscal: {{ props.historyName }}</VCardTitle
      >
      <VCardText>
        <VTable density="compact">
          <thead>
            <tr>
              <th class="text-left">Nombre del producto</th>
              <th class="text-left">Cantidad</th>
              <th class="text-left">Exento</th>
              <th class="text-left">IVA</th>
              <th class="text-left">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="detail in details" :key="detail.id">
              <td>{{ detail.product_name }}</td>
              <td>{{ detail.quantity }}</td>
              <td>${{ parseFloat(detail.exempt_amount).toFixed(2) }}</td>
              <td>${{ parseFloat(detail.iva_amount).toFixed(2) }}</td>
              <td>${{ parseFloat(detail.total_amount).toFixed(2) }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="onCancel"
          >Cerrar</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
