<script setup>
import { computed, defineEmits, defineProps, ref, watch, nextTick } from "vue";
import { useDisplay } from "vuetify";

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

const { mobile } = useDisplay();

// Datos del formulario
const formData = ref({
  id: null,
  name: "",
  start_date: "",
  end_date: "",
  discount_percentage: "",
  is_active: true,
});

const isSaving = ref(false);
const formErrors = ref({});

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
  return isEditing.value ? "Editar Oferta de Receta" : "Nueva Oferta de Receta";
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
  formErrors.value = {};

  if (!formData.value.name) formErrors.value.name = "EL NOMBRE ES REQUERIDO";
  if (!formData.value.start_date) formErrors.value.start_date = "FECHA INICIO REQUERIDA";
  if (!formData.value.end_date) formErrors.value.end_date = "FECHA FIN REQUERIDA";
  if (!formData.value.discount_percentage) formErrors.value.discount_percentage = "DESCUENTO REQUERIDO";

  if (Object.keys(formErrors.value).length > 0) {
    return;
  }

  isSaving.value = true;
  const payload = {
    ...formData.value,
    discount_percentage: parseFloat(formData.value.discount_percentage),
  };

  emit("prescription-saved", payload);
  // El padre se encarga de cerrar el modal y resetear el isSaving si es necesario, 
  // pero aquí lo bajamos por si acaso hay un error inmediato o validación síncrona.
  isSaving.value = false; 
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
    if (isVisible) {
      if (props.prescriptionData) {
        nextTick(() => {
          formData.value = {
            id: props.prescriptionData.id,
            name: props.prescriptionData.name,
            start_date: formatDateForInput(props.prescriptionData.start_date),
            end_date: formatDateForInput(props.prescriptionData.end_date),
            discount_percentage: props.prescriptionData.discount_percentage,
            is_active: Boolean(props.prescriptionData.is_active),
          };
        });
      } else {
        resetForm();
      }
    }
  }
);
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="700px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="dialogVisible" :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-prescription" color="primary" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              Configuración de descuentos por recetas médicas
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="isSaving"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
        <VRow dense>
          <VCol cols="12">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Nombre Descriptivo de la Oferta</span>
            <AppTextField
              v-model="formData.name"
              placeholder="EJ: CAMPAÑA RECETAS ENERO 2024"
              variant="outlined"
              density="compact"
              hide-details
              class="premium-input-compact mb-4"
              :error="!!formErrors.name"
              :disabled="isSaving"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Inicio</span>
            <AppDateTimePicker
              v-model="formData.start_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-event"
              density="compact"
              hide-details
              class="premium-input-compact mb-4"
              :error="!!formErrors.start_date"
              :disabled="isSaving"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Final</span>
            <AppDateTimePicker
              v-model="formData.end_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-off"
              density="compact"
              hide-details
              class="premium-input-compact mb-4"
              :error="!!formErrors.end_date"
              :disabled="isSaving"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">% Descuento</span>
            <AppTextField
              v-model="formData.discount_percentage"
              type="number"
              min="0"
              max="100"
              step="0.01"
              placeholder="0.00"
              variant="outlined"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-percentage"
              class="premium-input-compact mb-4"
              :error="!!formErrors.discount_percentage"
              :disabled="isSaving"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Estado Administrativo</span>
            <VSelect
              v-model="formData.is_active"
              :items="[
                { value: true, title: 'ACTIVA' },
                { value: false, title: 'INACTIVA' },
              ]"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
              class="premium-input-compact"
              :disabled="isSaving"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="isSaving"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg shadow-primary-lg text-button uppercase"
              :loading="isSaving"
              @click="onSave"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
              {{ isEditing ? "Guardar Cambios" : "Crear Oferta" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-input-compact :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;
  color: rgba(var(--v-border-color), 1) !important;
}

.premium-input-compact :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.premium-input-compact :deep(.v-field) {
  border-radius: 8px !important;
  min-height: 38px !important;
  background-color: white !important;
}

.premium-input-compact :deep(.v-field__input) {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-height: 38px !important;
  text-transform: uppercase;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.shadow-primary-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.25) !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
