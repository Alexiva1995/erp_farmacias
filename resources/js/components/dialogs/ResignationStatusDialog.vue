<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  employee: {
    type: Object,
    default: null,
  },
  newStatus: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm", "cancel"]);

const handleClose = () => {
  if (!props.loading) {
    emit("update:modelValue", false);
    emit("cancel");
  }
};

const handleConfirm = () => {
  emit("confirm");
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500px"
    persistent
    @update:model-value="handleClose"
  >
    <VCard class="rounded-lg overflow-hidden">
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon
          :icon="props.newStatus ? 'tabler-user-plus' : 'tabler-user-minus'"
          :color="props.newStatus ? 'success' : 'error'"
          class="me-3"
        />
        <span class="text-h6 font-weight-black">
          {{ props.newStatus ? "Activar Empleado" : "Desactivar Empleado" }}
        </span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pt-4 pa-4">
        <div class="text-sm font-weight-black text-primary mb-4">
          {{ props.employee?.name }}
        </div>

        <div v-if="props.newStatus" class="text-sm font-weight-medium">
          <VIcon icon="tabler-info-circle" color="success" class="me-2" size="18" />
          ¿Está seguro de que desea activar este empleado?
        </div>

        <div v-else class="text-sm font-weight-medium">
          <VIcon icon="tabler-info-circle" color="error" class="me-2" size="18" />
          ¿Está seguro de que desea desactivar este empleado?
        </div>

        <VAlert
          :color="props.newStatus ? 'success' : 'error'"
          variant="tonal"
          class="mt-4 rounded-lg"
        >
          <template #prepend>
            <VIcon :icon="props.newStatus ? 'tabler-eye' : 'tabler-eye-off'" />
          </template>

          <div v-if="props.newStatus">
            <strong class="text-xs font-weight-black uppercase">
              El empleado volverá a aparecer en la lista
            </strong>
            <div class="text-xs mt-1">
              Podrá acceder al sistema y realizar sus funciones normalmente.
            </div>
          </div>

          <div v-else>
            <strong class="text-xs font-weight-black uppercase">
              El empleado ya no aparecerá en la lista
            </strong>
            <div class="text-xs mt-1">
              No podrá acceder al sistema hasta que sea reactivado.
            </div>
          </div>
        </VAlert>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="tonal"
          :disabled="props.loading"
          class="flex-grow-1 rounded-lg font-weight-black"
          @click="handleClose"
        >
          <VIcon icon="tabler-x" class="me-2" size="18" />
          Cancelar
        </VBtn>
        <VBtn
          :color="props.newStatus ? 'success' : 'error'"
          variant="flat"
          :loading="props.loading"
          :disabled="props.loading"
          class="flex-grow-1 rounded-lg font-weight-black"
          @click="handleConfirm"
        >
          <VIcon icon="tabler-check" class="me-2" size="18" />
          {{ props.newStatus ? "Activar" : "Desactivar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
