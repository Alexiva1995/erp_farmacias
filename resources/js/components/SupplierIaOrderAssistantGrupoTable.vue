<script setup>
import VueApexCharts from 'vue3-apexcharts';
import { useDisplay } from 'vuetify';
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update:page", "refresh"]);

const { mdAndUp } = useDisplay();

// Estado para carga diferida de gráficos (evitar lag al abrir grupos)
const readyCharts = ref(new Set());

const markChartAsReady = (id) => {
  if (!readyCharts.value.has(id)) {
    // Usar requestAnimationFrame para distribuir la carga de renderizado
    requestAnimationFrame(() => {
      readyCharts.value.add(id);
    });
  }
};

// Acción para marcar como escaso
const togglingScarce = ref(null);

const handleToggleScarce = async (product) => {
  if (togglingScarce.value === product.id) return;
  
  togglingScarce.value = product.id;
  try {
    await axios.patch(`/api/products/${product.id}/toggle-scarce`);
    // Emitir evento para que el padre lo saque de la lista localmente
    emit('product-scarce-toggled', product.id);
  } catch (error) {
    console.error("Error toggling scarce status:", error);
  } finally {
    togglingScarce.value = null;
  }
};

// Configuración de Sparkline
const getChartOptions = (item, color = '#7367f0') => ({
  chart: {
    type: 'area',
    height: 25,
    sparkline: { enabled: true },
    animations: { enabled: true }
  },
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.5,
      opacityTo: 0,
      stops: [0, 90, 100]
    }
  },
  xaxis: {
    categories: item.sales_trend_labels || []
  },
  colors: [color],
  tooltip: {
    enabled: true,
    fixed: { enabled: false },
    x: { show: true },
    y: {
      title: { formatter: () => 'Ventas:' }
    },
    marker: { show: false }
  }
});

const getSeries = (item) => {
  const data = (item.sales_trend && item.sales_trend.length > 0) 
    ? item.sales_trend 
    : [0, 0, 0, 0, 0, 0];
  
  return [{ name: 'Ventas', data }];
};

const headers = [
  { title: "ID", key: "id", sortable: true, width: '50px' },
  { title: "Producto", key: "name", sortable: true, minWidth: '160px' },
  { title: "Trend", key: "trend", sortable: false, width: '80px' },
  { title: "Costo", key: "unit_cost", sortable: true, width: '80px' },
  { title: "Vent.", key: "total_sold_completed", sortable: true, width: '65px' },
  { title: "Stock", key: "lote_quantity", sortable: true, width: '65px' },
  {
    title: "PREF",
    key: "preferencia_product",
    sortable: true,
    width: '70px',
    value: (item) =>
      item.preferencia_product != "" && item.preferencia_product != null
        ? parseFloat(item.preferencia_product).toFixed(2)
        : 0,
  },
  {
    title: "Prom.",
    key: "promedio_calculado",
    sortable: true,
    width: '70px',
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "Ped.",
    key: "totalQuantityInAutoOrder",
    sortable: false,
    width: '70px',
  },
  {
    title: "Anál.",
    key: "solicitar",
    sortable: true,
    width: '85px',
    value: (item) => {
      item.solicitar != "" && item.solicitar != null
        ? parseFloat(item.solicitar).toFixed(2)
        : 0;
    },
  },
];

const groupBy = [{ key: "group.name" }];
</script>

<template>
  <div class="assistant-table-container">
    <VCard class="rounded-lg border shadow-sm bg-surface">
      <!-- Vista Desktop -->
      <div v-if="mdAndUp" class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.products"
          :items-length="props.totalProduct"
          :loading="props.loading"
          class="text-no-wrap assistant-data-table"
          :group-by="groupBy"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- ID -->
          <template #item.id="{ item }">
            <a
              :href="'/inventory/traceability?q=' + item.id"
              target="_blank"
              class="text-decoration-none text-sm font-weight-black text-primary"
            >
              {{ item.id }}
            </a>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex flex-column py-1" style="max-inline-size: 320px;">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate cursor-pointer hover-opacity"
                :class="{ 
                  'text-primary': item.psychotropic == 1,
                  'opacity-50': togglingScarce === item.id 
                }"
                :title="item.name + ' - Clic para marcar como escaso'"
                @click="handleToggleScarce(item)"
              >
                <VIcon v-if="togglingScarce === item.id" size="small" class="mr-1 rotate-spinner">tabler-loader-2</VIcon>
                {{ item.name.toUpperCase() }}
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 180px;">{{ item.active_ingredient }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
                <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-bold ml-1">(COL)</span>
              </div>
            </div>
          </template>

          <template #item.trend="{ item }">
            <div style="block-size: 25px; inline-size: 80px;" v-intersect="() => markChartAsReady(item.id)">
              <VueApexCharts
                v-if="readyCharts.has(item.id)"
                type="area"
                height="25"
                :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                :series="getSeries(item)"
              />
              <div v-else class="chart-placeholder"></div>
            </div>
          </template>
          
          <template #item.solicitar="{ item }">
            <span
              class="font-weight-black"
              :style="roundIaAnalysis(item.solicitar) > 0 ? 'color:#28c76f;' : roundIaAnalysis(item.solicitar) < 0 ? 'color:#ea5455;' : 'color:inherit;'"
            >
              {{ roundIaAnalysis(item.solicitar) > 0 ? "+" : "" }}{{ roundIaAnalysis(item.solicitar) }} u.
            </span>
          </template>

          <template #item.totalQuantityInAutoOrder="{ item }">
            <VChip
              :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'"
              variant="tonal"
              size="small"
              class="rounded-lg"
            >
              {{ item.totalQuantityInAutoOrder || 0 }}
            </VChip>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div v-else class="d-block d-md-none pa-2">
        <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
        
        <template v-if="props.products.length > 0">
          <div class="d-flex flex-column gap-2">
            <VCard
              v-for="item in props.products"
              :key="item.id"
              variant="flat"
              class="border mb-1 rounded-lg overflow-hidden card-ia-entry"
              :style="roundIaAnalysis(item.solicitar) > 0 ? 'border-inline-start: 4px solid #28c76f !important; background-color: rgba(40, 199, 111, 2%) !important;' : roundIaAnalysis(item.solicitar) < 0 ? 'border-inline-start: 4px solid #ea5455 !important; background-color: rgba(234, 84, 85, 2%) !important;' : ''"
            >
              <!-- Badge de Grupo integration -->
              <div class="bg-var-theme-background pa-2 px-3 border-b">
                <span class="text-super-xs font-weight-black text-uppercase text-disabled">
                  Grupo: {{ item.group?.name || 'Sin Grupo' }}
                </span>
              </div>

              <div class="pa-3">
                <div class="d-flex justify-space-between align-start mb-2">
                  <div class="flex-grow-1 pr-2">
                    <a
                      :href="'/inventory/traceability?q=' + item.id"
                      target="_blank"
                      class="text-decoration-none text-sm font-weight-black text-primary text-uppercase leading-tight truncate-2-lines mb-1"
                    >
                      {{ item.name }}
                    </a>
                    <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs text-disabled">
                      <span>{{ item.active_ingredient }}</span>
                      <VChip v-if="item.is_colombian_origin == 1" color="info" size="x-small" label class="ml-1 text-super-xs">COL</VChip>
                    </div>
                  </div>
                  <div class="text-right">
                    <div style="block-size: 25px; inline-size: 70px; margin-block-end: 2px;" v-intersect="() => markChartAsReady(item.id)">
                      <VueApexCharts
                        v-if="readyCharts.has(item.id)"
                        type="area"
                        height="25"
                        :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                        :series="getSeries(item)"
                      />
                      <div v-else class="chart-placeholder"></div>
                    </div>
                    <div class="text-sm font-weight-black" :style="roundIaAnalysis(item.solicitar) > 0 ? 'color:#28c76f' : roundIaAnalysis(item.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'">
                      {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }} u.
                    </div>
                  </div>
                </div>

                <VDivider class="my-2 border-opacity-10" />

                <div class="grid-mobile-info">
                  <div class="info-item">
                    <span class="label">Stock</span>
                    <span class="value">{{ item.lote_quantity || 0 }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Vent.</span>
                    <span class="value">{{ item.total_sold_completed || 0 }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Pedido</span>
                    <span class="value">
                      <VChip :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'" variant="tonal" size="x-small" label>
                        {{ item.totalQuantityInAutoOrder || 0 }}
                      </VChip>
                    </span>
                  </div>
                  <div class="info-item">
                    <span class="label">Costo</span>
                    <span class="value text-primary">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Prom.</span>
                    <span class="value">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '—' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">PREF</span>
                    <span class="value">{{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(1) : '—' }}</span>
                  </div>
                </div>
              </div>
            </VCard>
          </div>

          <!-- Paginación Móvil -->
          <div class="d-flex justify-center mt-4">
            <VPagination
              :model-value="props.page"
              :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
              :total-visible="3"
              density="compact"
              size="small"
              @update:model-value="emit('update:options', { page: $event, itemsPerPage: props.itemsPerPage, sortBy: [], groupBy: [] })"
            />
          </div>
        </template>

        <div v-else class="d-flex flex-column align-center py-12 text-disabled text-center">
          <VIcon icon="tabler-package-off" size="48" class="mb-3" />
          <span class="text-body-1 font-weight-medium">No hay productos filtrados</span>
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
:deep(.v-data-table-header) {
  background-color: #fff !important;
}

:deep(.v-data-table-header th) {
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.05);
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

:deep(.v-data-table-header th:last-child) {
  border-inline-end: none;
}

:deep(.v-data-table-group-header-row .v-table__group-header-content) {
  column-gap: 8px !important;
}

/* Ajuste fino para el botón de expansión */
:deep(.v-data-table-group-header-row .v-btn--icon) {
  margin-inline-start: -4px !important;
}

:deep(.assistant-data-table) {
  font-size: 0.8125rem !important;
}

:deep(.v-data-table__td),
:deep(.v-data-table__th) {
  padding-inline: 4px !important;
}

.grid-mobile-info {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(3, 1fr);
}

.info-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.info-item .label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
}

.info-item .value {
  font-size: 0.75rem;
  font-weight: 700;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.hover-opacity:hover {
  opacity: 0.7;
  text-decoration: underline;
}

.chart-placeholder {
  block-size: 25px;
  inline-size: 100%;
  background: linear-gradient(90deg, rgba(var(--v-border-color), 0.05) 25%, rgba(var(--v-border-color), 0.1) 50%, rgba(var(--v-border-color), 0.05) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@keyframes rotate-spinner {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.rotate-spinner {
  animation: rotate-spinner 1s linear infinite;
}

.opacity-50 {
  opacity: 0.5;
}

.gap-2 { gap: 8px !important; }
</style>
