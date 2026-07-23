<script setup>
import axios from "@/plugins/axios";
import { onMounted, ref, watch, computed } from "vue";
import { toast } from "@/plugins/sweetalert";

const skus = ref([]);
const loading = ref(false);
const exporting = ref(false);
const totalItems = ref(0);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// Filtros
const search = ref("");
const startDate = ref("2026-04-01");
const endDate = ref("");
const selectedLaboratory = ref(null);
const selectedGroup = ref(null);
const semaphoreFilter = ref(null);
const statusFilter = ref(1); // Por defecto activo

const laboratories = ref([]);
const groups = ref([]);

const summaryStats = ref({
  global_margin_real: 0,
  total_discounts: 0,
  total_loss: 0,
  critical_skus: 0
});

const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return startDate.value || endDate.value || selectedLaboratory.value || selectedGroup.value || statusFilter.value !== null;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const fetchFilters = async () => {
  try {
    const [labRes, grpRes] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/groups"),
    ]);
    laboratories.value = labRes.data;
    groups.value = grpRes.data;
  } catch (error) {
    console.error("Error cargando filtros:", error);
  }
};

const fetchReport = async () => {
  loading.value = true;
  
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    search: search.value,
    start_date: startDate.value,
    end_date: endDate.value,
    laboratory_id: selectedLaboratory.value,
    group_id: selectedGroup.value,
    semaphore: semaphoreFilter.value,
    is_active: statusFilter.value
  };

  // Limpiar nulos
  Object.keys(params).forEach(key => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const { data } = await axios.get("/bi/sku-margin", { params });
    skus.value = data.data;
    totalItems.value = data.total;
    if (data.summary) {
      summaryStats.value = data.summary;
    }
  } catch (error) {
    console.error("Error obteniendo reporte SKU:", error);
    toast.error("Hubo un error cargando el reporte SKU.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchFilters();
  fetchReport();
});

const handleClearFilters = () => {
  search.value = "";
  startDate.value = "";
  endDate.value = "";
  selectedLaboratory.value = null;
  selectedGroup.value = null;
  semaphoreFilter.value = null;
  statusFilter.value = null;
  isAdvancedFiltersVisible.value = false;
};

const handleExport = async () => {
  exporting.value = true;
  const params = {
    search: search.value,
    start_date: startDate.value,
    end_date: endDate.value,
    laboratory_id: selectedLaboratory.value,
    group_id: selectedGroup.value,
    semaphore: semaphoreFilter.value,
    is_active: statusFilter.value
  };

  Object.keys(params).forEach(key => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const response = await axios.get('/bi/sku-margin/export', {
      params,
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    
    // Extraer el nombre del archivo del header Content-Disposition si es posible
    const contentDisposition = response.headers['content-disposition'];
    let fileName = 'margen_sku.csv';
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="?(.+)"?/);
      if (fileNameMatch && fileNameMatch.length >= 2) {
        fileName = fileNameMatch[1].replace(/"/g, '');
      }
    }

    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success('Reporte exportado con éxito');
  } catch (error) {
    console.error('Error exportando reporte:', error);
    toast.error('Hubo un error exportando el reporte.');
  } finally {
    exporting.value = false;
  }
};

// VDataTable Config
const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '60px' },
  { title: 'PRODUCTO', key: 'product_name', sortable: true, minWidth: '200px' },
  { title: 'VEND.', key: 'total_sold', sortable: true, width: '70px' },
  { title: 'COSTO', key: 'current_cost', sortable: false, width: '90px' },
  { title: 'P. LISTA', key: 'list_price', sortable: false, width: '90px' },
  { title: 'M. BRUTO', key: 'gross_margin_percent', sortable: true, width: '95px' },
  { title: 'DESC.', key: 'discount_avg_percent', sortable: false, width: '85px' },
  { title: 'M. NETO', key: 'net_margin_percent', sortable: false, width: '95px' },
  { title: 'MERMAS', key: 'loss_value', sortable: false, width: '85px' },
  { title: 'M. REAL', key: 'real_margin_percent', sortable: true, width: '95px' },
  { title: 'ESTADO', key: 'semaphore', sortable: false, width: '110px' }
];

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if(options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
};

let debounceTimer;
const debouncedFetchReport = (delay = 300) => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchReport();
  }, delay);
};

// Watch para filtros pesados (reinician a la página 1 sin duplicar peticiones)
watch(
  [search, startDate, endDate, selectedLaboratory, selectedGroup, semaphoreFilter, statusFilter],
  () => {
    if (page.value !== 1) {
      page.value = 1;
    } else {
      debouncedFetchReport(400);
    }
  }
);

// Watch para paginación y orden
watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetchReport(100);
  }
);

const getSemaphoreColor = (status) => {
  const mapping = {
    'verde': 'success',
    'amarillo': 'warning',
    'rojo': 'error',
    'negro': 'dark'
  };
  return mapping[status] || 'default';
};

const getSemaphoreLabel = (status) => {
  const mapping = {
    'verde': 'Rentable',
    'amarillo': 'Medio',
    'rojo': 'Peligro',
    'negro': 'Pérdidas'
  };
  return mapping[status] || status;
};

const formatPercent = (val) => {
  return Number(val).toFixed(2) + '%';
};
const formatMoney = (val) => {
  return '$' + Number(val).toFixed(2);
};
</script>

<template>
  <div>

    
    <!-- Filtros Estandarizados -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <!-- Barra de Búsqueda Principal (Siempre Visible) -->
        <VRow align="center" no-gutters class="gap-2">
          <!-- Buscador SKU/Producto -->
          <VCol cols="12" md="4" lg="4">
            <AppTextField
              v-model="search"
              placeholder="Buscar por SKU o Nombre..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              class="premium-input-compact"
            />
          </VCol>

          <!-- Filtro Semáforo -->
          <VCol cols="12" md="3" lg="3">
            <AppSelect
              v-model="semaphoreFilter"
              :items="[{title: '✅ Rentable (>25%)', value: 'verde'}, {title: '⚠️ Medio (10-25%)', value: 'amarillo'}, {title: '🚨 Peligro (<10%)', value: 'rojo'}, {title: '🏴 Pérdidas (<0%)', value: 'negro'}]"
              placeholder="Estado de Rentabilidad"
              density="compact"
              hide-details
              clearable
              class="premium-select-compact"
              prepend-inner-icon="tabler-traffic-lights"
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-1">
            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
              @click="toggleAdvancedFilters"
            >
              <VBadge
                v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
                color="error"
                dot
                offset-x="2"
                offset-y="-2"
              >
                <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              </VBadge>
              <VIcon v-else :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            </VBtn>

            <!-- Aplicar Filtros -->
            <VBtn
              icon
              variant="flat"
              color="primary"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="loading"
              @click="fetchReport"
            >
              <VIcon icon="tabler-player-play" size="20" />
              <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros (Siempre Visible) -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              :disabled="loading"
              @click="handleClearFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
            <!-- Exportar a Excel -->
            <VBtn
              icon
              variant="tonal"
              color="success"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="exporting"
              :disabled="loading || exporting"
              @click="handleExport"
            >
              <VIcon icon="tabler-download" size="20" />
              <VTooltip activator="parent" location="top">Exportar (Excel/CSV)</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzado -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
            
            <VRow>
              <VCol cols="12" sm="6" md="4">
                <AppDateTimePicker
                  v-model="startDate"
                  placeholder="Fecha Inicio"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                  prepend-inner-icon="tabler-calendar"
                />
              </VCol>

              <VCol cols="12" sm="6" md="4">
                <AppDateTimePicker
                  v-model="endDate"
                  placeholder="Fecha Fin"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                  prepend-inner-icon="tabler-calendar-check"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <AppAutocomplete
                  v-model="selectedLaboratory"
                  :items="laboratories"
                  item-title="name"
                  item-value="id"
                  placeholder="Laboratorio / Proveedor"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-flask"
                />
              </VCol>
              
              <VCol cols="12" sm="6" md="3">
                <AppSelect
                  v-model="statusFilter"
                  :items="[{ title: 'Activos', value: 1 }, { title: 'Inactivos', value: 0 }, { title: 'Todos', value: null }]"
                  placeholder="Estado"
                  density="compact"
                  hide-details
                  clearable
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-power"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Dashboard KPIs Estilo Premium -->
    <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
      <VCol v-for="(kpi, index) in [
        {
          title: 'Margen Real Global',
          value: formatPercent(summaryStats.global_margin_real),
          color: summaryStats.global_margin_real > 0 ? 'success' : 'error',
          icon: 'tabler-percentage',
          desc: 'Neto - Mermas'
        },
        {
          title: 'Impacto Desc.',
          value: formatMoney(summaryStats.total_discounts),
          color: 'warning',
          icon: 'tabler-discount-2',
          desc: 'Dinero cedido en ofertas'
        },
        {
          title: 'Pérdida por Mermas',
          value: formatMoney(summaryStats.total_loss),
          color: summaryStats.total_loss > 0 ? 'error' : 'secondary',
          icon: 'tabler-trash',
          desc: 'Costo total vencidos'
        },
        {
          title: 'Alertas Críticas',
          value: summaryStats.critical_skus,
          color: summaryStats.critical_skus > 0 ? 'error' : 'success',
          icon: 'tabler-alert-triangle',
          desc: 'SKUs en Pérdida'
        }
      ]" :key="index" cols="6" md="3" class="pa-1">
        <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
          <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)` }"></div>
          <VCardText class="pa-5 relative-content">
            <div v-if="loading" class="d-flex flex-column gap-2 py-2">
              <div class="d-flex justify-space-between align-center">
                <div class="w-25 bg-secondary-light animate-pulse rounded" style="height: 32px;"></div>
                <div class="w-50 bg-secondary-light animate-pulse rounded" style="height: 24px;"></div>
              </div>
              <div class="w-100 bg-secondary-light animate-pulse rounded mt-3" style="height: 10px;"></div>
            </div>
            <div v-else>
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon :icon="kpi.icon" size="26" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important; line-height: 1.2; display: block">{{ kpi.title }}</span>
                  <h4 class="text-h4 font-weight-black mt-1">{{ kpi.value }}</h4>
                </div>
              </div>
              <VDivider class="mb-3 opacity-20" />
            </div>
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
              <VIcon icon="tabler-chart-pie" size="16" :color="kpi.color" class="opacity-50" />
            </div>
          </VCardText>
          <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"></div>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Head de Tabla -->
      <VCardText class="d-flex justify-space-between align-center py-3">
        <h2 class="text-h6 font-weight-bold d-flex align-center">
          <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
          Desglose Financiero (Waterfall)
        </h2>
      </VCardText>
      <VDivider class="border-opacity-10" />

      <div class="d-none d-md-block">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="skus"
          :items-length="totalItems"
          :loading="loading"
          fixed-header
          height="600px"
          class="premium-table density-compact"
          @update:options="updateTableOptions"
        >
          <template #item.id="{ item }">
            <a
              :href="'/inventory/traceability?q=' + (item.id || item.product_id)"
              target="_blank"
              class="text-decoration-none font-weight-black text-primary"
            >
              {{ item.id || item.product_id }}
            </a>
          </template>

        <template #item.product_name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.product_name">
              {{ item.product_name.toUpperCase() }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs">
              <span class="text-disabled truncate" style="max-inline-size: 200px;">
                {{ item.active_ingredient || item.active_ingredient_inventory || 'SIN INGREDIENTE' }}
              </span>
              <span class="text-disabled mx-1">|</span>
              <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                {{ item.laboratory_name || 'S/L' }}
              </span>
            </div>
          </div>
        </template>
        
        <template #item.current_cost="{ item }">
          {{ formatMoney(item.current_cost) }}
        </template>
        
        <template #item.list_price="{ item }">
          {{ formatMoney(item.list_price) }}
        </template>

        <template #item.gross_margin_percent="{ item }">
          <VChip size="small" variant="tonal" color="info">
            {{ formatPercent(item.gross_margin_percent) }}
          </VChip>
          <div class="text-caption text-disabled mt-1">{{ formatMoney(item.gross_margin_value) }}</div>
        </template>

        <template #item.discount_avg_percent="{ item }">
          <span class="text-error font-weight-bold">-{{ formatPercent(item.discount_avg_percent) }}</span>
        </template>

        <template #item.net_margin_percent="{ item }">
          <VChip size="small" variant="tonal" color="primary">
            {{ formatPercent(item.net_margin_percent) }}
          </VChip>
          <div class="text-caption text-disabled mt-1">{{ formatMoney(item.net_margin_value) }}</div>
        </template>
        
        <template #item.loss_value="{ item }">
          <span v-if="item.loss_value > 0" class="text-error">-${{ Number(item.loss_value).toFixed(2) }}</span>
          <span v-else class="text-disabled">$0.00</span>
        </template>

        <template #item.real_margin_percent="{ item }">
          <strong class="text-h6" :class="`text-${getSemaphoreColor(item.semaphore)}`">
             {{ formatPercent(item.real_margin_percent) }}
          </strong>
          <div class="text-caption mt-1">{{ formatMoney(item.real_margin_value) }} Total</div>
        </template>

        <template #item.semaphore="{ item }">
          <VChip
            :color="getSemaphoreColor(item.semaphore)"
            size="small"
            class="text-uppercase font-weight-bold"
          >
            {{ getSemaphoreLabel(item.semaphore) }}
          </VChip>
        </template>
        
        <template #no-data>
          <div class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
            <p>Sin resultados para los filtros aplicados</p>
          </div>
        </template>
      </VDataTableServer>
      </div>

      <!-- Vista Cards: solo móvil -->
      <div class="d-md-none">
        <VProgressLinear v-if="loading" indeterminate color="primary" />
        <div v-if="skus.length === 0 && !loading" class="text-center pa-8 text-medium-emphasis">
          <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
          <p>Sin resultados para los filtros aplicados</p>
        </div>
        <div v-for="item in skus" :key="item.product_id || item.id" class="px-2 py-1">
          <VCard variant="flat" class="product-mobile-card border mb-2">
            <div class="pa-3">
              <!-- Cabecera -->
              <div class="d-flex align-start justify-space-between gap-2">
                <div class="flex-grow-1 min-width-0">
                  <div class="d-flex align-center gap-1 mb-1">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                      <span class="text-primary text-xs">#{{ item.product_id || item.id }}</span>
                      <span class="mx-1 text-disabled">|</span>
                      {{ item.product_name }}
                    </h3>
                  </div>
                  <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                    <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                      {{ item.laboratory_name || 'S/L' }}
                    </span>
                  </div>
                </div>
                <VChip
                  :color="getSemaphoreColor(item.semaphore)"
                  class="text-uppercase font-weight-black flex-shrink-0"
                  variant="elevated"
                  size="x-small"
                  label
                >
                  {{ getSemaphoreLabel(item.semaphore) }}
                </VChip>
              </div>

              <VDivider class="my-3 border-opacity-10" />

              <!-- Métricas en grilla (Estilo Inventory) -->
              <div class="metrics-grid rounded border-dashed-thin bg-var-theme-background">
                <VRow dense class="ma-0">
                  <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Costo Unit.</div>
                    <div class="text-sm font-weight-black">{{ formatMoney(item.current_cost) }}</div>
                  </VCol>
                  <VCol cols="6" class="pa-2 border-b border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Precio Lista / Venta</div>
                    <div class="text-sm font-weight-black">{{ formatMoney(item.list_price) }}</div>
                  </VCol>
                  <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1 text-info">Margen Bruto</div>
                    <div class="text-sm font-weight-black text-info">{{ formatPercent(item.gross_margin_percent) }}</div>
                    <div class="text-super-xs text-disabled" v-if="item.discount_avg_percent > 0">Desc: -{{ formatPercent(item.discount_avg_percent) }}</div>
                  </VCol>
                  <VCol cols="6" class="pa-2 border-b border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1 text-primary">Margen Neto</div>
                    <div class="text-sm font-weight-black text-primary">{{ formatPercent(item.net_margin_percent) }}</div>
                    <div class="text-super-xs text-disabled" :class="{'text-error': item.loss_value > 0}">Mermas: {{ formatMoney(item.loss_value) }}</div>
                  </VCol>
                  <VCol cols="12" class="pa-2 d-flex justify-space-between align-center">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">M. Real Efectivo</div>
                    <div class="text-base font-weight-black" :class="`text-${getSemaphoreColor(item.semaphore)}`">
                      {{ formatPercent(item.real_margin_percent) }}
                    </div>
                  </VCol>
                </VRow>
              </div>
            </div>

            <!-- Acciones -->
            <div class="d-flex border-t border-opacity-10">
              <VBtn 
                :href="'/inventory/traceability?q=' + (item.product_id || item.id)" 
                target="_blank"
                block 
                color="primary" 
                variant="text" 
                class="rounded-0 text-caption font-weight-bold" 
                height="40"
              >
                <VIcon icon="tabler-history" size="18" class="me-2" />
                Ver Trazabilidad
              </VBtn>
            </div>
          </VCard>
        </div>

        <!-- Paginación móvil -->
        <div class="d-flex justify-center align-center pa-3 gap-3">
          <VBtn icon variant="text" size="32" :disabled="page <= 1" @click="page--">
            <VIcon icon="tabler-chevron-left" size="18" />
          </VBtn>
          <span class="text-caption text-medium-emphasis">Pág. {{ page }}</span>
          <VBtn icon variant="text" size="32" :disabled="skus.length < itemsPerPage" @click="page++">
            <VIcon icon="tabler-chevron-right" size="18" />
          </VBtn>
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.premium-table :deep(td) {
  padding: 0 8px !important;
  font-size: 0.75rem !important;
  height: 48px !important;
}

.premium-table :deep(th) {
  padding: 0 8px !important;
}

.text-dark {
  color: #212121 !important;
}

.text-super-xs {
  font-size: 0.6rem !important;
  line-height: 1.1;
}

.gap-1 { gap: 4px !important; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.bg-secondary-light {
  background-color: rgba(var(--v-theme-secondary), 0.15);
}
</style>
