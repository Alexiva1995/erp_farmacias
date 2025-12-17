<script setup>
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "axios";
import Swal from "sweetalert2";
import { onMounted, ref } from "vue";

// Estado reactivo
const loading = ref(false);
const resignations = ref([]);
const search = ref("");
const showConfirmDialog = ref(false);
const employeeToToggle = ref(null);
const newStatus = ref(null);

// Variables para el modal de edición
const showResignationDialog = ref(false);
const selectedEmployeeForResignation = ref(null);
const isEditingResignation = ref(false);
const existingResignationData = ref(null);

const headers = [
  { title: "Empleado", key: "employee_name" },
  { title: "Identificación", key: "employee_identification" },
  { title: "Tipo", key: "resignation_type" },
  { title: "Fecha Efectiva", key: "effective_date" },
  { title: "Estado", key: "employee_status" },
  { title: "Acciones", key: "actions", sortable: false },
];

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
};

// Lifecycle
onMounted(() => {
  fetchResignations();
});
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="12">
            <AppTextField
              v-model="search"
              placeholder="Buscar renuncia..."
              prepend-inner-icon="tabler-search"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <VDataTable
          :headers="headers"
          :items="resignations"
          :search="search"
          :loading="loading"
          items-per-page="10"
          class="text-no-wrap"
        >
          <!-- Empleado -->
          <template #item.employee_name="{ item }">
            <span class="font-weight-medium">{{ item.employee_name }}</span>
          </template>

          <!-- Tipo -->
          <template #item.resignation_type="{ item }">
            <VChip
              :color="
                item.resignation_type === 'voluntary' ? 'success' : 'warning'
              "
              size="small"
              label
            >
              {{
                item.resignation_type === "voluntary"
                  ? "Justificada"
                  : "Injustificada"
              }}
            </VChip>
          </template>

          <!-- Fecha -->
          <template #item.effective_date="{ item }">
            {{ formatDate(item.effective_date) }}
          </template>

          <!-- Estado -->
          <template #item.employee_status="{ item }">
            <VChip
              :color="item.employee_status === 'Activo' ? 'success' : 'error'"
              size="small"
              label
            >
              {{ item.employee_status }}
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <VTooltip text="Descargar Carta" location="top">
              <template #activator="{ props }">
                <IconBtn v-bind="props" @click="downloadResignationPDF(item)">
                  <VIcon icon="tabler-download" />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip text="Editar Renuncia" location="top">
              <template #activator="{ props }">
                <IconBtn v-bind="props" @click="editResignation(item)">
                  <VIcon icon="tabler-edit" />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip text="Cambiar Estado" location="top">
              <template #activator="{ props }">
                <IconBtn
                  v-bind="props"
                  @click="
                    openToggleConfirmDialog(
                      item.employee_id,
                      item.employee_status === 'Activo',
                      item.employee_name
                    )
                  "
                >
                  <VIcon
                    :icon="
                      item.employee_status === 'Activo'
                        ? 'tabler-user-minus'
                        : 'tabler-user-plus'
                    "
                  />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip text="Eliminar Renuncia" location="top">
              <template #activator="{ props }">
                <IconBtn v-bind="props" @click="deleteResignation(item)">
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </template>
            </VTooltip>
          </template>
        </VDataTable>
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
