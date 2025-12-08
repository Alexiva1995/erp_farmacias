<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
});

const emit = defineEmits(["update:isDialogVisible", "submit"]);

const date = ref("");

watch(
  () => props.isDialogVisible,
  (val) => {
    if (val) date.value = "";
  }
);

const handleClose = () => {
  emit("update:isDialogVisible", false);
};

const handleSubmit = () => {
  if (!date.value) return;
  emit("submit", date.value);
  handleClose();
};
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="400"
    persistent
    @update:model-value="handleClose"
  >
    <VCard title="Eliminar Productos Antiguos">
      <VCardText>
        <p class="mb-4 text-body-2">
          Seleccione la fecha de corte. <strong>Se mantendrán</strong> los
          productos actualizados desde esa fecha en adelante. Los anteriores
          serán eliminados.
        </p>

        <AppDateTimePicker
          v-model="date"
          label="Mantener productos desde..."
          placeholder="Seleccionar fecha"
          :config="{ enableTime: false, dateFormat: 'Y-m-d' }"
        />
      </VCardText>

      <!-- MODIFICADO: Agregamos gap-3 para separar los botones y padding -->
      <VCardActions class="d-flex gap-3 px-6 pb-6">
        <!-- Eliminamos VSpacer -->

        <!-- Botón Cancelar (50%) -->
        <VBtn
          color="secondary"
          variant="tonal"
          class="flex-grow-1"
          @click="handleClose"
        >
          Cancelar
        </VBtn>

        <VBtn
          color="error"
          variant="elevated"
          class="flex-grow-1"
          :disabled="!date"
          @click="handleSubmit"
        >
          Confirmar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
