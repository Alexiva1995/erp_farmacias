<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:isDialogVisible", "submit"]);

const discountPercentage = ref(null);

watch(
  () => props.isDialogVisible,
  (val) => {
    if (val) {
      discountPercentage.value = null;
    }
  }
);

const handleClose = () => {
  emit("update:isDialogVisible", false);
};

const handleSubmit = () => {
  if (!discountPercentage.value) return;

  emit("submit", {
    supplier: props.selectedSupplier,
    percentage: discountPercentage.value,
  });

  handleClose();
};
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="500"
    @update:model-value="handleClose"
  >
    <VCard title="Aplicar Descuento Global">
      <VCardText>
        <p class="mb-4">
          Ingrese el porcentaje de descuento que desea aplicar a todos los
          productos del proveedor
          <strong>{{ props.selectedSupplier?.name }}</strong
          >.
        </p>

        <AppTextField
          v-model="discountPercentage"
          label="Porcentaje de Descuento"
          placeholder="Ej: 15"
          type="number"
          suffix="%"
          autofocus
        />
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="handleClose">
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="elevated" @click="handleSubmit">
          Aplicar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
