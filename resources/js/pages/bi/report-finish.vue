<script setup>
import { ref, reactive, watch, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import Swal from 'sweetalert2';
import { formatCurrency } from '@/utils/currencyFormatter';

import FinishModule1General from '@/components/bi/FinishModule1General.vue';
import FinishModule2CzRecovery from '@/components/bi/FinishModule2CzRecovery.vue';
import FinishModule3AbRestock from '@/components/bi/FinishModule3AbRestock.vue';
import FinishModule4MarginAlerts from '@/components/bi/FinishModule4MarginAlerts.vue';

const router = useRouter();

// --- Estados de Listado de Snapshots ---
const loading = ref(false);
const snapshots = ref([]);
const totalSnapshots = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'cutoff_date', order: 'desc' }]);
const search = ref('');

// --- Diálogo para Nueva Foto Finish ---
const isCreateDialogOpen = ref(false);
const creatingSnapshot = ref(false);
const createForm = reactive({
  cutoff_date: new Date().toISOString().split('T')[0],
  period_days: 30,
  name: '',
});
const createErrors = reactive({
  cutoff_date: '',
  period_days: '',
  name: '',
});

// --- Estados del Visor de Detalle ---
const activeTab = ref('list'); // 'list' | 'detail'
const activeModuleTab = ref('module1'); // 'module1' | 'module2' | 'module3' | 'module4'
const selectedSnapshot = ref(null);
const detailLoading = ref(false);
const auditLoading = ref(false);
const exportingExcel = ref(false);

// Datos de los 4 Módulos
const auditData = ref({
  module_1_summary: {
    total_inventory_value: 0,
    total_inventory_units: 0,
    total_sales_value: 0,
    frozen_capital_cz: 0,
    overstock_capital_ab: 0,
    stockout_skus_count: 0,
    stockout_ab_count: 0,
    total_products_count: 0,
  },
  module_2_cz_recovery: {
    total_initial_cz_capital: 0,
    total_current_cz_capital: 0,
    total_cash_released: 0,
    total_units_released: 0,
    recovery_percentage: 0,
    items: [],
  },
  module_3_ab_restock: {
    total_critical_items: 0,
    restocked_count: 0,
    still_stockout_count: 0,
    effectiveness_rate: 0,
    items: [],
  },
  module_4_margin_alerts: {
    negative_margin_count: 0,
    low_margin_count: 0,
    healthy_margin_count: 0,
    total_evaluated: 0,
    alerts: [],
  },
});

// Detalle paginado de la tabla de ítems (Pestaña 1)
const detailItems = ref([]);
const totalDetailItems = ref(0);
const detailPage = ref(1);
const detailItemsPerPage = ref(15);
const detailSortBy = ref([{ key: 'inventory_value_usd', order: 'desc' }]);
const detailSearch = ref('');
const selectedSalesClass = ref(null);
const onlyOverstock = ref(false);

// Filtros internos para Módulos 2, 3 y 4
const czSearch = ref('');
const restockSearch = ref('');
const marginSearch = ref('');

const snapshotHeaders = [
  { title: 'ID', key: 'id', width: '75px', sortable: true },
  { title: 'NOMBRE / IDENTIFICADOR', key: 'name', sortable: true },
  { title: 'FECHA DE CORTE', key: 'cutoff_date', align: 'center', sortable: true },
  { title: 'SKUS', key: 'total_products', align: 'end', sortable: true },
  { title: 'STOCK (UND)', key: 'total_inventory_units', align: 'end', sortable: true },
  { title: 'VALOR INV. ($)', key: 'total_inventory_value', align: 'end', sortable: true },
  { title: 'VENTAS 30D ($)', key: 'total_sales_value', align: 'end', sortable: true },
  { title: 'SOBRESTOCK (>90D)', key: 'overstock_products_count', align: 'center', sortable: true },
  { title: 'TIPO', key: 'is_automatic', align: 'center', sortable: true },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '130px' },
];

const itemHeaders = [
  { title: 'ID', key: 'id_producto', width: '70px', sortable: true },
  { title: 'PRODUCTO / LABORATORIO', key: 'nombre_producto', sortable: true },
  { title: 'CLASIF.', key: 'clasificacion_ventas', align: 'center', sortable: true },
  { title: 'VENTAS (30D)', key: 'ventas_unidades_30d', align: 'end', sortable: true },
  { title: 'VENTAS ($)', key: 'ventas_totales_usd_30d', align: 'end', sortable: true },
  { title: 'STOCK', key: 'stock_actual_unidades', align: 'end', sortable: true },
  { title: 'VALOR INV. ($)', key: 'valor_inventario_usd', align: 'end', sortable: true },
  { title: 'MARGEN (%)', key: 'margen_porcentaje', align: 'end', sortable: true },
  { title: 'COBERTURA', key: 'cobertura_dias', align: 'end', sortable: true },
  { title: 'GMROI (%)', key: 'gmroi_anual_porcentaje', align: 'center', sortable: true },
  { title: 'VENCE', key: 'dias_para_vencer', align: 'center', sortable: true },
  { title: 'ESTADO', key: 'es_sobrestock', align: 'center', sortable: false },
];

// --- Peticiones Listado Snapshots ---
const fetchSnapshots = async () => {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key || 'cutoff_date',
      orderBy: sortBy.value[0]?.order || 'desc',
      search: search.value || null,
    };

    const response = await axios.get('/bi/snapshots', { params });
    snapshots.value = response.data.data;
    totalSnapshots.value = response.data.meta.total;
  } catch (err) {
    console.error('Error fetching snapshots:', err);
    toast.error('No se pudieron cargar las Fotos Finish.');
  } finally {
    loading.value = false;
  }
};

// --- Peticiones Detalle Snapshot & Auditoría de 4 Módulos ---
const fetchSnapshotDetails = async (snapshotId) => {
  detailLoading.value = true;
  try {
    const params = {
      page: detailPage.value,
      itemsPerPage: detailItemsPerPage.value,
      sortBy: detailSortBy.value[0]?.key || 'inventory_value_usd',
      orderBy: detailSortBy.value[0]?.order || 'desc',
      search: detailSearch.value || null,
      sales_class: selectedSalesClass.value || null,
      is_overstock: onlyOverstock.value ? true : null,
    };

    const response = await axios.get(/bi/snapshots/, { params });
    selectedSnapshot.value = response.data.snapshot;
    detailItems.value = response.data.items;
    totalDetailItems.value = response.data.meta.total;
  } catch (err) {
    console.error('Error loading snapshot details:', err);
    toast.error('Error al cargar los detalles de la Foto Finish.');
  } finally {
    detailLoading.value = false;
  }
};

const fetchAuditModules = async (snapshotId) => {
  auditLoading.value = true;
  try {
    const response = await axios.get(/bi/snapshots//audit);
    auditData.value = response.data;
  } catch (err) {
    console.error('Error loading audit modules:', err);
    toast.error('Error al cargar los 4 módulos de control.');
  } finally {
    auditLoading.value = false;
  }
};

const handleOpenDetail = (snapshot) => {
  selectedSnapshot.value = snapshot;
  detailPage.value = 1;
  detailSearch.value = '';
  selectedSalesClass.value = null;
  onlyOverstock.value = false;
  activeTab.value = 'detail';
  activeModuleTab.value = 'module1';
  fetchSnapshotDetails(snapshot.id);
  fetchAuditModules(snapshot.id);
};

const handleBackToList = () => {
  activeTab.value = 'list';
  fetchSnapshots();
};

// --- Crear Nueva Foto Finish ---
const handleOpenCreateDialog = () => {
  const today = new Date().toISOString().split('T')[0];
  createForm.cutoff_date = today;
  createForm.period_days = 30;
  createForm.name = Foto Finish ;
  createErrors.cutoff_date = '';
  createErrors.period_days = '';
  createErrors.name = '';
  isCreateDialogOpen.value = true;
};

const handleCreateSnapshot = async () => {
  createErrors.cutoff_date = '';
  createErrors.period_days = '';
  createErrors.name = '';
  creatingSnapshot.value = true;

  try {
    const payload = {
      cutoff_date: createForm.cutoff_date,
      period_days: Number(createForm.period_days),
      name: createForm.name || null,
    };

    const response = await axios.post('/bi/snapshots', payload);
    if (response.status === 201 || response.status === 200) {
      toast.success('Foto Finish generada y guardada con éxito.');
      isCreateDialogOpen.value = false;
      fetchSnapshots();
    }
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data.errors || {};
      Object.keys(errors).forEach((key) => {
        if (createErrors.hasOwnProperty(key)) {
          createErrors[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
        }
      });
      toast.error('Por favor revise los campos del formulario.');
    } else {
      toast.error(err.response?.data?.message || 'Error al generar la Foto Finish.');
    }
  } finally {
    creatingSnapshot.value = false;
  }
};

// --- Eliminar Snapshot ---
const handleDeleteSnapshot = async (snapshot) => {
  const result = await Swal.fire({
    title: '¿Eliminar Foto Finish?',
    text: Esta acción eliminará el registro histórico "" y todos sus datos calculados.,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    customClass: {
      confirmButton: 'v-btn v-btn--variant-flat bg-error text-white h-auto py-2 px-6 rounded-lg font-weight-black uppercase ms-3',
      cancelButton: 'v-btn v-btn--variant-tonal text-secondary h-auto py-2 px-6 rounded-lg font-weight-black uppercase',
    },
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(/bi/snapshots/);
      toast.success('Foto Finish eliminada correctamente.');
      if (activeTab.value === 'detail') {
        handleBackToList();
      } else {
        fetchSnapshots();
      }
    } catch (err) {
      toast.error(err.response?.data?.message || 'Error al eliminar la Foto Finish.');
    }
  }
};

// --- Exportar Snapshot a Excel ---
const handleExportSnapshot = async (snapshotId) => {
  exportingExcel.value = true;
  try {
    const response = await axios.get(/bi/snapshots//export, {
      responseType: 'blob',
    });

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', oto_finish__.xlsx);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success('Archivo Excel descargado exitosamente.');
  } catch (err) {
    console.error('Error exporting snapshot:', err);
    toast.error('Error al exportar la Foto Finish a Excel.');
  } finally {
    exportingExcel.value = false;
  }
};

// Filtros reactivos en memoria para listas de módulos 2, 3 y 4
const filteredCzItems = computed(() => {
  const items = auditData.value.module_2_cz_recovery?.items || [];
  if (!czSearch.value) return items;
  const term = czSearch.value.toLowerCase();
  return items.filter(i => 
    i.product_name.toLowerCase().includes(term) ||
    i.laboratory_name.toLowerCase().includes(term) ||
    String(i.product_id).includes(term)
  );
});

const filteredRestockItems = computed(() => {
  const items = auditData.value.module_3_ab_restock?.items || [];
  if (!restockSearch.value) return items;
  const term = restockSearch.value.toLowerCase();
  return items.filter(i => 
    i.product_name.toLowerCase().includes(term) ||
    i.laboratory_name.toLowerCase().includes(term) ||
    String(i.product_id).includes(term)
  );
});

const filteredMarginAlerts = computed(() => {
  const items = auditData.value.module_4_margin_alerts?.alerts || [];
  if (!marginSearch.value) return items;
  const term = marginSearch.value.toLowerCase();
  return items.filter(i => 
    i.product_name.toLowerCase().includes(term) ||
    i.laboratory_name.toLowerCase().includes(term) ||
    String(i.product_id).includes(term)
  );
});

// Watchers
let searchDebounce;
watch(search, () => {
  page.value = 1;
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    fetchSnapshots();
  }, 350);
});

watch([page, itemsPerPage, sortBy], () => {
  if (activeTab.value === 'list') {
    fetchSnapshots();
  }
}, { deep: true });

let detailSearchDebounce;
watch(detailSearch, () => {
  detailPage.value = 1;
  clearTimeout(detailSearchDebounce);
  detailSearchDebounce = setTimeout(() => {
    if (selectedSnapshot.value) {
      fetchSnapshotDetails(selectedSnapshot.value.id);
    }
  }, 350);
});

watch([detailPage, detailItemsPerPage, detailSortBy, selectedSalesClass, onlyOverstock], () => {
  if (activeTab.value === 'detail' && selectedSnapshot.value) {
    fetchSnapshotDetails(selectedSnapshot.value.id);
  }
}, { deep: true });

onMounted(() => {
  fetchSnapshots();
});
</script>

<template>
  <div class="report-finish-view pb-12">
    <!-- Breadcrumb & Header Principal -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
      <div>
        <div class="d-flex align-center gap-2 mb-1">
          <VBtn
            v-if="activeTab === 'detail'"
            icon
            variant="tonal"
            size="small"
            color="secondary"
            class="rounded-circle me-1"
            @click="handleBackToList"
          >
            <VIcon icon="tabler-arrow-left" size="18" />
            <VTooltip activator="parent" location="top">Volver al listado de fotos</VTooltip>
          </VBtn>
          <h1 class="text-h5 font-weight-black d-flex align-center mb-0">
            <VIcon icon="tabler-camera" class="me-2 text-primary" size="26" />
            {{ activeTab === 'detail' ? `Foto Finish: ${selectedSnapshot?.name || ''}` : 'Foto Finish de Inventario & Control Mensual' }}
          </h1>
        </div>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ activeTab === 'detail'
            ? `Corte: ${selectedSnapshot?.cutoff_date || ''} (${selectedSnapshot?.period_days || 30} días) — 4 Módulos de Control para Reemplazo Total de Excel.`
            : 'Historial de cierres mensuales congelados con los 4 módulos de auditoría: KPIs de Cierre, Capital Recuperado CZ, Compras A/B y Márgenes.' }}
        </p>
      </div>

      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="activeTab === 'detail'"
          variant="outlined"
          color="success"
          :loading="exportingExcel"
          :disabled="detailLoading || exportingExcel"
          @click="handleExportSnapshot(selectedSnapshot.id)"
        >
          <VIcon icon="tabler-file-spreadsheet" size="18" class="me-1" />
          Exportar Excel
        </VBtn>

        <VBtn
          variant="flat"
          color="primary"
          @click="handleOpenCreateDialog"
        >
          <VIcon icon="tabler-camera-plus" size="18" class="me-1" />
          Tomar Foto Finish
        </VBtn>
      </div>
    </div>

    <!-- VISTA 1: LISTADO DE FOTOS FINISH HISTÓRICAS -->
    <template v-if="activeTab === 'list'">
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
        <VCardText class="pa-4">
          <VRow align="center" dense class="mb-2">
            <VCol cols="12" md="4">
              <AppTextField
                v-model="search"
                placeholder="Buscar por nombre o fecha (YYYY-MM-DD)..."
                prepend-inner-icon="tabler-search"
                clearable
                density="compact"
                hide-details
                variant="outlined"
                :disabled="loading"
              />
            </VCol>
            <VCol cols="12" md="auto" class="ms-auto d-flex align-center gap-2">
              <VBtn
                icon
                variant="text"
                color="secondary"
                size="36"
                class="rounded-circle"
                :disabled="loading"
                @click="fetchSnapshots"
              >
                <VIcon icon="tabler-refresh" size="18" />
                <VTooltip activator="parent" location="top">Refrescar listado</VTooltip>
              </VBtn>
            </VCol>
          </VRow>

          <VDataTableServer
            v-model:items-per-page="itemsPerPage"
            v-model:page="page"
            v-model:sort-by="sortBy"
            :items-length="totalSnapshots"
            :headers="snapshotHeaders"
            :items="snapshots"
            :loading="loading"
            class="premium-table"
            hover
            density="comfortable"
          >
            <!-- Empty state -->
            <template #no-data>
              <div class="py-10 text-center text-medium-emphasis">
                <VIcon icon="tabler-camera-off" size="52" class="mb-3 opacity-40" />
                <p class="text-body-1 font-weight-medium mb-1">Aún no se han generado Fotos Finish</p>
                <p class="text-caption text-disabled mb-4">
                  Las fotos se generan automáticamente el día 1 de cada mes a las 02:00 AM o manualmente con cualquier fecha de corte.
                </p>
                <VBtn size="small" color="primary" variant="flat" @click="handleOpenCreateDialog">
                  <VIcon icon="tabler-camera-plus" size="16" class="me-1" />
                  Tomar Primera Foto Finish
                </VBtn>
              </div>
            </template>

            <!-- ID -->
            <template #item.id="{ item }">
              <span class="font-weight-black text-primary">#{{ item.id }}</span>
            </template>

            <!-- Nombre -->
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-2">
                <span class="font-weight-bold text-high-emphasis text-base">{{ item.name }}</span>
                <span class="text-caption text-medium-emphasis">
                  Creado: {{ item.created_at ? new Date(item.created_at).toLocaleDateString() : 'N/D' }}
                  <span v-if="item.creator_name"> por {{ item.creator_name }}</span>
                </span>
              </div>
            </template>

            <!-- Fecha de Corte -->
            <template #item.cutoff_date="{ item }">
              <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                <VIcon icon="tabler-calendar" size="14" class="me-1" />
                {{ item.cutoff_date }}
              </VChip>
            </template>

            <!-- SKUs -->
            <template #item.total_products="{ item }">
              <span class="font-weight-black">{{ item.total_products }}</span>
            </template>

            <!-- Stock Total -->
            <template #item.total_inventory_units="{ item }">
              <span class="font-weight-bold">{{ Number(item.total_inventory_units).toLocaleString() }}</span>
            </template>

            <!-- Valor Inventario -->
            <template #item.total_inventory_value="{ item }">
              <span class="font-weight-black text-primary text-base">{{ formatCurrency(item.total_inventory_value) }}</span>
            </template>

            <!-- Ventas 30d -->
            <template #item.total_sales_value="{ item }">
              <span class="font-weight-bold text-success">{{ formatCurrency(item.total_sales_value) }}</span>
            </template>

            <!-- Sobrestock -->
            <template #item.overstock_products_count="{ item }">
              <VTooltip location="top">
                <template #activator="{ props: tipProps }">
                  <VChip
                    v-bind="tipProps"
                    :color="item.overstock_products_count > 0 ? 'error' : 'success'"
                    size="small"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    <VIcon :icon="item.overstock_products_count > 0 ? 'tabler-alert-triangle' : 'tabler-circle-check'" size="14" class="me-1" />
                    {{ item.overstock_products_count }} SKUs
                  </VChip>
                </template>
                <span>Capital en Sobrestock: {{ formatCurrency(item.overstock_inventory_value) }}</span>
              </VTooltip>
            </template>

            <!-- Tipo -->
            <template #item.is_automatic="{ item }">
              <VChip size="x-small" :color="item.is_automatic ? 'info' : 'default'" variant="flat">
                {{ item.is_automatic ? 'Automático' : 'Manual' }}
              </VChip>
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex align-center justify-center gap-1">
                <VBtn
                  icon
                  variant="text"
                  size="30"
                  color="primary"
                  @click="handleOpenDetail(item)"
                >
                  <VIcon icon="tabler-eye" size="18" />
                  <VTooltip activator="parent" location="top">Ver 4 Módulos de Control</VTooltip>
                </VBtn>

                <VBtn
                  icon
                  variant="text"
                  size="30"
                  color="success"
                  @click="handleExportSnapshot(item.id)"
                >
                  <VIcon icon="tabler-download" size="18" />
                  <VTooltip activator="parent" location="top">Descargar Excel</VTooltip>
                </VBtn>

                <VBtn
                  icon
                  variant="text"
                  size="30"
                  color="error"
                  @click="handleDeleteSnapshot(item)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent" location="top">Eliminar Foto Finish</VTooltip>
                </VBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCardText>
      </VCard>
    </template>

    <!-- VISTA 2: DETALLE CON LOS 4 MÓDULOS DE CONTROL ESTRATÉGICO -->
    <template v-else-if="activeTab === 'detail' && selectedSnapshot">
      <!-- PESTAÑAS DE NAVEGACIÓN ENTRE LOS 4 MÓDULOS -->
      <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VTabs
          v-model="activeModuleTab"
          bg-color="surface"
          color="primary"
          grow
          density="comfortable"
        >
          <VTab value="module1" class="font-weight-bold">
            <VIcon icon="tabler-photo" size="18" class="me-2" />
            Módulo 1: Fotografía General
          </VTab>
          <VTab value="module2" class="font-weight-bold">
            <VIcon icon="tabler-cash" size="18" class="me-2 text-success" />
            Módulo 2: Capital Recuperado CZ
            <VBadge
              v-if="auditData.module_2_cz_recovery?.total_cash_released > 0"
              color="success"
              inline
              class="ms-1 font-weight-black"
              :content="formatCurrency(auditData.module_2_cz_recovery.total_cash_released)"
            />
          </VTab>
          <VTab value="module3" class="font-weight-bold">
            <VIcon icon="tabler-truck-delivery" size="18" class="me-2 text-primary" />
            Módulo 3: Compras Prioritarias A/B
            <VBadge
              v-if="auditData.module_3_ab_restock?.total_critical_items > 0"
              :color="auditData.module_3_ab_restock.still_stockout_count > 0 ? 'error' : 'success'"
              inline
              class="ms-1"
              :content="`${auditData.module_3_ab_restock.restocked_count}/${auditData.module_3_ab_restock.total_critical_items}`"
            />
          </VTab>
          <VTab value="module4" class="font-weight-bold">
            <VIcon icon="tabler-percentage" size="18" class="me-2 text-warning" />
            Módulo 4: Alerta de Márgenes
            <VBadge
              v-if="auditData.module_4_margin_alerts?.negative_margin_count > 0"
              color="error"
              inline
              class="ms-1"
              :content="auditData.module_4_margin_alerts.negative_margin_count"
            />
          </VTab>
        </VTabs>
      </VCard>

      <VWindow v-model="activeModuleTab">
        <!-- MÓDULO 1: FOTOGRAFÍA GENERAL -->
        <VWindowItem value="module1">
          <FinishModule1General
            :audit-summary="auditData.module_1_summary"
            :selected-snapshot="selectedSnapshot"
            v-model:detail-search="detailSearch"
            v-model:selected-sales-class="selectedSalesClass"
            v-model:only-overstock="onlyOverstock"
            :items="detailItems"
            :total-items="totalDetailItems"
            v-model:page="detailPage"
            v-model:items-per-page="detailItemsPerPage"
            v-model:sort-by="detailSortBy"
            :loading="detailLoading"
            :headers="itemHeaders"
          />
        </VWindowItem>

        <!-- MÓDULO 2: MONITOREO DE CAPITAL RECUPERADO CZ -->
        <VWindowItem value="module2">
          <FinishModule2CzRecovery
            :cz-summary="auditData.module_2_cz_recovery"
            :items="filteredCzItems"
            v-model:search="czSearch"
          />
        </VWindowItem>

        <!-- MÓDULO 3: CONTROL DE COMPRAS PRIORITARIAS A/B -->
        <VWindowItem value="module3">
          <FinishModule3AbRestock
            :restock-summary="auditData.module_3_ab_restock"
            :items="filteredRestockItems"
            v-model:search="restockSearch"
          />
        </VWindowItem>

        <!-- MÓDULO 4: ALERTA DE MÁRGENES SALUDABLES -->
        <VWindowItem value="module4">
          <FinishModule4MarginAlerts
            :margin-summary="auditData.module_4_margin_alerts"
            :alerts="filteredMarginAlerts"
            v-model:search="marginSearch"
          />
        </VWindowItem>
      </VWindow>
    </template>

    <!-- DIÁLOGO: TOMAR FOTO FINISH MANUAL -->
    <VDialog v-model="isCreateDialogOpen" max-width="500" persistent>
      <VCard class="rounded-xl overflow-hidden">
        <VCardItem class="bg-primary text-white pa-4">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-camera-plus" size="22" />
              <h3 class="text-h6 font-weight-bold text-white mb-0">Tomar Foto Finish</h3>
            </div>
            <VBtn icon variant="text" size="30" color="white" :disabled="creatingSnapshot" @click="isCreateDialogOpen = false">
              <VIcon icon="tabler-x" size="20" />
            </VBtn>
          </div>
        </VCardItem>

        <VCardText class="pa-5">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Selecciona la fecha de corte para congelar la radiografía de inventario, existencias, ventas y cálculo de sobrestock.
          </p>

          <VRow dense>
            <VCol cols="12">
              <label class="text-caption font-weight-bold mb-1 d-block">Fecha de Corte *</label>
              <AppTextField
                v-model="createForm.cutoff_date"
                type="date"
                :max="new Date().toISOString().split('T')[0]"
                density="compact"
                variant="outlined"
                :error-messages="createErrors.cutoff_date"
                :disabled="creatingSnapshot"
              />
            </VCol>

            <VCol cols="12">
              <label class="text-caption font-weight-bold mb-1 d-block">Periodo de Ventas (Días)</label>
              <AppTextField
                v-model="createForm.period_days"
                type="number"
                min="7"
                max="365"
                density="compact"
                variant="outlined"
                placeholder="30"
                :error-messages="createErrors.period_days"
                :disabled="creatingSnapshot"
              />
              <span class="text-super-xs text-medium-emphasis">Ventana de días hacia atrás desde la fecha de corte (por defecto 30 días).</span>
            </VCol>

            <VCol cols="12" class="mt-2">
              <label class="text-caption font-weight-bold mb-1 d-block">Nombre / Identificador (Opcional)</label>
              <AppTextField
                v-model="createForm.name"
                placeholder="Ej: Cierre Agosto 2026, Auditoría Q3..."
                density="compact"
                variant="outlined"
                :error-messages="createErrors.name"
                :disabled="creatingSnapshot"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider class="border-opacity-10" />

        <VCardActions class="pa-4 d-flex justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="creatingSnapshot"
            @click="isCreateDialogOpen = false"
          >
            Cancelar
          </VBtn>

          <VBtn
            variant="flat"
            color="primary"
            :loading="creatingSnapshot"
            :disabled="creatingSnapshot"
            @click="handleCreateSnapshot"
          >
            <VIcon icon="tabler-camera" size="18" class="me-1" />
            Congelar Foto Finish
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.6875rem !important;
  line-height: 0.875rem !important;
}
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
