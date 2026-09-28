<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { useAbility } from '@casl/vue'
import FinanceModuleCard from '@/components/configuration/FinanceModuleCard.vue'
import ProfitabilityFormulaCard from '@/components/configuration/ProfitabilityFormulaCard.vue'

const { can } = useAbility()
const brandingStore = useBrandingStore()

// Estados reactivos de carga, guardado y errores
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const selectedCategory = ref('all')

const enabledFinanceViews = ref([])
const profitabilityCalculationType = ref('simple')

// Respaldos reactivos para rollback en caso de error
let previousEnabledViews = []
let previousCalculationType = 'simple'

// Catálogo de vistas estructuradas por categoría
const availableFinanceViews = [
  { key: 'cashout', title: 'Flujo de Caja', description: 'Reportes de balance, wallets y ajustes de saldos.', icon: 'tabler-wallet', category: 'treasury' },
  { key: 'cash-closure', title: 'Cierre de Caja', description: 'Control administrativo de cierres de cajas de sucursales.', icon: 'tabler-device-desktop-analytics', category: 'treasury' },
  { key: 'cash-closure-user', title: 'Cierre de Usuarios', description: 'Gestión y arqueo de turnos para cajeros.', icon: 'tabler-lock-square', category: 'treasury' },
  { key: 'exchangerate', title: 'Tasa de Cambio', description: 'Monitoreo y ajuste de tasas BCV, COP, EUR y Binance.', icon: 'tabler-coin', category: 'treasury' },
  { key: 'profitability', title: 'Rentabilidad', description: 'Márgenes de ganancia global y costos operativos.', icon: 'tabler-chart-pie', category: 'operations' },
  { key: 'pending-payments', title: 'Cuentas por Pagar', description: 'Facturas acumuladas y control de pagos parciales.', icon: 'tabler-file-analytics', category: 'operations' },
  { key: 'payment-history', title: 'Historial de Pagos', description: 'Consulta y conciliación de pagos efectuados.', icon: 'tabler-receipt', category: 'operations' },
  { key: 'expense-expenses', title: 'Control de Gastos', description: 'Seguimiento de egresos operativos y recurrentes.', icon: 'tabler-trending-down', category: 'operations' },
  { key: 'payslips', title: 'Nómina', description: 'Administración y emisión de recibos de personal.', icon: 'tabler-users-group', category: 'operations' },
  { key: 'income-statement', title: 'Estado de Resultados', description: 'Reporte consolidado de ganancias y pérdidas (P&L).', icon: 'tabler-presentation', category: 'reports' },
  { key: 'balance-general', title: 'Balance General', description: 'Situación financiera patrimonial y activos.', icon: 'tabler-report', category: 'reports' },
  { key: 'furnitures-list', title: 'Mobiliario y Activos', description: 'Control de activos fijos e inventario estructural.', icon: 'tabler-armchair', category: 'reports' },
  { key: 'loans-list', title: 'Préstamos', description: 'Registro y amortización de financiamientos.', icon: 'tabler-cash-banknote', category: 'reports' },
]

const categories = [
  { value: 'all', label: 'Todas las Vistas', icon: 'tabler-grid-dots' },
  { value: 'treasury', label: 'Tesorería y Caja', icon: 'tabler-cash' },
  { value: 'operations', label: 'Operaciones y Pagos', icon: 'tabler-building-bank' },
  { value: 'reports', label: 'Reportes y Contabilidad', icon: 'tabler-report-analytics' },
]

// Filtro de vistas según la categoría seleccionada
const filteredFinanceViews = computed(() => {
  if (selectedCategory.value === 'all') return availableFinanceViews
  return availableFinanceViews.filter(v => v.category === selectedCategory.value)
})

const totalCount = computed(() => availableFinanceViews.length)
const activeCount = computed(() => enabledFinanceViews.value.length)
const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})
const allEnabled = computed(() => activeCount.value === totalCount.value)
const noneEnabled = computed(() => activeCount.value === 0)

// Consulta inicial de parámetros
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_finance_views,profitability_calculation_type' }
    })
    const settings = response.data.data
    if (settings) {
      if (settings.enabled_finance_views) {
        enabledFinanceViews.value = [...settings.enabled_finance_views]
        previousEnabledViews = [...settings.enabled_finance_views]
      }
      if (settings.profitability_calculation_type) {
        profitabilityCalculationType.value = settings.profitability_calculation_type
        previousCalculationType = settings.profitability_calculation_type
      }
    }
  } catch (error) {
    console.error('Error cargando configuración de Finanzas:', error)
    hasError.value = true
    errorMessage.value = 'No se pudo cargar la configuración financiera. Verifique su conexión e intente de nuevo.'
    toast.error('Error al cargar la configuración')
  } finally {
    isLoading.value = false
  }
}

// Alternar vista financiera individual
const toggleFinanceView = async (key) => {
  if (isSaving.value || isLoading.value || !can('manage', 'settings')) return

  previousEnabledViews = [...enabledFinanceViews.value]
  const currentViews = [...enabledFinanceViews.value]
  const index = currentViews.indexOf(key)
  if (index > -1) {
    currentViews.splice(index, 1)
  } else {
    currentViews.push(key)
  }

  enabledFinanceViews.value = currentViews
  await updateSettings()
}

// Cambiar la fórmula de cálculo de rentabilidad
const changeCalculationType = async (type) => {
  if (isSaving.value || profitabilityCalculationType.value === type || !can('manage', 'settings')) return

  previousCalculationType = profitabilityCalculationType.value
  profitabilityCalculationType.value = type
  await updateSettings()
}

// Acciones masivas con confirmación SweetAlert2 para acciones destructivas
const setAllViews = async (enable) => {
  if (isSaving.value || isLoading.value || !can('manage', 'settings')) return

  if (!enable) {
    const confirmed = await confirmDialog({
      title: '¿Desactivar todas las vistas?',
      text: 'Los módulos de finanzas se ocultarán de la barra lateral para todos los usuarios.',
      confirmButtonText: 'Sí, ocultar todas',
      confirmButtonColor: '#FF4C51'
    })
    if (!confirmed) return
  }

  previousEnabledViews = [...enabledFinanceViews.value]
  enabledFinanceViews.value = enable ? availableFinanceViews.map(v => v.key) : []
  await updateSettings()
}

// Persistir la configuración en el servidor
const updateSettings = async () => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_finance_views: enabledFinanceViews.value,
      profitability_calculation_type: profitabilityCalculationType.value
    })

    await brandingStore.fetchSettings()
    toast.success('Configuración de Finanzas actualizada exitosamente')

    previousEnabledViews = [...enabledFinanceViews.value]
    previousCalculationType = profitabilityCalculationType.value
  } catch (error) {
    console.error('Error al guardar:', error)
    toast.error('Error al actualizar la configuración')

    // Rollback al estado anterior en caso de fallo
    enabledFinanceViews.value = [...previousEnabledViews]
    profitabilityCalculationType.value = previousCalculationType
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="position-relative">
    <!-- Barra de progreso superior durante guardado -->
    <VProgressLinear
      v-if="isSaving"
      color="primary"
      indeterminate
      height="4"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 99;"
    />

    <!-- Banner de Error con Reintento -->
    <VAlert
      v-if="hasError"
      type="error"
      variant="tonal"
      class="mb-6 rounded-lg"
      closable
    >
      <template #title>Error de Carga</template>
      {{ errorMessage }}
      <template #append>
        <VBtn color="error" variant="text" size="small" @click="fetchSettings">
          Reintentar
        </VBtn>
      </template>
    </VAlert>

    <!-- Skeletons durante Carga Inicial -->
    <div v-if="isLoading" class="d-flex flex-column gap-6">
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VSkeletonLoader type="heading, paragraph" class="mb-4" />
          <VRow>
            <VCol cols="12" md="6" v-for="i in 2" :key="i">
              <VSkeletonLoader type="article" height="130" class="rounded-lg border" />
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>

      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VSkeletonLoader type="heading, paragraph" class="mb-4" />
          <VRow>
            <VCol cols="12" sm="6" md="3" v-for="i in 8" :key="i">
              <VSkeletonLoader type="article, actions" height="130" class="rounded-lg border" />
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div v-else-if="!hasError">
      <!-- Fórmula de Rentabilidad y Precios de Venta -->
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
            <VIcon icon="tabler-calculator" color="primary" size="28" />
            Fórmula de Rentabilidad y Precios de Venta
          </VCardTitle>
          <p class="text-caption text-medium-emphasis mb-6">
            Selecciona la fórmula matemática para calcular el precio de venta sugerido en base al costo del inventario.
          </p>

          <VRow>
            <VCol cols="12" md="6">
              <ProfitabilityFormulaCard
                title="Fórmula Simple (Markup Directo)"
                formula="PV = Costo * (1 + Margen / 100)"
                description="Añade un porcentaje directo sobre el costo base del producto. Ideal para operaciones comerciales sencillas."
                icon="tabler-percentage"
                :is-active="profitabilityCalculationType === 'simple'"
                :is-saving="isSaving"
                @select="changeCalculationType('simple')"
              />
            </VCol>

            <VCol cols="12" md="6">
              <ProfitabilityFormulaCard
                title="Fórmula Compuesta (Margen Financiero)"
                formula="PV = (Costo USA * (1 + TAX) + Flete + Embalaje) / (1 - Margen)"
                description="Cálculo estructurado multi-variable que descuenta impuestos de importación, fletes y embalajes para asegurar el margen neto deseado."
                icon="tabler-math-function"
                :is-active="profitabilityCalculationType === 'compound'"
                :is-saving="isSaving"
                @select="changeCalculationType('compound')"
              />
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>

      <!-- Configuración de Vistas de Finanzas -->
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4 mb-4">
            <div>
              <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
                <VIcon icon="tabler-chart-bar" color="primary" size="28" />
                Configuración de Vistas de Finanzas
              </VCardTitle>
              <p class="text-caption text-medium-emphasis mb-0 mt-1">
                Habilita o deshabilita las vistas financieras en la barra de navegación lateral.
              </p>
            </div>

            <!-- Métricas y Botones de Acción Masiva -->
            <div class="d-flex align-center gap-2 flex-wrap">
              <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
                {{ activeCount }} / {{ totalCount }} Vistas Activas ({{ activePercentage }}%)
              </VChip>
              <VBtn
                size="small"
                variant="outlined"
                color="primary"
                :disabled="allEnabled || isSaving"
                @click="setAllViews(true)"
              >
                Activar Todas
              </VBtn>
              <VBtn
                size="small"
                variant="outlined"
                color="error"
                :disabled="noneEnabled || isSaving"
                @click="setAllViews(false)"
              >
                Desactivar Todas
              </VBtn>
            </div>
          </div>

          <!-- Filtros Rápidos por Categoría -->
          <div class="d-flex gap-2 flex-wrap mb-6">
            <VChip
              v-for="cat in categories"
              :key="cat.value"
              :color="selectedCategory === cat.value ? 'primary' : 'secondary'"
              :variant="selectedCategory === cat.value ? 'flat' : 'outlined'"
              size="small"
              class="cursor-pointer font-weight-medium"
              @click="selectedCategory = cat.value"
            >
              <VIcon :icon="cat.icon" start size="16" />
              {{ cat.label }}
            </VChip>
          </div>

          <VDivider class="mb-6" />

          <!-- Rejilla de Módulos Financieros Filtrados -->
          <VRow>
            <VCol
              v-for="view in filteredFinanceViews"
              :key="view.key"
              cols="12"
              sm="6"
              md="3"
            >
              <FinanceModuleCard
                :view="view"
                :is-active="enabledFinanceViews.includes(view.key)"
                :is-saving="isSaving"
                @toggle="toggleFinanceView"
              />
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>
    </div>
  </div>
</template>
