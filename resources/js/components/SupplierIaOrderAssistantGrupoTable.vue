<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import VueApexCharts from 'vue3-apexcharts';
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  grupos: { type: Array, required: true },         // Array de { group_id, group_name, productos }
  totalGrupos: { type: Number, required: true },   // Total de grupos (para paginación)
  perPage: { type: Number, default: 25 },
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['page-change', 'product-scarce-toggled']);

// Grupo expandido (uno a la vez)
const expandedGroupId = ref(null);

const toggleGroup = (groupId) => {
  if (expandedGroupId.value === groupId) {
    expandedGroupId.value = null;
  } else {
    expandedGroupId.value = groupId;
  }
};

const isExpanded = (groupId) => expandedGroupId.value === groupId;

// Marcar escaso
const togglingScarce = ref(null);
const handleToggleScarce = async (product) => {
  if (togglingScarce.value === product.id) return;
  togglingScarce.value = product.id;
  try {
    await axios.post(`/api/products/${product.id}/toggle-scarce`);
    emit('product-scarce-toggled', product.id);
  } catch (error) {
    console.error("Error toggling scarce:", error);
  } finally {
    togglingScarce.value = null;
  }
};

// Sparkline
const readyCharts = ref(new Set());
const markChartAsReady = (id) => {
  if (!readyCharts.value.has(id)) {
    requestAnimationFrame(() => readyCharts.value.add(id));
  }
};

const getChartOptions = (item, color = '#7367f0') => ({
  chart: {
    type: 'area',
    height: 22,
    sparkline: { enabled: true },
    animations: { enabled: true },
    parentHeightOffset: 0,
  },
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: { opacityFrom: 0.4, opacityTo: 0 }
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
      title: { formatter: () => 'Ventas: ' }
    },
    marker: { show: false }
  }
});

const getSeries = (item) => [{ name: 'Ventas', data: item.sales_trend?.length > 0 ? item.sales_trend : [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0] }];

// KPIs de grupo
const grupoKpi = (productos) => {
  let falta = 0, exceso = 0, ok = 0;
  productos.forEach(p => {
    const v = roundIaAnalysis(p.solicitar);
    if (v > 0 || (v === 0 && (p.lote_quantity ?? 0) <= 0)) falta++;
    else if (v < 0) exceso++;
    else ok++;
  });
  return { falta, exceso, ok };
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm bg-surface">

    <!-- Estado vacío -->
    <div v-if="!loading && grupos.length === 0" class="d-flex flex-column align-center py-16 text-disabled">
      <VIcon icon="tabler-package-off" size="48" class="mb-3" />
      <span class="text-body-1 font-weight-medium">No hay grupos con productos filtrados</span>
    </div>

    <!-- Skeleton de carga -->
    <div v-else-if="loading" class="pa-4 d-flex flex-column gap-3">
      <div v-for="n in 5" :key="n" class="rounded-lg border pa-3 skeleton-group">
        <div class="skeleton-bar short mb-2" />
        <div class="skeleton-bar medium" />
      </div>
    </div>

    <!-- Acordeón de grupos -->
    <div v-else class="pa-2">
      <div
        v-for="grupo in grupos"
        :key="grupo.group_id"
        class="grupo-card mb-2 rounded-lg border overflow-hidden"
      >
        <!-- Cabecera del grupo (clickeable) -->
        <div
          class="grupo-header d-flex align-center justify-space-between pa-3 cursor-pointer"
          :class="isExpanded(grupo.group_id) ? 'grupo-header--expanded' : ''"
          @click="toggleGroup(grupo.group_id)"
        >
          <div class="d-flex align-center gap-3">
            <VIcon
              :icon="isExpanded(grupo.group_id) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
              size="18"
              color="primary"
            />
            <span class="text-sm font-weight-black text-uppercase text-high-emphasis">
              {{ grupo.group_name || `Grupo #${grupo.group_id}` }}
            </span>
            <VChip size="x-small" variant="tonal" color="primary" class="font-weight-bold">
              {{ grupo.productos.length }} prod.
            </VChip>
          </div>

          <!-- KPIs rápidos del grupo -->
          <div class="d-flex align-center gap-2" @click.stop>
            <VChip v-if="grupoKpi(grupo.productos).falta > 0" size="x-small" color="success" variant="tonal" class="font-weight-bold">
              <VIcon start size="10">tabler-arrow-up</VIcon>
              {{ grupoKpi(grupo.productos).falta }} faltan
            </VChip>
            <VChip v-if="grupoKpi(grupo.productos).exceso > 0" size="x-small" color="error" variant="tonal" class="font-weight-bold">
              <VIcon start size="10">tabler-arrow-down</VIcon>
              {{ grupoKpi(grupo.productos).exceso }} exceso
            </VChip>
            <VChip v-if="grupoKpi(grupo.productos).ok > 0" size="x-small" color="default" variant="tonal">
              {{ grupoKpi(grupo.productos).ok }} ok
            </VChip>
          </div>
        </div>

        <!-- Tabla de productos del grupo (expandible) -->
        <div v-if="isExpanded(grupo.group_id)" class="grupo-body">
          <VTable density="compact" class="grupo-table">
            <thead>
              <tr>
                <th class="text-xs font-weight-black text-uppercase" style="inline-size:50px">ID</th>
                <th class="text-xs font-weight-black text-uppercase">Producto</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:75px">Trend</th>
                <th class="text-xs font-weight-black text-uppercase text-right" style="inline-size:75px">Costo</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:60px">Vent.</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:60px">Stock</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:65px">Prom.</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:60px">PREF</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:60px">Ped.</th>
                <th class="text-xs font-weight-black text-uppercase text-center" style="inline-size:80px">Anál.</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in grupo.productos"
                :key="item.id"
                class="producto-row"
                :class="{
                  'row-needs': roundIaAnalysis(item.solicitar) > 0,
                  'row-excess': roundIaAnalysis(item.solicitar) < 0,
                }"
              >
                <td>
                  <a :href="`/inventory/traceability?q=${item.id}`" target="_blank"
                     class="text-decoration-none text-xs font-weight-black text-primary">
                    {{ item.id }}
                  </a>
                </td>
                <td>
                  <div class="d-flex flex-column py-1" style="max-inline-size: 260px;">
                    <span
                      class="text-xs font-weight-black text-high-emphasis text-uppercase text-truncate cursor-pointer hover-opacity"
                      :class="{ 'text-primary': item.psychotropic == 1, 'opacity-50': togglingScarce === item.id }"
                      :title="item.name + ' - Clic para marcar como escaso'"
                      @click="handleToggleScarce(item)"
                    >
                      <VIcon v-if="togglingScarce === item.id" size="x-small" class="mr-1 rotate-spinner">tabler-loader-2</VIcon>
                      {{ item.name }}
                    </span>
                    <div class="d-flex align-center gap-1 text-super-xs text-truncate">
                      <span class="text-disabled text-truncate" style="max-inline-size: 140px;">{{ item.active_ingredient }}</span>
                      <span v-if="item.laboratory" class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 100px;">
                        - {{ item.laboratory.name }}
                      </span>
                      <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-bold ml-1">(COL)</span>
                    </div>
                  </div>
                </td>
                <td class="text-center">
                  <div class="d-flex align-center justify-center p-0" style="block-size: 28px; inline-size: 70px; margin: auto; overflow: hidden;" v-intersect="() => markChartAsReady(item.id)">
                    <VueApexCharts
                      v-if="readyCharts.has(item.id)"
                      type="area" height="22" width="100%"
                      :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                      :series="getSeries(item)"
                    />
                    <div v-else class="chart-placeholder" />
                  </div>
                </td>
                <td class="text-right text-xs">${{ Number(item.unit_cost ?? 0).toFixed(2) }}</td>
                <td class="text-center text-xs">{{ item.total_sold_completed ?? 0 }}</td>
                <td class="text-center text-xs">{{ item.lote_quantity ?? 0 }}</td>
                <td class="text-center text-xs">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '—' }}</td>
                <td class="text-center text-xs">
                  <span class="font-weight-bold" :style="item.preferencia_product > 0 ? 'color:#7367f0' : ''">
                    {{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(1) + '%' : '—' }}
                  </span>
                </td>
                <td class="text-center">
                  <VChip :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'" variant="tonal" size="x-small">
                    {{ item.totalQuantityInAutoOrder || 0 }}
                  </VChip>
                </td>
                <td class="text-center">
                  <span
                    class="text-xs font-weight-black"
                    :style="roundIaAnalysis(item.solicitar) > 0 ? 'color:#28c76f' : roundIaAnalysis(item.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'"
                  >
                    {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }} u.
                  </span>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </div>
    </div>

    <!-- Paginación de grupos -->
    <div v-if="!loading && totalGrupos > 0" class="d-flex align-center justify-space-between pa-4 border-t">
      <span class="text-sm text-disabled">
        Mostrando grupos {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, totalGrupos) }} de {{ totalGrupos }}
      </span>
       <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
    </div>
  </VCard>
</template>

<style scoped>
.grupo-card {
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  transition: box-shadow 0.2s;
}
.grupo-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.grupo-header {
  background: rgba(var(--v-border-color), 0.03);
  transition: background 0.2s;
  user-select: none;
}
.grupo-header:hover {
  background: rgba(var(--v-theme-primary), 0.06);
}
.grupo-header--expanded {
  background: rgba(var(--v-theme-primary), 0.06);
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}
.grupo-table {
  font-size: 0.78rem !important;
}
:deep(.grupo-table thead tr th) {
  background: rgba(var(--v-border-color), 0.04) !important;
  font-size: 0.65rem !important;
  padding-inline: 6px !important;
  padding-block: 5px !important;
}
:deep(.grupo-table tbody tr td) {
  padding-inline: 6px !important;
  padding-block: 4px !important;
  font-size: 0.78rem !important;
}

.row-needs td {
  background-color: rgba(40, 199, 111, 3%) !important;
}

.row-excess td {
  background-color: rgba(234, 84, 85, 3%) !important;
}

.row-needs td:first-child {
  border-inline-start: 3px solid #28c76f;
}
.producto-row:hover td {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}
.text-super-xs { font-size: 0.62rem !important; }
.hover-opacity:hover { opacity: 0.7; text-decoration: underline; }
.chart-placeholder {
  block-size: 22px; inline-size: 100%;
  background: rgba(var(--v-border-color), 0.08);
  border-radius: 4px;
}
.skeleton-group { background: rgba(var(--v-border-color), 0.05); }
.skeleton-bar {
  height: 12px; border-radius: 6px;
  background: linear-gradient(90deg, rgba(var(--v-border-color), 0.08) 25%, rgba(var(--v-border-color), 0.16) 50%, rgba(var(--v-border-color), 0.08) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.skeleton-bar.short { width: 40%; }
.skeleton-bar.medium { width: 70%; }
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.rotate-spinner { animation: rotate 1s linear infinite; }
@keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
