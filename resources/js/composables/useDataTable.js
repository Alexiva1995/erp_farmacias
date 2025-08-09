import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { reactive, ref, watch } from 'vue';

export function useDataTable(apiEndpoint, filters) {
  const items = ref([]);
  const totalItems = ref(0);
  const loading = ref(false);

  const options = reactive({
    page: 1,
    itemsPerPage: 10,
    sortBy: undefined,
    orderBy: undefined,
  });

  const fetchData = async () => {
    loading.value = true;
    
    const params = {
      ...filters,
      page: options.page,
      itemsPerPage: options.itemsPerPage,
      sortBy: options.sortBy,
      orderBy: options.orderBy,
    };

    Object.keys(params).forEach(
      (key) => (params[key] === null || params[key] === '' || params[key] === undefined) && delete params[key]
    );

    try {
      const response = await axios.get(apiEndpoint, { params });
      items.value = response.data.data;
      totalItems.value = response.data.total;
    } catch (error) {
      console.error(`Error al obtener datos de ${apiEndpoint}:`, error);
      toast.error('Error al obtener los datos de la tabla.');
      items.value = [];
      totalItems.value = 0;
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
    { deep: true }
  );

  watch(
    () => ({ ...filters }),
    () => {
      options.page = 1;
    },
    { deep: true }
  );

  const updateTableOptions = (newOptions) => {
    options.page = newOptions.page;
    options.itemsPerPage = newOptions.itemsPerPage;
    options.sortBy = newOptions.sortBy?.[0]?.key;
    options.orderBy = newOptions.sortBy?.[0]?.order;
  };
  
  return {
    items,
    totalItems,
    loading,
    options,
    fetchData,
    updateTableOptions,
  };
}
