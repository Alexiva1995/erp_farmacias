<script setup>
import { toast } from "@/plugins/sweetalert";
import axios from "axios";
import { onMounted, ref } from "vue";

// Estado reactivo
const loading = ref(false);
const resignations = ref([]);
const stats = ref({});
const showConfirmDialog = ref(false);
const employeeToToggle = ref(null);
const newStatus = ref(null);

// Métodos
const fetchResignations = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/api/rrhh/resignations");

    if (data.success) {
      resignations.value = data.data;
    }
  } catch (error) {
    console.error("Error fetching resignations:", error);
    toast.error("Error al cargar las renuncias");
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
    console.error("Error fetching stats:", error);
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

    // Actualizar la lista
    fetchResignations();

    // Cerrar modal
    showConfirmDialog.value = false;
    employeeToToggle.value = null;
    newStatus.value = null;
  } catch (error) {
    console.error("Error toggling employee status:", error);
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
    console.error("Error downloading PDF:", error);
    toast.error("No se pudo descargar la carta de renuncia");
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
          <div
            v-for="resignation in resignations"
            :key="resignation.id"
            class="mb-4"
          >
            <VCard variant="outlined">
              <VCardText>
                <VRow>
                  <VCol cols="12" md="3">
                    <div class="text-body-2 text-medium-emphasis">Empleado</div>
                    <div class="text-body-1 font-weight-medium">
                      {{ resignation.employee_name }}
                    </div>
                  </VCol>
                  <VCol cols="12" md="2">
                    <div class="text-body-2 text-medium-emphasis">
                      Identificación
                    </div>
                    <div class="text-body-1">
                      {{ resignation.employee_identification }}
                    </div>
                  </VCol>
                  <VCol cols="12" md="2">
                    <div class="text-body-2 text-medium-emphasis">Tipo</div>
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
                  </VCol>
                  <VCol cols="12" md="2">
                    <div class="text-body-2 text-medium-emphasis">
                      Fecha Efectiva
                    </div>
                    <div class="text-body-1">
                      {{ formatDate(resignation.effective_date) }}
                    </div>
                  </VCol>
                  <VCol cols="12" md="1">
                    <div class="text-body-2 text-medium-emphasis">Estado</div>
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
                  </VCol>
                  <VCol cols="12" md="2" class="d-flex align-center gap-2">
                    <VTooltip text="Descargar Carta de Renuncia" location="top">
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

                    <VTooltip text="Cambiar Estado del Empleado" location="top">
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
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
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
  </div>
</template>

<style scoped>
.v-card {
  border-radius: 12px;
}
</style>
