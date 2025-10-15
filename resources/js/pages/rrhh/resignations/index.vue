<script setup>
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "axios";
import Swal from "sweetalert2";
import { onMounted, ref } from "vue";

// Estado reactivo
const loading = ref(false);
const resignations = ref([]);
const stats = ref({});
const showConfirmDialog = ref(false);
const employeeToToggle = ref(null);
const newStatus = ref(null);

// Variables para el modal de edición
const showResignationDialog = ref(false);
const selectedEmployeeForResignation = ref(null);
const isEditingResignation = ref(false);
const existingResignationData = ref(null);

// Métodos
const fetchResignations = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/api/rrhh/resignations");

    if (data.success) {
      resignations.value = data.data;
    } else {

      toast.error("Error en la respuesta del servidor");
    }
  } catch (error) {

    toast.error(
      `Error al cargar las renuncias: ${
        error.response?.data?.message || error.message
      }`
    );
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const { data } = await axios.get("/api/rrhh/resignations/stats");
    if (data.success) {
      stats.value = data.data;
    }
  } catch (error) {

  }
};

const openToggleConfirmDialog = (employeeId, currentStatus, employeeName) => {
  employeeToToggle.value = {
    id: employeeId,
    name: employeeName,
    currentStatus: currentStatus,
  };
  newStatus.value = !currentStatus;
  showConfirmDialog.value = true;
};

const confirmToggleStatus = async () => {
  try {
    await axios.put("/api/rrhh/resignations/toggle-employee-status", {
      employee_id: employeeToToggle.value.id,
      is_active: newStatus.value,
    });

    toast.success(
      `Empleado ${newStatus.value ? "activado" : "desactivado"} exitosamente`
    );

    // Actualizar la lista y estadísticas
    fetchResignations();
    fetchStats();

    // Cerrar modal
    showConfirmDialog.value = false;
    employeeToToggle.value = null;
    newStatus.value = null;
  } catch (error) {

    toast.error("Error al cambiar el estado del empleado");
  }
};

const cancelToggleStatus = () => {
  showConfirmDialog.value = false;
  employeeToToggle.value = null;
  newStatus.value = null;
};

const downloadResignationPDF = async (resignation) => {
  try {
    const response = await axios.get(
      `/api/rrhh/resignations/${resignation.id}/download-pdf`,
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
      `carta-renuncia-${resignation.employee_identification}.pdf`
    );
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Carta de renuncia descargada exitosamente");
  } catch (error) {

    toast.error("No se pudo descargar la carta de renuncia");
  }
};

const editResignation = async (resignation) => {
  try {
    const confirmed = await Swal.fire({
      title: "¿Editar carta de renuncia?",
      html: `
        <div class="text-left">
          <p><strong>Empleado:</strong> ${resignation.employee_name}</p>
          <p><strong>Identificación:</strong> ${
            resignation.employee_identification
          }</p>
          <p><strong>Tipo:</strong> ${
            resignation.resignation_type === "voluntary"
              ? "Renuncia Voluntaria"
              : "Despido Injustificado"
          }</p>
          <p><strong>Fecha Efectiva:</strong> ${formatDate(
            resignation.effective_date
          )}</p>
        </div>
        <p class="mt-3">¿Desea editar esta carta de renuncia?</p>
      `,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#ff9800",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, editar",
      cancelButtonText: "Cancelar",
      width: "500px",
    });

    if (confirmed.isConfirmed) {
      // Obtener los datos de la renuncia existente
      const response = await axios.get(
        `/api/rrhh/resignations/${resignation.id}/edit`
      );

      if (response.data.success) {
        // Configurar para edición
        selectedEmployeeForResignation.value = {
          id: resignation.employee_id,
          name: resignation.employee_name.split(" ")[0],
          last_name: resignation.employee_name.split(" ").slice(1).join(" "),
          identification: resignation.employee_identification,
          email: resignation.employee_email,
          position: resignation.employee_position,
          start_date: resignation.start_date,
        };
        existingResignationData.value = response.data.data;
        isEditingResignation.value = true;
        showResignationDialog.value = true;

        toast.success("Datos de renuncia cargados para edición");
      }
    }
  } catch (error) {

    toast.error("No se pudieron cargar los datos para edición");
  }
};

const deleteResignation = async (resignation) => {
  try {
    const confirmed = await Swal.fire({
      title: "¿Eliminar carta de renuncia?",
      html: `
        <div class="text-left">
          <p><strong>Empleado:</strong> ${resignation.employee_name}</p>
          <p><strong>Identificación:</strong> ${
            resignation.employee_identification
          }</p>
          <p><strong>Tipo:</strong> ${
            resignation.resignation_type === "voluntary"
              ? "Renuncia Voluntaria"
              : "Despido Injustificado"
          }</p>
          <p><strong>Fecha Efectiva:</strong> ${formatDate(
            resignation.effective_date
          )}</p>
        </div>
        <div class="alert alert-warning mt-3" style="background-color: transparent; border: 2px solid #ffc107; padding: 10px; border-radius: 5px; color: #ffc107;">
          <strong>⚠️ Advertencia:</strong> Esta acción eliminará la carta de renuncia. El empleado seguirá activo en el sistema.
        </div>
        <p class="mt-3"><strong>¿Está seguro de que desea eliminar esta carta de renuncia?</strong></p>
      `,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      width: "600px",
    });

    if (confirmed.isConfirmed) {
      await axios.delete(`/api/rrhh/resignations/${resignation.id}`);

      toast.success("Renuncia eliminada exitosamente");
      fetchResignations(); // Recargar la lista
      fetchStats(); // Actualizar estadísticas
    }
  } catch (error) {

    toast.error("No se pudo eliminar la renuncia");
  }
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

// Función para manejar cuando se genera una renuncia (nueva o editada)
const handleResignationGenerated = () => {
  showResignationDialog.value = false;
  isEditingResignation.value = false;
  existingResignationData.value = null;
  selectedEmployeeForResignation.value = null;
  fetchResignations();
  fetchStats();
};

// Lifecycle
onMounted(() => {
  fetchResignations();
  fetchStats();
});
</script>

<template>
  <div>
    <!-- Header con estadísticas -->
    <VRow class="mb-6">
      <VCol cols="12" md="3">
        <VCard color="primary" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">{{ stats.total || 0 }}</div>
            <div class="text-body-2">Total Renuncias</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="3">
        <VCard color="success" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ stats.voluntary || 0 }}
            </div>
            <div class="text-body-2">Justificadas</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="3">
        <VCard color="warning" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ stats.unjustified_dismissal || 0 }}
            </div>
            <div class="text-body-2">Injustificadas</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="3">
        <VCard color="info" variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ stats.this_month || 0 }}
            </div>
            <div class="text-body-2">Este Mes</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Renuncias -->
    <VCard>
      <VCardTitle>
        <VIcon icon="tabler-file-text" class="me-2" />
        Listado de Renuncias Generadas
      </VCardTitle>
      <VCardText>
        <div v-if="loading" class="text-center py-4">
          <VProgressCircular indeterminate color="primary" />
          <div class="mt-2">Cargando renuncias...</div>
        </div>
        <div v-else-if="resignations.length === 0" class="text-center py-8">
          <VIcon icon="tabler-file-x" size="64" color="grey" />
          <div class="text-h6 mt-4">No hay renuncias generadas</div>
          <div class="text-body-2 text-medium-emphasis">
            Las renuncias aparecerán aquí una vez que se generen cartas de
            renuncia
          </div>
        </div>
        <div v-else>
          <!-- Tabla responsiva con scroll horizontal -->
          <div class="table-responsive">
            <VTable class="resignations-table">
              <thead>
                <tr>
                  <th class="text-left">Empleado</th>
                  <th class="text-left">Identificación</th>
                  <th class="text-center">Tipo</th>
                  <th class="text-center">Fecha Efectiva</th>
                  <th class="text-center">Estado</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="resignation in resignations" :key="resignation.id">
                  <td>
                    <div class="text-body-1 font-weight-medium">
                      {{ resignation.employee_name }}
                    </div>
                  </td>
                  <td>
                    <div class="text-body-1">
                      {{ resignation.employee_identification }}
                    </div>
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="
                        resignation.resignation_type === 'voluntary'
                          ? 'success'
                          : 'warning'
                      "
                      size="small"
                    >
                      {{
                        resignation.resignation_type === "voluntary"
                          ? "Justificada"
                          : "Injustificada"
                      }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <div class="text-body-1">
                      {{ formatDate(resignation.effective_date) }}
                    </div>
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="
                        resignation.employee_status === 'Activo'
                          ? 'success'
                          : 'error'
                      "
                      size="small"
                    >
                      {{ resignation.employee_status }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <div class="d-flex align-center justify-center gap-2">
                      <VTooltip
                        text="Descargar Carta de Renuncia"
                        location="top"
                      >
                        <template #activator="{ props }">
                          <VBtn
                            v-bind="props"
                            color="primary"
                            size="small"
                            @click="downloadResignationPDF(resignation)"
                          >
                            <VIcon icon="tabler-download" />
                          </VBtn>
                        </template>
                      </VTooltip>

                      <VTooltip text="Editar Renuncia" location="top">
                        <template #activator="{ props }">
                          <VBtn
                            v-bind="props"
                            color="warning"
                            size="small"
                            @click="editResignation(resignation)"
                          >
                            <VIcon icon="tabler-edit" />
                          </VBtn>
                        </template>
                      </VTooltip>

                      <VTooltip text="Eliminar Renuncia" location="top">
                        <template #activator="{ props }">
                          <VBtn
                            v-bind="props"
                            color="error"
                            size="small"
                            @click="deleteResignation(resignation)"
                          >
                            <VIcon icon="tabler-trash" />
                          </VBtn>
                        </template>
                      </VTooltip>

                      <VTooltip
                        text="Cambiar Estado del Empleado"
                        location="top"
                      >
                        <template #activator="{ props }">
                          <VBtn
                            v-bind="props"
                            :color="
                              resignation.employee_status === 'Activo'
                                ? 'error'
                                : 'success'
                            "
                            size="small"
                            @click="
                              openToggleConfirmDialog(
                                resignation.employee_id,
                                resignation.employee_status === 'Activo',
                                resignation.employee_name
                              )
                            "
                          >
                            <VIcon
                              :icon="
                                resignation.employee_status === 'Activo'
                                  ? 'tabler-user-minus'
                                  : 'tabler-user-plus'
                              "
                            />
                          </VBtn>
                        </template>
                      </VTooltip>
                    </div>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Modal de Confirmación -->
    <VDialog v-model="showConfirmDialog" max-width="500px" persistent>
      <VCard>
        <VCardTitle class="d-flex align-center">
          <VIcon
            :icon="newStatus ? 'tabler-user-plus' : 'tabler-user-minus'"
            :color="newStatus ? 'success' : 'error'"
            class="me-2"
          />
          <span class="headline">
            {{ newStatus ? "Activar Empleado" : "Desactivar Empleado" }}
          </span>
        </VCardTitle>

        <VDivider />

        <VCardText class="pt-4">
          <div class="text-body-1 mb-4">
            <strong>{{ employeeToToggle?.name }}</strong>
          </div>

          <div v-if="newStatus" class="text-body-2">
            <VIcon icon="tabler-info-circle" color="success" class="me-2" />
            <strong>¿Está seguro de que desea activar este empleado?</strong>
          </div>

          <div v-else class="text-body-2">
            <VIcon icon="tabler-info-circle" color="error" class="me-2" />
            <strong>¿Está seguro de que desea desactivar este empleado?</strong>
          </div>

          <VAlert
            :color="newStatus ? 'success' : 'error'"
            variant="tonal"
            class="mt-4"
          >
            <template #prepend>
              <VIcon :icon="newStatus ? 'tabler-eye' : 'tabler-eye-off'" />
            </template>

            <div v-if="newStatus">
              <strong
                >El empleado volverá a aparecer en la lista de empleados
                activos</strong
              >
              <div class="text-body-2 mt-1">
                Podrá acceder al sistema y realizar sus funciones normalmente.
              </div>
            </div>

            <div v-else>
              <strong
                >El empleado ya no aparecerá en la lista de empleados
                activos</strong
              >
              <div class="text-body-2 mt-1">
                No podrá acceder al sistema hasta que sea reactivado.
              </div>
            </div>
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VBtn
            color="secondary"
            variant="outlined"
            @click="cancelToggleStatus"
            class="flex-grow-1"
          >
            <VIcon icon="tabler-x" class="me-2" />
            Cancelar
          </VBtn>
          <VBtn
            :color="newStatus ? 'success' : 'error'"
            variant="flat"
            @click="confirmToggleStatus"
            class="flex-grow-1"
          >
            <VIcon
              :icon="newStatus ? 'tabler-check' : 'tabler-check'"
              class="me-2"
            />
            {{ newStatus ? "Activar" : "Desactivar" }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de formulario de renuncia -->
    <ResignationFormDialog
      v-model="showResignationDialog"
      :selectedEmployee="selectedEmployeeForResignation"
      :isEdit="isEditingResignation"
      :existingResignation="existingResignationData"
      @resignation-generated="handleResignationGenerated"
    />
  </div>
</template>

<style scoped>
.v-card {
  border-radius: 12px;
}

/* Estilos para tabla responsiva */
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  margin: -16px; /* Compensar el padding del VCardText */
  padding: 16px; /* Restaurar el padding interno */
}

.resignations-table {
  min-width: 800px; /* Ancho mínimo para mantener legibilidad */
  width: 100%;
}

.resignations-table th {
  background-color: rgba(var(--v-theme-primary), 0.1);
  font-weight: 600;
  padding: 16px 12px;
  border-bottom: 2px solid rgba(var(--v-theme-primary), 0.2);
  white-space: nowrap;
}

.resignations-table td {
  padding: 16px 12px;
  border-bottom: 1px solid rgba(var(--v-theme-border), 0.2);
  vertical-align: middle;
}

.resignations-table tbody tr:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
  transition: background-color 0.2s ease;
}

.resignations-table tbody tr:last-child td {
  border-bottom: none;
}

/* Evitar scroll en el contenedor padre */
.v-card-text {
  overflow-x: hidden !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .resignations-table {
    min-width: 600px;
  }

  .resignations-table th,
  .resignations-table td {
    padding: 12px 8px;
    font-size: 0.875rem;
  }
}

@media (max-width: 480px) {
  .resignations-table {
    min-width: 500px;
  }

  .resignations-table th,
  .resignations-table td {
    padding: 8px 6px;
    font-size: 0.8rem;
  }

  .resignations-table .d-flex {
    flex-direction: column;
    gap: 4px;
  }
}
</style>
