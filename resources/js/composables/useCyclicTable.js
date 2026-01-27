import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { reactive, ref, watch, nextTick } from 'vue';

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

  const showCorrectionModal = ref(false);
  const selectedItemForCorrection = ref(null);
  const showLotDistributionModal = ref(false);
  const itemForLotDistribution = ref(null);
  const targetQuantityForDistribution = ref(0);
  const pendingAction = ref({ type: null, data: {} });

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
      items.value = response.data.data;
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
      // Fix: Ensure we target /inventory/count when using /products prefix
      const processEndpoint = endpointPrefix.replace('/products', '/inventory/count');
      console.log("processEndpoint:", processEndpoint);
      
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
      showCorrectionModal.value = false;
      showLotDistributionModal.value = false;
      selectedItemForCorrection.value = null;
      itemForLotDistribution.value = null;
      pendingAction.value = { type: null, data: {} };
    }
  };

  const handleApproveItem = async (item) => {
    const discrepancy = item.discrepancy;
    if (discrepancy !== 0 && item.product.lots && item.product.lots.length > 0) {
      itemForLotDistribution.value = item.product;
      targetQuantityForDistribution.value = item.counted_quantity;
      pendingAction.value = { type: "approve" };
      await nextTick();
      showLotDistributionModal.value = true;
    } else {
      await callProcessApi(item.id, { action: "approve" });
    }
  };

  const handleRejectItem = async (item) => {
    // Primero establecer el item, luego abrir el modal
    selectedItemForCorrection.value = item;
    // Usar nextTick para asegurar que el componente se actualice
    await nextTick();
    showCorrectionModal.value = true;
  };

  const handleCorrectionProcessed = async (correctionData) => {
    const item = selectedItemForCorrection.value;
    const newDiscrepancy = correctionData.correctedQuantity - item.system_quantity;
    const payload = {
      action: "reject",
      corrected_quantity: correctionData.correctedQuantity,
      original_quantity: correctionData.originalQuantity,
    };

    if (newDiscrepancy !== 0 && item.product.lots && item.product.lots.length > 0) {
      itemForLotDistribution.value = item.product;
      targetQuantityForDistribution.value = correctionData.correctedQuantity;
      pendingAction.value = { type: "reject", data: payload };
      showCorrectionModal.value = false;
      await nextTick();
      showLotDistributionModal.value = true;
    } else {
      await callProcessApi(item.id, payload);
    }
  };

  const handleLotsDistributed = async (distributionData) => {
    const product = itemForLotDistribution.value;
    let finalPayload = {};

    const payloadLots = {
        updated_lots: distributionData.updatedLots,
        new_lots: distributionData.newLots,
    };
    
    if (pendingAction.value.type === "approve") {
      finalPayload = { action: "approve", ...payloadLots };
    } else if (pendingAction.value.type === "reject") {
      finalPayload = { ...pendingAction.value.data, ...payloadLots };
    }

    let originalCountRecord = selectedItemForCorrection.value || items.value.find(p => p.product_id === product.id);

    if (originalCountRecord) {
      await callProcessApi(originalCountRecord.id, finalPayload);
    } else {
      toast.error("Error crítico: No se pudo encontrar el registro del conteo original.");
    }
  };

  return {
    items,
    totalItems,
    loading,
    options,
    updateTableOptions,
    fetchData,
    showCorrectionModal,
    selectedItemForCorrection,
    showLotDistributionModal,
    itemForLotDistribution,
    targetQuantityForDistribution,
    handleApproveItem,
    handleRejectItem,
    handleCorrectionProcessed,
    handleLotsDistributed,
  };
}
