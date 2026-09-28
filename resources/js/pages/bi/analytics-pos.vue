<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import PosAnalyticsFilters from './components/PosAnalyticsFilters.vue';
import PosAnalyticsKpis from './components/PosAnalyticsKpis.vue';
import PosAnalyticsTemporalCharts from './components/PosAnalyticsTemporalCharts.vue';
import PosAnalyticsSegmentation from './components/PosAnalyticsSegmentation.vue';
import PosAnalyticsHourlyTables from './components/PosAnalyticsHourlyTables.vue';

// --- ESTADO REACTIVO ---
const loading = ref(false);
const exporting = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10));
const endDate = ref(new Date().toISOString().substring(0, 10));
const sellerId = ref(null);
const dashboardData = ref(null);
const errorMessage = ref('');

let abortController = null;

// --- CARGA DE DATOS ASÍNCRONA ---
const fetchDashboard = async () => {
  if (abortController) {
    abortController.abort();
  }
  abortController = new AbortController();

  loading.value = true;
  errorMessage.value = '';
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
      seller_id: sellerId.value || undefined,
    };
    const { data } = await axios.get('/bi/pos/dashboard', {
      params,
      signal: abortController.signal,
    });
    
    // Soporte para respuesta estandarizada de API Resource
    dashboardData.value = data.data || data;
  } catch (error) {
    if (error.name !== 'CanceledError') {
      console.error('Error al cargar dashboard de TPV:', error);
      errorMessage.value = error.response?.data?.message || 'Error al cargar los datos del dashboard. Por favor intente de nuevo.';
    }
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10);
  endDate.value = new Date().toISOString().substring(0, 10);
  sellerId.value = null;
};

// --- EXPORTACIÓN A PDF ---
const handleExportPdf = () => {
  if (!dashboardData.value) return;
  exporting.value = true;

  try {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const kpis = dashboardData.value.kpis || {};
    const formatCurrency = (val) => `$${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(val || 0)}`;

    // Título y encabezado
    doc.setFontSize(16);
    doc.setTextColor(226, 0, 116); // Primary #E20074
    doc.text('REPORTE EJECUTIVO DE VENTAS - POS FARMACIA', 14, 18);

    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text(`Rango: ${startDate.value} al ${endDate.value} | Generado: ${new Date().toLocaleDateString('es-ES')}`, 14, 25);

    // Tabla de KPIs
    const kpiRows = [
      ['Facturación Total', formatCurrency(kpis.total_revenue), 'Transacciones Exitosas', `${kpis.completed_sales || 0}`],
      ['Ticket Medio', formatCurrency(kpis.avg_ticket), 'Unidades por Ticket (UPT)', `${(kpis.units_per_transaction || 0).toFixed(2)}`],
      ['Penetración Venta Cruzada', `${kpis.cross_selling_rate || 0}%`, 'Tickets Multi-ítem', `${kpis.cross_selling_count || 0}`],
      ['Venta Diaria Operativa', formatCurrency(kpis.avg_daily_sales), 'Días Operativos', `${kpis.operational_days || 0}`],
      ['Cotizaciones Emitidas', `${kpis.quotations_generated || 0}`, 'Tasa de Conversión', `${kpis.conversion_rate || 0}%`],
      ['Tickets Abandonados', `${kpis.abandoned_sales || 0}`, 'Descuentos Totales', formatCurrency(kpis.discount_total)],
    ];

    autoTable(doc, {
      startY: 30,
      head: [['Métrica Principal', 'Valor', 'Métrica Secundaria', 'Valor']],
      body: kpiRows,
      theme: 'striped',
      headStyles: { fillColor: [226, 0, 116], textColor: [255, 255, 255], fontStyle: 'bold' },
      styles: { fontSize: 9, cellPadding: 3 },
    });

    // Tabla de Distribución Horaria
    const hourlyData = dashboardData.value.charts?.hourly_distribution?.series?.[0]?.data || [];
    const hourlyRows = hourlyData.map((h) => [
      h.x,
      `${h.y}%`,
      formatCurrency(h.revenue),
      h.top_seller?.seller_name || 'N/A',
    ]);

    doc.setFontSize(12);
    doc.setTextColor(122, 0, 153); // Secondary #7A0099
    doc.text('Distribución y Tráfico por Franja Horaria', 14, doc.lastAutoTable.finalY + 10);

    autoTable(doc, {
      startY: doc.lastAutoTable.finalY + 14,
      head: [['Hora', '% Participación', 'Facturación (USD)', 'Vendedor Estrella']],
      body: hourlyRows,
      theme: 'grid',
      headStyles: { fillColor: [122, 0, 153], textColor: [255, 255, 255], fontStyle: 'bold' },
      styles: { fontSize: 8, cellPadding: 2 },
    });

    doc.save(`reporte_pos_${startDate.value}_${endDate.value}.pdf`);
    toast.success('Reporte PDF descargado exitosamente.');
  } catch (error) {
    console.error('Error al exportar PDF:', error);
    toast.error('Hubo un error al generar el PDF.');
  } finally {
    exporting.value = false;
  }
};

// --- EXPORTACIÓN A CSV ---
const handleExportCsv = () => {
  if (!dashboardData.value) return;
  exporting.value = true;

  try {
    const hourlyData = dashboardData.value.charts?.hourly_distribution?.series?.[0]?.data || [];
    let csvContent = 'data:text/csv;charset=utf-8,';
    
    csvContent += 'Hora,Participacion_Pct,Facturacion_USD,Top_Vendedor\n';
    hourlyData.forEach((row) => {
      csvContent += `"${row.x}","${row.y}%","${row.revenue}","${row.top_seller?.seller_name || 'N/A'}"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `reporte_pos_horario_${startDate.value}_${endDate.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    toast.success('Archivo CSV descargado exitosamente.');
  } catch (error) {
    console.error('Error al exportar CSV:', error);
    toast.error('Hubo un error al generar el archivo CSV.');
  } finally {
    exporting.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
});

watch([startDate, endDate, sellerId], () => {
  fetchDashboard();
});

const hasData = computed(() => {
  return Boolean(dashboardData.value?.kpis && dashboardData.value.kpis.completed_sales > 0);
});

const sellersList = computed(() => {
  return dashboardData.value?.filter_options?.sellers || [];
});
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Filtros de Consulta -->
    <PosAnalyticsFilters
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      v-model:seller-id="sellerId"
      :sellers="sellersList"
      :loading="loading"
      :exporting="exporting"
      @fetch="fetchDashboard"
      @reset="resetFilters"
      @export-pdf="handleExportPdf"
      @export-csv="handleExportCsv"
    />

    <!-- Estado de Error -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      closable
      class="mb-6 rounded-lg"
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Estado de Carga (Skeleton) -->
    <div v-if="loading && !dashboardData" class="px-1">
      <VRow class="mb-6" dense>
        <VCol cols="12" sm="6" md="3" v-for="i in 8" :key="i">
          <VSkeletonLoader type="card" height="90" class="rounded-lg" />
        </VCol>
      </VRow>
      <VRow class="mb-6" dense>
        <VCol cols="12" md="6" v-for="i in 2" :key="i">
          <VSkeletonLoader type="card" height="350" class="rounded-lg" />
        </VCol>
      </VRow>
      <VRow dense>
        <VCol cols="12" md="4" v-for="i in 3" :key="i">
          <VSkeletonLoader type="table" height="250" class="rounded-lg" />
        </VCol>
      </VRow>
    </div>

    <!-- Estado Vacío -->
    <VCard
      v-else-if="!loading && !hasData"
      variant="outlined"
      class="rounded-lg text-center pa-10 mb-6"
    >
      <VAvatar color="warning" variant="tonal" size="64" class="mb-4">
        <VIcon icon="tabler-shopping-cart-off" size="32" />
      </VAvatar>
      <h3 class="text-h6 font-weight-bold mb-2">No se encontraron ventas</h3>
      <p class="text-medium-emphasis text-body-2 mb-0">
        No existen registros de ventas completadas para los filtros y rango de fechas seleccionados.
      </p>
    </VCard>

    <!-- Contenido del Dashboard -->
    <div v-else-if="dashboardData" class="px-1">
      <!-- Tarjetas de KPI -->
      <PosAnalyticsKpis :kpis="dashboardData.kpis || {}" />

      <!-- Gráficos Temporales -->
      <PosAnalyticsTemporalCharts :charts="dashboardData.charts || {}" />

      <!-- Segmentación por Volumen y Valor -->
      <PosAnalyticsSegmentation
        :segmentation="dashboardData.segmentation || {}"
        :kpis="dashboardData.kpis || {}"
      />

      <!-- Tablas de Clasificación Horaria -->
      <PosAnalyticsHourlyTables
        :hourly-distribution="dashboardData.charts?.hourly_distribution || {}"
        :completed-sales="dashboardData.kpis?.completed_sales || 0"
        :total-revenue="dashboardData.kpis?.total_revenue || 0"
      />
    </div>
  </VContainer>
</template>
