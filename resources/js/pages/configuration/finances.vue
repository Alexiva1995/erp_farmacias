<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()

const isLoading = ref(true)
const isSaving = ref(false)

const enabledFinanceViews = ref([])
const profitabilityCalculationType = ref('simple')

// Guardar estados previos para rollback en caso de error
let previousEnabledViews = []
let previousCalculationType = 'simple'

const availableFinanceViews = [
  { key: 'profitability', title: 'Rentabilidad', description: 'Cálculo de márgenes globales y edición de costos/márgenes de ganancia.', icon: 'tabler-chart-pie' },
  { key: 'exchangerate', title: 'Tasa de cambio', description: 'Actualización diaria de tasas BCV, COP, EUR y Binance.', icon: 'tabler-coin' },
  { key: 'pending-payments', title: 'Por Pagar', description: 'Control de facturas acumuladas por pagar y pagos parciales.', icon: 'tabler-file-analytics' },
  { key: 'payment-history', title: 'Historial de Pagos', description: 'Consulta detallada de todos los pagos completados.', icon: 'tabler-receipt' },
  { key: 'cashout', title: 'Flujo de caja', description: 'Reportes de balance, wallets y ajustes de saldos.', icon: 'tabler-wallet' },
  { key: 'payslips', title: 'Nómina', description: 'Administración y entrega digital de recibos de nómina.', icon: 'tabler-users-group' },
  { key: 'cash-closure', title: 'Cierre de caja', description: 'Visualización y control administrativo de cierres de caja.', icon: 'tabler-device-desktop-analytics' },
  { key: 'cash-closure-user', title: 'Cierre de caja Usuarios', description: 'Gestión de cierres específicos para cajeros del sistema.', icon: 'tabler-lock-square' },
  { key: 'income-statement', title: 'Estado de Resultados', description: 'Reportes financieros consolidados de ganancias y pérdidas.', icon: 'tabler-presentation' },
  { key: 'expense-expenses', title: 'Gastos', description: 'Seguimiento de egresos, egresos rápidos y recurrentes.', icon: 'tabler-trending-down' },
  { key: 'balance-general', title: 'Balance General', description: 'Estado general de situación financiera y activos.', icon: 'tabler-report' },
  { key: 'furnitures-list', title: 'Mobiliario', description: 'Control de activos fijos, estantería y mobiliario.', icon: 'tabler-armchair' },
  { key: 'loans-list', title: 'Préstamos', description: 'Registro y seguimiento de préstamos y amortizaciones.', icon: 'tabler-cash-banknote' },
]

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings?only=enabled_finance_views,profitability_calculation_type')
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
    console.error("Error cargando configuración de Finanzas:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

const toggleFinanceView = async (key) => {
  if (isSaving.value) return
  
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

const changeCalculationType = async (type) => {
  if (isSaving.value || profitabilityCalculationType.value === type) return
  
  previousCalculationType = profitabilityCalculationType.value
  profitabilityCalculationType.value = type
  await updateSettings()
}

const updateSettings = async () => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_finance_views: enabledFinanceViews.value,
      profitability_calculation_type: profitabilityCalculationType.value
    })
    await brandingStore.fetchSettings()
    toast.success("Configuración de Finanzas actualizada exitosamente")
    
    previousEnabledViews = [...enabledFinanceViews.value]
    previousCalculationType = profitabilityCalculationType.value
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
    
    // Rollback al estado anterior en caso de fallo de red/servidor
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
  <div class="finances-config-container position-relative">
    <!-- Barra de progreso lineal para indicar guardado en segundo plano -->
    <div class="progress-bar-container">
      <VProgressLinear
        v-if="isSaving"
        color="primary"
        indeterminate
        height="4"
        class="position-absolute top-0 start-0 z-index-10"
      />
    </div>

    <!-- ESTRUCTURA DE CARGA (Skeleton Loader) -->
    <div v-if="isLoading">
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VSkeletonLoader type="list-item-avatar-two-line" class="mb-4" />
          <VRow>
            <VCol cols="12" md="6" v-for="i in 2" :key="i">
              <VSkeletonLoader type="image" height="120" class="rounded-lg" />
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>

      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VSkeletonLoader type="list-item-avatar-two-line" class="mb-4" />
          <VRow>
            <VCol cols="12" sm="6" md="3" v-for="i in 8" :key="i">
              <VSkeletonLoader type="card" height="110" class="rounded-lg" />
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>
    </div>

    <!-- CONTENIDO DE LA VISTA -->
    <div v-else :class="{ 'disabled-interaction': isSaving }">
      <!-- Configuración del Tipo de Cálculo de Rentabilidad -->
      <VCard class="mb-6 rounded-lg border shadow-sm main-config-card">
        <VCardItem class="py-5">
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
            <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
              <VIcon icon="tabler-calculator" class="text-primary" size="26" />
              Fórmula de Rentabilidad y Precios de Venta
            </VCardTitle>
            <span v-if="isSaving" class="text-xs text-primary font-weight-bold animate-pulse">
              Guardando cambios...
            </span>
          </div>
          <p class="text-caption text-medium-emphasis mb-6">
            Selecciona la fórmula matemática y estructura de cálculo financiero a utilizar para definir los precios de venta a partir de los costos unitarios del inventario.
          </p>

          <VRow>
            <VCol cols="12" md="6">
              <VCard
                variant="outlined"
                class="rounded-lg cursor-pointer transition-all h-100 premium-selection-card"
                :class="profitabilityCalculationType === 'simple' ? 'active-card border-primary' : 'inactive-card'"
                @click="changeCalculationType('simple')"
              >
                <VCardItem class="py-5 px-5">
                  <div class="d-flex align-start gap-4">
                    <VAvatar :color="profitabilityCalculationType === 'simple' ? 'primary' : 'secondary'" variant="tonal" size="48" class="rounded-lg transition-all">
                      <VIcon icon="tabler-percentage" size="24" />
                    </VAvatar>
                    <div class="d-flex flex-column gap-1">
                      <span class="font-weight-black text-body-1 text-high-emphasis">Fórmula Simple (Markup)</span>
                      <span class="text-xs text-primary font-weight-bold">PV = Costo * (1 + Margen / 100)</span>
                      <p class="text-caption text-medium-emphasis mt-2 mb-0">
                        Calcula el precio de venta añadiendo un porcentaje directo sobre el costo base del producto. Ideal para flujos simples y rápidos.
                      </p>
                    </div>
                  </div>
                </VCardItem>
              </VCard>
            </VCol>

            <VCol cols="12" md="6">
              <VCard
                variant="outlined"
                class="rounded-lg cursor-pointer transition-all h-100 premium-selection-card"
                :class="profitabilityCalculationType === 'compound' ? 'active-card border-primary' : 'inactive-card'"
                @click="changeCalculationType('compound')"
              >
                <VCardItem class="py-5 px-5">
                  <div class="d-flex align-start gap-4">
                    <VAvatar :color="profitabilityCalculationType === 'compound' ? 'primary' : 'secondary'" variant="tonal" size="48" class="rounded-lg transition-all">
                      <VIcon icon="tabler-math-function" size="24" />
                    </VAvatar>
                    <div class="d-flex flex-column gap-1">
                      <span class="font-weight-black text-body-1 text-high-emphasis">Fórmula Compuesta (Margen Financiero)</span>
                      <span class="text-xs text-primary font-weight-bold">PV = (Costo USA * (1 + TAX) + Envío + Embalaje + Gastos Fijos) / (1 - Margen Ganancia)</span>
                      <p class="text-caption text-medium-emphasis mt-2 mb-0">
                        Cálculo estructurado multi-variable que descuenta impuestos de importación, flete, embalaje y margen de gastos para garantizar el porcentaje de beneficio neto deseado.
                      </p>
                    </div>
                  </div>
                </VCardItem>
              </VCard>
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>

      <!-- Tarjeta Principal de Configuración de Vistas de Finanzas -->
      <VCard class="mb-6 rounded-lg border shadow-sm main-config-card">
        <VCardItem class="py-5">
          <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
            <VIcon icon="tabler-chart-bar" class="text-primary" size="26" />
            Configuración de Vistas de Finanzas
          </VCardTitle>
          <p class="text-caption text-medium-emphasis mb-6">
            Habilita o deshabilita los módulos y vistas financieras en la barra de navegación lateral. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios.
          </p>

          <VRow>
            <VCol v-for="view in availableFinanceViews" :key="view.key" cols="12" sm="6" md="3" class="d-flex">
              <VCard
                variant="outlined"
                class="rounded-lg cursor-pointer transition-all w-100 premium-selection-card d-flex flex-column justify-between"
                :class="enabledFinanceViews.includes(view.key) ? 'active-card border-primary' : 'inactive-card'"
                @click="toggleFinanceView(view.key)"
              >
                <VCardItem class="py-4 px-4 flex-grow-1 d-flex flex-column justify-space-between">
                  <div>
                    <div class="d-flex align-center justify-space-between w-100 mb-3">
                      <div class="d-flex align-center">
                        <VAvatar :color="enabledFinanceViews.includes(view.key) ? 'primary' : 'secondary'" variant="tonal" size="32" class="me-2 transition-all">
                          <VIcon :icon="view.icon" size="18" />
                        </VAvatar>
                        <span class="font-weight-black text-body-2 text-truncate" :class="enabledFinanceViews.includes(view.key) ? 'text-high-emphasis' : 'text-disabled'" style="max-width: 110px;">
                          {{ view.title }}
                        </span>
                      </div>
                      <VSwitch
                        :model-value="enabledFinanceViews.includes(view.key)"
                        density="compact"
                        hide-details
                        color="primary"
                        :disabled="isSaving"
                        @click.stop
                        @update:model-value="toggleFinanceView(view.key)"
                      />
                    </div>
                    <p class="text-caption text-medium-emphasis mb-0 leading-tight description-text">
                      {{ view.description }}
                    </p>
                  </div>
                </VCardItem>
              </VCard>
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.finances-config-container {
  min-height: 200px;
}

.progress-bar-container {
  height: 4px;
  position: relative;
  width: 100%;
}

.disabled-interaction {
  pointer-events: none;
  opacity: 0.85;
}

/* Efectos de Hover y Selección Premium */
.premium-selection-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
  border-width: 1px !important;
}

.premium-selection-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.08) !important;
  border-color: rgba(var(--v-theme-primary), 0.5) !important;
}

.active-card {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
  border-color: rgb(var(--v-theme-primary)) !important;
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.05) !important;
}

.inactive-card {
  opacity: 0.65;
  border-color: rgba(var(--v-theme-on-surface), 0.12) !important;
}

.inactive-card:hover {
  opacity: 0.9;
}

.description-text {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.75rem !important;
}

.animate-pulse {
  animation: pulse 1.5s infinite alternate;
}

@keyframes pulse {
  from { opacity: 0.6; }
  to { opacity: 1; }
}
</style>

