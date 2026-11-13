<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { formatCurrency as formatRawCurrency } from '@/utils/currencyFormatter'

const authStore = useAuthStore()
const brandingStore = useBrandingStore()

const isApprover = computed(() => Boolean(authStore.isAdmin || authStore.isSupervisor))

const defaultCurrency = computed(() => brandingStore.settings?.default_currency || 'USD')

const activeExchangeRate = computed(() => {
  const currency = defaultCurrency.value
  if (currency === 'USD') return 1

  const rates = Array.isArray(brandingStore.exchangeRates) ? brandingStore.exchangeRates : []

  if (currency === 'COP') {
    const copObj = rates.find(r => r && (r.currency_code === 'COP' || r.currency_code === 'COPC'))
    return Number(copObj?.rate || 4000)
  }

  if (currency === 'BS' || currency === 'Bs') {
    const bsObj = rates.find(r => r && r.currency_code === 'BS')
    return Number(bsObj?.rate || 1)
  }

  return 1
})

const formatCurrency = price => {
  const numPrice = Number(price)
  if (isNaN(numPrice)) return '—'

  const currency = defaultCurrency.value
  if (currency === 'USD') {
    return formatRawCurrency(numPrice, 'USD')
  }

  const rate = activeExchangeRate.value
  if (currency === 'COP') {
    const rawCop = numPrice * rate
    if (rawCop > 0 && rawCop < 100) {
      return formatRawCurrency(Math.round(rawCop), 'COP')
    }
    const cleanInt = Math.round(rawCop)
    const rounded = cleanInt % 100 === 0 ? cleanInt : Math.ceil(cleanInt / 100) * 100
    return formatRawCurrency(rounded, 'COP')
  }

  if (currency === 'BS' || currency === 'Bs') {
    return formatRawCurrency(numPrice * rate, 'BS')
  }

  return formatRawCurrency(numPrice, currency)
}

// Estado del listado y filtros avanzados
const wastes = ref([])
const loading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const totalItems = ref(0)
const statusFilter = ref('all')
const itemTypeFilter = ref('all')
const reasonFilter = ref('all')
const stationFilter = ref('all')
const datePreset = ref('month')
const dateFrom = ref('')
const dateTo = ref('')
const searchQuery = ref('')

// Catálogos auxiliares
const kitchenStations = ref([])
const dishesCatalog = ref([])
const loadingDishes = ref(false)

const summary = reactive({
  pending_count: 0,
  approved_count: 0,
  rejected_count: 0,
  total_loss_approved_usd: 0,
  total_loss_pending_usd: 0,
  operational_loss_approved_usd: 0,
  operational_approved_count: 0,
  internal_consumption_approved_usd: 0,
  internal_approved_count: 0,
})

// Estado del modal de reporte de merma (Hoja de Merma unificada)
const showReportModal = ref(false)
const submitting = ref(false)
const loadingProductById = ref(false)
const productNotFoundError = ref('')
const resolvedProduct = ref(null)
const resolvedDish = ref(null)

// Buscador de producto por nombre
const productSearch = ref('')
const productOptions = ref([])
const loadingProductOptions = ref(false)
const selectedProductOption = ref(null)
const selectedDishOption = ref(null)

const wasteForm = reactive({
  item_type: 'product',
  product_id: '',
  dish_id: null,
  quantity: null,
  reason: 'burned_spoiled',
  notes: '',
  photo: null,
})

// Renglones acumulados en la Hoja de Merma
const batchItems = ref([])

const formErrors = reactive({
  product_id: '',
  dish_id: '',
  quantity: '',
  notes: '',
  photo: '',
})

const photoPreviewUrl = ref(null)

// Estado para visor de foto y detalles de escandallo
const showPhotoViewer = ref(false)
const selectedWasteForPhoto = ref(null)

// Estado para modal de rechazo
const showRejectModal = ref(false)
const rejectingWaste = ref(null)
const rejectionReason = ref('')
const processingActionId = ref(null)

const wasteReasons = [
  { label: 'Quemado / Malogrado', value: 'burned_spoiled', requiresPhoto: true, category: 'Merma Operativa (Desperdicio)' },
  { label: 'Vencimiento / Caducado', value: 'expired', requiresPhoto: true, category: 'Merma Operativa (Desperdicio)' },
  { label: 'Caída / Accidente', value: 'dropped', requiresPhoto: true, category: 'Merma Operativa (Desperdicio)' },
  { label: 'Merma de Limpieza / Cocción', value: 'preparation_trim', requiresPhoto: false, category: 'Merma Operativa (Desperdicio)' },
  { label: 'Comida de Personal', value: 'staff_meal', requiresPhoto: false, category: 'Consumo Interno / Comercial' },
  { label: 'Cortesía de la Casa', value: 'courtesy', requiresPhoto: false, category: 'Consumo Interno / Comercial' },
]

const currentReasonObj = computed(() =>
  wasteReasons.find(r => r.value === wasteForm.reason) || wasteReasons[0]
)

// Indica si el formulario actual exige fotografía obligatoria
const isPhotoRequired = computed(() => {
  if (batchItems.value.length > 0) {
    return batchItems.value.some(item => {
      const r = wasteReasons.find(wr => wr.value === item.reason)
      return Boolean(r?.requiresPhoto)
    })
  }
  return Boolean(currentReasonObj.value?.requiresPhoto)
})

// Costo unitario unificado según el ítem seleccionado (SIEMPRE desde products.unit_cost)
const activeUnifiedUnitCost = computed(() => {
  if (wasteForm.item_type === 'product') {
    return Number(resolvedProduct.value?.unit_cost || 0)
  }
  if (wasteForm.item_type === 'dish') {
    return Number(resolvedDish.value?.unified_cost || 0)
  }
  return 0
})

const estimatedLossUsd = computed(() => {
  const qty = Number(wasteForm.quantity) || 0
  if (qty <= 0) return 0
  return parseFloat((qty * activeUnifiedUnitCost.value).toFixed(2))
})

const batchTotalLossUsd = computed(() =>
  batchItems.value.reduce((acc, item) => acc + (Number(item.total_loss_usd) || 0), 0)
)

// Formatear fecha YYYY-MM-DD local
const formatDateInput = dateObj => {
  const year = dateObj.getFullYear()
  const month = String(dateObj.getMonth() + 1).padStart(2, '0')
  const day = String(dateObj.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const applyDatePreset = preset => {
  datePreset.value = preset
  const now = new Date()

  if (preset === 'today') {
    const todayStr = formatDateInput(now)
    dateFrom.value = todayStr
    dateTo.value = todayStr
  } else if (preset === 'week') {
    const dayOfWeek = now.getDay()
    const diffToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek
    const monday = new Date(now)
    monday.setDate(now.getDate() + diffToMonday)
    dateFrom.value = formatDateInput(monday)
    dateTo.value = formatDateInput(now)
  } else if (preset === 'month') {
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
    dateFrom.value = formatDateInput(firstDay)
    dateTo.value = formatDateInput(now)
  } else {
    dateFrom.value = ''
    dateTo.value = ''
  }
}

// Cargar estaciones de cocina
const fetchKitchenStations = async () => {
  try {
    const { data } = await axios.get('/kitchen-stations')
    kitchenStations.value = Array.isArray(data) ? data : (data.data || [])
  } catch (error) {
    console.error('Error cargando estaciones de cocina:', error)
  }
}

// Cargar platillos y sub-recetas calculando su costo con el costo unificado de products
const fetchDishesCatalog = async () => {
  loadingDishes.value = true
  try {
    const { data } = await axios.get('/dishes', {
      params: { itemsPerPage: 500 },
    })
    const rawDishes = data?.data || (Array.isArray(data) ? data : [])
    dishesCatalog.value = rawDishes.map(dish => {
      const ingredients = Array.isArray(dish.ingredients) ? dish.ingredients : []
      let unifiedRecipeCost = 0
      const mappedIngredients = ingredients.map(ing => {
        const unifiedIngCost = Number(ing.unit_cost ?? ing.cost_price ?? 0)
        const portion = Number(ing.pivot?.portion ?? ing.portion ?? 0)
        unifiedRecipeCost += unifiedIngCost * portion
        return {
          id: ing.id,
          name: ing.name,
          unit_of_measure: ing.unit_of_measure || 'und',
          portion,
          unified_unit_cost: unifiedIngCost,
          stock: Number(ing.stock ?? 0),
        }
      })

      const isSubRecipe = Number(dish.status) === 3
      const yieldQty = Number(dish.expected_yield_quantity) || 1
      if (isSubRecipe && yieldQty > 0 && unifiedRecipeCost > 0) {
        unifiedRecipeCost = unifiedRecipeCost / yieldQty
      }

      const finalUnifiedCost = unifiedRecipeCost > 0
        ? parseFloat(unifiedRecipeCost.toFixed(4))
        : Number(dish.cost_price || dish.cpv || 0)

      return {
        id: dish.id,
        name: dish.name,
        is_sub_recipe: isSubRecipe,
        type_badge: isSubRecipe ? 'Sub-receta (Prep)' : 'Platillo de Menú',
        label: `#${dish.id} - ${dish.name} (${isSubRecipe ? 'Sub-receta' : 'Platillo'})`,
        unit_of_measure: dish.unit_of_measure || (isSubRecipe ? 'porción/lote' : 'plato'),
        unified_cost: finalUnifiedCost,
        ingredients: mappedIngredients,
      }
    })
  } catch (error) {
    console.error('Error cargando catálogo de platillos:', error)
  } finally {
    loadingDishes.value = false
  }
}

// Cargar registros de mermas
const fetchWastes = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/restaurant/waste/records', {
      params: {
        page: page.value,
        per_page: 15,
        status: statusFilter.value,
        item_type: itemTypeFilter.value !== 'all' ? itemTypeFilter.value : undefined,
        reason: reasonFilter.value !== 'all' ? reasonFilter.value : undefined,
        kitchen_station_id: stationFilter.value !== 'all' ? stationFilter.value : undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        q: searchQuery.value || undefined,
      },
    })

    wastes.value = data.data || []
    lastPage.value = data.last_page || 1
    totalItems.value = data.total || wastes.value.length

    if (data.summary) {
      summary.pending_count = data.summary.pending_count || 0
      summary.approved_count = data.summary.approved_count || 0
      summary.rejected_count = data.summary.rejected_count || 0
      summary.total_loss_approved_usd = data.summary.total_loss_approved_usd || 0
      summary.total_loss_pending_usd = data.summary.total_loss_pending_usd || 0
      summary.operational_loss_approved_usd = data.summary.operational_loss_approved_usd || 0
      summary.operational_approved_count = data.summary.operational_approved_count || 0
      summary.internal_consumption_approved_usd = data.summary.internal_consumption_approved_usd || 0
      summary.internal_approved_count = data.summary.internal_approved_count || 0
    }
  } catch (error) {
    console.error('Error al cargar mermas:', error)
    toast.error('No se pudieron cargar los registros de mermas.')
  } finally {
    loading.value = false
  }
}

let filterTimer = null
watch(
  [statusFilter, itemTypeFilter, reasonFilter, stationFilter, dateFrom, dateTo, searchQuery],
  () => {
    page.value = 1
    clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
      fetchWastes()
    }, 300)
  }
)

watch(page, () => {
  fetchWastes()
})

// Consultar producto por su ID directo (toma siempre unit_cost de la tabla products)
let productIdTimer = null
const lookupProductById = async idValue => {
  const cleanId = String(idValue || '').trim()
  if (!cleanId || isNaN(Number(cleanId)) || Number(cleanId) <= 0) {
    resolvedProduct.value = null
    productNotFoundError.value = ''
    return
  }

  loadingProductById.value = true
  productNotFoundError.value = ''
  try {
    const { data } = await axios.get(`/product/${Number(cleanId)}`)
    const prod = data?.data || data
    if (prod && prod.id) {
      resolvedProduct.value = {
        id: prod.id,
        name: prod.name,
        stock: Number(prod.stock ?? 0),
        unit_of_measure: prod.unit_of_measure || 'und',
        unit_cost: Number(prod.unit_cost ?? prod.cost_price ?? 0),
      }
      formErrors.product_id = ''
    } else {
      resolvedProduct.value = null
      productNotFoundError.value = `No se encontró ningún producto con el ID #${cleanId}.`
    }
  } catch (error) {
    resolvedProduct.value = null
    productNotFoundError.value = `No existe un producto con el ID #${cleanId}.`
  } finally {
    loadingProductById.value = false
  }
}

watch(
  () => wasteForm.product_id,
  newId => {
    if (wasteForm.item_type !== 'product') return
    clearTimeout(productIdTimer)
    if (selectedProductOption.value && Number(selectedProductOption.value.id) === Number(newId)) {
      return
    }
    productIdTimer = setTimeout(() => {
      lookupProductById(newId)
    }, 350)
  }
)

// Búsqueda asistida de productos por nombre
let productSearchTimer = null
const searchProductsByName = async (query = '') => {
  loadingProductOptions.value = true
  try {
    const { data } = await axios.get('/products/autocomplete', {
      params: { q: query },
    })
    productOptions.value = (data.data || []).map(item => ({
      id: item.id,
      name: item.name,
      label: `#${item.id} - ${item.name}`,
      stock: Number(item.stock ?? 0),
      unit_of_measure: item.unit_of_measure || 'und',
      unit_cost: Number(item.unit_cost ?? item.cost_price ?? 0),
    }))
  } catch (error) {
    console.error('Error buscando productos:', error)
  } finally {
    loadingProductOptions.value = false
  }
}

watch(productSearch, val => {
  clearTimeout(productSearchTimer)
  productSearchTimer = setTimeout(() => {
    searchProductsByName(val || '')
  }, 300)
})

const onSelectProductOption = item => {
  if (!item) {
    resolvedProduct.value = null
    wasteForm.product_id = ''
    return
  }
  wasteForm.product_id = item.id
  resolvedProduct.value = {
    id: item.id,
    name: item.name,
    stock: item.stock,
    unit_of_measure: item.unit_of_measure,
    unit_cost: item.unit_cost,
  }
  productNotFoundError.value = ''
  formErrors.product_id = ''
}

const onSelectDishOption = dish => {
  if (!dish) {
    resolvedDish.value = null
    wasteForm.dish_id = null
    return
  }
  wasteForm.dish_id = dish.id
  resolvedDish.value = dish
  formErrors.dish_id = ''
}

// Manejo de archivo de foto y previsualización
const onPhotoSelected = files => {
  if (photoPreviewUrl.value) {
    URL.revokeObjectURL(photoPreviewUrl.value)
    photoPreviewUrl.value = null
  }

  const file = Array.isArray(files) ? files[0] : files
  if (file instanceof File) {
    wasteForm.photo = file
    photoPreviewUrl.value = URL.createObjectURL(file)
    formErrors.photo = ''
  } else {
    wasteForm.photo = null
  }
}

const resetItemInputs = () => {
  wasteForm.product_id = ''
  wasteForm.dish_id = null
  wasteForm.quantity = null
  wasteForm.notes = ''
  resolvedProduct.value = null
  resolvedDish.value = null
  selectedProductOption.value = null
  selectedDishOption.value = null
  productNotFoundError.value = ''
  formErrors.product_id = ''
  formErrors.dish_id = ''
  formErrors.quantity = ''
  formErrors.notes = ''
}

const resetReportForm = () => {
  wasteForm.item_type = 'product'
  wasteForm.reason = 'burned_spoiled'
  wasteForm.photo = null
  batchItems.value = []
  formErrors.photo = ''
  resetItemInputs()
  if (photoPreviewUrl.value) {
    URL.revokeObjectURL(photoPreviewUrl.value)
    photoPreviewUrl.value = null
  }
}

const openReportModal = () => {
  resetReportForm()
  searchProductsByName('')
  if (dishesCatalog.value.length === 0) {
    fetchDishesCatalog()
  }
  showReportModal.value = true
}

const validateSingleLine = () => {
  let valid = true
  formErrors.product_id = ''
  formErrors.dish_id = ''
  formErrors.quantity = ''
  formErrors.notes = ''

  if (wasteForm.item_type === 'product') {
    if (!wasteForm.product_id || Number(wasteForm.product_id) <= 0) {
      formErrors.product_id = 'Selecciona un insumo o ingresa su ID.'
      valid = false
    } else if (productNotFoundError.value) {
      formErrors.product_id = productNotFoundError.value
      valid = false
    }
  } else {
    if (!wasteForm.dish_id) {
      formErrors.dish_id = 'Selecciona el platillo o sub-receta afectada.'
      valid = false
    }
  }

  if (!wasteForm.quantity || Number(wasteForm.quantity) <= 0) {
    formErrors.quantity = 'Ingresa una cantidad perdida mayor a 0.'
    valid = false
  }

  if (!wasteForm.notes || !String(wasteForm.notes).trim()) {
    formErrors.notes = 'Escribe una breve descripción de lo ocurrido.'
    valid = false
  }

  return valid
}

// Agregar renglón a la Hoja de Merma en Lote
const addLineToBatch = () => {
  if (!validateSingleLine()) return

  const reasonObj = wasteReasons.find(r => r.value === wasteForm.reason) || wasteReasons[0]
  const qty = Number(wasteForm.quantity)
  const unitCost = activeUnifiedUnitCost.value
  const totalLoss = parseFloat((qty * unitCost).toFixed(2))

  batchItems.value.push({
    item_type: wasteForm.item_type,
    product_id: wasteForm.item_type === 'product' ? Number(wasteForm.product_id) : null,
    dish_id: wasteForm.item_type === 'dish' ? Number(wasteForm.dish_id) : null,
    item_name: wasteForm.item_type === 'product'
      ? (resolvedProduct.value?.name || `Producto #${wasteForm.product_id}`)
      : (resolvedDish.value?.name || `Platillo #${wasteForm.dish_id}`),
    unit_of_measure: wasteForm.item_type === 'product'
      ? (resolvedProduct.value?.unit_of_measure || 'und')
      : (resolvedDish.value?.unit_of_measure || 'plato'),
    quantity: qty,
    unit_cost: unitCost,
    total_loss_usd: totalLoss,
    reason: wasteForm.reason,
    reason_label: reasonObj.label,
    notes: String(wasteForm.notes).trim(),
  })

  resetItemInputs()
}

const removeBatchLine = index => {
  batchItems.value.splice(index, 1)
}

const submitWasteReport = async () => {
  formErrors.photo = ''

  if (batchItems.value.length === 0) {
    if (!validateSingleLine()) return
  }

  if (isPhotoRequired.value && !wasteForm.photo) {
    formErrors.photo = 'La fotografía de evidencia es obligatoria para mermas físicas (Quemado, Vencido o Caída).'
    return
  }

  submitting.value = true
  try {
    const formData = new FormData()

    if (batchItems.value.length > 0) {
      formData.append('items', JSON.stringify(batchItems.value))
    } else {
      formData.append('item_type', wasteForm.item_type)
      if (wasteForm.item_type === 'product') {
        formData.append('product_id', String(wasteForm.product_id))
      } else {
        formData.append('dish_id', String(wasteForm.dish_id))
      }
      formData.append('quantity', String(wasteForm.quantity))
      formData.append('reason', wasteForm.reason)
      formData.append('notes', String(wasteForm.notes).trim())
    }

    if (wasteForm.photo instanceof File) {
      formData.append('photo', wasteForm.photo)
    }

    const { data } = await axios.post('/restaurant/waste/records', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    toast.success(data.message || 'Reporte de merma procesado correctamente.')
    showReportModal.value = false
    resetReportForm()
    await fetchWastes()
  } catch (error) {
    const errors = error.response?.data?.errors
    if (errors) {
      formErrors.product_id = errors.product_id?.[0] || ''
      formErrors.dish_id = errors.dish_id?.[0] || ''
      formErrors.quantity = errors.quantity?.[0] || ''
      formErrors.notes = errors.notes?.[0] || ''
      formErrors.photo = errors.photo?.[0] || ''
    }
    toast.error(error.response?.data?.message || 'Error al registrar el reporte de merma.')
  } finally {
    submitting.value = false
  }
}

// Visualizar foto y detalles
const openPhotoDialog = waste => {
  selectedWasteForPhoto.value = waste
  showPhotoViewer.value = true
}

// Aprobar merma (Admin/Supervisor)
const handleApproveWaste = async waste => {
  const itemCode = waste.item_type === 'product' ? `#${waste.product_id}` : `Platillo #${waste.dish_id}`
  const confirmResult = await Swal.fire({
    title: '¿Aprobar merma y descontar inventario?',
    html: `Se descontarán <b>${waste.quantity} ${waste.product_unit || 'und'}</b> de <b>${itemCode} - ${waste.item_name}</b> valorizados al <b>costo unificado de la tabla productos (${formatCurrency(waste.unified_unit_cost || waste.unit_cost)})</b>.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, aprobar y descontar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  })

  if (!confirmResult.isConfirmed) return

  processingActionId.value = waste.id
  try {
    const { data } = await axios.post(`/restaurant/waste/records/${waste.id}/approve`)
    toast.success(data.message || 'Merma aprobada y descontada del inventario como pérdida.')
    if (showPhotoViewer.value) {
      showPhotoViewer.value = false
    }
    await fetchWastes()
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo aprobar la merma.')
  } finally {
    processingActionId.value = null
  }
}

// Abrir modal de rechazo (Admin/Supervisor)
const openRejectDialog = waste => {
  rejectingWaste.value = waste
  rejectionReason.value = ''
  showRejectModal.value = true
}

const confirmRejectWaste = async () => {
  if (!rejectingWaste.value) return

  processingActionId.value = rejectingWaste.value.id
  try {
    const { data } = await axios.post(`/restaurant/waste/records/${rejectingWaste.value.id}/reject`, {
      rejection_reason: rejectionReason.value ? String(rejectionReason.value).trim() : null,
    })
    toast.success(data.message || 'Merma rechazada.')
    showRejectModal.value = false
    if (showPhotoViewer.value) {
      showPhotoViewer.value = false
    }
    await fetchWastes()
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo rechazar la merma.')
  } finally {
    processingActionId.value = null
  }
}

onMounted(() => {
  brandingStore.fetchSettings?.()
  applyDatePreset('month')
  if (isApprover.value) {
    statusFilter.value = 'all'
  }
  fetchKitchenStations()
  fetchWastes()
})

onUnmounted(() => {
  clearTimeout(filterTimer)
  clearTimeout(productIdTimer)
  clearTimeout(productSearchTimer)
  if (photoPreviewUrl.value) {
    URL.revokeObjectURL(photoPreviewUrl.value)
  }
})
</script>

<template>
  <div>
    <!-- Tarjetas de Resumen Operativo (Estilo Vuexy del Sistema) -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <span class="text-body-2 text-medium-emphasis">Pendientes de Revisión</span>
              <h4 class="text-h4 font-weight-bold text-warning my-1">
                {{ summary.pending_count }}
              </h4>
              <span class="text-caption text-medium-emphasis">
                En espera: {{ formatCurrency(summary.total_loss_pending_usd) }}
              </span>
            </div>
            <VAvatar color="warning" variant="tonal" rounded size="44">
              <VIcon icon="tabler-clock-exclamation" size="26" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <span class="text-body-2 text-medium-emphasis">Merma Operativa</span>
              <h4 class="text-h4 font-weight-bold text-error my-1">
                -{{ formatCurrency(summary.operational_loss_approved_usd) }}
              </h4>
              <span class="text-caption text-medium-emphasis">
                {{ summary.operational_approved_count }} bajas (Desperdicio)
              </span>
            </div>
            <VAvatar color="error" variant="tonal" rounded size="44">
              <VIcon icon="tabler-flame" size="26" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <span class="text-body-2 text-medium-emphasis">Personal y Cortesías</span>
              <h4 class="text-h4 font-weight-bold text-info my-1">
                -{{ formatCurrency(summary.internal_consumption_approved_usd) }}
              </h4>
              <span class="text-caption text-medium-emphasis">
                {{ summary.internal_approved_count }} registros internos
              </span>
            </div>
            <VAvatar color="info" variant="tonal" rounded size="44">
              <VIcon icon="tabler-tools-kitchen-2" size="26" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <span class="text-body-2 text-medium-emphasis">Total Aprobado ({{ summary.approved_count }})</span>
              <h4 class="text-h4 font-weight-bold text-primary my-1">
                -{{ formatCurrency(summary.total_loss_approved_usd) }}
              </h4>
              <span class="text-caption text-medium-emphasis">
                Rechazadas: {{ summary.rejected_count }}
              </span>
            </div>
            <VAvatar color="primary" variant="tonal" rounded size="44">
              <VIcon icon="tabler-chart-pie" size="26" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Card Principal (Mismo estilo que Inventario de Productos) -->
    <VCard>
      <VCardItem class="pb-4">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" rounded size="40">
            <VIcon icon="tabler-trash-x" size="22" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h5">
          Control de Mermas y Desperdicios
        </VCardTitle>
        <VCardSubtitle>
          Valorización por Costo Unificado (Tabla Productos) • Insumos, Sub-recetas y Platillos
        </VCardSubtitle>
        <template #append>
          <div class="d-flex flex-wrap align-center gap-2">
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-refresh"
              :loading="loading"
              @click="fetchWastes"
            >
              Actualizar
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              @click="openReportModal"
            >
              Reportar Merma
            </VBtn>
          </div>
        </template>
      </VCardItem>

      <VDivider />

      <!-- Pestañas de Estado y Atajos de Fecha -->
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
        <VTabs v-model="statusFilter" density="compact" color="primary">
          <VTab value="all">
            Todas
          </VTab>
          <VTab value="pending">
            <VIcon start size="18" icon="tabler-clock" />
            Pendientes
            <VChip
              v-if="summary.pending_count > 0"
              size="x-small"
              color="warning"
              variant="flat"
              class="ms-2 font-weight-bold"
            >
              {{ summary.pending_count }}
            </VChip>
          </VTab>
          <VTab value="approved">
            <VIcon start size="18" icon="tabler-check" />
            Aprobadas
          </VTab>
          <VTab value="rejected">
            <VIcon start size="18" icon="tabler-x" />
            Rechazadas
          </VTab>
        </VTabs>

        <div class="d-flex flex-wrap align-center gap-1">
          <VBtn
            size="small"
            :variant="datePreset === 'today' ? 'flat' : 'tonal'"
            :color="datePreset === 'today' ? 'primary' : 'secondary'"
            @click="applyDatePreset('today')"
          >
            Hoy
          </VBtn>
          <VBtn
            size="small"
            :variant="datePreset === 'week' ? 'flat' : 'tonal'"
            :color="datePreset === 'week' ? 'primary' : 'secondary'"
            @click="applyDatePreset('week')"
          >
            Esta Semana
          </VBtn>
          <VBtn
            size="small"
            :variant="datePreset === 'month' ? 'flat' : 'tonal'"
            :color="datePreset === 'month' ? 'primary' : 'secondary'"
            @click="applyDatePreset('month')"
          >
            Este Mes
          </VBtn>
          <VBtn
            size="small"
            :variant="datePreset === 'all' ? 'flat' : 'tonal'"
            :color="datePreset === 'all' ? 'primary' : 'secondary'"
            @click="applyDatePreset('all')"
          >
            Todo
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- Barra de Filtros -->
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="6" sm="2">
            <AppTextField
              v-model="dateFrom"
              type="date"
              label="Desde"
              density="compact"
              hide-details
              @update:model-value="datePreset = 'custom'"
            />
          </VCol>
          <VCol cols="6" sm="2">
            <AppTextField
              v-model="dateTo"
              type="date"
              label="Hasta"
              density="compact"
              hide-details
              @update:model-value="datePreset = 'custom'"
            />
          </VCol>
          <VCol cols="6" sm="2">
            <AppSelect
              v-model="itemTypeFilter"
              :items="[
                { title: 'Todos los tipos', value: 'all' },
                { title: 'Insumo / Producto', value: 'product' },
                { title: 'Platillo / Sub-receta', value: 'dish' },
              ]"
              label="Tipo de Ítem"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol cols="6" sm="2">
            <AppSelect
              v-model="reasonFilter"
              :items="[{ title: 'Todos los motivos', value: 'all' }, ...wasteReasons.map(r => ({ title: r.label, value: r.value }))]"
              label="Motivo"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="4">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar por nombre, ID o descripción..."
              prepend-inner-icon="tabler-search"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Estado de Carga -->
      <div v-if="loading && wastes.length === 0" class="pa-6">
        <v-skeleton-loader type="table-row-divider@5" />
      </div>

      <!-- Estado Vacío -->
      <div v-else-if="!loading && wastes.length === 0" class="pa-12 text-center text-medium-emphasis">
        <v-icon icon="tabler-clipboard-check" size="56" class="mb-3 opacity-50" />
        <h3 class="text-subtitle-1 font-weight-bold mb-1">
          No hay registros de mermas con los filtros seleccionados
        </h3>
        <p class="text-body-2 mb-4">
          Puedes registrar una merma individual o cargar la hoja de merma de cierre de turno valorizada al costo unificado de la tabla productos.
        </p>
        <div class="d-flex justify-center gap-2">
          <v-btn color="primary" prepend-icon="tabler-plus" @click="openReportModal">
            Reportar Merma
          </v-btn>
        </div>
      </div>

      <!-- Tabla de Mermas -->
      <div v-else>
        <v-table density="comfortable" hover class="text-no-wrap">
          <thead>
            <tr>
              <th>Ítem / Código</th>
              <th>Insumo / Platillo</th>
              <th class="text-center">Cant. Perdida</th>
              <th class="text-right">Costo Unificado Unit.</th>
              <th class="text-right">Pérdida Total</th>
              <th>Clasificación / Descripción</th>
              <th class="text-center">Evidencia</th>
              <th>Reportado Por</th>
              <th class="text-center">Estado</th>
              <th>Fecha</th>
              <th v-if="isApprover" class="text-end">Acciones Admin</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="waste in wastes" :key="waste.id">
              <td>
                <div class="d-flex flex-column gap-1 py-1">
                  <v-chip
                    size="small"
                    :color="waste.item_type === 'dish' ? 'secondary' : 'primary'"
                    variant="tonal"
                    class="font-weight-black"
                  >
                    {{ waste.item_type === 'dish' ? `PLATO #${waste.dish_id}` : `PROD #${waste.product_id}` }}
                  </v-chip>
                  <span class="text-caption text-medium-emphasis">
                    {{ waste.item_type_label }}
                  </span>
                </div>
              </td>
              <td>
                <div class="d-flex flex-column py-1">
                  <span class="font-weight-bold text-body-2">{{ waste.item_name }}</span>
                  <span v-if="waste.item_type === 'product'" class="text-caption text-medium-emphasis">
                    Stock actual: {{ waste.current_stock }} {{ waste.product_unit }}
                  </span>
                  <span
                    v-else-if="waste.ingredients_breakdown && waste.ingredients_breakdown.length > 0"
                    class="text-caption text-info font-weight-medium"
                  >
                    Descuenta {{ waste.ingredients_breakdown.length }} insumo(s) de receta
                  </span>
                </div>
              </td>
              <td class="text-center">
                <span class="font-weight-black text-error">
                  -{{ waste.quantity }} {{ waste.product_unit }}
                </span>
              </td>
              <td class="text-right">
                <div class="d-flex flex-column align-end">
                  <span class="font-weight-medium">{{ formatCurrency(waste.unified_unit_cost || waste.unit_cost) }}</span>
                  <span class="text-caption text-medium-emphasis">Costo Unificado</span>
                </div>
              </td>
              <td class="text-right font-weight-bold text-error">
                -{{ formatCurrency(waste.total_loss_usd) }}
              </td>
              <td style="max-width: 280px; white-space: normal;">
                <div class="py-1">
                  <div class="d-flex align-center gap-1 mb-1">
                    <v-chip
                      size="x-small"
                      :color="waste.is_operational_waste ? 'error' : 'info'"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ waste.reason_label }}
                    </v-chip>
                  </div>
                  <div class="text-body-2 font-weight-medium">
                    {{ waste.notes || 'Sin descripción' }}
                  </div>
                  <div v-if="waste.rejection_reason" class="text-caption text-error font-weight-bold mt-1">
                    Rechazo: {{ waste.rejection_reason }}
                  </div>
                </div>
              </td>
              <td class="text-center">
                <v-btn
                  v-if="waste.photo_url || (waste.ingredients_breakdown && waste.ingredients_breakdown.length > 0)"
                  size="small"
                  variant="tonal"
                  color="info"
                  :prepend-icon="waste.photo_url ? 'tabler-photo' : 'tabler-list-Search'"
                  @click="openPhotoDialog(waste)"
                >
                  {{ waste.photo_url ? 'Ver Evidencia' : 'Ver Escandallo' }}
                </v-btn>
                <span v-else class="text-caption text-disabled">Opcional / Sin foto</span>
              </td>
              <td>
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-medium">{{ waste.user_name }}</span>
                  <span v-if="waste.approved_by_name" class="text-caption text-medium-emphasis">
                    Rev: {{ waste.approved_by_name }}
                  </span>
                </div>
              </td>
              <td class="text-center">
                <v-chip
                  size="small"
                  :color="waste.status_color || (waste.status === 'approved' ? 'success' : waste.status === 'rejected' ? 'error' : 'warning')"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ waste.status_label }}
                </v-chip>
              </td>
              <td class="text-caption text-medium-emphasis">
                {{ waste.occurred_at }}
              </td>
              <td v-if="isApprover" class="text-end">
                <div v-if="waste.status === 'pending'" class="d-flex justify-end gap-2">
                  <v-btn
                    size="small"
                    color="success"
                    variant="flat"
                    prepend-icon="tabler-check"
                    :loading="processingActionId === waste.id"
                    :disabled="processingActionId !== null"
                    @click="handleApproveWaste(waste)"
                  >
                    Aprobar
                  </v-btn>
                  <v-btn
                    size="small"
                    color="error"
                    variant="outlined"
                    prepend-icon="tabler-x"
                    :disabled="processingActionId !== null"
                    @click="openRejectDialog(waste)"
                  >
                    Rechazar
                  </v-btn>
                </div>
                <span v-else class="text-caption text-medium-emphasis">
                  {{ waste.approved_at || 'Procesada' }}
                </span>
              </td>
            </tr>
          </tbody>
        </v-table>

        <div v-if="lastPage > 1" class="d-flex justify-end pa-4 border-t">
          <v-pagination
            v-model="page"
            :length="lastPage"
            :total-visible="5"
            density="compact"
          />
        </div>
      </div>
    </v-card>

    <!-- DIÁLOGO: REPORTAR MERMA (INDIVIDUAL O HOJA DE MERMA DE TURNO) -->
    <v-dialog
      v-model="showReportModal"
      max-width="760"
      :fullscreen="$vuetify.display.xs"
      scrollable
      persistent
    >
      <v-card class="rounded-lg d-flex flex-column" style="max-height: 92vh;">
        <v-card-title class="d-flex align-center justify-space-between bg-primary text-white py-3 px-4 flex-shrink-0">
          <div class="d-flex align-center gap-2">
            <v-icon color="white">tabler-alert-triangle</v-icon>
            <span class="text-subtitle-1 font-weight-bold text-white">
              Registrar Merma / Hoja de Merma
            </span>
          </div>
          <v-btn
            icon="tabler-x"
            variant="text"
            color="white"
            size="small"
            :disabled="submitting"
            @click="showReportModal = false"
          />
        </v-card-title>

        <v-card-text class="pa-4 flex-grow-1 overflow-y-auto">
          <v-form id="wasteReportForm" @submit.prevent="submitWasteReport">
            <!-- Selector de Tipo de Ítem -->
            <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4 pb-3 border-b">
              <v-btn-toggle
                v-model="wasteForm.item_type"
                color="primary"
                density="compact"
                mandatory
                variant="outlined"
                @update:model-value="resetItemInputs"
              >
                <v-btn value="product" prepend-icon="tabler-package">
                  Insumo / Producto
                </v-btn>
                <v-btn value="dish" prepend-icon="tabler-soup">
                  Platillo / Sub-receta
                </v-btn>
              </v-btn-toggle>
            </div>

            <v-row dense>
              <!-- MODO INSUMO / PRODUCTO: Buscador primario por nombre + ID sincronizado -->
              <template v-if="wasteForm.item_type === 'product'">
                <v-col cols="12" sm="8">
                  <v-autocomplete
                    v-model="selectedProductOption"
                    v-model:search="productSearch"
                    :items="productOptions"
                    item-title="label"
                    return-object
                    :loading="loadingProductOptions"
                    label="Buscar Insumo o Producto por Nombre *"
                    placeholder="Escribe el nombre del insumo..."
                    prepend-inner-icon="tabler-search"
                    density="compact"
                    variant="outlined"
                    clearable
                    @update:model-value="onSelectProductOption"
                  />
                </v-col>

                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model.number="wasteForm.product_id"
                    type="number"
                    min="1"
                    label="ID Producto *"
                    placeholder="Ej: 105"
                    prepend-inner-icon="tabler-hash"
                    density="compact"
                    variant="outlined"
                    :loading="loadingProductById"
                    :error-messages="formErrors.product_id || productNotFoundError"
                  />
                </v-col>

                <!-- Información del producto seleccionado -->
                <v-col v-if="resolvedProduct" cols="12" class="mb-2">
                  <div class="d-flex flex-wrap align-center justify-space-between gap-2 px-3 py-2 rounded border text-caption">
                    <span><strong>#{{ resolvedProduct.id }} — {{ resolvedProduct.name }}</strong></span>
                    <span class="text-medium-emphasis">
                      Stock: {{ resolvedProduct.stock }} {{ resolvedProduct.unit_of_measure }} • Costo: {{ formatCurrency(resolvedProduct.unit_cost) }} / {{ resolvedProduct.unit_of_measure }}
                    </span>
                  </div>
                </v-col>
              </template>

              <!-- MODO PLATILLO / SUB-RECETA -->
              <template v-else>
                <v-col cols="12">
                  <v-autocomplete
                    v-model="selectedDishOption"
                    :items="dishesCatalog"
                    item-title="label"
                    return-object
                    :loading="loadingDishes"
                    label="Seleccionar Platillo Terminado o Sub-receta *"
                    placeholder="Escribe el nombre del plato o preparación..."
                    prepend-inner-icon="tabler-soup"
                    density="compact"
                    variant="outlined"
                    clearable
                    :error-messages="formErrors.dish_id"
                    @update:model-value="onSelectDishOption"
                  />
                </v-col>

                <!-- Escandallo de ingredientes valorizado al Costo Unificado de la Tabla Productos -->
                <v-col v-if="resolvedDish" cols="12" class="mb-3">
                  <v-alert
                    color="secondary"
                    variant="tonal"
                    density="compact"
                    icon="tabler-tools-kitchen-2"
                  >
                    <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-2">
                      <div>
                        <strong>#{{ resolvedDish.id }} — {{ resolvedDish.name }}</strong>
                        <v-chip size="x-small" class="ms-2" color="secondary" variant="flat">
                          {{ resolvedDish.type_badge }}
                        </v-chip>
                      </div>
                      <v-chip size="x-small" color="primary" variant="flat" class="font-weight-bold">
                        Costo Unificado Receta: {{ formatCurrency(resolvedDish.unified_cost) }} / {{ resolvedDish.unit_of_measure }}
                      </v-chip>
                    </div>

                    <div v-if="resolvedDish.ingredients && resolvedDish.ingredients.length > 0" class="text-caption">
                      <div class="font-weight-bold mb-1">
                        Insumos que se descontarán del inventario (valorizados al costo unificado de la tabla productos):
                      </div>
                      <div class="d-flex flex-wrap gap-1">
                        <v-chip
                          v-for="ing in resolvedDish.ingredients"
                          :key="ing.id"
                          size="x-small"
                          variant="outlined"
                        >
                          {{ ing.name }}: {{ Number((ing.portion * (Number(wasteForm.quantity) || 1)).toFixed(4)) }} {{ ing.unit_of_measure }}
                          ({{ formatCurrency(ing.unified_unit_cost) }}/{{ ing.unit_of_measure }})
                        </v-chip>
                      </div>
                    </div>
                  </v-alert>
                </v-col>
              </template>

              <!-- Cantidad y Clasificación -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="wasteForm.quantity"
                  type="number"
                  step="0.0001"
                  min="0.0001"
                  label="Cantidad Perdida *"
                  placeholder="Ej: 1.5"
                  :suffix="wasteForm.item_type === 'product' ? (resolvedProduct?.unit_of_measure || 'und') : (resolvedDish?.unit_of_measure || 'plato')"
                  density="compact"
                  variant="outlined"
                  :error-messages="formErrors.quantity"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-select
                  v-model="wasteForm.reason"
                  :items="wasteReasons"
                  item-title="label"
                  item-value="value"
                  label="Motivo / Clasificación *"
                  density="compact"
                  variant="outlined"
                />
              </v-col>

              <!-- Nota / Descripción -->
              <v-col cols="12">
                <v-textarea
                  v-model="wasteForm.notes"
                  label="Descripción / Observación *"
                  placeholder="Indica qué ocurrió (ej. se quemó en plancha, venció en cuarto frío, almuerzo de turno)..."
                  rows="2"
                  density="compact"
                  variant="outlined"
                  :error-messages="formErrors.notes"
                />
              </v-col>

              <!-- Resumen del impacto del renglón actual -->
              <v-col v-if="estimatedLossUsd > 0" cols="12" class="mb-2">
                <div class="d-flex justify-space-between align-center px-3 py-2 rounded border">
                  <span class="text-caption font-weight-medium">Pérdida estimada de este ítem:</span>
                  <span class="text-subtitle-2 font-weight-bold text-error">
                    -{{ formatCurrency(estimatedLossUsd) }}
                  </span>
                </div>
              </v-col>

              <!-- Botón para agregar a la Hoja de Merma -->
              <v-col cols="12" class="mb-3">
                <v-btn
                  block
                  color="secondary"
                  variant="tonal"
                  prepend-icon="tabler-plus"
                  @click="addLineToBatch"
                >
                  Agregar a la Hoja de Merma
                </v-btn>
              </v-col>

              <!-- Tabla de renglones agregados en la Hoja de Merma -->
              <v-col v-if="batchItems.length > 0" cols="12" class="mb-3">
                <div class="border rounded-lg overflow-hidden">
                  <div class="bg-grey-lighten-4 px-3 py-2 d-flex justify-space-between align-center border-b">
                    <span class="text-caption font-weight-bold text-uppercase">
                      Ítems en la Hoja de Merma ({{ batchItems.length }})
                    </span>
                    <span class="text-caption font-weight-black text-error">
                      Total Hoja: -{{ formatCurrency(batchTotalLossUsd) }}
                    </span>
                  </div>
                  <v-table density="compact">
                    <thead>
                      <tr>
                        <th>Ítem</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-right">Costo Unif.</th>
                        <th class="text-right">Subtotal</th>
                        <th>Motivo</th>
                        <th class="text-end">Quitar</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(line, idx) in batchItems" :key="idx">
                        <td class="text-caption font-weight-bold">
                          {{ line.item_name }}
                        </td>
                        <td class="text-center text-caption">
                          {{ line.quantity }} {{ line.unit_of_measure }}
                        </td>
                        <td class="text-right text-caption">
                          {{ formatCurrency(line.unit_cost) }}
                        </td>
                        <td class="text-right text-caption font-weight-bold text-error">
                          -{{ formatCurrency(line.total_loss_usd) }}
                        </td>
                        <td class="text-caption">
                          {{ line.reason_label }}
                        </td>
                        <td class="text-end">
                          <v-btn
                            icon="tabler-trash"
                            size="x-small"
                            color="error"
                            variant="text"
                            @click="removeBatchLine(idx)"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </v-table>
                </div>
              </v-col>

              <!-- Anexar Foto de Evidencia (Obligatoria en mermas físicas, Opcional en personal/cortesía/limpieza) -->
              <v-col cols="12">
                <v-file-input
                  accept="image/*"
                  capture="environment"
                  :label="isPhotoRequired ? 'Fotografía de Evidencia (Obligatoria para este motivo) *' : 'Fotografía de Respaldo (Opcional para este motivo)'"
                  placeholder="Selecciona o toma una foto del producto/plato"
                  prepend-icon=""
                  prepend-inner-icon="tabler-camera"
                  density="compact"
                  variant="outlined"
                  show-size
                  :error-messages="formErrors.photo"
                  @update:model-value="onPhotoSelected"
                />
              </v-col>

              <!-- Previsualización de la foto seleccionada -->
              <v-col v-if="photoPreviewUrl" cols="12" class="mb-2">
                <div class="border rounded-lg pa-2 text-center bg-grey-lighten-4">
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-caption font-weight-bold">Vista previa de la evidencia:</span>
                    <v-btn
                      size="x-small"
                      color="error"
                      variant="text"
                      prepend-icon="tabler-trash"
                      @click="wasteForm.photo = null; photoPreviewUrl = null"
                    >
                      Quitar foto
                    </v-btn>
                  </div>
                  <v-img
                    :src="photoPreviewUrl"
                    max-height="160"
                    contain
                    class="rounded mx-auto"
                  />
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />
        <v-card-actions class="pa-3 bg-surface flex-shrink-0 d-flex justify-end gap-2">
          <v-btn
            variant="outlined"
            color="secondary"
            :disabled="submitting"
            @click="showReportModal = false"
          >
            Cancelar
          </v-btn>
          <v-btn
            type="submit"
            form="wasteReportForm"
            color="primary"
            variant="flat"
            prepend-icon="tabler-send"
            :loading="submitting"
            :disabled="submitting"
          >
            {{ batchItems.length > 0 ? `Registrar Hoja de Merma (${batchItems.length})` : (isApprover ? 'Registrar y Aprobar Merma' : 'Enviar Merma a Aprobación') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: VISOR DE EVIDENCIA Y ESCANDALLO DE RECETA -->
    <v-dialog v-model="showPhotoViewer" max-width="680">
      <v-card v-if="selectedWasteForPhoto" class="rounded-lg overflow-hidden">
        <v-card-title class="d-flex align-center justify-space-between bg-primary text-white py-3 px-4">
          <span class="text-subtitle-1 font-weight-bold text-white">
            Detalle de Merma — {{ selectedWasteForPhoto.item_name }}
          </span>
          <v-btn
            icon="tabler-x"
            variant="text"
            color="white"
            size="small"
            @click="showPhotoViewer = false"
          />
        </v-card-title>

        <v-card-text class="pa-4">
          <div v-if="selectedWasteForPhoto.photo_url" class="border rounded-lg pa-2 mb-3 bg-grey-lighten-4">
            <v-img
              :src="selectedWasteForPhoto.photo_url"
              max-height="380"
              contain
              class="rounded"
            />
          </div>

          <v-row dense class="mb-2">
            <v-col cols="12" sm="6">
              <div class="text-caption text-medium-emphasis">Ítem Afectado:</div>
              <div class="font-weight-bold">
                {{ selectedWasteForPhoto.item_name }} ({{ selectedWasteForPhoto.item_type_label }})
              </div>
            </v-col>
            <v-col cols="12" sm="6">
              <div class="text-caption text-medium-emphasis">Cantidad y Pérdida (Costo Unificado):</div>
              <div class="font-weight-black text-error">
                -{{ selectedWasteForPhoto.quantity }} {{ selectedWasteForPhoto.product_unit }}
                ({{ formatCurrency(selectedWasteForPhoto.total_loss_usd) }})
              </div>
            </v-col>
            <v-col cols="12" class="mt-2">
              <div class="text-caption text-medium-emphasis">
                Observación reportada por {{ selectedWasteForPhoto.user_name }} ({{ selectedWasteForPhoto.station_name }}):
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ selectedWasteForPhoto.notes || 'Sin descripción' }}
              </div>
            </v-col>
          </v-row>

          <!-- Tabla de desglose de ingredientes cuando es un Platillo / Sub-receta -->
          <div
            v-if="selectedWasteForPhoto.ingredients_breakdown && selectedWasteForPhoto.ingredients_breakdown.length > 0"
            class="border rounded-lg overflow-hidden mt-3"
          >
            <div class="bg-grey-lighten-4 px-3 py-2 border-b text-caption font-weight-bold">
              Desglose de Insumos Descontados (Costo Unificado Tabla Productos)
            </div>
            <v-table density="compact">
              <thead>
                <tr>
                  <th>Insumo</th>
                  <th class="text-center">Porción Receta</th>
                  <th class="text-center">Total Descontado</th>
                  <th class="text-right">Costo Unificado Unit.</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ing in selectedWasteForPhoto.ingredients_breakdown" :key="ing.product_id">
                  <td class="text-caption font-weight-bold">#{{ ing.product_id }} - {{ ing.name }}</td>
                  <td class="text-center text-caption">{{ ing.portion_per_unit }} {{ ing.unit_of_measure }}</td>
                  <td class="text-center text-caption text-error font-weight-bold">
                    -{{ ing.total_deducted_qty }} {{ ing.unit_of_measure }}
                  </td>
                  <td class="text-right text-caption">{{ formatCurrency(ing.unified_unit_cost) }}</td>
                </tr>
              </tbody>
            </v-table>
          </div>
        </v-card-text>

        <v-card-actions v-if="isApprover && selectedWasteForPhoto.status === 'pending'" class="justify-end px-4 pb-4 gap-2">
          <v-btn
            color="error"
            variant="outlined"
            prepend-icon="tabler-x"
            @click="openRejectDialog(selectedWasteForPhoto)"
          >
            Rechazar
          </v-btn>
          <v-btn
            color="success"
            variant="flat"
            prepend-icon="tabler-check"
            :loading="processingActionId === selectedWasteForPhoto.id"
            @click="handleApproveWaste(selectedWasteForPhoto)"
          >
            Aprobar y Descontar Inventario
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: RECHAZAR MERMA -->
    <v-dialog v-model="showRejectModal" max-width="480">
      <v-card class="rounded-lg">
        <v-card-title class="text-subtitle-1 font-weight-bold pt-4 px-4">
          Rechazar Reporte de Merma
        </v-card-title>
        <v-card-text class="px-4 py-2">
          <p class="text-body-2 text-medium-emphasis mb-3">
            Al rechazar esta solicitud, no se descontará ninguna unidad del inventario.
          </p>
          <v-textarea
            v-model="rejectionReason"
            label="Motivo del rechazo (opcional)"
            placeholder="Indica por qué se rechaza este reporte..."
            rows="3"
            density="compact"
            variant="outlined"
          />
        </v-card-text>
        <v-card-actions class="justify-end px-4 pb-4 gap-2">
          <v-btn variant="text" color="secondary" @click="showRejectModal = false">
            Cancelar
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="processingActionId !== null"
            @click="confirmRejectWaste"
          >
            Confirmar Rechazo
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
