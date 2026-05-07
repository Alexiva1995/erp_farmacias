<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useDisplay } from 'vuetify';
import { useDebounceFn } from '@vueuse/core';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  grupos: { type: Array, required: true },         // Array de { group_id, group_name, productos }
  totalGrupos: { type: Number, required: true },   // Total de grupos (para paginación)
  perPage: { type: Number, default: 25 },
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  loading: { type: Boolean, default: false },
  showGraphs: { type: Boolean, default: false },
  withSuppliers: { type: Boolean, default: false },
});

const emit = defineEmits(['page-change', 'product-scarce-toggled', 'open-comparator', 'remove-item']);

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

// Acciones de Pedido/Ignorar
const editedValues = ref({});
const isProcessing = ref({});

const getInputValue = (item) => {
  if (item.id in editedValues.value) return editedValues.value[item.id];
  return roundIaAnalysis(item.solicitar ?? 0);
};

const updateInputValue = (item, val) => {
  const numericVal = val === "" ? null : parseFloat(val);
  editedValues.value[item.id] = numericVal;
  persistManualQuantity(item.id, numericVal);
};

// Función para persistir en BD con debounce
const persistManualQuantity = useDebounceFn(async (productId, quantity) => {
  try {
    await axios.post(`/api/suppliers-ia-order-assistant/products/${productId}/update-manual-quantity`, {
      quantity: quantity
    });
  } catch (error) {
    console.error("Error persisting manual quantity:", error);
  }
}, 800);

const onActionClick = async (item, action) => {
  if (isProcessing.value[item.id]) return;

  if (action === 'add') {
    const quantity = getInputValue(item);
    if (!item.best_supplier) {
      emit('open-comparator', { item, quantity });
      return;
    }
    
    isProcessing.value[item.id] = 'adding';
    try {
      await axios.post('/api/suppliers-ia-order-assistant/add-to-order', {
        product_id: item.id,
        quantity: quantity,
        product_supplier_id: item.best_supplier.product_suppliers_id
      });
      emit('remove-item', item.id);
    } catch (error) {
       console.error("Error adding to order:", error);
    } finally {
      delete isProcessing.value[item.id];
    }
  }

  if (action === 'ignore') {
    isProcessing.value[item.id] = 'ignoring';
    try {
      await axios.post(`/api/suppliers-ia-order-assistant/products/${item.id}/ignore`);
      emit('remove-item', item.id);
    } catch (error) {
       console.error("Error ignoring product:", error);
    } finally {
      delete isProcessing.value[item.id];
    }
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

const { mdAndUp } = useDisplay();

// Headers para la tabla interna en desktop
const innerHeaders = computed(() => {
  const base = [
    { title: "ID", key: "id", sortable: true, width: '50px' },
    { title: "Producto", key: "name", sortable: true, minWidth: '260px' },
  ];

  if (props.showGraphs) {
    base.push({ title: "Trend", key: "trend", sortable: false, width: '80px' });
  }

  base.push({ title: "Costo", key: "unit_cost", sortable: true, align: 'end', width: '80px' });

  if (props.withSuppliers) {
    base.push({ title: "COSTP", key: "best_supplier_price", sortable: false, align: 'end', width: '90px' });
  }

  base.push(
    { title: "Vent.", key: "total_sold_completed", sortable: true, align: 'end', width: '65px' },
    { title: "Stock", key: "lote_quantity", sortable: true, align: 'end', width: '65px' },
    { title: "PREF", key: "preferencia_product", sortable: true, align: 'end', width: '70px' },
    { title: "Prom.", key: "promedio_calculado", sortable: true, align: 'end', width: '70px' },
    { title: "Ped.", key: "totalQuantityInAutoOrder", sortable: true, align: 'end', width: '70px' },
    { title: "Pedido", key: "solicitar", sortable: true, align: 'center', width: '100px' },
    { title: "Acción", key: "actions", sortable: false, align: 'end', width: '110px' }
  );

  return base;
});

// Determina el color de fondo por fila
function rowClass(item) {
  const val = parseFloat(item.solicitar);
  if (val > 0) return 'row-needs';
  if (val < 0) return 'row-excess';
  return '';
}
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
        </div>        <!-- Productos del grupo (expandible) en formato tarjetas -->
        <!-- Contenido expandido -->
        <div v-if="isExpanded(grupo.group_id)" class="grupo-body pa-4 bg-var-theme-background">
          
          <!-- Vista Desktop (Tabla) -->
          <div v-if="mdAndUp" class="d-none d-md-block">
            <VDataTable
              :headers="innerHeaders"
              :items="grupo.productos"
              density="compact"
              class="inner-products-table"
              hide-default-footer
              :items-per-page="-1"
              :row-props="({ item }) => ({ class: rowClass(item) })"
            >
              <template #item.id="{ item }">
                <a :href="'/inventory/traceability?q=' + item.id" target="_blank" class="text-decoration-none text-xs font-weight-black text-primary">
                  {{ item.id }}
                </a>
              </template>

              <template #item.name="{ item }">
                <div class="d-flex flex-column py-1">
                  <span
                    class="text-sm font-weight-black text-high-emphasis text-uppercase text-wrap cursor-pointer hover-opacity"
                    :class="{ 'text-primary': item.psychotropic == 1, 'opacity-50': togglingScarce === item.id }"
                    @click="handleToggleScarce(item)"
                  >
                    <VIcon v-if="togglingScarce === item.id" size="small" class="mr-1 rotate-spinner">tabler-loader-2</VIcon>
                    {{ item.name }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs flex-wrap">
                    <span class="text-disabled">{{ item.active_ingredient }}</span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-primary font-weight-black text-uppercase">
                      {{ item.laboratory?.name || 'S/L' }}
                      <span v-if="item.best_supplier && props.withSuppliers" class="text-warning ml-1">- {{ item.best_supplier?.name }}</span>
                    </span>
                    <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" label class="ml-1 text-super-xs px-1">COL</VChip>
                  </div>
                </div>
              </template>

              <template v-if="props.showGraphs" #item.trend="{ item }">
                <div style="block-size: 22px; inline-size: 80px;" v-intersect="() => markChartAsReady(item.id)">
                  <VueApexCharts
                    v-if="readyCharts.has(item.id)"
                    type="area" height="22" width="100%"
                    :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                    :series="getSeries(item)"
                  />
                </div>
              </template>

              <template #item.unit_cost="{ item }">
                <span class="font-weight-medium">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
              </template>

              <template v-if="props.withSuppliers" #item.best_supplier_price="{ item }">
                <span class="font-weight-black text-warning">
                  ${{ Number(item.best_supplier_price || 0).toFixed(2) }}
                  <span v-if="item.best_supplier_percentage !== 0" class="ms-1" style="font-size: 10px;" :class="item.best_supplier_percentage < 0 ? 'text-success' : 'text-error'">
                    ({{ item.best_supplier_percentage < 0 ? '↓' : '↑' }}{{ Math.abs(item.best_supplier_percentage).toFixed(0) }}%)
                  </span>
                </span>
              </template>

              <template #item.total_sold_completed="{ item }">
                <span class="font-weight-bold">{{ item.total_sold_completed ?? 0 }}</span>
              </template>

              <template #item.lote_quantity="{ item }">
                <span class="font-weight-bold" :class="Number(item.lote_quantity) <= 0 ? 'text-error' : ''">
                  {{ item.lote_quantity ?? 0 }}
                </span>
              </template>

              <template #item.preferencia_product="{ item }">
                <span :class="item.preferencia_product > 0 ? 'text-primary font-weight-black' : ''">
                  {{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(1) + '%' : '—' }}
                </span>
              </template>

              <template #item.promedio_calculado="{ item }">
                <span>{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '—' }}</span>
              </template>

              <template #item.totalQuantityInAutoOrder="{ item }">
                <VChip v-if="item.totalQuantityInAutoOrder > 0" color="info" size="x-small" variant="tonal" class="font-weight-black">
                  {{ item.totalQuantityInAutoOrder }}
                </VChip>
                <span v-else>—</span>
              </template>

              <template #item.solicitar="{ item }">
                <VTextField
                  :model-value="getInputValue(item)"
                  @update:model-value="(val) => updateInputValue(item, val)"
                  type="number"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="centered-input-text-super-xs mx-auto"
                  style="max-inline-size: 85px;"
                  @click.stop
                />
              </template>

              <template #item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                  <VBtn
                    variant="tonal"
                    color="success"
                    size="30"
                    icon
                    :loading="isProcessing[item.id] === 'adding'"
                    @click.stop="onActionClick(item, 'add')"
                  >
                    <VIcon size="18">tabler-shopping-cart-plus</VIcon>
                    <VTooltip activator="parent" location="top">Añadir a Orden</VTooltip>
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    color="error"
                    size="30"
                    icon
                    :loading="isProcessing[item.id] === 'ignoring'"
                    @click.stop="onActionClick(item, 'ignore')"
                  >
                    <VIcon size="18">tabler-square-x</VIcon>
                    <VTooltip activator="parent" location="top">Rechazar (7 días)</VTooltip>
                  </VBtn>
                </div>
              </template>
            </VDataTable>
          </div>

          <!-- Vista Móvil (Cards - existente) -->
          <div v-else class="d-block d-md-none">
            <VRow>
              <VCol
                v-for="item in grupo.productos"
                :key="item.id"
                cols="12"
              >
                <VCard
                  variant="outlined"
                  class="producto-card h-100 d-flex flex-column"
                  :class="{
                    'card-needs': roundIaAnalysis(item.solicitar) > 0,
                    'card-excess': roundIaAnalysis(item.solicitar) < 0,
                  }"
                >
                  <!-- Cabecera de la Tarjeta -->
                  <VCardItem class="pb-1">
                    <template #prepend>
                      <a :href="`/inventory/traceability?q=${item.id}`" target="_blank"
                         class="text-decoration-none text-xs font-weight-black text-primary me-2">
                        #{{ item.id }}
                      </a>
                    </template>
                    <VCardTitle class="text-sm font-weight-black text-uppercase text-truncate">
                      <span
                        class="cursor-pointer hover-opacity"
                        :class="{ 'text-primary': item.psychotropic == 1, 'opacity-50': togglingScarce === item.id }"
                        @click="handleToggleScarce(item)"
                      >
                        <VIcon v-if="togglingScarce === item.id" size="x-small" class="mr-1 rotate-spinner">tabler-loader-2</VIcon>
                        {{ item.name }}
                      </span>
                    </VCardTitle>
                    <VCardSubtitle class="text-super-xs d-flex align-center flex-wrap ga-1 mt-1">
                      <span class="text-truncate" style="max-inline-size: 150px;">{{ item.active_ingredient }}</span>
                      <span v-if="item.laboratory" class="text-primary font-weight-black text-uppercase">
                        • {{ item.laboratory.name }}
                      </span>
                      <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" density="compact" variant="tonal" class="px-1 text-super-xs">COL</VChip>
                    </VCardSubtitle>
                  </VCardItem>

                  <VDivider />

                  <VCardText class="flex-grow-1 py-3 px-3">
                    <!-- Grid de Estadísticas -->
                    <div class="stats-grid mb-3">
                      <div class="stat-item">
                        <span class="stat-label">Ventas</span>
                        <span class="stat-value">{{ item.total_sold_completed ?? 0 }}</span>
                      </div>
                      <div class="stat-item">
                        <span class="stat-label">Stock</span>
                        <span class="stat-value">{{ item.lote_quantity ?? 0 }}</span>
                      </div>
                      <div class="stat-item">
                        <span class="stat-label">Prom.</span>
                        <span class="stat-value">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '—' }}</span>
                      </div>
                      <div class="stat-item">
                        <span class="stat-label">PREF</span>
                        <span class="stat-value" :class="item.preferencia_product > 0 ? 'text-primary font-weight-black' : ''">
                          {{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(1) + '%' : '—' }}
                        </span>
                      </div>
                    </div>

                    <!-- Precios y Tendencia -->
                    <VRow no-gutters align="center">
                      <VCol cols="7">
                        <div class="d-flex flex-column ga-1">
                          <div class="d-flex align-center justify-space-between text-xs">
                            <span class="text-disabled">Costo Actual:</span>
                            <span class="font-weight-bold">${{ Number(item.unit_cost ?? 0).toFixed(2) }}</span>
                          </div>
                          <div v-if="props.withSuppliers" class="d-flex align-center justify-space-between text-xs">
                            <span class="text-warning font-weight-bold">Mejor Precio:</span>
                            <span class="text-warning font-weight-black">${{ Number(item.best_supplier_price ?? 0).toFixed(2) }}</span>
                          </div>
                          <div v-if="item.totalQuantityInAutoOrder > 0" class="d-flex align-center justify-space-between text-xs">
                            <span class="text-info font-weight-bold">En Pedido:</span>
                            <VChip color="info" size="x-small" variant="tonal" class="font-weight-black">{{ item.totalQuantityInAutoOrder }}</VChip>
                          </div>
                        </div>
                      </VCol>
                      <VCol cols="5" class="ps-3 border-s">
                        <div v-if="props.showGraphs" class="trend-container">
                          <div class="text-super-xs text-disabled text-center mb-1">Tendencia</div>
                          <div style="block-size:30px; inline-size:100%;" v-intersect="() => markChartAsReady(item.id)">
                            <VueApexCharts
                              v-if="readyCharts.has(item.id)"
                              type="area" height="30" width="100%"
                              :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                              :series="getSeries(item)"
                            />
                          </div>
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>

                  <VDivider />

                  <!-- Pie de Tarjeta: Acciones -->
                  <VCardActions class="pa-3 bg-var-theme-background">
                    <div class="d-flex align-center w-100 ga-2">
                      <div class="flex-grow-1">
                        <div class="d-flex align-center ga-2">
                          <span class="text-xs font-weight-bold">Análisis:</span>
                          <VTextField
                            :model-value="getInputValue(item)"
                            @update:model-value="(val) => updateInputValue(item, val)"
                            type="number"
                            density="compact"
                            hide-details
                            variant="outlined"
                            class="centered-input-text-super-xs"
                            style="max-inline-size: 80px;"
                          />
                        </div>
                      </div>

                      <div class="d-flex ga-1">
                        <VBtn
                          icon
                          variant="tonal"
                          color="success"
                          size="32"
                          :loading="isProcessing[item.id] === 'adding'"
                          @click.stop="onActionClick(item, 'add')"
                        >
                          <VIcon icon="tabler-shopping-cart-plus" size="18" />
                          <VTooltip activator="parent" location="top">Añadir a Orden</VTooltip>
                        </VBtn>

                        <VBtn
                          icon
                          variant="tonal"
                          color="error"
                          size="32"
                          :loading="isProcessing[item.id] === 'ignoring'"
                          @click.stop="onActionClick(item, 'ignore')"
                        >
                          <VIcon icon="tabler-square-x" size="18" />
                          <VTooltip activator="parent" location="top">Rechazar (7 días)</VTooltip>
                        </VBtn>
                      </div>
                    </div>
                  </VCardActions>
                </VCard>
              </VCol>
            </VRow>
          </div>
        </div>
      </div>
    </div>

    <!-- Paginación de grupos -->
    <div v-if="!loading && totalGrupos > 0" class="d-flex align-center justify-space-between pa-4 border-t">
      <span class="text-sm text-disabled">
        Mostrando grupos {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, totalGrupos) }} de {{ totalGrupos }}
      </span>
        <AppMobilePagination
          :page="props.currentPage"
          :items-per-page="props.perPage"
          :total-items="props.totalGrupos"
          :loading="props.loading"
          :items-per-page-options="[10, 25, 50]"
          @change="(options) => emit('page-change', options.page)"
        />
    </div>
  </VCard>
</template>

<style scoped>
.grupo-card {
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.grupo-header {
  background: rgba(var(--v-border-color), 0.03);
  transition: background 0.2s;
  user-select: none;
}

.grupo-header:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.grupo-header--expanded {
  background: rgba(var(--v-theme-primary), 0.08);
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}

/* Estilos de Tarjeta de Producto */
.producto-card {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
  transition: transform 0.2s, box-shadow 0.2s;
  border-radius: 12px !important;
  background: rgb(var(--v-theme-surface));
}

.producto-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.card-needs {
  border-left: 4px solid #28c76f !important;
}

.card-excess {
  border-left: 4px solid #ea5455 !important;
}

/* Grid de Estadísticas */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  background: rgba(var(--v-border-color), 0.04);
  padding: 8px;
  border-radius: 8px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.stat-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-weight: 600;
  margin-bottom: 2px;
}

.stat-value {
  font-size: 0.85rem;
  font-weight: 700;
}

.trend-container {
  min-height: 45px;
}

:deep(.centered-input-text-super-xs .v-field__input) {
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  min-height: 32px !important;
  padding-block: 4px !important;
  text-align: center !important;
}

.text-super-xs {
  font-size: 0.68rem !important;
}

.hover-opacity:hover {
  opacity: 0.7;
  text-decoration: underline;
  color: rgb(var(--v-theme-primary));
}

/* Animaciones */
.rotate-spinner {
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Skeleton Loading */
.skeleton-group {
  background: rgba(var(--v-border-color), 0.05);
}

.skeleton-bar {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(
    90deg,
    rgba(var(--v-border-color), 0.08) 25%,
    rgba(var(--v-border-color), 0.16) 50%,
    rgba(var(--v-border-color), 0.08) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-bar.short { width: 40%; }
.skeleton-bar.medium { width: 70%; }

.bg-var-theme-background-secondary {
  background-color: rgba(var(--v-theme-secondary), 0.03);
}

.inner-products-table {
  background: transparent !important;
}

:deep(.inner-products-table .v-table__wrapper) {
  border-radius: 8px !important;
  overflow: hidden !important;
}

:deep(.inner-products-table table) {
  border-collapse: separate !important;
  border-spacing: 0 4px !important;
}

:deep(.inner-products-table tr) {
  background: white !important;
  transition: all 0.2s ease;
}

:deep(.inner-products-table tr:hover) {
  filter: brightness(0.98);
  transform: scale(1.002);
}

:deep(.inner-products-table td) {
  border-bottom: none !important;
  font-size: 0.75rem !important;
}

:deep(.row-needs td:first-child) {
  border-left: 0px !important;
  box-shadow: inset 4px 0 0 #28c76f !important;
}

:deep(.row-needs td:last-child) {
  border-right: 0px !important;
  box-shadow: inset -4px 0 0 #28c76f !important;
}

:deep(.row-excess td:first-child) {
  border-left: 0px !important;
  box-shadow: inset 4px 0 0 #ea5455 !important;
}

:deep(.row-excess td:last-child) {
  border-right: 0px !important;
  box-shadow: inset -4px 0 0 #ea5455 !important;
}
</style>
