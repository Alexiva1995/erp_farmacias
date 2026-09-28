<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { useAbility } from '@casl/vue'
import BiModuleCard from '@/components/configuration/BiModuleCard.vue'

const brandingStore = useBrandingStore()
const { can } = useAbility()

// Verificación de permisos de usuario para gestión de configuración
const canManageSettings = computed(() => can('manage', 'admin') || can('update', 'settings'))

// Estados reactivos de la interfaz
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const searchQuery = ref('')
const selectedCategory = ref('all')

const enabledBiViews = ref([])

// Catálogo completo de vistas de BI categorizadas
const availableBiViews = [
  { key: 'abc', title: 'Matriz ABC-XYZ & Foto Finish', category: 'inventario', description: 'Categorización estratégica de inventario y auditoría de rotación.', icon: 'tabler-abc' },
  { key: 'sku', title: 'Margen SKU', category: 'finanzas', description: 'Detalle de utilidad bruta y rendimiento financiero individual por SKU.', icon: 'tabler-calculator' },
  { key: 'products', title: 'Catálogo General', category: 'comercial', description: 'Visión consolidada y analíticas globales de ventas de productos.', icon: 'tabler-chart-pie' },
  { key: 'expiry', title: 'BI Caducidad', category: 'inventario', description: 'Predicciones y alertas de productos próximos a vencer por lotes.', icon: 'tabler-calendar' },
  { key: 'supplier-returns', title: 'Devoluciones Proveedores', category: 'inventario', description: 'Métricas de devoluciones por fallas de calidad o vencimientos.', icon: 'tabler-truck-return' },
  { key: 'laboratories', title: 'Marcas / Laboratorios', category: 'comercial', description: 'Análisis comercial y cuota de mercado por laboratorio fabricante.', icon: 'tabler-building-factory-2' },
  { key: 'pos', title: 'Analíticas TPV', category: 'comercial', description: 'Métricas de venta en terminales, transacciones por hora y ticket promedio.', icon: 'tabler-device-desktop' },
  { key: 'cyclic', title: 'Análisis Cíclico', category: 'inventario', description: 'Auditoría de discrepancias y efectividad de conteos de stock.', icon: 'tabler-refresh' },
  { key: 'customer', title: 'Analítica Clientes', category: 'clientes', description: 'Comportamiento de compra recurrente, LTV y segmentación.', icon: 'tabler-users' },
  { key: 'performance', title: 'Rendimiento RRHH', category: 'rrhh', description: 'Scorecard de productividad, metas comerciales y gamificación.', icon: 'tabler-trophy' },
]

// Lista de categorías para el filtro
const categories = [
  { value: 'all', title: 'Todas las Categorías' },
  { value: 'inventario', title: 'Inventario & Lotes' },
  { value: 'comercial', title: 'Comercial & Ventas' },
  { value: 'finanzas', title: 'Finanzas' },
  { value: 'clientes', title: 'Clientes' },
  { value: 'rrhh', title: 'RRHH' },
]

// Filtro de vistas según categoría y término de búsqueda
const filteredBiViews = computed(() => {
  return availableBiViews.filter(view => {
    const matchesCategory = selectedCategory.value === 'all' || view.category === selectedCategory.value
    const term = (searchQuery.value || '').trim().toLowerCase()
    const matchesSearch = !term ||
      view.title.toLowerCase().includes(term) ||
      view.description.toLowerCase().includes(term)

    return matchesCategory && matchesSearch
  })
})

// Métricas de estado global
const totalCount = computed(() => availableBiViews.length)
const activeCount = computed(() => enabledBiViews.value.length)
const activePercentage = computed(() => (totalCount.value === 0 ? 0 : Math.round((activeCount.value / totalCount.value) * 100)))
const allEnabled = computed(() => activeCount.value === totalCount.value)
const noneEnabled = computed(() => activeCount.value === 0)

// Consulta inicial de ajustes en el servidor
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_bi_views' },
    })
    const settings = response.data.data
    if (settings && Array.isArray(settings.enabled_bi_views)) {
      enabledBiViews.value = settings.enabled_bi_views
    }
  } catch (error) {
    hasError.value = true
    errorMessage.value = 'No se pudo obtener la configuración de módulos BI.'
    toast.error('Error al cargar la configuración de BI')
  } finally {
    isLoading.value = false
  }
}

// Persistencia en el backend
const updateSettings = async (previousState = null) => {
  if (!canManageSettings.value) {
    toast.error('No tiene permisos para modificar esta configuración')
    return
  }

  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_bi_views: enabledBiViews.value,
    })
    await brandingStore.fetchSettings()
    toast.success('Configuración actualizada correctamente')
  } catch (error) {
    if (previousState) {
      enabledBiViews.value = previousState
    }
    toast.error('Error al guardar la configuración')
  } finally {
    isSaving.value = false
  }
}

// Alternar vista individual
const toggleBiView = async key => {
  if (isSaving.value || isLoading.value) return

  const previousState = [...enabledBiViews.value]
  const updatedViews = [...enabledBiViews.value]
  const index = updatedViews.indexOf(key)

  if (index > -1) {
    updatedViews.splice(index, 1)
  } else {
    updatedViews.push(key)
  }

  enabledBiViews.value = updatedViews
  await updateSettings(previousState)
}

// Confirmación y ejecución masiva
const handleSetAllViews = async enable => {
  if (isSaving.value || isLoading.value || !canManageSettings.value) return

  if (!enable) {
    const confirmation = await Swal.fire({
      title: '¿Desactivar todas las vistas de BI?',
      text: 'Los usuarios no podrán visualizar ningún reporte de BI en el menú lateral.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#E20074',
      cancelButtonColor: '#7A0099',
      confirmButtonText: 'Sí, desactivar todas',
      cancelButtonText: 'Cancelar',
    })

    if (!confirmation.isConfirmed) return
  }

  const previousState = [...enabledBiViews.value]
  enabledBiViews.value = enable ? availableBiViews.map(v => v.key) : []
  await updateSettings(previousState)
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="position-relative">
    <VProgressLinear
      v-if="isSaving"
      color="primary"
      indeterminate
      height="4"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 10;"
    />

    <VCard class="mb-6 rounded-lg">
      <VCardItem class="py-5">
        <!-- Encabezado Principal y Acciones -->
        <div class="d-flex flex-column flex-md-row justify-space-between align-start align-md-center gap-4 mb-6">
          <div>
            <VCardTitle class="text-h5 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-chart-bar" color="primary" size="28" />
              Configuración de Módulos BI
            </VCardTitle>
            <p class="text-body-2 text-medium-emphasis mb-0 mt-1">
              Administre la visibilidad de los reportes analíticos del ERP en el menú lateral.
            </p>
          </div>

          <div class="d-flex align-center gap-2 flex-wrap" v-if="!isLoading && !hasError">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
              {{ activeCount }} / {{ totalCount }} Activos ({{ activePercentage }}%)
            </VChip>
            <VBtn
              size="small"
              variant="outlined"
              color="primary"
              :disabled="allEnabled || isSaving || !canManageSettings"
              @click="handleSetAllViews(true)"
            >
              Activar Todas
            </VBtn>
            <VBtn
              size="small"
              variant="outlined"
              color="error"
              :disabled="noneEnabled || isSaving || !canManageSettings"
              @click="handleSetAllViews(false)"
            >
              Desactivar Todas
            </VBtn>
          </div>
        </div>

        <VDivider class="mb-5" />

        <!-- Barra de Búsqueda y Filtro de Categorías -->
        <VRow class="mb-4" v-if="!isLoading && !hasError">
          <VCol cols="12" md="6">
            <VTextField
              v-model="searchQuery"
              placeholder="Buscar reporte por título o descripción..."
              prepend-inner-icon="tabler-search"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              clearable
            />
          </VCol>
          <VCol cols="12" md="6">
            <VSelect
              v-model="selectedCategory"
              :items="categories"
              item-title="title"
              item-value="value"
              label="Filtrar por Categoría"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </VCol>
        </VRow>

        <!-- Banner de Error de Conexión -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
        >
          <template #title>Error de Carga</template>
          {{ errorMessage }}
          <template #append>
            <VBtn color="error" variant="text" size="small" @click="fetchSettings">
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Esqueletos de Carga -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 6" :key="n" cols="12" sm="6" md="4">
            <VSkeletonLoader type="card" class="rounded-lg border" height="150" />
          </VCol>
        </VRow>

        <!-- Estado Vacío por Búsqueda -->
        <div v-else-if="!hasError && filteredBiViews.length === 0" class="text-center py-10">
          <VIcon icon="tabler-folder-off" size="48" class="text-disabled mb-2" />
          <p class="text-body-1 text-medium-emphasis mb-0">
            No se encontraron módulos con el criterio de búsqueda especificado.
          </p>
        </div>

        <!-- Cuadrícula de Tarjetas de Módulos BI -->
        <VRow v-else-if="!hasError">
          <VCol
            v-for="view in filteredBiViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
          >
            <BiModuleCard
              :view="view"
              :is-active="enabledBiViews.includes(view.key)"
              :is-saving="isSaving"
              @toggle="toggleBiView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
