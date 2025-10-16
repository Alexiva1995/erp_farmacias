<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  activity: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

const formData = ref({
  status: "",
  completed_date: null,
  notes: "",
});

const statusOptions = [
  { title: "Pendiente", value: "Pendiente" },
  { title: "Completada", value: "Completada" },
  { title: "Cancelada", value: "Cancelada" },
];

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      formData.value = {
        status: props.activity.status || "Pendiente",
        completed_date: props.activity.completed_date || null,
        notes: props.activity.notes || "",
      };
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
};

const handleSubmit = () => {
  const dataToSend = {
    status: formData.value.status,
    completed_date: formData.value.completed_date,
    notes: formData.value.notes,
  };

  emit("save", dataToSend);
};

const getStatusColor = (status) => {
  const statusColors = {
    Pendiente: "warning",
    Completada: "success",
    Cancelada: "error",
  };
  return statusColors[status] || "default";
};

const getStatusIcon = (status) => {
  const statusIcons = {
    Pendiente: "tabler-clock",
    Completada: "tabler-check",
    Cancelada: "tabler-x",
  };
  return statusIcons[status] || "tabler-circle";
};

const showCompletedDate = computed(() => {
  return formData.value.status === "Completada";
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600"
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon icon="tabler-edit" size="24" class="text-primary" />
        <span class="text-h6">Actualizar Estado de Actividad</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Información de la Actividad -->
        <VCard variant="outlined" class="mb-4">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" variant="tonal" size="48">
                <VIcon icon="tabler-checkbox" size="24" />
              </VAvatar>
              <div class="flex-grow-1">
                <div class="text-h6 font-weight-medium mb-1">
                  {{ props.activity.activity_name }}
                </div>
                <div class="text-sm text-disabled">
                  {{ props.activity.description || "Sin descripción" }}
                </div>
              </div>
              <VChip
                :color="getStatusColor(props.activity.status)"
                variant="tonal"
              >
                <VIcon
                  :icon="getStatusIcon(props.activity.status)"
                  size="14"
                  class="me-1"
                />
                {{ props.activity.status }}
              </VChip>
            </div>
          </VCardText>
        </VCard>

        <!-- Formulario -->
        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Estado -->
            <VCol cols="12">
              <VSelect
                v-model="formData.status"
                :items="statusOptions"
                label="Estado *"
                placeholder="Selecciona el estado"
                prepend-inner-icon="tabler-flag"
                :error-messages="props.errors.status"
                @update:model-value="emit('clear-errors')"
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VIcon :icon="getStatusIcon(item.value)" size="18" />
                    <span>{{ item.title }}</span>
                  </div>
                </template>
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps">
                    <template #prepend>
                      <VIcon
                        :icon="getStatusIcon(item.value)"
                        :color="getStatusColor(item.value)"
                        size="20"
                      />
                    </template>
                  </VListItem>
                </template>
              </VSelect>
            </VCol>

            <!-- Fecha de Completado (solo si está completada) -->
            <VCol v-if="showCompletedDate" cols="12">
              <AppDateTimePicker
                v-model="formData.completed_date"
                label="Fecha de Completado"
                placeholder="Selecciona fecha"
                prepend-inner-icon="tabler-calendar"
                :error-messages="props.errors.completed_date"
                @update:model-value="emit('clear-errors')"
              />
            </VCol>

            <!-- Notas -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.notes"
                label="Notas"
                placeholder="Agrega comentarios o notas sobre esta actividad..."
                prepend-inner-icon="tabler-note"
                rows="3"
                :error-messages="props.errors.notes"
                counter="500"
                @update:model-value="emit('clear-errors')"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5 d-flex gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          class="flex-grow-1"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          class="flex-grow-1"
          :disabled="!formData.status"
          @click="handleSubmit"
        >
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
