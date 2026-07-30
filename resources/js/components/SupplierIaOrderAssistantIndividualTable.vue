<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

import { useDisplay } from 'vuetify';
import VueApexCharts from 'vue3-apexcharts';
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { ref, computed, watch } from 'vue';
import axios from "@/plugins/axios";
import { useDebounceFn } from '@vueuse/core';
import Swal from 'sweetalert2';
import { toast } from "@/plugins/sweetalert";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  withSuppliers: { type: Boolean, default: false },
  showGraphs: { type: Boolean, default: false },
  sortBy: { type: String, default: "solicitar" },
  orderBy: { type: String, default: "desc" },
  selectedSupplierId: { type: [Number, String], default: null },
});

const emit = defineEmits(["update:options", "update:page", "refresh", "product-scarce-toggled", "open-comparator", "remove-item", "reject-ai-match"]);

// Track edited pedido values per item id
const editedValues = ref({});

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
    await axios.post(`/suppliers-ia-order-assistant/products/${productId}/update-manual-quantity`, {
      quantity: quantity
    });
  } catch (error) {
    console.error("Error persisting manual quantity:", error);
    toast.error("Error al guardar la cantidad manual.");
  }
}, 800);

// Estado para carga diferida de gráficos
const readyCharts = ref(new Set());

const markChartAsReady = (id) => {
  if (!readyCharts.value.has(id)) {
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
    await axios.post(`/suppliers-ia-order-assistant/products-without-supplier/${product.id}/toggle-scarce`);
    emit('product-scarce-toggled', product.id);
    toast.success("Estado de escasez actualizado.");
  } catch (error) {
    console.error("Error toggling scarce status:", error);
    toast.error("Error al cambiar estado de escasez.");
  } finally {
    togglingScarce.value = null;
  }
};

// Acciones Directas
const isProcessing = ref({});

// Rechazar un match sugerido por IA para que el sistema aprenda
const rejectAiMatch = async (item) => {
  if (!item.best_supplier?.is_ai_matched || !item.best_supplier?.id) return;
  try {
    await axios.post('/supplier-ai-match/reject', {
      product_id:          item.id,
      product_supplier_id: item.best_supplier.id,
    });
    // Ignorar por 7 días al rechazar coincidencia
    await axios.post(`/suppliers-ia-order-assistant/products/${item.id}/ignore`);
    emit('remove-item', item.id);
    toast.success("Sugerencia rechazada y producto ocultado por 7 días.");
  } catch (error) {
    console.error('Error rechazando match IA:', error);
    toast.error("Error al rechazar la sugerencia de la IA.");
  }
};

const onActionClick = async (item, action) => {
  if (isProcessing.value[item.id]) return;

  if (action === 'add') {
    const quantity = getInputValue(item);
    const isColombian = Number(item.is_colombian_origin) === 1;

    if (!props.selectedSupplierId && !item.best_supplier && !isColombian) {
      // Si no hay proveedor seleccionado, ni proveedor más barato, ni es producto COL, abrir modal de comparación
      emit('open-comparator', { item, quantity });
      return;
    }

    if (item.best_supplier?.is_ai_matched) {
      const { isConfirmed } = await Swal.fire({
        title: "Coincidencia por IA",
        html: `Por IA se sugiere que este producto corresponde a:<br><strong>${item.best_supplier.matched_name || item.best_supplier.name}</strong> del proveedor.<br><br>¿Deseas confirmar esta coincidencia y agregarlo a la orden?`,
        icon: "info",
        showCancelButton: true,
        confirmButtonText: "Sí, agregar",
        cancelButtonText: "Cancelar",
      });
      if (!isConfirmed) return;
    }
    
    // Validar código de barras diferente y preguntar por reemplazo si el listado tiene uno
    const nuestroBarcode = item.barcode ? String(item.barcode).trim() : '';
    const cheapestBarcode = item.cheapest_barcode ? String(item.cheapest_barcode).trim() : '';

    if (cheapestBarcode && nuestroBarcode !== cheapestBarcode) {
      const { isConfirmed } = await Swal.fire({
        title: "¿Reemplazar código de barras?",
        html: `Nuestro producto actual tiene el código: <strong>${nuestroBarcode || 'vacío'}</strong>.<br>El del listado del proveedor es: <strong>${cheapestBarcode}</strong>.<br><br>¿Desea actualizar nuestro código de barras por el del listado?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, reemplazar",
        cancelButtonText: "No, mantener actual",
      });

      if (isConfirmed) {
        try {
          await axios.post(`/suppliers-ia-order-assistant/products/${item.id}/update-barcode`, {
            barcode: cheapestBarcode
          });
          // Actualizamos la propiedad barcode localmente
          item.barcode = cheapestBarcode;
          toast.success("Código de barras actualizado.");
        } catch (updateError) {
          console.error("Error updating barcode:", updateError);
          if (updateError.response?.status === 409 && updateError.response?.data?.conflict) {
            const { isConfirmed: confirmForce } = await Swal.fire({
              title: "Código duplicado",
              text: updateError.response.data.message,
              icon: "warning",
              showCancelButton: true,
              confirmButtonText: "Sí, desvincular y asignar",
              cancelButtonText: "Cancelar",
            });
            if (confirmForce) {
              try {
                await axios.post(`/suppliers-ia-order-assistant/products/${item.id}/update-barcode`, {
                  barcode: cheapestBarcode,
                  force: true
                });
                item.barcode = cheapestBarcode;
                toast.success("Código de barras asignado y desvinculado.");
              } catch (forceError) {
                console.error("Error forcing barcode update:", forceError);
                toast.error("No se pudo forzar el reajuste del código de barras.");
              }
            }
          } else {
            toast.error("No se pudo actualizar el código de barras.");
          }
        }
      }
    }

    isProcessing.value[item.id] = 'adding';
    try {
      const payload = {
        product_id: item.id,
        quantity: quantity,
      };

      if (isColombian) {
        payload.supplier_id = 48;
      } else if (props.selectedSupplierId) {
        payload.supplier_id = props.selectedSupplierId;
        if (item.best_supplier_price) {
          payload.unit_cost = item.best_supplier_price;
        }
      } else if (item.best_supplier) {
        payload.product_supplier_id = item.best_supplier.product_suppliers_id;
        if (item.best_supplier_price) {
          payload.unit_cost = item.best_supplier_price;
        }
      }

      await axios.post('/suppliers-ia-order-assistant/add-to-order', payload);
      toast.success("Producto añadido a la orden.");
      emit('remove-item', item.id);
    } catch (error) {
       console.error("Error adding to order:", error);
       toast.error("Error al añadir producto a la orden.");
    } finally {
      delete isProcessing.value[item.id];
    }
  } else if (action === 'ignore') {
    isProcessing.value[item.id] = 'ignoring';
    try {
      await axios.post(`/suppliers-ia-order-assistant/products/${item.id}/ignore`);
      toast.success("Producto ignorado.");
      emit('remove-item', item.id);
    } catch (error) {
      console.error("Error ignoring product:", error);
      toast.error("No se pudo ignorar el producto.");
    } finally {
      delete isProcessing.value[item.id];
    }
  }
};

const { mdAndUp } = useDisplay();

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
    : [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
  
  return [{ name: 'Ventas', data }];
};

// Headers computados dinámicos
const headers = computed(() => {
  const base = [
    { title: "ID", key: "id", sortable: true, width: '50px' },
    { title: "Producto", key: "name", sortable: true, minWidth: '260px' },
  ];

  if (props.showGraphs) {
    base.push({ title: "Trend", key: "trend", sortable: false, width: '80px' });
  }

  base.push({ title: "Costo", key: "unit_cost", sortable: true, align: 'end', width: '80px' });

  if (props.withSuppliers) {
    base.push({ title: "COSTP", key: "best_supplier_percentage", sortable: true, align: 'end', width: '90px' });
  }

  base.push(
    { title: "Vent.", key: "total_sold_completed", sortable: true, align: 'end', width: '65px' },
    { title: "Stock", key: "lote_quantity", sortable: true, align: 'end', width: '65px' },
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
          :row-props="({ item }) => ({ class: rowClass(item) })"
          :sort-by="props.sortBy ? [{ key: props.sortBy, order: props.orderBy }] : []"
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
            <div class="d-flex flex-column py-1">
              <div class="d-flex align-center gap-1">
                <span
                  class="text-sm font-weight-black text-high-emphasis text-uppercase text-wrap cursor-pointer hover-opacity"
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
                <!-- Badge: producto nuevo sin historial de ventas -->
                <VChip
                  v-if="item.is_new_without_history"
                  color="secondary"
                  size="x-small"
                  variant="tonal"
                  class="ml-1"
                  title="Producto nuevo sin historial de ventas (< 90 días). Se sugiere revisar."
                >
                  <VIcon start size="10">tabler-sparkles</VIcon>
                  NUEVO
                </VChip>
              </div>
              <div class="d-flex align-center gap-1 text-super-xs flex-wrap">
                <span class="text-disabled">{{ item.active_ingredient }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase">
                  {{ item.laboratory?.name || 'S/L' }}
                  <span v-if="item.best_supplier && props.withSuppliers" class="text-warning ml-1">
                    - {{ item.best_supplier?.name || 'S/P' }}
                  </span>
                </span>
                <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-bold ml-1">(COL)</span>
                <!-- Advertencia: promedio desactualizado (> 48h) -->
                <VTooltip v-if="item.is_stale_average" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VIcon
                      v-bind="tooltipProps"
                      icon="tabler-alert-triangle"
                      color="warning"
                      size="13"
                      class="ml-1"
                    />
                  </template>
                  <span>Promedio de ventas desactualizado (más de 48h). Ejecuta el cálculo para mayor precisión.</span>
                </VTooltip>
              </div>
            </div>
          </template>

          <template v-if="props.showGraphs" #item.trend="{ item }">
            <div style="block-size: 25px; inline-size: 80px; overflow: hidden;" v-intersect="() => markChartAsReady(item.id)">
              <VueApexCharts
                v-if="readyCharts.has(item.id)"
                :key="'chart-' + item.id + '-' + (item.sales_trend ? item.sales_trend.join(',') : 'empty')"
                type="area"
                height="25"
                width="100%"
                :options="getChartOptions(item, roundIaAnalysis(item.solicitar) > 0 ? '#28c76f' : '#7367f0')"
                :series="getSeries(item)"
              />
              <div v-else class="chart-placeholder"></div>
            </div>
          </template>

          <!-- Ventas -->
          <template #item.total_sold_completed="{ item }">
            <span class="font-weight-medium">
              {{ item.total_sold_completed ? Math.round(item.total_sold_completed) : 0 }}
            </span>
          </template>

          <!-- Stock -->
          <template #item.lote_quantity="{ item }">
            <span class="font-weight-medium">
              {{ item.lote_quantity ? Math.round(item.lote_quantity) : 0 }}
            </span>
          </template>

          <!-- Costo -->
          <template #item.unit_cost="{ item }">
            <span class="font-weight-medium">
              ${{ Number(item.unit_cost || 0).toFixed(2) }}
            </span>
          </template>

          <!-- Costo Proveedor -->
          <template v-if="props.withSuppliers" #item.best_supplier_percentage="{ item }">
            <span class="font-weight-black text-warning">
              ${{ Number(item.best_supplier_price || 0).toFixed(2) }}
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

          <!-- Pedido (Editable) -->
          <template #item.solicitar="{ item }">
            <div class="d-flex flex-column align-center py-1" style="inline-size: 100px;">
              <VTextField
                :model-value="getInputValue(item)"
                @update:model-value="(val) => updateInputValue(item, val)"
                type="number"
                density="compact"
                hide-details
                variant="outlined"
                class="centered-input-text-sm mb-1"
                @click.stop
              />
              <span v-if="props.withSuppliers && item.best_supplier && item.best_supplier_percentage !== 0" class="text-super-xs font-weight-bold" :class="item.best_supplier_percentage < 0 ? 'text-success' : 'text-error'">
                {{ item.best_supplier_percentage < 0 ? ' ahorro ' : ' subió ' }}{{ Math.abs(item.best_supplier_percentage).toFixed(1) }}%
              </span>
            </div>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end ga-1">
              <!-- Indicador de Matching en Progreso -->
              <div v-if="item.ia_matching_in_progress" class="d-flex align-center ga-1 pr-2">
                <VProgressCircular indeterminate size="16" width="2" color="info" />
                <span class="text-xs font-weight-black text-info text-uppercase">Buscando Proveedor...</span>
              </div>
              <template v-else>
                <VBtn
                  v-if="!isRestaurant"
                  icon
                  variant="tonal"
                  color="error"
                  size="32"
                  :loading="isProcessing[item.id] === 'ignoring'"
                  @click.stop="onActionClick(item, 'ignore')"
                >
                  <VIcon icon="tabler-trash-x" size="18" />
                  <VTooltip activator="parent" location="top">Rechazar / Ignorar</VTooltip>
                </VBtn>
                <!-- Botón rechazar match IA: solo visible si el proveedor fue sugerido por IA -->
                <VBtn
                  v-if="item.best_supplier?.is_ai_matched"
                  icon
                  variant="tonal"
                  color="warning"
                  size="32"
                  title="Rechazar match IA (aprendizaje)"
                  @click.stop="rejectAiMatch(item)"
                >
                  <VIcon icon="tabler-brain-off" size="18" />
                  <VTooltip activator="parent" location="top">Rechazar sugerencia de IA</VTooltip>
                </VBtn>
                <VBtn
                  icon
                  variant="tonal"
                  :color="item.best_supplier?.is_ai_matched ? 'info' : 'success'"
                  size="32"
                  :loading="isProcessing[item.id] === 'adding'"
                  @click.stop="onActionClick(item, 'add')"
                >
                  <VIcon :icon="item.best_supplier?.is_ai_matched ? 'tabler-brain' : 'tabler-shopping-cart-plus'" size="18" />
                  <VTooltip activator="parent" location="top">
                    {{ item.best_supplier?.is_ai_matched ? 'Coincidencia sugerida por IA' : 'Añadir a Orden' }}
                  </VTooltip>
                </VBtn>
              </template>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div v-else class="d-block d-md-none pa-2">
        <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
        
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
                      <span v-if="props.withSuppliers && item.best_supplier" class="text-warning font-weight-bold">- {{ item.best_supplier?.name }}</span>
                      <span>|</span>
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
                    <!-- Acciones Móvil -->
                    <div class="d-flex ga-1 justify-end mt-2">
                       <div v-if="item.ia_matching_in_progress" class="d-flex align-center ga-1">
                         <VProgressCircular indeterminate size="14" width="2" color="info" />
                         <span class="text-xs font-weight-bold text-info">Buscando Proveedor...</span>
                       </div>
                       <template v-else>
                         <VBtn v-if="!isRestaurant" icon variant="tonal" color="error" size="32" :loading="isProcessing[item.id] === 'ignoring'" @click.stop="onActionClick(item, 'ignore')">
                           <VIcon icon="tabler-trash-x" size="18" />
                         </VBtn>
                         <!-- Rechazar match IA en móvil -->
                         <VBtn v-if="item.best_supplier?.is_ai_matched" icon variant="tonal" color="warning" size="32" @click.stop="rejectAiMatch(item)">
                           <VIcon icon="tabler-brain-off" size="18" />
                         </VBtn>
                          <VBtn icon variant="tonal" :color="item.best_supplier?.is_ai_matched ? 'info' : 'success'" size="32" :loading="isProcessing[item.id] === 'adding'" @click.stop="onActionClick(item, 'add')">
                            <VIcon :icon="item.best_supplier?.is_ai_matched ? 'tabler-brain' : 'tabler-shopping-cart-plus'" size="18" />
                         </VBtn>
                       </template>
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
                  <div v-if="props.withSuppliers" class="info-item">
                    <span class="label">Prov</span>
                    <span class="value text-warning">${{ Number(item.best_supplier_price || 0).toFixed(2) }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Prom.</span>
                    <span class="value">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '—' }}</span>
                  </div>
                </div>
              </div>
            </VCard>
          </div>

          <!-- Paginación Móvil -->
          <div class="d-flex justify-center mt-4">
             <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            :items-per-page-options="[10, 25, 50]"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
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
  font-size: 0.8125rem !important;
}

:deep(.v-data-table__wrapper) {
  overflow: visible !important;
}

:deep(.v-data-table__td),
:deep(.v-data-table__th) {
  padding-inline: 4px !important;
}

:deep(.row-needs td) {
  background-color: rgba(40, 199, 111, 4%) !important;
}

:deep(.row-excess td) {
  background-color: rgba(234, 84, 85, 4%) !important;
}

:deep(.centered-input-text-sm .v-field__input) {
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  min-block-size: 32px !important;
  padding-block: 4px !important;
  text-align: center !important;
}

/* Estilos para cards móviles */
.row-needs.v-card {
  background-color: rgba(40, 199, 111, 2%) !important;
  border-inline-start: 4px solid #28c76f !important;
}

.row-excess.v-card {
  background-color: rgba(234, 84, 85, 2%) !important;
  border-inline-start: 4px solid #ea5455 !important;
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

.hover-opacity:hover {
  opacity: 0.7;
  text-decoration: underline;
}

.chart-placeholder {
  animation: shimmer 1.5s infinite;
  background: linear-gradient(90deg, rgba(var(--v-border-color), 0.05) 25%, rgba(var(--v-border-color), 0.1) 50%, rgba(var(--v-border-color), 0.05) 75%);
  background-size: 200% 100%;
  block-size: 25px;
  inline-size: 100%;
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

.legend-dot {
  display: inline-block;
  border-radius: 50%;
  block-size: 10px;
  inline-size: 10px;
}
.legend-needs { background: rgba(40, 199, 111, 40%); }
.legend-excess { background: rgba(234, 84, 85, 40%); }
</style>

