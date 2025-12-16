<script setup>
import { toast } from "@/plugins/sweetalert";
import axios from "axios";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  selectedEmployee: { type: Object, default: null },
  isEdit: { type: Boolean, default: false },
  existingResignation: { type: Object, default: null },
});

const emit = defineEmits([
  "update:modelValue",
  "resignation-generated",
  "edit-confirmed",
]);

const errors = ref({});
const loading = ref(false);
const resignationType = ref("");
const effectiveDate = ref("");
const requestDate = ref(new Date().toISOString().split("T")[0]);
const employeePosition = ref("");
const showDuplicateConfirm = ref(false);
const duplicateResignationData = ref(null);

const resignationTypes = [
  { title: "Renuncia Justificada", value: "voluntary" },
  { title: "Renuncia Injustificada", value: "unjustified_dismissal" },
];

// Watcher para pre-llenar campos cuando se está editando
watch(
  [() => props.isEdit, () => props.existingResignation],
  ([isEdit, existingResignation]) => {
    if (isEdit && existingResignation) {
      resignationType.value = existingResignation.resignation_type || "";
      effectiveDate.value = existingResignation.effective_date || "";
      requestDate.value =
        existingResignation.request_date ||
        new Date().toISOString().split("T")[0];
      employeePosition.value = existingResignation.employee_position || "";
    }
  },
  { immediate: true }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  resetForm();
};

const resetForm = () => {
  errors.value = {};
  resignationType.value = "";
  effectiveDate.value = "";
  requestDate.value = new Date().toISOString().split("T")[0];
  employeePosition.value = ""; // Reset del nuevo campo
  loading.value = false;
};

const validateForm = () => {
  errors.value = {};

  if (!resignationType.value) {
    errors.value.resignationType = "Debe seleccionar el tipo de renuncia";
  }

  if (!effectiveDate.value) {
    errors.value.effectiveDate =
      "Debe seleccionar la fecha efectiva de renuncia";
  } else {
    const effective = new Date(effectiveDate.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (effective < today) {
      errors.value.effectiveDate =
        "La fecha efectiva no puede ser anterior a hoy";
    }
  }

  return Object.keys(errors.value).length === 0;
};

const generateResignation = async () => {
  if (!validateForm()) {
    return;
  }

  loading.value = true;

  try {
    const resignationData = {
      employee_id: props.selectedEmployee.id,
      employee_name: `${props.selectedEmployee.name} ${props.selectedEmployee.last_name}`,
      employee_identification: props.selectedEmployee.identification,
      employee_email: props.selectedEmployee.email,
      employee_status: props.selectedEmployee.is_active ? "Activo" : "Inactivo",
      employee_position: employeePosition.value || "empleado", // Campo opcional con valor por defecto
      start_date: new Date().toISOString().split("T")[0], // Fecha actual como fecha de inicio
      resignation_type: resignationType.value,
      effective_date: effectiveDate.value,
    };

    // Solo agregar request_date si no es edición
    if (!props.isEdit) {
      resignationData.request_date = requestDate.value;
    } else {
    }

    // Agregar flag de edición
    resignationData.is_edit = props.isEdit;

    // Llamada a la API para generar PDF

    const response = await axios.post(
      "/api/rrhh/resignations/generate",
      resignationData,
      {
        responseType: "blob",
        headers: {
          Accept: "application/pdf",
        },
      }
    );

    // Crear y descargar archivo PDF
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `carta-renuncia-${resignationData.employee_identification}.pdf`
    );
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Carta de renuncia generada y descargada exitosamente");

    emit("resignation-generated", resignationData);
    closeDialog();
  } catch (error) {
    // Mostrar el contenido completo del error si es un Blob
    if (error.response?.data instanceof Blob) {
      error.response.data.text().then((text) => {
        try {
          const errorData = JSON.parse(text);
        } catch (e) {}
      });
    }

    if (error.response?.status === 409) {
      // Error de duplicado - mostrar modal de confirmación
      duplicateResignationData.value = error.response.data.existing_resignation;
      showDuplicateConfirm.value = true;
      loading.value = false;
      return;
    } else if (error.response?.status === 422) {
      // Error de validación
      errors.value = error.response.data.errors;
      toast.error("Error de validación en los datos");
    } else {
      // Otros errores
      toast.error("No se pudo generar la carta de renuncia");
    }
  } finally {
    loading.value = false;
  }
};

const confirmEditResignation = () => {
  showDuplicateConfirm.value = false;
  // Cargar datos existentes en el formulario
  if (duplicateResignationData.value) {
    resignationType.value = duplicateResignationData.value.resignation_type;
    effectiveDate.value = duplicateResignationData.value.effective_date;
    requestDate.value = duplicateResignationData.value.request_date;
  }
  emit("edit-confirmed", duplicateResignationData.value);
};

const cancelEditResignation = () => {
  showDuplicateConfirm.value = false;
  duplicateResignationData.value = null;
  closeDialog();
};

// Función para formatear fechas
const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const day = date.getDate().toString().padStart(2, "0");
  const month = (date.getMonth() + 1).toString().padStart(2, "0");
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
};

// Calcular fecha mínima (hoy)
const minDate = computed(() => {
  return new Date().toISOString().split("T")[0];
});

// Calcular fecha máxima (1 año desde hoy)
const maxDate = computed(() => {
  const max = new Date();
  max.setFullYear(max.getFullYear() + 1);
  return max.toISOString().split("T")[0];
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-file-text" class="me-2" color="warning" />
        <span class="headline">Generar Carta de Renuncia</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog" :disabled="loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VContainer v-if="props.selectedEmployee">
        <!-- Información del Empleado -->
        <VCard variant="outlined" class="mb-4">
          <VCardTitle class="text-h6 pa-4 pb-2">
            <VIcon icon="tabler-user" class="me-2" />
            Información del Empleado
          </VCardTitle>
          <VCardText class="pt-0">
            <VRow>
              <VCol cols="12" sm="6">
                <div class="text-body-2 text-medium-emphasis">
                  Nombre Completo
                </div>
                <div class="text-body-1 font-weight-medium">
                  {{ props.selectedEmployee.name }}
                  {{ props.selectedEmployee.last_name }}
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="text-body-2 text-medium-emphasis">
                  Identificación
                </div>
                <div class="text-body-1 font-weight-medium">
                  {{ props.selectedEmployee.identification }}
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="text-body-2 text-medium-emphasis">
                  Correo Electrónico
                </div>
                <div class="text-body-1 font-weight-medium">
                  {{ props.selectedEmployee.email }}
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="text-body-2 text-medium-emphasis">Estado</div>
                <div class="text-body-1 font-weight-medium">
                  <VChip
                    :color="
                      props.selectedEmployee.is_active ? 'success' : 'error'
                    "
                    size="small"
                  >
                    {{
                      props.selectedEmployee.is_active ? "Activo" : "Inactivo"
                    }}
                  </VChip>
                </div>
              </VCol>
              <VCol cols="12">
                <div class="text-body-2 text-medium-emphasis">
                  Fecha de Registro
                </div>
                <div class="text-body-1 font-weight-medium">
                  {{ formatDate(new Date().toISOString().split("T")[0]) }}
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Formulario de Renuncia -->
        <VForm @submit.prevent="generateResignation">
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="resignationType"
                label="Tipo de Renuncia *"
                variant="outlined"
                :items="resignationTypes"
                :error-messages="errors.resignationType"
                :disabled="loading"
                required
              />
            </VCol>

            <!-- Campo opcional para cargo -->
            <VCol cols="12">
              <VTextField
                v-model="employeePosition"
                label="Cargo del Empleado"
                variant="outlined"
                :error-messages="errors.employeePosition"
                :disabled="loading"
                hint="Opcional"
                persistent-hint
                placeholder="Ejemplo: vendedora"
              >
                <template #append-inner>
                  <VTooltip location="top">
                    <template #activator="{ props }">
                      <VIcon
                        icon="tabler-help-circle"
                        size="small"
                        color="grey"
                        v-bind="props"
                      />
                    </template>
                    <span
                      >Ejemplo: vendedora, cajero, farmacéutico, etc. Si no se
                      especifica, se usará 'empleado'</span
                    >
                  </VTooltip>
                </template>
              </VTextField>
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="requestDate"
                label="Fecha de Solicitud *"
                type="date"
                variant="outlined"
                :disabled="true"
                readonly
                hint="Fecha actual del sistema"
                persistent-hint
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="effectiveDate"
                label="Fecha Efectiva de Renuncia *"
                type="date"
                variant="outlined"
                :min="minDate"
                :max="maxDate"
                :error-messages="errors.effectiveDate"
                :disabled="loading"
                required
                hint="Fecha en que el empleado dejará de trabajar"
                persistent-hint
              />
            </VCol>
          </VRow>

          <!-- Resumen de la Renuncia -->
          <VCard
            variant="tonal"
            color="warning"
            class="mt-4"
            v-if="resignationType && effectiveDate"
          >
            <VCardText>
              <div class="text-h6 mb-2">
                <VIcon icon="tabler-info-circle" class="me-2" />
                Resumen de la Renuncia
              </div>
              <div class="text-body-2">
                <strong
                  >{{ props.selectedEmployee.name }}
                  {{ props.selectedEmployee.last_name }}</strong
                >
                solicita
                {{
                  resignationTypes
                    .find((t) => t.value === resignationType)
                    ?.title.toLowerCase()
                }}
                como
                <strong>{{ employeePosition || "empleado" }}</strong>
                con fecha efectiva el
                <strong>{{ formatDate(effectiveDate) }}</strong
                >.
              </div>
            </VCardText>
          </VCard>
        </VForm>
      </VContainer>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          :disabled="loading"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="generateResignation"
          :loading="loading"
          :disabled="!resignationType || !effectiveDate"
          width="100%"
          class="flex-grow-1 w-0"
        >
          <VIcon icon="tabler-file-download" class="me-2" />
          Generar Carta PDF
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Modal de confirmación para editar renuncia existente -->
  <VDialog v-model="showDuplicateConfirm" max-width="500">
    <VCard>
      <VCardTitle class="text-h6 pa-4 pb-2">
        <VIcon icon="tabler-alert-triangle" class="me-2" color="warning" />
        Renuncia Existente
      </VCardTitle>
      <VCardText class="pa-4 pt-0">
        <div class="text-body-1 mb-3">
          Este empleado ya tiene una carta de renuncia generada:
        </div>
        <VCard variant="outlined" class="pa-3 mb-3">
          <div class="text-body-2 text-medium-emphasis">Empleado:</div>
          <div class="text-body-1 font-weight-medium">
            {{ duplicateResignationData?.employee_name }}
          </div>
          <div class="text-body-2 text-medium-emphasis mt-2">Tipo:</div>
          <div class="text-body-1 font-weight-medium">
            {{
              duplicateResignationData?.resignation_type === "voluntary"
                ? "Renuncia Voluntaria"
                : "Despido Injustificado"
            }}
          </div>
          <div class="text-body-2 text-medium-emphasis mt-2">
            Fecha Efectiva:
          </div>
          <div class="text-body-1 font-weight-medium">
            {{ formatDate(duplicateResignationData?.effective_date) }}
          </div>
        </VCard>
        <div class="text-body-1">
          ¿Desea editar la carta de renuncia existente?
        </div>
      </VCardText>
      <VCardActions class="pa-4 pt-0">
        <VBtn
          color="grey"
          variant="outlined"
          @click="cancelEditResignation"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="confirmEditResignation"
          class="flex-grow-1 w-0"
        >
          <VIcon icon="tabler-edit" class="me-2" />
          Editar Renuncia!
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.v-card {
  border-radius: 12px;
}

.v-dialog .v-card {
  max-height: 90vh;
  overflow-y: auto;
}

.text-medium-emphasis {
  opacity: 0.7;
}
</style>
