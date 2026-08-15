import { ref, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvCatalog({
  filterSearchQuery,
  selectedLaboratory,
  selectedOrigin,
  selectedCategory,
  stockStatusFilter,
  isStrictSearch,
  sortBy,
  orderBy,
  page,
  itemsPerPage,
  currentGroupId,
  tableOptions,
}) {
  const products = ref([])
  const totalProduct = ref(0)
  const loading = ref(false)

  const laboratories = ref([])
  const origins = ref([])
  const categories = ref([])
  const isLoadingFilters = ref(false)

  let activeAbortController = null

  const fetchProducts = async () => {
    if (activeAbortController) {
      activeAbortController.abort()
    }
    activeAbortController = new AbortController()

    loading.value = true
    const params = {
      q: filterSearchQuery.value,
      categoryId: selectedCategory.value,
      laboratoryId: selectedLaboratory.value,
      originId: selectedOrigin.value,
      ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      ...(currentGroupId.value !== null && { groupId: currentGroupId.value }),
      isStrictSearch: isStrictSearch.value,
    }
    Object.keys(params).forEach((key) => (params[key] === null || params[key] === '') && delete params[key])

    try {
      const response = await axios.get('/tpv/order', {
        params,
        signal: activeAbortController.signal,
      })
      products.value = response.data.data
      totalProduct.value = response.data.total
    } catch (error) {
      if (axios.isCancel(error) || error.name === 'CanceledError' || error.name === 'AbortError') {
        return
      }
      if (error.response?.status === 401) {
        window.location.reload()
      } else if (error.response?.status === 429) {
        toast.warning('Límite de peticiones alcanzado. Espera un momento.')
      } else {
        toast.error('Error al obtener los productos.')
      }
    } finally {
      if (activeAbortController && !activeAbortController.signal.aborted) {
        loading.value = false
      }
    }
  }

  const fetchSelectOptions = async () => {
    isLoadingFilters.value = true
    try {
      const [labResponse, originResponse, catResponse] = await Promise.all([
        axios.get('/laboratories'),
        axios.get('/origins'),
        axios.get('/categories'),
      ])
      laboratories.value = labResponse.data
      origins.value = originResponse.data
      categories.value = catResponse.data
    } catch (error) {
      if (error.response?.status === 401) {
        window.location.reload()
      } else {
        toast.error('No se pudieron cargar los filtros.')
      }
    } finally {
      isLoadingFilters.value = false
    }
  }

  const handleClearFilters = () => {
    filterSearchQuery.value = ''
    selectedLaboratory.value = null
    selectedOrigin.value = null
    selectedCategory.value = null
    stockStatusFilter.value = null
    isStrictSearch.value = false
    sortBy.value = undefined
    orderBy.value = undefined
    if (tableOptions) tableOptions.value.sortBy = []
  }

  const handleClearSortOrder = () => {
    sortBy.value = undefined
    orderBy.value = undefined
    if (tableOptions) tableOptions.value.sortBy = []
  }

  const updateTableOptions = (options) => {
    page.value = options.page
    itemsPerPage.value = options.itemsPerPage
    if (options.sortBy && options.sortBy.length > 0) {
      sortBy.value = options.sortBy[0].key
      orderBy.value = options.sortBy[0].order
    }
  }

  const handleSort = (sortOptions) => {
    sortBy.value = sortOptions.key
    orderBy.value = sortOptions.order
  }

  const fetchGroupProducts = async (groupId) => {
    if (!groupId) {
      toast.info('Este producto no pertenece a un grupo.')
      if (currentGroupId.value !== null) {
        currentGroupId.value = null
      }
      return
    }
    currentGroupId.value = groupId
  }

  const fetchFailuresProducts = async (productId) => {
    try {
      await axios.post('/tpv/product-failure', { product_id: productId })
      toast.info('Reporte de falla guardado correctamente.')
    } catch (error) {
      if (error.response) {
        console.error('Errores de validación:', error.response.data.errors)
        toast.error('Hubo un problema al procesar su reporte de falla.')
      } else {
        console.error('Error de conexión:', error.message)
      }
    }
  }

  const handleBackFromGroupView = () => {
    currentGroupId.value = null
  }

  const handleExternalSort = async (sortData) => {
    sortBy.value = sortData.key
    orderBy.value = sortData.order
    if (tableOptions) tableOptions.value.sortBy = [{ key: sortData.key, order: sortData.order }]
    try {
      await axios.post('/user/update-sort-config', {
        sortBy: sortData.key,
        orderBy: sortData.order,
      })
      toast.success('Orden guardada como preferida')
    } catch (error) {
      console.error('Error al guardar preferencia:', error)
    }
  }

  let debounceTimer
  watch(
    [
      page,
      itemsPerPage,
      sortBy,
      orderBy,
      filterSearchQuery,
      selectedLaboratory,
      selectedOrigin,
      selectedCategory,
      stockStatusFilter,
      isStrictSearch,
      currentGroupId,
    ],
    () => {
      clearTimeout(debounceTimer)
      debounceTimer = setTimeout(() => {
        try {
          fetchProducts()
        } catch (error) {
          console.error('Error en watcher de productos:', error)
        }
      }, 400)
    },
    { deep: false }
  )

  watch(
    [filterSearchQuery, selectedLaboratory, selectedOrigin, selectedCategory, stockStatusFilter],
    () => {
      if (page.value !== 1) {
        page.value = 1
      }
    }
  )

  return {
    products,
    totalProduct,
    loading,
    laboratories,
    origins,
    categories,
    isLoadingFilters,
    fetchProducts,
    fetchSelectOptions,
    handleClearFilters,
    handleClearSortOrder,
    updateTableOptions,
    handleSort,
    fetchGroupProducts,
    fetchFailuresProducts,
    handleBackFromGroupView,
    handleExternalSort,
  }
}
