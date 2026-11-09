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

// Estado del listado y filtros
const wastes = ref([])
const loading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const totalItems = ref(0)
const statusFilter = ref('all')
const searchQuery = ref('')

const summary = reactive({
  pending_count: 0,
  approved_count: 0,
  rejected_count: 0,
  total_loss_approved_usd: 0,
  total_loss_pending_usd: 0,
})

// Estado del modal de reporte de merma
const showReportModal = ref(false)
const submitting = ref(false)
const loadingProductById = ref(false)
const productNotFoundError = ref('')
const resolvedProduct = ref(null)

// Buscador auxiliar por nombre de producto
const productSearch = ref('')
const productOptions = ref([])
const loadingProductOptions = ref(false)
const selectedProductOption = ref(null)

const wasteForm = reactive({
  product_id: '',
  quantity: null,
  reason: 'burned_spoiled',
  notes: '',
  photo: null,
})

const formErrors = reactive({
  product_id: '',
  quantity: '',
  notes: '',
  photo: '',
})

const photoPreviewUrl = ref(null)

// Estado para visor de foto de evidencia
const showPhotoViewer = ref(false)
const selectedWasteForPhoto = ref(null)

// Estado para modal de rechazo
const showRejectModal = ref(false)
const rejectingWaste = ref(null)
const rejectionReason = ref('')
const processingActionId = ref(null)

const wasteReasons = [
  { label: 'Quemado / Malogrado', value: 'burned_spoiled' },
  { label: 'Vencimiento / Caducado', value: 'expired' },
  { label: 'Merma de Limpieza / Cocción', value: 'preparation_trim' },
  { label: 'Caída / Accidente', value: 'dropped' },
  { label: 'Comida de Personal', value: 'staff_meal' },
  { label: 'Cortesía de la Casa', value: 'courtesy' },
]

const estimatedLossUsd = computed(() => {
  if (!resolvedProduct.value || !wasteForm.quantity) return 0
  const qty = Number(wasteForm.quantity) || 0
  const cost = Number(resolvedProduct.value.unit_cost) || 0
  return parseFloat((qty * cost).toFixed(2))
})

// Cargar registros de mermas
const fetchWastes = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/restaurant/waste/records', {
      params: {
        page: page.value,
        per_page: 15,
        status: statusFilter.value,
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
    }
  } catch (error) {
    console.error('Error al cargar mermas:', error)
    toast.error('No se pudieron cargar los registros de mermas.')
  } finally {
    loading.value = false
  }
}

let filterTimer = null
watch([statusFilter, searchQuery], () => {
  page.value = 1
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    fetchWastes()
  }, 300)
})

watch(page, () => {
  fetchWastes()
})

// Consultar producto por su ID directo
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
        unit_cost: Number(prod.unit_cost ?? 0),
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
      unit_cost: Number(item.unit_cost ?? 0),
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
  if (!item) return
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

const resetReportForm = () => {
  wasteForm.product_id = ''
  wasteForm.quantity = null
  wasteForm.reason = 'burned_spoiled'
  wasteForm.notes = ''
  wasteForm.photo = null
  resolvedProduct.value = null
  selectedProductOption.value = null
  productNotFoundError.value = ''
  formErrors.product_id = ''
  formErrors.quantity = ''
  formErrors.notes = ''
  formErrors.photo = ''
  if (photoPreviewUrl.value) {
    URL.revokeObjectURL(photoPreviewUrl.value)
    photoPreviewUrl.value = null
  }
}

const openReportModal = () => {
  resetReportForm()
  searchProductsByName('')
  showReportModal.value = true
}

const validateForm = () => {
  let valid = true
  formErrors.product_id = ''
  formErrors.quantity = ''
  formErrors.notes = ''
  formErrors.photo = ''

  if (!wasteForm.product_id || Number(wasteForm.product_id) <= 0) {
    formErrors.product_id = 'Debes ingresar el ID del producto.'
    valid = false
  } else if (productNotFoundError.value) {
    formErrors.product_id = productNotFoundError.value
    valid = false
  }

  if (!wasteForm.quantity || Number(wasteForm.quantity) <= 0) {
    formErrors.quantity = 'Ingresa una cantidad perdida mayor a 0.'
    valid = false
  }

  if (!wasteForm.notes || !String(wasteForm.notes).trim()) {
    formErrors.notes = 'Debes escribir una descripción de la pérdida.'
    valid = false
  }

  if (!wasteForm.photo) {
    formErrors.photo = 'Debes anexar una fotografía de evidencia.'
    valid = false
  }

  return valid
}

const submitWasteReport = async () => {
  if (!validateForm()) return

  submitting.value = true
  try {
    const formData = new FormData()
    formData.append('item_type', 'product')
    formData.append('product_id', String(wasteForm.product_id))
    formData.append('quantity', String(wasteForm.quantity))
    formData.append('reason', wasteForm.reason)
    formData.append('notes', String(wasteForm.notes).trim())
    if (wasteForm.photo instanceof File) {
      formData.append('photo', wasteForm.photo)
    }

    const { data } = await axios.post('/restaurant/waste/records', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    toast.success(data.message || 'Merma reportada y enviada a revisión del administrador.')
    showReportModal.value = false
    resetReportForm()
    await fetchWastes()
  } catch (error) {
    const errors = error.response?.data?.errors
    if (errors) {
      formErrors.product_id = errors.product_id?.[0] || ''
      formErrors.quantity = errors.quantity?.[0] || ''
      formErrors.notes = errors.notes?.[0] || ''
      formErrors.photo = errors.photo?.[0] || ''
    }
    toast.error(error.response?.data?.message || 'Error al registrar el reporte de merma.')
  } finally {
    submitting.value = false
  }
}

// Visualizar foto en grande
const openPhotoDialog = waste => {
  selectedWasteForPhoto.value = waste
  showPhotoViewer.value = true
}

// Aprobar merma (Admin/Supervisor)
const handleApproveWaste = async waste => {
  const confirmResult = await Swal.fire({
    title: '¿Aprobar merma y descontar inventario?',
    html: `Se descontarán <b>${waste.quantity} ${waste.product_unit || 'und'}</b> del producto <b>#${waste.product_id} - ${waste.item_name}</b> y se registrará como <b>Pérdida</b> en el inventario.`,
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
  if (isApprover.value) {
    statusFilter.value = 'pending'
  }
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
  <div class="pb-12">
    <!-- Encabezado Principal -->
    <v-card class="mb-4" elevation="1">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="error" variant="tonal" rounded size="44">
            <v-icon size="26">tabler-trash-x</v-icon>
          </v-avatar>
          <div>
            <h1 class="text-h6 font-weight-bold mb-0">Mermas y Pérdidas de Restaurante</h1>
            <div class="text-caption text-medium-emphasis">
              Reporte de pérdidas por producto con evidencia fotográfica y aprobación administrativa
            </div>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <v-btn
            variant="outlined"
            color="secondary"
            prepend-icon="tabler-refresh"
            :loading="loading"
            @click="fetchWastes"
          >
            Actualizar
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openReportModal"
          >
            Reportar Merma
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <!-- Tarjetas de Resumen Operativo -->
    <v-row class="mb-4" dense>
      <v-col cols="12" sm="4">
        <v-card variant="outlined" class="bg-surface h-100">
          <v-card-text class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-bold text-uppercase">
                Pendientes por Aprobar
              </div>
              <div class="text-h5 font-weight-black text-warning mt-1">
                {{ summary.pending_count }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Estimado: {{ formatCurrency(summary.total_loss_pending_usd) }}
              </div>
            </div>
            <v-avatar color="warning" variant="tonal" size="42">
              <v-icon>tabler-clock-exclamation</v-icon>
            </v-avatar>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="4">
        <v-card variant="outlined" class="bg-surface h-100">
          <v-card-text class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-bold text-uppercase">
                Mermas Aprobadas (En Pérdida)
              </div>
              <div class="text-h5 font-weight-black text-error mt-1">
                {{ summary.approved_count }}
              </div>
              <div class="text-caption text-error font-weight-bold">
                Total descontado: -{{ formatCurrency(summary.total_loss_approved_usd) }}
              </div>
            </div>
            <v-avatar color="error" variant="tonal" size="42">
              <v-icon>tabler-trending-down</v-icon>
            </v-avatar>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="4">
        <v-card variant="outlined" class="bg-surface h-100">
          <v-card-text class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-bold text-uppercase">
                Rechazadas
              </div>
              <div class="text-h5 font-weight-black text-medium-emphasis mt-1">
                {{ summary.rejected_count }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Sin descuento de inventario
              </div>
            </div>
            <v-avatar color="secondary" variant="tonal" size="42">
              <v-icon>tabler-circle-x</v-icon>
            </v-avatar>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Barra de Pestañas de Estado y Buscador -->
    <v-card variant="flat" class="border rounded-lg bg-surface overflow-hidden">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-3 border-b">
        <v-tabs v-model="statusFilter" density="compact" color="primary">
          <v-tab value="pending">
            <v-icon start size="18">tabler-clock</v-icon>
            Pendientes
            <v-chip
              v-if="summary.pending_count > 0"
              size="x-small"
              color="warning"
              variant="flat"
              class="ms-2 font-weight-bold"
            >
              {{ summary.pending_count }}
            </v-chip>
          </v-tab>
          <v-tab value="approved">
            <v-icon start size="18">tabler-check</v-icon>
            Aprobadas (Pérdidas)
          </v-tab>
          <v-tab value="rejected">
            <v-icon start size="18">tabler-x</v-icon>
            Rechazadas
          </v-tab>
          <v-tab value="all">
            Todas
          </v-tab>
        </v-tabs>

        <div style="min-width: 260px; max-width: 360px; width: 100%;">
          <v-text-field
            v-model="searchQuery"
            placeholder="Buscar por ID de producto, nombre o descripción..."
            prepend-inner-icon="tabler-search"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
        </div>
      </v-card-text>

      <!-- Estado de Carga -->
      <div v-if="loading && wastes.length === 0" class="pa-6">
        <v-skeleton-loader type="table-row-divider@5" />
      </div>

      <!-- Estado Vacío -->
      <div v-else-if="!loading && wastes.length === 0" class="pa-12 text-center text-medium-emphasis">
        <v-icon icon="tabler-clipboard-check" size="56" class="mb-3 opacity-50" />
        <h3 class="text-subtitle-1 font-weight-bold mb-1">
          No hay registros de mermas en esta bandeja
        </h3>
        <p class="text-body-2 mb-4">
          Puedes registrar una nueva merma indicando el ID del producto, la cantidad perdida, la descripción y su foto de respaldo.
        </p>
        <v-btn color="primary" prepend-icon="tabler-plus" @click="openReportModal">
          Reportar Merma
        </v-btn>
      </div>

      <!-- Tabla de Mermas -->
      <div v-else>
        <v-table density="comfortable" hover class="text-no-wrap">
          <thead>
            <tr>
              <th>ID Producto</th>
              <th>Producto / Insumo</th>
              <th class="text-center">Cant. Perdida</th>
              <th class="text-right">Pérdida Est.</th>
              <th>Descripción / Motivo</th>
              <th class="text-center">Foto</th>
              <th>Reportado Por</th>
              <th class="text-center">Estado</th>
              <th>Fecha</th>
              <th v-if="isApprover" class="text-end">Acciones Admin</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="waste in wastes" :key="waste.id">
              <td>
                <v-chip size="small" color="primary" variant="tonal" class="font-weight-black">
                  #{{ waste.product_id || waste.dish_id || waste.id }}
                </v-chip>
              </td>
              <td>
                <div class="d-flex flex-column py-1">
                  <span class="font-weight-bold text-body-2">{{ waste.item_name }}</span>
                  <span class="text-caption text-medium-emphasis">
                    Stock actual: {{ waste.current_stock }} {{ waste.product_unit }}
                  </span>
                </div>
              </td>
              <td class="text-center">
                <span class="font-weight-black text-error">
                  -{{ waste.quantity }} {{ waste.product_unit }}
                </span>
              </td>
              <td class="text-right font-weight-bold text-error">
                -{{ formatCurrency(waste.total_loss_usd) }}
              </td>
              <td style="max-width: 280px; white-space: normal;">
                <div class="py-1">
                  <div class="text-body-2 font-weight-medium">
                    {{ waste.notes || 'Sin descripción' }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Motivo: {{ waste.reason_label }}
                  </div>
                  <div v-if="waste.rejection_reason" class="text-caption text-error font-weight-bold mt-1">
                    Rechazo: {{ waste.rejection_reason }}
                  </div>
                </div>
              </td>
              <td class="text-center">
                <v-btn
                  v-if="waste.photo_url"
                  size="small"
                  variant="tonal"
                  color="info"
                  prepend-icon="tabler-photo"
                  @click="openPhotoDialog(waste)"
                >
                  Ver Foto
                </v-btn>
                <span v-else class="text-caption text-disabled">Sin foto</span>
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

    <!-- DIÁLOGO: REPORTAR NUEVA MERMA -->
    <v-dialog v-model="showReportModal" max-width="640" persistent>
      <v-card class="rounded-lg overflow-hidden">
        <v-card-title class="d-flex align-center justify-space-between bg-primary text-white py-3 px-4">
          <div class="d-flex align-center gap-2">
            <v-icon color="white">tabler-alert-triangle</v-icon>
            <span class="text-subtitle-1 font-weight-bold text-white">
              Reportar Merma / Pérdida de Producto
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

        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitWasteReport">
            <v-row dense>
              <!-- Campo 1: ID del Producto -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="wasteForm.product_id"
                  type="number"
                  min="1"
                  label="ID del Producto *"
                  placeholder="Ej: 105"
                  prepend-inner-icon="tabler-hash"
                  density="compact"
                  variant="outlined"
                  :loading="loadingProductById"
                  :error-messages="formErrors.product_id || productNotFoundError"
                />
              </v-col>

              <!-- Buscador asistido opcional para autocompletar el ID -->
              <v-col cols="12" sm="8">
                <v-autocomplete
                  v-model="selectedProductOption"
                  v-model:search="productSearch"
                  :items="productOptions"
                  item-title="label"
                  return-object
                  :loading="loadingProductOptions"
                  label="Buscar producto por nombre (autocompleta el ID)"
                  placeholder="Escribe el nombre del insumo o producto..."
                  prepend-inner-icon="tabler-search"
                  density="compact"
                  variant="outlined"
                  clearable
                  @update:model-value="onSelectProductOption"
                />
              </v-col>

              <!-- Tarjeta informativa del producto encontrado por ID -->
              <v-col v-if="resolvedProduct" cols="12" class="mb-3">
                <v-alert
                  color="info"
                  variant="tonal"
                  density="compact"
                  icon="tabler-package"
                >
                  <div class="d-flex flex-wrap align-center justify-space-between gap-2">
                    <div>
                      <strong>#{{ resolvedProduct.id }} — {{ resolvedProduct.name }}</strong>
                    </div>
                    <div class="d-flex gap-3 text-caption font-weight-bold">
                      <span>Stock actual: {{ resolvedProduct.stock }} {{ resolvedProduct.unit_of_measure }}</span>
                      <span>Costo Unit.: {{ formatCurrency(resolvedProduct.unit_cost) }}</span>
                    </div>
                  </div>
                </v-alert>
              </v-col>

              <!-- Cantidad Perdida y Motivo -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="wasteForm.quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  label="Cantidad Perdida *"
                  placeholder="Ej: 2"
                  :suffix="resolvedProduct?.unit_of_measure || 'und'"
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
                  label="Clasificación de la Merma"
                  density="compact"
                  variant="outlined"
                />
              </v-col>

              <!-- Descripción detallada -->
              <v-col cols="12">
                <v-textarea
                  v-model="wasteForm.notes"
                  label="Descripción de la Pérdida *"
                  placeholder="Indica detalladamente qué ocurrió con el producto..."
                  rows="3"
                  density="compact"
                  variant="outlined"
                  :error-messages="formErrors.notes"
                />
              </v-col>

              <!-- Anexar Foto de Evidencia -->
              <v-col cols="12">
                <v-file-input
                  accept="image/*"
                  capture="environment"
                  label="Anexar Fotografía de Evidencia *"
                  placeholder="Selecciona o toma una foto del producto dañado/mermado"
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
                  <div class="text-caption font-weight-bold mb-1">Vista previa de la evidencia:</div>
                  <v-img
                    :src="photoPreviewUrl"
                    max-height="220"
                    contain
                    class="rounded mx-auto"
                  />
                </div>
              </v-col>

              <!-- Resumen del impacto -->
              <v-col v-if="estimatedLossUsd > 0" cols="12" class="mb-2">
                <div class="d-flex justify-space-between align-center pa-2 rounded bg-error-lighten-5">
                  <span class="text-caption font-weight-bold">Pérdida estimada a revisión:</span>
                  <span class="text-subtitle-2 font-weight-black text-error">
                    -{{ formatCurrency(estimatedLossUsd) }}
                  </span>
                </div>
              </v-col>
            </v-row>

            <div class="d-flex justify-end gap-2 mt-4">
              <v-btn
                variant="text"
                color="secondary"
                :disabled="submitting"
                @click="showReportModal = false"
              >
                Cancelar
              </v-btn>
              <v-btn
                type="submit"
                color="primary"
                prepend-icon="tabler-send"
                :loading="submitting"
                :disabled="submitting"
              >
                Enviar Merma a Aprobación
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: VISOR DE FOTOGRAFÍA DE EVIDENCIA -->
    <v-dialog v-model="showPhotoViewer" max-width="680">
      <v-card v-if="selectedWasteForPhoto" class="rounded-lg overflow-hidden">
        <v-card-title class="d-flex align-center justify-space-between bg-primary text-white py-3 px-4">
          <span class="text-subtitle-1 font-weight-bold text-white">
            Evidencia de Merma — Producto #{{ selectedWasteForPhoto.product_id }} ({{ selectedWasteForPhoto.item_name }})
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
          <div class="border rounded-lg pa-2 mb-3 bg-grey-lighten-4">
            <v-img
              :src="selectedWasteForPhoto.photo_url"
              max-height="420"
              contain
              class="rounded"
            />
          </div>

          <v-row dense>
            <v-col cols="12" sm="6">
              <div class="text-caption text-medium-emphasis">Producto:</div>
              <div class="font-weight-bold">
                #{{ selectedWasteForPhoto.product_id }} — {{ selectedWasteForPhoto.item_name }}
              </div>
            </v-col>
            <v-col cols="12" sm="6">
              <div class="text-caption text-medium-emphasis">Cantidad Perdida:</div>
              <div class="font-weight-black text-error">
                -{{ selectedWasteForPhoto.quantity }} {{ selectedWasteForPhoto.product_unit }} ({{ formatCurrency(selectedWasteForPhoto.total_loss_usd) }})
              </div>
            </v-col>
            <v-col cols="12" class="mt-2">
              <div class="text-caption text-medium-emphasis">Descripción reportada por {{ selectedWasteForPhoto.user_name }}:</div>
              <div class="text-body-2 font-weight-medium">
                {{ selectedWasteForPhoto.notes || 'Sin descripción' }}
              </div>
            </v-col>
          </v-row>
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
