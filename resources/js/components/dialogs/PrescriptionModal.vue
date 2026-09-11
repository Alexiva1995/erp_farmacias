<script setup>
import { computed, ref, watch, nextTick } from "vue";
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

const endDateConfig = computed(() => ({
  altFormat: "Y-m-d",
  dateFormat: "Y-m-d",
  minDate: formData.value.start_date || undefined,
}));

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
    max-width="680px"
    width="680px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="dialogVisible" :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="38"
            class="me-3 elevation-1 text-primary font-weight-black"
          >
            <VIcon
              icon="tabler-prescription"
              size="22"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Campaña de Descuentos por Recetas Médicas
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="onCancel"
            :disabled="isSaving"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Bloque 1: Configuración de la Campaña -->
        <div class="mb-5">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Información de la Campaña</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-disabled uppercase">Activa</span>
              <VSwitch
                v-model="formData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <VRow dense>
            <VCol cols="12">
              <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Nombre Descriptivo de la Oferta *</span>
              <VTextField
                v-model="formData.name"
                placeholder="EJ: CAMPAÑA RECETAS ENERO..."
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded font-weight-bold"
                :error="!!formErrors.name"
                :error-messages="formErrors.name"
                :disabled="isSaving"
              />
            </VCol>
          </VRow>
        </div>

        <!-- Bloque 2: Parámetros y Vigencia -->
        <div class="mb-2">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros y Vigencia</span>
          </div>

          <VRow dense>
            <VCol cols="12" sm="4">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">% Descuento Aplicable *</span>
                <VTextField
                  v-model="formData.discount_percentage"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  placeholder="0.00"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  prepend-inner-icon="tabler-percentage"
                  class="rounded font-weight-black"
                  :error="!!formErrors.discount_percentage"
                  :error-messages="formErrors.discount_percentage"
                  :disabled="isSaving"
                />
              </div>
            </VCol>

            <VCol cols="12" sm="4">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha Inicio *</span>
                <AppDateTimePicker
                  v-model="formData.start_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-event"
                  density="compact"
                  hide-details="auto"
                  class="rounded"
                  :error="!!formErrors.start_date"
                  :error-messages="formErrors.start_date"
                  :disabled="isSaving"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <VCol cols="12" sm="4">
              <div>
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha Final *</span>
                <AppDateTimePicker
                  v-model="formData.end_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-off"
                  density="compact"
                  hide-details="auto"
                  class="rounded"
                  :error="!!formErrors.end_date"
                  :error-messages="formErrors.end_date"
                  :disabled="isSaving"
                  :config="endDateConfig"
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <div class="d-flex gap-2 w-100 justify-end">
          <VBtn
            color="secondary"
            variant="outlined"
            height="44"
            class="font-weight-bold rounded text-button uppercase flex-grow-1 flex-sm-grow-0 px-5"
            @click="onCancel"
            :disabled="isSaving"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            height="44"
            prepend-icon="tabler-device-floppy"
            class="font-weight-black rounded shadow-primary text-button uppercase flex-grow-1 flex-sm-grow-0 px-6"
            :loading="isSaving"
            @click="onSave"
          >
            {{ isEditing ? "Guardar Cambios" : "Crear Oferta" }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
