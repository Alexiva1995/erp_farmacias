<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import TovaAuditModal from "@/components/TovaAuditModal.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useDisplay } from 'vuetify';
import { useDebounceFn } from '@vueuse/core';
import { ref, computed, watch } from 'vue';
import axios from "@/plugins/axios";
import Swal from 'sweetalert2';
import { toast } from "@/plugins/sweetalert";
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";

const brandingStore = useBrandingStore();
const { mdAndUp } = useDisplay();
const isRestaurant = computed(() => false);

const props = defineProps({
  grupos: { type: Array, required: true },         // Array de { group_id, group_name, productos }
  totalGrupos: { type: Number, required: true },   // Total de grupos (para paginación)
  perPage: { type: Number, default: 25 },
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  loading: { type: Boolean, default: false },
  showGraphs: { type: Boolean, default: false },
  withSuppliers: { type: Boolean, default: false },
  selectedSupplierId: { type: [Number, String], default: null },
});

const emit = defineEmits(['page-change', 'product-scarce-toggled', 'open-comparator', 'remove-item', 'reject-ai-match']);

// Grupo expandido (uno a la vez)
const expandedGroupId = ref(null);

const auditModalOpen = ref(false);
const selectedAuditItem = ref(null);

const openAuditModal = (item, grupo = null) => {
  selectedAuditItem.value = {
    ...item,
    group_name: item.group_name || grupo?.group_name || null,
    group_id: item.group_id || grupo?.group_id || null,
  };
  auditModalOpen.value = true;
};

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
    await axios.post(`/suppliers-ia-order-assistant/products-without-supplier/${product.id}/toggle-scarce`);
    emit('product-scarce-toggled', product.id);
    toast.success("Estado de escasez actualizado.");
  } catch (error) {
    console.error("Error toggling scarce:", error);
    toast.error("Error al cambiar estado de escasez.");
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
    await axios.post(`/suppliers-ia-order-assistant/products/${productId}/update-manual-quantity`, {
      quantity: quantity
    });
  } catch (error) {
    console.error("Error persisting manual quantity:", error);
    toast.error("Error al guardar la cantidad manual.");
  }
}, 800);

// Rechazar un match sugerido por IA para que el sistema aprenda
const rejectAiMatch = async (item) => {
  const psId = item.best_supplier?.product_suppliers_id || item.best_supplier?.product_supplier_id || item.best_supplier?.id;
  if (!item.best_supplier?.is_ai_matched || !psId) return;
  try {
    await axios.post('/supplier-ai-match/reject', {
      product_id:          item.id,
      product_supplier_id: psId,
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
      emit('open-comparator', { item, quantity });
      return;
    }

    if (item.best_supplier?.is_ai_matched) {
      const { isConfirmed } = await Swal.fire({
        title: "Coincidencia por IA",
        html: `Por IA se sugiere que este producto corresponde a:<br><strong>${item.best_supplier.matched_name || item.best_supplier.name}</strong> del proveedor.<br><br>¿Deseas confirmar esta coincidencia y agregarlo a la orden?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, agregar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#28c76f",
        cancelButtonColor: "#ea5455",
      });
      if (!isConfirmed) return;
    }

    isProcessing.value[item.id] = 'adding';
    try {
      const payload = {
        product_id: item.id,
        quantity: quantity,
        product_supplier_id: item.best_supplier?.product_suppliers_id || item.best_supplier?.product_supplier_id || item.best_supplier?.id || null,
        supplier_id: props.selectedSupplierId || item.best_supplier?.supplier_id || null,
      };
      await axios.post('/suppliers-ia-order-assistant/add-to-order', payload);
      toast.success("Producto agregado a la orden");
      emit('remove-item', item.id);
    } catch (e) {
      console.error(e);
      toast.error(e.response?.data?.message || "Error al agregar a la orden");
    } finally {
      isProcessing.value[item.id] = null;
    }
  } else if (action === 'ignore') {
    isProcessing.value[item.id] = 'ignoring';
    try {
      await axios.post(`/suppliers-ia-order-assistant/products/${item.id}/ignore`);
      toast.success("Producto ignorado por 7 días");
      emit('remove-item', item.id);
    } catch (e) {
      console.error(e);
      toast.error(e.response?.data?.message || "Error al ignorar");
    } finally {
      isProcessing.value[item.id] = null;
    }
  }
};

// Gráficas Lazy
const readyCharts = ref(new Set());
const markChartAsReady = (id) => {
  if (!readyCharts.value.has(id)) {
    readyCharts.value.add(id);
  }
};

const getChartOptions = (item, color) => ({
  chart: {
    type: 'area',
    sparkline: { enabled: true },
    animations: { enabled: false }
  },
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.45,
      opacityTo: 0.05,
      stops: [0, 100]
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
    if (v > 0) falta++;
    else if (v < 0) exceso++;
    else ok++;
  });
  return { falta, exceso, ok };
};

// Copiar JSON de auditoría
const copiarJsonAuditoria = async (grupo) => {
  try {
    const payload = {
      group_id: grupo.group_id,
      group_name: grupo.group_name || `Grupo #${grupo.group_id}`,
      lead_time_days: 7,
      buffer_days: 7,
      products: grupo.productos.map(p => ({
        product_id: p.id,
        name: p.name,
        tier_level: p.tier_level || p.liga_id || 1,
        tier_name: p.tier_name || p.liga_nombre || 'ECONÓMICA',
        costo: parseFloat(p.unit_cost || p.cost_price || 0),
        ventas_30d: parseFloat(p.ventas_30d || 0),
        ventas_m2: parseFloat(p.ventas_m2 || 0),
        ventas_m3: parseFloat(p.ventas_m3 || 0),
        dias_con_stock_m1: parseFloat(p.dias_con_stock_m1 || 0),
        dias_con_stock_m2: parseFloat(p.dias_con_stock_m2 || 0),
        dias_con_stock_m3: parseFloat(p.dias_con_stock_m3 || 0),
        promedio_historico: parseFloat(p.promedio_historico || p.promedio_calculado || 0),
        stock_fisico: parseFloat(p.stock_fisico || p.stock || 0),
        stock_transito: parseFloat(p.stock_transito || p.totalQuantityInAutoOrder || 0),
      }))
    };

    if (grupo.productos.length > 0 && grupo.productos[0].lead_time_days) {
      payload.lead_time_days = grupo.productos[0].lead_time_days;
      payload.buffer_days = grupo.productos[0].buffer_days;
    }

    await navigator.clipboard.writeText(JSON.stringify(payload, null, 2));
    toast.success('JSON de auditoría copiado al portapapeles');
  } catch (err) {
    console.error(err);
    toast.error('Error al copiar el JSON');
  }
};


// Resumen por Ligas (Económica, Premium, Promedio)
const getLigasSummary = (productos) => {
  const tiers = [
    { id: 1, name: 'ECONÓMICA', color: '#ea5455', chipColor: 'pink' },
    { id: 3, name: 'PREMIUM', color: '#28c76f', chipColor: 'success' },
    { id: 2, name: 'PROMEDIO', color: '#00cfe8', chipColor: 'info' },
  ];

  return tiers.map(tier => {
    const prods = productos.filter(p => {
      if (p.liga_id) return Number(p.liga_id) === tier.id;
      const ligaNorm = String(p.tier_name || p.liga_nombre || '').toLowerCase();
      if (tier.id === 1) return ligaNorm.includes('econ');
      if (tier.id === 2) return ligaNorm.includes('prom');
      if (tier.id === 3) return ligaNorm.includes('prem');
      return false;
    });

    if (!prods.length) return null;

    const demandaTotal = prods.reduce((acc, p) => acc + parseFloat(p.demanda_ponderada ?? p.promedio_calculado ?? 0), 0);
    const ropTotal = prods.reduce((acc, p) => acc + parseFloat(p.rop_calculado ?? p.rop ?? 0), 0);
    const stockUtil = prods.reduce((acc, p) => {
      const util = p.stock_util !== undefined 
        ? parseFloat(p.stock_util) 
        : Math.min(parseFloat(p.lote_quantity ?? p.stock ?? 0), Math.ceil(parseFloat(p.demanda_ponderada ?? p.promedio_calculado ?? 0)));
      return acc + util;
    }, 0);
    const ventas30d = prods.reduce((acc, p) => acc + parseFloat(p.total_sold_completed ?? 0), 0);
    
    const validCosts = prods.map(p => parseFloat(p.unit_cost ?? 0)).filter(c => c > 0);
    const costoProm = validCosts.length > 0 ? (validCosts.reduce((a, b) => a + b, 0) / validCosts.length) : 0;
    
    const faltante = Math.max(0, ropTotal - stockUtil);

    return {
      ...tier,
      count: prods.length,
      demandaTotal: demandaTotal.toFixed(1),
      ropTotal: ropTotal.toFixed(1),
      stockUtil: stockUtil.toFixed(1),
      ventas30d: Math.round(ventas30d),
      costoProm: costoProm.toFixed(2),
      faltante: faltante.toFixed(1),
      isCovered: faltante <= 0
    };
  }).filter(Boolean);
};

// Headers para la tabla interna en desktop
const innerHeaders = computed(() => {
  const base = [
    { title: "ID", key: "id", sortable: true, width: '70px' },
    { title: "PRODUCTO", key: "name", sortable: true, minWidth: '220px' },
  ];

  if (props.showGraphs) {
    base.push({ title: "TREND", key: "trend", sortable: false, width: '80px' });
  }

  base.push(
    { title: "COSTO", key: "unit_cost", sortable: true, align: 'end', width: '75px' },
    { title: "VENT.", key: "total_sold_completed", sortable: true, align: 'center', width: '70px' },
    { title: "Q STO.", key: "dias_quiebre", sortable: true, align: 'center', width: '70px' },
    { title: "DEMANDA", key: "promedio_calculado", sortable: true, align: 'center', width: '75px' },
    { title: "ROP", key: "rop_calculado", sortable: true, align: 'center', width: '70px' },
    { title: "FÍSICO", key: "lote_quantity", sortable: true, align: 'center', width: '70px' },
    { title: "TRÁNS.", key: "totalQuantityInAutoOrder", sortable: true, align: 'center', width: '70px' },
    { title: "IPO", key: "ipo", sortable: true, align: 'center', width: '65px' },
    { title: "SUG.", key: "solicitar", sortable: true, align: 'center', width: '75px' },
    { title: "ACCIÓN", key: "actions", sortable: false, align: 'end', width: '80px' }
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

    <!-- Cargador de carga limpio -->
    <div v-else-if="loading" class="pa-12 text-center bg-white rounded-lg">
      <VProgressCircular indeterminate color="primary" size="38" class="mb-3" />
      <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando grupos de sugerencias...</div>
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
            <VBtn
              size="x-small"
              variant="text"
              color="primary"
              icon="tabler-copy"
              @click="copiarJsonAuditoria(grupo)"
              title="Copiar JSON de Auditoría"
            ></VBtn>
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

              <!-- PRODUCTO -->
              <template #item.name="{ item }">
                <div class="d-flex flex-column py-1">
                  <div class="d-flex align-center gap-1">
                    <span
                      class="text-sm font-weight-black text-high-emphasis text-uppercase text-wrap cursor-pointer hover-opacity"
                      :class="{ 'text-primary': item.psychotropic == 1, 'opacity-50': togglingScarce === item.id }"
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
                    <span class="text-primary font-weight-black text-uppercase">
                      {{ item.laboratory?.name || 'S/L' }}
                      <span v-if="item.best_supplier && props.withSuppliers" class="text-warning ml-1">- {{ item.best_supplier?.name }}</span>
                    </span>
                    <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" label class="ml-1 text-super-xs px-1">COL</VChip>
                    <VChip
                      v-if="item.liga_nombre || item.tier_name"
                      :color="String(item.tier_name || item.liga_nombre || '').toLowerCase().includes('econ') ? 'pink' : (item.liga_color || (String(item.tier_name || item.liga_nombre || '').toLowerCase().includes('prem') ? 'success' : 'info'))"
                      size="x-small"
                      variant="tonal"
                      class="ml-1 font-weight-black text-uppercase"
                      style="font-size: 10px; height: 18px; padding: 0 6px;"
                      label
                    >
                      {{ item.tier_name || item.liga_nombre }}
                    </VChip>
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

              <!-- Gráfica si está activa -->
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

              <!-- COSTO -->
              <template #item.unit_cost="{ item }">
                <div class="d-flex flex-column align-end">
                  <span class="font-weight-medium">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
                  <div v-if="props.withSuppliers && item.best_supplier && Number(item.best_supplier_price) > 0" class="d-flex align-center" style="line-height: 1;">
                    <span class="font-weight-black text-warning" style="font-size: 11px;">
                      ${{ Number(item.best_supplier_price || 0).toFixed(2) }}
                    </span>
                    <span v-if="item.best_supplier_percentage && !isNaN(item.best_supplier_percentage) && item.best_supplier_percentage !== 0" class="ms-1 font-weight-bold" style="font-size: 9px;" :class="item.best_supplier_percentage < 0 ? 'text-success' : 'text-error'">
                      ({{ item.best_supplier_percentage < 0 ? '↓' : '↑' }}{{ Math.abs(item.best_supplier_percentage).toFixed(0) }}%)
                    </span>
                  </div>
                </div>
              </template>

              <!-- VENTA 30D -->
              <template #item.total_sold_completed="{ item }">
                <span class="font-weight-bold">{{ item.total_sold_completed ? Math.round(item.total_sold_completed) : 0 }}</span>
              </template>

              <!-- QUIEBRE (90D) -->
              <template #item.dias_quiebre="{ item }">
                <span
                  class="font-weight-bold"
                  :class="Number(item.dias_quiebre || 0) > 30 ? 'text-error font-weight-black' : (Number(item.dias_quiebre || 0) >= 11 ? 'text-warning font-weight-bold' : (Number(item.dias_quiebre || 0) >= 1 ? 'text-medium-emphasis font-weight-medium' : 'text-disabled'))"
                >
                  {{ item.dias_quiebre !== undefined ? Math.round(item.dias_quiebre) + 'd' : '0d' }}
                </span>
              </template>

              <!-- DEMANDA (30D) -->
              <template #item.promedio_calculado="{ item }">
                <span class="font-weight-medium text-medium-emphasis">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '0.0' }}</span>
              </template>

              <!-- ROP REAL (14D / 21D) -->
              <template #item.rop_calculado="{ item }">
                <span class="font-weight-bold text-primary font-mono">{{ (item.rop_calculado !== undefined ? parseFloat(item.rop_calculado) : (parseFloat(item.rop ?? 0))).toFixed(1) }}</span>
              </template>

              <!-- STOCK FÍSICO -->
              <template #item.lote_quantity="{ item }">
                <span class="font-weight-bold" :class="Number(item.lote_quantity ?? item.stock) <= 0 ? 'text-error' : ''">
                  {{ (item.lote_quantity ?? item.stock) ? Math.round(item.lote_quantity ?? item.stock) : 0 }}
                </span>
              </template>

              <!-- TRÁNSITO -->
              <template #item.totalQuantityInAutoOrder="{ item }">
                <VChip v-if="Number(item.totalQuantityInAutoOrder) > 0" color="info" size="x-small" variant="tonal" class="font-weight-black">
                  {{ item.totalQuantityInAutoOrder }}
                </VChip>
                <span v-else class="text-disabled">0</span>
              </template>


              <!-- IPO % (Semáforo de Posición de Inventario) -->
              <template #item.ipo="{ item }">
                <VChip
                  size="x-small"
                  variant="tonal"
                  class="font-weight-bold"
                  :color="parseFloat(item.ipo ?? item.preferencia_product ?? 0) < 25 ? 'error' : (parseFloat(item.ipo ?? item.preferencia_product ?? 0) <= 60 ? 'warning' : 'success')"
                >
                  {{ item.ipo ? item.ipo + '%' : (item.preferencia_product ? Math.round(item.preferencia_product) + '%' : (item.liga_id ? '100%' : '—')) }}
                </VChip>
              </template>

              <!-- SUGERIDO FINAL -->
              <template #item.solicitar="{ item }">
                <div class="d-flex align-center justify-center">
                  <VTextField
                    :model-value="getInputValue(item)"
                    @update:model-value="(val) => updateInputValue(item, val)"
                    type="number"
                    density="compact"
                    hide-details
                    variant="outlined"
                    class="centered-input-text-super-xs"
                    :class="{
                      'input-dirty-highlight': (item.id in editedValues) || (item.manual_solicitar !== null && item.manual_solicitar !== undefined),
                      'text-success font-weight-black': roundIaAnalysis(item.solicitar) > 0,
                      'text-error': roundIaAnalysis(item.solicitar) < 0
                    }"
                    style="max-inline-size: 75px;"
                    @click.stop
                  />
                </div>
              </template>

              <!-- ACCIÓN -->
              <template #item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                  <!-- Indicador de Matching en Progreso -->
                  <div v-if="item.ia_matching_in_progress" class="d-flex align-center ga-1 pr-2">
                    <VProgressCircular indeterminate size="16" width="2" color="info" />
                    <span class="text-xs font-weight-black text-info text-uppercase">Buscando Proveedor...</span>
                  </div>
                  <template v-else>
                    <VBtn
                      variant="tonal"
                      color="primary"
                      size="30"
                      icon
                      @click.stop="openAuditModal(item, grupo)"
                    >
                      <VIcon size="18">tabler-terminal-2</VIcon>
                      <VTooltip activator="parent" location="top">Ver Log de Decisión</VTooltip>
                    </VBtn>
                    <VBtn
                      v-if="!isRestaurant"
                      variant="tonal"
                      color="error"
                      size="30"
                      icon
                      :loading="isProcessing[item.id] === 'ignoring'"
                      @click.stop="onActionClick(item, 'ignore')"
                    >
                      <VIcon size="18">tabler-trash-x</VIcon>
                      <VTooltip activator="parent" location="top">Rechazar / Ignorar</VTooltip>
                    </VBtn>
                     <!-- Botón rechazar match IA: solo si fue sugerido por IA -->
                     <VBtn
                       v-if="item.best_supplier?.is_ai_matched"
                       variant="tonal"
                       color="warning"
                       size="30"
                       icon
                       @click.stop="rejectAiMatch(item)"
                     >
                       <VIcon size="18">tabler-brain-off</VIcon>
                       <VTooltip activator="parent" location="top">Rechazar sugerencia de IA</VTooltip>
                     </VBtn>
                     <VBtn
                      variant="tonal"
                      :color="item.best_supplier?.is_ai_matched ? 'info' : 'success'"
                      size="30"
                      icon
                      :loading="isProcessing[item.id] === 'adding'"
                      @click.stop="onActionClick(item, 'add')"
                    >
                      <VIcon size="18">{{ item.best_supplier?.is_ai_matched ? 'tabler-brain' : 'tabler-shopping-cart-plus' }}</VIcon>
                      <VTooltip activator="parent" location="top">
                        {{ item.best_supplier?.is_ai_matched ? 'Coincidencia sugerida por IA' : 'Añadir a Orden' }}
                      </VTooltip>
                    </VBtn>
                  </template>
                </div>
              </template>
            </VDataTable>
          </div>

          <!-- Vista Móvil (Cards) -->
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
                        {{ item.name }}
                      </span>
                    </VCardTitle>
                    <template #append>
                      <VChip
                        v-if="item.liga_nombre || item.tier_name"
                        :color="String(item.tier_name || item.liga_nombre || '').toLowerCase().includes('econ') ? 'pink' : (item.liga_color || (String(item.tier_name || item.liga_nombre || '').toLowerCase().includes('prem') ? 'success' : 'info'))"
                        size="x-small"
                        variant="tonal"
                        class="font-weight-black text-uppercase"
                        label
                      >
                        {{ item.tier_name || item.liga_nombre }}
                      </VChip>
                    </template>
                  </VCardItem>

                  <VCardText class="pb-2 pt-0">
                    <div class="d-flex justify-space-between align-center text-xs my-1">
                      <span class="text-disabled">Costo:</span>
                      <div class="d-flex flex-column align-end">
                        <span class="font-weight-bold">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
                        <div v-if="props.withSuppliers && item.best_supplier && Number(item.best_supplier_price) > 0" class="d-flex align-center" style="line-height: 1;">
                          <span class="font-weight-black text-warning" style="font-size: 10px;">
                            ${{ Number(item.best_supplier_price || 0).toFixed(2) }}
                          </span>
                          <span v-if="item.best_supplier_percentage && !isNaN(item.best_supplier_percentage) && item.best_supplier_percentage !== 0" class="ms-1 font-weight-bold" style="font-size: 9px;" :class="item.best_supplier_percentage < 0 ? 'text-success' : 'text-error'">
                            ({{ item.best_supplier_percentage < 0 ? '↓' : '↑' }}{{ Math.abs(item.best_supplier_percentage).toFixed(0) }}%)
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="d-flex justify-space-between text-xs my-1">
                      <span class="text-disabled">Vent. (30d):</span>
                      <span class="font-weight-bold">{{ item.total_sold_completed ? Math.round(item.total_sold_completed) : 0 }}</span>
                    </div>
                    <div class="d-flex justify-space-between text-xs my-1">
                      <span class="text-disabled">Q Sto. (90d):</span>
                      <span
                        class="font-weight-bold"
                        :class="Number(item.dias_quiebre || 0) > 30 ? 'text-error font-weight-black' : (Number(item.dias_quiebre || 0) >= 11 ? 'text-warning font-weight-bold' : (Number(item.dias_quiebre || 0) >= 1 ? 'text-medium-emphasis font-weight-medium' : 'text-disabled'))"
                      >
                        {{ item.dias_quiebre !== undefined ? Math.round(item.dias_quiebre) + 'd' : '0d' }}
                      </span>
                    </div>
                    <div class="d-flex justify-space-between text-xs my-1">
                      <span class="text-disabled">Demanda (30d):</span>
                      <span class="font-weight-medium text-medium-emphasis">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '0.0' }}</span>
                    </div>
                    <div class="d-flex justify-space-between text-xs my-1">
                      <span class="text-disabled">ROP:</span>
                      <span class="font-weight-bold text-primary font-mono">{{ (item.rop_calculado !== undefined ? parseFloat(item.rop_calculado) : (parseFloat(item.rop ?? 0))).toFixed(1) }}</span>
                    </div>
                    <div class="d-flex justify-space-between text-xs my-1">
                      <span class="text-disabled">Stock Físico:</span>
                      <span class="font-weight-bold" :class="Number(item.lote_quantity ?? item.stock) <= 0 ? 'text-error' : ''">
                        {{ (item.lote_quantity ?? item.stock) ? Math.round(item.lote_quantity ?? item.stock) : 0 }}
                      </span>
                    </div>
                    <div class="d-flex justify-space-between text-xs my-1">
                      <span class="text-disabled">IPO:</span>
                      <VChip
                        size="x-small"
                        variant="tonal"
                        class="font-weight-bold"
                        :color="parseFloat(item.ipo ?? item.preferencia_product ?? 0) < 25 ? 'error' : (parseFloat(item.ipo ?? item.preferencia_product ?? 0) <= 60 ? 'warning' : 'success')"
                      >
                        {{ item.ipo ? item.ipo + '%' : (item.preferencia_product ? Math.round(item.preferencia_product) + '%' : (item.liga_id ? '100%' : '—')) }}
                      </VChip>
                    </div>
                  </VCardText>

                  <VDivider />

                  <VCardActions class="pa-2 d-flex justify-space-between align-center">
                    <div class="d-flex align-center gap-1">
                      <span class="text-super-xs font-weight-bold text-disabled">Sugerido:</span>
                      <VTextField
                        :model-value="getInputValue(item)"
                        @update:model-value="(val) => updateInputValue(item, val)"
                        type="number"
                        density="compact"
                        hide-details
                        variant="outlined"
                        class="centered-input-text-super-xs"
                        :class="{
                          'input-dirty-highlight': (item.id in editedValues) || (item.manual_solicitar !== null && item.manual_solicitar !== undefined)
                        }"
                        style="max-inline-size: 70px;"
                        @click.stop
                      />
                    </div>
                    <div class="d-flex ga-1">
                      <VBtn
                        variant="tonal"
                        color="primary"
                        size="28"
                        icon
                        @click.stop="openAuditModal(item, grupo)"
                      >
                        <VIcon size="16">tabler-terminal-2</VIcon>
                      </VBtn>
                      <VBtn
                        v-if="!isRestaurant"
                        variant="tonal"
                        color="error"
                        size="28"
                        icon
                        :loading="isProcessing[item.id] === 'ignoring'"
                        @click.stop="onActionClick(item, 'ignore')"
                      >
                        <VIcon size="16">tabler-trash-x</VIcon>
                      </VBtn>
                      <VBtn
                        variant="tonal"
                        color="success"
                        size="28"
                        icon
                        :loading="isProcessing[item.id] === 'adding'"
                        @click.stop="onActionClick(item, 'add')"
                      >
                        <VIcon size="16">tabler-shopping-cart-plus</VIcon>
                      </VBtn>
                    </div>
                  </VCardActions>
                </VCard>
              </VCol>
            </VRow>
          </div>

          <!-- Tarjetas de Resumen por Liga (Hiperplus) -->
          <div class="d-flex flex-wrap ga-3 mt-4">
            <VCard
              v-for="liga in getLigasSummary(grupo.productos)"
              :key="liga.id"
              variant="outlined"
              class="flex-1-1 pa-3 pa-sm-4 rounded-lg border elevation-1"
              :style="{ borderColor: liga.color + '55', backgroundColor: 'rgba(var(--v-theme-surface), 0.95)' }"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <VChip :color="liga.chipColor" size="small" variant="tonal" class="font-weight-black text-uppercase px-2" label>
                  {{ liga.name }} ({{ liga.count }})
                </VChip>
                <div class="text-xs font-weight-bold" :class="liga.isCovered ? 'text-success' : 'text-warning'">
                  Faltante ROP: <span class="font-weight-black font-mono">{{ liga.faltante }}</span>
                  <span v-if="liga.isCovered" class="text-super-xs font-weight-normal ms-1">(Cubierta)</span>
                </div>
              </div>
              <VDivider class="my-2 opacity-20" />
              <div class="d-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(115px, 1fr)); gap: 8px; font-size: 11px;">
                <div>
                  <span class="text-disabled">Demanda (30d):</span>
                  <span class="font-weight-black text-high-emphasis ms-1 font-mono">{{ liga.demandaTotal }}</span>
                </div>
                <div>
                  <span class="text-disabled">ROP (14d):</span>
                  <span class="font-weight-black text-primary ms-1 font-mono">{{ liga.ropTotal }}</span>
                </div>
                <div>
                  <span class="text-disabled">Stock Útil:</span>
                  <span class="font-weight-black text-high-emphasis ms-1 font-mono">{{ liga.stockUtil }}</span>
                </div>
                <div>
                  <span class="text-disabled">Ventas (30d):</span>
                  <span class="font-weight-black text-high-emphasis ms-1 font-mono">{{ liga.ventas30d }}</span>
                </div>
                <div>
                  <span class="text-disabled">Costo Prom.:</span>
                  <span class="font-weight-black text-success ms-1 font-mono">${{ liga.costoProm }}</span>
                </div>
              </div>
            </VCard>
          </div>

        </div>
      </div>
    </div>

    <!-- Paginación Footer Desktop & Móvil -->
    <div v-if="grupos.length > 0" class="d-flex flex-column flex-sm-row align-center justify-space-between pa-4 border-t gap-4">
      <div class="d-flex align-center flex-wrap gap-4">
        <span class="text-xs text-disabled font-weight-bold text-uppercase">
          Mostrando {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, totalGrupos) }} de {{ totalGrupos }} grupos
        </span>
        <div class="d-flex align-center gap-2">
          <span class="text-xs text-disabled">Filas:</span>
          <VSelect
            :model-value="perPage"
            :items="[10, 25, 50, 100]"
            density="compact"
            variant="outlined"
            hide-details
            style="max-inline-size: 85px;"
            @update:model-value="(val) => emit('page-change', { page: 1, itemsPerPage: Number(val) })"
          />
        </div>
      </div>

      <VPagination
        :model-value="currentPage"
        :length="lastPage"
        :total-visible="$vuetify.display.xs ? 3 : 5"
        density="compact"
        @update:model-value="(val) => emit('page-change', { page: Number(val), itemsPerPage: perPage })"
      />
    </div>

    <TovaAuditModal v-model="auditModalOpen" :item="selectedAuditItem" />
  </VCard>
</template>

<style scoped>
.inner-products-table :deep(th) {
  font-size: 11px !important;
  font-weight: 800 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.inner-products-table :deep(td) {
  font-size: 12px !important;
  padding-top: 6px !important;
  padding-bottom: 6px !important;
}

.inner-products-table :deep(tr.row-needs td:first-child) {
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}
.inner-products-table :deep(tr.row-needs td:last-child) {
  border-inline-end: 4px solid rgb(var(--v-theme-success)) !important;
}

.inner-products-table :deep(tr.row-excess td:first-child) {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}
.inner-products-table :deep(tr.row-excess td:last-child) {
  border-inline-end: 4px solid rgb(var(--v-theme-error)) !important;
}

.card-needs {
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}

.card-excess {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}

.grupo-header--expanded {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.rotate-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.centered-input-text-super-xs :deep(input) {
  text-align: center;
  font-size: 11px !important;
  font-weight: 800 !important;
  padding: 4px 6px !important;
}

.input-dirty-highlight :deep(.v-field__outline) {
  --v-field-border-opacity: 0.8 !important;
  border-color: rgba(var(--v-theme-warning), 0.8) !important;
}

.input-dirty-highlight :deep(.v-field) {
  background-color: rgba(var(--v-theme-warning), 0.08) !important;
}
</style>
