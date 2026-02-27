import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { nextTick, reactive, ref, watch } from 'vue';

export function useCyclicTable(endpointPrefix, filters) {
  const items = ref([]);
  const totalItems = ref(0);
  const loading = ref(false);
  const options = reactive({
    page: 1,
    itemsPerPage: 10,
    sortBy: undefined,
    orderBy: undefined,
  });

  // Modal de verificación (nuevo flujo único)
  const showVerifyModal = ref(false);
  const itemForVerification = ref(null);

  // Modal de distribución de lotes (se mantiene)
  const showLotDistributionModal = ref(false);
  const itemForLotDistribution = ref(null);
  const targetQuantityForDistribution = ref(0);
  const pendingVerifyData = ref(null);

  const fetchData = async () => {
    loading.value = true;
    const params = {
      q: filters.searchQuery,
      laboratoryId: filters.selectedLaboratory,
      discrepancyFilter: filters.discrepancyFilter,
      userId: filters.selectedUser,
      page: options.page,
      itemsPerPage: options.itemsPerPage,
      sortBy: options.sortBy,
      orderBy: options.orderBy,
    };
    Object.keys(params).forEach(
      (key) => (params[key] === null || params[key] === '' || params[key] === undefined) && delete params[key]
    );

    try {
      const response = await axios.get(`${endpointPrefix}/count`, { params });
      items.value = (response.data.data || []).map(item => ({
        ...item,
        product_id: item.product_id ?? item.product?.id ?? null,
        productId: item.product_id ?? item.product?.id ?? null,
      }));
      totalItems.value = response.data.total;
    } catch (error) {
      console.error(`Error al obtener datos de ${endpointPrefix}/count:`, error);
      toast.error('Error al obtener los datos de la tabla.');
    } finally {
      loading.value = false;
    }
  };

  let debounceTimer;
  watch(
    [options, filters],
    () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => fetchData(), 300);
    },
    { deep: true, immediate: true }
  );

  watch(() => ({...filters}), () => { options.page = 1; }, { deep: true });

  const updateTableOptions = (newOptions) => {
    options.page = newOptions.page;
    options.itemsPerPage = newOptions.itemsPerPage;
    options.sortBy = newOptions.sortBy?.[0]?.key;
    options.orderBy = newOptions.sortBy?.[0]?.order;
  };

  const callProcessApi = async (itemId, payload) => {
    try {
      const processEndpoint = endpointPrefix.replace('/products', '/inventory/count');
      const response = await axios.post(`${processEndpoint}/${itemId}/process`, payload);

      if (response.data.success) {
        toast.success(response.data.message);
        await fetchData();
      } else {
        toast.error(response.data.message);
      }
    } catch (error) {
      console.error("Error al procesar la acción:", error);
      const errorMessage = error.response?.data?.message || "Hubo un error al procesar la acción.";
      toast.error(errorMessage);
    } finally {
      showLotDistributionModal.value = false;
      itemForLotDistribution.value = null;
      pendingVerifyData.value = null;
    }
  };

  // Acción única: abrir modal de verificación
  const handleVerifyItem = async (item) => {
    itemForVerification.value = item;
    await nextTick();
    showVerifyModal.value = true;
  };

  // El supervisor confirmó que no hay discrepancia → aprobar con ajuste 0
  const onVerifyNoDiscrepancy = async ({ countRecord }) => {
    await callProcessApi(countRecord.id, { action: 'approve' });
  };

  // El supervisor encontró diferencia → abrir modal de lotes
  const onVerifyWithDiscrepancy = async ({ countRecord, newCountedQuantity }) => {
    pendingVerifyData.value = { countRecord, newCountedQuantity };
    itemForLotDistribution.value = countRecord.product;
    targetQuantityForDistribution.value = newCountedQuantity;
    await nextTick();
    showLotDistributionModal.value = true;
  };

  // El supervisor distribuyó los lotes → enviar al backend
  const handleLotsDistributed = async (distributionData) => {
    if (!pendingVerifyData.value) {
      toast.error("Error: no hay datos de verificación pendientes.");
      return;
    }
    const { countRecord, newCountedQuantity } = pendingVerifyData.value;
    const payload = {
      action: 'approve',
      corrected_quantity: newCountedQuantity,
      updated_lots: distributionData.updatedLots,
      new_lots: distributionData.newLots,
    };
    await callProcessApi(countRecord.id, payload);
  };

  return {
    items,
    totalItems,
    loading,
    options,
    updateTableOptions,
    fetchData,
    showVerifyModal,
    itemForVerification,
    showLotDistributionModal,
    itemForLotDistribution,
    targetQuantityForDistribution,
    handleVerifyItem,
    onVerifyNoDiscrepancy,
    onVerifyWithDiscrepancy,
    handleLotsDistributed,
  };
}
