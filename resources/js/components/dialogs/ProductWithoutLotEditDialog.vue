<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  lot: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save"]);

const editableLot = ref({});

watch(
  () => props.lot,
  (newLot) => {
    if (newLot) {
      editableLot.value = { ...newLot };
    }
  },
  { deep: true, immediate: true }
);

const onSave = () => {
  const updateData = {
    id: editableLot.value.id,
    quantity: editableLot.value.quantity,
  };
  emit("save", updateData);
};

const onCancel = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500px"
    @update:model-value="onCancel"
  >
    <VCard>
      <VCardTitle>Ajustar Cantidad del Lote</VCardTitle>
      <VCardSubtitle v-if="editableLot.product">
        Ajustando lote para: {{ editableLot.product.name }}
      </VCardSubtitle>
      <VCardText class="mt-4">
        <VTextField
          :model-value="editableLot.product?.stock"
          label="Stock Total del Producto"
          readonly
          disabled
          class="mb-4"
        />
        <VTextField
          v-model="editableLot.quantity"
          label="Nueva Cantidad en este Lote"
          type="number"
          autofocus
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="onCancel"
          >Cancelar</VBtn
        >
        <VBtn color="primary" variant="flat" @click="onSave"
          >Guardar Cambios</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
