<script setup>
import ResignationFilters from "@/components/ResignationFilters.vue";
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import ResignationStatusDialog from "@/components/dialogs/ResignationStatusDialog.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { useDisplay } from "vuetify";
import { onMounted, ref, watch } from "vue";

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

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "EMPLEADO", key: "employee_name" },
  { title: "IDENTIFICACIÓN", key: "employee_identification" },
  { title: "TIPO", key: "resignation_type" },
  { title: "FECHA EFECTIVA", key: "effective_date", align: 'center' },
  { title: "ESTADO", key: "employee_status", align: 'center' },
  { title: "ACCIONES", key: "actions", sortable: false, align: 'end' },
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
          <p><strong>Identificación:</strong> ${resignation.employee_identification}</p>
          <p><strong>Tipo:</strong> ${
            resignation.resignation_type === "voluntary"
              ? "Renuncia Voluntaria"
              : "Despido Injustificado"
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
      width: "500px",
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
          <p><strong>Identificación:</strong> ${resignation.employee_identification}</p>
          <p><strong>Tipo:</strong> ${
            resignation.resignation_type === "voluntary"
              ? "Renuncia Voluntaria"
              : "Despido Injustificado"
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
      width: "600px",
    });

    if (confirmed.isConfirmed) {
      await axios.delete(`/rrhh/resignations/${resignation.id}`);
      toast.success("Renuncia eliminada exitosamente");
      fetchResignations();
    }
  } catch (error) {
    toast.error("No se pudo eliminar la renuncia");
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const day = date.getDate().toString().padStart(2, "0");
  const month = (date.getMonth() + 1).toString().padStart(2, "0");
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
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
  if (options.page !== page.value || options.itemsPerPage !== perPage.value) {
    page.value = options.page;
    perPage.value = options.itemsPerPage;
    fetchResignations();
  }
};

watch([search, filters], () => {
  page.value = 1;
  fetchResignations();
}, { deep: true });

onMounted(() => {
  fetchResignations();
});
</script>

<template>
  <div class="resignations-page mt-1">
    <div class="d-flex flex-column gap-1">
      <!-- Barra de Búsqueda y Filtros -->
      <ResignationFilters
        v-model:search="search"
        v-model:filters="filters"
        @clear="handleClearFilters"
        @add-resignation="handleGenerateResignation"
      />

      <!-- Listado: Tabla o Cards -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden">
        <!-- Vista de Escritorio: Tabla Premium Paginada por Servidor -->
        <VDataTableServer
          v-if="!mobile"
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="headers"
          :items="resignations"
          :items-length="totalItems"
          :loading="loading"
          class="premium-table text-no-wrap"
          density="compact"
          @update:options="handleOptionsUpdate"
        >
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">{{ item.id }}</span>
          </template>
          <!-- Empleado -->
          <template #item.employee_name="{ item }">
            <div class="d-flex align-center gap-3 py-2">
              <VAvatar color="primary" variant="tonal" size="32" class="rounded font-weight-black text-xs">
                {{ item.employee_name ? item.employee_name.charAt(0) : 'E' }}
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-black text-high-emphasis uppercase">{{ item.employee_name }}</span>
                <span class="text-super-xs text-disabled uppercase font-weight-bold">{{ item.employee_position || 'Cargo no especificado' }}</span>
              </div>
            </div>
          </template>

          <!-- Tipo -->
          <template #item.resignation_type="{ item }">
            <VChip
              :color="item.resignation_type === 'voluntary' ? 'success' : 'warning'"
              size="x-small"
              class="font-weight-black px-2 rounded"
              variant="flat"
            >
              {{ item.resignation_type === "voluntary" ? "JUSTIFICADA" : "INJUSTIFICADA" }}
            </VChip>
          </template>

          <!-- Fecha -->
          <template #item.effective_date="{ item }">
            <div class="text-sm font-weight-black text-high-emphasis tabular-nums">
              {{ formatDate(item.effective_date) }}
            </div>
          </template>

          <!-- Estado -->
          <template #item.employee_status="{ item }">
            <VChip
              :color="item.employee_status === 'Activo' ? 'success' : 'error'"
              size="x-small"
              class="font-weight-black px-2 rounded"
              variant="tonal"
            >
              {{ item.employee_status ? item.employee_status.toUpperCase() : 'INACTIVO' }}
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <VTooltip text="Descargar" location="top">
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon="tabler-file-download" variant="tonal" color="primary" size="32" class="rounded-circle shadow-sm" @click="downloadResignationPDF(item)" />
                </template>
              </VTooltip>

              <VTooltip text="Editar" location="top">
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon="tabler-edit" variant="tonal" color="info" size="32" class="rounded-circle shadow-sm" @click="editResignation(item)" />
                </template>
              </VTooltip>

              <VTooltip text="Estado" location="top">
                <template #activator="{ props }">
                  <VBtn 
                    v-bind="props" 
                    :icon="item.employee_status === 'Activo' ? 'tabler-user-minus' : 'tabler-user-plus'" 
                    variant="tonal" 
                    :color="item.employee_status === 'Activo' ? 'warning' : 'success'" 
                    size="32" 
                    class="rounded-circle shadow-sm"
                    @click="openToggleConfirmDialog(item.employee_id, item.employee_status === 'Activo', item.employee_name)" 
                  />
                </template>
              </VTooltip>

              <VTooltip text="Eliminar" location="top">
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon="tabler-trash" variant="tonal" color="error" size="32" class="rounded-circle shadow-sm" @click="deleteResignation(item)" />
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTableServer>

        <!-- Vista Móvil: Cards Premium -->
        <div v-else class="pa-4 bg-light">
          <VRow dense v-if="!loading">
            <VCol v-for="item in resignations" :key="item.id" cols="12">
              <VCard class="rounded-lg border shadow-sm mb-2 overflow-hidden">
                <div class="pa-3 border-b d-flex justify-space-between align-center">
                  <div class="d-flex align-center gap-3">
                    <VAvatar color="primary" variant="tonal" size="40" class="rounded font-weight-black">
                      {{ item.employee_name ? item.employee_name.charAt(0) : 'E' }}
                    </VAvatar>
                    <div class="d-flex flex-column">
                      <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                        <span class="text-primary text-xs">{{ item.id }}</span>
                        <span class="mx-1 text-disabled">|</span>
                        {{ item.employee_name }}
                      </h3>
                      <span class="text-xs text-disabled font-weight-bold">{{ item.employee_identification }}</span>
                    </div>
                  </div>
                  <VChip
                    :color="item.employee_status === 'Activo' ? 'success' : 'error'"
                    size="x-small"
                    class="font-weight-black px-2 rounded"
                    variant="tonal"
                  >
                    {{ item.employee_status ? item.employee_status.toUpperCase() : 'INACTIVO' }}
                  </VChip>
                </div>
                <VCardText class="pa-3">
                  <div class="d-flex justify-space-between mb-2">
                    <span class="text-xs font-weight-black text-disabled uppercase">Tipo de Egreso</span>
                    <VChip
                      :color="item.resignation_type === 'voluntary' ? 'success' : 'warning'"
                      size="x-small"
                      class="font-weight-black px-2 rounded"
                      variant="flat"
                    >
                      {{ item.resignation_type === "voluntary" ? "JUSTIFICADA" : "INJUSTIFICADA" }}
                    </VChip>
                  </div>
                  <div class="d-flex justify-space-between mb-3">
                    <span class="text-xs font-weight-black text-disabled uppercase">Fecha Efectiva</span>
                    <span class="text-xs font-weight-black text-high-emphasis tabular-nums">{{ formatDate(item.effective_date) }}</span>
                  </div>
                  
                  <VDivider class="border-opacity-10 mb-3" />
                  
                  <div class="d-flex gap-1 justify-end">
                     <VBtn icon="tabler-file-download" color="primary" variant="tonal" size="32" class="rounded-circle shadow-sm" @click="downloadResignationPDF(item)" />
                     <VBtn icon="tabler-edit" color="info" variant="tonal" size="32" class="rounded-circle shadow-sm" @click="editResignation(item)" />
                     <VBtn 
                       :icon="item.employee_status === 'Activo' ? 'tabler-user-minus' : 'tabler-user-plus'" 
                       variant="tonal" 
                       :color="item.employee_status === 'Activo' ? 'warning' : 'success'" 
                       size="32" 
                       class="rounded-circle shadow-sm"
                       @click="openToggleConfirmDialog(item.employee_id, item.employee_status === 'Activo', item.employee_name)" 
                     />
                     <VBtn icon="tabler-trash" color="error" variant="tonal" size="32" class="rounded-circle shadow-sm" @click="deleteResignation(item)" />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
          
          <div v-if="!loading && resignations.length === 0" class="text-center py-8">
            <VIcon icon="tabler-ghost" size="48" color="disabled" class="mb-2" />
            <div class="text-xs font-weight-bold text-disabled">No se encontraron resultados</div>
          </div>
        </div>
      </VCard>
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
.resignations-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

/* Estilos para Inputs Compactos Premium */
:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }

  .v-field__outline {
    --v-field-border-opacity: 0.15;
  }
}

/* Estilos para Tabla Premium */
:deep(.premium-table) {
  background: transparent !important;

  thead {
    th {
      background-color: white !important;
      color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
      font-size: 0.75rem !important;
      font-weight: 700 !important;
      letter-spacing: 0.05rem !important;
      text-transform: uppercase !important;
      border-bottom: 1px solid rgba(var(--v-border-color), 0.1) !important;
    }
  }

  tbody tr {
    transition: background-color 0.2s ease;

    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }

    td {
      padding-block: 12px !important;
      color: #334155 !important;
      border-block-end: 1px solid rgba(var(--v-border-color), 0.03) !important;
    }
  }
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.leading-tight {
  line-height: 1.25;
}

.leading-none {
  line-height: 1;
}

/* Sombras suaves */
.shadow-sm {
  box-shadow:
    0 2px 12px -4px rgba(var(--v-shadow-key-umbra-opacity), 0.08),
    0 4px 20px -2px rgba(var(--v-shadow-key-penumbra-opacity), 0.04) !important;
}

:deep(.v-data-table-footer) {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}
</style>
