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
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-prescription"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Campaña de Descuentos por Recetas Médicas • Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="isSaving"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Configuración de la Campaña -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración de la Campaña</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm mb-0"
        >
          <VRow dense>
            <VCol cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre Descriptivo de la Oferta</span>
                <VTextField
                  v-model="formData.name"
                  placeholder="EJ: CAMPAÑA RECETAS ENERO 2024"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-black"
                  :error="!!formErrors.name"
                  :disabled="isSaving"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Inicio</span>
                <AppDateTimePicker
                  v-model="formData.start_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-event"
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
                  :error="!!formErrors.start_date"
                  :disabled="isSaving"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Final</span>
                <AppDateTimePicker
                  v-model="formData.end_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-off"
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
                  :error="!!formErrors.end_date"
                  :disabled="isSaving"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4 mb-sm-0">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">% Descuento Aplicable</span>
                <VTextField
                  v-model="formData.discount_percentage"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  placeholder="0.00"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :error="!!formErrors.discount_percentage"
                  :disabled="isSaving"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Estado Administrativo</span>
                <VSelect
                  v-model="formData.is_active"
                  :items="[
                    { value: true, title: 'CAMPAÑA ACTIVA' },
                    { value: false, title: 'CAMPAÑA INACTIVA' },
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-black"
                  :disabled="isSaving"
                />
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Mensaje de Soporte -->
        <div class="mt-6 pa-4 rounded-xl bg-primary bg-opacity-10 border-dashed-2 d-flex align-center gap-4">
          <VAvatar
            color="primary"
            variant="tonal"
            size="40"
            class="rounded-lg"
          >
            <VIcon
              icon="tabler-info-circle"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1">Nota de Aplicación</span>
            <p class="text-super-xs text-medium-emphasis mb-0 leading-tight">
              Los descuentos por recetas se aplicarán durante el periodo de vigencia establecido en el Punto de Venta.
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="isSaving"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="isSaving"
              @click="onSave"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
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
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
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
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.italic {
  font-style: italic;
}
</style>
