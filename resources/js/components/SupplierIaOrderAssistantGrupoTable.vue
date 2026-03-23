<script setup>
import VueApexCharts from 'vue3-apexcharts';
import { useDisplay } from 'vuetify';
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
const getChartOptions = (color = '#7367f0') => ({
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
  colors: [color],
  tooltip: { enabled: false }
});

const getSeries = (item) => {
  const base = Number(item.total_sold_completed || 0);
  const data = [
    base * 0.9, base * 1.1, base * 0.8, base * 1.2, base * 0.7, base * 1.4, base
  ].map(v => Math.max(0, Math.round(v)));
  
  return [{ name: 'Ventas', data }];
};

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Tendencia", key: "trend", sortable: false, width: '100px' },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Ventas", key: "total_sold_completed", sortable: true },
  { title: "Stock", key: "lote_quantity", sortable: true },
  {
    title: "Preferencia",
    key: "preferencia_product",
    sortable: true,
    value: (item) =>
      item.preferencia_product != "" && item.preferencia_product != null
        ? parseFloat(item.preferencia_product).toFixed(2)
        : 0,
  },
  {
    title: "Promedio",
    key: "promedio_calculado",
    sortable: true,
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "AO",
    key: "totalQuantityInAutoOrder",
    sortable: false,
  },
  {
    title: "Análisis",
    key: "solicitar",
    sortable: true,
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
    <!-- Vista Desktop -->
    <VCard v-if="mdAndUp" variant="outlined" class="rounded-lg elevation-0 bg-surface">
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
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4">
            <div class="d-flex flex-column py-1">
              <span
                class="text-body-2 font-weight-medium text-high-emphasis"
                :class="{ 'text-primary': item.psychotropic == 1 }"
              >
                {{ item.name }}
              </span>

              <span class="text-caption text-disabled">
                {{ item.active_ingredient }}
                <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-bold ml-1">(COL)</span>
              </span>
            </div>
          </div>
        </template>

        <template #item.trend="{ item }">
          <div style="block-size: 30px; inline-size: 100px;">
            <VueApexCharts
              type="area"
              height="30"
              :options="getChartOptions(roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
              :series="getSeries(item)"
            />
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
          >
            {{ item.totalQuantityInAutoOrder || 0 }}
          </VChip>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-assistant-cards">
      <div v-if="props.loading" class="d-flex justify-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="props.products.length > 0">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          class="mb-4 elevation-0 border rounded-lg overflow-hidden card-ia-entry"
        >
          <!-- Badge de Grupo si aplica -->
          <div class="bg-var-theme-background pa-2 px-4 border-b">
            <span class="text-caption font-weight-bold text-uppercase text-disabled">
              Grupo: {{ item.group?.name || 'Sin Grupo' }}
            </span>
          </div>

          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="flex-grow-1 pr-2">
                <div class="text-subtitle-2 font-weight-bold text-primary mb-1">
                  {{ item.name }}
                </div>
                <div class="text-caption text-disabled">
                  {{ item.active_ingredient }}
                  <VChip v-if="item.is_colombian_origin == 1" color="info" size="x-small" label class="ml-1">COL</VChip>
                </div>
              </div>
              <div class="text-right">
                <div style="block-size: 30px; inline-size: 80px; margin-block-end: 4px;">
                  <VueApexCharts
                    type="area"
                    height="30"
                    :options="getChartOptions(roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                    :series="getSeries(item)"
                  />
                </div>
                <div class="text-h6 font-weight-black" :style="roundIaAnalysis(item.solicitar) > 0 ? 'color:#28c76f' : roundIaAnalysis(item.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'">
                  {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }} u.
                </div>
                <div class="text-caption text-uppercase font-weight-bold text-disabled">Sugerencia</div>
              </div>
            </div>

            <VDivider class="my-3" />

            <VRow dense>
              <VCol cols="4">
                <div class="text-caption text-disabled text-uppercase">Stock</div>
                <div class="font-weight-bold">{{ item.lote_quantity || 0 }}</div>
              </VCol>
              <VCol cols="4">
                <div class="text-caption text-disabled text-uppercase">Ventas</div>
                <div class="font-weight-bold">{{ item.total_sold_completed || 0 }}</div>
              </VCol>
              <VCol cols="4">
                <div class="text-caption text-disabled text-uppercase">Pedido</div>
                <VChip
                  :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'"
                  variant="tonal"
                  size="x-small"
                  label
                >
                  {{ item.totalQuantityInAutoOrder || 0 }}
                </VChip>
              </VCol>
              <VCol cols="4" class="mt-2">
                <div class="text-caption text-disabled text-uppercase">Costo</div>
                <div class="font-weight-bold">${{ Number(item.unit_cost || 0).toFixed(2) }}</div>
              </VCol>
              <VCol cols="4" class="mt-2">
                <div class="text-caption text-disabled text-uppercase">Promedio</div>
                <div class="font-weight-bold">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '—' }}</div>
              </VCol>
              <VCol cols="4" class="mt-2">
                <div class="text-caption text-disabled text-uppercase">Pref.</div>
                <div class="font-weight-bold">{{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(1) : '—' }}</div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
          :total-visible="5"
          @update:model-value="emit('update:options', { page: $event, itemsPerPage: props.itemsPerPage, sortBy: [], groupBy: [] })"
        />
      </template>

      <div v-else class="d-flex flex-column align-center py-12 text-disabled border rounded-lg bg-surface">
        <VIcon icon="tabler-package-off" size="48" class="mb-3" />
        <span class="text-body-1 font-weight-medium">No hay productos filtrados</span>
      </div>
    </div>
  </div>
</template>
