import { computed, reactive, ref } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import dayjs from 'dayjs';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export function useLaboratoryReport() {
  const loading = ref(false);
  const groupByCorporate = ref(false);
  const startDate = ref(dayjs().startOf('month').format('YYYY-MM-DD'));
  const endDate = ref(dayjs().format('YYYY-MM-DD'));

  // Catálogo de laboratorios
  const laboratories = ref([]);

  // Datos globales del dashboard
  const dashboardData = reactive({
    rankings: {
      by_units: { data: [], total: 0, current_page: 1, per_page: 10 },
      by_revenue: { data: [], total: 0, current_page: 1, per_page: 10 },
      by_stock: { data: [], total: 0, current_page: 1, per_page: 10 }
    },
    trends: [],
    stock_on_hand: [],
    profitability: []
  });

  // Métricas ejecutivas resumidas (Scorecards)
  const summaryKpis = computed(() => {
    const revenueList = dashboardData.rankings.by_revenue?.data || [];
    const unitsList = dashboardData.rankings.by_units?.data || [];
    const stockList = dashboardData.stock_on_hand || [];
    const profitList = dashboardData.profitability || [];

    const totalRevenue = revenueList.reduce((acc, curr) => acc + (parseFloat(curr.total_revenue) || 0), 0);
    const totalUnits = unitsList.reduce((acc, curr) => acc + (parseFloat(curr.total_units) || 0), 0);
    const totalStockValue = stockList.reduce((acc, curr) => acc + (parseFloat(curr.inventory_value) || 0), 0);
    
    const avgMargin = profitList.length 
      ? profitList.reduce((acc, curr) => acc + (parseFloat(curr.margin_percent) || 0), 0) / profitList.length 
      : 0;

    return {
      totalRevenue,
      totalUnits,
      totalStockValue,
      avgMargin
    };
  });

  // Paginación y estados de rankings
  const pageUnits = ref(1);
  const pageRevenue = ref(1);
  const pageStock = ref(1);
  const loadingUnits = ref(false);
  const loadingRevenue = ref(false);
  const loadingStock = ref(false);

  // Benchmarking
  const labA = ref(null);
  const labB = ref(null);
  const benchmarkingData = reactive({
    lab_a: null,
    lab_b: null,
    shared_groups: []
  });
  const loadingBenchmarking = ref(false);

  // Deep Dive
  const selectedLabId = ref(null);
  const deepDiveData = reactive({
    top_products: [],
    group_performance: [],
    stats: null
  });
  const loadingDeepDive = ref(false);

  // Carga de catálogo de laboratorios/grupos
  const fetchCatalogs = async () => {
    try {
      const { data } = await axios.get('/bi/laboratories/catalogs', {
        params: { group_by_corporate: groupByCorporate.value }
      });
      laboratories.value = Array.isArray(data) ? data : [];
    } catch (error) {
      console.error('Error cargando catálogos:', error);
      toast.error('Error al cargar catálogo de laboratorios');
    }
  };

  // Carga general del dashboard
  const fetchDashboard = async () => {
    loading.value = true;
    try {
      const params = {
        start_date: startDate.value,
        end_date: endDate.value,
        group_by_corporate: groupByCorporate.value
      };
      const { data } = await axios.get('/bi/laboratories/dashboard', { params });
      
      dashboardData.rankings = data.rankings || {
        by_units: { data: [], total: 0, current_page: 1, per_page: 10 },
        by_revenue: { data: [], total: 0, current_page: 1, per_page: 10 },
        by_stock: { data: [], total: 0, current_page: 1, per_page: 10 }
      };
      dashboardData.trends = data.trends || [];
      dashboardData.stock_on_hand = data.stock_on_hand || [];
      dashboardData.profitability = data.profitability || [];
    } catch (error) {
      console.error('Error al cargar dashboard:', error);
      toast.error('Error al cargar los datos del dashboard');
    } finally {
      loading.value = false;
    }
  };

  // Paginación de rankings por métrica
  const fetchRankings = async (metric = 'total_units', page = 1) => {
    let isLoading;
    let pageRef;
    let dataKey;

    if (metric === 'total_units') {
      isLoading = loadingUnits;
      pageRef = pageUnits;
      dataKey = 'by_units';
    } else if (metric === 'total_revenue') {
      isLoading = loadingRevenue;
      pageRef = pageRevenue;
      dataKey = 'by_revenue';
    } else {
      isLoading = loadingStock;
      pageRef = pageStock;
      dataKey = 'by_stock';
    }

    isLoading.value = true;
    try {
      const params = {
        metric,
        page,
        start_date: startDate.value,
        end_date: endDate.value,
        group_by_corporate: groupByCorporate.value
      };
      const { data } = await axios.get('/bi/laboratories/rankings', { params });
      dashboardData.rankings[dataKey] = data;
      pageRef.value = page;
    } catch (error) {
      console.error(`Error cargando ranking ${metric}:`, error);
      toast.error('Error al cargar los rankings');
    } finally {
      isLoading.value = false;
    }
  };

  // Carga comparativa de benchmarking
  const fetchBenchmarking = async () => {
    if (!labA.value || !labB.value) return;

    loadingBenchmarking.value = true;
    try {
      const params = {
        lab_a: labA.value,
        lab_b: labB.value,
        start_date: startDate.value,
        end_date: endDate.value,
        group_by_corporate: groupByCorporate.value
      };
      const { data } = await axios.get('/bi/laboratories/benchmarking', { params });
      benchmarkingData.lab_a = data.lab_a;
      benchmarkingData.lab_b = data.lab_b;
      benchmarkingData.shared_groups = data.shared_groups || [];
    } catch (error) {
      console.error('Error en benchmarking:', error);
      toast.error('Error al realizar la comparativa de laboratorios');
    } finally {
      loadingBenchmarking.value = false;
    }
  };

  // Detalle profundo (Deep Dive)
  const fetchDeepDive = async (id) => {
    if (!id) return;
    loadingDeepDive.value = true;
    try {
      const params = {
        start_date: startDate.value,
        end_date: endDate.value,
        group_by_corporate: groupByCorporate.value
      };
      const { data } = await axios.get(`/bi/laboratories/${id}/deep-dive`, { params });
      deepDiveData.top_products = data.top_products || [];
      deepDiveData.group_performance = data.group_performance || [];
      deepDiveData.stats = data.stats || null;
      selectedLabId.value = id;
    } catch (error) {
      console.error('Error en deep dive:', error);
      toast.error('Error al obtener detalle del laboratorio');
    } finally {
      loadingDeepDive.value = false;
    }
  };

  const resetComparisons = () => {
    labA.value = null;
    labB.value = null;
    benchmarkingData.lab_a = null;
    benchmarkingData.lab_b = null;
    benchmarkingData.shared_groups = [];
  };

  // Exportación a PDF (Resumen Ejecutivo)
  const exportExecutivePdf = (formatCurrencyFn) => {
    try {
      const doc = new jsPDF('p', 'mm', 'a4');
      const format = formatCurrencyFn || ((v) => `$${Number(v || 0).toFixed(2)}`);

      // Encabezado
      doc.setFillColor(226, 0, 116);
      doc.rect(0, 0, 210, 20, 'F');
      doc.setTextColor(255, 255, 255);
      doc.setFontSize(14);
      doc.setFont('helvetica', 'bold');
      doc.text('REPORTE EJECUTIVO DE INTELIGENCIA DE LABORATORIOS', 14, 13);

      doc.setTextColor(50, 50, 50);
      doc.setFontSize(9);
      doc.setFont('helvetica', 'normal');
      doc.text(`Período: ${startDate.value} al ${endDate.value} | Agrupación: ${groupByCorporate.value ? 'Corporativa' : 'Individual'}`, 14, 28);
      doc.text(`Generado: ${dayjs().format('DD/MM/YYYY HH:mm')}`, 14, 33);

      // Tabla de Top Venta Bruta
      const revenueRows = (dashboardData.rankings.by_revenue?.data || []).map((item, idx) => [
        idx + 1,
        item.name || 'N/A',
        format(item.total_revenue || 0)
      ]);

      autoTable(doc, {
        startY: 38,
        head: [['#', 'Laboratorio (Top Facturación)', 'Venta Bruta (USD)']],
        body: revenueRows.length ? revenueRows : [['-', 'Sin datos', '-']],
        theme: 'striped',
        headStyles: { fillColor: [226, 0, 116], textColor: [255, 255, 255] },
        styles: { fontSize: 8 }
      });

      // Tabla de Top Unidades
      const unitsRows = (dashboardData.rankings.by_units?.data || []).map((item, idx) => [
        idx + 1,
        item.name || 'N/A',
        Math.round(item.total_units || 0).toLocaleString()
      ]);

      autoTable(doc, {
        startY: doc.lastAutoTable.finalY + 10,
        head: [['#', 'Laboratorio (Top Volumen)', 'Unidades Vendidas']],
        body: unitsRows.length ? unitsRows : [['-', 'Sin datos', '-']],
        theme: 'striped',
        headStyles: { fillColor: [40, 199, 111], textColor: [255, 255, 255] },
        styles: { fontSize: 8 }
      });

      doc.save(`reporte-laboratorios-${startDate.value}_${endDate.value}.pdf`);
      toast.success('Reporte PDF descargado exitosamente');
    } catch (err) {
      console.error('Error al exportar PDF:', err);
      toast.error('Error al generar PDF');
    }
  };

  // Exportación a CSV
  const exportExecutiveCsv = () => {
    try {
      const revenueList = dashboardData.rankings.by_revenue?.data || [];
      if (!revenueList.length) {
        toast.info('No hay datos para exportar');
        return;
      }

      let csv = 'Posición,Laboratorio,Venta Bruta USD,Unidades Vendidas\n';
      revenueList.forEach((item, index) => {
        const unitsMatch = (dashboardData.rankings.by_units?.data || []).find(u => u.name === item.name);
        const units = unitsMatch ? unitsMatch.total_units : 0;
        csv += `${index + 1},"${item.name.replace(/"/g, '""')}",${item.total_revenue || 0},${units}\n`;
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `reporte-laboratorios-${startDate.value}_${endDate.value}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      toast.success('Reporte CSV descargado exitosamente');
    } catch (err) {
      console.error('Error al exportar CSV:', err);
      toast.error('Error al exportar CSV');
    }
  };

  return {
    loading,
    groupByCorporate,
    startDate,
    endDate,
    laboratories,
    dashboardData,
    summaryKpis,
    pageUnits,
    pageRevenue,
    pageStock,
    loadingUnits,
    loadingRevenue,
    loadingStock,
    labA,
    labB,
    benchmarkingData,
    loadingBenchmarking,
    selectedLabId,
    deepDiveData,
    loadingDeepDive,
    fetchCatalogs,
    fetchDashboard,
    fetchRankings,
    fetchBenchmarking,
    fetchDeepDive,
    resetComparisons,
    exportExecutivePdf,
    exportExecutiveCsv
  };
}
