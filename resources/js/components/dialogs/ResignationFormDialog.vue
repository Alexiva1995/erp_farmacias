<script setup>
import { toast } from "@/plugins/sweetalert";
import axios from "axios";
import { ref, watch, computed } from "vue";
import { useAuthStore } from "@/stores/auth.js";

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

const authStore = useAuthStore();
const errors = ref({});
const loading = ref(false);
const resignationType = ref("");
const effectiveDate = ref("");
const requestDate = ref(new Date().toISOString().split("T")[0]);
const employeePosition = ref("");
const hireDate = ref("");
const showDuplicateConfirm = ref(false);
const duplicateResignationData = ref(null);

const employees = ref([]);
const selectedEmployeeId = ref(null);

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      if (!authStore.isAdmin) {
        resignationType.value = "voluntary";
        employeePosition.value = "Cajera";
      }

      if (!props.selectedEmployee && employees.value.length === 0) {
        try {
          const { data } = await axios.get("/rrhh/employees", {
            params: { perPage: 1000, active: true },
          });
          employees.value = data.data || [];

          if (!authStore.isAdmin && authStore.user && employees.value.length > 0) {
            const userEmp = employees.value.find(e => Number(e.user_id) === Number(authStore.user.id) || e.email === authStore.user.email || (e.name && authStore.user.name && e.name.toLowerCase().includes(authStore.user.name.toLowerCase())));
            if (userEmp) {
              selectedEmployeeId.value = userEmp.id;
            } else {
              selectedEmployeeId.value = employees.value[0].id;
            }
          }
        } catch (error) {
          toast.error("Error al cargar la lista de empleados");
        }
      } else if (!props.selectedEmployee && employees.value.length > 0) {
        if (!authStore.isAdmin && authStore.user) {
          const userEmp = employees.value.find(e => Number(e.user_id) === Number(authStore.user.id) || e.email === authStore.user.email || (e.name && authStore.user.name && e.name.toLowerCase().includes(authStore.user.name.toLowerCase())));
          if (userEmp) {
            selectedEmployeeId.value = userEmp.id;
          } else {
            selectedEmployeeId.value = employees.value[0].id;
          }
        }
      }
    } else {
      selectedEmployeeId.value = null;
    }
  }
);

const currentEmployee = computed(() => {
  if (props.selectedEmployee) return props.selectedEmployee;
  if (selectedEmployeeId.value && employees.value.length) {
    return employees.value.find((e) => e.id === selectedEmployeeId.value) || null;
  }
  if (!authStore.isAdmin && employees.value.length > 0) {
    if (authStore.user) {
      const match = employees.value.find(e => Number(e.user_id) === Number(authStore.user.id) || e.email === authStore.user.email || (e.name && authStore.user.name && e.name.toLowerCase().includes(authStore.user.name.toLowerCase())));
      if (match) return match;
    }
    return employees.value[0];
  }
  return null;
});

const resignationTypes = [
  { title: "Renuncia Justificada", value: "voluntary" },
  { title: "Renuncia Injustificada", value: "unjustified_dismissal" },
];

// Watcher para pre-llenar campos cuando se está editando
watch(
  [() => props.isEdit, () => props.existingResignation, currentEmployee],
  ([isEdit, existingResignation, emp]) => {
    if (emp) {
      hireDate.value = emp.created_at?.split("T")[0] || ""; // Fallback si no hay hire_date explícito
    }
    if (isEdit && existingResignation) {
      resignationType.value = existingResignation.resignation_type || "";
      effectiveDate.value = existingResignation.effective_date || "";
      requestDate.value =
        existingResignation.request_date ||
        new Date().toISOString().split("T")[0];
      employeePosition.value = existingResignation.employee_position || "";
      if (existingResignation.start_date) {
        hireDate.value = existingResignation.start_date;
      }
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
  employeePosition.value = ""; 
  selectedEmployeeId.value = null;
  loading.value = false;
};

const validateForm = () => {
  errors.value = {};

  if (!currentEmployee.value) {
    errors.value.employee = "Debe seleccionar un empleado";
    toast.error("Debe seleccionar un empleado");
  }

  if (!resignationType.value) {
    errors.value.resignationType = "Debe seleccionar el tipo de renuncia";
  }

  if (!effectiveDate.value) {
    errors.value.effectiveDate =
      "Debe seleccionar la fecha efectiva de renuncia";
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
      employee_id: currentEmployee.value.id,
      employee_name: `${currentEmployee.value.name} ${currentEmployee.value.last_name}`,
      employee_identification: currentEmployee.value.identification,
      employee_email: currentEmployee.value.email,
      employee_status: currentEmployee.value.is_active ? "Activo" : "Inactivo",
      employee_position: employeePosition.value || "empleado", 
      start_date: hireDate.value, 
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
// Se elimina minDate para permitir fechas efectivas pasadas
const minDate = null;

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
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    @update:model-value="closeDialog"
  >
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card overflow-hidden border-0 elevation-12'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-file-text" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Generar Carta de Renuncia
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                Administración de Personal y Egresos
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg ms-3"
            @click="closeDialog"
            :disabled="loading"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6">
        <div class="d-flex flex-column gap-6">
          
          <!-- Seccion: Información del Empleado -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Datos del Empleado</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow>
                <VCol v-if="props.selectedEmployee || (!authStore.isAdmin && currentEmployee)" cols="12" sm="6" class="py-1">
                  <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">Nombre Completo</div>
                  <div class="text-xs font-weight-black text-high-emphasis tracking-tight">
                    {{ currentEmployee?.name }} {{ currentEmployee?.last_name }}
                  </div>
                </VCol>
                <VCol v-if="props.selectedEmployee || (!authStore.isAdmin && currentEmployee)" cols="12" sm="6" class="py-1">
                  <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">Identificación</div>
                  <div class="text-xs font-weight-black text-high-emphasis tabular-nums">
                    {{ currentEmployee?.identification }}
                  </div>
                </VCol>
                <VCol v-if="!props.selectedEmployee && authStore.isAdmin" cols="12" class="py-1">
                  <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">Seleccionar Empleado *</div>
                  <VAutocomplete
                    v-model="selectedEmployeeId"
                    :items="employees"
                    item-title="name"
                    item-value="id"
                    :readonly="!authStore.isAdmin"
                    :custom-filter="(item, queryText, itemText) => (item.raw.name + ' ' + item.raw.last_name + ' ' + item.raw.identification).toLowerCase().includes(queryText.toLowerCase())"
                    :item-props="item => ({ title: `${item.name} ${item.last_name}`, subtitle: item.identification })"
                    placeholder="Buscar y seleccionar empleado..."
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    prepend-inner-icon="tabler-user-search"
                    class="premium-input-compact"
                  />
                </VCol>
                <VCol cols="12" class="py-1 mt-2">
                  <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">Fecha de Ingreso</div>
                  <VTextField
                    v-model="hireDate"
                    type="date"
                    variant="outlined"
                    density="compact"
                    hide-details
                    class="premium-input-compact"
                    :readonly="false"
                    prepend-inner-icon="tabler-calendar"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Seccion: Detalles de la Renuncia -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator warning shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Detalles de la Renuncia</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VForm @submit.prevent="generateResignation">
                <VRow>
                  <VCol cols="12">
                    <VSelect
                      v-model="resignationType"
                      label="Tipo de Renuncia *"
                      variant="outlined"
                      density="comfortable"
                      :items="resignationTypes"
                      :error-messages="errors.resignationType"
                      :disabled="loading"
                      :readonly="!authStore.isAdmin"
                      required
                      prepend-inner-icon="tabler-category"
                      class="shadow-sm"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextField
                      v-model="employeePosition"
                      label="Cargo del Empleado"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.employeePosition"
                      :disabled="loading"
                      :readonly="!authStore.isAdmin"
                      placeholder="Ejemplo: vendedora"
                      prepend-inner-icon="tabler-briefcase"
                      class="shadow-sm"
                      hide-details="auto"
                    >
                      <template #append-inner>
                        <VTooltip location="top">
                          <template #activator="{ props }">
                            <VIcon
                              icon="tabler-help-circle"
                              size="18"
                              color="grey"
                              v-bind="props"
                              class="opacity-60"
                            />
                          </template>
                          <span>Ejemplo: vendedora, cajero, farmacéutico, etc. Si no se especifica, se usará 'empleado'</span>
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
                      density="comfortable"
                      :disabled="loading"
                      :readonly="false"
                      class="shadow-sm opacity-80"
                      hide-details="auto"
                      prepend-inner-icon="tabler-calendar-check"
                    />
                  </VCol>

                  <VCol cols="12" sm="6">
                    <VTextField
                      v-model="effectiveDate"
                      label="Fecha Efectiva *"
                      type="date"
                      variant="outlined"
                      density="comfortable"
                      :max="maxDate"
                      :error-messages="errors.effectiveDate"
                      :disabled="loading"
                      required
                      class="shadow-sm"
                      hide-details="auto"
                      prepend-inner-icon="tabler-calendar-event"
                    />
                  </VCol>
                </VRow>
              </VForm>
            </VCard>
          </section>

          <!-- Resumen con Estilo Premium -->
          <VExpandTransition>
            <div v-if="currentEmployee && resignationType && effectiveDate">
              <VCard variant="flat" border class="resignation-summary rounded-lg overflow-hidden">
                <div class="pa-4 bg-light d-flex align-center border-b">
                  <VIcon icon="tabler-info-circle" class="me-2" color="warning" />
                  <span class="text-super-xs font-weight-black text-warning uppercase letter-spacing-1">Resumen del Documento</span>
                </div>
                <VCardText class="pa-4 bg-white">
                  <p class="text-xs text-high-emphasis leading-relaxed mb-0">
                    <span class="font-weight-black text-primary">{{ currentEmployee.name }} {{ currentEmployee.last_name }}</span>
                    solicita
                    <span class="font-weight-black text-warning">{{ resignationTypes.find((t) => t.value === resignationType)?.title.toLowerCase() }}</span>
                    como
                    <span class="font-weight-black">{{ employeePosition || "empleado" }}</span>
                    con fecha efectiva el
                    <span class="font-weight-black text-error">{{ formatDate(effectiveDate) }}</span>.
                  </p>
                </VCardText>
              </VCard>
            </div>
          </VExpandTransition>
        </div>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
              :disabled="loading"
            >
              <VIcon :icon="mobile ? 'tabler-x' : ''" :start="!mobile" :class="mobile ? '' : 'me-2'" />
              <span v-if="!mobile">Cancelar</span>
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="generateResignation"
              :loading="loading"
              :disabled="!currentEmployee || !resignationType || !effectiveDate"
            >
              <VIcon icon="tabler-file-download" :class="mobile ? '' : 'me-2'" />
              <span v-if="!mobile">Generar Carta</span>
            </VBtn>
          </VCol>
        </VRow>
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
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 8px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.warning {
  background-color: rgb(var(--v-theme-warning));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.shadow-sm {
  box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05) !important;
}

.bg-light {
  background-color: #f8fafc !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.premium-input-compact :deep(.v-field) {
  background-color: white !important;
  border-radius: 8px !important;
}

.resignation-summary {
  border: 1px solid rgba(var(--v-theme-warning), 20%) !important;
}

.last\:border-0:last-child {
  border-bottom: 0 !important;
}
</style>
