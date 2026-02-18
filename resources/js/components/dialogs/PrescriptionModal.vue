<script setup>
import { computed, defineEmits, defineProps, ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  prescriptionData: {
    type: Object,
    default: () => null,
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "modal-closed",
  "prescription-saved",
]);

// Datos del formulario
const formData = ref({
  id: null,
  name: "",
  start_date: "",
  end_date: "",
  discount_percentage: "",
  is_active: true,
});

const loading = ref(false);
const formErrors = ref({});

// Opciones de estatus
const statusOptions = [
  { title: "Activa", value: true },
  { title: "Inactiva", value: false },
];

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => {
    emit("update:isDialogVisible", val);
    if (!val) emit("modal-closed");
  },
});

const isEditing = computed(
  () => !!props.prescriptionData && !!props.prescriptionData.id
);

const dialogTitle = computed(() => {
  return isEditing.value ? "Editar Oferta de Receta" : "Crear Oferta de Receta";
});

// Limpiar formulario
const resetForm = () => {
  formData.value = {
    id: null,
    name: "",
    start_date: "",
    end_date: "",
    discount_percentage: "",
    is_active: true,
  };
  formErrors.value = {};
};

// Guardar
const onSave = () => {
  // Validación básica local
  formErrors.value = {};

  if (!formData.value.name) formErrors.value.name = "El nombre es requerido";
  if (!formData.value.start_date)
    formErrors.value.start_date = "Fecha inicio requerida";
  if (!formData.value.end_date)
    formErrors.value.end_date = "Fecha fin requerida";
  if (!formData.value.discount_percentage)
    formErrors.value.discount_percentage = "Descuento requerido";

  if (Object.keys(formErrors.value).length > 0) return;

  loading.value = true;

  // Preparamos los datos
  const payload = {
    ...formData.value,
    discount_percentage: parseFloat(formData.value.discount_percentage),
  };

  // Emitimos al padre para que maneje la API (como está en tu vista padre)
  emit("prescription-saved", payload);
  loading.value = false;
};

const onCancel = () => {
  dialogVisible.value = false;
  resetForm();
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// Watcher para cargar datos al editar
watch(
  () => props.isDialogVisible,
  (isVisible) => {
    if (isVisible && props.prescriptionData) {
      formData.value = {
        id: props.prescriptionData.id,
        name: props.prescriptionData.name,
        start_date: formatDateForInput(props.prescriptionData.start_date),
        end_date: formatDateForInput(props.prescriptionData.end_date),
        discount_percentage: props.prescriptionData.discount_percentage,
        is_active: Boolean(props.prescriptionData.is_active),
      };
    } else if (isVisible) {
      resetForm();
    }
  }
);
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="600px"
    persistent
    scrollable
    :retain-focus="false"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard :loading="loading">
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-prescription" size="24" color="white" />
          <span class="text-h6 text-white">{{ dialogTitle }}</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="onCancel" :disabled="loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="formData.name"
              label="Nombre de la Oferta *"
              placeholder="Ej: Descuento Recetas Enero"
              variant="outlined"
              :error-messages="formErrors.name"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.start_date"
              label="Fecha de Inicio *"
              type="date"
              variant="outlined"
              :error-messages="formErrors.start_date"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.end_date"
              label="Fecha de Finalización *"
              type="date"
              variant="outlined"
              :error-messages="formErrors.end_date"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.discount_percentage"
              label="Porcentaje de Descuento *"
              type="number"
              placeholder="Ej: 20"
              suffix="%"
              min="0"
              max="100"
              variant="outlined"
              :error-messages="formErrors.discount_percentage"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              v-model="formData.is_active"
              label="Estatus"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="onCancel"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="onSave"
              :loading="loading"
            >
              {{ isEditing ? "Actualizar" : "Guardar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
