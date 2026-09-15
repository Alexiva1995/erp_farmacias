<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import ResignationFilters from "@/components/ResignationFilters.vue";
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import ResignationStatusDialog from "@/components/dialogs/ResignationStatusDialog.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { useDisplay } from "vuetify";
import { onMounted, onUnmounted, ref, watch } from "vue";

const { mobile } = useDisplay();

// Estado reactivo
const loading = ref(false);
const actionLoading = ref(false);
const resignations = ref([]);
const search = ref("");

// Paginación Servidor
const page = ref(1);
const perPage = ref(10);
const totalItems = ref(0);

// Filtros avanzados
const filters = ref({
  resignation_type: null,
  date_from: null,
  date_to: null,
  status: null,
});

const showConfirmDialog = ref(false);
const employeeToToggle = ref(null);
const newStatus = ref(null);

// Variables para el modal de edición
const showResignationDialog = ref(false);
const selectedEmployeeForResignation = ref(null);
const isEditingResignation = ref(false);
const existingResignationData = ref(null);

const toTitleCase = (str) => {
  if (!str) return "—";
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};

const formatIdentification = (val) => {
  if (!val) return "—";
  const cleaned = String(val).replace(/\D/g, "");
  if (!cleaned) return String(val);
  const withDots = cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return `V-${withDots}`;
};

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  const day = date.getDate().toString().padStart(2, "0");
  const month = (date.getMonth() + 1).toString().padStart(2, "0");
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
};

const headers = [
  { title: "ID", key: "id", sortable: true, width: "70px" },
  { title: "Empleado", key: "employee_name", sortable: false },
  { title: "Identificación", key: "employee_identification", sortable: false, align: "center" },
  { title: "Tipo", key: "resignation_type", sortable: false, align: "center" },
  { title: "Fecha Efectiva", key: "effective_date", sortable: false, align: "center" },
  { title: "Estado", key: "employee_status", sortable: false, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "end" },
];

// Métodos
const fetchResignations = async () => {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      perPage: perPage.value,
    };

    if (search.value) params.search = search.value;
    if (filters.value.resignation_type) params.resignation_type = filters.value.resignation_type;
    if (filters.value.date_from) params.date_from = filters.value.date_from;
    if (filters.value.date_to) params.date_to = filters.value.date_to;

    const response = await axios.get("/rrhh/resignations", { params });
    const { data } = response;

    if (data.success) {
      resignations.value = data.data || [];
      if (data.pagination) {
        totalItems.value = data.pagination.total;
      }
    } else {
      toast.error("Error en la respuesta del servidor");
    }
  } catch (error) {
    console.error("Error al cargar renuncias:", error);
    toast.error(`Error al cargar las renuncias: ${error.response?.data?.message || error.message}`);
  } finally {
    loading.value = false;
  }
};

const handleClearFilters = () => {
  search.value = "";
  filters.value = {
    resignation_type: null,
    date_from: null,
    date_to: null,
    status: null,
  };
  page.value = 1;
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
  actionLoading.value = true;
  try {
    await axios.put("/rrhh/resignations/toggle-employee-status", {
      employee_id: employeeToToggle.value.id,
      is_active: newStatus.value,
    });

    toast.success(
      `Empleado ${newStatus.value ? "activado" : "desactivado"} exitosamente`
    );

    await fetchResignations();
    showConfirmDialog.value = false;
    employeeToToggle.value = null;
    newStatus.value = null;
  } catch (error) {
    toast.error("Error al cambiar el estado del empleado");
  } finally {
    actionLoading.value = false;
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
      `/rrhh/resignations/${resignation.id}/download-pdf`,
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
          <p><strong>Identificación:</strong> ${formatIdentification(resignation.employee_identification)}</p>
          <p><strong>Tipo:</strong> ${
            resignation.resignation_type === "voluntary"
              ? "Renuncia Justificada"
              : "Renuncia Injustificada"
          }</p>
          <p><strong>Fecha Efectiva:</strong> ${formatDate(resignation.effective_date)}</p>
        </div>
        <p class="mt-3">¿Desea editar esta carta de renuncia?</p>
      `,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#ff9800",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, editar",
      cancelButtonText: "Cancelar",
    });

    if (confirmed.isConfirmed) {
      const response = await axios.get(`/rrhh/resignations/${resignation.id}/edit`);

      if (response.data.success) {
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
          <p><strong>Identificación:</strong> ${formatIdentification(resignation.employee_identification)}</p>
          <p><strong>Tipo:</strong> ${
            resignation.resignation_type === "voluntary"
              ? "Renuncia Justificada"
              : "Renuncia Injustificada"
          }</p>
          <p><strong>Fecha Efectiva:</strong> ${formatDate(resignation.effective_date)}</p>
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
    });

    if (confirmed.isConfirmed) {
      await axios.delete(`/rrhh/resignations/${resignation.id}`);
      toast.success("Renuncia eliminada exitosamente");
      await fetchResignations();
    }
  } catch (error) {
    toast.error("No se pudo eliminar la renuncia");
  }
};

const handleResignationGenerated = () => {
  showResignationDialog.value = false;
  isEditingResignation.value = false;
  existingResignationData.value = null;
  selectedEmployeeForResignation.value = null;
  fetchResignations();
};

const handleGenerateResignation = () => {
  selectedEmployeeForResignation.value = null;
  existingResignationData.value = null;
  isEditingResignation.value = false;
  showResignationDialog.value = true;
};

const handleOptionsUpdate = (options) => {
  if (options.page) page.value = options.page;
  if (options.itemsPerPage) perPage.value = options.itemsPerPage;
};

watch([search, filters], () => {
  page.value = 1;
}, { deep: true });

let debounceTimer;
watch(
  [page, perPage, search, filters],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchResignations(), 300);
  },
  { deep: true }
);

onMounted(() => {
  fetchResignations();
});

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
});
</script>

<template>
  <div class="rrhh-resignations-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Barra de Búsqueda y Filtros -->
      <ResignationFilters
        v-model:search="search"
        v-model:filters="filters"
        @clear="handleClearFilters"
        @add-resignation="handleGenerateResignation"
      />

      <!-- Vista Desktop -->
      <div class="d-none d-md-block">
        <VCard border variant="flat">
          <VDataTableServer
            :headers="headers"
            :items-per-page="perPage"
            :items="resignations"
            :items-length="totalItems"
            :loading="loading"
            :page="page"
            density="comfortable"
            @update:options="handleOptionsUpdate"
          >
            <template #no-data>
              <AppEmptyState
                title="No hay cartas de renuncia"
                message="No se encontraron registros de renuncias."
                icon="tabler-file-minus"
              />
            </template>

            <template #item.id="{ item }">
              <span class="font-weight-bold text-primary">{{ item.id }}</span>
            </template>

            <!-- Empleado -->
            <template #item.employee_name="{ item }">
              <div class="d-flex align-center gap-3 py-1">
                <VAvatar color="primary" variant="tonal" size="34" class="rounded-lg">
                  <span class="text-xs font-weight-bold">{{ item.employee_name ? item.employee_name.charAt(0) : 'E' }}</span>
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-sm font-weight-medium text-high-emphasis leading-tight">
                    {{ toTitleCase(item.employee_name) }}
                  </span>
                  <span class="text-super-xs text-medium-emphasis font-weight-medium">
                    {{ item.employee_position || 'Cargo no especificado' }}
                  </span>
                </div>
              </div>
            </template>

            <!-- Identificación -->
            <template #item.employee_identification="{ item }">
              <span class="font-weight-semibold text-high-emphasis">{{ formatIdentification(item.employee_identification) }}</span>
            </template>

            <!-- Tipo -->
            <template #item.resignation_type="{ item }">
              <VChip
                :color="item.resignation_type === 'voluntary' ? 'success' : 'warning'"
                size="x-small"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ item.resignation_type === "voluntary" ? "Justificada" : "Injustificada" }}
              </VChip>
            </template>

            <!-- Fecha Efectiva -->
            <template #item.effective_date="{ item }">
              <span class="text-sm font-weight-medium text-high-emphasis tabular-nums">
                {{ formatDate(item.effective_date) }}
              </span>
            </template>

            <!-- Estado -->
            <template #item.employee_status="{ item }">
              <VChip
                :color="item.employee_status === 'Activo' ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ item.employee_status || 'Inactivo' }}
              </VChip>
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex justify-end gap-1">
                <IconBtn color="primary" size="small" @click="downloadResignationPDF(item)">
                  <VIcon icon="tabler-file-download" size="18" />
                  <VTooltip activator="parent">Descargar Carta PDF</VTooltip>
                </IconBtn>

                <IconBtn color="info" size="small" @click="editResignation(item)">
                  <VIcon icon="tabler-edit" size="18" />
                  <VTooltip activator="parent">Editar Carta</VTooltip>
                </IconBtn>

                <IconBtn
                  :color="item.employee_status === 'Activo' ? 'warning' : 'success'"
                  size="small"
                  @click="openToggleConfirmDialog(item.employee_id, item.employee_status === 'Activo', item.employee_name)"
                >
                  <VIcon :icon="item.employee_status === 'Activo' ? 'tabler-user-minus' : 'tabler-user-plus'" size="18" />
                  <VTooltip activator="parent">{{ item.employee_status === 'Activo' ? 'Desactivar Empleado' : 'Activar Empleado' }}</VTooltip>
                </IconBtn>

                <IconBtn color="error" size="small" @click="deleteResignation(item)">
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent">Eliminar Registro</VTooltip>
                </IconBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </div>

      <!-- Vista Móvil: Cards -->
      <div class="d-block d-md-none pa-2 bg-light">
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-2" />

        <AppEmptyState
          v-if="resignations.length === 0 && !loading"
          title="No hay cartas de renuncia"
          message="No se encontraron registros de renuncias."
          icon="tabler-file-minus"
        />

        <div class="d-flex flex-column gap-3">
          <VCard
            v-for="item in resignations"
            :key="item.id"
            variant="flat"
            border
            class="mb-1 overflow-hidden premium-card bg-white"
          >
            <div class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3 min-width-0">
                  <VAvatar color="primary" variant="tonal" size="42" class="rounded-lg">
                    <span class="text-sm font-weight-bold">{{ item.employee_name ? item.employee_name.charAt(0) : 'E' }}</span>
                  </VAvatar>
                  <div class="d-flex flex-column min-width-0">
                    <span class="text-primary font-weight-black text-xs uppercase mb-0.5">ID #{{ item.id }}</span>
                    <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                      {{ toTitleCase(item.employee_name) }}
                    </h3>
                    <div class="d-flex align-center gap-1 mt-0.5">
                      <span class="text-super-xs text-medium-emphasis font-weight-bold">{{ formatIdentification(item.employee_identification) }}</span>
                      <span class="text-xs text-disabled">•</span>
                      <span class="text-super-xs text-primary font-weight-bold truncate">{{ item.employee_position || 'Cargo no especificado' }}</span>
                    </div>
                  </div>
                </div>

                <VChip
                  :color="item.employee_status === 'Activo' ? 'success' : 'error'"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ item.employee_status || 'Inactivo' }}
                </VChip>
              </div>

              <VDivider class="my-3 border-opacity-10" />

              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VChip
                    :color="item.resignation_type === 'voluntary' ? 'success' : 'warning'"
                    size="x-small"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    {{ item.resignation_type === "voluntary" ? "Justificada" : "Injustificada" }}
                  </VChip>
                  <span class="text-super-xs text-medium-emphasis font-weight-medium">
                    {{ formatDate(item.effective_date) }}
                  </span>
                </div>

                <div class="d-flex gap-1">
                  <IconBtn color="primary" size="x-small" @click="downloadResignationPDF(item)">
                    <VIcon icon="tabler-file-download" size="16" />
                    <VTooltip activator="parent">Descargar Carta PDF</VTooltip>
                  </IconBtn>

                  <IconBtn color="info" size="x-small" @click="editResignation(item)">
                    <VIcon icon="tabler-edit" size="16" />
                    <VTooltip activator="parent">Editar Carta</VTooltip>
                  </IconBtn>

                  <IconBtn
                    :color="item.employee_status === 'Activo' ? 'warning' : 'success'"
                    size="x-small"
                    @click="openToggleConfirmDialog(item.employee_id, item.employee_status === 'Activo', item.employee_name)"
                  >
                    <VIcon :icon="item.employee_status === 'Activo' ? 'tabler-user-minus' : 'tabler-user-plus'" size="16" />
                    <VTooltip activator="parent">{{ item.employee_status === 'Activo' ? 'Desactivar Empleado' : 'Activar Empleado' }}</VTooltip>
                  </IconBtn>

                  <IconBtn color="error" size="x-small" @click="deleteResignation(item)">
                    <VIcon icon="tabler-trash" size="16" />
                    <VTooltip activator="parent">Eliminar Registro</VTooltip>
                  </IconBtn>
                </div>
              </div>
            </div>
          </VCard>
        </div>

        <!-- Mobile Pagination -->
        <div class="d-flex justify-center mt-4 pb-2">
          <AppMobilePagination
            :page="page"
            :items-per-page="perPage"
            :total-items="totalItems"
            :loading="loading"
            @change="handleOptionsUpdate"
          />
        </div>
      </div>
    </div>

    <!-- Modal Desacoplado de Estado del Empleado -->
    <ResignationStatusDialog
      v-model="showConfirmDialog"
      :employee="employeeToToggle"
      :new-status="newStatus"
      :loading="actionLoading"
      @confirm="confirmToggleStatus"
      @cancel="cancelToggleStatus"
    />

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
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
