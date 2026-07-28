<script setup>
import RetentionFilters from "@/components/RetentionFilters.vue";
import RetentionTable from "@/components/RetentionTable.vue";
import axios from "@/plugins/axios";
import { toast, Swal } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();
const loading = ref(false);
const downloadingPdf = ref({}); // Estado de descarga independiente por comprobante
const invoices = ref([]);
const suppliers = ref([]);
const totalRecords = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const selected = ref([]);
const currentTab = ref("pending");

// Filtros de búsqueda y fechas
const search = ref("");
const supplierId = ref(null);
const startDate = ref("");
const endDate = ref("");
const sortBy = ref("created_invoice_date");
const orderBy = ref("desc");

// Establece el rango de fechas al inicio del año actual (dinámico)
const setYearPreset = () => {
  const now = new Date();
  const currentYear = now.getFullYear();
  startDate.value = `${currentYear}-01-01`;
  endDate.value = `${currentYear}-12-31`;
};

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/suppliers", { 
      params: { itemsPerPage: -1, select_minimal: true } 
    });
    suppliers.value = response.data?.data || response.data || [];
  } catch (error) {
    console.error("Error al cargar lista de proveedores:", error);
  }
};

const fetchRetentions = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/retentions", {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage.value,
        search: search.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        supplier_id: supplierId.value || undefined,
        is_generated: currentTab.value === "generated",
        sortBy: sortBy.value,
        orderBy: orderBy.value,
      },
    });
    invoices.value = response.data?.data || [];
    totalRecords.value = response.data?.pagination?.total || 0;
  } catch (error) {
    console.error("Error al cargar retenciones fiscales:", error);
    toast.error("Error al sincronizar datos de retenciones.");
  } finally {
    loading.value = false;
  }
};

const handleTableUpdate = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  }
  fetchRetentions();
};

const handleBulkGenerate = async () => {
  if (selected.value.length === 0) return;

  try {
    loading.value = true;
    const idsToProcess = [...selected.value];
    const response = await axios.post("/retentions/bulk-generate", {
      ids: idsToProcess,
    });
    
    toast.success(response.data?.message || "Retención generada exitosamente.");
    
    if (response.data?.retention_id) {
      await downloadPdf(response.data.retention_id, true);
    }
    
    selected.value = [];
    fetchRetentions();
  } catch (error) {
    console.error("Error al generar retención masiva:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al procesar las retenciones.");
  } finally {
    loading.value = false;
  }
};

const handleBatchGenerateAll = async () => {
  const result = await Swal.fire({
    title: '¿Generar todas las retenciones?',
    text: `Se procesarán las facturas pendientes entre ${startDate.value} y ${endDate.value}. Esta acción creará un comprobante por cada proveedor.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#fb8c00',
    cancelButtonColor: '#9e9e9e',
    confirmButtonText: 'SÍ, GENERAR TODO',
    cancelButtonText: 'CANCELAR'
  });

  if (!result.isConfirmed) return;

  try {
    loading.value = true;
    const response = await axios.post("/retentions/batch-generate-all", {
      start_date: startDate.value,
      end_date: endDate.value,
    });
    
    toast.success(response.data?.message || "Retenciones procesadas correctamente.");
    fetchRetentions();
  } catch (error) {
    console.error("Error en generación masiva:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al procesar la solicitud.");
  } finally {
    loading.value = false;
  }
};

const downloadPdf = async (id, isRetention = true) => {
  downloadingPdf.value[id] = true;
  try {
    const params = isRetention ? { retention_id: id } : { ids: id };
    const response = await axios.get("/retentions/download", {
      params,
      responseType: "blob",
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `comprobante_retencion_${id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Comprobante PDF descargado.");
  } catch (error) {
    console.error("Error al descargar PDF:", error);
    toast.error("Error al obtener el archivo PDF.");
  } finally {
    downloadingPdf.value[id] = false;
  }
};

const clearFilters = () => {
  search.value = "";
  supplierId.value = null;
  setYearPreset();
  page.value = 1;
  fetchRetentions();
};

const handleDeleteRetention = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar comprobante?',
    text: 'Esta acción desvinculará las facturas y volverán a estado pendiente.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e53935',
    cancelButtonColor: '#9e9e9e',
    confirmButtonText: 'SÍ, ELIMINAR',
    cancelButtonText: 'CANCELAR'
  });

  if (!result.isConfirmed) return;

  try {
    loading.value = true;
    const response = await axios.delete(`/retentions/${id}`);
    toast.success(response.data?.message || "Retención eliminada correctamente.");
    fetchRetentions();
  } catch (error) {
    console.error("Error al eliminar retención:", error);
    toast.error(error.response?.data?.message || "Error al eliminar la retención.");
  } finally {
    loading.value = false;
  }
};

const handleEditRetention = async (retention) => {
  const { value: newNumber } = await Swal.fire({
    title: 'Editar Número de Comprobante',
    input: 'text',
    inputLabel: 'Número de Comprobante',
    inputValue: retention.number,
    showCancelButton: true,
    confirmButtonColor: '#fb8c00',
    cancelButtonColor: '#9e9e9e',
    confirmButtonText: 'GUARDAR',
    cancelButtonText: 'CANCELAR',
    inputValidator: (value) => {
      if (!value) {
        return '¡Debes ingresar un número válido!';
      }
    }
  });

  if (!newNumber) return;

  try {
    loading.value = true;
    const response = await axios.put(`/retentions/${retention.id}`, { number: newNumber });
    toast.success(response.data?.message || "Número actualizado correctamente.");
    fetchRetentions();
  } catch (error) {
    console.error("Error al editar comprobante:", error);
    toast.error(error.response?.data?.message || "Error al actualizar el número.");
  } finally {
    loading.value = false;
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
  page.value = 1;
  fetchRetentions();
};

watch(currentTab, (newTab) => {
  page.value = 1;
  selected.value = [];
  
  if (newTab === "generated") {
    sortBy.value = "date";
    orderBy.value = "desc";
  } else {
    sortBy.value = "created_invoice_date";
    orderBy.value = "desc";
  }
  
  fetchRetentions();
});

let debounceTimer = null;
watch([search, supplierId, startDate, endDate], () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    page.value = 1;
    fetchRetentions();
  }, 400);
});

onMounted(() => {
  setYearPreset();
  fetchSuppliers();
  fetchRetentions();
});
</script>

<template>
  <div class="retenciones-index-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros Premium -->
      <RetentionFilters
        v-model:search="search"
        v-model:supplier-id="supplierId"
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :suppliers="suppliers"
        :loading="loading"
        :selected-count="selected.length"
        :current-tab="currentTab"
        @clear="clearFilters"
        @bulk-generate="handleBulkGenerate"
        @batch-generate-all="handleBatchGenerateAll"
        @sort="handleSort"
        class="mb-0"
      />

      <!-- Gestión de Retenciones con Pestañas -->
      <VCard class="ma-0 rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VCardTitle class="pa-4 px-6 d-flex align-center flex-wrap gap-2">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="32" class="me-3 rounded-lg">
              <VIcon icon="tabler-file-analytics" size="18" />
            </VAvatar>
            <span class="text-sm font-weight-black uppercase">Gestión de Retenciones IVA</span>
          </div>
          <VSpacer />
          <VBtn
            v-if="selected.length > 0 && currentTab === 'pending'"
            color="success"
            variant="flat"
            class="rounded-lg text-xs font-weight-black px-4 shadow-sm"
            :loading="loading"
            @click="handleBulkGenerate"
          >
            <VIcon start icon="tabler-check" size="18" />
            GENERAR {{ selected.length }} RETENCIONES
          </VBtn>
        </VCardTitle>

        <VTabs v-model="currentTab" color="primary" grow class="premium-tabs bg-surface-variant-opacity-2">
          <VTab value="pending" class="text-xs font-weight-black py-4">
            <VIcon start icon="tabler-clock-pause" size="18" />
            FACTURAS PENDIENTES
            <VChip v-if="currentTab === 'pending'" size="x-small" color="primary" class="ms-2 font-weight-black">
              {{ totalRecords }}
            </VChip>
          </VTab>
          <VTab value="generated" class="text-xs font-weight-black py-4">
            <VIcon start icon="tabler-checkbox" size="18" />
            COMPROBANTES GENERADOS
            <VChip v-if="currentTab === 'generated'" size="x-small" color="success" class="ms-2 font-weight-black">
              {{ totalRecords }}
            </VChip>
          </VTab>
        </VTabs>

        <VDivider class="opacity-10" />

        <VCardText class="pa-0">
          <RetentionTable
            v-model:selected="selected"
            :invoices="invoices"
            :loading="loading"
            :downloading-pdf="downloadingPdf"
            :total-records="totalRecords"
            :page="page"
            :items-per-page="itemsPerPage"
            :current-tab="currentTab"
            @update:options="handleTableUpdate"
            @download-pdf="downloadPdf"
            @delete-retention="handleDeleteRetention"
            @edit-retention="handleEditRetention"
          />
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.premium-tabs :deep(.v-tab) {
  letter-spacing: 0.05em !important;
  opacity: 0.6;
}

.premium-tabs :deep(.v-tab--selected) {
  opacity: 1 !important;
  background-color: white !important;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}
</style>
