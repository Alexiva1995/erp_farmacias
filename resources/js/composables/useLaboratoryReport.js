import { reactive, ref } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

export function useLaboratoryReport() {
  const loading = ref(false);
  const groupByCorporate = ref(false);
  const startDate = ref('2026-04-01');
  const endDate = ref(new Date().toISOString().split('T')[0]);

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

  return {
    loading,
    groupByCorporate,
    startDate,
    endDate,
    laboratories,
    dashboardData,
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
    resetComparisons
  };
}
