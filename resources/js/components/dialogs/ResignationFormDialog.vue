<script setup>
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";
import { ref, watch, computed } from "vue";
import { useAuthStore } from "@/stores/auth.js";
import { useDisplay } from "vuetify";

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

const { mobile } = useDisplay();
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

const formatIdentification = (val) => {
  if (!val) return "—";
  const cleaned = String(val).replace(/\D/g, "");
  if (!cleaned) return String(val);
  const withDots = cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return `V-${withDots}`;
};

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
            const userEmp = employees.value.find(
              (e) =>
                Number(e.user_id) === Number(authStore.user.id) ||
                e.email === authStore.user.email ||
                (e.name &&
                  authStore.user.name &&
                  e.name.toLowerCase().includes(authStore.user.name.toLowerCase()))
            );
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
          const userEmp = employees.value.find(
            (e) =>
              Number(e.user_id) === Number(authStore.user.id) ||
              e.email === authStore.user.email ||
              (e.name &&
                authStore.user.name &&
                e.name.toLowerCase().includes(authStore.user.name.toLowerCase()))
          );
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
      const match = employees.value.find(
        (e) =>
          Number(e.user_id) === Number(authStore.user.id) ||
          e.email === authStore.user.email ||
          (e.name &&
            authStore.user.name &&
            e.name.toLowerCase().includes(authStore.user.name.toLowerCase()))
      );
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

watch(
  [() => props.isEdit, () => props.existingResignation, currentEmployee],
  ([isEdit, existingResignation, emp]) => {
    if (emp) {
      hireDate.value = emp.created_at?.split("T")[0] || "";
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
    errors.value.effectiveDate = "Debe seleccionar la fecha efectiva de renuncia";
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

    if (!props.isEdit) {
      resignationData.request_date = requestDate.value;
    }

    resignationData.is_edit = props.isEdit;

    const response = await axios.post(
      "/rrhh/resignations/generate",
      resignationData,
      {
        responseType: "blob",
        headers: {
          Accept: "application/pdf",
        },
      }
    );

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
    let errorMsg = "No se pudo generar la carta de renuncia";
    if (error.response?.data instanceof Blob) {
      try {
        const text = await error.response.data.text();
        const errorData = JSON.parse(text);
        if (error.response.status === 409) {
          duplicateResignationData.value = errorData.existing_resignation;
          showDuplicateConfirm.value = true;
          loading.value = false;
          return;
        } else if (error.response.status === 422) {
          errors.value = errorData.errors || {};
          errorMsg = errorData.message || "Error de validación en los datos";
        } else if (errorData.message || errorData.error) {
          errorMsg = errorData.message || errorData.error;
        }
      } catch (e) {}
    } else {
      if (error.response?.status === 409) {
        duplicateResignationData.value = error.response.data.existing_resignation;
        showDuplicateConfirm.value = true;
        loading.value = false;
        return;
      } else if (error.response?.status === 422) {
        errors.value = error.response.data.errors || {};
        errorMsg = error.response.data.message || "Error de validación en los datos";
      } else if (error.response?.data?.message) {
        errorMsg = error.response.data.message;
      }
    }
    toast.error(errorMsg);
  } finally {
    loading.value = false;
  }
};

const confirmEditResignation = () => {
  showDuplicateConfirm.value = false;
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

const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const day = date.getDate().toString().padStart(2, "0");
  const month = (date.getMonth() + 1).toString().padStart(2, "0");
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
};

const maxDate = computed(() => {
  const max = new Date();
  max.setFullYear(max.getFullYear() + 1);
  return max.toISOString().split("T")[0];
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="650px"
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
          <VAvatar color="white" variant="flat" size="44" class="me-3 elevation-2">
            <VIcon icon="tabler-file-text" color="primary" size="24" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.isEdit ? 'Editar Carta de Renuncia' : 'Generar Carta de Renuncia' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-0.5">
              <span class="text-super-xs text-white opacity-75 font-weight-bold">
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
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light d-flex flex-column gap-3">
        <!-- Seccion: Información del Empleado -->
        <div class="bg-white pa-3.5 rounded-lg border">
          <div class="d-flex align-center gap-2 mb-2.5">
            <div class="header-indicator primary shadow-sm"></div>
            <span class="text-caption font-weight-bold text-high-emphasis">Datos del Empleado</span>
          </div>

          <VRow dense>
            <VCol v-if="props.selectedEmployee || (!authStore.isAdmin && currentEmployee)" cols="12" sm="6">
              <div class="text-super-xs font-weight-bold text-disabled uppercase mb-0.5">Nombre Completo</div>
              <div class="text-xs font-weight-bold text-high-emphasis">
                {{ currentEmployee?.name }} {{ currentEmployee?.last_name }}
              </div>
            </VCol>
            <VCol v-if="props.selectedEmployee || (!authStore.isAdmin && currentEmployee)" cols="12" sm="6">
              <div class="text-super-xs font-weight-bold text-disabled uppercase mb-0.5">Identificación</div>
              <div class="text-xs font-weight-bold text-high-emphasis tabular-nums">
                {{ formatIdentification(currentEmployee?.identification) }}
              </div>
            </VCol>
            <VCol v-if="!props.selectedEmployee && authStore.isAdmin" cols="12">
              <VAutocomplete
                v-model="selectedEmployeeId"
                :items="employees"
                item-title="name"
                item-value="id"
                :readonly="!authStore.isAdmin"
                :custom-filter="(item, queryText) => (item.raw.name + ' ' + item.raw.last_name + ' ' + item.raw.identification).toLowerCase().includes(queryText.toLowerCase())"
                :item-props="item => ({ title: `${item.name} ${item.last_name}`, subtitle: formatIdentification(item.identification) })"
                label="Seleccionar Empleado *"
                placeholder="Buscar por nombre o cédula..."
                variant="outlined"
                density="compact"
                hide-details="auto"
                prepend-inner-icon="tabler-user-search"
              />
            </VCol>
            <VCol cols="12" :class="!props.selectedEmployee && authStore.isAdmin ? 'mt-2' : ''">
              <AppTextField
                v-model="hireDate"
                label="Fecha de Ingreso"
                type="date"
                variant="outlined"
                density="compact"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </div>

        <!-- Seccion: Detalles de la Renuncia -->
        <div class="bg-white pa-3.5 rounded-lg border">
          <div class="d-flex align-center gap-2 mb-2.5">
            <div class="header-indicator warning shadow-sm"></div>
            <span class="text-caption font-weight-bold text-high-emphasis">Detalles de la Renuncia</span>
          </div>

          <VForm @submit.prevent="generateResignation">
            <VRow dense>
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="resignationType"
                  label="Tipo de Renuncia *"
                  variant="outlined"
                  density="compact"
                  :items="resignationTypes"
                  :error-messages="errors.resignationType"
                  :disabled="loading"
                  :readonly="!authStore.isAdmin"
                  required
                  hide-details="auto"
                />
              </VCol>

              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="employeePosition"
                  label="Cargo del Empleado"
                  variant="outlined"
                  density="compact"
                  :error-messages="errors.employeePosition"
                  :disabled="loading"
                  :readonly="!authStore.isAdmin"
                  placeholder="Ej: Vendedora, Cajero..."
                  hide-details="auto"
                />
              </VCol>

              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="requestDate"
                  label="Fecha de Solicitud *"
                  type="date"
                  variant="outlined"
                  density="compact"
                  :disabled="loading"
                  hide-details="auto"
                />
              </VCol>

              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="effectiveDate"
                  label="Fecha Efectiva *"
                  type="date"
                  variant="outlined"
                  density="compact"
                  :max="maxDate"
                  :error-messages="errors.effectiveDate"
                  :disabled="loading"
                  required
                  hide-details="auto"
                />
              </VCol>
            </VRow>
          </VForm>
        </div>

        <!-- Resumen -->
        <VExpandTransition>
          <div v-if="currentEmployee && resignationType && effectiveDate">
            <VCard variant="flat" class="bg-primary-lighten-5 rounded-lg border border-dashed pa-3">
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon icon="tabler-info-circle" size="16" color="primary" />
                <span class="text-super-xs font-weight-bold text-primary uppercase letter-spacing-1">Resumen del Documento</span>
              </div>
              <p class="text-xs text-high-emphasis leading-tight mb-0">
                <strong class="text-primary">{{ currentEmployee.name }} {{ currentEmployee.last_name }}</strong>
                solicita
                <strong class="text-warning">{{ resignationTypes.find((t) => t.value === resignationType)?.title.toLowerCase() }}</strong>
                como
                <strong class="text-high-emphasis">{{ employeePosition || "empleado" }}</strong>
                con fecha efectiva el
                <strong class="text-error">{{ formatDate(effectiveDate) }}</strong>.
              </p>
            </VCard>
          </div>
        </VExpandTransition>
      </VCardText>

      <VCardActions class="pa-3 bg-light border-t">
        <VRow no-gutters class="w-100 gap-2 justify-end">
          <VCol cols="auto">
            <VBtn
              color="secondary"
              variant="outlined"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-4 text-none"
              @click="closeDialog"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              color="primary"
              variant="flat"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-5 shadow-primary text-none"
              @click="generateResignation"
              :loading="loading"
              :disabled="!currentEmployee || !resignationType || !effectiveDate"
            >
              <VIcon start icon="tabler-file-download" class="me-1" />
              {{ props.isEdit ? 'Actualizar Carta' : 'Generar Carta' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Modal de confirmación para editar renuncia existente -->
  <VDialog v-model="showDuplicateConfirm" max-width="500">
    <VCard class="detail-dialog-card rounded-lg overflow-hidden border-0 elevation-12">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
            <VIcon icon="tabler-alert-triangle" color="warning" size="22" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Renuncia Existente
            </h2>
          </div>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light">
        <div class="bg-white pa-3.5 rounded-lg border">
          <p class="text-xs text-medium-emphasis mb-2">
            Este empleado ya cuenta con una carta de renuncia previa:
          </p>
          <div class="text-xs font-weight-bold mb-1">
            Empleado: <span class="text-primary">{{ duplicateResignationData?.employee_name }}</span>
          </div>
          <div class="text-xs font-weight-bold mb-1">
            Tipo: <span>{{ duplicateResignationData?.resignation_type === "voluntary" ? "Renuncia Justificada" : "Renuncia Injustificada" }}</span>
          </div>
          <div class="text-xs font-weight-bold mb-3">
            Fecha Efectiva: <span>{{ formatDate(duplicateResignationData?.effective_date) }}</span>
          </div>
          <p class="text-xs font-weight-bold text-high-emphasis mb-0">
            ¿Desea editar la carta de renuncia existente?
          </p>
        </div>
      </VCardText>

      <VCardActions class="pa-3 bg-light border-t">
        <VRow no-gutters class="w-100 gap-2 justify-end">
          <VCol cols="auto">
            <VBtn
              color="secondary"
              variant="outlined"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-4 text-none"
              @click="cancelEditResignation"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              color="primary"
              variant="flat"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-5 text-none"
              @click="confirmEditResignation"
            >
              <VIcon start icon="tabler-edit" class="me-1" />
              Editar Renuncia
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
    rgb(var(--v-theme-gradient-end)) 100%
  );
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

.header-indicator.warning {
  background-color: rgb(var(--v-theme-warning));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
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
</style>
