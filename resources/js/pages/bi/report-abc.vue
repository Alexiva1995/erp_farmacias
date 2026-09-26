<script setup>
import { ref, reactive, watch, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { formatCurrency } from '@/utils/currencyFormatter';
import AbcReportFilters from './components/AbcReportFilters.vue';
import AbcReportKpiCards from './components/AbcReportKpiCards.vue';
import AbcReportMobileView from './components/AbcReportMobileView.vue';
import AbcParetoChart from './components/AbcParetoChart.vue';
import AbcDecisionMatrix from './components/AbcDecisionMatrix.vue';
import IndividualCreateOffer from '@/components/dialogs/IndividualOfferModal.vue';
import AssignProductToEmployeesDialog from './components/AssignProductToEmployeesDialog.vue';

const router = useRouter();
const loading = ref(false);
const exporting = ref(false);
const navigatingAssistant = ref(false);
const errorMessage = ref(null);
const items = ref([]);
const totalItems = ref(0);

// Filtros
const selectedDateRange = ref('30 days');
const selectedLaboratories = ref([]);
const selectedLaboratoryGroups = ref([]);
const selectedFinalClassification = ref(null);
const selectedAnalysisType = ref('all');
const minGmroi = ref(null);
const stockFilter = ref('all');
const search = ref('');
const isAdvancedFiltersVisible = ref(false);

// Vistas y Herramientas Analíticas
const showParetoChart = ref(true);
const showDecisionMatrix = ref(false);
const selectedQuadrant = ref(null);
const paretoCurve = ref([]);
const paretoInflection = ref({
  point_80: { count: 0, sku_pct: 0, sales_pct: 0 },
  point_95: { count: 0, sku_pct: 0, sales_pct: 0 },
});
const matrix3x3Data = ref({});

// Paginación y Ordenamiento
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'total_sales', order: 'desc' }]);

// Diálogos de Acciones Rápidas
const isOfferDialogVisible = ref(false);
const isEditingOffer = ref(false);
const offerLoading = ref(false);
const currentOfferToEdit = ref(null);
const currentIndvOffer = reactive({
  id: null,
  product_id: null,
  discount_percent: '',
  start_date: '',
  end_date: '',
});
const offerErrors = reactive({
  id: '',
  product_id: '',
  discount_percent: '',
  start_date: '',
  end_date: '',
});

const isAssignEmployeesDialogVisible = ref(false);
const selectedProductForAssign = ref(null);
const selectedProductForOffer = ref(null);

const handleOpenIndividualOffer = (item) => {
  selectedProductForOffer.value = {
    id: item.id,
    name: item.name || item.product_name,
    active_ingredient: item.active_ingredient,
    stock: item.current_stock,
    sale_price: item.sale_price ?? (item.total_sales > 0 && item.sold_units > 0 ? (item.total_sales / item.sold_units) : item.last_cost),
    last_cost: item.last_cost,
    barcode: item.barcode,
    laboratory: { name: item.laboratory_name },
  };

  Object.assign(currentIndvOffer, {
    id: null,
    product_id: item.id,
    discount_percent: item.individual_offer_discount || '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
  });
  Object.keys(offerErrors).forEach((key) => (offerErrors[key] = ''));
  isEditingOffer.value = false;
  currentOfferToEdit.value = null;
  isOfferDialogVisible.value = true;
};

const handleSaveIndividualOffer = async (payload) => {
  Object.keys(offerErrors).forEach((key) => (offerErrors[key] = ''));
  offerLoading.value = true;
  try {
    let response;
    if (currentIndvOffer.id) {
      response = await axios.put(`/tpv/promotions/individual/${payload.id}`, payload);
    } else {
      response = await axios.post('/tpv/promotions/individual', payload);
    }
    if (response.status === 200 || response.status === 201) {
      toast.success('Oferta guardada exitosamente.');
      isOfferDialogVisible.value = false;
      fetchReport();
    }
  } catch (error) {
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      Object.keys(errors).forEach((key) => {
        if (offerErrors.hasOwnProperty(key)) {
          offerErrors[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
        }
      });
      toast.error('Por favor revise los errores en el formulario de la oferta.');
    } else {
      toast.error(error.response?.data?.message || 'Error al guardar la oferta.');
    }
  } finally {
    offerLoading.value = false;
  }
};

const handleOpenAssignEmployees = (item) => {
  selectedProductForAssign.value = item;
  isAssignEmployeesDialogVisible.value = true;
};

const handleAssignedEmployees = () => {
  // Asignación completada
};

const handleSuggestIaOrder = (item) => {
  const productName = item.name || item.product_name || `Producto #${item.id}`;
  toast.info(`Transfiriendo ${productName} al Asistente IA de Pedidos...`);
  router.push({
    name: 'suppliers-supplieriaorderassistant',
    query: {
      stock: 'fallas',
      source: 'abc_row_action',
      product_ids: item.id.toString(),
      auto_match: 'true',
    },
  });
};

const handleSelectQuadrant = (code) => {
  selectedQuadrant.value = code;
  selectedFinalClassification.value = code;
};

const handleClearQuadrant = () => {
  selectedQuadrant.value = null;
  selectedFinalClassification.value = null;
};

const handleFilterClass = (classLetter) => {
  selectedFinalClassification.value = classLetter;
};

// Catálogos y Estadísticas
const laboratories = ref([]);
const laboratoryGroups = ref([]);
const summaryStats = ref({
  total_volume: 0,
  aax_products: 0,
  avg_margin: 0,
  frozen_capital: 0,
  count_a: 0,
  count_b: 0,
  count_c: 0,
  critical_stockouts: 0,
  total_products: 0,
});

const getDateRange = (rangeType) => {
  const end = new Date();
  const start = new Date();
  
  if (rangeType === '30 days') {
    start.setDate(end.getDate() - 30);
  } else if (rangeType === '90 days') {
    start.setDate(end.getDate() - 90);
  } else if (rangeType === '12 months') {
    start.setMonth(end.getMonth() - 12);
  }

  return {
    start_date: start.toISOString().split('T')[0],
    end_date: end.toISOString().split('T')[0],
  };
};

const isSimplifiedView = ref(false);

const fullHeaders = [
  { title: 'PRODUCTO / LABORATORIO', key: 'name', sortable: true },
  { title: 'Desempeño Comercial', key: 'sold_units', align: 'end', sortable: true },
  { title: '% Acum. (Pareto)', key: 'accumulated_sales_pct', align: 'end', sortable: true, width: '135px' },
  { title: 'Rentabilidad Bruta', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'GMROI (Retorno)', key: 'gmroi', align: 'center', sortable: true },
  { title: 'Cobertura', key: 'current_stock', align: 'end', sortable: true },
  { title: 'Costo Unit.', key: 'last_cost', align: 'end', sortable: true },
  { title: 'Perfil ABC-XYZ', key: 'final_classification', align: 'center', sortable: true },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '130px' },
];

const simplifiedHeaders = computed(() => [
  { title: 'PRODUCTO / LABORATORIO', key: 'name', sortable: true },
  { title: selectedAnalysisType.value === 'expiring_risk' ? 'STOCK EN RIESGO' : 'STOCK ACTUAL', key: 'current_stock', align: 'end', sortable: true, width: '150px' },
  { title: 'VENTAS EN PERIODO', key: 'sold_units', align: 'end', sortable: true, width: '180px' },
  { title: selectedAnalysisType.value === 'expiring_risk' ? 'CAPITAL POR EXPIRAR ($)' : 'TOTAL CAPITAL PARADO ($)', key: 'inventory_value', align: 'end', sortable: true, width: '200px' },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '130px' },
]);

const activeHeaders = computed(() => {
  return isSimplifiedView.value ? simplifiedHeaders.value : fullHeaders;
});

const fetchCatalogs = async () => {
  try {
    const [labsRes, groupsRes] = await Promise.all([
      axios.get('/laboratories'),
      axios.get('/laboratory-groups'),
    ]);
    laboratories.value = labsRes.data;
    laboratoryGroups.value = groupsRes.data;
  } catch (err) {
    console.error('Error loading catalogs:', err);
  }
};

const fetchReport = async () => {
  loading.value = true;
  errorMessage.value = null;
  try {
    const dates = getDateRange(selectedDateRange.value);
    
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key || 'total_sales',
      orderBy: sortBy.value[0]?.order || 'desc',
      start_date: dates.start_date,
      end_date: dates.end_date,
      laboratory_id: selectedLaboratories.value?.length ? selectedLaboratories.value : null,
      laboratory_group_id: selectedLaboratoryGroups.value?.length ? selectedLaboratoryGroups.value : null,
      final_classification: selectedFinalClassification.value,
      analysis_type: selectedAnalysisType.value,
      min_gmroi: minGmroi.value,
      stock_filter: stockFilter.value !== 'all' ? stockFilter.value : null,
      search: search.value || null,
    };

    Object.keys(params).forEach(
      (key) => (params[key] === null || params[key] === undefined || params[key] === '') && delete params[key],
    );

    const response = await axios.get('/bi/abc', { params });
    const responseData = response.data.data;
    const summary = response.data.summary;
    
    items.value = responseData;
    totalItems.value = response.data.meta.total;

    if (summary) {
      summaryStats.value = {
        total_volume: summary.total_sales,
        aax_products: summary.aax_products,
        avg_margin: summary.avg_margin,
        frozen_capital: summary.frozen_capital,
        count_a: summary.count_a ?? 0,
        count_b: summary.count_b ?? 0,
        count_c: summary.count_c ?? 0,
        critical_stockouts: summary.critical_stockouts ?? 0,
        total_products: summary.total_products ?? 0,
      };
      paretoCurve.value = summary.pareto_curve || [];
      paretoInflection.value = summary.pareto_inflection || {
        point_80: { count: 0, sku_pct: 0, sales_pct: 0 },
        point_95: { count: 0, sku_pct: 0, sales_pct: 0 },
      };
      matrix3x3Data.value = summary.matrix_3x3 || {};
    }
  } catch (error) {
    console.error('Error fetching ABC report:', error);
    errorMessage.value = 'No se pudo cargar el reporte ABC. Verifique la conexión de datos e intente nuevamente.';
  } finally {
    loading.value = false;
  }
};

const getAbcBadgeStyle = (classification) => {
  if (!classification) {
    return {
      backgroundColor: '#F1F3F4',
      color: '#5F6368',
      border: '1px solid #DADCE0',
    };
  }

  const code = classification.toUpperCase();

  // Tier 1: Productos Estrella (A en ventas y A en margen) -> Verde Menta Suave
  if (['AAX', 'AAY'].includes(code)) {
    return {
      backgroundColor: '#E6F4EA',
      color: '#137333',
      border: '1px solid #CEEAD6',
    };
  }

  // Tier 2: Alto Desempeño / Volumen Seguro -> Azul Suave
  if (['AAZ', 'ABX', 'ABY', 'BAX', 'BAY'].includes(code)) {
    return {
      backgroundColor: '#E8F0FE',
      color: '#1A73E8',
      border: '1px solid #D2E3FC',
    };
  }

  // Tier 3: Margen o Ventas Moderadas -> Lavanda / Violeta Suave
  if (['ABZ', 'BBX', 'BBY', 'CAX', 'CAY'].includes(code)) {
    return {
      backgroundColor: '#F3E8FD',
      color: '#7627BB',
      border: '1px solid #E9D2FD',
    };
  }

  // Tier 4: Margen Bajo / Rotación Fluctuante -> Amarillo/Ámbar Suave
  if (['ACX', 'ACY', 'BAZ', 'BBZ', 'BCX', 'BCY', 'CBX', 'CBY'].includes(code)) {
    return {
      backgroundColor: '#FEF7E0',
      color: '#B06000',
      border: '1px solid #FEEFC3',
    };
  }

  // Tier 5: Críticos / Riesgo / Baja Rotación / Margen Mínimo -> Rosa/Rojo Pastel Suave
  if (['ACZ', 'BCZ', 'CCX', 'CCY', 'CCZ', 'CBZ'].includes(code)) {
    return {
      backgroundColor: '#FCE8E6',
      color: '#C5221F',
      border: '1px solid #FAD2CF',
    };
  }

  // Por defecto (Gris ejecutivo suave)
  return {
    backgroundColor: '#F1F3F4',
    color: '#3C4043',
    border: '1px solid #DADCE0',
  };
};

const getColorClass = (classification) => {
  if (!classification) return 'default';
  if (['AAX', 'AAY', 'BAX', 'CAX'].includes(classification)) return 'success';
  if (['CCZ', 'CBZ', 'ACX'].includes(classification)) return 'error';
  if (['ABX', 'BBX'].includes(classification)) return 'warning';
  return 'secondary';
};

const getGmroiColor = (gmroi) => {
  if (gmroi >= 500) return 'text-success';
  if (gmroi >= 200) return 'text-primary';
  if (gmroi > 0) return 'text-warning';
  return 'text-error';
};

watch(selectedAnalysisType, (newType) => {
  if (newType === 'frozen_capital' || newType === 'dead_stock' || newType === 'expiring_risk') {
    sortBy.value = [{ key: 'inventory_value', order: 'desc' }];
    isSimplifiedView.value = true;
  } else if (newType === 'negative_margin') {
    sortBy.value = [{ key: 'margin_percentage', order: 'asc' }];
    isSimplifiedView.value = false;
  } else {
    sortBy.value = [{ key: 'total_sales', order: 'desc' }];
    isSimplifiedView.value = false;
  }
});

let searchDebounceTimer;
watch(search, () => {
  page.value = 1;
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    fetchReport();
  }, 350);
});

watch([selectedDateRange, selectedLaboratories, selectedLaboratoryGroups, selectedFinalClassification, selectedAnalysisType, minGmroi, stockFilter], () => {
  page.value = 1;
});

watch([page, itemsPerPage, sortBy, selectedDateRange, selectedLaboratories, selectedLaboratoryGroups, selectedFinalClassification, selectedAnalysisType, minGmroi, stockFilter], () => {
  fetchReport();
}, { deep: true });

onMounted(() => {
  fetchCatalogs();
  fetchReport();
});

const handleClearFilters = () => {
  search.value = '';
  selectedDateRange.value = '30 days';
  selectedLaboratories.value = [];
  selectedLaboratoryGroups.value = [];
  selectedFinalClassification.value = null;
  selectedAnalysisType.value = 'all';
  minGmroi.value = null;
  stockFilter.value = 'all';
  isAdvancedFiltersVisible.value = false;
  isSimplifiedView.value = false;
};

const handleExport = async (exportType = 'all') => {
  exporting.value = true;
  try {
    const dates = getDateRange(selectedDateRange.value);
    const params = {
      start_date: dates.start_date,
      end_date: dates.end_date,
      laboratory_id: selectedLaboratories.value?.length ? selectedLaboratories.value : null,
      laboratory_group_id: selectedLaboratoryGroups.value?.length ? selectedLaboratoryGroups.value : null,
      final_classification: selectedFinalClassification.value,
      analysis_type: selectedAnalysisType.value,
      min_gmroi: minGmroi.value,
      stock_filter: stockFilter.value !== 'all' ? stockFilter.value : null,
      sortBy: sortBy.value[0]?.key || 'total_sales',
      orderBy: sortBy.value[0]?.order || 'desc',
      export_type: exportType,
      search: search.value || null,
    };

    // Filtrar parámetros nulos o indefinidos
    Object.keys(params).forEach(key => (params[key] === null || params[key] === undefined || params[key] === '') && delete params[key]);

    const response = await axios.get('/bi/abc/export', {
      params,
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;

    const contentDisposition = response.headers['content-disposition'];
    let fileName = `reporte_abc_${exportType}_${new Date().toISOString().slice(0,10)}.xlsx`;
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

    toast.success('Reporte exportado con éxito a Excel.');
  } catch (error) {
    console.error('Error exportando reporte ABC:', error);
    toast.error('Hubo un error al exportar el archivo Excel.');
  } finally {
    exporting.value = false;
  }
};

const handleGoToAssistant = async (autoMatch = false) => {
  navigatingAssistant.value = true;
  try {
    const dates = getDateRange(selectedDateRange.value);
    const params = {
      start_date: dates.start_date,
      end_date: dates.end_date,
      laboratory_id: selectedLaboratories.value?.length ? selectedLaboratories.value : null,
      analysis_type: 'critical_stock',
      itemsPerPage: -1,
    };
    Object.keys(params).forEach(key => (params[key] === null || params[key] === undefined || params[key] === '') && delete params[key]);

    const res = await axios.get('/bi/abc', { params });
    const criticalItems = res.data.data || [];
    const criticalIds = criticalItems.map(i => i.id);

    if (criticalIds.length === 0) {
      toast.info('No se encontraron productos en quiebre o riesgo de stock bajo los filtros seleccionados.');
      return;
    }

    const toastMsg = autoMatch 
      ? `Iniciando cotización y pedido IA para ${criticalIds.length} productos críticos...`
      : `Transfiriendo ${criticalIds.length} productos en quiebre/riesgo al Asistente IA...`;
    toast.info(toastMsg);

    const query = {
      stock: 'fallas',
      source: 'abc_critical',
      product_ids: criticalIds.join(','),
    };
    if (autoMatch) {
      query.auto_match = 'true';
    }

    router.push({
      name: 'suppliers-supplieriaorderassistant',
      query,
    });
  } catch (err) {
    console.error('Error al transferir productos críticos al asistente:', err);
    router.push({
      name: 'suppliers-supplieriaorderassistant',
      query: {
        stock: 'fallas',
        source: 'abc_critical',
        ...(autoMatch ? { auto_match: 'true' } : {}),
      },
    });
  } finally {
    navigatingAssistant.value = false;
  }
};

const handleFilterCritical = () => {
  selectedAnalysisType.value = 'critical_stock';
};
</script>

<template>
  <div class="report-abc-view pb-12">
    <!-- Componente Desacoplado: Filtros -->
    <AbcReportFilters
      v-model:search="search"
      v-model:selected-date-range="selectedDateRange"
      v-model:selected-analysis-type="selectedAnalysisType"
      v-model:selected-laboratories="selectedLaboratories"
      v-model:selected-laboratory-groups="selectedLaboratoryGroups"
      v-model:selected-final-classification="selectedFinalClassification"
      v-model:min-gmroi="minGmroi"
      v-model:stock-filter="stockFilter"
      v-model:is-advanced-filters-visible="isAdvancedFiltersVisible"
      :loading="loading"
      :exporting="exporting"
      :navigating-assistant="navigatingAssistant"
      :laboratories="laboratories"
      :laboratory-groups="laboratoryGroups"
      @fetch="fetchReport"
      @clear="handleClearFilters"
      @export="handleExport"
      @go-to-assistant="handleGoToAssistant"
      @filter-critical="handleFilterCritical"
    />

    <!-- Banner de error -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4 rounded-lg"
      closable
      @click:close="errorMessage = null"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ errorMessage }}</span>
        <VBtn size="small" color="error" variant="flat" @click="fetchReport">
          <VIcon icon="tabler-refresh" size="14" class="me-1" />
          Reintentar
        </VBtn>
      </div>
    </VAlert>

    <!-- Componente Desacoplado: KPIs -->
    <AbcReportKpiCards
      :loading="loading"
      :selected-analysis-type="selectedAnalysisType"
      :summary-stats="summaryStats"
    />

    <!-- Selector de Herramientas Analíticas Visuales (Pareto y Matriz 3x3) -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
      <div class="d-flex align-center flex-wrap gap-2">
        <VBtn
          :color="showParetoChart ? 'primary' : 'secondary'"
          :variant="showParetoChart ? 'tonal' : 'outlined'"
          size="small"
          class="font-weight-bold"
          @click="showParetoChart = !showParetoChart"
        >
          <VIcon icon="tabler-chart-dots" size="16" class="me-1.5" />
          {{ showParetoChart ? 'Ocultar Curva de Pareto' : 'Ver Curva de Pareto (80/15/5)' }}
        </VBtn>

        <VBtn
          :color="showDecisionMatrix ? 'info' : 'secondary'"
          :variant="showDecisionMatrix ? 'tonal' : 'outlined'"
          size="small"
          class="font-weight-bold"
          @click="showDecisionMatrix = !showDecisionMatrix"
        >
          <VIcon icon="tabler-grid-dots" size="16" class="me-1.5" />
          {{ showDecisionMatrix ? 'Ocultar Matriz 3×3' : 'Ver Matriz de Decisión 3×3' }}
        </VBtn>
      </div>

      <div v-if="selectedQuadrant" class="d-flex align-center gap-1.5">
        <span class="text-caption text-medium-emphasis">Filtro activo:</span>
        <VChip
          color="primary"
          size="small"
          variant="flat"
          closable
          class="font-weight-bold"
          @click:close="handleClearQuadrant"
        >
          Cuadrante {{ selectedQuadrant }}
        </VChip>
      </div>
    </div>

    <!-- 1. Curva de Pareto Visual (80/15/5) -->
    <VExpandTransition>
      <div v-show="showParetoChart">
        <AbcParetoChart
          :pareto-curve="paretoCurve"
          :pareto-inflection="paretoInflection"
          :total-products="summaryStats.total_products"
          :total-sales="summaryStats.total_volume"
          :loading="loading"
          @filter-class="handleFilterClass"
        />
      </div>
    </VExpandTransition>

    <!-- 2. Matriz de Decisión Estratégica 3x3 -->
    <VExpandTransition>
      <div v-show="showDecisionMatrix">
        <AbcDecisionMatrix
          :matrix-data="matrix3x3Data"
          :selected-quadrant="selectedQuadrant"
          :total-products="summaryStats.total_products"
          :total-sales="summaryStats.total_volume"
          :loading="loading"
          @select-quadrant="handleSelectQuadrant"
          @clear-quadrant="handleClearQuadrant"
        />
      </div>
    </VExpandTransition>

    <!-- Contenedor Principal de Resultados -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="d-flex justify-space-between align-center py-2.5 px-4 flex-wrap gap-2">
        <div class="d-flex align-center flex-wrap gap-2">
          <h2 class="text-subtitle-1 font-weight-bold d-flex align-center mb-0 text-high-emphasis">
            <VIcon icon="tabler-list-details" class="me-2 text-primary" size="20" />
            {{ isSimplifiedView ? (selectedAnalysisType === 'expiring_risk' ? 'Datos Clave: Capital por Expirar' : 'Datos Clave: Capital Parado') : 'Resultados del Análisis' }}
          </h2>
          <VChip
            v-if="isSimplifiedView"
            color="warning"
            size="x-small"
            variant="tonal"
            class="font-weight-bold"
          >
            <VIcon icon="tabler-bolt" size="12" class="me-1" />
            Vista Rápida
          </VChip>
        </div>

        <!-- Pestañas de Conmutación de Vistas y Acceso a Foto Finish -->
        <div class="d-flex align-center gap-2 ms-auto">
          <VBtnToggle
            :model-value="isSimplifiedView ? 'simplified' : 'full'"
            density="compact"
            variant="outlined"
            divided
            mandatory
            color="primary"
            class="rounded-lg bg-surface border"
            @update:model-value="isSimplifiedView = ($event === 'simplified')"
          >
            <VBtn value="full" size="small" class="font-weight-medium text-caption px-3">
              <VIcon icon="tabler-layout-table" size="14" class="me-1" />
              Vista Completa
            </VBtn>
            <VBtn value="simplified" size="small" class="font-weight-medium text-caption px-3">
              <VIcon icon="tabler-bulb" size="14" class="me-1" />
              {{ selectedAnalysisType === 'expiring_risk' ? 'Capital por Expirar' : 'Capital Parado' }}
            </VBtn>
          </VBtnToggle>

          <VBtn
            color="info"
            variant="tonal"
            size="small"
            class="font-weight-bold text-caption rounded-lg"
            @click="router.push('/bi/report-finish')"
          >
            <VIcon icon="tabler-camera" size="15" class="me-1" />
            Foto Finish
            <VTooltip activator="parent" location="top">Ir a Análisis Comparativo Foto Finish</VTooltip>
          </VBtn>
        </div>
      </VCardText>
      <VDivider class="border-opacity-10" />

      <!-- Vista Tabla Desktop -->
      <div class="d-none d-md-block">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          v-model:sort-by="sortBy"
          :items-length="totalItems"
          :headers="activeHeaders"
          :items="items"
          :search="search"
          :loading="loading"
          class="premium-table"
          hover
          density="compact"
        >
          <!-- Empty State Personalizado -->
          <template #no-data>
            <div class="py-8 text-center text-medium-emphasis">
              <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
              <p class="text-body-1 font-weight-medium mb-1">No se encontraron productos para los criterios aplicados</p>
              <p class="text-caption text-disabled mb-4">Intente ajustar o borrar los filtros de búsqueda</p>
              <VBtn size="small" color="primary" variant="outlined" @click="handleClearFilters">
                <VIcon icon="tabler-eraser" size="16" class="me-1" />
                Limpiar Filtros
              </VBtn>
            </div>
          </template>

          <!-- Columna Producto / Laboratorio -->
          <template #item.name="{ item }">
            <div class="d-flex flex-column py-1" style="min-width: 230px; max-width: 340px;">
              <a
                :href="`/inventory/traceability?q=${item.id}`"
                target="_blank"
                class="text-sm font-weight-bold text-high-emphasis text-uppercase text-truncate text-decoration-none id-link cursor-pointer mb-0.5"
                :title="item.name"
              >
                <span class="text-primary font-weight-bold me-1">#{{ item.id }}</span>
                {{ item.name }}
              </a>

              <div class="d-flex align-center flex-wrap gap-1.5">
                <span class="text-caption text-medium-emphasis text-uppercase text-truncate" style="max-width: 180px;">
                  {{ item.laboratory_name || 'Sin laboratorio' }}
                </span>

                <!-- Oferta Individual (Ámbar operativo) -->
                <VTooltip v-if="item.has_individual_offer || item.individual_offer_discount" location="top">
                  <template #activator="{ props: tipProps }">
                    <VChip
                      v-bind="tipProps"
                      color="warning"
                      size="x-small"
                      variant="tonal"
                      density="compact"
                      class="font-weight-medium"
                    >
                      <VIcon icon="tabler-tag" size="10" class="me-0.5" />
                      -{{ Math.round(item.individual_offer_discount) }}%
                    </VChip>
                  </template>
                  <span>Oferta individual activa: {{ item.individual_offer_discount }}% de descuento</span>
                </VTooltip>

                <!-- Vencimiento (Rojo solo si vencido; Ámbar si es preventivo) -->
                <VTooltip v-if="item.is_expiring_soon || (item.days_to_expiration !== null && item.days_to_expiration <= 180) || item.has_expiration_risk" location="top">
                  <template #activator="{ props: tipProps }">
                    <VChip
                      v-bind="tipProps"
                      :color="(item.days_to_expiration !== null && item.days_to_expiration <= 0) ? 'error' : 'warning'"
                      size="x-small"
                      variant="tonal"
                      density="compact"
                      class="font-weight-medium"
                    >
                      <VIcon icon="tabler-clock-exclamation" size="10" class="me-0.5" />
                      {{ item.days_to_expiration <= 0 ? 'Vencido' : (item.days_to_expiration !== null ? `${item.days_to_expiration}d` : 'Riesgo FEFO') }}
                    </VChip>
                  </template>
                  <span>Próximo vencimiento: {{ item.next_expiration_date || 'Lote próximo' }} ({{ item.days_to_expiration }} días restantes)</span>
                </VTooltip>
              </div>
            </div>
          </template>

          <!-- Columna Desempeño Comercial -->
          <template #item.sold_units="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="text-sm font-weight-bold text-high-emphasis">
                {{ item.sold_units }} unds
              </span>
              <div class="d-flex align-center gap-1 mt-0.5">
                <span class="text-caption text-medium-emphasis">Fact: {{ formatCurrency(item.total_sales) }}</span>
                <span v-if="!isSimplifiedView && item.contribution_sales_pct" class="text-caption text-disabled">
                  ({{ item.contribution_sales_pct.toFixed(1) }}%)
                </span>
              </div>
            </div>
          </template>

          <!-- Columna % Acumulado Pareto -->
          <template #item.accumulated_sales_pct="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="text-sm font-weight-bold text-high-emphasis">
                {{ item.accumulated_sales_pct !== undefined ? item.accumulated_sales_pct.toFixed(1) : '100.0' }}%
              </span>
              <span
                class="text-caption font-weight-medium"
                :class="(item.accumulated_sales_pct <= 80) ? 'text-success' : ((item.accumulated_sales_pct <= 95) ? 'text-warning' : 'text-disabled')"
              >
                {{ (item.accumulated_sales_pct <= 80) ? 'Zona A (80%)' : ((item.accumulated_sales_pct <= 95) ? 'Zona B (95%)' : 'Zona C (100%)') }}
              </span>
            </div>
          </template>
          
          <!-- Columna Rentabilidad Bruta -->
          <template #item.margin_percentage="{ item }">
            <VTooltip location="top">
              <template #activator="{ props: tipProps }">
                <div v-bind="tipProps" class="d-flex flex-column align-end cursor-help">
                  <span
                    class="text-sm font-weight-bold"
                    :class="(item.margin_percentage ?? 0) >= 0 ? 'text-success' : 'text-error'"
                  >
                    {{ typeof item.margin_percentage === 'number' ? item.margin_percentage.toFixed(2) : item.margin_percentage }}%
                  </span>
                  <span
                    class="text-caption mt-0.5"
                    :class="(item.margin_amount ?? 0) >= 0 ? 'text-medium-emphasis' : 'text-error font-weight-medium'"
                  >
                    {{ (item.margin_amount ?? 0) > 0 ? '+' : '' }}{{ formatCurrency(item.margin_amount) }}
                  </span>
                </div>
              </template>
              <span>Aporte al margen total: {{ item.contribution_margin_pct ? item.contribution_margin_pct.toFixed(2) : '0.00' }}%</span>
            </VTooltip>
          </template>

          <!-- Columna GMROI -->
          <template #item.gmroi="{ item }">
            <div class="d-flex flex-column align-center">
              <span class="text-sm font-weight-bold" :class="getGmroiColor(item.gmroi)">
                {{ item.gmroi >= 9999 ? 'MAX' : Math.round(item.gmroi) + '%' }}
              </span>
              <span class="text-caption text-disabled font-weight-medium" style="font-size: 0.65rem !important;">ROI ANUAL</span>
            </div>
          </template>

          <!-- Columna Cobertura -->
          <template #item.current_stock="{ item }">
            <div class="d-flex flex-column align-end">
              <template v-if="isSimplifiedView">
                <span class="text-sm font-weight-bold text-high-emphasis">
                  {{ selectedAnalysisType === 'expiring_risk' && item.risk_expiring_units > 0 && item.risk_expiring_units < item.current_stock ? `${item.risk_expiring_units} de ${item.current_stock} unds` : `${item.current_stock} unds` }}
                </span>
                <span v-if="selectedAnalysisType === 'expiring_risk' && item.risk_expiring_units > 0 && item.risk_expiring_units < item.current_stock" class="text-caption text-warning font-weight-medium mt-0.5">
                  (Riesgo por lote)
                </span>
                <span v-else class="text-caption text-medium-emphasis mt-0.5">
                  Costo: {{ formatCurrency(item.last_cost) }}
                </span>
              </template>
              <template v-else>
                <span
                  class="text-sm font-weight-bold"
                  :class="item.current_stock <= 0 || item.inventory_days < 10 ? 'text-error' : 'text-high-emphasis'"
                >
                  {{ item.current_stock <= 0 ? 'Sin stock (0d)' : (item.inventory_days === 9999 ? 'Sin rotación' : `${Math.round(item.inventory_days)} días`) }}
                </span>
                <span class="text-caption text-medium-emphasis mt-0.5">
                  {{ item.current_stock }} unds · {{ formatCurrency(item.inventory_value ?? (item.current_stock * (item.last_cost ?? 0))) }}
                </span>
              </template>
            </div>
          </template>

          <!-- Columna Capital Inmovilizado / En Riesgo -->
          <template #item.inventory_value="{ item }">
            <div class="d-flex flex-column align-end">
              <span
                class="text-sm font-weight-bold"
                :class="selectedAnalysisType === 'expiring_risk' ? 'text-warning' : (['dead_stock', 'frozen_capital'].includes(selectedAnalysisType) ? 'text-high-emphasis' : 'text-high-emphasis')"
              >
                {{ formatCurrency(selectedAnalysisType === 'expiring_risk' && item.risk_expiring_capital > 0 ? item.risk_expiring_capital : item.inventory_value) }}
              </span>
              <span v-if="selectedAnalysisType === 'expiring_risk' && summaryStats.expiring_risk_capital > 0" class="text-caption text-medium-emphasis mt-0.5">
                {{ (((item.risk_expiring_capital > 0 ? item.risk_expiring_capital : item.inventory_value) / summaryStats.expiring_risk_capital) * 100).toFixed(1) }}% del riesgo
              </span>
              <span v-else-if="summaryStats.frozen_capital > 0" class="text-caption text-medium-emphasis mt-0.5">
                {{ ((item.inventory_value / summaryStats.frozen_capital) * 100).toFixed(1) }}% del inmovilizado
              </span>
            </div>
          </template>
          
          <!-- Columna Costo Unitario -->
          <template #item.last_cost="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="text-sm font-weight-bold text-high-emphasis">{{ formatCurrency(item.last_cost) }}</span>
              <span v-if="item.inventory_value > 0" class="text-caption text-medium-emphasis mt-0.5">
                Inv: {{ formatCurrency(item.inventory_value) }}
              </span>
            </div>
          </template>

          <!-- Columna Clasificación ABC-XYZ -->
          <template #item.final_classification="{ item }">
            <VTooltip location="top" content-class="bg-grey-900 border-opacity-100">
              <template #activator="{ props: tipProps }">
                <span
                  v-bind="tipProps"
                  class="abc-badge-pill cursor-help"
                  :style="getAbcBadgeStyle(item.final_classification)"
                >
                  {{ item.final_classification }}
                </span>
              </template>
              <div class="d-flex flex-column gap-1 text-caption text-left text-white pa-1">
                <span><strong>A</strong>porte Ventas: {{ item.class_sales === 'A' ? 'Alto (80%)' : (item.class_sales === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
                <span><strong>M</strong>argen Cbción: {{ item.class_margin === 'A' ? 'Alto (80%)' : (item.class_margin === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
                <span><strong>R</strong>otación Dem.: {{ item.class_rotation === 'X' ? 'Constante (Seguro)' : (item.class_rotation === 'Y' ? 'Fluctuante (Normal)' : 'Esporádica (Riesgo)') }}</span>
              </div>
            </VTooltip>
          </template>

          <!-- Acciones Operativas en Fila (Oferta Flash / Asignar Vendedores / IA Pedidos / Kardex) -->
          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-1">
              <!-- Oferta Flash TPV -->
              <VTooltip location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon
                    size="small"
                    variant="tonal"
                    color="warning"
                    @click="handleOpenIndividualOffer(item)"
                  >
                    <VIcon icon="tabler-tag" size="16" />
                  </VBtn>
                </template>
                <span>🏷️ Crear Oferta Flash en TPV</span>
              </VTooltip>

              <!-- Asignar a Vendedores -->
              <VTooltip location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon
                    size="small"
                    variant="tonal"
                    color="info"
                    @click="handleOpenAssignEmployees(item)"
                  >
                    <VIcon icon="tabler-user-plus" size="16" />
                  </VBtn>
                </template>
                <span>👤 Asignar meta a Vendedores</span>
              </VTooltip>

              <!-- Menú de Acciones Adicionales -->
              <VMenu location="bottom end" :close-on-content-click="true">
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    icon
                    size="small"
                    variant="text"
                    color="secondary"
                  >
                    <VIcon icon="tabler-dots-vertical" size="16" />
                  </VBtn>
                </template>
                <VList density="compact" class="py-1 shadow-md border rounded-lg" min-width="230">
                  <VListItem
                    prepend-icon="tabler-sparkles"
                    title="🛒 Cotizar / Pedido con IA"
                    subtitle="Transferir SKU a Asistente IA"
                    @click="handleSuggestIaOrder(item)"
                  />
                  <VListItem
                    prepend-icon="tabler-history"
                    title="📊 Ver Kardex / Trazabilidad"
                    subtitle="Movimientos y lotes del SKU"
                    :href="`/inventory/traceability?q=${item.id}`"
                    target="_blank"
                  />
                </VList>
              </VMenu>
            </div>
          </template>

        </VDataTableServer>
      </div>

      <!-- Vista Móvil Desacoplada -->
      <AbcReportMobileView
        v-model:page="page"
        :items="items"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :selected-analysis-type="selectedAnalysisType"
        :is-simplified-view="isSimplifiedView"
        :get-color-class="getColorClass"
        :get-abc-badge-style="getAbcBadgeStyle"
        :get-gmroi-color="getGmroiColor"
        @open-offer="handleOpenIndividualOffer"
        @open-assign="handleOpenAssignEmployees"
      />
    </VCard>

    <!-- Modal Oferta Individual -->
    <IndividualCreateOffer
      v-model="isOfferDialogVisible"
      :form-data="currentIndvOffer"
      :product="selectedProductForOffer"
      :form-errors="offerErrors"
      :is-editing="isEditingOffer"
      :loading="offerLoading"
      :product-offer-to-edit="currentOfferToEdit"
      @save="handleSaveIndividualOffer"
      @modal-closed="isOfferDialogVisible = false"
    />

    <!-- Diálogo Asignar a Vendedores -->
    <AssignProductToEmployeesDialog
      v-model="isAssignEmployeesDialogVisible"
      :product="selectedProductForAssign"
      @assigned="handleAssignedEmployees"
    />
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.72rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
  padding-block: 10px !important;
  padding-inline: 14px !important;
}

.premium-table :deep(td) {
  padding-block: 10px !important;
  padding-inline: 14px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.06) !important;
}

.premium-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.report-abc-view {
  min-block-size: 100vh;
}

.abc-badge-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.5px;
  line-height: 1.2;
}

.id-link {
  transition: color 0.15s ease-in-out;
}
.id-link:hover {
  color: rgb(var(--v-theme-primary)) !important;
  text-decoration: underline !important;
}

.cursor-help {
  cursor: help;
}

.gap-1 { gap: 4px !important; }
.gap-1\.5 { gap: 6px !important; }
.gap-2 { gap: 8px !important; }
</style>
