import { ref, computed, watch } from 'vue'

export function useTpvState({ brandingStore, isRestaurant, isSportsRental }) {
  // --- Modo TPV ---
  const isSimpleTpv = computed(() => brandingStore.settings?.tpv_mode === 'simple')

  const defaultCurrency = computed(() => {
    if (isRestaurant.value || isSportsRental.value) return 'COP'
    const saved = localStorage.getItem('tpv_last_currency')
    if (saved && ['COP', 'USD', 'BS'].includes(saved.toUpperCase())) {
      return saved.toUpperCase()
    }
    return brandingStore.settings?.default_currency || 'COP'
  })

  const initialCurrency = localStorage.getItem('tpv_last_currency') || defaultCurrency.value
  const selectedDisplayCurrency = ref(initialCurrency)
  watch(selectedDisplayCurrency, (newVal) => { 
    if (newVal) {
      localStorage.setItem('tpv_last_currency', newVal.toUpperCase()) 
    }
  }, { immediate: true })

  // --- Estado de la sesión de orden ---
  const foreignOrdersCount = ref(0)
  const isFinishingOrder = ref(false)
  const isCurrencyChanging = ref(false)
  const activeReservationId = ref(null)
  const showRegisterClientModal = ref(false)
  const selectedClient = ref(null)
  const isLoadingInitialOrder = ref(true)
  const showBuysModal = ref(false)
  const hasOpenOrder = ref(false)
  const openOrderData = ref(null)
  const orderData = ref(null)
  const reservedOrderData = ref(null)
  const pendingOpenOrder = ref(null)
  const pendingQuotationProducts = ref([])
  const orderItems = ref([])

  // --- Estado del catálogo / filtros ---
  const page = ref(1)
  const itemsPerPage = ref(25)
  const sortBy = ref('name')
  const orderBy = ref('asc')
  const filterSearchQuery = ref('')
  const selectedLaboratory = ref(null)
  const selectedOrigin = ref(null)
  const stockStatusFilter = ref(null)
  const isStrictSearch = ref(false)
  const discount = ref(0)
  const discountMinProducts = ref(0)
  const discountMaxProducts = ref(0)
  const selectedCategory = ref(null)
  const currentGroupId = ref(null)
  const tableOptions = ref({ page: 1, itemsPerPage: 25, sortBy: [{ key: 'name', order: 'asc' }] })

  return {
    isSimpleTpv,
    defaultCurrency,
    selectedDisplayCurrency,
    foreignOrdersCount,
    isFinishingOrder,
    isCurrencyChanging,
    activeReservationId,
    showRegisterClientModal,
    selectedClient,
    isLoadingInitialOrder,
    showBuysModal,
    hasOpenOrder,
    openOrderData,
    orderData,
    reservedOrderData,
    pendingOpenOrder,
    pendingQuotationProducts,
    orderItems,
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    filterSearchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    isStrictSearch,
    discount,
    discountMinProducts,
    discountMaxProducts,
    selectedCategory,
    currentGroupId,
    tableOptions,
  }
}
