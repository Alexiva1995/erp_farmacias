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
  photo: null,
  notes: "",
});

const photoPreview = ref(null);
const photoFile = ref(null);

const statusOptions = [
  { title: "Pendiente", value: "Pendiente", disabled: false },
  { title: "Procesada", value: "Procesada", disabled: false },
];

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      formData.value = {
        status: props.activity.status || "Pendiente",
        photo: null,
        notes: props.activity.notes || "",
      };
      photoPreview.value = null;
      photoFile.value = null;
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
  resetForm();
};

const resetForm = () => {
  formData.value = {
    status: "Pendiente",
    photo: null,
    notes: "",
  };
  photoPreview.value = null;
  photoFile.value = null;
};

const handlePhotoChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    // Validar tipo de archivo
    if (!file.type.startsWith("image/")) {
      emit("clear-errors");
      return;
    }

    // Validar tamaño (máx 5MB)
    if (file.size > 5 * 1024 * 1024) {
      emit("clear-errors");
      return;
    }

    photoFile.value = file;
    formData.value.photo = file;

    // Crear preview
    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const removePhoto = () => {
  formData.value.photo = null;
  photoFile.value = null;
  photoPreview.value = null;
};

const handleSubmit = () => {
  // Validación del lado del cliente
  if (formData.value.status === "Procesada" && !formData.value.photo) {
    // El error se manejará desde el backend
    return;
  }

  // Crear FormData para enviar la foto
  const submitData = new FormData();
  submitData.append("status", formData.value.status);

  if (formData.value.photo) {
    submitData.append("photo", formData.value.photo);
  }

  if (formData.value.notes) {
    submitData.append("notes", formData.value.notes);
  }

  emit("save", submitData);
};

const getStatusColor = (status) => {
  const statusColors = {
    Pendiente: "warning",
    Procesada: "info",
    Completada: "success",
    Vencida: "error",
    Cancelada: "secondary",
  };
  return statusColors[status] || "default";
};

const getStatusIcon = (status) => {
  const statusIcons = {
    Pendiente: "tabler-clock",
    Procesada: "tabler-hourglass",
    Completada: "tabler-check",
    Vencida: "tabler-alert-triangle",
    Cancelada: "tabler-x",
  };
  return statusIcons[status] || "tabler-circle";
};

const showPhotoUpload = computed(() => {
  return formData.value.status === "Procesada";
});

const isFormValid = computed(() => {
  if (formData.value.status === "Procesada") {
    return formData.value.photo !== null;
  }
  return formData.value.status !== "";
});

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("es-ES", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon icon="tabler-upload" size="24" class="text-primary" />
        <span class="text-h6">Procesar Actividad de Limpieza</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Información de la Actividad -->
        <VCard variant="outlined" class="mb-5">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3 mb-3">
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

            <VDivider class="my-3" />

            <div class="d-flex flex-wrap gap-4">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar" size="18" color="primary" />
                <div>
                  <div class="text-xs text-disabled">Fecha Inicio</div>
                  <div class="text-sm font-weight-medium">
                    {{ formatDate(props.activity.scheduled_date) }}
                  </div>
                </div>
              </div>

              <!-- NUEVO: Fecha Límite -->
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar-due" size="18" color="error" />
                <div>
                  <div class="text-xs text-disabled">Fecha Límite</div>
                  <div class="text-sm font-weight-medium">
                    {{ formatDate(props.activity.due_date) }}
                  </div>
                </div>
              </div>

              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-repeat" size="18" color="success" />
                <div>
                  <div class="text-xs text-disabled">Frecuencia</div>
                  <div class="text-sm font-weight-medium">
                    {{ props.activity.frequency }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Mostrar razón de rechazo si existe -->
            <VAlert
              v-if="props.activity.rejection_reason"
              type="error"
              variant="tonal"
              class="mt-4"
            >
              <template #prepend>
                <VIcon icon="tabler-alert-circle" />
              </template>
              <div class="text-sm">
                <strong>Razón del rechazo:</strong><br />
                {{ props.activity.rejection_reason }}
              </div>
            </VAlert>
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

            <!-- Alerta informativa cuando selecciona Procesada -->
            <VCol v-if="showPhotoUpload" cols="12">
              <VAlert type="info" variant="tonal">
                <template #prepend>
                  <VIcon icon="tabler-info-circle" />
                </template>
                <div class="text-sm">
                  <strong>¡Importante!</strong> Debes subir una foto de
                  evidencia para marcar esta actividad como procesada. Un
                  supervisor revisará y aprobará tu trabajo.
                </div>
              </VAlert>
            </VCol>

            <!-- Subida de Foto (solo si está en Procesada) -->
            <VCol v-if="showPhotoUpload" cols="12">
              <VCard variant="outlined" class="pa-4">
                <div class="d-flex align-center gap-2 mb-3">
                  <VIcon icon="tabler-camera" size="20" color="primary" />
                  <span class="text-body-1 font-weight-medium"
                    >Foto de Evidencia *</span
                  >
                </div>

                <!-- Input de archivo (oculto) -->
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/jpg"
                  style="display: none"
                  @change="handlePhotoChange"
                />

                <!-- Preview o botón de subida -->
                <div v-if="photoPreview" class="text-center">
                  <VImg
                    :src="photoPreview"
                    max-height="300"
                    contain
                    class="mb-3 rounded"
                  />
                  <VBtn
                    color="error"
                    variant="outlined"
                    prepend-icon="tabler-trash"
                    @click="removePhoto"
                  >
                    Eliminar Foto
                  </VBtn>
                </div>

                <div v-else class="text-center py-8">
                  <VIcon
                    icon="tabler-cloud-upload"
                    size="64"
                    color="grey-lighten-1"
                    class="mb-3"
                  />
                  <div class="text-body-2 text-disabled mb-4">
                    Sube una foto como evidencia de la actividad completada
                  </div>
                  <VBtn
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-upload"
                    @click="$refs.fileInput.click()"
                  >
                    Seleccionar Foto
                  </VBtn>
                  <div class="text-xs text-disabled mt-2">
                    Formatos: JPG, PNG • Tamaño máximo: 5MB
                  </div>
                </div>

                <!-- Error de foto -->
                <div v-if="props.errors.photo" class="text-error text-sm mt-2">
                  <VIcon icon="tabler-alert-circle" size="16" />
                  {{ props.errors.photo[0] }}
                </div>
              </VCard>
            </VCol>

            <!-- Notas -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.notes"
                label="Notas u Observaciones"
                placeholder="Agrega comentarios sobre esta actividad (opcional)..."
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
          :disabled="!isFormValid"
          @click="handleSubmit"
        >
          <VIcon icon="tabler-check" class="me-2" />
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
