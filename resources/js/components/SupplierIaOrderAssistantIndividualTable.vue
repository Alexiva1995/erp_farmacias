<script setup>

import { useDisplay } from 'vuetify';
import VueApexCharts from 'vue3-apexcharts';
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update:page"]);

const { mdAndUp } = useDisplay();

// Configuración de Sparkline
const getChartOptions = (item, color = '#7367f0') => ({
  chart: {
    type: 'area',
    height: 30,
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
  { title: "ID", key: "id", sortable: true, width: '60px' },
  { title: "Producto", key: "name", sortable: true, minWidth: '220px' },
  { title: "Tendencia", key: "trend", sortable: false, width: '100px' },
  { title: "Costo", key: "unit_cost", sortable: true, align: 'end', width: '100px' },
  { title: "Vent.", key: "total_sold_completed", sortable: true, align: 'end', width: '90px' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'end', width: '90px' },
  { title: "PREF", key: "preferencia_product", sortable: true, align: 'end', width: '110px' },
  { title: "Prom.", key: "promedio_calculado", sortable: true, align: 'end', width: '100px' },
  { title: "En Pedido", key: "totalQuantityInAutoOrder", sortable: true, align: 'end', width: '100px' },
  { title: "Análisis (u)", key: "solicitar", sortable: true, align: 'end', width: '110px' },
];

// Determina el color de fondo por fila
function rowClass(item) {
  const val = parseFloat(item.solicitar);
  if (val > 0) return 'row-needs';
  if (val < 0) return 'row-excess';
  return '';
}
</script>

<template>
  <div class="assistant-table-container">
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Desktop -->
      <div v-if="mdAndUp" class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.products"
          :items-length="props.totalProduct"
          :loading="props.loading"
          :row-props="({ item }) => ({ class: rowClass(item) })"
          class="text-no-wrap assistant-data-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado vacío -->
          <template #no-data>
            <div class="d-flex flex-column align-center py-12 text-disabled">
              <VIcon icon="tabler-package-off" size="48" class="mb-3" />
              <span class="text-body-1 font-weight-medium">No hay productos que coincidan con los filtros</span>
              <span class="text-caption mt-1">Ajusta los filtros de laboratorio, grupo o lapso de tiempo</span>
            </div>
          </template>

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
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                :class="{ 'text-primary': item.psychotropic == 1 }"
                :title="item.name"
              >
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

          <!-- Tendencia -->
          <template #item.trend="{ item }">
            <div style="block-size: 30px; inline-size: 100px;">
              <VueApexCharts
                type="area"
                height="30"
                :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                :series="getSeries(item)"
              />
            </div>
          </template>

          <!-- Costo -->
          <template #item.unit_cost="{ item }">
            <span class="font-weight-medium">
              ${{ Number(item.unit_cost || 0).toFixed(2) }}
            </span>
          </template>

          <!-- Preferencia con tooltip -->
          <template #item.preferencia_product="{ item }">
            <VTooltip text="Unidades de preferencia histórica del proveedor">
              <template #activator="{ props: tp }">
                <span v-bind="tp">{{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(2) : '—' }}</span>
              </template>
            </VTooltip>
          </template>

          <!-- Promedio con tooltip -->
          <template #item.promedio_calculado="{ item }">
            <VTooltip text="Promedio de ventas en el lapso de tiempo seleccionado">
              <template #activator="{ props: tp }">
                <span v-bind="tp">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(2) : '—' }}</span>
              </template>
            </VTooltip>
          </template>

          <!-- En Pedido con tooltip -->
          <template #item.totalQuantityInAutoOrder="{ item }">
            <VTooltip text="Unidades ya incluidas en otras órdenes de compra activas">
              <template #activator="{ props: tp }">
                <VChip
                  v-bind="tp"
                  :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'"
                  variant="tonal"
                  size="small"
                  class="rounded-lg"
                >
                  {{ item.totalQuantityInAutoOrder || 0 }}
                </VChip>
              </template>
            </VTooltip>
          </template>

          <!-- Análisis -->
          <template #item.solicitar="{ item }">
            <VTooltip :text="parseFloat(item.solicitar) > 0 ? 'Unidades sugeridas a reponer' : parseFloat(item.solicitar) < 0 ? 'Exceso: no se necesita comprar' : 'Stock suficiente'">
              <template #activator="{ props: tp }">
                <span
                  v-bind="tp"
                  class="font-weight-black"
                  :style="roundIaAnalysis(item.solicitar) > 0 ? 'color:#28c76f' : roundIaAnalysis(item.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'"
                >
                  {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }} u.
                </span>
              </template>
            </VTooltip>
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
              class="border mb-1 rounded-lg overflow-hidden"
              :class="rowClass(item)"
            >
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
                      <span>{{ item.laboratory?.name || 'S/L' }}</span>
                      <span>|</span>
                      <span>{{ item.active_ingredient }}</span>
                      <VChip v-if="item.is_colombian_origin == 1" color="info" size="x-small" label class="ml-1 text-super-xs">COL</VChip>
                    </div>
                  </div>
                  <div class="text-right">
                    <div style="block-size: 25px; inline-size: 70px; margin-block-end: 2px;">
                      <VueApexCharts
                        type="area"
                        height="25"
                        :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                        :series="getSeries(item)"
                      />
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

:deep(.assistant-data-table) {
  font-size: 0.875rem !important;
}

:deep(.row-needs td) {
  background-color: rgba(40, 199, 111, 4%) !important;
}

:deep(.row-excess td) {
  background-color: rgba(234, 84, 85, 4%) !important;
}

/* Estilos para cards móviles */
.row-needs.v-card {
  border-inline-start: 4px solid #28c76f !important;
  background-color: rgba(40, 199, 111, 2%) !important;
}

.row-excess.v-card {
  border-inline-start: 4px solid #ea5455 !important;
  background-color: rgba(234, 84, 85, 2%) !important;
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

.gap-2 { gap: 8px !important; }

.legend-dot {
  display: inline-block;
  border-radius: 50%;
  block-size: 10px;
  inline-size: 10px;
}
.legend-needs { background: rgba(40, 199, 111, 40%); }
.legend-excess { background: rgba(234, 84, 85, 40%); }
</style>
